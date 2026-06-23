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
 * One-shot migration of legacy "Digital content waiver" settings to the 0.5.0
 * model. Runs once per installation, gated by the `apg_withdrawal_migrated_to_0_5`
 * option flag so re-activating the plugin does not re-run it. Mappings:
 *
 *   - mode `virtual`             → `digital`
 *   - mode `specific`            → `digital`, plus every category and product
 *                                  that was previously selected is marked with
 *                                  `_apg_withdrawal_type = digital` (preserving
 *                                  the prior behaviour at product level via the
 *                                  Phase 1.1 category-inheritance helper).
 *
 * After the mapping, the two now-unused arrays `digital_waiver_categories` and
 * `digital_waiver_products` are cleared inside the settings option.
 *
 * @return void
 */
function apg_withdrawal_maybe_migrate_settings_to_0_5() {
	if ( '1' === (string) get_option( 'apg_withdrawal_migrated_to_0_5', '0' ) ) {
		return;
	}

	$settings = get_option( 'apg_withdrawal_settings', array() );
	if ( ! is_array( $settings ) ) {
		update_option( 'apg_withdrawal_migrated_to_0_5', '1' );
		return;
	}

	$mode = isset( $settings['digital_waiver_mode'] ) ? (string) $settings['digital_waiver_mode'] : 'disabled';

	if ( 'virtual' === $mode ) {
		$settings['digital_waiver_mode'] = 'digital';
	} elseif ( 'specific' === $mode ) {
		$category_ids = isset( $settings['digital_waiver_categories'] ) && is_array( $settings['digital_waiver_categories'] )
			? array_map( 'absint', $settings['digital_waiver_categories'] )
			: array();
		$product_ids = isset( $settings['digital_waiver_products'] ) && is_array( $settings['digital_waiver_products'] )
			? array_map( 'absint', $settings['digital_waiver_products'] )
			: array();

		foreach ( $category_ids as $term_id ) {
			if ( ! $term_id ) {
				continue;
			}
			$existing = (string) get_term_meta( $term_id, '_apg_withdrawal_type', true );
			if ( '' === $existing || 'allowed' === $existing ) {
				update_term_meta( $term_id, '_apg_withdrawal_type', 'digital' );
			}
		}

		foreach ( $product_ids as $product_id ) {
			if ( ! $product_id ) {
				continue;
			}
			$existing = (string) get_post_meta( $product_id, '_apg_withdrawal_type', true );
			if ( '' === $existing || 'allowed' === $existing ) {
				update_post_meta( $product_id, '_apg_withdrawal_type', 'digital' );
			}
		}

		$settings['digital_waiver_mode']       = 'digital';
		$settings['digital_waiver_categories'] = array();
		$settings['digital_waiver_products']   = array();
	}

	update_option( 'apg_withdrawal_settings', $settings );
	update_option( 'apg_withdrawal_migrated_to_0_5', '1' );
}
add_action( 'init', 'apg_withdrawal_maybe_migrate_settings_to_0_5', 5 );

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
		'merchant_phone'     => '',
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
		'digital_waiver_mode'             => 'disabled',
		'digital_waiver_categories'       => array(),
		'digital_waiver_products'         => array(),
		'digital_waiver_custom_label'     => '',
		'exclusion_notice_excluded'       => '',
		'exclusion_notice_digital'        => '',
		'exclusion_notice_personalized'   => '',
		'exclusion_notice_manual'         => '',
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
 * Decides whether a withdrawal-related label looks ambiguous from the point of
 * view of Article 11 bis of Directive 2011/83/EU (added by Directive 2023/2673),
 * which requires the wording of the online withdrawal function to make it
 * unambiguous to the consumer that they are exercising their right of withdrawal.
 *
 * A label is considered unambiguous when it contains any of the "passes" terms
 * (e.g. `withdraw`, `desistir`, `revocation`). Otherwise, if it contains any of
 * the "blacklist" terms (e.g. `contacto`, `gestionar`, `volver`) the function
 * returns true so callers can warn the merchant. Both lists are filterable so
 * additional languages or merchant-specific vocabulary can be supported without
 * editing the plugin source.
 *
 * Comparison is case-insensitive and accent-insensitive.
 *
 * @param string $label Label to evaluate.
 * @return bool True when the label looks ambiguous and should trigger a warning.
 */
function apg_withdrawal_label_is_ambiguous( $label ) {
	$label = trim( (string) $label );
	if ( '' === $label ) {
		return false;
	}

	$normalized = remove_accents( $label );
	$normalized = function_exists( 'mb_strtolower' ) ? mb_strtolower( $normalized, 'UTF-8' ) : strtolower( $normalized );

	/**
	 * Filters the list of normalised terms that make a withdrawal label
	 * unambiguous for the purposes of Article 11 bis. If the label contains
	 * any of these substrings the ambiguity check returns false (label is OK).
	 *
	 * @param string[] $terms Lowercase, accent-stripped terms.
	 */
	$passes = (array) apply_filters(
		'apg_withdrawal_label_unambiguous_terms',
		array( 'desistir', 'desistimiento', 'withdraw', 'withdrawal', 'retract', 'revocacion', 'revocation' )
	);

	foreach ( $passes as $term ) {
		$term = (string) $term;
		if ( '' !== $term && false !== strpos( $normalized, $term ) ) {
			return false;
		}
	}

	/**
	 * Filters the list of normalised terms whose presence in the label triggers
	 * the Article 11 bis ambiguity warning. Useful to add more bad terms in
	 * other languages or to relax false positives.
	 *
	 * @param string[] $terms Lowercase, accent-stripped terms.
	 */
	$blacklist = (array) apply_filters(
		'apg_withdrawal_label_ambiguous_terms',
		array( 'contactanos', 'contacto', 'contact us', 'contact', 'consultar', 'consulta', 'consult', 'gestionar', 'gestiona', 'manage', 'volver', 'back', 'ayuda', 'help', 'ver pedido', 'view order' )
	);

	foreach ( $blacklist as $term ) {
		$term = (string) $term;
		if ( '' !== $term && false !== strpos( $normalized, $term ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Returns the mapping of merchant-configurable setting strings to the names
 * under which they are registered with WPML / Polylang (and looked up later
 * by `apg_withdrawal_translate_setting_string()`). Centralised here so the
 * registration and the runtime lookup cannot drift apart.
 *
 * @return array<string,string> Map of setting key → registered string name.
 */
function apg_withdrawal_get_translatable_setting_names() {
	return array(
		'button_text'                   => 'Confirmation button text',
		'digital_waiver_custom_label'   => 'Digital waiver custom label',
		'exclusion_notice_excluded'     => 'Exclusion notice excluded',
		'exclusion_notice_digital'      => 'Exclusion notice digital',
		'exclusion_notice_personalized' => 'Exclusion notice personalized',
		'exclusion_notice_manual'       => 'Exclusion notice manual',
	);
}

/**
 * Registers the merchant-configurable settings strings with WPML / Polylang
 * via the `wpml_register_single_string` action (Polylang ships a compatibility
 * layer for the same hook). No-op when neither plugin is active. Strings whose
 * stored value is empty are skipped because the i18n default already kicks in.
 *
 * @return void
 */
function apg_withdrawal_register_translatable_strings() {
	if ( ! function_exists( 'apg_withdrawal_get_settings' ) ) {
		return;
	}

	$settings = apg_withdrawal_get_settings();
	$domain   = 'apg-withdrawal-for-woocommerce';

	foreach ( apg_withdrawal_get_translatable_setting_names() as $key => $name ) {
		$value = isset( $settings[ $key ] ) ? (string) $settings[ $key ] : '';
		if ( '' === $value ) {
			continue;
		}
		do_action( 'wpml_register_single_string', $domain, $name, $value ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Third-party (WPML / Polylang) integration hook; not owned by this plugin.
	}
}
add_action( 'init', 'apg_withdrawal_register_translatable_strings', 20 );

/**
 * Returns the WPML / Polylang-translated version of a configurable setting
 * string. When no translation plugin is active, the `wpml_translate_single_string`
 * filter returns the original value unchanged.
 *
 * @param string $value Original (untranslated) string value.
 * @param string $name  Registered string name (see {@see apg_withdrawal_get_translatable_setting_names()}).
 * @return string
 */
function apg_withdrawal_translate_setting_string( $value, $name ) {
	return (string) apply_filters( 'wpml_translate_single_string', (string) $value, 'apg-withdrawal-for-woocommerce', (string) $name ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Third-party (WPML / Polylang) integration hook; not owned by this plugin.
}

/**
 * Sanitises the configurable label of the final confirmation button. Wraps
 * `sanitize_text_field()` to add an Article 11 bis ambiguity check: when the
 * resulting label looks ambiguous (see {@see apg_withdrawal_label_is_ambiguous()})
 * a settings warning is registered so the merchant sees it on next page load.
 *
 * The merchant's choice is never overridden — autonomy is preserved — only
 * surfaced as advisory.
 *
 * @param string $value Raw value from the settings form.
 * @return string Sanitised label.
 */
function apg_withdrawal_sanitize_button_text( $value ) {
	$value = sanitize_text_field( $value );

	if ( function_exists( 'add_settings_error' ) && apg_withdrawal_label_is_ambiguous( $value ) ) {
		add_settings_error(
			'apg_withdrawal_settings',
			'apg_withdrawal_button_text_ambiguous',
			sprintf(
				/* translators: %s saved button label. */
				__( 'The confirmation button label "%s" looks ambiguous. Article 11a of Directive 2011/83/EU (added by Directive (EU) 2023/2673) requires the wording to make it clear to the consumer that they are exercising their right of withdrawal. Consider using "Confirm withdrawal" or an equivalent unambiguous wording in your language.', 'apg-withdrawal-for-woocommerce' ),
				$value
			),
			'warning'
		);
	}

	return $value;
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
		$enlaces[] = '<a href="mailto:info@artprojectgroup.es" title="' . esc_attr__( 'Contact us by ', 'apg-withdrawal-for-woocommerce' ) . 'e-mail"><span class="genericon genericon-mail"></span></a>';
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
