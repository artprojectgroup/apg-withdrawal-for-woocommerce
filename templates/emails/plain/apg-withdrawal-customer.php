<?php
/**
 * Email de acuse de recibo al cliente - texto plano.
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

// translators: %s customer name.
echo sprintf( esc_html__( 'Hello %s,', 'wc-apg-withdrawal' ), esc_html( $data['name'] ?? '' ) ) . "\n\n";

echo esc_html__( 'We have correctly received your withdrawal request.', 'wc-apg-withdrawal' ) . "\n";
echo esc_html__( 'Your request will be reviewed in accordance with consumer and user protection legislation and the conditions applicable to the contracted order.', 'wc-apg-withdrawal' ) . "\n\n";

echo esc_html__( 'Request details:', 'wc-apg-withdrawal' ) . "\n";
echo esc_html( str_repeat( '-', 40 ) ) . "\n";

if ( $data['post_id'] ?? 0 ) {
	// translators: %d request number.
	printf( esc_html__( 'Request number', 'wc-apg-withdrawal' ) . ': #%d' . "\n", absint( $data['post_id'] ?? 0 ) );
}

if ( $data['name'] ?? '' ) {
	printf( esc_html__( 'Customer', 'wc-apg-withdrawal' ) . ': %s' . "\n", esc_html( $data['name'] ?? '' ) );
}

if ( $data['email'] ?? '' ) {
	printf( esc_html__( 'Email', 'wc-apg-withdrawal' ) . ': %s' . "\n", esc_html( $data['email'] ?? '' ) );
}

if ( $data['phone'] ?? '' ) {
	printf( esc_html__( 'Phone', 'wc-apg-withdrawal' ) . ': %s' . "\n", esc_html( $data['phone'] ?? '' ) );
}

if ( $data['request_date'] ?? '' ) {
	printf( esc_html__( 'Date and time', 'wc-apg-withdrawal' ) . ': %s' . "\n", esc_html( $data['request_date'] ?? '' ) );
}

printf( esc_html__( 'Order', 'wc-apg-withdrawal' ) . ': %s' . "\n", esc_html( $data['order_ref'] ?? '' ) );
printf( esc_html__( 'Scope', 'wc-apg-withdrawal' ) . ': %s' . "\n", esc_html( $apg_withdrawal_scope_label ) );

if ( 'partial' === ( $data['scope'] ?? 'full' ) && ! empty( $data['products'] ) ) {
	printf( esc_html__( 'Products', 'wc-apg-withdrawal' ) . ': %s' . "\n", esc_html( implode( ', ', (array) ( $data['products'] ?? array() ) ) ) );
}

if ( $data['details'] ?? '' ) {
	printf( esc_html__( 'Additional details', 'wc-apg-withdrawal' ) . ': %s' . "\n", esc_html( $data['details'] ?? '' ) );
}

echo "\n";

echo esc_html__( 'Should the right of withdrawal be applicable, it may be necessary to return the product in accordance with the instructions provided by the store.', 'wc-apg-withdrawal' ) . "\n\n";

if ( $additional_content ) {
	echo esc_html( $additional_content ) . "\n\n";
}

echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce footer text filter
