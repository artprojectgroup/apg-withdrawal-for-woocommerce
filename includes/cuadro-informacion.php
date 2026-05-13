<?php
/**
 * Cuadro lateral de información del plugin.
 *
 * @package WC_APG_Withdrawal
 * @global array<string,string> $apg_withdrawal
 */

defined( 'ABSPATH' ) || exit;

global $apg_withdrawal;
?>
<div class="informacion">
	<!-- Fila: Donación y autor -->
	<div class="fila">
		<div class="columna">
			<p><?php esc_html_e( 'If you enjoyed and find helpful this plugin, please make a donation:', 'wc-apg-withdrawal' ); ?></p>
			<p><a href="<?php echo esc_url( $apg_withdrawal['donacion'] ); ?>" target="_blank" title="<?php esc_attr_e( 'Make a donation by ', 'wc-apg-withdrawal' ); ?>APG"><span class="genericon genericon-cart"></span></a> </p>
		</div>
		<div class="columna">
			<p>Art Project Group:</p>
			<p><a href="https://www.artprojectgroup.es" title="Art Project Group" target="_blank"><strong class="artprojectgroup">APG</strong></a> </p>
		</div>
	</div>

	<!-- Fila: Redes sociales y más plugins -->
	<div class="fila">
		<div class="columna">
			<p><?php esc_html_e( 'Follow us:', 'wc-apg-withdrawal' ); ?></p>
			<p><a href="https://www.facebook.com/artprojectgroup" title="<?php esc_attr_e( 'Follow us on ', 'wc-apg-withdrawal' ); ?>Facebook" target="_blank"><span class="genericon genericon-facebook-alt"></span></a> <a href="https://x.com/artprojectgroup" title="<?php esc_attr_e( 'Follow us on ', 'wc-apg-withdrawal' ); ?>X" target="_blank"><span class="genericon genericon-x-alt"></span></a> <a href="https://es.linkedin.com/in/artprojectgroup" title="<?php esc_attr_e( 'Follow us on ', 'wc-apg-withdrawal' ); ?>LinkedIn" target="_blank"><span class="genericon genericon-linkedin"></span></a> </p>
		</div>
		<div class="columna">
			<p><?php esc_html_e( 'More plugins:', 'wc-apg-withdrawal' ); ?></p>
			<p><a href="https://profiles.wordpress.org/artprojectgroup/" title="<?php esc_attr_e( 'More plugins on ', 'wc-apg-withdrawal' ); ?>WordPress" target="_blank"><span class="genericon genericon-wordpress"></span></a> </p>
		</div>
	</div>

	<!-- Fila: Contacto y Documentación/Soporte -->
	<div class="fila">
		<div class="columna">
			<p><?php esc_html_e( 'Contact with us:', 'wc-apg-withdrawal' ); ?></p>
			<p><a href="mailto:info@artprojectgroup.es" title="<?php esc_attr_e( 'Contact with us by ', 'wc-apg-withdrawal' ); ?>e-mail"><span class="genericon genericon-mail"></span></a> </p>
		</div>
		<div class="columna">
			<p><?php esc_html_e( 'Documentation and Support:', 'wc-apg-withdrawal' ); ?></p>
			<p><a href="<?php echo esc_url( $apg_withdrawal['plugin_url'] ); ?>" title="<?php echo esc_attr( $apg_withdrawal['plugin'] ); ?>"><span class="genericon genericon-book"></span></a> <a href="<?php echo esc_url( $apg_withdrawal['soporte'] ); ?>" title="<?php esc_attr_e( 'Support', 'wc-apg-withdrawal' ); ?>"><span class="genericon genericon-cog"></span></a> </p>
		</div>
	</div>

	<!-- Fila final: Valoración -->
	<div class="fila final">
		<div class="columna">
			<p>
				<?php
				/* translators: %s plugin name */
				echo esc_html( sprintf( __( 'Please, rate %s:', 'wc-apg-withdrawal' ), $apg_withdrawal['plugin'] ) );
				?>
			</p>
			<?php echo wp_kses_post( apg_withdrawal_plugin( $apg_withdrawal['plugin_uri'] ) ); ?>
		</div>
		<div class="columna final"></div>
	</div>
</div>
