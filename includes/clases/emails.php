<?php
/**
 * Correos del plugin.
 *
 * @package APG_Withdrawal_For_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sends the withdrawal request acknowledgement email to the customer.
 *
 * @param string $email        Customer email address.
 * @param string $name         Customer full name.
 * @param string $order_ref    Order reference number.
 * @param string $scope        Withdrawal scope ('full' or 'partial').
 * @param int    $post_id      Withdrawal request post ID.
 * @param string $request_date Date and time the request was submitted.
 * @param string $phone        Optional customer phone number.
 * @return void
 */
function apg_withdrawal_send_customer_email( $email, $name, $order_ref, $scope, $post_id = 0, $request_date = '', $phone = '' ) {
	if ( function_exists( 'WC' ) && WC()->mailer() ) {
		$emails = WC()->mailer()->get_emails();

		if ( isset( $emails['APG_Withdrawal_Email_Customer'] ) ) {
			$emails['APG_Withdrawal_Email_Customer']->trigger( $post_id, $email, $name, $order_ref, $scope, $request_date, $phone );
			return;
		}
	}

	// Fallback: plain text via wp_mail
	$subject     = sprintf(
		/* translators: %s site name. */
		__( '[%s] Withdrawal request received', 'apg-withdrawal-for-woocommerce' ),
		wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
	);
	$scope_label = 'partial' === $scope ? __( 'Specific products only', 'apg-withdrawal-for-woocommerce' ) : __( 'Full order', 'apg-withdrawal-for-woocommerce' );
	$lines       = array(
		sprintf(
			/* translators: %s customer name. */
			__( 'Hello %s,', 'apg-withdrawal-for-woocommerce' ),
			$name
		),
		'',
		__( 'We have correctly received your withdrawal request.', 'apg-withdrawal-for-woocommerce' ),
		__( 'Your request will be reviewed in accordance with consumer and user protection legislation and the conditions applicable to the contracted order.', 'apg-withdrawal-for-woocommerce' ),
		'',
		__( 'Request details:', 'apg-withdrawal-for-woocommerce' ),
	);

	if ( $post_id ) {
		$lines[] = sprintf( '%s: #%d', __( 'Request number', 'apg-withdrawal-for-woocommerce' ), $post_id );
	}

	if ( $request_date ) {
		$lines[] = sprintf( '%s: %s', __( 'Date and time', 'apg-withdrawal-for-woocommerce' ), $request_date );
	}

	$lines[] = sprintf( '%s: %s', __( 'Order', 'apg-withdrawal-for-woocommerce' ), $order_ref );
	$lines[] = sprintf( '%s: %s', __( 'Scope', 'apg-withdrawal-for-woocommerce' ), $scope_label );
	$lines[] = '';
	$lines[] = __( 'Should the right of withdrawal be applicable, it may be necessary to return the product in accordance with the instructions provided by the store.', 'apg-withdrawal-for-woocommerce' );

	wp_mail( $email, $subject, implode( "\r\n", $lines ), array( 'Content-Type: text/plain; charset=UTF-8' ) );
}

/**
 * Sends a new withdrawal request notification email to the store administrator.
 *
 * @param int    $post_id            Withdrawal request post ID.
 * @param string $name               Customer full name.
 * @param string $email              Customer email address.
 * @param string $order_ref          Order reference number.
 * @param string $scope              Withdrawal scope ('full' or 'partial').
 * @param string $details            Additional details provided by the customer.
 * @param string $notification_email Recipient email address for the admin notification.
 * @param string $phone              Optional customer phone number.
 * @return void
 */
function apg_withdrawal_send_admin_email( $post_id, $name, $email, $order_ref, $scope, $details, $notification_email, $phone = '' ) {
	if ( function_exists( 'WC' ) && WC()->mailer() ) {
		$emails = WC()->mailer()->get_emails();

		if ( isset( $emails['APG_Withdrawal_Email_Admin'] ) ) {
			$emails['APG_Withdrawal_Email_Admin']->trigger( $post_id, $name, $email, $order_ref, $scope, $details, $notification_email, $phone );
			return;
		}
	}

	// Fallback: plain text via wp_mail
	$admin_email = $notification_email && is_email( $notification_email ) ? $notification_email : get_option( 'admin_email' );
	$scope_label = 'partial' === $scope ? __( 'Specific products only', 'apg-withdrawal-for-woocommerce' ) : __( 'Full order', 'apg-withdrawal-for-woocommerce' );
	$subject     = sprintf(
		/* translators: 1: site name, 2: order reference. */
		__( '[%1$s] New withdrawal request for order %2$s', 'apg-withdrawal-for-woocommerce' ),
		wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		$order_ref
	);
	$admin_lines = array(
		__( 'A new withdrawal request has been submitted.', 'apg-withdrawal-for-woocommerce' ),
		'',
		sprintf( '%s: %s', __( 'Customer', 'apg-withdrawal-for-woocommerce' ), $name ),
		sprintf( '%s: %s', __( 'Email', 'apg-withdrawal-for-woocommerce' ), $email ),
	);

	if ( $phone ) {
		$admin_lines[] = sprintf( '%s: %s', __( 'Phone', 'apg-withdrawal-for-woocommerce' ), $phone );
	}

	$admin_lines = array_merge(
		$admin_lines,
		array(
			sprintf( '%s: %s', __( 'Order', 'apg-withdrawal-for-woocommerce' ), $order_ref ),
			sprintf( '%s: %s', __( 'Scope', 'apg-withdrawal-for-woocommerce' ), $scope_label ),
			'',
			__( 'Details:', 'apg-withdrawal-for-woocommerce' ),
			$details ? $details : __( '(empty)', 'apg-withdrawal-for-woocommerce' ),
			'',
			__( 'Admin edit link:', 'apg-withdrawal-for-woocommerce' ),
			get_edit_post_link( $post_id, '' ),
		)
	);

	$message = implode( "\r\n", $admin_lines );
	$headers     = array( 'Content-Type: text/plain; charset=UTF-8' );

	if ( $email ) {
		$headers[] = sprintf( 'Reply-To: %s <%s>', sanitize_text_field( $name ), sanitize_email( $email ) );
	}

	wp_mail( $admin_email, $subject, $message, $headers );
}

/**
 * Appends a withdrawal request link to WooCommerce order emails sent to customers.
 *
 * Registered customers receive a link to the My Account withdrawal endpoint.
 * Guest customers receive a link to the public withdrawal form page.
 * Only fires on customer_completed_order and customer_processing_order emails.
 *
 * @param WC_Order $order         The WooCommerce order object.
 * @param bool     $sent_to_admin Whether the email is addressed to the store admin.
 * @param bool     $plain_text    Whether the email is plain text format.
 * @param WC_Email $email         The WooCommerce email object.
 * @return void
 */
function apg_withdrawal_email_order_withdrawal_link( $order, $sent_to_admin, $plain_text, $email ) {
	if ( $sent_to_admin ) {
		return;
	}

	if ( ! in_array( $email->id, array( 'customer_completed_order', 'customer_processing_order' ), true ) ) {
		return;
	}

	$order_id = $order->get_id();
	$user_id  = $order->get_user_id();

	if ( $user_id > 0 ) {
		$url = function_exists( 'apg_withdrawal_get_account_url' )
			? apg_withdrawal_get_account_url( array( 'order_id' => $order_id ) )
			: '';
	} else {
		$settings = apg_withdrawal_get_settings();
		$page_id  = absint( $settings['page_id'] ?? 0 );

		if ( ! $page_id ) {
			return;
		}

		$page_url = get_permalink( $page_id );
		if ( ! $page_url ) {
			return;
		}

		$url = add_query_arg(
			array(
				'order_id' => $order_id,
				'email'    => $order->get_billing_email(),
			),
			$page_url
		);
	}

	if ( ! $url ) {
		return;
	}

	if ( $plain_text ) {
		/* translators: %s withdrawal request URL. */
		printf( "\n" . esc_html__( 'Right of withdrawal: %s', 'apg-withdrawal-for-woocommerce' ) . "\n", esc_url( $url ) );
	} else {
		printf(
			'<p style="margin-top:16px;"><a href="%s">%s</a></p>',
			esc_url( $url ),
			esc_html__( 'Exercise your right of withdrawal', 'apg-withdrawal-for-woocommerce' )
		);
	}
}
add_action( 'woocommerce_email_after_order_table', 'apg_withdrawal_email_order_withdrawal_link', 10, 4 );
