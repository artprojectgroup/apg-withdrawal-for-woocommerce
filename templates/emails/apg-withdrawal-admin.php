<?php
/**
 * Email de notificación al administrador - HTML.
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

<p><?php esc_html_e( 'A new withdrawal request has been submitted.', 'wc-apg-withdrawal' ); ?></p>

<h2><?php esc_html_e( 'Request details', 'wc-apg-withdrawal' ); ?></h2>

<table cellspacing="0" cellpadding="6" style="width:100%;border-collapse:collapse;" border="1">
	<tbody>
		<tr>
			<th scope="row" style="text-align:left;border:1px solid #eee;padding:12px;"><?php esc_html_e( 'Request number', 'wc-apg-withdrawal' ); ?></th>
			<td style="text-align:left;border:1px solid #eee;padding:12px;">#<?php echo absint( $data['post_id'] ?? 0 ); ?></td>
		</tr>
		<tr>
			<th scope="row" style="text-align:left;border:1px solid #eee;padding:12px;"><?php esc_html_e( 'Customer', 'wc-apg-withdrawal' ); ?></th>
			<td style="text-align:left;border:1px solid #eee;padding:12px;"><?php echo esc_html( $data['name'] ?? '' ); ?></td>
		</tr>
		<tr>
			<th scope="row" style="text-align:left;border:1px solid #eee;padding:12px;"><?php esc_html_e( 'Email', 'wc-apg-withdrawal' ); ?></th>
			<td style="text-align:left;border:1px solid #eee;padding:12px;"><a href="mailto:<?php echo esc_attr( $data['customer_email'] ?? '' ); ?>"><?php echo esc_html( $data['customer_email'] ?? '' ); ?></a></td>
		</tr>
		<?php if ( $data['phone'] ?? '' ) : ?>
		<tr>
			<th scope="row" style="text-align:left;border:1px solid #eee;padding:12px;"><?php esc_html_e( 'Phone', 'wc-apg-withdrawal' ); ?></th>
			<td style="text-align:left;border:1px solid #eee;padding:12px;"><?php echo esc_html( $data['phone'] ?? '' ); ?></td>
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
		<?php if ( $data['details'] ?? '' ) : ?>
		<tr>
			<th scope="row" style="text-align:left;border:1px solid #eee;padding:12px;"><?php esc_html_e( 'Details', 'wc-apg-withdrawal' ); ?></th>
			<td style="text-align:left;border:1px solid #eee;padding:12px;"><?php echo nl2br( esc_html( $data['details'] ?? '' ) ); ?></td>
		</tr>
		<?php endif; ?>
	</tbody>
</table>

<?php
$apg_withdrawal_edit_url = get_edit_post_link( absint( $data['post_id'] ?? 0 ), '' );

if ( $apg_withdrawal_edit_url ) :
?>
<p style="margin-top:16px;">
	<a href="<?php echo esc_url( $apg_withdrawal_edit_url ); ?>" style="color:#2271b1;"><?php esc_html_e( 'View withdrawal request in admin', 'wc-apg-withdrawal' ); ?></a>
</p>
<?php endif; ?>

<?php if ( $additional_content ) : ?>
<p><?php echo wp_kses_post( nl2br( esc_html( $additional_content ) ) ); ?></p>
<?php endif; ?>

<?php do_action( 'woocommerce_email_footer', $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce standard hook ?>
