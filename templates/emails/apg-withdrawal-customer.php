<?php
/**
 * Email de acuse de recibo al cliente - HTML.
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

do_action( 'woocommerce_email_header', $email_heading, $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce standard hook

$apg_withdrawal_scope_label = 'partial' === ( $data['scope'] ?? 'full' )
	? __( 'Specific products only', 'wc-apg-withdrawal' )
	: __( 'Full order', 'wc-apg-withdrawal' );
?>

<p><?php
/* translators: %s customer name */
printf( esc_html__( 'Hello %s,', 'wc-apg-withdrawal' ), esc_html( $data['name'] ?? '' ) ); ?></p>
<p><?php esc_html_e( 'We have correctly received your withdrawal request.', 'wc-apg-withdrawal' ); ?></p>
<p><?php esc_html_e( 'Your request will be reviewed in accordance with consumer and user protection legislation and the conditions applicable to the contracted order.', 'wc-apg-withdrawal' ); ?></p>

<h2><?php esc_html_e( 'Request details', 'wc-apg-withdrawal' ); ?></h2>

<table cellspacing="0" cellpadding="6" style="width:100%;border-collapse:collapse;" border="1">
	<tbody>
		<?php if ( $data['post_id'] ?? 0 ) : ?>
		<tr>
			<th scope="row" style="text-align:left;border:1px solid #eee;padding:12px;"><?php esc_html_e( 'Request number', 'wc-apg-withdrawal' ); ?></th>
			<td style="text-align:left;border:1px solid #eee;padding:12px;">#<?php echo absint( $data['post_id'] ?? 0 ); ?></td>
		</tr>
		<?php endif; ?>
		<?php if ( $data['name'] ?? '' ) : ?>
		<tr>
			<th scope="row" style="text-align:left;border:1px solid #eee;padding:12px;"><?php esc_html_e( 'Customer', 'wc-apg-withdrawal' ); ?></th>
			<td style="text-align:left;border:1px solid #eee;padding:12px;"><?php echo esc_html( $data['name'] ?? '' ); ?></td>
		</tr>
		<?php endif; ?>
		<?php if ( $data['email'] ?? '' ) : ?>
		<tr>
			<th scope="row" style="text-align:left;border:1px solid #eee;padding:12px;"><?php esc_html_e( 'Email', 'wc-apg-withdrawal' ); ?></th>
			<td style="text-align:left;border:1px solid #eee;padding:12px;"><?php echo esc_html( $data['email'] ?? '' ); ?></td>
		</tr>
		<?php endif; ?>
		<?php if ( $data['phone'] ?? '' ) : ?>
		<tr>
			<th scope="row" style="text-align:left;border:1px solid #eee;padding:12px;"><?php esc_html_e( 'Phone', 'wc-apg-withdrawal' ); ?></th>
			<td style="text-align:left;border:1px solid #eee;padding:12px;"><?php echo esc_html( $data['phone'] ?? '' ); ?></td>
		</tr>
		<?php endif; ?>
		<?php if ( $data['request_date'] ?? '' ) : ?>
		<tr>
			<th scope="row" style="text-align:left;border:1px solid #eee;padding:12px;"><?php esc_html_e( 'Date and time', 'wc-apg-withdrawal' ); ?></th>
			<td style="text-align:left;border:1px solid #eee;padding:12px;"><?php echo esc_html( $data['request_date'] ?? '' ); ?></td>
		</tr>
		<?php endif; ?>
		<tr>
			<th scope="row" style="text-align:left;border:1px solid #eee;padding:12px;"><?php esc_html_e( 'Order', 'wc-apg-withdrawal' ); ?></th>
			<td style="text-align:left;border:1px solid #eee;padding:12px;"><?php echo esc_html( $data['order_ref'] ?? '' ); ?></td>
		</tr>
		<tr>
			<th scope="row" style="text-align:left;border:1px solid #eee;padding:12px;"><?php esc_html_e( 'Scope', 'wc-apg-withdrawal' ); ?></th>
			<td style="text-align:left;border:1px solid #eee;padding:12px;"><?php echo esc_html( $apg_withdrawal_scope_label ); ?></td>
		</tr>
		<?php if ( 'partial' === ( $data['scope'] ?? 'full' ) && ! empty( $data['products'] ) ) : ?>
		<tr>
			<th scope="row" style="text-align:left;border:1px solid #eee;padding:12px;"><?php esc_html_e( 'Products', 'wc-apg-withdrawal' ); ?></th>
			<td style="text-align:left;border:1px solid #eee;padding:12px;"><?php echo esc_html( implode( ', ', (array) ( $data['products'] ?? array() ) ) ); ?></td>
		</tr>
		<?php endif; ?>
		<?php if ( $data['details'] ?? '' ) : ?>
		<tr>
			<th scope="row" style="text-align:left;border:1px solid #eee;padding:12px;"><?php esc_html_e( 'Additional details', 'wc-apg-withdrawal' ); ?></th>
			<td style="text-align:left;border:1px solid #eee;padding:12px;"><?php echo wp_kses_post( nl2br( esc_html( $data['details'] ?? '' ) ) ); ?></td>
		</tr>
		<?php endif; ?>
	</tbody>
</table>

<p><?php esc_html_e( 'Should the right of withdrawal be applicable, it may be necessary to return the product in accordance with the instructions provided by the store.', 'wc-apg-withdrawal' ); ?></p>

<?php if ( $additional_content ) : ?>
<p><?php echo wp_kses_post( nl2br( esc_html( $additional_content ) ) ); ?></p>
<?php endif; ?>

<?php do_action( 'woocommerce_email_footer', $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce standard hook ?>
