<?php
/**
 * Public "withdrawal link" helpers: a `[apg_withdrawal_link]` shortcode and the
 * matching `apg-withdrawal/link` Gutenberg block. Both render a hyperlink to
 * the page configured as the withdrawal form page (the one that holds the
 * `[apg_withdrawal_form]` shortcode), so merchants and theme builders can
 * place the call-to-action of Article 11 bis of Directive 2011/83/EU wherever
 * suits their layout — footer, account menu, terms page, sidebar widget, FSE
 * template, etc. — without writing raw HTML.
 *
 * @package APG_Withdrawal_For_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns the absolute URL of the public withdrawal form page if one has been
 * configured (settings `page_id`). Falls back to an empty string when no page
 * has been chosen so callers can decide whether to render a link or nothing.
 *
 * @return string
 */
function apg_withdrawal_get_form_page_url() {
	if ( ! function_exists( 'apg_withdrawal_get_page_id' ) ) {
		return '';
	}

	$page_id = apg_withdrawal_get_page_id();
	if ( ! $page_id ) {
		return '';
	}

	$permalink = get_permalink( $page_id );
	return $permalink ? (string) $permalink : '';
}

/**
 * Returns the rendered `<a>` markup for the withdrawal link. The label
 * defaults to the literal wording suggested by Article 11 bis(1) of
 * Directive 2011/83/EU ("withdraw from the contract here"), so a barebones
 * `[apg_withdrawal_link]` is already legally compliant out of the box.
 *
 * @param array<string,string> $args Render arguments. Recognised keys:
 *                                   - `label`  (string) Inner text of the link.
 *                                   - `class`  (string) Extra CSS classes.
 *                                   - `target` (string) `_self`, `_blank`, …
 * @return string Anchor markup, or empty string when no page is configured.
 */
function apg_withdrawal_render_link_html( array $args = array() ) {
	$url = apg_withdrawal_get_form_page_url();
	if ( '' === $url ) {
		return '';
	}

	$defaults = array(
		'label'  => __( 'Withdraw from the contract here', 'apg-withdrawal-for-woocommerce' ),
		'class'  => '',
		'target' => '_self',
	);
	$args     = array_merge( $defaults, $args );

	$class_attr = trim( 'apg-withdrawal-link ' . (string) $args['class'] );
	$rel_attr   = '_blank' === $args['target'] ? ' rel="noopener"' : '';

	return sprintf(
		'<a href="%1$s" class="%2$s" target="%3$s"%4$s>%5$s</a>',
		esc_url( $url ),
		esc_attr( $class_attr ),
		esc_attr( $args['target'] ),
		$rel_attr,
		esc_html( (string) $args['label'] )
	);
}

/**
 * Shortcode `[apg_withdrawal_link label="..." class="..." target="..."]`.
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function apg_withdrawal_link_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'label'  => '',
			'class'  => '',
			'target' => '_self',
		),
		(array) $atts,
		'apg_withdrawal_link'
	);

	if ( '' === trim( (string) $atts['label'] ) ) {
		unset( $atts['label'] ); // Let the renderer apply the default label.
	}

	return apg_withdrawal_render_link_html( $atts );
}
add_shortcode( 'apg_withdrawal_link', 'apg_withdrawal_link_shortcode' );

/**
 * Registers a server-side rendered Gutenberg block at `apg-withdrawal/link`
 * exposing the same rendering as the shortcode, so the link can be inserted
 * into block-themed sites via the block inserter.
 *
 * @return void
 */
function apg_withdrawal_register_link_block() {
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}

	register_block_type(
		'apg-withdrawal/link',
		array(
			'api_version'     => 2,
			'title'           => __( 'Withdrawal link', 'apg-withdrawal-for-woocommerce' ),
			'category'        => 'woocommerce',
			'description'     => __( 'Inserts a link to the public withdrawal form page (Article 11a function of Directive 2011/83/EU).', 'apg-withdrawal-for-woocommerce' ),
			'attributes'      => array(
				'label'  => array(
					'type'    => 'string',
					'default' => '',
				),
				'class'  => array(
					'type'    => 'string',
					'default' => '',
				),
				'target' => array(
					'type'    => 'string',
					'default' => '_self',
				),
			),
			'supports'        => array(
				'html'  => false,
				'align' => array( 'wide', 'full' ),
			),
			'render_callback' => 'apg_withdrawal_render_link_block',
		)
	);
}
add_action( 'init', 'apg_withdrawal_register_link_block' );

/**
 * Render callback for the `apg-withdrawal/link` block.
 *
 * @param array $attributes Block attributes.
 * @return string Rendered block HTML, or empty string when no page is set.
 */
function apg_withdrawal_render_link_block( $attributes ) {
	$args = array(
		'label'  => isset( $attributes['label'] ) ? (string) $attributes['label'] : '',
		'class'  => isset( $attributes['class'] ) ? (string) $attributes['class'] : '',
		'target' => isset( $attributes['target'] ) ? (string) $attributes['target'] : '_self',
	);

	if ( '' === trim( $args['label'] ) ) {
		unset( $args['label'] );
	}

	return apg_withdrawal_render_link_html( $args );
}
