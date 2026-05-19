<?php
/**
 * Admin notification email — HTML version.
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

do_action( 'woocommerce_email_header', $email_heading, $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce standard hook

$apg_withdrawal_scope_label = 'partial' === ( $data['scope'] ?? 'full' )
	? __( 'Specific products only', 'apg-withdrawal-for-woocommerce' )
	: __( 'Full order', 'apg-withdrawal-for-woocommerce' );
?>

<p><?php esc_html_e( 'A new withdrawal request has been submitted.', 'apg-withdrawal-for-woocommerce' ); ?></p>

<h2><?php esc_html_e( 'Request details', 'apg-withdrawal-for-woocommerce' ); ?></h2>

<table cellspacing="0" cellpadding="6" style="width:100%;border-collapse:collapse;" border="1">
	<tbody>
		<tr>
			<th scope="row" style="text-align:left;border:1px solid #eee;padding:12px;"><?php esc_html_e( 'Request number', 'apg-withdrawal-for-woocommerce' ); ?></th>
			<td style="text-align:left;border:1px solid #eee;padding:12px;">#<?php echo absint( $data['post_id'] ?? 0 ); ?></td>
		</tr>
		<tr>
			<th scope="row" style="text-align:left;border:1px solid #eee;padding:12px;"><?php esc_html_e( 'Customer', 'apg-withdrawal-for-woocommerce' ); ?></th>
			<td style="text-align:left;border:1px solid #eee;padding:12px;"><?php echo esc_html( $data['name'] ?? '' ); ?></td>
		</tr>
		<tr>
			<th scope="row" style="text-align:left;border:1px solid #eee;padding:12px;"><?php esc_html_e( 'Email', 'apg-withdrawal-for-woocommerce' ); ?></th>
			<td style="text-align:left;border:1px solid #eee;padding:12px;"><a href="mailto:<?php echo esc_attr( $data['customer_email'] ?? '' ); ?>"><?php echo esc_html( $data['customer_email'] ?? '' ); ?></a></td>
		</tr>
		<?php if ( $data['phone'] ?? '' ) : ?>
		<tr>
			<th scope="row" style="text-align:left;border:1px solid #eee;padding:12px;"><?php esc_html_e( 'Phone', 'apg-withdrawal-for-woocommerce' ); ?></th>
			<td style="text-align:left;border:1px solid #eee;padding:12px;"><?php echo esc_html( $data['phone'] ?? '' ); ?></td>
		</tr>
		<?php endif; ?>
		<tr>
			<th scope="row" style="text-align:left;border:1px solid #eee;padding:12px;"><?php esc_html_e( 'Order', 'apg-withdrawal-for-woocommerce' ); ?></th>
			<td style="text-align:left;border:1px solid #eee;padding:12px;"><?php echo esc_html( $data['order_ref'] ?? '' ); ?></td>
		</tr>
		<tr>
			<th scope="row" style="text-align:left;border:1px solid #eee;padding:12px;"><?php esc_html_e( 'Scope', 'apg-withdrawal-for-woocommerce' ); ?></th>
			<td style="text-align:left;border:1px solid #eee;padding:12px;"><?php echo esc_html( $apg_withdrawal_scope_label ); ?></td>
		</tr>
		<?php if ( $data['details'] ?? '' ) : ?>
		<tr>
			<th scope="row" style="text-align:left;border:1px solid #eee;padding:12px;"><?php esc_html_e( 'Details', 'apg-withdrawal-for-woocommerce' ); ?></th>
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
	<a href="<?php echo esc_url( $apg_withdrawal_edit_url ); ?>" style="color:#2271b1;"><?php esc_html_e( 'View withdrawal request in admin', 'apg-withdrawal-for-woocommerce' ); ?></a>
</p>
<?php endif; ?>

<?php if ( $additional_content ) : ?>
<p><?php echo wp_kses_post( nl2br( esc_html( $additional_content ) ) ); ?></p>
<?php endif; ?>

<?php do_action( 'woocommerce_email_footer', $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce standard hook ?>
