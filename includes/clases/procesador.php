<?php
/**
 * Procesamiento de solicitudes.
 *
 * @package APG_Withdrawal_For_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns the client IP address used to key the rate limiter.
 *
 * Only `REMOTE_ADDR` is read by default, because it is the one value the caller
 * cannot choose: `X-Forwarded-For` and friends are claims the client sends, and
 * a limiter keyed on them is no limiter at all — a fresh header per request
 * lands in a fresh bucket every time. A site that genuinely sits behind a proxy
 * opts its own header in through `apg_withdrawal_trusted_proxy_headers`, which
 * is a statement about that site's infrastructure and cannot be made by the
 * visitor.
 *
 * @return string Client IP, or empty string when none could be determined.
 */
function apg_withdrawal_get_client_ip() {
	$ip = '';

	if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
		$candidate = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		$ip        = filter_var( $candidate, FILTER_VALIDATE_IP ) ? $candidate : '';
	}

	/**
	 * Filters the proxy headers the site trusts to carry the real client IP.
	 *
	 * Empty by default. Add e.g. `HTTP_CF_CONNECTING_IP` only when every request
	 * reaching PHP is known to pass through that proxy, otherwise the header can
	 * be forged and the rate limiter bypassed.
	 *
	 * @param array $headers `$_SERVER` keys to inspect, in order of preference.
	 */
	$trusted = (array) apply_filters( 'apg_withdrawal_trusted_proxy_headers', array() );

	foreach ( $trusted as $header ) {
		if ( empty( $_SERVER[ $header ] ) ) {
			continue;
		}

		$value = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );

		if ( false !== strpos( $value, ',' ) ) {
			$value = trim( strtok( $value, ',' ) );
		}

		if ( filter_var( $value, FILTER_VALIDATE_IP ) ) {
			$ip = $value;
			break;
		}
	}

	return (string) apply_filters( 'apg_withdrawal_client_ip', $ip );
}

/**
 * Throttles repeated public-form submissions from the same IP + email pair.
 * Default policy: 5 attempts every 10 minutes. Both values are filterable via
 * `apg_withdrawal_rate_limit_max` and `apg_withdrawal_rate_limit_window`, and
 * the check is bypassed entirely when either is set to a non-positive value
 * (useful for testing).
 *
 * Each entry point counts in its own bucket, so the two steps of one ordinary
 * submission do not eat each other's allowance while every step still has a
 * ceiling of its own.
 *
 * @param string $email  Customer email address used for keying.
 * @param string $bucket Counter to charge: 'submit', 'preview' or 'lookup'.
 * @return bool True when the visitor has exceeded the limit.
 */
function apg_withdrawal_is_rate_limited( $email, $bucket = 'submit' ) {
	$ip = apg_withdrawal_get_client_ip();
	if ( '' === $ip ) {
		return false;
	}

	$limit  = (int) apply_filters( 'apg_withdrawal_rate_limit_max', 5, $bucket );
	$window = (int) apply_filters( 'apg_withdrawal_rate_limit_window', 10 * MINUTE_IN_SECONDS, $bucket );

	if ( $limit < 1 || $window < 1 ) {
		return false;
	}

	$key   = 'apg_wfw_rl_' . md5( sanitize_key( $bucket ) . '|' . $ip . '|' . strtolower( (string) $email ) );
	$count = (int) get_transient( $key );

	if ( $count >= $limit ) {
		return true;
	}

	set_transient( $key, $count + 1, $window );
	return false;
}

/**
 * Detects whether the current request carries a filled honeypot field. The
 * field is hidden visually and from assistive technology, so any non-empty
 * value indicates an automated submission. Callers should return a benign
 * "success" notice on a positive result so bots cannot probe the defence.
 *
 * @return bool True when the honeypot was filled.
 */
function apg_withdrawal_honeypot_was_filled() {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Inspection only, no state change; the submission nonce is verified by every caller: procesador.php:412, procesador.php:555 and formulario.php:509.
	return ! empty( $_POST['apg_withdrawal_website'] );
}

/**
 * Reports whether a string is a well-formed `Y-m-d H:i:s` timestamp denoting a
 * real calendar date. Used to decide whether the declaration timestamp carried
 * by a verified submission can be trusted, or a fresh one has to be minted.
 *
 * @param string $value Candidate timestamp.
 * @return bool
 */
function apg_withdrawal_is_mysql_datetime( $value ) {
	$value = (string) $value;

	if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})$/', $value, $parts ) ) {
		return false;
	}

	if ( ! wp_checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1], $value ) ) {
		return false;
	}

	return (int) $parts[4] < 24 && (int) $parts[5] < 60 && (int) $parts[6] < 60;
}

/**
 * Redirects the browser to the withdrawal form page with a notice query argument.
 *
 * @param string $notice Notice key to append as a query parameter.
 * @return void
 */
function apg_withdrawal_redirect_with_notice( $notice ) {
	$target = apg_withdrawal_get_page_id() ? get_permalink( apg_withdrawal_get_page_id() ) : ( wp_get_referer() ? wp_get_referer() : home_url() );

	wp_safe_redirect(
		add_query_arg(
			array(
				'apg_withdrawal_notice' => sanitize_key( $notice ),
			),
			$target
		)
	);
	exit;
}


/**
 * Builds the fingerprint that identifies a declaration by what it declares:
 * the order, the address that declared it, the scope and, for a partial
 * withdrawal, the line items with the units claimed for each.
 *
 * The moment of the declaration is deliberately left out. The receipt hash
 * includes it, because it attests to one submission; this one answers whether
 * two submissions say the same thing, and a timestamp would make every repeat
 * look new — which is exactly how a double click ended up registering three
 * requests.
 *
 * The line items are sorted before hashing, so a different order of the same
 * selection yields the same fingerprint.
 *
 * @param int    $wc_order_id WooCommerce order ID the request belongs to.
 * @param string $email       Customer email address.
 * @param string $scope       'full' or 'partial'.
 * @param array  $products    Line-item IDs in scope.
 * @param array  $quantities  Units claimed, keyed by line-item ID.
 * @return string SHA-256 fingerprint.
 */
function apg_withdrawal_submission_fingerprint( $wc_order_id, $email, $scope, $products, $quantities ) {
	$lines = array();

	foreach ( (array) $products as $item_id ) {
		$key     = (string) $item_id;
		$lines[] = $key . ':' . ( isset( $quantities[ $key ] ) ? (int) $quantities[ $key ] : 0 );
	}

	sort( $lines, SORT_STRING );

	return hash(
		'sha256',
		implode(
			'|',
			array(
				(int) $wc_order_id,
				strtolower( trim( (string) $email ) ),
				(string) $scope,
				implode( ',', $lines ),
			)
		)
	);
}

/**
 * Rebuilds the fingerprint of a request already stored, from its own metadata.
 *
 * Deriving it instead of storing it is what makes the check work on requests
 * registered before this existed: there is nothing to migrate and no request
 * is left unprotected.
 *
 * @param int $post_id Withdrawal request post ID.
 * @return string SHA-256 fingerprint.
 */
function apg_withdrawal_request_fingerprint( $post_id ) {
	$products   = get_post_meta( $post_id, '_apg_withdrawal_products', true );
	$quantities = get_post_meta( $post_id, '_apg_withdrawal_quantities', true );

	return apg_withdrawal_submission_fingerprint(
		(int) get_post_meta( $post_id, '_apg_withdrawal_wc_order_id', true ),
		(string) get_post_meta( $post_id, '_apg_withdrawal_email', true ),
		(string) get_post_meta( $post_id, '_apg_withdrawal_scope', true ),
		is_array( $products ) ? $products : array(),
		is_array( $quantities ) ? $quantities : array()
	);
}

/**
 * Finds the requests already on file that declare exactly the same thing.
 *
 * Only the requests of that one order are read, which is a handful at most, and
 * each is compared by fingerprint. Two of them do not count, because in both
 * cases the customer is entitled to send the declaration again: one the shop
 * rejected, and one somebody moved to the trash.
 *
 * @param int    $wc_order_id WooCommerce order ID.
 * @param string $fingerprint Fingerprint to match.
 * @param int    $exclude_id  Request to leave out, normally the one just created.
 * @return array Matching request IDs, oldest first.
 */
function apg_withdrawal_find_matching_requests( $wc_order_id, $fingerprint, $exclude_id = 0 ) {
	if ( ! $wc_order_id ) {
		return array();
	}

	$candidates = get_posts(
		array(
			'post_type'        => 'apg_withdrawal',
			'post_status'      => 'publish',
			'numberposts'      => 100,
			'fields'           => 'ids',
			'orderby'          => 'ID',
			'order'            => 'ASC',
			'no_found_rows'    => true,
			'suppress_filters' => false,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Bounded to one order's requests, which is a handful; the meta is indexed by meta_key.
			'meta_query'       => array(
				array(
					'key'   => '_apg_withdrawal_wc_order_id',
					'value' => (int) $wc_order_id,
				),
			),
		)
	);

	// One trip for the metadata of every candidate, instead of one per field per
	// candidate: `fields => ids` returns IDs with no meta attached.
	update_meta_cache( 'post', $candidates );

	$matches = array();

	foreach ( $candidates as $candidate_id ) {
		$candidate_id = (int) $candidate_id;

		if ( $exclude_id && $candidate_id === (int) $exclude_id ) {
			continue;
		}

		if ( 'rejected' === get_post_meta( $candidate_id, '_apg_withdrawal_status', true ) ) {
			continue;
		}

		if ( apg_withdrawal_request_fingerprint( $candidate_id ) !== $fingerprint ) {
			continue;
		}

		$matches[] = $candidate_id;
	}

	return $matches;
}

/**
 * Validates and persists a withdrawal request from the submitted form data.
 *
 * When guest e-mail verification is enabled and the submission has not been
 * confirmed yet, the validated payload is parked instead of being registered
 * and a single-use link is sent to the address recorded on the order — see
 * `includes/clases/verificacion.php`.
 *
 * @param array $data Associative array with keys 'order', 'name', 'email', 'phone', 'scope',
 *                    'details', 'products', 'quantities', 'iban' and, for confirmed guest
 *                    submissions, 'verified' plus the original 'declared_at' timestamp.
 * @return string Result key: 'success', 'verify_sent', 'fields', 'email', 'iban', 'order',
 *                'expired', 'throttle' or 'general'.
 */
function apg_withdrawal_process_submission_data( $data ) {
	if ( apg_withdrawal_honeypot_was_filled() ) {
		return 'success';
	}

	$order_ref   = isset( $data['order'] ) ? sanitize_text_field( $data['order'] ) : '';
	$name        = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
	$email       = isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '';
	$phone       = isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : '';
	$scope       = isset( $data['scope'] ) ? sanitize_key( $data['scope'] ) : 'full';
	$details     = isset( $data['details'] ) ? sanitize_textarea_field( $data['details'] ) : '';
	$products    = isset( $data['products'] ) && is_array( $data['products'] ) ? array_map( 'sanitize_text_field', $data['products'] ) : array();
	$raw_amounts = isset( $data['quantities'] ) && is_array( $data['quantities'] ) ? $data['quantities'] : array();
	$iban        = isset( $data['iban'] ) ? apg_withdrawal_normalize_iban( $data['iban'] ) : '';
	$verified    = ! empty( $data['verified'] );
	$settings    = apg_withdrawal_get_settings();

	if ( apg_withdrawal_is_rate_limited( $email ) ) {
		return 'throttle';
	}

	if ( empty( $order_ref ) || empty( $name ) || empty( $email ) ) {
		return 'fields';
	}

	if ( ! is_email( $email ) ) {
		return 'email';
	}

	if ( 'partial' === $scope && empty( $products ) ) {
		return 'fields';
	}

	$iban_mode = isset( $settings['iban_field'] ) ? (string) $settings['iban_field'] : 'hidden';

	if ( 'hidden' === $iban_mode ) {
		$iban = '';
	} elseif ( 'required' === $iban_mode && '' === $iban ) {
		return 'fields';
	}

	if ( '' !== $iban && ! apg_withdrawal_validate_iban( $iban ) ) {
		return 'iban';
	}

	$validation = apg_withdrawal_validate_order( $order_ref, $email, $settings );

	if ( ! $validation['valid'] ) {
		return $validation['error'];
	}

	$quantities = 'partial' === $scope
		? apg_withdrawal_sanitize_item_quantities( $validation['order'], $products, $raw_amounts )
		: array();

	if ( 'partial' === $scope && empty( $quantities ) ) {
		return 'fields';
	}

	// A declaration that is already on file is not registered twice. The check
	// runs before the verification branch as well, so a repeat does not even
	// send a second confirmation link.
	$fingerprint = apg_withdrawal_submission_fingerprint( $validation['order_id'], $email, $scope, $products, $quantities );

	if ( apg_withdrawal_find_matching_requests( $validation['order_id'], $fingerprint ) ) {
		return 'duplicate';
	}

	// The declaration is dated when the consumer sent it. A submission that
	// travelled through the e-mail verification loop carries the moment that was
	// recorded server-side before the link was issued, so it survives the round
	// trip intact; any other path is dated now. The timestamp is only honoured
	// for an already-verified payload, which comes from a server-side transient
	// and never from the browser, so it cannot be used to backdate a request.
	$declared_at = $verified && isset( $data['declared_at'] ) ? sanitize_text_field( $data['declared_at'] ) : '';

	if ( ! apg_withdrawal_is_mysql_datetime( $declared_at ) ) {
		$declared_at = current_time( 'mysql', true );
	}

	if ( ! $verified && apg_withdrawal_guest_verification_required() ) {
		$payload = array(
			'order'       => $order_ref,
			'name'        => $name,
			'email'       => $email,
			'phone'       => $phone,
			'scope'       => $scope,
			'details'     => $details,
			'products'    => $products,
			'quantities'  => $quantities,
			'iban'        => $iban,
			'declared_at' => $declared_at,
		);

		$token = apg_withdrawal_store_pending_verification( $payload );

		if ( ! $token ) {
			return 'general';
		}

		apg_withdrawal_send_verification_email( $payload, apg_withdrawal_get_verification_url( $token, apg_withdrawal_get_current_form_url() ) );

		return 'verify_sent';
	}

	$post_id = wp_insert_post(
		array(
			'post_type'   => 'apg_withdrawal',
			'post_status' => 'publish',
			'post_title'  => sprintf(
				/* translators: 1: order number, 2: customer name. */
				__( 'Withdrawal request %1$s - %2$s', 'apg-withdrawal-for-woocommerce' ),
				$order_ref
				,
				$name
			),
			'post_content'  => $details,
			'post_date_gmt' => $declared_at,
			'post_date'     => get_date_from_gmt( $declared_at ),
		),
		true
	);

	if ( ! is_wp_error( $post_id ) && $post_id ) {
		update_post_meta( $post_id, '_apg_withdrawal_name', $name );
		update_post_meta( $post_id, '_apg_withdrawal_order', $order_ref );
		update_post_meta( $post_id, '_apg_withdrawal_email', $email );
		update_post_meta( $post_id, '_apg_withdrawal_scope', $scope );
		update_post_meta( $post_id, '_apg_withdrawal_status', 'pending' );
		update_post_meta( $post_id, '_apg_withdrawal_wc_order_id', $validation['order_id'] );
		update_post_meta( $post_id, '_apg_withdrawal_deadline_source', $validation['source'] );
		update_post_meta( $post_id, '_apg_withdrawal_deadline_date', $validation['date'] );
		update_post_meta( $post_id, '_apg_withdrawal_products', $products );
		update_post_meta( $post_id, '_apg_withdrawal_quantities', $quantities );

		if ( $phone ) {
			update_post_meta( $post_id, '_apg_withdrawal_phone', $phone );
		}

		if ( '' !== $iban ) {
			update_post_meta( $post_id, '_apg_withdrawal_iban', $iban );
		}

		if ( $verified ) {
			update_post_meta( $post_id, '_apg_withdrawal_email_verified', current_time( 'mysql', true ) );
		}

		if ( '1' === $settings['store_ip'] ) {
			update_post_meta( $post_id, '_apg_withdrawal_ip', WC_Geolocation::get_ip_address() );
		}

		if ( '1' === $settings['store_user_agent'] ) {
			update_post_meta( $post_id, '_apg_withdrawal_user_agent', isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '' );
		}

		if ( $validation['expired_warning'] ) {
			update_post_meta( $post_id, '_apg_withdrawal_expired_warning', '1' );
		}

		// Two clicks can both get past the check above before either has written
		// anything, so the race is settled here, once the metadata the
		// fingerprint is built from is on file: of the requests that say the
		// same thing, the oldest ID stands and the rest withdraw. This runs
		// before the order note and the notifications, so a losing copy leaves
		// no trace behind it.
		$earlier = apg_withdrawal_find_matching_requests( $validation['order_id'], $fingerprint, $post_id );

		foreach ( $earlier as $earlier_id ) {
			if ( $earlier_id < $post_id ) {
				wp_delete_post( $post_id, true );

				return 'duplicate';
			}
		}

		// SHA-256 receipt hash for legal traceability. The canonical form is a
		// pipe-separated concatenation of the receipt's user-visible fields plus
		// the GMT timestamp recorded with it, so the consumer can reconstruct
		// and verify the digest from the data shown in the acknowledgement email.
		// Partial requests fold each line item into an `id:units` pair so the
		// digest also covers the quantities that were declared.
		$hashed_products = array();

		foreach ( $products as $item_id ) {
			$hashed_products[] = isset( $quantities[ (string) $item_id ] )
				? $item_id . ':' . $quantities[ (string) $item_id ]
				: (string) $item_id;
		}

		$receipt_canonical = implode(
			'|',
			array(
				$name,
				$email,
				$order_ref,
				$scope,
				implode( ',', $hashed_products ),
				$details,
				$declared_at,
			)
		);
		$receipt_hash      = hash( 'sha256', $receipt_canonical );
		update_post_meta( $post_id, '_apg_withdrawal_receipt_hash', $receipt_hash );
		update_post_meta( $post_id, '_apg_withdrawal_receipt_hash_timestamp', $declared_at );

		apg_withdrawal_add_order_note( $validation['order'], $post_id, $scope, $details );
		apg_withdrawal_maybe_set_order_status( $validation['order'], $post_id );

		// Customer acknowledgement: capture delivery status (mailer acceptance,
		// not real recipient delivery) as legal evidence under Art. 16 bis(8).
		$initial_delivery = apg_withdrawal_send_with_delivery_capture(
			function () use ( $email, $name, $order_ref, $scope, $post_id, $phone, $declared_at ) {
				apg_withdrawal_send_customer_email( $email, $name, $order_ref, $scope, $post_id, get_date_from_gmt( $declared_at ), $phone );
			}
		);
		update_post_meta( $post_id, '_apg_withdrawal_initial_email_delivery', $initial_delivery );

		apg_withdrawal_send_admin_email( $post_id, $name, $email, $order_ref, $scope, $details, $settings['notification_email'], $phone );

		/**
		 * Fires once a withdrawal declaration has been fully registered: metadata
		 * written, order note added and notifications dispatched.
		 *
		 * @param int   $post_id Withdrawal request post ID.
		 * @param array $data    Submission payload as received by the processor.
		 */
		do_action( 'apg_withdrawal_request_registered', $post_id, $data );
	} else {
		return 'general';
	}

	return 'success';
}

/**
 * Reads the hidden fields carried by the confirmation step into the shape
 * expected by {@see apg_withdrawal_process_submission_data()}. Every value is
 * only unslashed here; the processor sanitises each field individually with the
 * callback that matches its type.
 *
 * Callers must have verified `apg_withdrawal_confirm_nonce` before invoking it.
 *
 * @return array Submission payload.
 */
function apg_withdrawal_read_confirmation_post() {
	// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified by every caller before this runs: procesador.php:412, procesador.php:555 and formulario.php:509. Each field is sanitised by the matching callback inside apg_withdrawal_process_submission_data().
	return array(
		'order'       => isset( $_POST['apg_withdrawal_order'] ) ? wp_unslash( $_POST['apg_withdrawal_order'] ) : '',
		'name'        => isset( $_POST['apg_withdrawal_name'] ) ? wp_unslash( $_POST['apg_withdrawal_name'] ) : '',
		'email'       => isset( $_POST['apg_withdrawal_email'] ) ? wp_unslash( $_POST['apg_withdrawal_email'] ) : '',
		'phone'       => isset( $_POST['apg_withdrawal_phone'] ) ? wp_unslash( $_POST['apg_withdrawal_phone'] ) : '',
		'scope'       => isset( $_POST['apg_withdrawal_scope'] ) ? wp_unslash( $_POST['apg_withdrawal_scope'] ) : 'full',
		'details'     => isset( $_POST['apg_withdrawal_details'] ) ? wp_unslash( $_POST['apg_withdrawal_details'] ) : '',
		'iban'        => isset( $_POST['apg_withdrawal_iban'] ) ? wp_unslash( $_POST['apg_withdrawal_iban'] ) : '',
		'products'    => isset( $_POST['apg_withdrawal_products'] ) && is_array( $_POST['apg_withdrawal_products'] ) ? wp_unslash( $_POST['apg_withdrawal_products'] ) : array(),
		'quantities'  => isset( $_POST['apg_withdrawal_quantities'] ) && is_array( $_POST['apg_withdrawal_quantities'] ) ? wp_unslash( $_POST['apg_withdrawal_quantities'] ) : array(),
	);
	// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
}

/**
 * Handles the non-AJAX confirmation form submission and redirects with a result notice.
 *
 * @return void
 */
function apg_withdrawal_confirm_submission() {
	if ( ! isset( $_POST['apg_withdrawal_confirm_nonce'] ) ) {
		apg_withdrawal_redirect_with_notice( 'nonce' );
		exit;
	}

	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['apg_withdrawal_confirm_nonce'] ) ), 'apg_withdrawal_confirm_action' ) ) {
		apg_withdrawal_redirect_with_notice( 'nonce' );
		exit;
	}

	apg_withdrawal_redirect_with_notice( apg_withdrawal_process_submission_data( apg_withdrawal_read_confirmation_post() ) );
}
add_action( 'admin_post_apg_withdrawal_confirm', 'apg_withdrawal_confirm_submission' );
add_action( 'admin_post_nopriv_apg_withdrawal_confirm', 'apg_withdrawal_confirm_submission' );

/**
 * Handles the AJAX request for the withdrawal form preview step and returns HTML.
 *
 * @return void
 */
function apg_withdrawal_ajax_preview() {
	if ( ! isset( $_POST['apg_withdrawal_preview_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['apg_withdrawal_preview_nonce'] ) ), 'apg_withdrawal_preview_action' ) ) {
		wp_send_json_error(
			array(
				'message' => apg_withdrawal_render_notice_html( 'nonce' ),
			)
		);
	}

	if ( apg_withdrawal_honeypot_was_filled() ) {
		wp_send_json_success(
			array(
				'html' => apg_withdrawal_render_notice_html( 'success' ),
			)
		);
	}

	$name     = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$phone    = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
	$order_id = isset( $_POST['order_id'] ) ? sanitize_text_field( wp_unslash( $_POST['order_id'] ) ) : '';
	$scope    = isset( $_POST['scope'] ) ? sanitize_key( wp_unslash( $_POST['scope'] ) ) : 'full';
	$details  = isset( $_POST['details'] ) ? sanitize_textarea_field( wp_unslash( $_POST['details'] ) ) : '';
	$acceptance = isset( $_POST['acceptance'] ) ? sanitize_text_field( wp_unslash( $_POST['acceptance'] ) ) : '';
	$products = isset( $_POST['products'] ) && is_array( $_POST['products'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['products'] ) ) : array();
	$amounts  = isset( $_POST['quantities'] ) && is_array( $_POST['quantities'] ) ? wp_unslash( $_POST['quantities'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cast to bounded integers by apg_withdrawal_sanitize_item_quantities().
	$iban     = isset( $_POST['iban'] ) ? apg_withdrawal_normalize_iban( wp_unslash( $_POST['iban'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Normaliser strips every character outside [A-Z0-9].
	$settings = apg_withdrawal_get_settings();

	// The preview answers whether an order number and an e-mail address belong
	// together, so it gets the same ceiling as the submission it precedes.
	if ( apg_withdrawal_is_rate_limited( $email, 'preview' ) ) {
		wp_send_json_error(
			array(
				'message' => apg_withdrawal_render_notice_html( 'throttle' ),
			)
		);
	}

	if ( empty( $name ) || empty( $email ) || empty( $order_id ) || empty( $acceptance ) ) {
		wp_send_json_error(
			array(
				'message' => apg_withdrawal_render_notice_html( 'fields' ),
			)
		);
	}

	if ( ! is_email( $email ) ) {
		wp_send_json_error(
			array(
				'message' => apg_withdrawal_render_notice_html( 'email' ),
			)
		);
	}

	if ( 'partial' === $scope && empty( $products ) ) {
		wp_send_json_error(
			array(
				'message' => apg_withdrawal_render_notice_html( 'fields' ),
			)
		);
	}

	$iban_mode = isset( $settings['iban_field'] ) ? (string) $settings['iban_field'] : 'hidden';

	if ( 'hidden' === $iban_mode ) {
		$iban = '';
	} elseif ( 'required' === $iban_mode && '' === $iban ) {
		wp_send_json_error(
			array(
				'message' => apg_withdrawal_render_notice_html( 'fields' ),
			)
		);
	}

	if ( '' !== $iban && ! apg_withdrawal_validate_iban( $iban ) ) {
		wp_send_json_error(
			array(
				'message' => apg_withdrawal_render_notice_html( 'iban' ),
			)
		);
	}

	$validation = apg_withdrawal_validate_order( $order_id, $email, $settings );

	if ( ! $validation['valid'] ) {
		wp_send_json_error(
			array(
				'message' => apg_withdrawal_render_notice_html( $validation['error'] ),
			)
		);
	}

	$quantities = 'partial' === $scope
		? apg_withdrawal_sanitize_item_quantities( $validation['order'], $products, $amounts )
		: array();

	if ( 'partial' === $scope && empty( $quantities ) ) {
		wp_send_json_error(
			array(
				'message' => apg_withdrawal_render_notice_html( 'fields' ),
			)
		);
	}

	$html  = apg_withdrawal_render_notice_html( '' );
	$html .= apg_withdrawal_render_confirmation_form( $name, $email, $order_id, $scope, $details, $products, $validation['order'], $settings, apg_withdrawal_get_current_form_url(), $phone, $validation['expired_warning'], $quantities, $iban );

	wp_send_json_success(
		array(
			'html' => $html,
		)
	);
}
add_action( 'wp_ajax_apg_withdrawal_preview_ajax', 'apg_withdrawal_ajax_preview' );
add_action( 'wp_ajax_nopriv_apg_withdrawal_preview_ajax', 'apg_withdrawal_ajax_preview' );

/**
 * Handles the AJAX final confirmation submission and returns a JSON success or error response.
 *
 * @return void
 */
function apg_withdrawal_ajax_confirm() {
	if ( ! isset( $_POST['apg_withdrawal_confirm_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['apg_withdrawal_confirm_nonce'] ) ), 'apg_withdrawal_confirm_action' ) ) {
		wp_send_json_error(
			array(
				'message' => apg_withdrawal_render_notice_html( 'nonce' ),
			)
		);
	}

	$notice = apg_withdrawal_process_submission_data( apg_withdrawal_read_confirmation_post() );

	if ( ! apg_withdrawal_notice_is_final( $notice ) ) {
		wp_send_json_error(
			array(
				'message' => apg_withdrawal_render_notice_html( $notice ),
			)
		);
	}

	wp_send_json_success(
		array(
			'html' => apg_withdrawal_render_notice_html( $notice ),
		)
	);
}
add_action( 'wp_ajax_apg_withdrawal_confirm_ajax', 'apg_withdrawal_ajax_confirm' );
add_action( 'wp_ajax_nopriv_apg_withdrawal_confirm_ajax', 'apg_withdrawal_ajax_confirm' );
