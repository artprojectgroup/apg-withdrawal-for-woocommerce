<?php
/**
 * Double opt-in verification of guest withdrawal declarations.
 *
 * The public form already refuses any order whose billing e-mail does not match
 * the address typed by the visitor, but for a guest checkout that pair (order
 * number + e-mail) may be known to somebody other than the consumer. When the
 * merchant enables this option, a guest submission is not registered straight
 * away: it is parked server-side and a single-use link is e-mailed to the
 * address recorded on the order. Only following that link registers the
 * declaration.
 *
 * The moment that counts legally is the one at which the consumer sent the
 * declaration, not the one at which they opened their inbox, so the original
 * submission timestamp travels inside the parked payload and becomes the
 * request's date and the timestamp covered by the SHA-256 receipt hash.
 *
 * @package APG_Withdrawal_For_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Query argument carrying the single-use verification token.
 */
const APG_WITHDRAWAL_VERIFY_ARG = 'apg_withdrawal_verify';

/**
 * Returns whether the current submission has to be confirmed by e-mail before
 * it is registered. Logged-in customers are exempt: WordPress has already
 * authenticated them, and the form only offers them their own orders.
 *
 * @return bool
 */
function apg_withdrawal_guest_verification_required() {
	$settings = apg_withdrawal_get_settings();
	$enabled  = isset( $settings['guest_verification'] ) && '1' === (string) $settings['guest_verification'];

	/**
	 * Filters whether the current withdrawal submission requires e-mail
	 * verification before being registered.
	 *
	 * @param bool $required Resolved from the setting and the login state.
	 */
	return (bool) apply_filters( 'apg_withdrawal_guest_verification_required', $enabled && ! is_user_logged_in() );
}

/**
 * Returns the lifetime of a verification link.
 *
 * @return int Seconds the token stays valid.
 */
function apg_withdrawal_get_verification_ttl() {
	$ttl = (int) apply_filters( 'apg_withdrawal_verification_ttl', DAY_IN_SECONDS );

	return $ttl > 0 ? $ttl : DAY_IN_SECONDS;
}

/**
 * Builds the transient key for a token. The token itself is never stored: only
 * its SHA-256 digest is, so a dump of the options table cannot be replayed as a
 * valid confirmation link.
 *
 * @param string $token Raw token.
 * @return string Transient key.
 */
function apg_withdrawal_get_verification_key( $token ) {
	return 'apg_wfw_verify_' . hash( 'sha256', (string) $token );
}

/**
 * Parks a validated submission and returns the single-use token that will
 * register it.
 *
 * @param array $payload Sanitised submission data.
 * @return string Token, or empty string when it could not be stored.
 */
function apg_withdrawal_store_pending_verification( $payload ) {
	$token = wp_generate_password( 40, false, false );
	$saved = set_transient( apg_withdrawal_get_verification_key( $token ), $payload, apg_withdrawal_get_verification_ttl() );

	return $saved ? $token : '';
}

/**
 * Retrieves and immediately invalidates a parked submission, so a confirmation
 * link can never register two declarations.
 *
 * @param string $token Raw token taken from the URL.
 * @return array|false Parked payload, or false when the token is unknown or expired.
 */
function apg_withdrawal_consume_pending_verification( $token ) {
	$token = preg_replace( '/[^A-Za-z0-9]/', '', (string) $token );

	if ( '' === $token ) {
		return false;
	}

	$key     = apg_withdrawal_get_verification_key( $token );
	$payload = get_transient( $key );

	if ( ! is_array( $payload ) || empty( $payload['order'] ) ) {
		return false;
	}

	delete_transient( $key );

	return $payload;
}

/**
 * Returns the absolute URL that registers a parked declaration.
 *
 * @param string $token     Verification token.
 * @param string $fallback  URL of the form the submission came from.
 * @return string
 */
function apg_withdrawal_get_verification_url( $token, $fallback = '' ) {
	$page_id = apg_withdrawal_get_page_id();
	$base    = $page_id ? get_permalink( $page_id ) : '';

	if ( ! $base ) {
		$base = $fallback ? $fallback : home_url( '/' );
	}

	return add_query_arg( APG_WITHDRAWAL_VERIFY_ARG, rawurlencode( $token ), $base );
}

/**
 * E-mails the confirmation link to the address recorded on the order.
 *
 * @param array  $payload Parked submission data.
 * @param string $url     Confirmation URL.
 * @return array{attempted: bool, accepted: bool|null, accepted_at: string, error: string}
 */
function apg_withdrawal_send_verification_email( $payload, $url ) {
	$name    = isset( $payload['name'] ) ? (string) $payload['name'] : '';
	$email   = isset( $payload['email'] ) ? (string) $payload['email'] : '';
	$order   = isset( $payload['order'] ) ? (string) $payload['order'] : '';
	$subject = sprintf(
		/* translators: %s: site name. */
		__( '[%s] Confirm your withdrawal request', 'apg-withdrawal-for-woocommerce' ),
		wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
	);

	$lines = array(
		sprintf(
			/* translators: %s: customer name. */
			__( 'Hello %s,', 'apg-withdrawal-for-woocommerce' ),
			$name
		),
		'',
		sprintf(
			/* translators: %s: order reference. */
			__( 'We have received a withdrawal request for order %s. To confirm that it was you who sent it, open the following link:', 'apg-withdrawal-for-woocommerce' ),
			$order
		),
		'',
		$url,
		'',
		sprintf(
			/* translators: %s: number of hours the link stays valid. */
			__( 'The link can be used once and expires in %s hours. Your request will be dated at the moment you submitted the form, not at the moment you open this link.', 'apg-withdrawal-for-woocommerce' ),
			number_format_i18n( apg_withdrawal_get_verification_ttl() / HOUR_IN_SECONDS )
		),
		'',
		__( 'If you did not request this, you can ignore this message: nothing has been registered.', 'apg-withdrawal-for-woocommerce' ),
	);

	return apg_withdrawal_send_with_delivery_capture(
		function () use ( $email, $subject, $lines ) {
			wp_mail( $email, $subject, implode( "\r\n", $lines ), array( 'Content-Type: text/plain; charset=UTF-8' ) );
		}
	);
}

/**
 * Registers a parked declaration when the consumer opens the confirmation link,
 * then redirects to the form with the resulting notice so a page refresh cannot
 * replay the request.
 *
 * @return void
 */
function apg_withdrawal_handle_verification_link() {
	if ( is_admin() || empty( $_GET[ APG_WITHDRAWAL_VERIFY_ARG ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The single-use token in the URL is the credential; it is verified below and destroyed on use.
		return;
	}

	$token   = sanitize_text_field( wp_unslash( $_GET[ APG_WITHDRAWAL_VERIFY_ARG ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- See above.
	$payload = apg_withdrawal_consume_pending_verification( $token );

	if ( ! $payload ) {
		apg_withdrawal_redirect_with_notice( 'verify_invalid' );
		exit;
	}

	$payload['verified'] = true;

	apg_withdrawal_redirect_with_notice( apg_withdrawal_process_submission_data( $payload ) );
}
add_action( 'template_redirect', 'apg_withdrawal_handle_verification_link' );
