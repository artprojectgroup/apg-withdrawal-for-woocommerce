<?php
/**
 * Email de actualización de estado al cliente - texto plano.
 *
 * @package WC_APG_Withdrawal
 * @var string   $email_heading
 * @var string   $additional_content
 * @var array    $data
 * @var bool     $sent_to_admin
 * @var bool     $plain_text
 * @var WC_Email $email
 */

defined( 'ABSPATH' ) || exit;

echo '= ' . esc_html( $email_heading ) . " =\n\n";

$apg_withdrawal_status_messages = array(
	'accepted'  => __( 'We are pleased to inform you that your withdrawal request has been accepted. We will contact you with instructions for returning the product.', 'wc-apg-withdrawal' ),
	'rejected'  => __( 'After reviewing your request, we regret to inform you that it has been rejected due to the legally applicable exceptions for this type of product or service.', 'wc-apg-withdrawal' ),
	'completed' => __( 'We are writing to confirm that the processing of your withdrawal request has been completed and the applicable refund has been arranged.', 'wc-apg-withdrawal' ),
);

$apg_withdrawal_status_message = $apg_withdrawal_status_messages[ $data['status'] ?? '' ] ?? '';

/* translators: %s customer name */
printf( esc_html__( 'Hello %s,', 'wc-apg-withdrawal' ), esc_html( $data['name'] ?? '' ) );
echo "\n\n";

echo esc_html__( 'We are writing to inform you of an update to your withdrawal request.', 'wc-apg-withdrawal' ) . "\n\n";

echo esc_html( str_repeat( '-', 40 ) ) . "\n";
printf( esc_html__( 'Request number', 'wc-apg-withdrawal' ) . ': #%d' . "\n", absint( $data['post_id'] ?? 0 ) );
printf( esc_html__( 'Order', 'wc-apg-withdrawal' ) . ': %s' . "\n", esc_html( $data['order_ref'] ?? '' ) );
printf( esc_html__( 'New status', 'wc-apg-withdrawal' ) . ': %s' . "\n", esc_html( $data['status_label'] ?? '' ) );
echo esc_html( str_repeat( '-', 40 ) ) . "\n\n";

if ( $apg_withdrawal_status_message ) {
	echo esc_html( $apg_withdrawal_status_message ) . "\n\n";
}

if ( $additional_content ) {
	echo esc_html( $additional_content ) . "\n\n";
}

echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce footer text filter
