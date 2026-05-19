<?php
/**
 * Common helper functions for the APG Withdrawal for WooCommerce plugin.
 *
 * @package APG_Withdrawal_For_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

global $apg_withdrawal;

/**
 * Static plugin data used in the admin area.
 *
 * @var array{
 *   plugin:string,
 *   plugin_uri:string,
 *   donacion:string,
 *   soporte:string,
 *   plugin_url:string,
 *   ajustes:string,
 *   puntuacion:string
 * }
 */
$apg_withdrawal = array(
	'plugin'     => 'APG Withdrawal for WooCommerce',
	'plugin_uri' => 'apg-withdrawal-for-woocommerce',
	'donacion'   => 'https://artprojectgroup.es/tienda/donacion',
	'soporte'    => 'https://artprojectgroup.es/tienda/soporte-tecnico',
	'plugin_url' => 'https://artprojectgroup.es/plugins-para-woocommerce/apg-withdrawal-for-woocommerce',
	'ajustes'    => 'admin.php?page=apg-withdrawal-for-woocommerce',
	'puntuacion' => 'https://www.wordpress.org/support/view/plugin-reviews/apg-withdrawal-for-woocommerce',
);

/**
 * Replaces the literal English plugin name in the global $apg_withdrawal array with
 * its translated counterpart once WordPress has fired `init`. Translating at array
 * initialization time would trigger `_load_textdomain_just_in_time` before the
 * domain is registered, which WordPress 6.7+ flags as a "too early" notice. All
 * call sites that read `$apg_withdrawal['plugin']` run on admin pages, filters
 * (`plugin_row_meta`, `plugin_action_links_*`) or enqueue callbacks fired after
 * `init`, so by the time they read the value it is already translated.
 *
 * @return void
 */
function apg_withdrawal_translate_plugin_name() {
	global $apg_withdrawal;
	$apg_withdrawal['plugin'] = __( 'APG Withdrawal for WooCommerce', 'apg-withdrawal-for-woocommerce' );
}
add_action( 'init', 'apg_withdrawal_translate_plugin_name' );

/**
 * Renders an admin notice indicating WooCommerce is required.
 *
 * @return void
 */
function apg_withdrawal_requiere_wc() {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'APG Withdrawal for WooCommerce requires WooCommerce to be installed and active.', 'apg-withdrawal-for-woocommerce' ); ?></p>
	</div>
	<?php
}

/**
 * Returns the plugin settings merged with defaults.
 *
 * @return array Plugin settings array.
 */
function apg_withdrawal_get_settings() {
	$defaults = array(
		'notification_email' => get_option( 'admin_email' ),
		'page_id'            => '0',
		'create_page'        => '1',
		'store_ip'           => '1',
		'store_user_agent'   => '1',
		'grace_days'         => '0',
		'withdrawal_days'    => '14',
		'deadline_source'    => 'completed',
		'button_text'        => __( 'Confirm withdrawal', 'apg-withdrawal-for-woocommerce' ),
		'order_status_map'   => array(
			'accepted'  => array(),
			'rejected'  => array(),
			'completed' => array(),
		),
		'email_on_status'    => array(
			'accepted'  => '0',
			'rejected'  => '1',
			'completed' => '1',
		),
		'digital_waiver_mode'         => 'disabled',
		'digital_waiver_categories'   => array(),
		'digital_waiver_products'     => array(),
		'digital_waiver_custom_label' => '',
	);

	return wp_parse_args( get_option( 'apg_withdrawal_settings', array() ), $defaults );
}

/**
 * Returns the withdrawal form page ID from settings.
 *
 * @return int Page ID.
 */
function apg_withdrawal_get_page_id() {
	$settings = apg_withdrawal_get_settings();

	return absint( $settings['page_id'] );
}

/**
 * Fetches plugin information from the WordPress.org API and returns the linked
 * HTML markup for the star rating.
 *
 * The remote response is cached for 24 h via a transient.
 *
 * @global array $apg_withdrawal
 *
 * @param string $nombre Plugin slug on WordPress.org.
 * @return string Star rating HTML markup (or fallback text on failure).
 */
function apg_withdrawal_plugin( $nombre ) {
	global $apg_withdrawal;

	$respuesta = get_transient( 'apg_withdrawal_plugin' );
	if ( false === $respuesta ) {
		$respuesta = wp_remote_get( 'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=' . $nombre );
		set_transient( 'apg_withdrawal_plugin', $respuesta, 24 * HOUR_IN_SECONDS );
	}
	if ( ! is_wp_error( $respuesta ) ) {
		$plugin = json_decode( wp_remote_retrieve_body( $respuesta ) );
	} else {
		/* translators: %s plugin name */
		return '<a title="' . sprintf( esc_attr__( 'Please, rate %s:', 'apg-withdrawal-for-woocommerce' ), $apg_withdrawal['plugin'] ) . '" href="' . $apg_withdrawal['puntuacion'] . '?rate=5#postform" class="estrellas">' . esc_attr__( 'Unknown rating', 'apg-withdrawal-for-woocommerce' ) . '</a>';
	}

	$rating = array(
		'rating' => ( isset( $plugin->rating ) ) ? $plugin->rating : 0,
		'type'   => 'percent',
		'number' => ( isset( $plugin->num_ratings ) ) ? $plugin->num_ratings : 0,
	);
	ob_start();
	wp_star_rating( $rating );
	$estrellas = ob_get_contents();
	ob_end_clean();

	/* translators: %s plugin name */
	return '<a title="' . sprintf( esc_attr__( 'Please, rate %s:', 'apg-withdrawal-for-woocommerce' ), $apg_withdrawal['plugin'] ) . '" href="' . $apg_withdrawal['puntuacion'] . '?rate=5#postform" class="estrellas">' . $estrellas . '</a>';
}

/**
 * Adds custom links (donation, social networks, rating, etc.) to the plugin row
 * inside the WordPress "Plugins" admin screen.
 *
 * Hook: `plugin_row_meta`.
 *
 * @global array $apg_withdrawal
 *
 * @param string[] $enlaces Existing list of plugin meta links.
 * @param string   $archivo Path of the plugin's main file being rendered.
 * @return string[] Original links merged with the plugin-specific ones when applicable.
 */
function apg_withdrawal_enlaces( $enlaces, $archivo ) {
	global $apg_withdrawal;

	if ( $archivo === apg_withdrawal_DIRECCION ) {
		$plugin    = apg_withdrawal_plugin( $apg_withdrawal['plugin_uri'] );
		$enlaces[] = '<a href="' . $apg_withdrawal['donacion'] . '" target="_blank" title="' . esc_attr__( 'Make a donation by ', 'apg-withdrawal-for-woocommerce' ) . 'APG"><span class="genericon genericon-cart"></span></a>';
		$enlaces[] = '<a href="' . esc_url( $apg_withdrawal['plugin_url'] ) . '" target="_blank" title="' . esc_attr( $apg_withdrawal['plugin'] ) . '"><strong class="artprojectgroup">APG</strong></a>';
		$enlaces[] = '<a href="https://www.facebook.com/artprojectgroup" title="' . esc_attr__( 'Follow us on ', 'apg-withdrawal-for-woocommerce' ) . 'Facebook" target="_blank"><span class="genericon genericon-facebook-alt"></span></a> <a href="https://x.com/artprojectgroup" title="' . esc_attr__( 'Follow us on ', 'apg-withdrawal-for-woocommerce' ) . 'X" target="_blank"><span class="genericon genericon-x-alt"></span></a> <a href="https://es.linkedin.com/in/artprojectgroup" title="' . esc_attr__( 'Follow us on ', 'apg-withdrawal-for-woocommerce' ) . 'LinkedIn" target="_blank"><span class="genericon genericon-linkedin"></span></a>';
		$enlaces[] = '<a href="https://profiles.wordpress.org/artprojectgroup/" title="' . esc_attr__( 'More plugins on ', 'apg-withdrawal-for-woocommerce' ) . 'WordPress" target="_blank"><span class="genericon genericon-wordpress"></span></a>';
		$enlaces[] = '<a href="mailto:info@artprojectgroup.es" title="' . esc_attr__( 'Contact with us by ', 'apg-withdrawal-for-woocommerce' ) . 'e-mail"><span class="genericon genericon-mail"></span></a>';
		$enlaces[] = $plugin;
	}

	return $enlaces;
}
add_filter( 'plugin_row_meta', 'apg_withdrawal_enlaces', 10, 2 );

/**
 * Adds the "Settings" and "Support" links to the plugin's action row.
 *
 * Hook: `plugin_action_links_{plugin_basename}`.
 *
 * @global array $apg_withdrawal
 *
 * @param string[] $enlaces Existing plugin action links.
 * @return string[] Updated links with "Settings" and "Support" prepended.
 */
function apg_withdrawal_enlace_de_ajustes( $enlaces ) {
	global $apg_withdrawal;

	$nuevos = array(
		'<a href="' . esc_url( $apg_withdrawal['soporte'] ) . '" title="' . esc_attr__( 'Support of ', 'apg-withdrawal-for-woocommerce' ) . esc_attr( $apg_withdrawal['plugin'] ) . '">' . esc_html__( 'Support', 'apg-withdrawal-for-woocommerce' ) . '</a>',
		'<a href="' . esc_url( admin_url( $apg_withdrawal['ajustes'] ) ) . '" title="' . esc_attr__( 'Settings of ', 'apg-withdrawal-for-woocommerce' ) . esc_attr( $apg_withdrawal['plugin'] ) . '">' . esc_html__( 'Settings', 'apg-withdrawal-for-woocommerce' ) . '</a>',
		'<a href="' . esc_url( admin_url( 'edit.php?post_type=apg_withdrawal' ) ) . '" title="' . esc_attr__( 'Withdrawals', 'apg-withdrawal-for-woocommerce' ) . '">' . esc_html__( 'Withdrawals', 'apg-withdrawal-for-woocommerce' ) . '</a>',
	);

	return array_merge( $nuevos, $enlaces );
}

/**
 * Plugin basename used to build the plugin action links filter hook name.
 *
 * @var string
 */
$plugin = apg_withdrawal_DIRECCION;
add_filter( "plugin_action_links_$plugin", 'apg_withdrawal_enlace_de_ajustes' );
