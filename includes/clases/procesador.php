<?php
/**
 * Procesamiento de solicitudes.
 *
 * @package APG_Withdrawal_For_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns the client IP address, inspecting common reverse-proxy headers and
 * falling back to `REMOTE_ADDR`. The first hop is trusted; merchants behind
 * multiple proxies can normalise via the `apg_withdrawal_client_ip` filter.
 *
 * @return string Client IP, or empty string when none could be determined.
 */
function apg_withdrawal_get_client_ip() {
	$candidates = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' );

	foreach ( $candidates as $header ) {
		if ( empty( $_SERVER[ $header ] ) ) {
			continue;
		}
		$value = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
		if ( false !== strpos( $value, ',' ) ) {
			$value = trim( strtok( $value, ',' ) );
		}
		if ( filter_var( $value, FILTER_VALIDATE_IP ) ) {
			return (string) apply_filters( 'apg_withdrawal_client_ip', $value );
		}
	}

	return '';
}

/**
 * Throttles repeated public-form submissions from the same IP + email pair.
 * Default policy: 5 attempts every 10 minutes. Both values are filterable via
 * `apg_withdrawal_rate_limit_max` and `apg_withdrawal_rate_limit_window`, and
 * the check is bypassed entirely when either is set to a non-positive value
 * (useful for testing).
 *
 * @param string $email Customer email address used for keying.
 * @return bool True when the visitor has exceeded the limit.
 */
function apg_withdrawal_is_rate_limited( $email ) {
	$ip = apg_withdrawal_get_client_ip();
	if ( '' === $ip ) {
		return false;
	}

	$limit  = (int) apply_filters( 'apg_withdrawal_rate_limit_max', 5 );
	$window = (int) apply_filters( 'apg_withdrawal_rate_limit_window', 10 * MINUTE_IN_SECONDS );

	if ( $limit < 1 || $window < 1 ) {
		return false;
	}

	$key   = 'apg_wfw_rl_' . md5( $ip . '|' . strtolower( (string) $email ) );
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
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Inspection only; submission nonce verified by caller.
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
	// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified by every caller; each field is sanitised inside apg_withdrawal_process_submission_data().
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
	if ( ! isset( $_POST['apg_withdrawal_confirm_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['apg_withdrawal_confirm_nonce'] ) ), 'apg_withdrawal_confirm_action' ) ) {
		apg_withdrawal_redirect_with_notice( 'nonce' );
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

	if ( ! in_array( $notice, array( 'success', 'verify_sent' ), true ) ) {
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
