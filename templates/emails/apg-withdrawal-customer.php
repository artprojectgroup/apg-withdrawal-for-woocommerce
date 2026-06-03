<?php
/**
 * Email de acuse de recibo al cliente - HTML.
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

<p><?php
/* translators: %s customer name */
printf( esc_html__( 'Hello %s,', 'apg-withdrawal-for-woocommerce' ), esc_html( $data['name'] ?? '' ) ); ?></p>
<p><?php esc_html_e( 'We have correctly received your withdrawal request.', 'apg-withdrawal-for-woocommerce' ); ?></p>
<p><?php esc_html_e( 'Your request will be reviewed in accordance with consumer and user protection legislation and the conditions applicable to the contracted order.', 'apg-withdrawal-for-woocommerce' ); ?></p>

<h2><?php esc_html_e( 'Request details', 'apg-withdrawal-for-woocommerce' ); ?></h2>

<table cellspacing="0" cellpadding="6" style="width:100%;border-collapse:collapse;" border="1">
	<tbody>
		<?php if ( $data['post_id'] ?? 0 ) : ?>
		<tr>
			<th scope="row" style="text-align:left;border:1px solid #eee;padding:12px;"><?php esc_html_e( 'Request number', 'apg-withdrawal-for-woocommerce' ); ?></th>
			<td style="text-align:left;border:1px solid #eee;padding:12px;">#<?php echo absint( $data['post_id'] ?? 0 ); ?></td>
		</tr>
		<?php endif; ?>
		<?php if ( $data['name'] ?? '' ) : ?>
		<tr>
			<th scope="row" style="text-align:left;border:1px solid #eee;padding:12px;"><?php esc_html_e( 'Customer', 'apg-withdrawal-for-woocommerce' ); ?></th>
			<td style="text-align:left;border:1px solid #eee;padding:12px;"><?php echo esc_html( $data['name'] ?? '' ); ?></td>
		</tr>
		<?php endif; ?>
		<?php if ( $data['email'] ?? '' ) : ?>
		<tr>
			<th scope="row" style="text-align:left;border:1px solid #eee;padding:12px;"><?php esc_html_e( 'Email', 'apg-withdrawal-for-woocommerce' ); ?></th>
			<td style="text-align:left;border:1px solid #eee;padding:12px;"><?php echo esc_html( $data['email'] ?? '' ); ?></td>
		</tr>
		<?php endif; ?>
		<?php if ( $data['phone'] ?? '' ) : ?>
		<tr>
			<th scope="row" style="text-align:left;border:1px solid #eee;padding:12px;"><?php esc_html_e( 'Phone', 'apg-withdrawal-for-woocommerce' ); ?></th>
			<td style="text-align:left;border:1px solid #eee;padding:12px;"><?php echo esc_html( $data['phone'] ?? '' ); ?></td>
		</tr>
		<?php endif; ?>
		<?php if ( $data['request_date'] ?? '' ) : ?>
		<tr>
			<th scope="row" style="text-align:left;border:1px solid #eee;padding:12px;"><?php esc_html_e( 'Date and time', 'apg-withdrawal-for-woocommerce' ); ?></th>
			<td style="text-align:left;border:1px solid #eee;padding:12px;"><?php echo esc_html( $data['request_date'] ?? '' ); ?></td>
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
		<?php if ( 'partial' === ( $data['scope'] ?? 'full' ) && ! empty( $data['products'] ) ) : ?>
		<tr>
			<th scope="row" style="text-align:left;border:1px solid #eee;padding:12px;"><?php esc_html_e( 'Products', 'apg-withdrawal-for-woocommerce' ); ?></th>
			<td style="text-align:left;border:1px solid #eee;padding:12px;"><?php echo esc_html( implode( ', ', (array) ( $data['products'] ?? array() ) ) ); ?></td>
		</tr>
		<?php endif; ?>
		<?php if ( $data['details'] ?? '' ) : ?>
		<tr>
			<th scope="row" style="text-align:left;border:1px solid #eee;padding:12px;"><?php esc_html_e( 'Additional details', 'apg-withdrawal-for-woocommerce' ); ?></th>
			<td style="text-align:left;border:1px solid #eee;padding:12px;"><?php echo wp_kses_post( nl2br( esc_html( $data['details'] ?? '' ) ) ); ?></td>
		</tr>
		<?php endif; ?>
	</tbody>
</table>

<?php if ( ! empty( $data['receipt_hash'] ) ) : ?>
<p style="font-size:13px;color:#555;border-top:1px solid #eee;padding-top:12px;">
	<?php
	printf(
		/* translators: 1: SHA-256 receipt hash, 2: timestamp in UTC. */
		esc_html__( 'Verification code for this acknowledgement (SHA-256): %1$s — computed at %2$s UTC.', 'apg-withdrawal-for-woocommerce' ),
		'<code>' . esc_html( $data['receipt_hash'] ) . '</code>',
		'<code>' . esc_html( $data['receipt_hash_timestamp'] ) . '</code>'
	);
	?>
</p>
<?php endif; ?>

<p><?php esc_html_e( 'Should the right of withdrawal be applicable, it may be necessary to return the product in accordance with the instructions provided by the store.', 'apg-withdrawal-for-woocommerce' ); ?></p>

<?php if ( $additional_content ) : ?>
<p><?php echo wp_kses_post( nl2br( esc_html( $additional_content ) ) ); ?></p>
<?php endif; ?>

<?php do_action( 'woocommerce_email_footer', $email ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce standard hook ?>
