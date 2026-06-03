<?php
/**
 * Checkout integration: digital-content withdrawal waiver checkbox.
 *
 * Injects a checkbox into both the classic and block-based WooCommerce checkouts
 * when the cart contains at least one product configured as "Digital content"
 * for withdrawal purposes. The customer must acknowledge that requesting the
 * immediate supply of digital content waives their right of withdrawal as required
 * by EU consumer protection legislation.
 *
 * @package APG_Withdrawal_For_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Determines whether the digital-content waiver checkbox should be displayed for
 * the current cart. The decision is driven by the `digital_waiver_mode` plugin
 * setting, which after the 0.5.0 simplification has only three possible values:
 *
 *   - 'disabled': never show.
 *   - 'digital':  show when at least one cart product has the effective
 *                 withdrawal type `digital` (per-product setting, or inherited
 *                 from one of its `product_cat` terms using the most-restrictive
 *                 rule from `apg_withdrawal_get_effective_withdrawal_type()`).
 *   - 'all':      show whenever the cart is not empty.
 *
 * @return bool
 */
function apg_withdrawal_cart_has_digital_content() {
	$settings = function_exists( 'apg_withdrawal_get_settings' ) ? apg_withdrawal_get_settings() : array();
	$mode     = isset( $settings['digital_waiver_mode'] ) ? $settings['digital_waiver_mode'] : 'disabled';

	if ( 'disabled' === $mode ) {
		return false;
	}

	if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
		return false;
	}

	if ( 'all' === $mode ) {
		return true;
	}

	if ( 'digital' !== $mode ) {
		return false;
	}

	foreach ( WC()->cart->get_cart() as $cart_item ) {
		$product_id = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;

		if ( ! $product_id ) {
			continue;
		}

		if ( 'digital' === apg_withdrawal_get_effective_withdrawal_type( $product_id ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Returns the label displayed next to the digital-content waiver checkbox. If the
 * administrator has filled in a custom label in the plugin settings, that value
 * is used verbatim; otherwise the default translatable acknowledgement string is
 * returned so the rendered text follows the current locale.
 *
 * @return string
 */
function apg_withdrawal_get_digital_waiver_label() {
	$settings = function_exists( 'apg_withdrawal_get_settings' ) ? apg_withdrawal_get_settings() : array();
	$custom   = isset( $settings['digital_waiver_custom_label'] ) ? trim( (string) $settings['digital_waiver_custom_label'] ) : '';

	if ( '' !== $custom ) {
		return $custom;
	}

	return __( 'I request the immediate supply of the digital content and acknowledge that, once execution has begun, I will lose my right of withdrawal.', 'apg-withdrawal-for-woocommerce' );
}

/**
 * Renders the digital-content waiver checkbox in the classic checkout, immediately
 * before the WooCommerce terms-and-conditions checkbox. The checkbox is optional:
 * the customer can submit the order whether or not it is ticked.
 *
 * Hook priority 999 ensures other plugins hooking into the same action at the default
 * priority of 10 render their own checkboxes first, so this waiver always sits last
 * in the "before terms" group and immediately before WooCommerce's native checkbox.
 *
 * @return void
 */
function apg_withdrawal_render_classic_digital_waiver() {
	if ( ! apg_withdrawal_cart_has_digital_content() ) {
		return;
	}

	?>
	<p class="form-row apg-withdrawal-digital-waiver-row" id="apg_withdrawal_digital_waiver_field">
		<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
			<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="apg_withdrawal_digital_waiver" id="apg_withdrawal_digital_waiver" value="1" />
			<span class="woocommerce-terms-and-conditions-checkbox-text"><?php echo esc_html( apg_withdrawal_get_digital_waiver_label() ); ?></span>
		</label>
	</p>
	<?php
}
add_action( 'woocommerce_checkout_before_terms_and_conditions', 'apg_withdrawal_render_classic_digital_waiver', 999 );

/**
 * Enqueues the script that manages the digital-content waiver checkbox in the
 * block-based WooCommerce checkout. The script is enqueued whenever the customer
 * is on the checkout page rendered with the `woocommerce/checkout` block so it
 * can react to cart changes mid-checkout (showing the checkbox when a qualifying
 * product is added, removing it when none remain). The initial qualification is
 * computed server-side and passed to JavaScript so the first render is correct
 * without an extra round-trip.
 *
 * @return void
 */
function apg_withdrawal_enqueue_block_digital_waiver() {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
		return;
	}

	wp_enqueue_script(
		'apg-withdrawal-checkout-block',
		plugins_url( 'assets/js/checkout-block.js', apg_withdrawal_DIRECCION ),
		array( 'wc-blocks-checkout' ),
		apg_withdrawal_VERSION,
		true
	);

	wp_localize_script(
		'apg-withdrawal-checkout-block',
		'apgWithdrawalCheckout',
		array(
			'label'            => apg_withdrawal_get_digital_waiver_label(),
			'initialQualifies' => apg_withdrawal_cart_has_digital_content(),
			'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
			'recheckNonce'     => wp_create_nonce( 'apg_withdrawal_check_cart_waiver' ),
		)
	);
}
add_action(
	'woocommerce_init',
	function () {
		if ( ! function_exists( 'has_block' ) || ! function_exists( 'wc_get_page_id' ) ) {
			return;
		}

		if ( has_block( 'woocommerce/checkout', wc_get_page_id( 'checkout' ) ) ) {
			add_action( 'wp_enqueue_scripts', 'apg_withdrawal_enqueue_block_digital_waiver' );
		}
	}
);

/**
 * AJAX handler used by the block-checkout JavaScript to re-check, after a cart
 * mutation, whether the digital-content waiver should be displayed. Returns the
 * boolean result of `apg_withdrawal_cart_has_digital_content()` so the client
 * can show or remove the checkbox dynamically without a full page reload.
 *
 * @return void
 */
function apg_withdrawal_ajax_check_cart_waiver() {
	check_ajax_referer( 'apg_withdrawal_check_cart_waiver', 'nonce', false );

	wp_send_json_success(
		array(
			'qualifies' => apg_withdrawal_cart_has_digital_content(),
		)
	);
}
add_action( 'wp_ajax_apg_withdrawal_check_cart_waiver', 'apg_withdrawal_ajax_check_cart_waiver' );
add_action( 'wp_ajax_nopriv_apg_withdrawal_check_cart_waiver', 'apg_withdrawal_ajax_check_cart_waiver' );

/**
 * Builds a structured log of the customer's choice on the digital-content
 * waiver checkbox, suitable for persisting on the order as legal evidence under
 * Article 16 bis(8) of Directive 2011/83/EU (burden of proof on the merchant).
 *
 * @param bool   $checked       Whether the customer ticked the checkbox.
 * @param string $checkout_type Either 'classic' or 'block', for traceability.
 * @return array<string,mixed>
 */
function apg_withdrawal_build_digital_waiver_log( $checked, $checkout_type ) {
	$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 1024 ) : '';
	$ip         = class_exists( 'WC_Geolocation' ) ? WC_Geolocation::get_ip_address() : ( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
	$label      = function_exists( 'apg_withdrawal_get_digital_waiver_label' ) ? apg_withdrawal_get_digital_waiver_label() : '';

	return array(
		'accepted'      => (bool) $checked,
		'label_shown'   => (string) $label,
		'timestamp_utc' => gmdate( 'Y-m-d H:i:s' ),
		'ip'            => (string) $ip,
		'user_agent'    => (string) $user_agent,
		'checkout_type' => 'block' === $checkout_type ? 'block' : 'classic',
	);
}

/**
 * Persists the classic-checkout digital waiver acknowledgement to order meta
 * `_apg_withdrawal_digital_waiver_log` as a JSON-serialisable array including
 * the exact label shown to the customer, the timestamp (UTC), the IP address
 * and user agent. Only runs when the cart actually qualified for the checkbox
 * — irrelevant entries are not stored on regular orders.
 *
 * The legacy `_apg_withdrawal_digital_waiver` ('0' or '1') is also written so
 * any merchant tooling that reads the old key keeps working without changes.
 *
 * @param WC_Order $order Order being created.
 * @return void
 */
function apg_withdrawal_save_classic_digital_waiver( $order ) {
	if ( ! apg_withdrawal_cart_has_digital_content() ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Submission protected by WooCommerce's own checkout nonce
	$checked = ! empty( $_POST['apg_withdrawal_digital_waiver'] );

	$log = apg_withdrawal_build_digital_waiver_log( $checked, 'classic' );

	$order->update_meta_data( '_apg_withdrawal_digital_waiver_log', $log );
	$order->update_meta_data( '_apg_withdrawal_digital_waiver', $checked ? '1' : '0' );
}
add_action( 'woocommerce_checkout_create_order', 'apg_withdrawal_save_classic_digital_waiver', 10, 1 );

/**
 * Persists the block-checkout digital waiver acknowledgement to order meta when
 * the StoreAPI checkout request reaches the server. The boolean arrives via
 * `extensions['apg-withdrawal']['digital_waiver']`, populated by the front-end
 * fetch interceptor. The structured log is identical in shape to the
 * classic-checkout counterpart so downstream tooling can read both flows the
 * same way.
 *
 * @param WC_Order        $order   Order being created.
 * @param WP_REST_Request $request Incoming StoreAPI request.
 * @return void
 */
function apg_withdrawal_save_block_digital_waiver( $order, $request ) {
	if ( ! apg_withdrawal_cart_has_digital_content() ) {
		return;
	}

	$extensions = $request->get_param( 'extensions' );
	if ( ! is_array( $extensions ) || ! isset( $extensions['apg-withdrawal']['digital_waiver'] ) ) {
		return;
	}

	$checked = ! empty( $extensions['apg-withdrawal']['digital_waiver'] );

	$log = apg_withdrawal_build_digital_waiver_log( $checked, 'block' );

	$order->update_meta_data( '_apg_withdrawal_digital_waiver_log', $log );
	$order->update_meta_data( '_apg_withdrawal_digital_waiver', $checked ? '1' : '0' );
}
add_action( 'woocommerce_store_api_checkout_update_order_from_request', 'apg_withdrawal_save_block_digital_waiver', 10, 2 );
