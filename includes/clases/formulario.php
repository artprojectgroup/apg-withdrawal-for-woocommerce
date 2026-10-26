<?php
/**
 * Public withdrawal-request form rendering.
 *
 * @package APG_Withdrawal_For_WooCommerce
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
	// 'apg-withdrawal-admin' used to be asked for here, a handle that only
	// exists inside wp-admin, so the form went out with no stylesheet of its own
	// whenever it was rendered somewhere the wp_enqueue_scripts detection had
	// not already covered, such as a shortcode inside a widget or an FSE
	// template part.
	wp_enqueue_style( 'apg-withdrawal' );

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
	ob_start();
	apg_withdrawal_print_notice( $message );

	return (string) ob_get_clean();
}

/**
 * Reports whether a result key ends the submission, one way or another: the
 * request was registered, it is waiting for the customer to open the
 * confirmation link, or it was already on file. In all three cases there is
 * nothing left for the customer to correct, so the form gives way to the notice
 * instead of staying on the confirmation step.
 *
 * @param string $message Result key returned by the processor.
 * @return bool
 */
function apg_withdrawal_notice_is_final( $message ) {
	return in_array( $message, array( 'success', 'verify_sent', 'duplicate' ), true );
}

/**
 * Prints a withdrawal form notice through WooCommerce, so the theme paints it
 * like every other notice in the shop.
 *
 * Printing rather than returning keeps the markup out of `wp_kses_post()`,
 * whose allowlist would quietly drop anything structural WooCommerce decides to
 * put in a notice template.
 *
 * @param string $message Notice key (e.g. 'success', 'error', 'nonce').
 * @return void
 */
function apg_withdrawal_print_notice( $message ) {
	if ( ! $message || ! function_exists( 'wc_print_notice' ) ) {
		return;
	}

	if ( in_array( $message, array( 'success', 'verify_sent' ), true ) ) {
		$type = 'success';
	} elseif ( 'duplicate' === $message ) {
		// Nothing went wrong: the request the customer is sending is already on
		// file, so this tells them so instead of alarming them with an error.
		$type = 'notice';
	} else {
		$type = 'error';
	}

	wc_print_notice( apg_withdrawal_get_notice_message( $message ), $type );
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
 * @param array         $quantities        Units being withdrawn, keyed by line-item ID.
 * @param string        $iban              Optional normalised IBAN for the refund.
 * @return void
 */
function apg_withdrawal_print_confirmation_form( $name, $email, $order_id, $scope, $details, $selected_products, $current_order, $settings, $form_action, $phone = '', $expired_warning = false, $quantities = array(), $iban = '' ) {
	$statutory_reference = function_exists( 'apg_withdrawal_get_statutory_reference' ) ? apg_withdrawal_get_statutory_reference() : '';
	$needs_verification  = function_exists( 'apg_withdrawal_guest_verification_required' ) && apg_withdrawal_guest_verification_required();
	?>
	<form class="apg-withdrawal-confirmation" method="post" action="<?php echo esc_url( $form_action ); ?>" aria-labelledby="apg-withdrawal-confirmation-title">
		<h2 id="apg-withdrawal-confirmation-title" tabindex="-1"><?php esc_html_e( 'Review your withdrawal request', 'apg-withdrawal-for-woocommerce' ); ?></h2>
		<p class="apg-withdrawal-legal-text">
			<?php esc_html_e( 'By submitting this request you communicate your wish to withdraw from the contract in accordance with consumer protection legislation.', 'apg-withdrawal-for-woocommerce' ); ?>
			<?php esc_html_e( 'Receipt of this request does not automatically imply that the right of withdrawal applies; the applicable legal exceptions may apply.', 'apg-withdrawal-for-woocommerce' ); ?>
		</p>
		<?php
		if ( $expired_warning && function_exists( 'wc_print_notice' ) ) {
			wc_print_notice(
				__( 'According to the available information, the ordinary withdrawal period for this order may have expired. You may still submit your request and it will be reviewed by the store.', 'apg-withdrawal-for-woocommerce' ),
				'notice'
			);
		}

		if ( $needs_verification && function_exists( 'wc_print_notice' ) ) {
			wc_print_notice(
				__( 'To confirm that this request comes from you, we will send a confirmation link to the email address on the order. Your request will be dated at the moment you submit this form.', 'apg-withdrawal-for-woocommerce' ),
				'notice'
			);
		}
		?>
		<input type="hidden" name="apg_withdrawal_confirm_inline" value="1">
		<p class="apg-withdrawal-honeypot" aria-hidden="true">
			<label><?php esc_html_e( 'Website (leave empty)', 'apg-withdrawal-for-woocommerce' ); ?>
				<input type="text" name="apg_withdrawal_website" value="" autocomplete="off" tabindex="-1">
			</label>
		</p>
		<input type="hidden" name="apg_withdrawal_name" value="<?php echo esc_attr( $name ); ?>">
		<input type="hidden" name="apg_withdrawal_order" value="<?php echo esc_attr( $order_id ); ?>">
		<input type="hidden" name="apg_withdrawal_email" value="<?php echo esc_attr( $email ); ?>">
		<input type="hidden" name="apg_withdrawal_phone" value="<?php echo esc_attr( $phone ); ?>">
		<input type="hidden" name="apg_withdrawal_scope" value="<?php echo esc_attr( $scope ); ?>">
		<input type="hidden" name="apg_withdrawal_details" value="<?php echo esc_attr( $details ); ?>">
		<input type="hidden" name="apg_withdrawal_iban" value="<?php echo esc_attr( $iban ); ?>">
		<?php foreach ( $selected_products as $selected_product ) : ?>
			<input type="hidden" name="apg_withdrawal_products[]" value="<?php echo esc_attr( $selected_product ); ?>">
			<input type="hidden" name="apg_withdrawal_quantities[<?php echo esc_attr( $selected_product ); ?>]" value="<?php echo esc_attr( isset( $quantities[ (string) $selected_product ] ) ? $quantities[ (string) $selected_product ] : 1 ); ?>">
		<?php endforeach; ?>
		<?php wp_nonce_field( 'apg_withdrawal_confirm_action', 'apg_withdrawal_confirm_nonce' ); ?>
		<div class="woocommerce-address-fields">
			<div class="woocommerce-address-fields__field-wrapper">
				<p class="form-row form-row-wide"><strong><?php esc_html_e( 'Customer:', 'apg-withdrawal-for-woocommerce' ); ?></strong><br><?php echo esc_html( $name ); ?></p>
				<p class="form-row form-row-wide"><strong><?php esc_html_e( 'Email:', 'apg-withdrawal-for-woocommerce' ); ?></strong><br><?php echo esc_html( $email ); ?></p>
				<?php if ( $phone ) : ?>
				<p class="form-row form-row-wide"><strong><?php esc_html_e( 'Phone:', 'apg-withdrawal-for-woocommerce' ); ?></strong><br><?php echo esc_html( $phone ); ?></p>
				<?php endif; ?>
				<p class="form-row form-row-wide"><strong><?php esc_html_e( 'Order:', 'apg-withdrawal-for-woocommerce' ); ?></strong><br><?php echo esc_html( $current_order ? apg_withdrawal_get_order_option_label( $current_order ) : $order_id ); ?></p>
				<p class="form-row form-row-wide"><strong><?php esc_html_e( 'Scope:', 'apg-withdrawal-for-woocommerce' ); ?></strong><br><?php echo esc_html( 'partial' === $scope ? __( 'Specific products only', 'apg-withdrawal-for-woocommerce' ) : __( 'Full order', 'apg-withdrawal-for-woocommerce' ) ); ?></p>
				<?php if ( 'partial' === $scope && $selected_products ) : ?>
					<p class="form-row form-row-wide"><strong><?php esc_html_e( 'Products:', 'apg-withdrawal-for-woocommerce' ); ?></strong><br><?php echo esc_html( implode( ', ', apg_withdrawal_get_selected_product_labels( $current_order, $selected_products, $quantities ) ) ); ?></p>
				<?php endif; ?>
				<?php if ( $iban ) : ?>
					<p class="form-row form-row-wide"><strong><?php esc_html_e( 'Account for the refund:', 'apg-withdrawal-for-woocommerce' ); ?></strong><br><?php echo esc_html( apg_withdrawal_format_iban( $iban ) ); ?></p>
				<?php endif; ?>
				<?php if ( $details ) : ?>
					<p class="form-row form-row-wide"><strong><?php esc_html_e( 'Additional details:', 'apg-withdrawal-for-woocommerce' ); ?></strong><br><?php echo nl2br( esc_html( $details ) ); ?></p>
				<?php endif; ?>
				<p class="form-row apg-withdrawal-legal-notice">
					<?php esc_html_e( 'Receipt of this request does not imply automatic acceptance of the right of withdrawal; the legally applicable exceptions may apply.', 'apg-withdrawal-for-woocommerce' ); ?>
				</p>
				<?php if ( $statutory_reference ) : ?>
				<p class="form-row apg-withdrawal-legal-notice"><?php echo esc_html( $statutory_reference ); ?></p>
				<?php endif; ?>
				<p class="form-row">
					<button type="submit" class="button alt<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>"><?php echo esc_html( function_exists( 'apg_withdrawal_translate_setting_string' ) ? apg_withdrawal_translate_setting_string( $settings['button_text'], 'Confirmation button text' ) : $settings['button_text'] ); ?></button>
					<button type="button" class="button apg-withdrawal-back-btn<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>"><?php esc_html_e( 'Cancel', 'apg-withdrawal-for-woocommerce' ); ?></button>
				</p>
			</div>
		</div>
	</form>
	<?php
}

/**
 * Returns the confirmation form as a string, for the AJAX step that has to hand
 * the markup back in a JSON payload.
 *
 * Note for callers: this markup is a form with inputs and buttons, so it must
 * never be run through `wp_kses_post()` — that allowlist drops every one of
 * those tags and leaves the customer looking at a review screen with nothing to
 * submit. Print it with apg_withdrawal_print_confirmation_form() instead.
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
 * @param array         $quantities        Units being withdrawn, keyed by line-item ID.
 * @param string        $iban              Optional normalised IBAN for the refund.
 * @return string HTML confirmation form markup.
 */
function apg_withdrawal_render_confirmation_form( $name, $email, $order_id, $scope, $details, $selected_products, $current_order, $settings, $form_action, $phone = '', $expired_warning = false, $quantities = array(), $iban = '' ) {
	ob_start();
	apg_withdrawal_print_confirmation_form( $name, $email, $order_id, $scope, $details, $selected_products, $current_order, $settings, $form_action, $phone, $expired_warning, $quantities, $iban );

	return (string) ob_get_clean();
}

/**
 * Renders the selectable line items of a partial withdrawal: one row per order
 * line, with a checkbox that puts the line in scope and a number input bounded
 * to the units originally ordered.
 *
 * The same markup is produced client-side by `assets/js/frontend.js` when the
 * customer switches order, so both paths must stay in sync — the structure is
 * deliberately plain (no SelectWoo) to keep it operable with a keyboard and
 * announceable by screen readers.
 *
 * @param array<string,array{name:string,quantity:int,label:string}> $items      Line-item data.
 * @param array<int,string>                                          $selected   Selected line-item IDs.
 * @param array<string,int>                                          $quantities Units per line item.
 * @return void
 */
function apg_withdrawal_print_item_rows( $items, $selected, $quantities = array() ) {
	$selected = array_map( 'strval', (array) $selected );

	foreach ( $items as $item_id => $item ) {
		$item_id     = (string) $item_id;
		$ordered     = isset( $item['quantity'] ) ? max( 1, (int) $item['quantity'] ) : 1;
		$is_selected = in_array( $item_id, $selected, true );
		$quantity    = isset( $quantities[ $item_id ] ) ? max( 1, min( $ordered, (int) $quantities[ $item_id ] ) ) : $ordered;
		$checkbox_id = 'apg_withdrawal_item_' . $item_id;
		$quantity_id = 'apg_withdrawal_qty_' . $item_id;
		?>
		<li class="apg-withdrawal-item">
			<label class="checkbox" for="<?php echo esc_attr( $checkbox_id ); ?>">
				<input type="checkbox" id="<?php echo esc_attr( $checkbox_id ); ?>" name="products[]" value="<?php echo esc_attr( $item_id ); ?>"<?php checked( $is_selected ); ?>>
				<?php echo esc_html( isset( $item['label'] ) ? $item['label'] : '' ); ?>
			</label>
			<label class="apg-withdrawal-item-quantity" for="<?php echo esc_attr( $quantity_id ); ?>">
				<?php esc_html_e( 'Units', 'apg-withdrawal-for-woocommerce' ); ?>
				<input type="number" id="<?php echo esc_attr( $quantity_id ); ?>" name="quantities[<?php echo esc_attr( $item_id ); ?>]" value="<?php echo esc_attr( $quantity ); ?>" min="1" max="<?php echo esc_attr( $ordered ); ?>" step="1" inputmode="numeric"<?php disabled( $is_selected, false ); ?>>
			</label>
		</li>
		<?php
	}
}

/**
 * Prints a WooCommerce-styled form field, falling back to a custom renderer
 * when `wc_form_field()` is not available.
 *
 * The function prints instead of returning so that the escaping is visible at
 * the point of output: every caller is a plain `apg_withdrawal_print_field()`
 * with no `echo`, and no security sniff has to be suppressed at the call site.
 *
 * @param string $key   Field name/ID attribute.
 * @param array  $args  Field configuration arguments (type, label, required, options, etc.).
 * @param mixed  $value Current field value.
 * @return void
 */
function apg_withdrawal_print_field( $key, $args, $value = null ) {
	if ( apg_withdrawal_ensure_wc_form_field() ) {
		wc_form_field( $key, $args, $value );

		return;
	}

	$defaults = array(
		'type'              => 'text',
		'label'             => '',
		'required'          => false,
		'class'             => array( 'form-row-wide' ),
		'input_class'       => array(),
		'options'           => array(),
		'autocomplete'      => '',
		'description'       => '',
		'custom_attributes' => array(),
	);
	$args          = wp_parse_args( $args, $defaults );
	$type          = $args['type'];
	$classes       = implode( ' ', array_map( 'sanitize_html_class', (array) $args['class'] ) );
	$input_classes = implode( ' ', array_map( 'sanitize_html_class', (array) $args['input_class'] ) );
	$required_text = $args['required'] ? '&nbsp;<span class="required" aria-hidden="true">*</span>' : '&nbsp;<span class="optional">(' . esc_html__( 'optional', 'apg-withdrawal-for-woocommerce' ) . ')</span>';
	$label_class   = $args['required'] ? 'required_field' : '';
	$custom_attrs  = ! empty( $args['custom_attributes'] ) && is_array( $args['custom_attributes'] ) ? $args['custom_attributes'] : array();
	?>
	<p class="form-row <?php echo esc_attr( $classes ); ?>" id="<?php echo esc_attr( $key ); ?>_field">
		<label for="<?php echo esc_attr( $key ); ?>" class="<?php echo esc_attr( $label_class ); ?>">
			<?php echo esc_html( $args['label'] ); ?><?php echo wp_kses_post( $required_text ); ?>
		</label>
		<span class="woocommerce-input-wrapper">
			<?php if ( 'select' === $type ) : ?>
				<select name="<?php echo esc_attr( $key ); ?>" id="<?php echo esc_attr( $key ); ?>" class="<?php echo esc_attr( $input_classes ); ?>"<?php echo $args['required'] ? ' aria-required="true" required' : ''; ?>>
					<?php foreach ( $args['options'] as $option_value => $option_label ) : ?>
						<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( (string) $value, (string) $option_value ); ?>><?php echo esc_html( $option_label ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php elseif ( 'textarea' === $type ) : ?>
				<textarea name="<?php echo esc_attr( $key ); ?>" id="<?php echo esc_attr( $key ); ?>" class="<?php echo esc_attr( $input_classes ); ?>" rows="5"<?php echo $args['required'] ? ' aria-required="true" required' : ''; ?><?php echo $args['autocomplete'] ? ' autocomplete="' . esc_attr( $args['autocomplete'] ) . '"' : ''; ?>><?php echo esc_textarea( (string) $value ); ?></textarea>
			<?php elseif ( 'checkbox' === $type ) : ?>
				<label class="checkbox" for="<?php echo esc_attr( $key ); ?>">
					<input type="checkbox" name="<?php echo esc_attr( $key ); ?>" id="<?php echo esc_attr( $key ); ?>" value="1"<?php checked( ! empty( $value ) ); ?><?php echo $args['required'] ? ' aria-required="true" required' : ''; ?>>
					<?php echo esc_html( $args['label'] ); ?>
				</label>
			<?php else : ?>
				<input
					type="<?php echo esc_attr( $type ); ?>"
					class="<?php echo esc_attr( $input_classes ); ?>"
					name="<?php echo esc_attr( $key ); ?>"
					id="<?php echo esc_attr( $key ); ?>"
					value="<?php echo esc_attr( (string) $value ); ?>"
					<?php echo $args['required'] ? ' aria-required="true" required' : ''; ?>
					<?php echo $args['autocomplete'] ? ' autocomplete="' . esc_attr( $args['autocomplete'] ) . '"' : ''; ?>
					<?php foreach ( $custom_attrs as $attr_key => $attr_value ) : ?>
						<?php echo esc_attr( $attr_key ); ?>="<?php echo esc_attr( $attr_value ); ?>"
					<?php endforeach; ?>
					<?php echo $args['description'] ? ' aria-describedby="' . esc_attr( $key ) . '-description"' : ''; ?>
				>
			<?php endif; ?>
		</span>
		<?php if ( $args['description'] ) : ?>
			<span class="description" id="<?php echo esc_attr( $key ); ?>-description"><?php echo esc_html( $args['description'] ); ?></span>
		<?php endif; ?>
	</p>
	<?php
}

/**
 * Prints the full withdrawal request form, handling both step display and
 * submission. Prints rather than returns so callers need no `echo` and no
 * escaping suppression; {@see apg_withdrawal_render_form()} wraps it for the
 * shortcode, which has to hand a string back to WordPress.
 *
 * @param array $atts Optional shortcode/function attributes ('order_id', 'email').
 * @return void
 */
function apg_withdrawal_print_form( $atts = array() ) {
	$atts = wp_parse_args(
		$atts,
		array(
			'order_id' => '',
			'email'    => '',
			// The My Account endpoint prints the tab heading itself, so it asks
			// the form not to repeat it. Anywhere else the form keeps its own.
			'heading'  => true,
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
	$iban              = apg_withdrawal_normalize_iban( apg_withdrawal_get_request_value( 'iban', 'sanitize_text_field', '' ) );
	$iban_mode         = isset( $settings['iban_field'] ) ? (string) $settings['iban_field'] : 'hidden';
	$selected_products = apg_withdrawal_get_request_array( 'products', 'sanitize_text_field' );
	$selected_quantities = apg_withdrawal_get_request_array( 'quantities', 'absint' );

	// The pick-an-order dropdown lists orders, with their number, date, total and
	// line items, so it is only ever built for the account WordPress has already
	// authenticated, and from that account's own orders. Resolving it from the
	// e-mail in the request instead would hand the purchase history of any
	// address to whoever types it in the query string.
	$all_orders        = $current_user_id ? apg_withdrawal_get_customer_orders( '', $current_user_id ) : array();
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

	// Same rule for the order the form is currently working on: it is only
	// resolved once the requester has proved they may see it — the logged-in
	// customer it belongs to, or a visitor who supplied the e-mail recorded on
	// it. That is the very check the submission itself has to pass, so nothing
	// legitimate is lost and an order ID on its own stops being enough to read
	// the contents and the total of somebody else's order.
	$order_validation  = $order_id ? apg_withdrawal_validate_order( $order_id, $email, $settings ) : array( 'valid' => false );
	$current_order     = ! empty( $order_validation['valid'] ) ? $order_validation['order'] : false;
	$order_products    = $current_order ? apg_withdrawal_get_order_items_data( $current_order ) : array();
	$form_action       = apg_withdrawal_get_current_form_url();

	apg_withdrawal_enqueue_form_assets();

	$expired_warning = false;

	if ( 'POST' === strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET' ) && isset( $_POST['apg_withdrawal_confirm_inline'] ) ) {
		if ( ! isset( $_POST['apg_withdrawal_confirm_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['apg_withdrawal_confirm_nonce'] ) ), 'apg_withdrawal_confirm_action' ) ) {
			$message = 'nonce';
			$step    = 'request';
		} else {
			$message = apg_withdrawal_process_submission_data( apg_withdrawal_read_confirmation_post() );
			$step    = apg_withdrawal_notice_is_final( $message ) ? 'request' : 'confirm';
		}
	}

	if ( 'confirm' === $step && empty( $acceptance ) ) {
		$step    = 'request';
		$message = 'fields';
	}

	if ( 'confirm' === $step ) {
		if ( ! $current_order ) {
			$step    = 'request';
			$message = 'order';
		} else {
			$expired_warning = ! empty( $order_validation['expired_warning'] );
		}
	}

	?>
	<div class="woocommerce apg-withdrawal-form-wrapper">
		<?php apg_withdrawal_print_notice( $message ); ?>

		<?php if ( 'confirm' === $step ) : ?>
			<?php apg_withdrawal_print_confirmation_form( $name, $email, $order_id, $scope, $details, $selected_products, $current_order, $settings, $form_action, $phone, $expired_warning, apg_withdrawal_sanitize_item_quantities( $current_order, $selected_products, $selected_quantities ), $iban ); ?>
		<?php else : ?>
			<?php
			if ( function_exists( 'wc_print_notice' ) ) {
				wc_print_notice(
					sprintf(
						/* translators: %d number of days of the withdrawal window. */
						__( 'The right of withdrawal may be exercised within %d calendar days from receipt of the order.', 'apg-withdrawal-for-woocommerce' ),
						absint( $settings['withdrawal_days'] )
					),
					'notice'
				);
			}
			?>
			<?php if ( function_exists( 'apg_withdrawal_get_model_form_url' ) ) : ?>
				<p class="apg-withdrawal-model-link">
					<span><?php esc_html_e( 'If you prefer, you can use the official model (Annex I.B).', 'apg-withdrawal-for-woocommerce' ); ?></span>
					<a
						href="<?php echo esc_url( apg_withdrawal_get_model_form_url() ); ?>"
						target="_blank"
						rel="noopener"
						class="button<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>"
					>
						<?php esc_html_e( 'Open the model form', 'apg-withdrawal-for-woocommerce' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'apg-withdrawal-for-woocommerce' ); ?></span>
					</a>
				</p>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( $form_action ); ?>" class="apg-withdrawal-form">
				<input type="hidden" name="apg_withdrawal_step" value="confirm">
				<?php wp_nonce_field( 'apg_withdrawal_preview_action', 'apg_withdrawal_preview_nonce' ); ?>
				<p class="apg-withdrawal-honeypot" aria-hidden="true">
					<label><?php esc_html_e( 'Website (leave empty)', 'apg-withdrawal-for-woocommerce' ); ?>
						<input type="text" name="apg_withdrawal_website" value="" autocomplete="off" tabindex="-1">
					</label>
				</p>
				<?php if ( ! empty( $atts['heading'] ) ) : ?>
					<h2><?php esc_html_e( 'Withdrawal request', 'apg-withdrawal-for-woocommerce' ); ?></h2>
				<?php endif; ?>
				<div class="apg-withdrawal-product-warning" role="status" aria-live="polite" hidden></div>
				<div class="woocommerce-address-fields">
					<div class="woocommerce-address-fields__field-wrapper">
						<?php
						apg_withdrawal_print_field(
							'name',
							array(
								'type'         => 'text',
								'label'        => __( 'Full name', 'apg-withdrawal-for-woocommerce' ),
								'required'     => true,
								'class'        => array( 'form-row-wide' ),
								'input_class'  => array( 'input-text' ),
								'autocomplete' => 'name',
								'priority'     => 10,
							),
							$name
						);

						apg_withdrawal_print_field(
							'email',
							array(
								'type'         => 'email',
								'label'        => __( 'Email', 'apg-withdrawal-for-woocommerce' ),
								'required'     => true,
								'class'        => array( 'form-row-wide' ),
								'input_class'  => array( 'input-text' ),
								'autocomplete' => 'email',
								'priority'     => 20,
							),
							$email
						);

						apg_withdrawal_print_field(
							'phone',
							array(
								'type'         => 'tel',
								'label'        => __( 'Phone', 'apg-withdrawal-for-woocommerce' ),
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
								'' => __( 'Select an order', 'apg-withdrawal-for-woocommerce' ),
							);

							foreach ( $orders as $order ) {
								$label = apg_withdrawal_get_order_option_label( $order );
								if ( isset( $orders_warning[ (string) $order->get_id() ] ) && 'expired' === $orders_warning[ (string) $order->get_id() ] ) {
									$label .= ' *';
								}
								$order_options[ $order->get_id() ] = $label;
							}

							apg_withdrawal_print_field(
								'order_id',
								array(
									'type'        => 'select',
									'label'       => __( 'Order number', 'apg-withdrawal-for-woocommerce' ),
									'required'    => true,
									'class'       => array( 'form-row-wide' ),
									'input_class' => array( 'apg-withdrawal-selectwoo', 'wc-enhanced-select' ),
									'options'     => $order_options,
									'priority'    => 30,
								),
								$order_id
							);
						} else {
							apg_withdrawal_print_field(
								'order_id',
								array(
									'type'              => 'text',
									'label'             => __( 'Order number', 'apg-withdrawal-for-woocommerce' ),
									'required'          => true,
									'class'             => array( 'form-row-wide' ),
									'input_class'       => array( 'input-text' ),
									'priority'          => 30,
									'custom_attributes' => $email ? array() : array( 'disabled' => 'disabled' ),
								),
								$order_id
							);
						}

						apg_withdrawal_print_field(
							'scope',
							array(
								'type'        => 'select',
								'label'       => __( 'Withdrawal scope', 'apg-withdrawal-for-woocommerce' ),
								'class'       => array( 'form-row-wide' ),
								'input_class' => array( 'apg-withdrawal-selectwoo', 'wc-enhanced-select' ),
								'options'     => array(
									'full'    => __( 'Full order', 'apg-withdrawal-for-woocommerce' ),
									'partial' => __( 'Specific products only', 'apg-withdrawal-for-woocommerce' ),
								),
								'priority'    => 40,
							),
							$scope
						);
						?>
							<div class="form-row form-row-wide apg-withdrawal-products-row" id="products_field" data-priority="50" <?php echo 'partial' === $scope ? '' : 'style="display:none;"'; ?>>
							<fieldset class="apg-withdrawal-items-fieldset">
								<legend><?php esc_html_e( 'Products to withdraw', 'apg-withdrawal-for-woocommerce' ); ?></legend>
								<p class="description" id="products_description"><?php esc_html_e( 'Tick the products you are withdrawing from and, if you are keeping some of the units, lower the quantity.', 'apg-withdrawal-for-woocommerce' ); ?></p>
								<ul class="apg-withdrawal-items" aria-describedby="products_description">
									<?php apg_withdrawal_print_item_rows( $order_products, $selected_products, $selected_quantities ); ?>
								</ul>
							</fieldset>
						</div>
						<?php
						if ( 'hidden' !== $iban_mode ) {
							apg_withdrawal_print_field(
								'iban',
								array(
									'type'              => 'text',
									'label'             => __( 'Account for the refund (IBAN)', 'apg-withdrawal-for-woocommerce' ),
									'required'          => 'required' === $iban_mode,
									'class'             => array( 'form-row-wide' ),
									'input_class'       => array( 'input-text' ),
									'description'       => __( 'Only needed when the amount cannot be returned through the original payment method. It is checked against the international IBAN control digits before being accepted.', 'apg-withdrawal-for-woocommerce' ),
									'priority'          => 55,
									'custom_attributes' => array(
										'autocomplete'   => 'off',
										'autocapitalize' => 'characters',
										'spellcheck'     => 'false',
									),
								),
								$iban
							);
						}

						apg_withdrawal_print_field(
							'details',
							array(
								'type'        => 'textarea',
								'label'       => __( 'Additional details', 'apg-withdrawal-for-woocommerce' ),
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
								<?php esc_html_e( 'I understand that the next step will submit my withdrawal request after confirmation.', 'apg-withdrawal-for-woocommerce' ); ?><span class="required" aria-hidden="true">&nbsp;*</span>
							</label>
						</p>
						<p class="description form-row form-row-wide"><?php esc_html_e( 'The next step will show a confirmation screen before the request is submitted.', 'apg-withdrawal-for-woocommerce' ); ?></p>
						<p class="form-row">
							<button type="submit" class="button alt<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>"><?php esc_html_e( 'Open withdrawal confirmation', 'apg-withdrawal-for-woocommerce' ); ?></button>
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
				$apg_notice_placeholder    = '__APG_NOTICE_MESSAGE__';
				$apg_product_warn_template = function_exists( 'wc_print_notice' ) ? wc_print_notice( $apg_notice_placeholder, 'notice', array(), true ) : '';

				wp_localize_script(
					$apg_withdrawal_script_handle,
					'apgWithdrawal',
					array(
						'orderItemsNonce'       => wp_create_nonce( 'apg_withdrawal_order_items' ),
						'productsMap'           => $orders_map,
						'selectedProducts'      => array_values( $selected_products ),
						'selectedQuantities'    => (object) $selected_quantities,
						'ajaxUrl'               => admin_url( 'admin-ajax.php' ),
						'ordersWarning'         => $orders_warning,
						'productWarningTemplate' => $apg_product_warn_template,
						'noticePlaceholder'     => $apg_notice_placeholder,
						'i18n'                  => array(
							'noResults' => __( 'No results found', 'apg-withdrawal-for-woocommerce' ),
							'units'     => __( 'Units', 'apg-withdrawal-for-woocommerce' ),
						),
						'warningMessages'  => array(
							'excluded'     => __( "According to this product's configuration, the right of withdrawal may not apply under the legally provided exceptions. Your request will be reviewed by the store.", 'apg-withdrawal-for-woocommerce' ),
							'digital'      => __( 'This order contains digital content. If execution began with your prior express consent and you acknowledged the loss of the right of withdrawal at purchase, this right may no longer apply.', 'apg-withdrawal-for-woocommerce' ),
							'personalized' => __( 'This order contains personalised products. The right of withdrawal does not apply to goods made to your specifications or clearly personalised.', 'apg-withdrawal-for-woocommerce' ),
							'manual'       => __( 'This request requires manual review. The store will evaluate whether the right of withdrawal applies to this order.', 'apg-withdrawal-for-woocommerce' ),
							'expired'      => __( 'According to the available information, the ordinary withdrawal period for this order may have expired. You may still submit your request and it will be reviewed by the store.', 'apg-withdrawal-for-woocommerce' ),
						),
					)
				);
				wp_enqueue_script( $apg_withdrawal_script_handle );
				?>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Returns the withdrawal form markup as a string, for the shortcode and any
 * other caller that needs a value instead of direct output.
 *
 * @param array $atts Optional shortcode/function attributes ('order_id', 'email').
 * @return string HTML markup for the complete withdrawal form.
 */
function apg_withdrawal_render_form( $atts = array() ) {
	ob_start();
	apg_withdrawal_print_form( $atts );

	return (string) ob_get_clean();
}

/**
 * AJAX handler: returns the line items of a single order, and only to a caller
 * that supplies the e-mail address recorded on it.
 *
 * A guest has no dropdown to pick from — the plugin will not list somebody's
 * orders from an e-mail address alone — so the item rows of a partial
 * withdrawal are fetched here, once the visitor has typed both halves of the
 * pair. That pair is exactly the proof of ownership the submission itself
 * demands, so this endpoint gives away nothing that completing the form would
 * not, and it is throttled so it cannot be worked as a free oracle.
 *
 * @return void
 */
function apg_withdrawal_ajax_get_order_items() {
	if ( ! check_ajax_referer( 'apg_withdrawal_order_items', 'nonce', false ) ) {
		wp_send_json_error( null, 403 );
	}

	$email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$order_id = isset( $_POST['order_id'] ) ? sanitize_text_field( wp_unslash( $_POST['order_id'] ) ) : '';

	if ( ! is_email( $email ) || '' === $order_id ) {
		wp_send_json_error( null, 400 );
	}

	if ( apg_withdrawal_is_rate_limited( $email, 'lookup' ) ) {
		wp_send_json_error( null, 429 );
	}

	$settings   = apg_withdrawal_get_settings();
	$validation = apg_withdrawal_validate_order( $order_id, $email, $settings );

	// A pair that does not match answers exactly like an order with no lines,
	// so the response cannot be used to tell "wrong e-mail" from "no such order".
	if ( empty( $validation['valid'] ) ) {
		wp_send_json_success(
			array(
				'items'   => (object) array(),
				'warning' => '',
			)
		);
	}

	$order       = $validation['order'];
	$order_key   = (string) $order->get_id();
	$warning_map = function_exists( 'apg_withdrawal_get_orders_warning_map' )
		? apg_withdrawal_get_orders_warning_map( array( $order ), $settings )
		: array();

	wp_send_json_success(
		array(
			'items'   => (object) apg_withdrawal_get_order_items_data( $order ),
			'warning' => isset( $warning_map[ $order_key ] ) ? (string) $warning_map[ $order_key ] : '',
		)
	);
}
add_action( 'wp_ajax_apg_withdrawal_get_order_items', 'apg_withdrawal_ajax_get_order_items' );
add_action( 'wp_ajax_nopriv_apg_withdrawal_get_order_items', 'apg_withdrawal_ajax_get_order_items' );

/**
 * Returns the translated notice message string for a given message key.
 *
 * @param string $message Message key (e.g. 'success', 'nonce', 'fields', 'order').
 * @return string Translated notice message.
 */
function apg_withdrawal_get_notice_message( $message ) {
	$messages = array(
		'success' => __( 'Your withdrawal request has been registered.', 'apg-withdrawal-for-woocommerce' ),
		'nonce'   => __( 'Security validation failed. Please try again.', 'apg-withdrawal-for-woocommerce' ),
		'fields'  => __( 'Please complete all required fields before continuing.', 'apg-withdrawal-for-woocommerce' ),
		'email'   => __( 'The email address is not valid.', 'apg-withdrawal-for-woocommerce' ),
		'iban'    => __( 'The account number (IBAN) is not valid. Check that you copied every character.', 'apg-withdrawal-for-woocommerce' ),
		'order'   => __( 'The order could not be matched with the email address provided.', 'apg-withdrawal-for-woocommerce' ),
		'expired' => __( 'The withdrawal period for this order appears to have expired.', 'apg-withdrawal-for-woocommerce' ),
		'throttle' => __( 'Too many attempts. Please wait a few minutes before trying again.', 'apg-withdrawal-for-woocommerce' ),
		'verify_sent'    => __( 'We have sent a confirmation link to the email address on the order. Your request will be registered, with the date and time of this submission, as soon as you open it.', 'apg-withdrawal-for-woocommerce' ),
		'verify_invalid' => __( 'This confirmation link is no longer valid: it may have already been used or it may have expired. Please submit the form again.', 'apg-withdrawal-for-woocommerce' ),
		'duplicate' => __( 'We have already registered this withdrawal request, so we have not registered it again. Check your email for the acknowledgement of the first one.', 'apg-withdrawal-for-woocommerce' ),
		'general' => __( 'An error occurred while processing the withdrawal request.', 'apg-withdrawal-for-woocommerce' ),
	);

	return isset( $messages[ $message ] ) ? $messages[ $message ] : $messages['general'];
}

