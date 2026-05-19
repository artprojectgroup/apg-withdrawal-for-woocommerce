<?php
/**
 * Side information box for the plugin's settings page.
 *
 * @package APG_Withdrawal_For_WooCommerce
 * @global array<string,string> $apg_withdrawal
 */

defined( 'ABSPATH' ) || exit;

global $apg_withdrawal;
?>
<div class="informacion">
	<!-- Row: Donation and author -->
	<div class="fila">
		<div class="columna">
			<p><?php esc_html_e( 'If you enjoyed and find helpful this plugin, please make a donation:', 'apg-withdrawal-for-woocommerce' ); ?></p>
			<p><a href="<?php echo esc_url( $apg_withdrawal['donacion'] ); ?>" target="_blank" title="<?php esc_attr_e( 'Make a donation by ', 'apg-withdrawal-for-woocommerce' ); ?>APG"><span class="genericon genericon-cart"></span></a> </p>
		</div>
		<div class="columna">
			<p>Art Project Group:</p>
			<p><a href="https://www.artprojectgroup.es" title="Art Project Group" target="_blank"><strong class="artprojectgroup">APG</strong></a> </p>
		</div>
	</div>

	<!-- Row: Social networks and more plugins -->
	<div class="fila">
		<div class="columna">
			<p><?php esc_html_e( 'Follow us:', 'apg-withdrawal-for-woocommerce' ); ?></p>
			<p><a href="https://www.facebook.com/artprojectgroup" title="<?php esc_attr_e( 'Follow us on ', 'apg-withdrawal-for-woocommerce' ); ?>Facebook" target="_blank"><span class="genericon genericon-facebook-alt"></span></a> <a href="https://x.com/artprojectgroup" title="<?php esc_attr_e( 'Follow us on ', 'apg-withdrawal-for-woocommerce' ); ?>X" target="_blank"><span class="genericon genericon-x-alt"></span></a> <a href="https://es.linkedin.com/in/artprojectgroup" title="<?php esc_attr_e( 'Follow us on ', 'apg-withdrawal-for-woocommerce' ); ?>LinkedIn" target="_blank"><span class="genericon genericon-linkedin"></span></a> </p>
		</div>
		<div class="columna">
			<p><?php esc_html_e( 'More plugins:', 'apg-withdrawal-for-woocommerce' ); ?></p>
			<p><a href="https://profiles.wordpress.org/artprojectgroup/" title="<?php esc_attr_e( 'More plugins on ', 'apg-withdrawal-for-woocommerce' ); ?>WordPress" target="_blank"><span class="genericon genericon-wordpress"></span></a> </p>
		</div>
	</div>

	<!-- Row: Contact and Documentation/Support -->
	<div class="fila">
		<div class="columna">
			<p><?php esc_html_e( 'Contact with us:', 'apg-withdrawal-for-woocommerce' ); ?></p>
			<p><a href="mailto:info@artprojectgroup.es" title="<?php esc_attr_e( 'Contact with us by ', 'apg-withdrawal-for-woocommerce' ); ?>e-mail"><span class="genericon genericon-mail"></span></a> </p>
		</div>
		<div class="columna">
			<p><?php esc_html_e( 'Documentation and Support:', 'apg-withdrawal-for-woocommerce' ); ?></p>
			<p><a href="<?php echo esc_url( $apg_withdrawal['plugin_url'] ); ?>" title="<?php echo esc_attr( $apg_withdrawal['plugin'] ); ?>"><span class="genericon genericon-book"></span></a> <a href="<?php echo esc_url( $apg_withdrawal['soporte'] ); ?>" title="<?php esc_attr_e( 'Support', 'apg-withdrawal-for-woocommerce' ); ?>"><span class="genericon genericon-cog"></span></a> </p>
		</div>
	</div>

	<!-- Final row: Rating -->
	<div class="fila final">
		<div class="columna">
			<p>
				<?php
				/* translators: %s plugin name */
				echo esc_html( sprintf( __( 'Please, rate %s:', 'apg-withdrawal-for-woocommerce' ), $apg_withdrawal['plugin'] ) );
				?>
			</p>
			<?php echo wp_kses_post( apg_withdrawal_plugin( $apg_withdrawal['plugin_uri'] ) ); ?>
		</div>
		<div class="columna final"></div>
	</div>
</div>
