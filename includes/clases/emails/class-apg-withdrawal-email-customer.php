<?php
/**
 * Email de acuse de recibo al cliente.
 *
 * @package WC_APG_Withdrawal
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'APG_Withdrawal_Email_Customer', false ) ) :

	class APG_Withdrawal_Email_Customer extends WC_Email {

		/** @var array Email template data passed to the HTML and plain-text templates. */
		public $data = array();

		/**
		 * Sets up email ID, templates, and placeholders.
		 */
		public function __construct() {
			$this->id             = 'apg_withdrawal_customer';
			$this->customer_email = true;
			$this->title          = __( 'Withdrawal request received (customer)', 'wc-apg-withdrawal' );
			$this->description    = __( 'Sent to the customer when a withdrawal request is submitted.', 'wc-apg-withdrawal' );
			$this->template_html  = 'emails/apg-withdrawal-customer.php';
			$this->template_plain = 'emails/plain/apg-withdrawal-customer.php';
			$this->template_base  = apg_withdrawal_PLUGIN_DIR . 'templates/';
			$this->placeholders   = array(
				'{site_title}'   => $this->get_blogname(),
				'{order_number}' => '',
			);

			parent::__construct();
		}

		/**
		 * Returns the default email subject line.
		 *
		 * @return string Default subject string with site-title placeholder.
		 */
		public function get_default_subject() {
			return sprintf(
				/* translators: %s email heading. */
				'[{site_title}] %s',
				__( 'Withdrawal request received', 'wc-apg-withdrawal' )
			);
		}

		/**
		 * Returns the default email heading text.
		 *
		 * @return string Default heading string.
		 */
		public function get_default_heading() {
			return __( 'Withdrawal request received', 'wc-apg-withdrawal' );
		}

		/**
		 * Sets up email data and sends the customer acknowledgement email.
		 *
		 * @param int    $post_id       Withdrawal request post ID.
		 * @param string $email_address Customer email address.
		 * @param string $name          Customer full name.
		 * @param string $order_ref     Order reference number.
		 * @param string $scope         Withdrawal scope ('full' or 'partial').
		 * @param string $request_date  Date and time the request was submitted.
		 * @param string $phone         Optional customer phone number.
		 * @return void
		 */
		public function trigger( $post_id, $email_address, $name, $order_ref, $scope, $request_date = '', $phone = '' ) {
			$this->setup_locale();

			$post_obj = $post_id ? get_post( absint( $post_id ) ) : null;

			$products_list = array();
			if ( $post_id && 'partial' === $scope ) {
				$stored_items = get_post_meta( absint( $post_id ), '_apg_withdrawal_products', true );
				$wc_order_id  = absint( get_post_meta( absint( $post_id ), '_apg_withdrawal_wc_order_id', true ) );
				if ( $wc_order_id && is_array( $stored_items ) && function_exists( 'wc_get_order' ) ) {
					$wc_order = wc_get_order( $wc_order_id );
					if ( $wc_order ) {
						foreach ( $wc_order->get_items() as $item_id => $item ) {
							if ( in_array( (string) $item_id, array_map( 'strval', $stored_items ), true ) ) {
								$products_list[] = sprintf(
									/* translators: 1: product name, 2: quantity. */
									__( '%1$s x %2$d', 'wc-apg-withdrawal' ),
									$item->get_name(),
									$item->get_quantity()
								);
							}
						}
					}
				}
			}

			$this->data = array(
				'post_id'      => absint( $post_id ),
				'email'        => sanitize_email( $email_address ),
				'name'         => sanitize_text_field( $name ),
				'phone'        => sanitize_text_field( $phone ),
				'order_ref'    => sanitize_text_field( $order_ref ),
				'scope'        => sanitize_key( $scope ),
				'request_date' => $request_date,
				'details'      => $post_obj ? $post_obj->post_content : '',
				'products'     => $products_list,
			);

			$this->recipient                      = sanitize_email( $email_address );
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
