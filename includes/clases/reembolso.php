<?php
/**
 * Creating a WooCommerce refund out of a withdrawal request.
 *
 * A withdrawal declaration and a refund are two different things: the first is
 * the consumer exercising a right, the second is the merchant moving money.
 * The plugin therefore never refunds anything on its own — this screen only
 * prepares the figures the request implies and hands them to WooCommerce when
 * the merchant presses the button.
 *
 * The amounts come from the line items the customer actually declared, using
 * the per-line quantities stored since 0.7.0, and are always capped by what is
 * still refundable on the order, so a refund issued by hand beforehand can
 * never be duplicated here.
 *
 * @package APG_Withdrawal_For_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns whether the current user may turn a withdrawal request into a refund.
 * Refunds move money, so this is deliberately stricter than the capability the
 * rest of the plugin uses: it asks for the same capability WooCommerce itself
 * requires to refund an order.
 *
 * @return bool
 */
function apg_withdrawal_user_can_refund() {
	return current_user_can( 'edit_shop_orders' ) || current_user_can( 'manage_woocommerce' );
}

/**
 * Builds the refund the request implies, without creating anything.
 *
 * For a full-order withdrawal every line item still refundable is included,
 * plus the shipping the consumer paid — Article 13(1) of Directive 2011/83/EU
 * requires the standard delivery cost to be reimbursed as well. For a partial
 * withdrawal only the declared lines are included, each capped at the units
 * that have not been refunded yet.
 *
 * @param int $post_id Withdrawal request post ID.
 * @return array{order:WC_Order|false, line_items:array, amount:float, currency:string, reason:string, blocked:string}
 */
function apg_withdrawal_build_refund_plan( $post_id ) {
	$plan = array(
		'order'      => false,
		'line_items' => array(),
		'amount'     => 0.0,
		'currency'   => '',
		'reason'     => '',
		'blocked'    => '',
	);

	$order_id = absint( get_post_meta( $post_id, '_apg_withdrawal_wc_order_id', true ) );
	$order    = $order_id ? apg_withdrawal_get_order( $order_id ) : false;

	if ( ! $order ) {
		$plan['blocked'] = __( 'This request is not linked to a WooCommerce order.', 'apg-withdrawal-for-woocommerce' );
		return $plan;
	}

	$plan['order']    = $order;
	$plan['currency'] = $order->get_currency();
	$plan['reason']   = sprintf(
		/* translators: %d: withdrawal request log ID. */
		__( 'Withdrawal request #%d', 'apg-withdrawal-for-woocommerce' ),
		$post_id
	);

	if ( get_post_meta( $post_id, '_apg_withdrawal_refund_id', true ) ) {
		$plan['blocked'] = __( 'A refund has already been created from this request.', 'apg-withdrawal-for-woocommerce' );
		return $plan;
	}

	$scope      = 'partial' === get_post_meta( $post_id, '_apg_withdrawal_scope', true ) ? 'partial' : 'full';
	$declared   = (array) get_post_meta( $post_id, '_apg_withdrawal_products', true );
	$declared   = array_map( 'strval', $declared );
	$quantities = apg_withdrawal_get_request_quantities( $post_id );
	$decimals   = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2;
	$total      = 0.0;

	foreach ( $order->get_items() as $item_id => $item ) {
		$item_id = (string) $item_id;

		if ( 'partial' === $scope && ! in_array( $item_id, $declared, true ) ) {
			continue;
		}

		$ordered = max( 0, (int) $item->get_quantity() );

		if ( ! $ordered ) {
			continue;
		}

		$refunded_qty  = abs( (int) $order->get_qty_refunded_for_item( $item_id ) );
		$remaining_qty = max( 0, $ordered - $refunded_qty );

		if ( ! $remaining_qty ) {
			continue;
		}

		$wanted = 'partial' === $scope && isset( $quantities[ $item_id ] )
			? (int) $quantities[ $item_id ]
			: $ordered;
		$qty    = max( 0, min( $wanted, $remaining_qty ) );

		if ( ! $qty ) {
			continue;
		}

		// Money already given back for this line bounds what is left to refund,
		// so a manual partial refund cannot be handed out twice.
		$line_total     = (float) $item->get_total();
		$line_refunded  = abs( (float) $order->get_total_refunded_for_item( $item_id ) );
		$line_remaining = max( 0, $line_total - $line_refunded );
		$refund_total   = min( $line_remaining, round( ( $line_total / $ordered ) * $qty, $decimals ) );

		$refund_tax = array();
		$taxes      = $item->get_taxes();

		if ( ! empty( $taxes['total'] ) && is_array( $taxes['total'] ) ) {
			foreach ( $taxes['total'] as $tax_id => $tax_total ) {
				$tax_total     = (float) $tax_total;
				$tax_refunded  = abs( (float) $order->get_tax_refunded_for_item( $item_id, $tax_id ) );
				$tax_remaining = max( 0, $tax_total - $tax_refunded );
				$tax_amount    = min( $tax_remaining, round( ( $tax_total / $ordered ) * $qty, $decimals ) );

				if ( $tax_amount > 0 ) {
					$refund_tax[ $tax_id ] = $tax_amount;
					$total                += $tax_amount;
				}
			}
		}

		if ( $refund_total <= 0 && empty( $refund_tax ) ) {
			continue;
		}

		$total += $refund_total;

		$plan['line_items'][ $item_id ] = array(
			'name'         => $item->get_name(),
			'qty'          => $qty,
			'refund_total' => $refund_total,
			'refund_tax'   => $refund_tax,
		);
	}

	// Withdrawing from the whole contract gives the standard delivery cost back too.
	if ( 'full' === $scope ) {
		foreach ( $order->get_items( 'shipping' ) as $shipping_id => $shipping ) {
			$shipping_total     = (float) $shipping->get_total();
			$shipping_refunded  = abs( (float) $order->get_total_refunded_for_item( $shipping_id, 'shipping' ) );
			$shipping_remaining = max( 0, $shipping_total - $shipping_refunded );

			$refund_tax = array();
			$taxes      = $shipping->get_taxes();

			if ( ! empty( $taxes['total'] ) && is_array( $taxes['total'] ) ) {
				foreach ( $taxes['total'] as $tax_id => $tax_total ) {
					$tax_total     = (float) $tax_total;
					$tax_refunded  = abs( (float) $order->get_tax_refunded_for_item( $shipping_id, $tax_id, 'shipping' ) );
					$tax_remaining = max( 0, $tax_total - $tax_refunded );

					if ( $tax_remaining > 0 ) {
						$refund_tax[ $tax_id ] = round( $tax_remaining, $decimals );
						$total                += $refund_tax[ $tax_id ];
					}
				}
			}

			if ( $shipping_remaining <= 0 && empty( $refund_tax ) ) {
				continue;
			}

			$total += $shipping_remaining;

			$plan['line_items'][ $shipping_id ] = array(
				'name'         => $shipping->get_name(),
				'qty'          => 0,
				'refund_total' => round( $shipping_remaining, $decimals ),
				'refund_tax'   => $refund_tax,
			);
		}
	}

	$plan['amount'] = round( $total, $decimals );

	if ( empty( $plan['line_items'] ) || $plan['amount'] <= 0 ) {
		$plan['blocked'] = __( 'There is nothing left to refund on this order.', 'apg-withdrawal-for-woocommerce' );
	}

	/**
	 * Filters the refund a withdrawal request implies before it is shown to the
	 * merchant or handed to WooCommerce. Use it to add fees, exclude shipping or
	 * apply a deduction for diminished value of the returned goods.
	 *
	 * @param array $plan    Computed refund plan.
	 * @param int   $post_id Withdrawal request post ID.
	 */
	return (array) apply_filters( 'apg_withdrawal_refund_plan', $plan, $post_id );
}

/**
 * Describes the gateway that took the payment, so the merchant is told where
 * the money is actually going instead of being asked about an anonymous
 * "payment gateway", and so the Spanish rails can carry their own advice.
 *
 * Redsys — which is also what Bizum runs on for e-commerce — is recognised by
 * the gateway id, because every WooCommerce implementation of it (José Conti's,
 * Codection's, the official unified module) prefixes its id accordingly. Bizum
 * appears either as its own gateway id or as the method title of a Redsys
 * order, so both are checked.
 *
 * @param WC_Order $order WooCommerce order.
 * @return array{id:string, title:string, supports_refunds:bool, is_redsys:bool, is_bizum:bool}
 */
function apg_withdrawal_get_gateway_info( $order ) {
	$info = array(
		'id'               => (string) $order->get_payment_method(),
		'title'            => (string) $order->get_payment_method_title(),
		'supports_refunds' => false,
		'is_redsys'        => false,
		'is_bizum'         => false,
	);

	if ( function_exists( 'wc_get_payment_gateway_by_order' ) ) {
		$gateway = wc_get_payment_gateway_by_order( $order );

		if ( $gateway && is_callable( array( $gateway, 'supports' ) ) ) {
			$info['supports_refunds'] = (bool) $gateway->supports( 'refunds' );

			if ( '' === $info['title'] && isset( $gateway->title ) ) {
				$info['title'] = (string) $gateway->title;
			}
		}
	}

	$haystack = strtolower( $info['id'] . ' ' . $info['title'] );

	foreach ( array( 'redsys', 'sermepa', 'servired' ) as $needle ) {
		if ( false !== strpos( $haystack, $needle ) ) {
			$info['is_redsys'] = true;
			break;
		}
	}

	$info['is_bizum'] = false !== strpos( $haystack, 'bizum' );

	if ( $info['is_bizum'] ) {
		$info['is_redsys'] = true;
	}

	if ( '' === $info['title'] ) {
		$info['title'] = __( 'the payment gateway', 'apg-withdrawal-for-woocommerce' );
	}

	/**
	 * Filters the payment-gateway description used by the refund panel. Useful
	 * for gateways whose id does not reveal the underlying rail.
	 *
	 * @param array    $info  Resolved gateway information.
	 * @param WC_Order $order WooCommerce order.
	 */
	return (array) apply_filters( 'apg_withdrawal_gateway_info', $info, $order );
}

/**
 * Reports whether the gateway that took the payment can refund it back
 * programmatically, which decides if the "refund through the gateway" option is
 * offered at all.
 *
 * @param WC_Order $order WooCommerce order.
 * @return bool
 */
function apg_withdrawal_gateway_supports_refunds( $order ) {
	$info = apg_withdrawal_get_gateway_info( $order );

	return $info['supports_refunds'];
}

/**
 * Registers the refund metabox on the withdrawal request screen.
 *
 * @return void
 */
function apg_withdrawal_add_refund_metabox() {
	if ( ! apg_withdrawal_user_can_refund() ) {
		return;
	}

	add_meta_box(
		'apg_withdrawal_refund',
		__( 'Refund', 'apg-withdrawal-for-woocommerce' ),
		'apg_withdrawal_render_refund_metabox',
		'apg_withdrawal',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes', 'apg_withdrawal_add_refund_metabox' );

/**
 * Renders the refund metabox: either the refund already created from this
 * request, the reason why none can be created, or the breakdown plus the button
 * that creates it.
 *
 * @param WP_Post $post Withdrawal request post.
 * @return void
 */
function apg_withdrawal_render_refund_metabox( $post ) {
	$refund_id = absint( get_post_meta( $post->ID, '_apg_withdrawal_refund_id', true ) );

	if ( $refund_id ) {
		$order_id = absint( get_post_meta( $post->ID, '_apg_withdrawal_wc_order_id', true ) );
		$order    = $order_id ? apg_withdrawal_get_order( $order_id ) : false;
		$url      = $order && is_callable( array( $order, 'get_edit_order_url' ) ) ? $order->get_edit_order_url() : '';

		echo '<p>' . esc_html(
			sprintf(
				/* translators: %d: WooCommerce refund ID. */
				__( 'Refund #%d was created from this request.', 'apg-withdrawal-for-woocommerce' ),
				$refund_id
			)
		) . '</p>';

		if ( $url ) {
			printf( '<p><a href="%1$s">%2$s</a></p>', esc_url( $url ), esc_html__( 'View the order', 'apg-withdrawal-for-woocommerce' ) );
		}

		return;
	}

	$plan = apg_withdrawal_build_refund_plan( $post->ID );

	if ( '' !== $plan['blocked'] ) {
		echo '<p>' . esc_html( $plan['blocked'] ) . '</p>';
		return;
	}

	echo '<p>' . esc_html__( 'Based on the products the customer declared and on what is still refundable on the order:', 'apg-withdrawal-for-woocommerce' ) . '</p>';
	echo '<ul class="apg-withdrawal-refund-lines">';

	foreach ( $plan['line_items'] as $line ) {
		$label = $line['qty'] > 0
			? sprintf(
				/* translators: 1: product name, 2: quantity being refunded. */
				__( '%1$s x %2$d', 'apg-withdrawal-for-woocommerce' ),
				$line['name'],
				$line['qty']
			)
			: $line['name'];

		printf(
			'<li>%1$s — %2$s</li>',
			esc_html( $label ),
			wp_kses_post( wc_price( $line['refund_total'] + array_sum( $line['refund_tax'] ), array( 'currency' => $plan['currency'] ) ) )
		);
	}

	echo '</ul>';

	printf(
		'<p><strong>%1$s %2$s</strong></p>',
		esc_html__( 'Total to refund:', 'apg-withdrawal-for-woocommerce' ),
		wp_kses_post( wc_price( $plan['amount'], array( 'currency' => $plan['currency'] ) ) )
	);

	$gateway = apg_withdrawal_get_gateway_info( $plan['order'] );

	// The metabox is rendered inside WordPress's own post form, and a form cannot
	// be nested inside another: the browser drops the inner tag, the button ends
	// up submitting the post form, and the duplicated `action` field hijacks it —
	// which is what sent this screen to edit.php. So the controls live here and
	// declare, through the HTML5 `form` attribute, that they belong to a form
	// printed after the post form closes (see the admin_footer hook below).
	apg_withdrawal_refund_form_context( $post->ID );
	$form_id = 'apg-withdrawal-refund-form';
	?>
	<div class="apg-withdrawal-refund-actions">
		<?php if ( $gateway['supports_refunds'] ) : ?>
		<p>
			<label for="apg_withdrawal_refund_payment">
				<input type="checkbox" id="apg_withdrawal_refund_payment" name="refund_payment" value="1" form="<?php echo esc_attr( $form_id ); ?>">
				<?php
				printf(
					/* translators: %s: payment gateway name, e.g. "Redsys" or "Bizum". */
					esc_html__( 'Send the money back through %s', 'apg-withdrawal-for-woocommerce' ),
					esc_html( $gateway['title'] )
				);
				?>
			</label>
		</p>
		<?php else : ?>
		<p class="description">
			<?php
			printf(
				/* translators: %s: payment gateway name. */
				esc_html__( '%s cannot return the money from here, so the refund will only be recorded in WooCommerce. Issue the payment in your bank or gateway back office.', 'apg-withdrawal-for-woocommerce' ),
				esc_html( $gateway['title'] )
			);
			?>
		</p>
		<?php endif; ?>

		<?php if ( $gateway['is_redsys'] ) : ?>
		<p class="description">
			<?php
			echo esc_html(
				$gateway['is_bizum']
					? __( 'Bizum runs on Redsys, which accepts full and partial refunds. When the money reaches the customer depends on their bank and usually takes between 2 and 31 days; the 14-day limit in Article 13(1) of Directive 2011/83/EU applies to you issuing the refund, not to their bank crediting it.', 'apg-withdrawal-for-woocommerce' )
					: __( 'Redsys accepts full and partial refunds. When the money reaches the customer depends on their bank and usually takes between 2 and 31 days; the 14-day limit in Article 13(1) of Directive 2011/83/EU applies to you issuing the refund, not to their bank crediting it.', 'apg-withdrawal-for-woocommerce' )
			);
			?>
		</p>
		<?php endif; ?>

		<p>
			<label for="apg_withdrawal_restock_items">
				<input type="checkbox" id="apg_withdrawal_restock_items" name="restock_items" value="1" form="<?php echo esc_attr( $form_id ); ?>">
				<?php esc_html_e( 'Restock the returned products', 'apg-withdrawal-for-woocommerce' ); ?>
			</label>
		</p>

		<p>
			<button type="submit" class="button button-primary" form="<?php echo esc_attr( $form_id ); ?>"><?php esc_html_e( 'Create the refund', 'apg-withdrawal-for-woocommerce' ); ?></button>
		</p>
		<p class="description"><?php esc_html_e( 'Leaving the boxes unticked records the refund in WooCommerce without moving any money and without touching stock, so you can process the payment yourself.', 'apg-withdrawal-for-woocommerce' ); ?></p>
	</div>
	<?php
}

/**
 * Remembers which withdrawal request the refund panel was rendered for, so the
 * footer knows whether it has to print the form the panel's controls point at.
 *
 * @param int|null $post_id Request ID to store, or null to read the stored one.
 * @return int Stored request ID, or 0 when the panel did not render.
 */
function apg_withdrawal_refund_form_context( $post_id = null ) {
	static $context = 0;

	if ( null !== $post_id ) {
		$context = absint( $post_id );
	}

	return $context;
}

/**
 * Prints the form the refund panel's controls belong to.
 *
 * It has to live out here, after WordPress has closed its own post form, because
 * HTML forbids nesting one form inside another: placing it in the metabox made
 * the browser discard the tag and hand the button — and the `action` field — to
 * the post form instead. The controls stay in the metabox and are wired to this
 * form through their `form` attribute.
 *
 * @return void
 */
function apg_withdrawal_render_refund_form() {
	$post_id = apg_withdrawal_refund_form_context();

	if ( ! $post_id ) {
		return;
	}

	printf(
		'<form id="apg-withdrawal-refund-form" method="post" action="%1$s">
			<input type="hidden" name="action" value="apg_withdrawal_create_refund">
			<input type="hidden" name="request_id" value="%2$d">
			%3$s
		</form>',
		esc_url( admin_url( 'admin-post.php' ) ),
		absint( $post_id ),
		wp_nonce_field( 'apg_withdrawal_create_refund_' . $post_id, 'apg_withdrawal_refund_nonce', true, false ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_nonce_field() returns escaped markup.
	);
}
add_action( 'admin_footer', 'apg_withdrawal_render_refund_form' );

/**
 * Creates the refund and returns to the request screen with the outcome.
 *
 * @return void
 */
function apg_withdrawal_handle_create_refund() {
	$post_id = isset( $_POST['request_id'] ) ? absint( wp_unslash( $_POST['request_id'] ) ) : 0;

	if ( ! $post_id || ! isset( $_POST['apg_withdrawal_refund_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['apg_withdrawal_refund_nonce'] ) ), 'apg_withdrawal_create_refund_' . $post_id ) ) {
		wp_die( esc_html__( 'Security check failed.', 'apg-withdrawal-for-woocommerce' ) );
	}

	if ( ! apg_withdrawal_user_can_refund() || 'apg_withdrawal' !== get_post_type( $post_id ) ) {
		wp_die( esc_html__( 'You do not have permission to refund withdrawal requests.', 'apg-withdrawal-for-woocommerce' ) );
	}

	$plan = apg_withdrawal_build_refund_plan( $post_id );

	if ( '' !== $plan['blocked'] || ! function_exists( 'wc_create_refund' ) ) {
		apg_withdrawal_redirect_after_refund( $post_id, 'error' );
	}

	$line_items = array();

	foreach ( $plan['line_items'] as $item_id => $line ) {
		$line_items[ $item_id ] = array(
			'qty'          => $line['qty'],
			'refund_total' => $line['refund_total'],
			'refund_tax'   => $line['refund_tax'],
		);
	}

	$refund = wc_create_refund(
		array(
			'amount'         => $plan['amount'],
			'reason'         => $plan['reason'],
			'order_id'       => $plan['order']->get_id(),
			'line_items'     => $line_items,
			'refund_payment' => ! empty( $_POST['refund_payment'] ),
			'restock_items'  => ! empty( $_POST['restock_items'] ),
		)
	);

	if ( is_wp_error( $refund ) || ! $refund ) {
		// A gateway that refuses the refund says why — Redsys, for instance,
		// answers with a SIS error code. Swallowing it into a generic "could not
		// be created" leaves the merchant with nothing to act on, so the message
		// is carried over to the notice.
		apg_withdrawal_store_refund_error(
			$post_id,
			is_wp_error( $refund ) ? $refund->get_error_message() : ''
		);
		apg_withdrawal_redirect_after_refund( $post_id, 'error' );
	}

	update_post_meta( $post_id, '_apg_withdrawal_refund_id', $refund->get_id() );

	$plan['order']->add_order_note(
		sprintf(
			/* translators: 1: refund amount, 2: withdrawal request log ID. */
			__( 'Refund of %1$s created from withdrawal request #%2$d.', 'apg-withdrawal-for-woocommerce' ),
			wp_strip_all_tags( wc_price( $plan['amount'], array( 'currency' => $plan['currency'] ) ) ),
			$post_id
		),
		0,
		false
	);

	apg_withdrawal_redirect_after_refund( $post_id, 'created' );
}
add_action( 'admin_post_apg_withdrawal_create_refund', 'apg_withdrawal_handle_create_refund' );

/**
 * Parks the gateway's own failure message for the next page load. It travels in
 * a short-lived transient rather than in the URL because it is arbitrary text
 * coming from a third party and can be long.
 *
 * @param int    $post_id Withdrawal request post ID.
 * @param string $message Gateway error message.
 * @return void
 */
function apg_withdrawal_store_refund_error( $post_id, $message ) {
	if ( '' === trim( (string) $message ) ) {
		return;
	}

	set_transient( 'apg_wfw_refund_error_' . get_current_user_id() . '_' . absint( $post_id ), (string) $message, 5 * MINUTE_IN_SECONDS );
}

/**
 * Retrieves and clears the parked gateway failure message.
 *
 * @param int $post_id Withdrawal request post ID.
 * @return string
 */
function apg_withdrawal_take_refund_error( $post_id ) {
	$key     = 'apg_wfw_refund_error_' . get_current_user_id() . '_' . absint( $post_id );
	$message = get_transient( $key );

	if ( false === $message ) {
		return '';
	}

	delete_transient( $key );

	return (string) $message;
}

/**
 * Sends the merchant back to the request screen carrying the result flag.
 *
 * @param int    $post_id Withdrawal request post ID.
 * @param string $result  Either `created` or `error`.
 * @return void
 */
function apg_withdrawal_redirect_after_refund( $post_id, $result ) {
	$target = get_edit_post_link( $post_id, 'redirect' );

	// get_edit_post_link() returns null when the post type has no edit link or
	// the user lost the capability mid-request; falling back keeps the merchant
	// on the request instead of being dropped somewhere generic.
	if ( ! $target ) {
		$target = admin_url( 'post.php?post=' . absint( $post_id ) . '&action=edit' );
	}

	wp_safe_redirect( add_query_arg( 'apg_withdrawal_refund_result', sanitize_key( $result ), $target ) );
	exit;
}

/**
 * Reports the outcome of a refund attempt on the request screen.
 *
 * @return void
 */
function apg_withdrawal_refund_notice() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	if ( ! $screen || 'apg_withdrawal' !== $screen->post_type ) {
		return;
	}

	if ( empty( $_GET['apg_withdrawal_refund_result'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only feedback flag set by the redirect above.
		return;
	}

	$result = sanitize_key( wp_unslash( $_GET['apg_withdrawal_refund_result'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- See above.

	$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading the screen's own post ID.

	if ( 'created' === $result ) {
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html__( 'The refund was created in WooCommerce.', 'apg-withdrawal-for-woocommerce' )
		);
		return;
	}

	$reason = $post_id ? apg_withdrawal_take_refund_error( $post_id ) : '';

	printf(
		'<div class="notice notice-error is-dismissible"><p>%1$s%2$s</p></div>',
		esc_html__( 'The refund could not be created. Check the order in WooCommerce.', 'apg-withdrawal-for-woocommerce' ),
		'' !== $reason ? ' ' . esc_html(
			sprintf(
				/* translators: %s: error message returned by the payment gateway. */
				__( 'The payment gateway replied: %s', 'apg-withdrawal-for-woocommerce' ),
				$reason
			)
		) : ''
	);
}
add_action( 'admin_notices', 'apg_withdrawal_refund_notice' );
