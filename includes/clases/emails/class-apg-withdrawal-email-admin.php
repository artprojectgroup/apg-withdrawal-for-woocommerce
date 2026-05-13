<?php
/**
 * Email de notificación al administrador.
 *
 * @package WC_APG_Withdrawal
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'APG_Withdrawal_Email_Admin', false ) ) :

	class APG_Withdrawal_Email_Admin extends WC_Email {

		/** @var array Email template data passed to the HTML and plain-text templates. */
		public $data = array();

		/**
		 * Sets up email ID, templates, and placeholders and resolves the recipient address.
		 */
		public function __construct() {
			$this->id             = 'apg_withdrawal_admin';
			$this->customer_email = false;
			$this->title          = __( 'New withdrawal request (admin)', 'wc-apg-withdrawal' );
			$this->description    = __( 'Sent to the store administrator when a new withdrawal request is submitted.', 'wc-apg-withdrawal' );
			$this->template_html  = 'emails/apg-withdrawal-admin.php';
			$this->template_plain = 'emails/plain/apg-withdrawal-admin.php';
			$this->template_base  = apg_withdrawal_PLUGIN_DIR . 'templates/';
			$this->placeholders   = array(
				'{site_title}'   => $this->get_blogname(),
				'{order_number}' => '',
			);

			parent::__construct();

			$settings       = function_exists( 'apg_withdrawal_get_settings' ) ? apg_withdrawal_get_settings() : array();
			$plugin_email   = isset( $settings['notification_email'] ) && is_email( $settings['notification_email'] ) ? $settings['notification_email'] : get_option( 'admin_email' );
			$this->recipient = $this->get_option( 'recipient', $plugin_email );
		}

		/**
		 * Returns the default email subject line.
		 *
		 * @return string Default subject string with site-title and order-number placeholders.
		 */
		public function get_default_subject() {
			return sprintf(
				/* translators: %s email heading. */
				'[{site_title}] %s #{order_number}',
				__( 'New withdrawal request for order', 'wc-apg-withdrawal' )
			);
		}

		/**
		 * Returns the default email heading text.
		 *
		 * @return string Default heading string.
		 */
		public function get_default_heading() {
			return __( 'New withdrawal request', 'wc-apg-withdrawal' );
		}

		/**
		 * Sets up email data and sends the admin notification email.
		 *
		 * @param int    $post_id            Withdrawal request post ID.
		 * @param string $name               Customer full name.
		 * @param string $customer_email     Customer email address.
		 * @param string $order_ref          Order reference number.
		 * @param string $scope              Withdrawal scope ('full' or 'partial').
		 * @param string $details            Additional details provided by the customer.
		 * @param string $notification_email Override recipient email address.
		 * @param string $phone              Optional customer phone number.
		 * @return void
		 */
		public function trigger( $post_id, $name, $customer_email, $order_ref, $scope, $details, $notification_email = '', $phone = '' ) {
			$this->setup_locale();

			$this->data = array(
				'post_id'        => absint( $post_id ),
				'name'           => sanitize_text_field( $name ),
				'customer_email' => sanitize_email( $customer_email ),
				'phone'          => sanitize_text_field( $phone ),
				'order_ref'      => sanitize_text_field( $order_ref ),
				'scope'          => sanitize_key( $scope ),
				'details'        => sanitize_textarea_field( $details ),
			);

			if ( $notification_email && is_email( $notification_email ) ) {
				$this->recipient = $notification_email;
			}

			$this->placeholders['{order_number}'] = $order_ref;

			if ( $this->is_enabled() && $this->get_recipient() ) {
				$this->send(
					$this->get_recipient(),
					$this->get_subject(),
					$this->get_content(),
					$this->get_headers(),
					$this->get_attachments()
				);
			}

			$this->restore_locale();
		}

		/**
		 * Returns the email headers, adding a Reply-To header for the customer.
		 *
		 * @return string Email headers string.
		 */
		public function get_headers() {
			$headers = parent::get_headers();

			if ( ! empty( $this->data['customer_email'] ) ) {
				$headers .= 'Reply-To: ' . sanitize_text_field( $this->data['name'] ) . ' <' . sanitize_email( $this->data['customer_email'] ) . ">\r\n";
			}

			return $headers;
		}

		/**
		 * Returns the HTML version of the email content.
		 *
		 * @return string Rendered HTML email content.
		 */
		public function get_content_html() {
			return wc_get_template_html(
				$this->template_html,
				array(
					'email_heading'      => $this->get_heading(),
					'additional_content' => $this->get_additional_content(),
					'data'               => $this->data,
					'sent_to_admin'      => true,
					'plain_text'         => false,
					'email'              => $this,
				),
				'',
				$this->template_base
			);
		}

		/**
		 * Returns the plain-text version of the email content.
		 *
		 * @return string Rendered plain-text email content.
		 */
		public function get_content_plain() {
			return wc_get_template_html(
				$this->template_plain,
				array(
					'email_heading'      => $this->get_heading(),
					'additional_content' => $this->get_additional_content(),
					'data'               => $this->data,
					'sent_to_admin'      => true,
					'plain_text'         => true,
					'email'              => $this,
				),
				'',
				$this->template_base
			);
		}
	}

endif;
