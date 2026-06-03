<?php
/**
 * Annex I.B — Model withdrawal form (printable HTML).
 *
 * Implements the model withdrawal form set out in Annex I.B of Directive
 * 2011/83/EU. The form is delivered as a self-contained HTML page with
 * `@media print` styling so the consumer can open it in the browser and use
 * the browser's print-to-PDF feature to obtain a durable medium. No PDF
 * library dependency is required.
 *
 * The page is exposed at `?apg_withdrawal_model_form=1` on any front-end URL
 * (no permalink rewrite rules needed) and is linked from the public
 * withdrawal request form template.
 *
 * Merchant data shown at the top of the form is collected from:
 *   - Store name        → `blogname` option (= site title)
 *   - Store address     → WooCommerce `woocommerce_store_*` options
 *   - Merchant email    → WooCommerce `woocommerce_email_from_address` if set,
 *                         falling back to the plugin's `notification_email`
 *   - Merchant phone    → plugin setting `merchant_phone` (optional; if empty
 *                         the phone line is omitted from the form)
 *
 * @package APG_Withdrawal_For_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns the absolute URL that serves the printable Annex I.B model form on
 * the front-end. The query argument is read directly from `$_GET` by
 * `apg_withdrawal_maybe_render_model_form()` on `template_redirect`, so this
 * URL works regardless of the site's permalink configuration.
 *
 * @return string
 */
function apg_withdrawal_get_model_form_url() {
	return add_query_arg( 'apg_withdrawal_model_form', '1', home_url( '/' ) );
}

/**
 * Returns the merchant data dictionary used to populate the addressee block of
 * the Annex I.B model form. Empty values are returned as empty strings; the
 * template skips lines that have no content.
 *
 * @return array<string,string>
 */
function apg_withdrawal_get_merchant_data_for_model_form() {
	$settings = function_exists( 'apg_withdrawal_get_settings' ) ? apg_withdrawal_get_settings() : array();

	$store_email = get_option( 'woocommerce_email_from_address', '' );
	if ( empty( $store_email ) ) {
		$store_email = isset( $settings['notification_email'] ) ? $settings['notification_email'] : '';
	}

	$address_lines = array(
		(string) get_option( 'woocommerce_store_address', '' ),
		(string) get_option( 'woocommerce_store_address_2', '' ),
		trim( (string) get_option( 'woocommerce_store_postcode', '' ) . ' ' . (string) get_option( 'woocommerce_store_city', '' ) ),
		(string) get_option( 'woocommerce_default_country', '' ),
	);
	$address       = trim( implode( ', ', array_filter( array_map( 'trim', $address_lines ) ) ) );

	return array(
		'name'    => (string) get_bloginfo( 'name' ),
		'address' => $address,
		'phone'   => isset( $settings['merchant_phone'] ) ? trim( (string) $settings['merchant_phone'] ) : '',
		'email'   => (string) $store_email,
	);
}

/**
 * Renders the printable Annex I.B HTML page and terminates the response.
 * Called only after the `apg_withdrawal_model_form` query argument is detected.
 *
 * @return void
 */
function apg_withdrawal_render_model_form() {
	nocache_headers();

	$merchant = apg_withdrawal_get_merchant_data_for_model_form();
	$lang     = function_exists( 'get_bloginfo' ) ? get_bloginfo( 'language' ) : 'en';

	// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Output composed of esc_html'd parts.
	?><!DOCTYPE html>
<html lang="<?php echo esc_attr( $lang ); ?>">
<head>
	<meta charset="<?php echo esc_attr( get_bloginfo( 'charset' ) ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html__( 'Model withdrawal form (Annex I.B)', 'apg-withdrawal-for-woocommerce' ); ?></title>
	<style>
		body { font-family: Georgia, 'Times New Roman', serif; max-width: 760px; margin: 40px auto; padding: 0 24px; color: #222; line-height: 1.55; }
		h1 { font-size: 1.5em; margin-bottom: 8px; }
		h2 { font-size: 1.1em; margin-top: 32px; }
		.intro { font-style: italic; color: #555; margin-bottom: 28px; }
		.addressee { margin: 16px 0 32px; padding: 16px 20px; border-left: 4px solid #555; background: #f7f7f7; }
		.addressee p { margin: 4px 0; }
		.field { margin: 24px 0; border-bottom: 1px solid #aaa; padding-bottom: 4px; min-height: 26px; }
		.field-label { font-weight: 600; display: block; margin-bottom: 6px; }
		.print-actions { text-align: right; margin: 24px 0; }
		.print-actions button { font: inherit; padding: 8px 16px; cursor: pointer; }
		footer { font-size: 0.85em; color: #777; margin-top: 40px; border-top: 1px solid #ccc; padding-top: 12px; }
		@media print {
			body { margin: 0; max-width: none; padding: 0 12mm; }
			.print-actions { display: none; }
			.addressee { background: #fff; }
		}
	</style>
</head>
<body>
	<div class="print-actions">
		<button type="button" onclick="window.print();"><?php echo esc_html__( 'Print or save as PDF', 'apg-withdrawal-for-woocommerce' ); ?></button>
	</div>

	<h1><?php echo esc_html__( 'Model withdrawal form', 'apg-withdrawal-for-woocommerce' ); ?></h1>
	<p class="intro"><?php echo esc_html__( '(Complete and return this form only if you wish to withdraw from the contract.)', 'apg-withdrawal-for-woocommerce' ); ?></p>

	<h2><?php echo esc_html__( 'To', 'apg-withdrawal-for-woocommerce' ); ?>:</h2>
	<div class="addressee">
		<?php if ( '' !== $merchant['name'] ) : ?>
			<p><strong><?php echo esc_html( $merchant['name'] ); ?></strong></p>
		<?php endif; ?>
		<?php if ( '' !== $merchant['address'] ) : ?>
			<p><?php echo esc_html( $merchant['address'] ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== $merchant['phone'] ) : ?>
			<p><?php echo esc_html__( 'Phone', 'apg-withdrawal-for-woocommerce' ); ?>: <?php echo esc_html( $merchant['phone'] ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== $merchant['email'] ) : ?>
			<p><?php echo esc_html__( 'Email', 'apg-withdrawal-for-woocommerce' ); ?>: <?php echo esc_html( $merchant['email'] ); ?></p>
		<?php endif; ?>
	</div>

	<p><?php echo esc_html__( 'I/We (*) hereby give notice that I/We (*) withdraw from my/our (*) contract of sale of the following goods (*) / for the provision of the following service (*):', 'apg-withdrawal-for-woocommerce' ); ?></p>
	<div class="field">
		<span class="field-label"><?php echo esc_html__( 'Description of goods / service', 'apg-withdrawal-for-woocommerce' ); ?></span>
	</div>

	<div class="field">
		<span class="field-label"><?php echo esc_html__( 'Ordered on (*) / received on (*)', 'apg-withdrawal-for-woocommerce' ); ?></span>
	</div>

	<div class="field">
		<span class="field-label"><?php echo esc_html__( 'Name of consumer(s)', 'apg-withdrawal-for-woocommerce' ); ?></span>
	</div>

	<div class="field">
		<span class="field-label"><?php echo esc_html__( 'Address of consumer(s)', 'apg-withdrawal-for-woocommerce' ); ?></span>
	</div>

	<div class="field">
		<span class="field-label"><?php echo esc_html__( 'Signature of consumer(s) (only if this form is notified on paper)', 'apg-withdrawal-for-woocommerce' ); ?></span>
	</div>

	<div class="field">
		<span class="field-label"><?php echo esc_html__( 'Date', 'apg-withdrawal-for-woocommerce' ); ?></span>
	</div>

	<footer>
		<p>(*) <?php echo esc_html__( 'Delete as appropriate.', 'apg-withdrawal-for-woocommerce' ); ?></p>
		<p><?php echo esc_html__( 'Model withdrawal form pursuant to Annex I.B of Directive 2011/83/EU on consumer rights.', 'apg-withdrawal-for-woocommerce' ); ?></p>
	</footer>
</body>
</html>
	<?php
	// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
	exit;
}

/**
 * Detects the `apg_withdrawal_model_form` query argument on the front-end and,
 * when present, renders the printable Annex I.B model form. Hooked on
 * `template_redirect` so the WordPress query is already resolved (we don't
 * need it) and headers haven't been sent yet.
 *
 * @return void
 */
function apg_withdrawal_maybe_render_model_form() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read-only page with no side effects.
	if ( empty( $_GET['apg_withdrawal_model_form'] ) ) {
		return;
	}

	apg_withdrawal_render_model_form();
}
add_action( 'template_redirect', 'apg_withdrawal_maybe_render_model_form' );
