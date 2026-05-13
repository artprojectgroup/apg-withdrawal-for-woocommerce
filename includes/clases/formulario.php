<?php
/**
 * Render del formulario público.
 *
 * @package WC_APG_Withdrawal
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ensures the WooCommerce wc_form_field() function is available, loading it if necessary.
 *
 * @return bool True if wc_form_field() is available, false otherwise.
 */
function apg_withdrawal_ensure_wc_form_field() {
	if ( function_exists( 'wc_form_field' ) ) {
		return true;
	}

	if ( defined( 'WC_ABSPATH' ) && file_exists( WC_ABSPATH . 'includes/wc-template-functions.php' ) ) {
		include_once WC_ABSPATH . 'includes/wc-template-functions.php';
	}

	return function_exists( 'wc_form_field' );
}

/**
 * Returns the best available display name for a WordPress user.
 *
 * @param WP_User $user WordPress user object.
 * @return string Full name, display name, or empty string if user is invalid.
 */
function apg_withdrawal_get_default_customer_name( $user ) {
	if ( ! ( $user instanceof WP_User ) || ! $user->exists() ) {
		return '';
	}

	$full_name = trim( implode( ' ', array_filter( array( $user->first_name, $user->last_name ) ) ) );

	if ( $full_name ) {
		return $full_name;
	}

	return $user->display_name ? $user->display_name : '';
}

/**
 * Returns the URL of the current withdrawal form page or account endpoint.
 *
 * @return string Absolute URL for the withdrawal form.
 */
function apg_withdrawal_get_current_form_url() {
	if ( function_exists( 'is_account_page' ) && is_account_page() && function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'withdrawal' ) ) {
		return apg_withdrawal_get_account_url();
	}

	$page_id = apg_withdrawal_get_page_id();

	if ( $page_id && is_page( $page_id ) ) {
		return get_permalink( $page_id );
	}

	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

	if ( $request_uri ) {
		$request_path = strtok( $request_uri, '?' );

		if ( $request_path ) {
			return home_url( trailingslashit( ltrim( $request_path, '/' ) ) );
		}
	}

	return get_permalink() ? get_permalink() : home_url();
}

/**
 * Retrieves and sanitizes a single scalar value from the current POST or GET request.
 *
 * @param string   $key               Request parameter key.
 * @param callable $sanitize_callback Sanitization callback to apply to the value.
 * @param string   $default           Default value when the key is absent.
 * @return string Sanitized request value.
 */
function apg_withdrawal_get_request_value( $key, $sanitize_callback = 'sanitize_text_field', $default = '' ) {
	$value = $default;

	if ( isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only pre-fill; submission verified by nonce in procesador.php
		$value = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only pre-fill
	} elseif ( isset( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pre-fill; submission verified by nonce in procesador.php
		$value = wp_unslash( $_GET[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only pre-fill
	}

	return is_callable( $sanitize_callback ) ? call_user_func( $sanitize_callback, $value ) : $value;
}

/**
 * Retrieves and sanitizes an array value from the current POST or GET request.
 *
 * @param string   $key               Request parameter key.
 * @param callable $sanitize_callback Sanitization callback applied to each element.
 * @return array Sanitized array of values.
 */
function apg_withdrawal_get_request_array( $key, $sanitize_callback = 'sanitize_text_field' ) {
	$value = array();

	if ( isset( $_POST[ $key ] ) && is_array( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only pre-fill; submission verified by nonce in procesador.php
		$value = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only pre-fill
	} elseif ( isset( $_GET[ $key ] ) && is_array( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pre-fill; submission verified by nonce in procesador.php
		$value = wp_unslash( $_GET[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only pre-fill
	}

	return array_map( $sanitize_callback, $value );
}

/**
 * Enqueues the CSS and JavaScript assets required by the withdrawal form.
 *
 * @return void
 */
function apg_withdrawal_enqueue_form_assets() {
	wp_enqueue_style( 'apg-withdrawal-admin' );

	if ( function_exists( 'WC' ) || class_exists( 'WooCommerce' ) ) {
		wp_enqueue_style( 'select2' );
		wp_enqueue_script( 'selectWoo' );
	}
}

/**
 * Returns the HTML markup for a withdrawal form notice based on a message key.
 *
 * @param string $message Notice key (e.g. 'success', 'error', 'nonce').
 * @return string HTML notice markup, or empty string if message is empty.
 */
function apg_withdrawal_render_notice_html( $message ) {
	if ( ! $message ) {
		return '';
	}

	ob_start();
	?>
	<div class="<?php echo esc_attr( 'success' === $message ? 'woocommerce-message' : 'woocommerce-error' ); ?>"><?php echo esc_html( apg_withdrawal_get_notice_message( $message ) ); ?></div>
	<?php

	return ob_get_clean();
}

/**
 * Returns the HTML for the withdrawal confirmation form shown before final submission.
 *
 * @param string        $name              Customer full name.
 * @param string        $email             Customer email address.
 * @param string        $order_id          Order reference value.
 * @param string        $scope             Withdrawal scope ('full' or 'partial').
 * @param string        $details           Optional additional details.
 * @param array         $selected_products Array of selected line-item IDs.
 * @param WC_Order|bool $current_order     WooCommerce order object or false.
 * @param array         $settings          Plugin settings array.
 * @param string        $form_action       Form action URL.
 * @param string        $phone             Optional customer phone number.
 * @param bool          $expired_warning   Whether to display an expired-period warning.
 * @return string HTML confirmation form markup.
 */
function apg_withdrawal_render_confirmation_form( $name, $email, $order_id, $scope, $details, $selected_products, $current_order, $settings, $form_action, $phone = '', $expired_warning = false ) {
	ob_start();
	?>
	<form class="apg-withdrawal-confirmation" method="post" action="<?php echo esc_url( $form_action ); ?>">
		<h2><?php esc_html_e( 'Review your withdrawal request', 'wc-apg-withdrawal' ); ?></h2>
		<p class="apg-withdrawal-legal-text">
			<?php esc_html_e( 'By submitting this request you communicate your wish to withdraw from the contract in accordance with consumer protection legislation.', 'wc-apg-withdrawal' ); ?>
			<?php esc_html_e( 'Receipt of this request does not automatically imply that the right of withdrawal applies; the applicable legal exceptions may apply.', 'wc-apg-withdrawal' ); ?>
		</p>
		<?php if ( $expired_warning ) : ?>
		<div class="woocommerce-info apg-withdrawal-expired-notice">
			<?php esc_html_e( 'According to the available information, the ordinary withdrawal period for this order may have expired. You may still submit your request and it will be reviewed by the store.', 'wc-apg-withdrawal' ); ?>
		</div>
		<?php endif; ?>
		<input type="hidden" name="apg_withdrawal_confirm_inline" value="1">
		<input type="hidden" name="apg_withdrawal_name" value="<?php echo esc_attr( $name ); ?>">
		<input type="hidden" name="apg_withdrawal_order" value="<?php echo esc_attr( $order_id ); ?>">
		<input type="hidden" name="apg_withdrawal_email" value="<?php echo esc_attr( $email ); ?>">
		<input type="hidden" name="apg_withdrawal_phone" value="<?php echo esc_attr( $phone ); ?>">
		<input type="hidden" name="apg_withdrawal_scope" value="<?php echo esc_attr( $scope ); ?>">
		<input type="hidden" name="apg_withdrawal_details" value="<?php echo esc_attr( $details ); ?>">
		<?php foreach ( $selected_products as $selected_product ) : ?>
			<input type="hidden" name="apg_withdrawal_products[]" value="<?php echo esc_attr( $selected_product ); ?>">
		<?php endforeach; ?>
		<?php wp_nonce_field( 'apg_withdrawal_confirm_action', 'apg_withdrawal_confirm_nonce' ); ?>
		<div class="woocommerce-address-fields">
			<div class="woocommerce-address-fields__field-wrapper">
				<p class="form-row form-row-wide"><strong><?php esc_html_e( 'Customer:', 'wc-apg-withdrawal' ); ?></strong><br><?php echo esc_html( $name ); ?></p>
				<p class="form-row form-row-wide"><strong><?php esc_html_e( 'Email:', 'wc-apg-withdrawal' ); ?></strong><br><?php echo esc_html( $email ); ?></p>
				<?php if ( $phone ) : ?>
				<p class="form-row form-row-wide"><strong><?php esc_html_e( 'Phone:', 'wc-apg-withdrawal' ); ?></strong><br><?php echo esc_html( $phone ); ?></p>
				<?php endif; ?>
				<p class="form-row form-row-wide"><strong><?php esc_html_e( 'Order:', 'wc-apg-withdrawal' ); ?></strong><br><?php echo esc_html( $current_order ? apg_withdrawal_get_order_option_label( $current_order ) : $order_id ); ?></p>
				<p class="form-row form-row-wide"><strong><?php esc_html_e( 'Scope:', 'wc-apg-withdrawal' ); ?></strong><br><?php echo esc_html( 'partial' === $scope ? __( 'Specific products only', 'wc-apg-withdrawal' ) : __( 'Full order', 'wc-apg-withdrawal' ) ); ?></p>
				<?php if ( 'partial' === $scope && $selected_products ) : ?>
					<p class="form-row form-row-wide"><strong><?php esc_html_e( 'Products:', 'wc-apg-withdrawal' ); ?></strong><br><?php echo esc_html( implode( ', ', apg_withdrawal_get_selected_product_labels( $current_order, $selected_products ) ) ); ?></p>
				<?php endif; ?>
				<?php if ( $details ) : ?>
					<p class="form-row form-row-wide"><strong><?php esc_html_e( 'Additional details:', 'wc-apg-withdrawal' ); ?></strong><br><?php echo nl2br( esc_html( $details ) ); ?></p>
				<?php endif; ?>
				<p class="form-row apg-withdrawal-legal-notice">
					<?php esc_html_e( 'Receipt of this request does not imply automatic acceptance of the right of withdrawal; the legally applicable exceptions may apply.', 'wc-apg-withdrawal' ); ?>
				</p>
				<p class="form-row">
					<button type="submit" class="button alt wp-element-button"><?php echo esc_html( $settings['button_text'] ); ?></button>
					<button type="button" class="button apg-withdrawal-back-btn"><?php esc_html_e( 'Cancel', 'wc-apg-withdrawal' ); ?></button>
				</p>
			</div>
		</div>
	</form>
	<?php

	return ob_get_clean();
}

/**
 * Renders a WooCommerce-styled form field, falling back to a custom renderer if needed.
 *
 * @param string $key   Field name/ID attribute.
 * @param array  $args  Field configuration arguments (type, label, required, options, etc.).
 * @param mixed  $value Current field value.
 * @return string HTML field markup.
 */
function apg_withdrawal_render_field( $key, $args, $value = null ) {
	if ( apg_withdrawal_ensure_wc_form_field() ) {
		return wc_form_field( $key, $args, $value );
	}

	$defaults = array(
		'type'              => 'text',
		'label'             => '',
		'required'          => false,
		'class'             => array( 'form-row-wide' ),
		'input_class'       => array(),
		'options'           => array(),
		'autocomplete'      => '',
		'custom_attributes' => array(),
	);
	$args     = wp_parse_args( $args, $defaults );
	$type     = $args['type'];
	$classes  = implode( ' ', array_map( 'sanitize_html_class', (array) $args['class'] ) );
	$input_classes = implode( ' ', array_map( 'sanitize_html_class', (array) $args['input_class'] ) );
	$required_text = $args['required'] ? '&nbsp;<span class="required" aria-hidden="true">*</span>' : '&nbsp;<span class="optional">(' . esc_html__( 'optional', 'wc-apg-withdrawal' ) . ')</span>';
	$label_class   = $args['required'] ? 'required_field' : '';
	$attr_required = $args['required'] ? ' aria-required="true" required' : '';
	$attr_auto     = $args['autocomplete'] ? ' autocomplete="' . esc_attr( $args['autocomplete'] ) . '"' : '';
	$attr_custom   = '';
	if ( ! empty( $args['custom_attributes'] ) && is_array( $args['custom_attributes'] ) ) {
		foreach ( $args['custom_attributes'] as $attr_key => $attr_val ) {
			$attr_custom .= ' ' . esc_attr( $attr_key ) . '="' . esc_attr( $attr_val ) . '"';
		}
	}

	ob_start();
	?>
	<p class="form-row <?php echo esc_attr( $classes ); ?>" id="<?php echo esc_attr( $key ); ?>_field">
		<label for="<?php echo esc_attr( $key ); ?>" class="<?php echo esc_attr( $label_class ); ?>">
			<?php echo esc_html( $args['label'] ); ?><?php echo wp_kses_post( $required_text ); ?>
		</label>
		<span class="woocommerce-input-wrapper">
			<?php if ( 'select' === $type ) : ?>
				<select name="<?php echo esc_attr( $key ); ?>" id="<?php echo esc_attr( $key ); ?>" class="<?php echo esc_attr( $input_classes ); ?>"<?php echo esc_attr( $attr_required ); ?>>
					<?php foreach ( $args['options'] as $option_value => $option_label ) : ?>
						<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( (string) $value, (string) $option_value ); ?>><?php echo esc_html( $option_label ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php elseif ( 'textarea' === $type ) : ?>
				<textarea name="<?php echo esc_attr( $key ); ?>" id="<?php echo esc_attr( $key ); ?>" class="<?php echo esc_attr( $input_classes ); ?>" rows="5"<?php echo esc_attr( $attr_required ) . esc_attr( $attr_auto ); ?>><?php echo esc_textarea( (string) $value ); ?></textarea>
			<?php elseif ( 'checkbox' === $type ) : ?>
				<label class="checkbox" for="<?php echo esc_attr( $key ); ?>">
					<input type="checkbox" name="<?php echo esc_attr( $key ); ?>" id="<?php echo esc_attr( $key ); ?>" value="1"<?php checked( ! empty( $value ) ); ?><?php echo esc_attr( $attr_required ); ?>>
					<?php echo esc_html( $args['label'] ); ?>
				</label>
			<?php else : ?>
				<input type="<?php echo esc_attr( $type ); ?>" class="<?php echo esc_attr( $input_classes ); ?>" name="<?php echo esc_attr( $key ); ?>" id="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( (string) $value ); ?>"<?php echo $attr_required . $attr_auto . $attr_custom; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- All parts pre-escaped above ?>>
			<?php endif; ?>
		</span>
	</p>
	<?php

	return ob_get_clean();
}

/**
 * Renders the full withdrawal request form, handling both step display and submission.
 *
 * @param array $atts Optional shortcode/function attributes ('order_id', 'email').
 * @return string HTML markup for the complete withdrawal form.
 */
function apg_withdrawal_render_form( $atts = array() ) {
	$atts = wp_parse_args(
		$atts,
		array(
			'order_id' => '',
			'email'    => '',
		)
	);

	$settings = apg_withdrawal_get_settings();
	$step     = apg_withdrawal_get_request_value( 'apg_withdrawal_step', 'sanitize_key', 'request' );
	$message  = apg_withdrawal_get_request_value( 'apg_withdrawal_notice', 'sanitize_key', '' );
	$current_user      = wp_get_current_user();
	$current_user_id   = $current_user instanceof WP_User ? $current_user->ID : 0;
	$default_name      = apg_withdrawal_get_default_customer_name( $current_user );
	$default_email     = ! empty( $atts['email'] ) ? $atts['email'] : ( $current_user instanceof WP_User ? $current_user->user_email : '' );
	$default_phone     = $current_user_id ? (string) get_user_meta( $current_user_id, 'billing_phone', true ) : '';
	$name              = apg_withdrawal_get_request_value( 'name', 'sanitize_text_field', $default_name );
	$email             = apg_withdrawal_get_request_value( 'email', 'sanitize_email', $default_email );
	$order_id          = apg_withdrawal_get_request_value( 'order_id', 'sanitize_text_field', $atts['order_id'] );
	$scope             = apg_withdrawal_get_request_value( 'scope', 'sanitize_key', 'full' );
	$details           = apg_withdrawal_get_request_value( 'details', 'sanitize_textarea_field', '' );
	$phone             = apg_withdrawal_get_request_value( 'phone', 'sanitize_text_field', $default_phone );
	$acceptance        = apg_withdrawal_get_request_value( 'acceptance', 'sanitize_text_field', '' );
	$selected_products = apg_withdrawal_get_request_array( 'products', 'sanitize_text_field' );
	$all_orders        = apg_withdrawal_get_customer_orders( $email, $current_user_id );
	$active_order_ids  = function_exists( 'apg_withdrawal_get_order_ids_with_active_requests' ) ? apg_withdrawal_get_order_ids_with_active_requests() : array();
	$orders            = array_filter(
		$all_orders,
		function ( $order ) use ( $active_order_ids ) {
			if ( ! is_callable( array( $order, 'get_id' ) ) ) {
				return false;
			}
			return ! in_array( $order->get_id(), $active_order_ids, true );
		}
	);
	$orders_map        = apg_withdrawal_get_orders_products_map( $orders );
	$orders_warning    = function_exists( 'apg_withdrawal_get_orders_warning_map' ) ? apg_withdrawal_get_orders_warning_map( $orders, $settings ) : array();
	$current_order     = $order_id ? apg_withdrawal_get_order( $order_id ) : false;
	$order_products    = $current_order ? apg_withdrawal_get_order_products( $current_order ) : array();
	$form_action       = apg_withdrawal_get_current_form_url();

	apg_withdrawal_enqueue_form_assets();

	$expired_warning = false;

	if ( 'POST' === strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET' ) && isset( $_POST['apg_withdrawal_confirm_inline'] ) ) {
		if ( ! isset( $_POST['apg_withdrawal_confirm_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['apg_withdrawal_confirm_nonce'] ) ), 'apg_withdrawal_confirm_action' ) ) {
			$message = 'nonce';
			$step    = 'request';
		} else {
			$message = apg_withdrawal_process_submission_data(
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
			$step    = 'success' === $message ? 'request' : 'confirm';
		}
	}

	if ( 'confirm' === $step && empty( $acceptance ) ) {
		$step    = 'request';
		$message = 'fields';
	}

	if ( 'confirm' === $step && $current_order ) {
		$validation      = apg_withdrawal_validate_order( $order_id, $email, $settings );
		$expired_warning = $validation['expired_warning'];
	}

	ob_start();
	?>
	<div class="apg-withdrawal-form-wrapper">
		<?php echo wp_kses_post( apg_withdrawal_render_notice_html( $message ) ); ?>

		<?php if ( 'confirm' === $step ) : ?>
			<?php echo wp_kses_post( apg_withdrawal_render_confirmation_form( $name, $email, $order_id, $scope, $details, $selected_products, $current_order, $settings, $form_action, $phone, $expired_warning ) ); ?>
		<?php else : ?>
			<p class="apg-withdrawal-deadline-notice">
				<?php
				printf(
					/* translators: %d number of days of the withdrawal window. */
					esc_html__( 'The right of withdrawal may be exercised within %d calendar days from receipt of the order.', 'wc-apg-withdrawal' ),
					absint( $settings['withdrawal_days'] )
				);
				?>
			</p>
			<form method="post" action="<?php echo esc_url( $form_action ); ?>" class="apg-withdrawal-form">
				<input type="hidden" name="apg_withdrawal_step" value="confirm">
				<?php wp_nonce_field( 'apg_withdrawal_preview_action', 'apg_withdrawal_preview_nonce' ); ?>
				<h2><?php esc_html_e( 'Withdrawal request', 'wc-apg-withdrawal' ); ?></h2>
				<div class="woocommerce-info apg-withdrawal-product-warning" style="display:none;" aria-live="polite"></div>
				<div class="woocommerce-address-fields">
					<div class="woocommerce-address-fields__field-wrapper">
						<?php
						echo apg_withdrawal_render_field( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Internal function returns sanitized HTML
							'name',
							array(
								'type'         => 'text',
								'label'        => __( 'Full name', 'wc-apg-withdrawal' ),
								'required'     => true,
								'class'        => array( 'form-row-wide' ),
								'input_class'  => array( 'input-text' ),
								'autocomplete' => 'name',
								'priority'     => 10,
							),
							$name
						);

						echo apg_withdrawal_render_field( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Internal function returns sanitized HTML
							'email',
							array(
								'type'         => 'email',
								'label'        => __( 'Email', 'wc-apg-withdrawal' ),
								'required'     => true,
								'class'        => array( 'form-row-wide' ),
								'input_class'  => array( 'input-text' ),
								'autocomplete' => 'email',
								'priority'     => 20,
							),
							$email
						);

						echo apg_withdrawal_render_field( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Internal function returns sanitized HTML
							'phone',
							array(
								'type'         => 'tel',
								'label'        => __( 'Phone', 'wc-apg-withdrawal' ),
								'required'     => false,
								'class'        => array( 'form-row-wide' ),
								'input_class'  => array( 'input-text' ),
								'autocomplete' => 'tel',
								'priority'     => 25,
							),
							$phone
						);

						if ( $orders ) {
							$order_options = array(
								'' => __( 'Select an order', 'wc-apg-withdrawal' ),
							);

							foreach ( $orders as $order ) {
								$label = apg_withdrawal_get_order_option_label( $order );
								if ( isset( $orders_warning[ (string) $order->get_id() ] ) && 'expired' === $orders_warning[ (string) $order->get_id() ] ) {
									$label .= ' *';
								}
								$order_options[ $order->get_id() ] = $label;
							}

							echo apg_withdrawal_render_field( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Internal function returns sanitized HTML
								'order_id',
								array(
									'type'        => 'select',
									'label'       => __( 'Order number', 'wc-apg-withdrawal' ),
									'required'    => true,
									'class'       => array( 'form-row-wide' ),
									'input_class' => array( 'apg-withdrawal-selectwoo', 'wc-enhanced-select' ),
									'options'     => $order_options,
									'priority'    => 30,
								),
								$order_id
							);
						} else {
							echo apg_withdrawal_render_field( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Internal function returns sanitized HTML
								'order_id',
								array(
									'type'              => 'text',
									'label'             => __( 'Order number', 'wc-apg-withdrawal' ),
									'required'          => true,
									'class'             => array( 'form-row-wide' ),
									'input_class'       => array( 'input-text' ),
									'priority'          => 30,
									'custom_attributes' => $email ? array() : array( 'disabled' => 'disabled' ),
								),
								$order_id
							);
						}

						echo apg_withdrawal_render_field( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Internal function returns sanitized HTML
							'scope',
							array(
								'type'        => 'select',
								'label'       => __( 'Withdrawal scope', 'wc-apg-withdrawal' ),
								'class'       => array( 'form-row-wide' ),
								'input_class' => array( 'apg-withdrawal-selectwoo', 'wc-enhanced-select' ),
								'options'     => array(
									'full'    => __( 'Full order', 'wc-apg-withdrawal' ),
									'partial' => __( 'Specific products only', 'wc-apg-withdrawal' ),
								),
								'priority'    => 40,
							),
							$scope
						);
						?>
							<p class="form-row form-row-wide apg-withdrawal-products-row" id="products_field" data-priority="50" <?php echo 'partial' === $scope ? '' : 'style="display:none;"'; ?>>
							<label for="products"><?php esc_html_e( 'Products to withdraw', 'wc-apg-withdrawal' ); ?></label>
							<span class="woocommerce-input-wrapper">
								<select class="apg-withdrawal-selectwoo wc-enhanced-select" id="products" name="products[]" multiple="multiple" size="5">
									<?php foreach ( $order_products as $item_id => $product_label ) : ?>
										<option value="<?php echo esc_attr( $item_id ); ?>" <?php selected( in_array( (string) $item_id, $selected_products, true ), true ); ?>><?php echo esc_html( $product_label ); ?></option>
									<?php endforeach; ?>
								</select>
							</span>
							<span class="description"><?php esc_html_e( 'Choose one or more products from the selected order.', 'wc-apg-withdrawal' ); ?></span>
						</p>
						<?php
						echo apg_withdrawal_render_field( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Internal function returns sanitized HTML
							'details',
							array(
								'type'        => 'textarea',
								'label'       => __( 'Additional details', 'wc-apg-withdrawal' ),
								'class'       => array( 'form-row-wide' ),
								'input_class' => array( 'input-text' ),
								'priority'    => 60,
							),
							$details
						);

						?>
						<p class="form-row form-row-wide apg-withdrawal-checkbox" id="acceptance_field">
							<label class="checkbox" for="acceptance">
								<input type="checkbox" name="acceptance" id="acceptance" value="1" aria-required="true" required<?php checked( ! empty( $acceptance ) ); ?>>
								<?php esc_html_e( 'I understand that the next step will submit my withdrawal request after confirmation.', 'wc-apg-withdrawal' ); ?><span class="required" aria-hidden="true">&nbsp;*</span>
							</label>
						</p>
						<p class="description form-row form-row-wide"><?php esc_html_e( 'The next step will show a confirmation screen before the request is submitted.', 'wc-apg-withdrawal' ); ?></p>
						<p class="form-row">
							<button type="submit" class="button alt wp-element-button"><?php esc_html_e( 'Open withdrawal confirmation', 'wc-apg-withdrawal' ); ?></button>
						</p>
					</div>
				</div>
				</form>
				<?php
				$apg_withdrawal_script_handle = 'apg-withdrawal-frontend';
				wp_register_script(
					$apg_withdrawal_script_handle,
					plugins_url( 'assets/js/frontend.js', apg_withdrawal_DIRECCION ),
					array(),
					apg_withdrawal_VERSION,
					true
				);
				wp_localize_script(
					$apg_withdrawal_script_handle,
					'apgWithdrawal',
					array(
						'ordersNonce'      => wp_create_nonce( 'apg_withdrawal_guest_orders' ),
						'productsMap'      => $orders_map,
						'selectedProducts' => array_values( $selected_products ),
						'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
						'ordersWarning'    => $orders_warning,
						'i18n'             => array(
							'noResults'        => __( 'No results found', 'wc-apg-withdrawal' ),
							/* translators: %s: email address entered by the user. */
							'noOrdersForEmail' => __( 'No orders were found in the store for the email address %s.', 'wc-apg-withdrawal' ),
							'chooseProducts'   => __( 'Choose products...', 'wc-apg-withdrawal' ),
						),
						'warningMessages'  => array(
							'excluded'     => __( "According to this product's configuration, the right of withdrawal may not apply under the legally provided exceptions. Your request will be reviewed by the store.", 'wc-apg-withdrawal' ),
							'digital'      => __( 'This order contains digital content. If execution began with your prior express consent and you acknowledged the loss of the right of withdrawal at purchase, this right may no longer apply.', 'wc-apg-withdrawal' ),
							'personalized' => __( 'This order contains personalised products. The right of withdrawal does not apply to goods made to your specifications or clearly personalised.', 'wc-apg-withdrawal' ),
							'manual'       => __( 'This request requires manual review. The store will evaluate whether the right of withdrawal applies to this order.', 'wc-apg-withdrawal' ),
							'expired'      => __( 'According to the available information, the ordinary withdrawal period for this order may have expired. You may still submit your request and it will be reviewed by the store.', 'wc-apg-withdrawal' ),
						),
					)
				);
				wp_enqueue_script( $apg_withdrawal_script_handle );
				?>
		<?php endif; ?>
	</div>
	<?php

	return ob_get_clean();
}

/**
 * Returns the translated notice message string for a given message key.
 *
 * @param string $message Message key (e.g. 'success', 'nonce', 'fields', 'order').
 * @return string Translated notice message.
 */
function apg_withdrawal_get_notice_message( $message ) {
	$messages = array(
		'success' => __( 'Your withdrawal request has been registered.', 'wc-apg-withdrawal' ),
		'nonce'   => __( 'Security validation failed. Please try again.', 'wc-apg-withdrawal' ),
		'fields'  => __( 'Please complete all required fields before continuing.', 'wc-apg-withdrawal' ),
		'email'   => __( 'The email address is not valid.', 'wc-apg-withdrawal' ),
		'order'   => __( 'The order could not be matched with the email address provided.', 'wc-apg-withdrawal' ),
		'expired' => __( 'The withdrawal period for this order appears to have expired.', 'wc-apg-withdrawal' ),
		'general' => __( 'An error occurred while processing the withdrawal request.', 'wc-apg-withdrawal' ),
	);

	return isset( $messages[ $message ] ) ? $messages[ $message ] : $messages['general'];
}

/**
 * AJAX handler: returns orders matching a guest email address.
 *
 * Called on email blur when the order field is a plain text input (guest with no pre-filled
 * orders). If orders are found, the JS upgrades the input to a <select> dynamically.
 *
 * @return void
 */
function apg_withdrawal_ajax_get_guest_orders() {
	if ( ! check_ajax_referer( 'apg_withdrawal_guest_orders', 'nonce', false ) ) {
		wp_send_json_error( null, 403 );
	}

	$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

	if ( ! is_email( $email ) ) {
		wp_send_json_success( array( 'orders' => array() ) );
		return;
	}

	$settings         = apg_withdrawal_get_settings();
	$lookup_user      = get_user_by( 'email', $email );
	$lookup_user_id   = $lookup_user instanceof WP_User ? $lookup_user->ID : 0;
	$all_orders       = apg_withdrawal_get_customer_orders( $email, $lookup_user_id );
	$active_order_ids = function_exists( 'apg_withdrawal_get_order_ids_with_active_requests' )
		? apg_withdrawal_get_order_ids_with_active_requests()
		: array();

	$orders = array_filter(
		$all_orders,
		function ( $order ) use ( $active_order_ids ) {
			if ( ! is_callable( array( $order, 'get_id' ) ) ) {
				return false;
			}
			return ! in_array( $order->get_id(), $active_order_ids, true );
		}
	);

	$warning_map  = function_exists( 'apg_withdrawal_get_orders_warning_map' )
		? apg_withdrawal_get_orders_warning_map( $orders, $settings )
		: array();
	$orders_data  = array();
	$products_map = array();

	foreach ( $orders as $order ) {
		$order_id = $order->get_id();
		$label    = apg_withdrawal_get_order_option_label( $order );
		if ( isset( $warning_map[ (string) $order_id ] ) && 'expired' === $warning_map[ (string) $order_id ] ) {
			$label .= ' *';
		}
		$orders_data[] = array(
			'id'    => $order_id,
			'label' => $label,
		);

		$products_map[ $order_id ] = array();

		foreach ( $order->get_items() as $item_id => $item ) {
			$products_map[ $order_id ][ (string) $item_id ] = sprintf(
				/* translators: 1: product name, 2: quantity. */
				__( '%1$s x %2$d', 'wc-apg-withdrawal' ),
				$item->get_name(),
				$item->get_quantity()
			);
		}
	}

	wp_send_json_success(
		array(
			'orders'      => $orders_data,
			'productsMap' => $products_map,
			'warningMap'  => $warning_map,
			'placeholder' => __( 'Select an order', 'wc-apg-withdrawal' ),
		)
	);
}
add_action( 'wp_ajax_apg_withdrawal_get_guest_orders', 'apg_withdrawal_ajax_get_guest_orders' );
add_action( 'wp_ajax_nopriv_apg_withdrawal_get_guest_orders', 'apg_withdrawal_ajax_get_guest_orders' );
