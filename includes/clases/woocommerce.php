<?php
/**
 * Helpers WooCommerce.
 *
 * @package APG_Withdrawal_For_WooCommerce
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
			__( '%1$s x %2$d', 'apg-withdrawal-for-woocommerce' ),
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
 * Returns the raw withdrawal type configured directly on a product (post meta
 * `_apg_withdrawal_type`). Does NOT consider category-level inheritance — see
 * `apg_withdrawal_get_effective_withdrawal_type()` for the resolved value with
 * inheritance applied.
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
 * Returns the canonical priority map for withdrawal types. Higher number means
 * "more restrictive" and wins when several types compete (per-product vs each
 * of its categories' types). Centralised here so every caller uses the same
 * resolution rule.
 *
 * @return array<string,int>
 */
function apg_withdrawal_get_type_priorities() {
	return array(
		'excluded'     => 4,
		'personalized' => 3,
		'digital'      => 2,
		'manual'       => 1,
		'allowed'      => 0,
	);
}

/**
 * Returns the effective withdrawal type for a product, applying category-level
 * inheritance with the "most restrictive wins" rule.
 *
 * Resolution order:
 *   1. If the product has an explicit type other than 'allowed', that value
 *      always wins — the merchant has set it deliberately on the product page.
 *   2. Otherwise the type of every `product_cat` term the product belongs to
 *      is considered and the most restrictive one (highest priority) wins.
 *      Priority: excluded > personalized > digital > manual > allowed.
 *   3. If no category has a non-default type either, 'allowed' is returned.
 *
 * This is the function every consumer (cart-detection, product page notice,
 * withdrawal form warnings, order-warning lookup) should use.
 *
 * @param int $product_id WooCommerce product ID.
 * @return string One of 'allowed', 'excluded', 'digital', 'personalized' or 'manual'.
 */
function apg_withdrawal_get_effective_withdrawal_type( $product_id ) {
	$product_id  = absint( $product_id );
	$raw_product = apg_withdrawal_get_product_withdrawal_type( $product_id );

	if ( 'allowed' !== $raw_product ) {
		return $raw_product;
	}

	$priorities = apg_withdrawal_get_type_priorities();
	$highest    = 'allowed';

	$category_ids = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );

	if ( is_wp_error( $category_ids ) || ! is_array( $category_ids ) ) {
		return $highest;
	}

	foreach ( $category_ids as $term_id ) {
		$term_type = get_term_meta( absint( $term_id ), '_apg_withdrawal_type', true );

		if ( ! isset( $priorities[ $term_type ] ) ) {
			continue;
		}

		if ( $priorities[ $term_type ] > $priorities[ $highest ] ) {
			$highest = $term_type;
		}
	}

	return $highest;
}

/**
 * Returns the built-in default exclusion-notice text for a given withdrawal type
 * slug. Translatable so the localised string is delivered at runtime (well after
 * `init`), avoiding WordPress 6.7's "_load_textdomain_just_in_time" warnings that
 * would fire if the strings were referenced at file-load time.
 *
 * @param string $type Withdrawal type slug.
 * @return string Translated default notice text, or empty string for unknown types.
 */
function apg_withdrawal_get_default_exclusion_notice( $type ) {
	switch ( $type ) {
		case 'excluded':
			return __( 'This product is excluded from the right of withdrawal.', 'apg-withdrawal-for-woocommerce' );
		case 'digital':
			return __( 'This product contains digital content. If you requested its immediate supply and acknowledged the loss of the right of withdrawal at purchase, you no longer have the right of withdrawal.', 'apg-withdrawal-for-woocommerce' );
		case 'personalized':
			return __( 'This product is made to your specifications or clearly personalised, and is therefore excluded from the right of withdrawal.', 'apg-withdrawal-for-woocommerce' );
		case 'manual':
			return __( 'The applicability of the right of withdrawal on this product will be reviewed manually by the store after a request is submitted.', 'apg-withdrawal-for-woocommerce' );
		default:
			return '';
	}
}

/**
 * Returns the notice text to show for a given withdrawal type, taking the value
 * the merchant has saved in `Settings → Exclusion notice texts` if non-empty,
 * and falling back to the translated default otherwise. Empty string when the
 * type does not have an associated notice (e.g. `allowed`).
 *
 * @param string $type Withdrawal type slug.
 * @return string
 */
function apg_withdrawal_get_exclusion_notice_text( $type ) {
	$default = apg_withdrawal_get_default_exclusion_notice( $type );

	if ( '' === $default ) {
		return '';
	}

	$settings = apg_withdrawal_get_settings();
	$key      = 'exclusion_notice_' . $type;
	$saved    = isset( $settings[ $key ] ) ? trim( (string) $settings[ $key ] ) : '';

	return '' !== $saved ? $saved : $default;
}

/**
 * Returns the exclusion notice text that should be displayed for a given product,
 * combining (in order of precedence):
 *
 *   1. Per-product override stored in `_apg_withdrawal_custom_reason` post meta.
 *   2. The per-type text from settings (or the translated default for that type).
 *
 * Returns an empty string when the product's effective type is `allowed` — i.e.
 * the right of withdrawal applies and no notice is needed.
 *
 * @param int $product_id WooCommerce product ID.
 * @return string
 */
function apg_withdrawal_get_product_exclusion_notice( $product_id ) {
	$product_id = absint( $product_id );
	$type       = apg_withdrawal_get_effective_withdrawal_type( $product_id );

	if ( 'allowed' === $type ) {
		return '';
	}

	$override = trim( (string) get_post_meta( $product_id, '_apg_withdrawal_custom_reason', true ) );
	if ( '' !== $override ) {
		return $override;
	}

	return apg_withdrawal_get_exclusion_notice_text( $type );
}

/**
 * Returns the highest-priority withdrawal warning type found in any order line
 * item, considering category-level inheritance for each line item product.
 *
 * @param WC_Order|bool $order WooCommerce order object or false.
 * @return string Highest-priority warning type slug.
 */
function apg_withdrawal_get_order_warning_type( $order ) {
	if ( ! $order || ! is_callable( array( $order, 'get_items' ) ) ) {
		return 'allowed';
	}

	$priorities = apg_withdrawal_get_type_priorities();
	$highest    = 'allowed';

	foreach ( $order->get_items() as $item ) {
		$product_id = is_callable( array( $item, 'get_product_id' ) ) ? $item->get_product_id() : 0;

		if ( ! $product_id ) {
			continue;
		}

		$item_type = apg_withdrawal_get_effective_withdrawal_type( $product_id );

		if ( isset( $priorities[ $item_type ] ) && $priorities[ $item_type ] > $priorities[ $highest ] ) {
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

	$scope_label = 'partial' === $scope ? __( 'Specific products only', 'apg-withdrawal-for-woocommerce' ) : __( 'Full order', 'apg-withdrawal-for-woocommerce' );

	$order->add_order_note(
		sprintf(
			/* translators: 1: scope label, 2: log id. */
			__( 'Withdrawal request received (%1$s). Log ID: #%2$d', 'apg-withdrawal-for-woocommerce' ),
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
				__( 'Withdrawal details: %s', 'apg-withdrawal-for-woocommerce' ),
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
			'post_title'   => __( 'Exercise the right of withdrawal', 'apg-withdrawal-for-woocommerce' ),
			'post_content' => '<!-- wp:paragraph --><p>' . esc_html__( 'Use this page to exercise your right of withdrawal for WooCommerce orders.', 'apg-withdrawal-for-woocommerce' ) . '</p><!-- /wp:paragraph --><!-- wp:shortcode -->[apg_withdrawal_form]<!-- /wp:shortcode -->',
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
// Active requests and deadline checks
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
// Centralised status change handling
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

	$log = get_post_meta( $post_id, '_apg_withdrawal_status_log', true );
	$log = is_array( $log ) ? $log : array();

	$log_entry = array(
		'date'        => current_time( 'mysql' ),
		'user_id'     => null !== $user_id ? absint( $user_id ) : get_current_user_id(),
		'from'        => $old_status,
		'to'          => $new_status,
		'email_attempted'   => false,
		'email_accepted'    => null,
		'email_accepted_at' => '',
		'email_error'       => '',
	);

	$settings   = apg_withdrawal_get_settings();
	$email_on   = isset( $settings['email_on_status'] ) ? $settings['email_on_status'] : array();
	$send_email = isset( $email_on[ $new_status ] ) && '1' === $email_on[ $new_status ];

	if ( $send_email ) {
		$customer_email = get_post_meta( $post_id, '_apg_withdrawal_email', true );
		$customer_name  = get_post_meta( $post_id, '_apg_withdrawal_name', true );
		$order_ref      = get_post_meta( $post_id, '_apg_withdrawal_order', true );

		if ( $customer_email && function_exists( 'WC' ) && WC()->mailer() ) {
			$emails = WC()->mailer()->get_emails();

			if ( isset( $emails['APG_Withdrawal_Email_Status'] ) ) {
				$delivery = function_exists( 'apg_withdrawal_send_with_delivery_capture' )
					? apg_withdrawal_send_with_delivery_capture(
						function () use ( $emails, $post_id, $customer_email, $customer_name, $order_ref, $new_status ) {
							$emails['APG_Withdrawal_Email_Status']->trigger( $post_id, $customer_email, $customer_name, $order_ref, $new_status );
						}
					)
					: array( 'attempted' => true, 'accepted' => true, 'accepted_at' => gmdate( 'Y-m-d H:i:s' ), 'error' => '' );

				$log_entry['email_attempted']   = (bool) $delivery['attempted'];
				$log_entry['email_accepted']    = $delivery['accepted'];
				$log_entry['email_accepted_at'] = (string) $delivery['accepted_at'];
				$log_entry['email_error']       = (string) $delivery['error'];
			}
		}
	}

	$log[] = $log_entry;
	update_post_meta( $post_id, '_apg_withdrawal_status_log', $log );

	return true;
}

// ——————————————————————————————————
// Automatic order → withdrawal request synchronisation
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
