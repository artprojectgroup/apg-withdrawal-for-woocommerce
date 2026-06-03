<?php
/**
 * Public product-page exclusion notice.
 *
 * Injects an "excluded from withdrawal" notice in the WooCommerce product
 * summary (between the price and the Add to Cart button), driven by the
 * product's effective withdrawal type (per-product setting with category
 * inheritance via `apg_withdrawal_get_effective_withdrawal_type()`).
 *
 * Also exposes a `[apg_withdrawal_notice]` shortcode and a Gutenberg block
 * so the notice can be placed manually by themes that rebuild the WooCommerce
 * product summary instead of honouring the standard hooks.
 *
 * @package APG_Withdrawal_For_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns the HTML markup for the exclusion notice for a given product, or an
 * empty string when the product's effective type is `allowed`. The markup uses
 * WooCommerce-friendly CSS classes (`woocommerce-info` + `apg-withdrawal-product-notice`)
 * so it picks up the active theme's existing notice styling.
 *
 * @param int $product_id WooCommerce product ID.
 * @return string Notice HTML markup, or empty string.
 */
function apg_withdrawal_render_product_notice_html( $product_id ) {
	$product_id = absint( $product_id );
	if ( ! $product_id ) {
		return '';
	}

	$text = function_exists( 'apg_withdrawal_get_product_exclusion_notice' ) ? apg_withdrawal_get_product_exclusion_notice( $product_id ) : '';
	if ( '' === $text ) {
		return '';
	}

	return sprintf(
		'<div class="woocommerce-info apg-withdrawal-product-notice" role="status">%s</div>',
		wp_kses_post( wpautop( $text ) )
	);
}

/**
 * Echoes the exclusion notice on the single product page when the product is
 * classified with a type other than `allowed`. Hooked at priority 20 so it
 * lands between the price (priority 10) and the Add to Cart button
 * (priority 30) on the standard `woocommerce_single_product_summary` action.
 *
 * @return void
 */
function apg_withdrawal_inject_product_notice() {
	$product = function_exists( 'wc_get_product' ) ? wc_get_product( get_the_ID() ) : null;
	if ( ! $product || ! is_callable( array( $product, 'get_id' ) ) ) {
		return;
	}

	$html = apg_withdrawal_render_product_notice_html( $product->get_id() );
	if ( '' === $html ) {
		return;
	}

	echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already escaped inside the renderer.
}
add_action( 'woocommerce_single_product_summary', 'apg_withdrawal_inject_product_notice', 20 );

/**
 * Shortcode `[apg_withdrawal_notice]` rendering the exclusion notice for a
 * given product. Useful for themes that rebuild the product summary and do not
 * fire `woocommerce_single_product_summary`, or to place the notice in custom
 * locations (e.g. inside a tab, in a sidebar, etc.).
 *
 * Attributes:
 *   - `product_id` (optional): explicit product ID. Defaults to the current
 *     post when used inside a single-product context.
 *
 * @param array $atts Shortcode attributes.
 * @return string Notice HTML markup, or empty string.
 */
function apg_withdrawal_notice_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'product_id' => 0,
		),
		(array) $atts,
		'apg_withdrawal_notice'
	);

	$product_id = absint( $atts['product_id'] );
	if ( ! $product_id ) {
		$product_id = absint( get_the_ID() );
	}

	return apg_withdrawal_render_product_notice_html( $product_id );
}
add_shortcode( 'apg_withdrawal_notice', 'apg_withdrawal_notice_shortcode' );

/**
 * Registers a server-side rendered Gutenberg block named
 * `apg-withdrawal/notice`. The block accepts an optional `productId` attribute;
 * when omitted it picks up the current post in single-product context. Themes
 * built with FSE / block templates can drop this block wherever the notice
 * should appear instead of relying on the `woocommerce_single_product_summary`
 * action.
 *
 * @return void
 */
function apg_withdrawal_register_notice_block() {
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}

	register_block_type(
		'apg-withdrawal/notice',
		array(
			'api_version'     => 2,
			'title'           => __( 'Withdrawal exclusion notice', 'apg-withdrawal-for-woocommerce' ),
			'category'        => 'woocommerce',
			'description'     => __( 'Displays the exclusion-from-withdrawal notice for the current product (or for an explicit product ID), driven by the product / category withdrawal type.', 'apg-withdrawal-for-woocommerce' ),
			'attributes'      => array(
				'productId' => array(
					'type'    => 'integer',
					'default' => 0,
				),
			),
			'supports'        => array(
				'html'  => false,
				'align' => array( 'wide', 'full' ),
			),
			'render_callback' => 'apg_withdrawal_render_notice_block',
		)
	);
}
add_action( 'init', 'apg_withdrawal_register_notice_block' );

/**
 * Render callback for the `apg-withdrawal/notice` block.
 *
 * @param array $attributes Block attributes.
 * @return string Rendered block HTML, or empty string when the product's
 *                effective type is `allowed`.
 */
function apg_withdrawal_render_notice_block( $attributes ) {
	$product_id = isset( $attributes['productId'] ) ? absint( $attributes['productId'] ) : 0;
	if ( ! $product_id ) {
		$product_id = absint( get_the_ID() );
	}

	return apg_withdrawal_render_product_notice_html( $product_id );
}
