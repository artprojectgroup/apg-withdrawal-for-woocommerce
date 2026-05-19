<?php
/**
 * Admin notification email — plain text version.
 *
 * @package APG_Withdrawal_For_WooCommerce
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
	? __( 'Specific products only', 'apg-withdrawal-for-woocommerce' )
	: __( 'Full order', 'apg-withdrawal-for-woocommerce' );

echo esc_html__( 'A new withdrawal request has been submitted.', 'apg-withdrawal-for-woocommerce' ) . "\n\n";

echo esc_html__( 'Request details:', 'apg-withdrawal-for-woocommerce' ) . "\n";
echo esc_html( str_repeat( '-', 40 ) ) . "\n";

// translators: %d request number.
printf( esc_html__( 'Request number', 'apg-withdrawal-for-woocommerce' ) . ': #%d' . "\n", absint( $data['post_id'] ?? 0 ) );
printf( esc_html__( 'Customer', 'apg-withdrawal-for-woocommerce' ) . ': %s' . "\n", esc_html( $data['name'] ?? '' ) );
printf( esc_html__( 'Email', 'apg-withdrawal-for-woocommerce' ) . ': %s' . "\n", esc_html( $data['customer_email'] ?? '' ) );

if ( $data['phone'] ?? '' ) {
	printf( esc_html__( 'Phone', 'apg-withdrawal-for-woocommerce' ) . ': %s' . "\n", esc_html( $data['phone'] ?? '' ) );
}

printf( esc_html__( 'Order', 'apg-withdrawal-for-woocommerce' ) . ': %s' . "\n", esc_html( $data['order_ref'] ?? '' ) );
printf( esc_html__( 'Scope', 'apg-withdrawal-for-woocommerce' ) . ': %s' . "\n", esc_html( $apg_withdrawal_scope_label ) );

if ( $data['details'] ?? '' ) {
	printf( esc_html__( 'Details', 'apg-withdrawal-for-woocommerce' ) . ': %s' . "\n", esc_html( $data['details'] ?? '' ) );
}

echo "\n";

$apg_withdrawal_edit_url = get_edit_post_link( absint( $data['post_id'] ?? 0 ), '' );

if ( $apg_withdrawal_edit_url ) {
	printf( esc_html__( 'View withdrawal request', 'apg-withdrawal-for-woocommerce' ) . ': %s' . "\n", esc_url( $apg_withdrawal_edit_url ) );
}

if ( $additional_content ) {
	echo "\n" . esc_html( $additional_content ) . "\n\n";
}

echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce footer text filter
