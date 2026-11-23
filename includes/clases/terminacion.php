<?php
/**
 * Online contract termination function.
 *
 * Implements Article 23 of the proposed Digital Fairness Act — COM(2026) 1100
 * final, adopted by the Commission on 11 November 2026 — which requires a
 * trader who lets consumers conclude contracts through an online interface to
 * let them terminate the contract through that same interface, "irrespective of
 * the manner in which the contract was concluded".
 *
 * It is the sibling of the withdrawal function of Article 11a of Directive
 * 2011/83/EU (which the accompanying Simplification Directive, COM(2026) 1110
 * final, deletes and moves to Article 24 of that same Regulation). The two are
 * deliberately alike — recital 75 says both "should be provided in a similar
 * manner" — but they are not the same thing, and the differences are what this
 * file exists for:
 *
 *   - The withdrawal function lives only during the withdrawal period. This one
 *     is available for as long as the contract is.
 *   - It carries no deadline, no line items, no quantities and no refund
 *     account: terminating is terminating, not choosing what to send back.
 *   - The acknowledgement must also state the date on which the termination
 *     takes effect, and is due "without undue delay, and at the latest within a
 *     week".
 *   - The wording is prescribed: "terminate the contract here" to open it and
 *     "confirm the contract termination" to send it.
 *
 * The Regulation is a proposal at the start of the ordinary legislative
 * procedure and applies twelve months after it enters into force, so nothing
 * here is required of a shop yet. The feature therefore ships switched off, and
 * a shop that enables it is simply ready early.
 *
 * @package APG_Withdrawal_For_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reports whether the shop has enabled the contract termination function.
 *
 * @return bool
 */
function apg_withdrawal_termination_enabled() {
	$settings = function_exists( 'apg_withdrawal_get_settings' ) ? apg_withdrawal_get_settings() : array();

	return isset( $settings['termination_enabled'] ) && '1' === (string) $settings['termination_enabled'];
}

/**
 * Returns the ID of the page holding the termination form.
 *
 * @return int Page ID, or 0 when none is configured.
 */
function apg_withdrawal_get_termination_page_id() {
	$settings = function_exists( 'apg_withdrawal_get_settings' ) ? apg_withdrawal_get_settings() : array();

	return isset( $settings['termination_page_id'] ) ? absint( $settings['termination_page_id'] ) : 0;
}

/**
 * Returns the URL of the termination form page.
 *
 * @return string URL, or empty string when no page is configured.
 */
function apg_withdrawal_get_termination_page_url() {
	$page_id = apg_withdrawal_get_termination_page_id();

	if ( ! $page_id ) {
		return '';
	}

	$url = get_permalink( $page_id );

	return $url ? $url : '';
}

/**
 * Returns the label of the function that opens the termination form.
 *
 * Article 23(2) prescribes "terminate the contract here" or an unambiguous
 * corresponding formulation, so the default is that wording translated, and a
 * shop that overrides it is responsible for keeping it unambiguous.
 *
 * @return string
 */
function apg_withdrawal_get_termination_label() {
	$settings = function_exists( 'apg_withdrawal_get_settings' ) ? apg_withdrawal_get_settings() : array();
	$custom   = isset( $settings['termination_label'] ) ? trim( (string) $settings['termination_label'] ) : '';

	if ( '' !== $custom ) {
		return function_exists( 'apg_withdrawal_translate_setting_string' )
			? apg_withdrawal_translate_setting_string( $custom, 'Contract termination link text' )
			: $custom;
	}

	return __( 'Terminate the contract here', 'apg-withdrawal-for-woocommerce' );
}

/**
 * Returns the label of the confirmation function.
 *
 * Article 23(4) is stricter than the rest: the confirmation function shall be
 * labelled "only with the words 'confirm the contract termination' or with an
 * unambiguous corresponding formulation", so this one is not configurable.
 *
 * @return string
 */
function apg_withdrawal_get_termination_confirm_label() {
	return __( 'Confirm the contract termination', 'apg-withdrawal-for-woocommerce' );
}

/**
 * Returns the date on which a termination takes effect, as required by Article
 * 23(5).
 *
 * Immediate by default, which is the only answer a plugin can give on its own:
 * when a contract ends is a matter of the contract, not of the software. A shop
 * with notice periods, billing cycles or a subscriptions extension decides
 * otherwise through the filter.
 *
 * @param WC_Order|bool $order       Order the contract belongs to.
 * @param string        $declared_at Declaration timestamp, `Y-m-d H:i:s` GMT.
 * @return string Effective date in `Y-m-d H:i:s` GMT.
 */
function apg_withdrawal_get_termination_effective_date( $order, $declared_at ) {
	/**
	 * Filters the date on which the termination takes effect.
	 *
	 * @param string        $effective   Effective date, `Y-m-d H:i:s` GMT.
	 * @param WC_Order|bool $order       Order the contract belongs to.
	 * @param string        $declared_at Moment the consumer sent the declaration.
	 */
	return (string) apply_filters( 'apg_withdrawal_termination_effective_date', $declared_at, $order, $declared_at );
}

/**
 * Validates and registers a contract termination declaration.
 *
 * Mirrors the withdrawal processor, minus everything a termination has no use
 * for, and with one deliberate difference: no deadline is checked. A withdrawal
 * can arrive too late; a termination cannot, because the right it exercises
 * lasts as long as the contract does.
 *
 * @param array $data Associative array with 'order', 'name', 'email', 'phone' and 'details'.
 * @return string Result key: 'termination_registered', 'fields', 'email', 'order',
 *                'duplicate', 'throttle', 'disabled' or 'general'.
 */
function apg_withdrawal_process_termination_data( $data ) {
	if ( ! apg_withdrawal_termination_enabled() ) {
		return 'disabled';
	}

	if ( apg_withdrawal_honeypot_was_filled() ) {
		return 'termination_registered';
	}

	$order_ref = isset( $data['order'] ) ? sanitize_text_field( $data['order'] ) : '';
	$name      = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
	$email     = isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '';
	$phone     = isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : '';
	$details   = isset( $data['details'] ) ? sanitize_textarea_field( $data['details'] ) : '';
	$settings  = apg_withdrawal_get_settings();

	if ( apg_withdrawal_is_rate_limited( $email, 'termination' ) ) {
		return 'throttle';
	}

	// A malformed address is reported as such. `sanitize_email()` empties what it
	// cannot fix, so asking it afterwards would send the customer back to look
	// for a field they did fill in.
	$email_escrito = isset( $data['email'] ) ? trim( (string) $data['email'] ) : '';

	if ( '' !== $email_escrito && ( '' === $email || ! is_email( $email ) ) ) {
		return 'email';
	}

	if ( '' === $order_ref || '' === $name || '' === $email ) {
		return 'fields';
	}

	$validation = apg_withdrawal_validate_order( $order_ref, $email, $settings );

	if ( ! $validation['valid'] ) {
		// An expired withdrawal period is no obstacle here, so the only refusal
		// left is the order not matching the address.
		return 'order';
	}

	$verified    = ! empty( $data['verified'] );
	$declared_at = current_time( 'mysql', true );

	// A shop that asks guests to confirm by email asks it here too. Without
	// this, switching that setting on protected the withdrawal form and left
	// this one open: anybody holding an order number and the address on it
	// could end somebody else's contract, which is the heavier of the two
	// declarations. The timestamp survives the round trip, so the consumer is
	// dated when they sent the form, not when they opened their mail.
	if ( ! $verified && apg_withdrawal_guest_verification_required() ) {
		$payload = array(
			'kind'        => 'termination',
			'order'       => $order_ref,
			'name'        => $name,
			'email'       => $email,
			'phone'       => $phone,
			'details'     => $details,
			'declared_at' => $declared_at,
		);

		$token = apg_withdrawal_store_pending_verification( $payload );

		if ( ! $token ) {
			return 'general';
		}

		apg_withdrawal_send_verification_email( $payload, apg_withdrawal_get_verification_url( $token, apg_withdrawal_get_current_form_url() ) );

		return 'verify_sent';
	}

	// A payload that came back through the link carries the moment it was first
	// sent; anything else is dated now. It is only honoured for an already
	// verified payload, which comes from a server-side transient and never from
	// the browser, so it cannot be used to backdate a termination.
	if ( $verified && isset( $data['declared_at'] ) && apg_withdrawal_is_mysql_datetime( (string) $data['declared_at'] ) ) {
		$declared_at = sanitize_text_field( (string) $data['declared_at'] );
	}

	$effective = apg_withdrawal_get_termination_effective_date( $validation['order'], $declared_at );

	if ( ! apg_withdrawal_is_mysql_datetime( $effective ) ) {
		$effective = $declared_at;
	}

	// The same declaration is not registered twice: a double click, a reload or
	// a second tab reaches an order that is already being terminated.
	if ( apg_withdrawal_find_termination_requests( $validation['order_id'] ) ) {
		return 'duplicate';
	}

	$post_id = wp_insert_post(
		array(
			'post_type'     => 'apg_withdrawal',
			'post_status'   => 'publish',
			'post_title'    => sprintf(
				/* translators: 1: order number, 2: customer name. */
				__( 'Contract termination %1$s - %2$s', 'apg-withdrawal-for-woocommerce' ),
				$order_ref,
				$name
			),
			'post_content'  => $details,
			'post_date_gmt' => $declared_at,
			'post_date'     => get_date_from_gmt( $declared_at ),
			'meta_input'    => array(
				'_apg_withdrawal_kind'                  => 'termination',
				'_apg_withdrawal_name'                  => $name,
				'_apg_withdrawal_order'                 => $order_ref,
				'_apg_withdrawal_email'                 => $email,
				'_apg_withdrawal_scope'                 => 'full',
				'_apg_withdrawal_status'                => 'pending',
				'_apg_withdrawal_wc_order_id'           => $validation['order_id'],
				'_apg_withdrawal_termination_effective' => $effective,
			),
		),
		true
	);

	if ( is_wp_error( $post_id ) || ! $post_id ) {
		return 'general';
	}

	if ( '' !== $phone ) {
		update_post_meta( $post_id, '_apg_withdrawal_phone', $phone );
	}

	if ( '1' === (string) $settings['store_ip'] && class_exists( 'WC_Geolocation' ) ) {
		update_post_meta( $post_id, '_apg_withdrawal_ip', WC_Geolocation::get_ip_address() );
	}

	if ( '1' === (string) $settings['store_user_agent'] ) {
		update_post_meta( $post_id, '_apg_withdrawal_user_agent', isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '' );
	}

	// Two declarations can both get past the check above before either has
	// written anything; the older ID stands and the rest withdraw, before any
	// note or notification leaves a trace.
	foreach ( apg_withdrawal_find_termination_requests( $validation['order_id'], $post_id ) as $earlier_id ) {
		if ( $earlier_id < $post_id ) {
			wp_delete_post( $post_id, true );

			return 'duplicate';
		}
	}

	// Same canonical receipt as a withdrawal, with the effective date folded in,
	// so the consumer can reconstruct the digest from what the acknowledgement
	// shows them.
	$receipt_hash = hash( 'sha256', implode( '|', array( $name, $email, $order_ref, 'termination', $details, $declared_at, $effective ) ) );
	update_post_meta( $post_id, '_apg_withdrawal_receipt_hash', $receipt_hash );
	update_post_meta( $post_id, '_apg_withdrawal_receipt_hash_timestamp', $declared_at );

	apg_withdrawal_add_termination_order_note( $validation['order'], $post_id, $effective, $details );

	$delivery = apg_withdrawal_send_with_delivery_capture(
		function () use ( $email, $name, $order_ref, $post_id, $declared_at, $effective, $phone ) {
			apg_withdrawal_send_termination_customer_email( $email, $name, $order_ref, $post_id, $declared_at, $effective, $phone );
		}
	);
	update_post_meta( $post_id, '_apg_withdrawal_initial_email_delivery', $delivery );

	apg_withdrawal_send_termination_admin_email( $post_id, $name, $email, $order_ref, $details, $settings['notification_email'], $effective );

	/**
	 * Fires once a contract termination has been fully registered.
	 *
	 * @param int   $post_id Request post ID.
	 * @param array $data    Submission payload as received.
	 */
	do_action( 'apg_withdrawal_termination_registered', $post_id, $data );

	return 'termination_registered';
}

/**
 * Finds the terminations already on file for an order that still stand.
 *
 * @param int $wc_order_id WooCommerce order ID.
 * @param int $exclude_id  Request to leave out, normally the one just created.
 * @return array Request IDs, oldest first.
 */
function apg_withdrawal_find_termination_requests( $wc_order_id, $exclude_id = 0 ) {
	$matches = array();

	foreach ( apg_withdrawal_get_active_requests_for_order( $wc_order_id, $exclude_id ) as $candidate_id ) {
		if ( 'termination' === get_post_meta( $candidate_id, '_apg_withdrawal_kind', true ) ) {
			$matches[] = $candidate_id;
		}
	}

	return $matches;
}

/**
 * Records the termination on the order, so the shop sees it where it works.
 *
 * @param WC_Order|bool $order     Order object.
 * @param int           $post_id   Request post ID.
 * @param string        $effective Effective date, `Y-m-d H:i:s` GMT.
 * @param string        $details   Free text the consumer added.
 * @return void
 */
function apg_withdrawal_add_termination_order_note( $order, $post_id, $effective, $details ) {
	if ( ! $order || ! is_callable( array( $order, 'add_order_note' ) ) ) {
		return;
	}

	$order->add_order_note(
		sprintf(
			/* translators: 1: request ID, 2: date the termination takes effect. */
			__( 'Contract termination requested. Log ID: #%1$d. Takes effect: %2$s', 'apg-withdrawal-for-woocommerce' ),
			$post_id,
			apg_withdrawal_format_termination_date( $effective )
		)
	);

	if ( '' !== $details ) {
		$order->add_order_note(
			sprintf(
				/* translators: %s free text written by the customer. */
				__( 'Termination details: %s', 'apg-withdrawal-for-woocommerce' ),
				$details
			)
		);
	}
}

/**
 * Formats a GMT timestamp in the site's own date and time format.
 *
 * @param string $gmt Timestamp in `Y-m-d H:i:s` GMT.
 * @return string
 */
function apg_withdrawal_format_termination_date( $gmt ) {
	$local = get_date_from_gmt( $gmt );

	return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $local ) );
}

/**
 * Sends the acknowledgement of receipt required by Article 23(5).
 *
 * @param string $email       Customer address.
 * @param string $name        Customer name.
 * @param string $order_ref   Order reference.
 * @param int    $post_id     Request post ID.
 * @param string $declared_at Moment the declaration was submitted, GMT.
 * @param string $effective   Date the termination takes effect, GMT.
 * @param string $phone       Optional phone number.
 * @return void
 */
function apg_withdrawal_send_termination_customer_email( $email, $name, $order_ref, $post_id, $declared_at, $effective, $phone = '' ) {
	if ( function_exists( 'WC' ) && WC()->mailer() ) {
		$emails = WC()->mailer()->get_emails();

		if ( isset( $emails['APG_Withdrawal_Email_Termination'] ) ) {
			$emails['APG_Withdrawal_Email_Termination']->trigger( $post_id, $email, $name, $order_ref, $declared_at, $effective, $phone );

			return;
		}
	}

	$subject = sprintf(
		/* translators: %s site name. */
		__( '[%s] Contract termination received', 'apg-withdrawal-for-woocommerce' ),
		wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
	);
	$lines   = array(
		sprintf(
			/* translators: %s customer name. */
			__( 'Hello %s,', 'apg-withdrawal-for-woocommerce' ),
			$name
		),
		'',
		__( 'We have received your request to terminate the contract.', 'apg-withdrawal-for-woocommerce' ),
		'',
		sprintf(
			/* translators: %s order number. */
			__( 'Contract: %s', 'apg-withdrawal-for-woocommerce' ),
			$order_ref
		),
		sprintf(
			/* translators: %s date and time the declaration was submitted. */
			__( 'Submitted on: %s', 'apg-withdrawal-for-woocommerce' ),
			apg_withdrawal_format_termination_date( $declared_at )
		),
		sprintf(
			/* translators: %s date the termination takes effect. */
			__( 'The termination takes effect on: %s', 'apg-withdrawal-for-woocommerce' ),
			apg_withdrawal_format_termination_date( $effective )
		),
	);

	if ( '' !== $phone ) {
		$lines[] = sprintf(
			/* translators: %s customer phone number. */
			__( 'Phone: %s', 'apg-withdrawal-for-woocommerce' ),
			$phone
		);
	}

	$lines[] = '';
	$lines[] = __( 'Keep this message: it is the acknowledgement of receipt of your termination.', 'apg-withdrawal-for-woocommerce' );

	wp_mail( $email, $subject, implode( "\n", $lines ) );
}

/**
 * Notifies the shop that a contract termination has been received.
 *
 * @param int    $post_id            Request post ID.
 * @param string $name               Customer name.
 * @param string $email              Customer address.
 * @param string $order_ref          Order reference.
 * @param string $details            Free text the consumer added.
 * @param string $notification_email Address configured for notifications.
 * @param string $effective          Date the termination takes effect, GMT.
 * @return void
 */
function apg_withdrawal_send_termination_admin_email( $post_id, $name, $email, $order_ref, $details, $notification_email, $effective ) {
	$to = is_email( $notification_email ) ? $notification_email : get_option( 'admin_email' );

	$subject = sprintf(
		/* translators: 1: site name, 2: order number. */
		__( '[%1$s] Contract termination for order %2$s', 'apg-withdrawal-for-woocommerce' ),
		wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		$order_ref
	);
	$lines   = array(
		__( 'A customer has terminated a contract.', 'apg-withdrawal-for-woocommerce' ),
		'',
		sprintf(
			/* translators: %s customer name. */
			__( 'Customer: %s', 'apg-withdrawal-for-woocommerce' ),
			$name
		),
		sprintf(
			/* translators: %s customer email address. */
			__( 'Email: %s', 'apg-withdrawal-for-woocommerce' ),
			$email
		),
		sprintf(
			/* translators: %s order number. */
			__( 'Contract: %s', 'apg-withdrawal-for-woocommerce' ),
			$order_ref
		),
		sprintf(
			/* translators: %s date the termination takes effect. */
			__( 'The termination takes effect on: %s', 'apg-withdrawal-for-woocommerce' ),
			apg_withdrawal_format_termination_date( $effective )
		),
	);

	if ( '' !== $details ) {
		$lines[] = sprintf(
			/* translators: %s free text written by the customer. */
			__( 'Details: %s', 'apg-withdrawal-for-woocommerce' ),
			$details
		);
	}

	$lines[] = '';
	$lines[] = apg_withdrawal_get_request_edit_url( $post_id );

	wp_mail( $to, $subject, implode( "\n", $lines ) );
}

/**
 * Prints the contract termination form.
 *
 * Two steps, like the withdrawal one and for the same reason: Article 23
 * separates writing the declaration from sending it, and the confirmation
 * function has wording of its own. It works without JavaScript — an ordinary
 * POST to the page itself — so a blocked script, a security plugin guarding
 * wp-admin or a text browser cannot stand between a consumer and the end of
 * their contract.
 *
 * @param array $atts Optional 'order_id' and 'email' to prefill.
 * @return void
 */
function apg_withdrawal_print_termination_form( $atts = array() ) {
	if ( ! apg_withdrawal_termination_enabled() ) {
		apg_withdrawal_print_notice( 'termination_disabled' );

		return;
	}

	$atts     = wp_parse_args( $atts, array( 'order_id' => '', 'email' => '' ) );
	$settings = apg_withdrawal_get_settings();
	$usuario  = wp_get_current_user();
	$mensaje  = '';
	$paso     = 'request';

	$nombre  = apg_withdrawal_get_request_value( 'apg_withdrawal_name', 'sanitize_text_field', apg_withdrawal_get_default_customer_name( $usuario ) );
	$correo  = apg_withdrawal_get_request_value( 'apg_withdrawal_email', 'sanitize_email', $atts['email'] ? $atts['email'] : ( $usuario->ID ? $usuario->user_email : '' ) );
	$pedido  = apg_withdrawal_get_request_value( 'apg_withdrawal_order', 'sanitize_text_field', $atts['order_id'] );
	$tel     = apg_withdrawal_get_request_value( 'apg_withdrawal_phone', 'sanitize_text_field' );
	$detalle = apg_withdrawal_get_request_value( 'apg_withdrawal_details', 'sanitize_textarea_field' );

	$es_post = 'POST' === strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET' );

	// Step two: the consumer asked to review the declaration before sending it.
	if ( $es_post && isset( $_POST['apg_withdrawal_termination_review'] ) ) {
		if ( ! isset( $_POST['apg_withdrawal_termination_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['apg_withdrawal_termination_nonce'] ) ), 'apg_withdrawal_termination_action' ) ) {
			$mensaje = 'nonce';
		} elseif ( '' === $nombre || '' === $correo || '' === $pedido ) {
			$mensaje = 'fields';
		} elseif ( ! is_email( $correo ) ) {
			$mensaje = 'email';
		} else {
			$validacion = apg_withdrawal_validate_order( $pedido, $correo, $settings );
			$paso       = ! empty( $validacion['valid'] ) ? 'confirm' : 'request';
			$mensaje    = ! empty( $validacion['valid'] ) ? '' : 'order';
		}
	}

	// The declaration itself.
	if ( $es_post && isset( $_POST['apg_withdrawal_termination_confirm'] ) ) {
		if ( ! isset( $_POST['apg_withdrawal_termination_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['apg_withdrawal_termination_nonce'] ) ), 'apg_withdrawal_termination_action' ) ) {
			$mensaje = 'nonce';
			$paso    = 'request';
		} else {
			$mensaje = apg_withdrawal_process_termination_data(
				array(
					'order'   => $pedido,
					'name'    => $nombre,
					'email'   => $correo,
					'phone'   => $tel,
					'details' => $detalle,
				)
			);
			$paso    = apg_withdrawal_notice_is_final( $mensaje ) ? 'request' : 'confirm';
		}
	}

	$accion = apg_withdrawal_get_current_form_url();

	?>
	<div class="woocommerce apg-withdrawal-form-wrapper apg-withdrawal-termination-wrapper">
		<?php apg_withdrawal_print_notice( $mensaje ); ?>

		<?php if ( 'confirm' === $paso ) : ?>
			<form class="apg-withdrawal-confirmation" method="post" action="<?php echo esc_url( $accion ); ?>" aria-labelledby="apg-withdrawal-termination-title">
				<h2 id="apg-withdrawal-termination-title"><?php esc_html_e( 'Review your contract termination', 'apg-withdrawal-for-woocommerce' ); ?></h2>
				<p><?php esc_html_e( 'By sending this declaration you inform us of your decision to terminate the contract. We will send you an acknowledgement of receipt stating the date on which it takes effect.', 'apg-withdrawal-for-woocommerce' ); ?></p>

				<p class="form-row form-row-wide"><strong><?php esc_html_e( 'Name:', 'apg-withdrawal-for-woocommerce' ); ?></strong> <?php echo esc_html( $nombre ); ?></p>
				<p class="form-row form-row-wide"><strong><?php esc_html_e( 'Email:', 'apg-withdrawal-for-woocommerce' ); ?></strong> <?php echo esc_html( $correo ); ?></p>
				<p class="form-row form-row-wide"><strong><?php esc_html_e( 'Contract:', 'apg-withdrawal-for-woocommerce' ); ?></strong> <?php echo esc_html( $pedido ); ?></p>
				<?php if ( '' !== $tel ) : ?>
					<p class="form-row form-row-wide"><strong><?php esc_html_e( 'Phone:', 'apg-withdrawal-for-woocommerce' ); ?></strong> <?php echo esc_html( $tel ); ?></p>
				<?php endif; ?>
				<?php if ( '' !== $detalle ) : ?>
					<p class="form-row form-row-wide"><strong><?php esc_html_e( 'Details:', 'apg-withdrawal-for-woocommerce' ); ?></strong> <?php echo esc_html( $detalle ); ?></p>
				<?php endif; ?>

				<input type="hidden" name="apg_withdrawal_termination_confirm" value="1">
				<input type="hidden" name="apg_withdrawal_name" value="<?php echo esc_attr( $nombre ); ?>">
				<input type="hidden" name="apg_withdrawal_email" value="<?php echo esc_attr( $correo ); ?>">
				<input type="hidden" name="apg_withdrawal_order" value="<?php echo esc_attr( $pedido ); ?>">
				<input type="hidden" name="apg_withdrawal_phone" value="<?php echo esc_attr( $tel ); ?>">
				<input type="hidden" name="apg_withdrawal_details" value="<?php echo esc_attr( $detalle ); ?>">
				<p class="apg-withdrawal-honeypot" aria-hidden="true">
					<label><?php esc_html_e( 'Leave this field empty', 'apg-withdrawal-for-woocommerce' ); ?>
						<input type="text" name="apg_withdrawal_website" value="" autocomplete="off" tabindex="-1">
					</label>
				</p>
				<?php wp_nonce_field( 'apg_withdrawal_termination_action', 'apg_withdrawal_termination_nonce' ); ?>

				<p class="form-row">
					<button type="submit" class="button alt<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>"><?php echo esc_html( apg_withdrawal_get_termination_confirm_label() ); ?></button>
				</p>
			</form>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( $accion ); ?>" class="apg-withdrawal-form apg-withdrawal-termination-form">
				<?php
				apg_withdrawal_print_field(
					'apg_withdrawal_name',
					array(
						'type'     => 'text',
						'label'    => __( 'Full name', 'apg-withdrawal-for-woocommerce' ),
						'required' => true,
						'class'    => array( 'form-row-wide' ),
					),
					$nombre
				);
				apg_withdrawal_print_field(
					'apg_withdrawal_email',
					array(
						'type'     => 'email',
						'label'    => __( 'Email address', 'apg-withdrawal-for-woocommerce' ),
						'required' => true,
						'class'    => array( 'form-row-wide' ),
					),
					$correo
				);
				apg_withdrawal_print_field(
					'apg_withdrawal_order',
					array(
						'type'        => 'text',
						'label'       => __( 'Contract or order number', 'apg-withdrawal-for-woocommerce' ),
						'required'    => true,
						'class'       => array( 'form-row-wide' ),
						'description' => __( 'The number of the order the contract belongs to.', 'apg-withdrawal-for-woocommerce' ),
					),
					$pedido
				);
				apg_withdrawal_print_field(
					'apg_withdrawal_phone',
					array(
						'type'  => 'tel',
						'label' => __( 'Phone', 'apg-withdrawal-for-woocommerce' ),
						'class' => array( 'form-row-wide' ),
					),
					$tel
				);
				apg_withdrawal_print_field(
					'apg_withdrawal_details',
					array(
						'type'  => 'textarea',
						'label' => __( 'Additional details', 'apg-withdrawal-for-woocommerce' ),
						'class' => array( 'form-row-wide' ),
					),
					$detalle
				);
				?>
				<p class="apg-withdrawal-honeypot" aria-hidden="true">
					<label><?php esc_html_e( 'Leave this field empty', 'apg-withdrawal-for-woocommerce' ); ?>
						<input type="text" name="apg_withdrawal_website" value="" autocomplete="off" tabindex="-1">
					</label>
				</p>
				<input type="hidden" name="apg_withdrawal_termination_review" value="1">
				<?php wp_nonce_field( 'apg_withdrawal_termination_action', 'apg_withdrawal_termination_nonce' ); ?>
				<p class="description form-row form-row-wide"><?php esc_html_e( 'The next step will show a confirmation screen before the termination is sent.', 'apg-withdrawal-for-woocommerce' ); ?></p>
				<p class="form-row">
					<button type="submit" class="button alt<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>"><?php esc_html_e( 'Continue', 'apg-withdrawal-for-woocommerce' ); ?></button>
				</p>
			</form>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Shortcode that prints the contract termination form.
 *
 * @param array $atts Shortcode attributes: 'order_id', 'email'.
 * @return string
 */
function apg_withdrawal_termination_form_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'order_id' => '',
			'email'    => '',
		),
		$atts,
		'apg_withdrawal_termination_form'
	);

	apg_withdrawal_enqueue_form_assets();

	ob_start();
	apg_withdrawal_print_termination_form( $atts );

	return (string) ob_get_clean();
}
add_shortcode( 'apg_withdrawal_termination_form', 'apg_withdrawal_termination_form_shortcode' );

/**
 * Shortcode that prints the link opening the termination function, labelled as
 * Article 23(2) prescribes.
 *
 * @param array $atts Shortcode attributes: 'class', 'text'.
 * @return string
 */
function apg_withdrawal_termination_link_shortcode( $atts ) {
	if ( ! apg_withdrawal_termination_enabled() ) {
		return '';
	}

	$url = apg_withdrawal_get_termination_page_url();

	if ( '' === $url ) {
		return '';
	}

	$atts = shortcode_atts(
		array(
			'class' => 'apg-withdrawal-termination-link',
			'text'  => '',
		),
		$atts,
		'apg_withdrawal_termination_link'
	);

	return sprintf(
		'<a class="%1$s" href="%2$s">%3$s</a>',
		esc_attr( $atts['class'] ),
		esc_url( $url ),
		esc_html( '' !== $atts['text'] ? $atts['text'] : apg_withdrawal_get_termination_label() )
	);
}
add_shortcode( 'apg_withdrawal_termination_link', 'apg_withdrawal_termination_link_shortcode' );
