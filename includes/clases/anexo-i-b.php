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

	$raw_country  = (string) get_option( 'woocommerce_default_country', '' );
	$country_code = $raw_country;
	$state_code   = '';
	if ( false !== strpos( $raw_country, ':' ) ) {
		list( $country_code, $state_code ) = explode( ':', $raw_country, 2 );
	}

	$country_name = $country_code;
	$state_name   = $state_code;
	if ( function_exists( 'WC' ) && WC()->countries ) {
		$countries = WC()->countries->get_countries();
		if ( isset( $countries[ $country_code ] ) ) {
			$country_name = (string) $countries[ $country_code ];
		}
		if ( '' !== $state_code ) {
			$states = WC()->countries->get_states( $country_code );
			if ( is_array( $states ) && isset( $states[ $state_code ] ) ) {
				$state_name = (string) $states[ $state_code ];
			}
		}
	}

	$address_lines = array(
		(string) get_option( 'woocommerce_store_address', '' ),
		(string) get_option( 'woocommerce_store_address_2', '' ),
		trim( (string) get_option( 'woocommerce_store_postcode', '' ) . ' ' . (string) get_option( 'woocommerce_store_city', '' ) ),
	);
	$address       = trim( implode( ', ', array_filter( array_map( 'trim', $address_lines ) ) ) );

	return array(
		'name'    => (string) get_bloginfo( 'name' ),
		'address' => $address,
		'state'   => trim( $state_name ),
		'country' => trim( $country_name ),
		'phone'   => isset( $settings['merchant_phone'] ) ? trim( (string) $settings['merchant_phone'] ) : '',
		'email'   => (string) $store_email,
	);
}

/**
 * Renders one field of the model form: its caption plus the blank ruled lines
 * the consumer writes on. The number of lines is what gives each field its
 * writing space, so it is sized after the answer it expects — a postal address
 * needs more room than a date.
 *
 * @param string $label Field caption.
 * @param int    $lines Number of ruled lines to draw underneath.
 * @return string Escaped HTML for the field.
 */
function apg_withdrawal_print_model_form_field( $label, $lines = 1 ) {
	?>
	<div class="field">
		<span class="field-label"><?php echo esc_html( $label ); ?></span>
		<?php for ( $line = 0; $line < max( 1, (int) $lines ); $line++ ) : ?>
			<span class="field-line"></span>
		<?php endfor; ?>
	</div>
	<?php
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

		/*
		 * The writing space belongs to the ruled lines, never to the container:
		 * putting a min-height on `.field` only stretched the box the caption
		 * already filled, which left the rule sitting flush under the caption
		 * with nowhere to actually write.
		 */
		.field { margin: 20px 0; }
		.field-label { font-weight: 600; display: block; margin-bottom: 6px; }
		.field-line { display: block; height: 2.4em; border-bottom: 1px solid #999; }
		.print-actions { text-align: right; margin: 24px 0; }
		.print-actions button { font: inherit; padding: 8px 16px; cursor: pointer; }
		footer { font-size: 0.85em; color: #777; margin-top: 40px; border-top: 1px solid #ccc; padding-top: 12px; }

		@page { size: A4; margin: 16mm; }

		@media print {
			body { margin: 0; max-width: none; padding: 0; }
			.print-actions { display: none; }
			.addressee { background: #fff; }
			/* Keep a caption and its ruled lines on the same sheet. */
			.field, .addressee, footer { break-inside: avoid; page-break-inside: avoid; }
			h1, h2 { break-after: avoid; page-break-after: avoid; }
		}
	</style>
</head>
<body>
	<div class="print-actions">
		<button type="button" id="apg-withdrawal-print"><?php echo esc_html__( 'Print or save as PDF', 'apg-withdrawal-for-woocommerce' ); ?></button>
	</div>

	<h1><?php echo esc_html__( 'Model withdrawal form', 'apg-withdrawal-for-woocommerce' ); ?></h1>
	<p class="intro"><?php echo esc_html__( '(Complete and return this form only if you wish to withdraw from the contract.)', 'apg-withdrawal-for-woocommerce' ); ?></p>

	<h2><?php echo esc_html__( 'To', 'apg-withdrawal-for-woocommerce' ); ?>:</h2>
	<div class="addressee">
		<?php if ( '' !== $merchant['name'] ) : ?>
			<p><strong><?php echo esc_html( $merchant['name'] ); ?></strong>.</p>
		<?php endif; ?>
		<?php
		$location_parts = array_filter( array( $merchant['address'], $merchant['state'], $merchant['country'] ) );
		if ( ! empty( $location_parts ) ) :
			?>
			<p><?php echo esc_html( implode( '. ', $location_parts ) ); ?>.</p>
		<?php endif; ?>
		<?php if ( '' !== $merchant['phone'] ) : ?>
			<p><?php echo esc_html__( 'Phone', 'apg-withdrawal-for-woocommerce' ); ?>: <?php echo esc_html( $merchant['phone'] ); ?>.</p>
		<?php endif; ?>
		<?php if ( '' !== $merchant['email'] ) : ?>
			<p><?php echo esc_html__( 'Email', 'apg-withdrawal-for-woocommerce' ); ?>: <?php echo esc_html( $merchant['email'] ); ?>.</p>
		<?php endif; ?>
	</div>

	<p><?php echo esc_html__( 'I/We (*) hereby give notice that I/We (*) withdraw from my/our (*) contract of sale of the following goods (*) / for the provision of the following service (*):', 'apg-withdrawal-for-woocommerce' ); ?></p>

	<?php
	apg_withdrawal_print_model_form_field( __( 'Description of goods / service', 'apg-withdrawal-for-woocommerce' ), 3 );
	apg_withdrawal_print_model_form_field( __( 'Ordered on (*) / received on (*)', 'apg-withdrawal-for-woocommerce' ), 1 );
	apg_withdrawal_print_model_form_field( __( 'Name of consumer(s)', 'apg-withdrawal-for-woocommerce' ), 1 );
	apg_withdrawal_print_model_form_field( __( 'Address of consumer(s)', 'apg-withdrawal-for-woocommerce' ), 3 );
	apg_withdrawal_print_model_form_field( __( 'Signature of consumer(s) (only if this form is notified on paper)', 'apg-withdrawal-for-woocommerce' ), 2 );
	apg_withdrawal_print_model_form_field( __( 'Date', 'apg-withdrawal-for-woocommerce' ), 1 );
	?>

	<footer>
		<p>(*) <?php echo esc_html__( 'Delete as appropriate.', 'apg-withdrawal-for-woocommerce' ); ?></p>
		<p><?php echo esc_html__( 'Model withdrawal form pursuant to Annex I.B of Directive 2011/83/EU on consumer rights.', 'apg-withdrawal-for-woocommerce' ); ?></p>
	</footer>
	<script>
	( function () {
		/*
		 * Safari leaves the document blank once the print dialog is dismissed: the
		 * DOM survives but the page loses its rendering. Forcing a reflow brings the
		 * painted page back, and it is a no-op in browsers that never lost it.
		 *
		 * Both signals are wired because Safari is inconsistent about which one it
		 * emits when the dialog is cancelled rather than confirmed; `repainting`
		 * keeps the work to once per print cycle when it emits both.
		 */
		var repainting = false;

		var repaint = function () {
			if ( repainting || ! document.body ) {
				return;
			}

			repainting = true;

			window.requestAnimationFrame( function () {
				var body     = document.body;
				var previous = body.style.display;

				body.style.display = 'none';
				void body.offsetHeight;
				body.style.display = previous;
				repainting = false;
			} );
		};

		window.addEventListener( 'afterprint', repaint );

		if ( window.matchMedia ) {
			var printQuery = window.matchMedia( 'print' );
			var onChange   = function ( event ) {
				if ( ! event.matches ) {
					repaint();
				}
			};

			if ( printQuery.addEventListener ) {
				printQuery.addEventListener( 'change', onChange );
			} else if ( printQuery.addListener ) {
				printQuery.addListener( onChange );
			}
		}

		var button = document.getElementById( 'apg-withdrawal-print' );

		if ( ! button ) {
			return;
		}

		/*
		 * Safari also renders an empty sheet when window.print() runs before layout
		 * and web fonts have settled — which is what happened while this page
		 * auto-printed on load, and the bad state survived into any later print.
		 * Printing now only ever happens from this button (a real user gesture),
		 * and waits for the fonts and one paint before handing over to the browser.
		 */
		button.addEventListener( 'click', function () {
			var print = function () {
				window.requestAnimationFrame( function () {
					window.print();
				} );
			};

			if ( document.fonts && document.fonts.ready && document.fonts.ready.then ) {
				document.fonts.ready.then( print ).catch( print );
				return;
			}

			print();
		} );
	}() );
	</script>
</body>
</html>
	<?php
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
