<?php
/**
 * Helpers WooCommerce.
 *
 * @package WC_APG_Withdrawal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Retrieves a WooCommerce order object from an order reference value.
 *
 * @param mixed $order_ref Order ID or numeric string reference.
 * @return WC_Order|bool WooCommerce order object or false on failure.
 */
function apg_withdrawal_get_order( $order_ref ) {
	if ( ! function_exists( 'wc_get_order' ) ) {
		return false;
	}

	$order_id = absint( $order_ref );

	return $order_id ? wc_get_order( $order_id ) : false;
}

/**
 * Returns a human-readable label for an order suitable for use in a select option.
 *
 * @param WC_Order $order WooCommerce order object.
 * @return string Formatted label including order number, date and total.
 */
function apg_withdrawal_get_order_option_label( $order ) {
	$date = is_callable( array( $order, 'get_date_created' ) ) && $order->get_date_created() ? $order->get_date_created()->date_i18n( get_option( 'date_format' ) ) : '';
	$total = is_callable( array( $order, 'get_formatted_order_total' ) ) ? wp_strip_all_tags( html_entity_decode( $order->get_formatted_order_total(), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) : '';

	return trim( sprintf( '#%1$s %2$s %3$s', $order->get_order_number(), $date ? ' - ' . $date : '', $total ? ' - ' . $total : '' ) );
}

/**
 * Returns all WooCommerce orders for a given customer identified by email or user ID.
 *
 * @param string $email   Customer billing email address.
 * @param int    $user_id WordPress user ID.
 * @return WC_Order[] Array of WooCommerce order objects.
 */
function apg_withdrawal_get_customer_orders( $email = '', $user_id = 0 ) {
	if ( ! function_exists( 'wc_get_orders' ) ) {
		return array();
	}

	$args = array(
		'limit'   => -1,
		'orderby' => 'date',
		'order'   => 'DESC',
		'status'  => array_keys( wc_get_order_statuses() ),
	);

	if ( $user_id ) {
		$args['customer_id'] = absint( $user_id );
	} elseif ( $email ) {
		$args['billing_email'] = sanitize_email( $email );
	} else {
		return array();
	}

	return wc_get_orders( $args );
}

/**
 * Returns an associative array of line-item ID to product label for an order.
 *
 * @param WC_Order|bool $order WooCommerce order object or false.
 * @return array Associative array of item_id => label string.
 */
function apg_withdrawal_get_order_products( $order ) {
	$products = array();

	if ( ! $order || ! is_callable( array( $order, 'get_items' ) ) ) {
		return $products;
	}

	foreach ( $order->get_items() as $item_id => $item ) {
		$products[ (string) $item_id ] = sprintf(
			/* translators: 1: product name, 2: quantity. */
			__( '%1$s x %2$d', 'wc-apg-withdrawal' ),
			$item->get_name(),
			$item->get_quantity()
		);
	}

	return $products;
}

/**
 * Builds a map of order ID to product labels for an array of orders.
 *
 * @param WC_Order[] $orders Array of WooCommerce order objects.
 * @return array Associative array of order_id => product labels array.
 */
function apg_withdrawal_get_orders_products_map( $orders ) {
	$map = array();

	foreach ( $orders as $order ) {
		if ( ! $order || ! is_callable( array( $order, 'get_id' ) ) ) {
			continue;
		}

		$map[ (string) $order->get_id() ] = apg_withdrawal_get_order_products( $order );
	}

	return $map;
}

/**
 * Returns the product labels for a set of selected line-item IDs within an order.
 *
 * @param WC_Order|bool $order            WooCommerce order object or false.
 * @param array         $selected_products Array of selected line-item IDs.
 * @return array Array of product label strings.
 */
function apg_withdrawal_get_selected_product_labels( $order, $selected_products ) {
	$labels   = array();
	$products = apg_withdrawal_get_order_products( $order );

	foreach ( $selected_products as $item_id ) {
		$item_id = (string) $item_id;

		if ( isset( $products[ $item_id ] ) ) {
			$labels[] = $products[ $item_id ];
		}
	}

	return $labels;
}

/**
 * Returns the deadline timestamp for a withdrawal request.
 *
 * @param WC_Order $order    WooCommerce order object.
 * @param array    $settings Plugin settings array.
 * @return array Associative array with keys 'timestamp', 'source' and 'date'.
 */
function apg_withdrawal_get_deadline_timestamp( $order, $settings ) {
	$source          = isset( $settings['deadline_source'] ) ? $settings['deadline_source'] : 'completed';
	$withdrawal_days = isset( $settings['withdrawal_days'] ) ? max( 1, absint( $settings['withdrawal_days'] ) ) : 14;
	$date            = false;

	if ( 'completed' === $source && is_callable( array( $order, 'get_date_completed' ) ) ) {
		$date = $order->get_date_completed();
	}

	if ( ! $date && is_callable( array( $order, 'get_date_created' ) ) ) {
		$date   = $order->get_date_created();
		$source = 'created';
	}

	if ( ! $date ) {
		return array(
			'timestamp' => 0,
			'source'    => $source,
			'date'      => '',
		);
	}

	return array(
		'timestamp' => $date->getTimestamp() + ( $withdrawal_days * DAY_IN_SECONDS ) + ( absint( $settings['grace_days'] ) * DAY_IN_SECONDS ),
		'source'    => $source,
		'date'      => gmdate( 'Y-m-d H:i:s', $date->getTimestamp() ),
	);
}

/**
 * Validates that an order reference belongs to the given customer and returns deadline info.
 *
 * @param mixed  $order_ref Order reference value.
 * @param string $email     Customer billing email address.
 * @param array  $settings  Plugin settings array.
 * @return array Associative array with keys 'valid', 'error', 'order', 'order_id', 'source', 'date' and 'expired_warning'.
 */
function apg_withdrawal_validate_order( $order_ref, $email, $settings ) {
	$response = array(
		'valid'           => false,
		'error'           => 'order',
		'order'           => false,
		'order_id'        => 0,
		'source'          => '',
		'date'            => '',
		'expired_warning' => false,
	);

	$order = apg_withdrawal_get_order( $order_ref );

	if ( ! $order ) {
		return $response;
	}

	$order_email       = is_callable( array( $order, 'get_billing_email' ) ) ? $order->get_billing_email() : '';
	$order_customer_id = is_callable( array( $order, 'get_customer_id' ) ) ? absint( $order->get_customer_id() ) : 0;
	$current_user_id   = absint( get_current_user_id() );

	$email_matches   = $order_email && strtolower( trim( $order_email ) ) === strtolower( trim( $email ) );
	$user_owns_order = $current_user_id && $order_customer_id && $current_user_id === $order_customer_id;

	// Also accept the WP account email: if the entered email belongs to a WP account that owns this order.
	if ( ! $email_matches && ! $user_owns_order && is_email( $email ) ) {
		$email_user    = get_user_by( 'email', $email );
		$email_user_id = $email_user instanceof WP_User ? absint( $email_user->ID ) : 0;
		if ( $email_user_id && $order_customer_id && $email_user_id === $order_customer_id ) {
			$email_matches = true;
		}
	}

	if ( ! $email_matches && ! $user_owns_order ) {
		return $response;
	}

	$deadline = apg_withdrawal_get_deadline_timestamp( $order, $settings );
	$expired  = $deadline['timestamp'] && time() > $deadline['timestamp'];

	$response['valid']           = true;
	$response['error']           = '';
	$response['order']           = $order;
	$response['order_id']        = $order->get_id();
	$response['source']          = $deadline['source'];
	$response['date']            = $deadline['date'];
	$response['expired_warning'] = $expired;

	return $response;
}

/**
 * Returns the withdrawal type configured for a product.
 *
 * @param int $product_id WooCommerce product ID.
 * @return string One of 'allowed', 'excluded', 'digital', 'personalized' or 'manual'.
 */
function apg_withdrawal_get_product_withdrawal_type( $product_id ) {
	$type = get_post_meta( absint( $product_id ), '_apg_withdrawal_type', true );

	$allowed = array( 'allowed', 'excluded', 'digital', 'personalized', 'manual' );

	return in_array( $type, $allowed, true ) ? $type : 'allowed';
}

/**
 * Returns the highest-priority withdrawal warning type found in any order line item.
 *
 * @param WC_Order|bool $order WooCommerce order object or false.
 * @return string Highest-priority warning type slug.
 */
function apg_withdrawal_get_order_warning_type( $order ) {
	if ( ! $order || ! is_callable( array( $order, 'get_items' ) ) ) {
		return 'allowed';
	}

	$priority = array(
		'excluded'     => 4,
		'digital'      => 3,
		'personalized' => 2,
		'manual'       => 1,
		'allowed'      => 0,
	);

	$highest = 'allowed';

	foreach ( $order->get_items() as $item ) {
		$product_id = is_callable( array( $item, 'get_product_id' ) ) ? $item->get_product_id() : 0;

		if ( ! $product_id ) {
			continue;
		}

		$item_type = apg_withdrawal_get_product_withdrawal_type( $product_id );

		if ( isset( $priority[ $item_type ] ) && $priority[ $item_type ] > $priority[ $highest ] ) {
			$highest = $item_type;
		}
	}

	return $highest;
}

/**
 * Builds a map of order ID to warning type for orders that have non-default warning types.
 *
 * @param WC_Order[]|object[] $orders   Array of WooCommerce order objects.
 * @param array               $settings Plugin settings array. When provided, expired orders are included.
 * @return array Associative array of order_id => warning type slug.
 */
function apg_withdrawal_get_orders_warning_map( $orders, $settings = array() ) {
	$map = array();

	foreach ( $orders as $order ) {
		if ( ! $order || ! is_callable( array( $order, 'get_id' ) ) ) {
			continue;
		}

		$type = apg_withdrawal_get_order_warning_type( $order );

		if ( 'allowed' === $type && ! empty( $settings ) ) {
			$deadline = apg_withdrawal_get_deadline_timestamp( $order, $settings );
			if ( $deadline['timestamp'] && time() > $deadline['timestamp'] ) {
				$type = 'expired';
			}
		}

		if ( 'allowed' !== $type ) {
			$map[ (string) $order->get_id() ] = $type;
		}
	}

	return $map;
}

/**
 * Adds a withdrawal request note to the linked WooCommerce order.
 *
 * @param WC_Order $order   WooCommerce order object.
 * @param int      $post_id Withdrawal request post ID.
 * @param string   $scope   Withdrawal scope ('full' or 'partial').
 * @param string   $details Optional additional details provided by the customer.
 * @return void
 */
function apg_withdrawal_add_order_note( $order, $post_id, $scope, $details ) {
	if ( ! $order || ! is_callable( array( $order, 'add_order_note' ) ) ) {
		return;
	}

	$scope_label = 'partial' === $scope ? __( 'Specific products only', 'wc-apg-withdrawal' ) : __( 'Full order', 'wc-apg-withdrawal' );

	$order->add_order_note(
		sprintf(
			/* translators: 1: scope label, 2: log id. */
			__( 'Withdrawal request received (%1$s). Log ID: #%2$d', 'wc-apg-withdrawal' ),
			$scope_label,
			$post_id
		),
		0,
		false
	);

	if ( $details ) {
		$order->add_order_note(
			sprintf(
				/* translators: %s customer details. */
				__( 'Withdrawal details: %s', 'wc-apg-withdrawal' ),
				$details
			),
			0,
			false
		);
	}
}

/**
 * Creates the withdrawal form page if the setting is enabled and no valid page exists yet.
 *
 * @return void
 */
function apg_withdrawal_maybe_create_page() {
	$settings = apg_withdrawal_get_settings();

	if ( ! empty( $settings['page_id'] ) && get_post( absint( $settings['page_id'] ) ) ) {
		$page = get_post( absint( $settings['page_id'] ) );

		if ( $page && 'withdrawal' === $page->post_name ) {
			wp_update_post(
				array(
					'ID'        => $page->ID,
					'post_name' => 'withdrawal-form',
				)
			);
		}

		return;
	}

	$page_id = wp_insert_post(
		array(
			'post_title'   => __( 'Withdrawal', 'wc-apg-withdrawal' ),
			'post_name'    => 'withdrawal-form',
			'post_content' => '<!-- wp:paragraph --><p>' . esc_html__( 'Use this page to exercise your right of withdrawal for WooCommerce orders.', 'wc-apg-withdrawal' ) . '</p><!-- /wp:paragraph --><!-- wp:shortcode -->[apg_withdrawal_form]<!-- /wp:shortcode -->',
			'post_status'  => 'publish',
			'post_type'    => 'page',
		),
		true
	);

	if ( ! is_wp_error( $page_id ) && $page_id ) {
		$settings['page_id'] = strval( $page_id );
		update_option( 'apg_withdrawal_settings', $settings );
	}
}

// ——————————————————————————————————
// Solicitudes activas y plazo
// ——————————————————————————————————

/**
 * Checks whether a WooCommerce order has any active (non-rejected) withdrawal request.
 *
 * @param int $order_id WooCommerce order ID.
 * @return bool True if at least one active request exists, false otherwise.
 */
function apg_withdrawal_order_has_active_request( $order_id ) {
	global $wpdb;

	$count = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Performance-critical single-row lookup
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} pm_order
			INNER JOIN {$wpdb->postmeta} pm_status ON pm_order.post_id = pm_status.post_id
			INNER JOIN {$wpdb->posts} p ON p.ID = pm_order.post_id
			WHERE pm_order.meta_key = '_apg_withdrawal_wc_order_id'
			AND pm_order.meta_value = %s
			AND pm_status.meta_key = '_apg_withdrawal_status'
			AND pm_status.meta_value IN ('pending','accepted','completed')
			AND p.post_type = 'apg_withdrawal'
			AND p.post_status = 'publish'",
			(string) absint( $order_id )
		)
	);

	return (int) $count > 0;
}

/**
 * Returns IDs of all WooCommerce orders that have at least one active withdrawal request.
 *
 * @return int[] Array of WooCommerce order IDs.
 */
function apg_withdrawal_get_order_ids_with_active_requests() {
	global $wpdb;

	$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Performance-critical single-row lookup
		"SELECT DISTINCT pm_order.meta_value
		FROM {$wpdb->postmeta} pm_order
		INNER JOIN {$wpdb->postmeta} pm_status ON pm_order.post_id = pm_status.post_id
		INNER JOIN {$wpdb->posts} p ON p.ID = pm_order.post_id
		WHERE pm_order.meta_key = '_apg_withdrawal_wc_order_id'
		AND pm_status.meta_key = '_apg_withdrawal_status'
		AND pm_status.meta_value IN ('pending','accepted','completed')
		AND p.post_type = 'apg_withdrawal'
		AND p.post_status = 'publish'"
	);

	return array_map( 'absint', $ids );
}

/**
 * Returns whether the withdrawal deadline for an order has not yet passed.
 *
 * @param WC_Order $order    WooCommerce order object.
 * @param array    $settings Plugin settings array.
 * @return bool True if still within the deadline or no deadline is set.
 */
function apg_withdrawal_order_is_within_deadline( $order, $settings ) {
	$deadline = apg_withdrawal_get_deadline_timestamp( $order, $settings );

	if ( ! $deadline['timestamp'] ) {
		return true;
	}

	return time() <= $deadline['timestamp'];
}

// ——————————————————————————————————
// Cambio de estado centralizado
// ——————————————————————————————————

/**
 * Changes the status of a withdrawal request and optionally sends a status email.
 *
 * @param int      $post_id    Withdrawal request post ID.
 * @param string   $new_status Target status slug ('pending', 'accepted', 'rejected' or 'completed').
 * @param int|null $user_id    ID of the user performing the action, or null for current user.
 * @return bool True on success, false if the status is invalid or unchanged.
 */
function apg_withdrawal_change_status( $post_id, $new_status, $user_id = null ) {
	$allowed = array( 'pending', 'accepted', 'rejected', 'completed' );

	if ( ! in_array( $new_status, $allowed, true ) ) {
		return false;
	}

	$old_status = get_post_meta( $post_id, '_apg_withdrawal_status', true );
	$old_status = $old_status ? $old_status : 'pending';

	if ( $old_status === $new_status ) {
		return false;
	}

	update_post_meta( $post_id, '_apg_withdrawal_status', $new_status );

	$log   = get_post_meta( $post_id, '_apg_withdrawal_status_log', true );
	$log   = is_array( $log ) ? $log : array();
	$log[] = array(
		'date'    => current_time( 'mysql' ),
		'user_id' => null !== $user_id ? absint( $user_id ) : get_current_user_id(),
		'from'    => $old_status,
		'to'      => $new_status,
	);
	update_post_meta( $post_id, '_apg_withdrawal_status_log', $log );

	$settings     = apg_withdrawal_get_settings();
	$email_on     = isset( $settings['email_on_status'] ) ? $settings['email_on_status'] : array();
	$send_email   = isset( $email_on[ $new_status ] ) && '1' === $email_on[ $new_status ];

	if ( $send_email ) {
		$customer_email = get_post_meta( $post_id, '_apg_withdrawal_email', true );
		$customer_name  = get_post_meta( $post_id, '_apg_withdrawal_name', true );
		$order_ref      = get_post_meta( $post_id, '_apg_withdrawal_order', true );

		if ( $customer_email && function_exists( 'WC' ) && WC()->mailer() ) {
			$emails = WC()->mailer()->get_emails();

			if ( isset( $emails['APG_Withdrawal_Email_Status'] ) ) {
				$emails['APG_Withdrawal_Email_Status']->trigger( $post_id, $customer_email, $customer_name, $order_ref, $new_status );
			}
		}
	}

	return true;
}

// ——————————————————————————————————
// Sincronización automática pedido → solicitud
// ——————————————————————————————————

add_action(
	'woocommerce_order_status_changed',
	function ( $order_id, $old_status, $new_status ) {
		$settings = apg_withdrawal_get_settings();
		$map      = isset( $settings['order_status_map'] ) ? $settings['order_status_map'] : array();

		if ( empty( $map ) ) {
			return;
		}

		$wc_status_key = 'wc-' . $new_status;

		foreach ( array( 'accepted', 'rejected', 'completed' ) as $withdrawal_status ) {
			$mapped = isset( $map[ $withdrawal_status ] ) ? (array) $map[ $withdrawal_status ] : array();

			if ( empty( $mapped ) ) {
				continue;
			}

			if ( in_array( $wc_status_key, $mapped, true ) ) {
				$withdrawals = apg_withdrawal_get_order_linked_withdrawals( $order_id );

				foreach ( $withdrawals as $withdrawal ) {
					apg_withdrawal_change_status( $withdrawal->ID, $withdrawal_status, 0 );
				}

				break;
			}
		}
	},
	10,
	3
);
