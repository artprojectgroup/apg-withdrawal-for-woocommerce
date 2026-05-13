<?php
/**
 * Email de actualización de estado al cliente.
 *
 * @package WC_APG_Withdrawal
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'APG_Withdrawal_Email_Status', false ) ) :

	class APG_Withdrawal_Email_Status extends WC_Email {

		/** @var array Email template data passed to the HTML and plain-text templates. */
		public $data        = array();

		/** @var string The new withdrawal status slug being communicated. */
		public $new_status  = '';

		/**
		 * Sets up email ID, templates, and placeholders.
		 */
		public function __construct() {
			$this->id             = 'apg_withdrawal_status';
			$this->customer_email = true;
			$this->title          = __( 'Withdrawal request status update (customer)', 'wc-apg-withdrawal' );
			$this->description    = __( 'Sent to the customer when the status of their withdrawal request changes.', 'wc-apg-withdrawal' );
			$this->template_html  = 'emails/apg-withdrawal-status.php';
			$this->template_plain = 'emails/plain/apg-withdrawal-status.php';
			$this->template_base  = apg_withdrawal_PLUGIN_DIR . 'templates/';
			$this->placeholders   = array(
				'{site_title}'    => $this->get_blogname(),
				'{order_number}'  => '',
				'{status_label}'  => '',
			);

			parent::__construct();
		}

		/**
		 * Returns the default email subject line.
		 *
		 * @return string Default subject string with site-title and order-number placeholders.
		 */
		public function get_default_subject() {
			return sprintf(
				/* translators: %s email heading. */
				'[{site_title}] %s',
				__( 'Update on your withdrawal request #{order_number}', 'wc-apg-withdrawal' )
			);
		}

		/**
		 * Returns the default email heading text.
		 *
		 * @return string Default heading string.
		 */
		public function get_default_heading() {
			return __( 'Withdrawal request update', 'wc-apg-withdrawal' );
		}

		/**
		 * Returns the translated label for a withdrawal status slug.
		 *
		 * @param string $status Status slug ('pending', 'accepted', 'rejected' or 'completed').
		 * @return string Translated status label, or the raw slug if not found.
		 */
		public function get_status_label( $status ) {
			$labels = array(
				'pending'   => __( 'Pending', 'wc-apg-withdrawal' ),
				'accepted'  => __( 'Accepted', 'wc-apg-withdrawal' ),
				'rejected'  => __( 'Rejected', 'wc-apg-withdrawal' ),
				'completed' => __( 'Completed', 'wc-apg-withdrawal' ),
			);
			return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
		}

		/**
		 * Sets up email data and sends the status update email to the customer.
		 *
		 * @param int    $post_id       Withdrawal request post ID.
		 * @param string $email_address Customer email address.
		 * @param string $name          Customer full name.
		 * @param string $order_ref     Order reference number.
		 * @param string $status        New withdrawal status slug.
		 * @return void
		 */
		public function trigger( $post_id, $email_address, $name, $order_ref, $status ) {
			$this->setup_locale();

			$this->new_status = sanitize_key( $status );
			$status_label     = $this->get_status_label( $this->new_status );

			$this->data = array(
				'post_id'      => absint( $post_id ),
				'name'         => sanitize_text_field( $name ),
				'order_ref'    => sanitize_text_field( $order_ref ),
				'status'       => $this->new_status,
				'status_label' => $status_label,
			);

			$this->recipient                       = sanitize_email( $email_address );
			$this->placeholders['{order_number}']  = $order_ref;
			$this->placeholders['{status_label}']  = $status_label;

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
					'sent_to_admin'      => false,
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
					'sent_to_admin'      => false,
					'plain_text'         => true,
					'email'              => $this,
				),
				'',
				$this->template_base
			);
		}
	}

endif;
