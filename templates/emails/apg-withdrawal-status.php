<?php
/**
 * Email de actualización de estado al cliente - HTML.
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

do_action( 'woocommerce_email_header', $email_heading, $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce standard hook

$apg_withdrawal_status_messages = array(
	'accepted'  => __( 'We are pleased to inform you that your withdrawal request has been accepted. We will contact you with instructions for returning the product.', 'wc-apg-withdrawal' ),
	'rejected'  => __( 'After reviewing your request, we regret to inform you that it has been rejected due to the legally applicable exceptions for this type of product or service.', 'wc-apg-withdrawal' ),
	'completed' => __( 'We are writing to confirm that the processing of your withdrawal request has been completed and the applicable refund has been arranged.', 'wc-apg-withdrawal' ),
);

$apg_withdrawal_status_message = $apg_withdrawal_status_messages[ $data['status'] ?? '' ] ?? '';
?>

<p><?php
/* translators: %s customer name */
printf( esc_html__( 'Hello %s,', 'wc-apg-withdrawal' ), esc_html( $data['name'] ?? '' ) ); ?></p>
<p><?php esc_html_e( 'We are writing to inform you of an update to your withdrawal request.', 'wc-apg-withdrawal' ); ?></p>

<table cellspacing="0" cellpadding="6" style="width:100%;border-collapse:collapse;" border="1">
	<tbody>
		<tr>
			<th scope="row" style="text-align:left;border:1px solid #eee;padding:12px;"><?php esc_html_e( 'Request number', 'wc-apg-withdrawal' ); ?></th>
			<td style="text-align:left;border:1px solid #eee;padding:12px;">#<?php echo absint( $data['post_id'] ?? 0 ); ?></td>
		</tr>
		<tr>
			<th scope="row" style="text-align:left;border:1px solid #eee;padding:12px;"><?php esc_html_e( 'Order', 'wc-apg-withdrawal' ); ?></th>
			<td style="text-align:left;border:1px solid #eee;padding:12px;"><?php echo esc_html( $data['order_ref'] ?? '' ); ?></td>
		</tr>
		<tr>
			<th scope="row" style="text-align:left;border:1px solid #eee;padding:12px;"><?php esc_html_e( 'New status', 'wc-apg-withdrawal' ); ?></th>
			<td style="text-align:left;border:1px solid #eee;padding:12px;"><strong><?php echo esc_html( $data['status_label'] ?? '' ); ?></strong></td>
		</tr>
	</tbody>
</table>

<?php if ( $apg_withdrawal_status_message ) : ?>
<p style="margin-top:16px;"><?php echo esc_html( $apg_withdrawal_status_message ); ?></p>
<?php endif; ?>

<?php if ( $additional_content ) : ?>
<p><?php echo wp_kses_post( nl2br( esc_html( $additional_content ) ) ); ?></p>
<?php endif; ?>

<?php do_action( 'woocommerce_email_footer', $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce standard hook ?>
