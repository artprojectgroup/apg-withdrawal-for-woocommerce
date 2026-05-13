<?php
/**
 * Clasificación de productos para el derecho de desistimiento.
 *
 * @package WC_APG_Withdrawal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds the Withdrawal tab to the WooCommerce product data panel.
 *
 * @param array $tabs Existing product data tabs array.
 * @return array Modified tabs array with the Withdrawal tab added.
 */
function apg_withdrawal_product_tab( $tabs ) {
	$tabs['apg_withdrawal'] = array(
		'label'    => __( 'Withdrawal', 'wc-apg-withdrawal' ),
		'target'   => 'apg_withdrawal_product_data',
		'class'    => array(),
		'priority' => 80,
	);

	return $tabs;
}
add_filter( 'woocommerce_product_data_tabs', 'apg_withdrawal_product_tab' );

/**
 * Renders the Withdrawal tab content panel on the product edit screen.
 *
 * @return void
 */
function apg_withdrawal_product_tab_content() {
	global $post;

	$current = get_post_meta( $post->ID, '_apg_withdrawal_type', true );
	$current = $current ?: 'allowed';

	$types = array(
		'allowed'      => __( 'Withdrawal allowed (default)', 'wc-apg-withdrawal' ),
		'excluded'     => __( 'Withdrawal excluded', 'wc-apg-withdrawal' ),
		'digital'      => __( 'Digital content', 'wc-apg-withdrawal' ),
		'personalized' => __( 'Personalised product', 'wc-apg-withdrawal' ),
		'manual'       => __( 'Manual review required', 'wc-apg-withdrawal' ),
	);

	echo '<div id="apg_withdrawal_product_data" class="panel woocommerce_options_panel">';
	echo '<div class="options_group">';

	woocommerce_wp_select(
		array(
			'id'          => '_apg_withdrawal_type',
			'label'       => __( 'Withdrawal type', 'wc-apg-withdrawal' ),
			'description' => __( 'Defines whether the right of withdrawal applies to this product. Shown as a notice to the customer in the withdrawal form.', 'wc-apg-withdrawal' ),
			'desc_tip'    => true,
			'value'       => $current,
			'options'     => $types,
		)
	);

	echo '</div>';
	echo '</div>';
}
add_action( 'woocommerce_product_data_panels', 'apg_withdrawal_product_tab_content' );

/**
 * Saves the withdrawal type meta value when a product is saved.
 *
 * @param int $post_id WooCommerce product post ID.
 * @return void
 */
function apg_withdrawal_save_product_type( $post_id ) {
	if ( ! current_user_can( 'edit_product', $post_id ) ) {
		return;
	}

	// Nonce is verified upstream by WooCommerce before firing woocommerce_process_product_meta.
	$type = isset( $_POST['_apg_withdrawal_type'] ) ? sanitize_key( wp_unslash( $_POST['_apg_withdrawal_type'] ) ) : 'allowed'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by WooCommerce upstream.

	$allowed = array( 'allowed', 'excluded', 'digital', 'personalized', 'manual' );

	update_post_meta( $post_id, '_apg_withdrawal_type', in_array( $type, $allowed, true ) ? $type : 'allowed' );
}
add_action( 'woocommerce_process_product_meta', 'apg_withdrawal_save_product_type' );
