<?php
/**
 * Procesamiento de solicitudes.
 *
 * @package APG_Withdrawal_For_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

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
 * @param array $data Associative array with keys 'order', 'name', 'email', 'phone', 'scope', 'details' and 'products'.
 * @return string Result key: 'success', 'fields', 'email', 'order', 'expired' or 'general'.
 */
function apg_withdrawal_process_submission_data( $data ) {
	$order_ref = isset( $data['order'] ) ? sanitize_text_field( $data['order'] ) : '';
	$name      = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
	$email     = isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '';
	$phone     = isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : '';
	$scope     = isset( $data['scope'] ) ? sanitize_key( $data['scope'] ) : 'full';
	$details   = isset( $data['details'] ) ? sanitize_textarea_field( $data['details'] ) : '';
	$products  = isset( $data['products'] ) && is_array( $data['products'] ) ? array_map( 'sanitize_text_field', $data['products'] ) : array();
	$settings  = apg_withdrawal_get_settings();

	if ( empty( $order_ref ) || empty( $name ) || empty( $email ) ) {
		return 'fields';
	}

	if ( ! is_email( $email ) ) {
		return 'email';
	}

	if ( 'partial' === $scope && empty( $products ) ) {
		return 'fields';
	}

	$validation = apg_withdrawal_validate_order( $order_ref, $email, $settings );

	if ( ! $validation['valid'] ) {
		return $validation['error'];
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
			'post_content' => $details,
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

		if ( $phone ) {
			update_post_meta( $post_id, '_apg_withdrawal_phone', $phone );
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
		$receipt_timestamp = current_time( 'mysql', true );
		$receipt_canonical = implode(
			'|',
			array(
				$name,
				$email,
				$order_ref,
				$scope,
				implode( ',', $products ),
				$details,
				$receipt_timestamp,
			)
		);
		$receipt_hash      = hash( 'sha256', $receipt_canonical );
		update_post_meta( $post_id, '_apg_withdrawal_receipt_hash', $receipt_hash );
		update_post_meta( $post_id, '_apg_withdrawal_receipt_hash_timestamp', $receipt_timestamp );

		apg_withdrawal_add_order_note( $validation['order'], $post_id, $scope, $details );

		// Customer acknowledgement: capture delivery status (mailer acceptance,
		// not real recipient delivery) as legal evidence under Art. 16 bis(8).
		$initial_delivery = apg_withdrawal_send_with_delivery_capture(
			function () use ( $email, $name, $order_ref, $scope, $post_id, $phone ) {
				apg_withdrawal_send_customer_email( $email, $name, $order_ref, $scope, $post_id, current_time( 'mysql' ), $phone );
			}
		);
		update_post_meta( $post_id, '_apg_withdrawal_initial_email_delivery', $initial_delivery );

		apg_withdrawal_send_admin_email( $post_id, $name, $email, $order_ref, $scope, $details, $settings['notification_email'], $phone );
	} else {
		return 'general';
	}

	return 'success';
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

	$notice = apg_withdrawal_process_submission_data(
		array(
			'order'    => isset( $_POST['apg_withdrawal_order'] ) ? wp_unslash( $_POST['apg_withdrawal_order'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized individually before use
			'name'     => isset( $_POST['apg_withdrawal_name'] ) ? wp_unslash( $_POST['apg_withdrawal_name'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized individually before use
			'email'    => isset( $_POST['apg_withdrawal_email'] ) ? wp_unslash( $_POST['apg_withdrawal_email'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized individually before use
			'phone'    => isset( $_POST['apg_withdrawal_phone'] ) ? wp_unslash( $_POST['apg_withdrawal_phone'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized individually before use
			'scope'    => isset( $_POST['apg_withdrawal_scope'] ) ? wp_unslash( $_POST['apg_withdrawal_scope'] ) : 'full', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized individually before use
			'details'  => isset( $_POST['apg_withdrawal_details'] ) ? wp_unslash( $_POST['apg_withdrawal_details'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized individually before use
			'products' => isset( $_POST['apg_withdrawal_products'] ) && is_array( $_POST['apg_withdrawal_products'] ) ? wp_unslash( $_POST['apg_withdrawal_products'] ) : array(), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized individually before use
		)
	);

	apg_withdrawal_redirect_with_notice( $notice );
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

	$name     = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$phone    = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
	$order_id = isset( $_POST['order_id'] ) ? sanitize_text_field( wp_unslash( $_POST['order_id'] ) ) : '';
	$scope    = isset( $_POST['scope'] ) ? sanitize_key( wp_unslash( $_POST['scope'] ) ) : 'full';
	$details  = isset( $_POST['details'] ) ? sanitize_textarea_field( wp_unslash( $_POST['details'] ) ) : '';
	$acceptance = isset( $_POST['acceptance'] ) ? sanitize_text_field( wp_unslash( $_POST['acceptance'] ) ) : '';
	$products = isset( $_POST['products'] ) && is_array( $_POST['products'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['products'] ) ) : array();
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

	$validation = apg_withdrawal_validate_order( $order_id, $email, $settings );

	if ( ! $validation['valid'] ) {
		wp_send_json_error(
			array(
				'message' => apg_withdrawal_render_notice_html( $validation['error'] ),
			)
		);
	}

	$html  = apg_withdrawal_render_notice_html( '' );
	$html .= apg_withdrawal_render_confirmation_form( $name, $email, $order_id, $scope, $details, $products, $validation['order'], $settings, apg_withdrawal_get_current_form_url(), $phone, $validation['expired_warning'] );

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

	$notice = apg_withdrawal_process_submission_data(
		array(
			'order'    => isset( $_POST['apg_withdrawal_order'] ) ? wp_unslash( $_POST['apg_withdrawal_order'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized individually before use
			'name'     => isset( $_POST['apg_withdrawal_name'] ) ? wp_unslash( $_POST['apg_withdrawal_name'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized individually before use
			'email'    => isset( $_POST['apg_withdrawal_email'] ) ? wp_unslash( $_POST['apg_withdrawal_email'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized individually before use
			'phone'    => isset( $_POST['apg_withdrawal_phone'] ) ? wp_unslash( $_POST['apg_withdrawal_phone'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized individually before use
			'scope'    => isset( $_POST['apg_withdrawal_scope'] ) ? wp_unslash( $_POST['apg_withdrawal_scope'] ) : 'full', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized individually before use
			'details'  => isset( $_POST['apg_withdrawal_details'] ) ? wp_unslash( $_POST['apg_withdrawal_details'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized individually before use
			'products' => isset( $_POST['apg_withdrawal_products'] ) && is_array( $_POST['apg_withdrawal_products'] ) ? wp_unslash( $_POST['apg_withdrawal_products'] ) : array(), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized individually before use
		)
	);

	if ( 'success' !== $notice ) {
		wp_send_json_error(
			array(
				'message' => apg_withdrawal_render_notice_html( $notice ),
			)
		);
	}

	wp_send_json_success(
		array(
			'html' => apg_withdrawal_render_notice_html( 'success' ),
		)
	);
}
add_action( 'wp_ajax_apg_withdrawal_confirm_ajax', 'apg_withdrawal_ajax_confirm' );
add_action( 'wp_ajax_nopriv_apg_withdrawal_confirm_ajax', 'apg_withdrawal_ajax_confirm' );
