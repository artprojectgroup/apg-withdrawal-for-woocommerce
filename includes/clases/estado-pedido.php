<?php
/**
 * Custom WooCommerce order status `wc-apg-withdrawal` ("Withdrawal requested").
 *
 * Having a first-class order status — instead of only a linked custom post type —
 * lets the merchant filter, sort, report on and bulk-edit the orders affected by
 * a withdrawal declaration using the native WooCommerce tooling, and makes the
 * state visible to every third-party integration that reads order statuses
 * (ERP connectors, shipping plugins, analytics).
 *
 * The status is registered for both storage backends: `register_post_status()`
 * covers the legacy post-based orders list, while the `wc_order_statuses` filter
 * is what HPOS, the order editor dropdown and the plugin's own Automation
 * mapping read from.
 *
 * @package APG_Withdrawal_For_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Status key used both as the post status (legacy storage) and as the key
 * inside `wc_get_order_statuses()`.
 */
const APG_WITHDRAWAL_ORDER_STATUS = 'wc-apg-withdrawal';

/**
 * Returns the translated label of the custom order status.
 *
 * @return string
 */
function apg_withdrawal_get_order_status_label() {
	return _x( 'Withdrawal requested', 'Order status', 'apg-withdrawal-for-woocommerce' );
}

/**
 * Registers the custom order post status for the legacy (post-based) orders
 * storage. Harmless when HPOS is the active backend: WooCommerce keeps the post
 * status registry in sync for compatibility.
 *
 * @return void
 */
function apg_withdrawal_register_order_status() {
	register_post_status(
		APG_WITHDRAWAL_ORDER_STATUS,
		array(
			'label'                     => apg_withdrawal_get_order_status_label(),
			'public'                    => false,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s: number of orders. */
			'label_count'               => _n_noop(
				'Withdrawal requested <span class="count">(%s)</span>',
				'Withdrawal requested <span class="count">(%s)</span>',
				'apg-withdrawal-for-woocommerce'
			),
		)
	);
}
add_action( 'init', 'apg_withdrawal_register_order_status' );

/**
 * Adds the custom status to the list WooCommerce uses everywhere: the order
 * editor dropdown, the HPOS list table views, the reports and this plugin's own
 * Automation settings.
 *
 * It is inserted right after `wc-processing` so it reads as a mid-lifecycle
 * state rather than a terminal one.
 *
 * @param array<string,string> $statuses Registered order statuses.
 * @return array<string,string> Statuses including the withdrawal one.
 */
function apg_withdrawal_add_order_status( $statuses ) {
	$ordered = array();

	foreach ( $statuses as $key => $label ) {
		$ordered[ $key ] = $label;

		if ( 'wc-processing' === $key ) {
			$ordered[ APG_WITHDRAWAL_ORDER_STATUS ] = apg_withdrawal_get_order_status_label();
		}
	}

	if ( ! isset( $ordered[ APG_WITHDRAWAL_ORDER_STATUS ] ) ) {
		$ordered[ APG_WITHDRAWAL_ORDER_STATUS ] = apg_withdrawal_get_order_status_label();
	}

	return $ordered;
}
add_filter( 'wc_order_statuses', 'apg_withdrawal_add_order_status' );

/**
 * Offers the status as a bulk action in the orders list. WooCommerce resolves
 * any `mark_<status>` action generically against `wc_get_order_statuses()`, so
 * registering the entry is enough — no separate handler is required.
 *
 * @param array<string,string> $actions Registered bulk actions.
 * @return array<string,string> Bulk actions including the withdrawal one.
 */
function apg_withdrawal_add_order_bulk_action( $actions ) {
	$actions[ 'mark_' . substr( APG_WITHDRAWAL_ORDER_STATUS, 3 ) ] = sprintf(
		/* translators: %s: order status label. */
		__( 'Change status to %s', 'apg-withdrawal-for-woocommerce' ),
		apg_withdrawal_get_order_status_label()
	);

	return $actions;
}
add_filter( 'bulk_actions-edit-shop_order', 'apg_withdrawal_add_order_bulk_action' );
add_filter( 'bulk_actions-woocommerce_page_wc-orders', 'apg_withdrawal_add_order_bulk_action' );

/**
 * Moves the linked WooCommerce order to the status configured in
 * `Settings → Order status when a request is received` as soon as a withdrawal
 * declaration is registered. Disabled by default (empty setting) so existing
 * installations keep their current behaviour after updating.
 *
 * The change is skipped when the order already sits in the target status, so
 * no redundant order note or status-change email is generated.
 *
 * @param WC_Order|bool $order   WooCommerce order object or false.
 * @param int           $post_id Withdrawal request post ID.
 * @return void
 */
function apg_withdrawal_maybe_set_order_status( $order, $post_id ) {
	if ( ! $order || ! is_callable( array( $order, 'get_status' ) ) ) {
		return;
	}

	$settings = apg_withdrawal_get_settings();
	$target   = isset( $settings['order_status_on_request'] ) ? sanitize_key( $settings['order_status_on_request'] ) : '';

	if ( '' === $target ) {
		return;
	}

	$statuses = function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : array();

	if ( ! isset( $statuses[ $target ] ) ) {
		return;
	}

	$target_slug = 0 === strpos( $target, 'wc-' ) ? substr( $target, 3 ) : $target;

	if ( $order->get_status() === $target_slug ) {
		return;
	}

	$order->update_status(
		$target_slug,
		sprintf(
			/* translators: %d: withdrawal request log ID. */
			__( 'Status changed automatically after withdrawal request #%d.', 'apg-withdrawal-for-woocommerce' ),
			absint( $post_id )
		)
	);
}
