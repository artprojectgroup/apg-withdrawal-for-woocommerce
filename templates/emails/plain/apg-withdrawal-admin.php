<?php
/**
 * Email de notificación al administrador - texto plano.
 *
 * @package WC_APG_Withdrawal
 * @var string $email_heading
 * @var string $additional_content
 * @var array  $data
 * @var bool   $sent_to_admin
 * @var bool   $plain_text
 * @var WC_Email $email
 */

defined( 'ABSPATH' ) || exit;

echo '= ' . esc_html( $email_heading ) . " =\n\n";

$apg_withdrawal_scope_label = 'partial' === ( $data['scope'] ?? 'full' )
	? __( 'Specific products only', 'wc-apg-withdrawal' )
	: __( 'Full order', 'wc-apg-withdrawal' );

echo esc_html__( 'A new withdrawal request has been submitted.', 'wc-apg-withdrawal' ) . "\n\n";

echo esc_html__( 'Request details:', 'wc-apg-withdrawal' ) . "\n";
echo esc_html( str_repeat( '-', 40 ) ) . "\n";

// translators: %d request number.
printf( esc_html__( 'Request number', 'wc-apg-withdrawal' ) . ': #%d' . "\n", absint( $data['post_id'] ?? 0 ) );
printf( esc_html__( 'Customer', 'wc-apg-withdrawal' ) . ': %s' . "\n", esc_html( $data['name'] ?? '' ) );
printf( esc_html__( 'Email', 'wc-apg-withdrawal' ) . ': %s' . "\n", esc_html( $data['customer_email'] ?? '' ) );

if ( $data['phone'] ?? '' ) {
	printf( esc_html__( 'Phone', 'wc-apg-withdrawal' ) . ': %s' . "\n", esc_html( $data['phone'] ?? '' ) );
}

printf( esc_html__( 'Order', 'wc-apg-withdrawal' ) . ': %s' . "\n", esc_html( $data['order_ref'] ?? '' ) );
printf( esc_html__( 'Scope', 'wc-apg-withdrawal' ) . ': %s' . "\n", esc_html( $apg_withdrawal_scope_label ) );

if ( $data['details'] ?? '' ) {
	printf( esc_html__( 'Details', 'wc-apg-withdrawal' ) . ': %s' . "\n", esc_html( $data['details'] ?? '' ) );
}

echo "\n";

$apg_withdrawal_edit_url = get_edit_post_link( absint( $data['post_id'] ?? 0 ), '' );

if ( $apg_withdrawal_edit_url ) {
	printf( esc_html__( 'View withdrawal request', 'wc-apg-withdrawal' ) . ': %s' . "\n", esc_url( $apg_withdrawal_edit_url ) );
}

if ( $additional_content ) {
	echo "\n" . esc_html( $additional_content ) . "\n\n";
}

echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce footer text filter
