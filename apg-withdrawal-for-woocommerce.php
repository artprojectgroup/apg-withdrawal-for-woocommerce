<?php
/*
Plugin Name: APG Withdrawal for WooCommerce
Requires Plugins: woocommerce
Version: 0.5.0
Plugin URI: https://wordpress.org/plugins/apg-withdrawal-for-woocommerce/
Description: Add to WooCommerce an online withdrawal workflow compliant with EU requirements.
Author URI: https://artprojectgroup.es/
Author: Art Project Group
License: GNU General Public License v3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
WC requires at least: 7.0
WC tested up to: 10.8.0

Text Domain: apg-withdrawal-for-woocommerce
Domain Path: /languages

@package APG_Withdrawal_For_WooCommerce
@category Core
@author Art Project Group
*/

defined( 'ABSPATH' ) || exit;

define( 'apg_withdrawal_DIRECCION', plugin_basename( __FILE__ ) );
define( 'apg_withdrawal_VERSION', '0.5.0' );
define( 'apg_withdrawal_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

include_once 'includes/admin/funciones-apg.php';

include_once ABSPATH . 'wp-admin/includes/plugin.php';
if ( is_plugin_active( 'woocommerce/woocommerce.php' ) || is_network_only_plugin( 'woocommerce/woocommerce.php' ) ) {
	/**
	 * Returns the URL for the withdrawal endpoint in the customer account area.
	 *
	 * @param array $args Optional query arguments to append to the URL.
	 * @return string Absolute URL for the withdrawal account endpoint.
	 */
	function apg_withdrawal_get_account_url( $args = array() ) {
		if ( function_exists( 'wc_get_account_endpoint_url' ) ) {
			$url = wc_get_account_endpoint_url( 'withdrawal' );
		} elseif ( function_exists( 'wc_get_page_permalink' ) ) {
			$url = wc_get_page_permalink( 'myaccount' );
		} else {
			$url = home_url( '/' );
		}

		return $args ? add_query_arg( $args, $url ) : $url;
	}

	add_action(
		'before_woocommerce_init',
		function () {
			if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			}
		}
	);

	add_filter(
		'woocommerce_email_classes',
		function ( $email_classes ) {
			if ( ! isset( $email_classes['APG_Withdrawal_Email_Customer'] ) ) {
				include_once apg_withdrawal_PLUGIN_DIR . 'includes/clases/emails/class-apg-withdrawal-email-customer.php';
				$email_classes['APG_Withdrawal_Email_Customer'] = new APG_Withdrawal_Email_Customer();
			}

			if ( ! isset( $email_classes['APG_Withdrawal_Email_Admin'] ) ) {
				include_once apg_withdrawal_PLUGIN_DIR . 'includes/clases/emails/class-apg-withdrawal-email-admin.php';
				$email_classes['APG_Withdrawal_Email_Admin'] = new APG_Withdrawal_Email_Admin();
			}

			if ( ! isset( $email_classes['APG_Withdrawal_Email_Status'] ) ) {
				include_once apg_withdrawal_PLUGIN_DIR . 'includes/clases/emails/class-apg-withdrawal-email-status.php';
				$email_classes['APG_Withdrawal_Email_Status'] = new APG_Withdrawal_Email_Status();
			}

			return $email_classes;
		}
	);

	class APG_Withdrawal {
		/** @var bool Tracks whether any expired-deadline withdrawal button was rendered in the orders list. */
		private $apg_withdrawal_has_expired_action = false;

		/**
		 * Registers all plugin hooks, includes class files and registers shortcodes.
		 */
		public function __construct() {
			add_action( 'init', array( $this, 'apg_withdrawal_registra_cpt' ) );
			add_action( 'init', array( $this, 'apg_withdrawal_registra_endpoint' ) );
			add_action( 'admin_menu', array( $this, 'apg_withdrawal_admin_menu' ), 15 );
			add_action( 'admin_init', array( $this, 'apg_withdrawal_registra_opciones' ) );
			add_action( 'admin_init', array( $this, 'apg_withdrawal_maybe_create_default_page' ) );
			add_filter( 'woocommerce_screen_ids', array( $this, 'apg_withdrawal_screen_id' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'apg_withdrawal_admin_css' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'apg_withdrawal_frontend_css' ) );
			add_action( 'woocommerce_account_withdrawal_endpoint', array( $this, 'apg_withdrawal_endpoint_contenido' ) );
			add_filter( 'woocommerce_account_menu_items', array( $this, 'apg_withdrawal_menu_cuenta' ) );
			add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'apg_withdrawal_accion_pedido' ), 10, 2 );
			add_action( 'woocommerce_after_account_orders', array( $this, 'apg_withdrawal_expired_footnote' ) );
			add_action( 'woocommerce_order_details_after_order_table', array( $this, 'apg_withdrawal_expired_footnote' ) );
			add_filter( 'query_vars', array( $this, 'apg_withdrawal_query_vars' ), 0 );
			add_shortcode( 'apg_withdrawal_form', array( $this, 'apg_withdrawal_shortcode' ) );

			include_once 'includes/clases/admin/solicitudes.php';
			include_once 'includes/clases/emails.php';
			include_once 'includes/clases/formulario.php';
			include_once 'includes/clases/pedidos.php';
			include_once 'includes/clases/procesador.php';
			include_once 'includes/clases/producto.php';
			include_once 'includes/clases/categoria.php';
			include_once 'includes/clases/aviso-producto.php';
			include_once 'includes/clases/anexo-i-b.php';
			include_once 'includes/clases/woocommerce.php';
			include_once 'includes/clases/checkout.php';
			include_once 'includes/clases/rgpd.php';
			include_once 'includes/clases/enlace.php';
		}

		/**
		 * Registers the plugin submenu pages under the WooCommerce menu.
		 *
		 * Adds the withdrawal requests list first, then the settings page.
		 * Also registers parent/submenu file filters so the correct menu item
		 * stays highlighted when editing individual withdrawal records.
		 *
		 * @return void
		 */
		public function apg_withdrawal_admin_menu() {
			add_submenu_page(
				'woocommerce',
				esc_attr__( 'Withdrawals', 'apg-withdrawal-for-woocommerce' ),
				esc_attr__( 'Withdrawals', 'apg-withdrawal-for-woocommerce' ),
				'manage_woocommerce',
				'edit.php?post_type=apg_withdrawal'
			);
			add_submenu_page(
				'woocommerce',
				esc_attr__( 'APG Withdrawal', 'apg-withdrawal-for-woocommerce' ),
				esc_attr__( 'Withdrawal', 'apg-withdrawal-for-woocommerce' ),
				'manage_woocommerce',
				'apg-withdrawal-for-woocommerce',
				array( $this, 'apg_withdrawal_tab' )
			);
			add_filter(
				'parent_file',
				function ( $parent_file ) {
					global $post_type;
					return 'apg_withdrawal' === $post_type ? 'woocommerce' : $parent_file;
				}
			);
			add_filter(
				'submenu_file',
				function ( $submenu_file ) {
					global $post_type;
					return 'apg_withdrawal' === $post_type ? 'edit.php?post_type=apg_withdrawal' : $submenu_file;
				}
			);
		}

		/**
		 * Adds the plugin's screen ID to WooCommerce's screen list so WC admin assets load.
		 *
		 * @param array<int,string> $woocommerce_screen_ids Registered WooCommerce screen IDs.
		 * @return array<int,string> Screen IDs with the withdrawal settings page added.
		 */
		public function apg_withdrawal_screen_id( $woocommerce_screen_ids ) {
			$woocommerce_screen_ids[] = 'woocommerce_page_apg-withdrawal-for-woocommerce';

			return $woocommerce_screen_ids;
		}

		/**
		 * Renders the plugin settings page by including the settings form template.
		 *
		 * @return void
		 */
		public function apg_withdrawal_tab() {
			include 'includes/formulario.php';
		}

		/**
		 * Registers the plugin settings option with its sanitization callback.
		 *
		 * @return void
		 */
		public function apg_withdrawal_registra_opciones() {
			register_setting(
				'apg_withdrawal_settings_group',
				'apg_withdrawal_settings',
				array(
					'sanitize_callback' => 'apg_withdrawal_sanitiza_opciones',
				)
			);
		}

		/**
		 * Creates the default withdrawal form page if the current user can manage options.
		 *
		 * @return void
		 */
		public function apg_withdrawal_maybe_create_default_page() {
			if ( current_user_can( 'manage_options' ) ) {
				apg_withdrawal_maybe_create_page();
			}
		}

		/**
		 * Registers the apg_withdrawal custom post type.
		 *
		 * @return void
		 */
		public function apg_withdrawal_registra_cpt() {
			$labels = array(
				'name'          => esc_html__( 'Withdrawals', 'apg-withdrawal-for-woocommerce' ),
				'singular_name' => esc_html__( 'Withdrawal', 'apg-withdrawal-for-woocommerce' ),
				'menu_name'     => esc_html__( 'Withdrawals', 'apg-withdrawal-for-woocommerce' ),
				'all_items'     => esc_html__( 'Withdrawals', 'apg-withdrawal-for-woocommerce' ),
				'add_new_item'  => esc_html__( 'Add withdrawal', 'apg-withdrawal-for-woocommerce' ),
				'edit_item'     => esc_html__( 'Edit withdrawal', 'apg-withdrawal-for-woocommerce' ),
				'view_item'     => esc_html__( 'View withdrawal', 'apg-withdrawal-for-woocommerce' ),
				'search_items'  => esc_html__( 'Search withdrawals', 'apg-withdrawal-for-woocommerce' ),
			);

			register_post_type(
				'apg_withdrawal',
				array(
					'labels'             => $labels,
					'public'             => false,
					'publicly_queryable' => false,
					'show_ui'            => true,
					'show_in_menu'       => false,
					'show_in_rest'       => false,
					'query_var'          => false,
					'rewrite'            => false,
					'capability_type'    => 'post',
					'map_meta_cap'       => true,
					'has_archive'        => false,
					'hierarchical'       => false,
					'menu_icon'          => 'dashicons-undo',
					'supports'           => array( 'title' ),
				)
			);
		}

		/**
		 * Registers the withdrawal rewrite endpoint for the account area.
		 *
		 * @return void
		 */
		public function apg_withdrawal_registra_endpoint() {
			add_rewrite_endpoint( 'withdrawal', EP_ROOT | EP_PAGES );
		}

		/**
		 * Adds the withdrawal query variable to WordPress's recognised query vars.
		 *
		 * @param array $vars Existing public query variables.
		 * @return array Modified query variables array.
		 */
		public function apg_withdrawal_query_vars( $vars ) {
			$vars[] = 'withdrawal';

			return $vars;
		}

		/**
		 * Adds the Withdrawal link to the WooCommerce My Account navigation menu.
		 *
		 * @param array $items Existing account menu items.
		 * @return array Modified menu items with the Withdrawal entry added before logout.
		 */
		public function apg_withdrawal_menu_cuenta( $items ) {
			$logout = isset( $items['customer-logout'] ) ? array( 'customer-logout' => $items['customer-logout'] ) : array();

			if ( $logout ) {
				unset( $items['customer-logout'] );
			}

			$items['withdrawal'] = esc_html__( 'Withdrawal', 'apg-withdrawal-for-woocommerce' );

			return array_merge( $items, $logout );
		}

		/**
		 * Renders the withdrawal form on the My Account withdrawal endpoint page.
		 *
		 * @return void
		 */
		public function apg_withdrawal_endpoint_contenido() {
			$user  = wp_get_current_user();
			$email = $user && ! empty( $user->user_email ) ? $user->user_email : '';

			echo apg_withdrawal_render_form( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is built entirely with escaped values inside apg_withdrawal_render_form()
				array(
					'email' => $email,
				)
			);
		}

		/**
		 * Adds a Withdrawal request action button to the My Account orders table.
		 *
		 * @param array             $actions Existing order action buttons.
		 * @param \WC_Order|object  $order   WooCommerce order object.
		 * @return array Modified actions array with the withdrawal button added when applicable.
		 */
		public function apg_withdrawal_accion_pedido( $actions, $order ) {
			if ( ! $order || ! is_callable( array( $order, 'get_id' ) ) ) {
				return $actions;
			}

			if ( function_exists( 'apg_withdrawal_order_has_active_request' ) && apg_withdrawal_order_has_active_request( $order->get_id() ) ) {
				return $actions;
			}

			$settings = apg_withdrawal_get_settings();
			$expired  = function_exists( 'apg_withdrawal_order_is_within_deadline' )
				&& ! apg_withdrawal_order_is_within_deadline( $order, $settings );

			$url = apg_withdrawal_get_account_url(
				array(
					'order_id' => $order->get_id(),
				)
			);

			if ( $expired ) {
				$this->apg_withdrawal_has_expired_action = true;
				$actions['apg_withdrawal_expired'] = array(
					'url'  => $url,
					'name' => esc_html__( 'Withdraw from the contract here *', 'apg-withdrawal-for-woocommerce' ),
				);
			} else {
				$actions['apg_withdrawal'] = array(
					'url'  => $url,
					'name' => esc_html__( 'Withdraw from the contract here', 'apg-withdrawal-for-woocommerce' ),
				);
			}

			return $actions;
		}

		/**
		 * Renders the expired-deadline footnote below the My Account orders table when needed.
		 *
		 * @param \WC_Order|null $order Order object passed by woocommerce_order_details_after_order_table (unused).
		 * @return void
		 */
		public function apg_withdrawal_expired_footnote( $order = null ) {
			if ( ! $this->apg_withdrawal_has_expired_action ) {
				return;
			}
			?>
<p class="apg-withdrawal-orders-expired-note">
    <?php esc_html_e( '* The standard withdrawal period for this order may have expired. You may still submit a request and it will be reviewed by the store.', 'apg-withdrawal-for-woocommerce' ); ?>
</p>
<?php
		}

		/**
		 * Renders the withdrawal form via the [apg_withdrawal_form] shortcode.
		 *
		 * @param array $atts Shortcode attributes ('order_id', 'email').
		 * @return string HTML output of the withdrawal form.
		 */
		public function apg_withdrawal_shortcode( $atts ) {
			$atts = shortcode_atts(
				array(
					'order_id' => '',
					'email'    => '',
				),
				$atts,
				'apg_withdrawal_form'
			);

			return apg_withdrawal_render_form( $atts );
		}

		/**
		 * Enqueues the plugin stylesheet on relevant admin screens.
		 *
		 * @return void
		 */
		public function apg_withdrawal_admin_css() {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : false;

			if ( ! $screen ) {
				return;
			}

			$is_plugin_screen = false !== strpos( $screen->id, 'apg-withdrawal-for-woocommerce' );
			$is_plugin_cpt    = 'apg_withdrawal' === $screen->post_type;
			$is_order_screen  = 'shop_order' === $screen->post_type
								|| in_array( $screen->id, array( 'woocommerce_page_wc-orders', 'edit-shop_order' ), true );
			$is_plugins_page  = 'plugins' === $screen->id;

			if ( ! $is_plugin_screen && ! $is_plugin_cpt && ! $is_order_screen && ! $is_plugins_page ) {
				return;
			}

			wp_enqueue_style(
				'apg-withdrawal-admin',
				plugins_url( 'assets/css/style.css', __FILE__ ),
				array(),
				apg_withdrawal_VERSION
			);
		}

		/**
		 * Enqueues the plugin stylesheet and select scripts on frontend withdrawal pages.
		 *
		 * @return void
		 */
		public function apg_withdrawal_frontend_css() {
			$page_id  = apg_withdrawal_get_page_id();
			$is_withdrawal_view = function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'withdrawal' );
			$is_withdrawal_view = $is_withdrawal_view || get_query_var( 'withdrawal', false );
			$load_css           = ( $page_id && is_page( $page_id ) ) || $is_withdrawal_view;

			if ( ! $load_css && function_exists( 'is_account_page' ) && is_account_page() ) {
				$load_css = true;
			}

			if ( ! $load_css && is_singular() ) {
				$post = get_post();

				if ( $post && has_shortcode( $post->post_content, 'apg_withdrawal_form' ) ) {
					$load_css = true;
				}
			}

			if ( ! $load_css ) {
				return;
			}

			$deps = wp_style_is( 'woocommerce-general', 'registered' ) ? array( 'woocommerce-general' ) : array();
			wp_enqueue_style(
				'apg-withdrawal',
				plugins_url( 'assets/css/frontend.css', __FILE__ ),
				$deps,
				apg_withdrawal_VERSION
			);

			if ( function_exists( 'WC' ) || class_exists( 'WooCommerce' ) ) {
				wp_enqueue_style( 'selectWoo' );
				wp_enqueue_script( 'selectWoo' );
				wp_enqueue_script( 'wc-enhanced-select' );
			}
		}
	}

	/**
	 * Sanitizes and validates the plugin settings before saving to the database.
	 *
	 * @param array $opciones Raw settings values submitted from the settings form.
	 * @return array Sanitized settings array.
	 */
	function apg_withdrawal_sanitiza_opciones( $opciones ) {
		$predeterminadas = array(
			'notification_email'       => get_option( 'admin_email' ),
			'merchant_phone'           => '',
			'page_id'                  => '0',
			'store_ip'                 => '1',
			'store_user_agent'         => '1',
			'grace_days'               => '0',
			'withdrawal_days'          => '14',
			'deadline_source'          => 'completed',
			'button_text'              => __( 'Confirm withdrawal', 'apg-withdrawal-for-woocommerce' ),
			'delete_data_on_uninstall' => '1',
			'order_status_map'         => array(
				'accepted'  => array(),
				'rejected'  => array(),
				'completed' => array(),
			),
			'email_on_status'          => array(
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

		$opciones = wp_parse_args( is_array( $opciones ) ? $opciones : array(), $predeterminadas );

		$raw_map      = isset( $opciones['order_status_map'] ) && is_array( $opciones['order_status_map'] ) ? $opciones['order_status_map'] : array();
		$raw_email_on = isset( $opciones['email_on_status'] ) && is_array( $opciones['email_on_status'] ) ? $opciones['email_on_status'] : array();
		$allowed_waiver_modes = array( 'disabled', 'digital', 'all' );
		$raw_waiver_mode      = isset( $opciones['digital_waiver_mode'] ) ? sanitize_key( $opciones['digital_waiver_mode'] ) : 'disabled';
		$raw_waiver_cats      = isset( $opciones['digital_waiver_categories'] ) && is_array( $opciones['digital_waiver_categories'] ) ? $opciones['digital_waiver_categories'] : array();
		$raw_waiver_products  = isset( $opciones['digital_waiver_products'] ) && is_array( $opciones['digital_waiver_products'] ) ? $opciones['digital_waiver_products'] : array();
		$raw_waiver_label     = isset( $opciones['digital_waiver_custom_label'] ) ? $opciones['digital_waiver_custom_label'] : '';
		$raw_notice_excluded     = isset( $opciones['exclusion_notice_excluded'] ) ? $opciones['exclusion_notice_excluded'] : '';
		$raw_notice_digital      = isset( $opciones['exclusion_notice_digital'] ) ? $opciones['exclusion_notice_digital'] : '';
		$raw_notice_personalized = isset( $opciones['exclusion_notice_personalized'] ) ? $opciones['exclusion_notice_personalized'] : '';
		$raw_notice_manual       = isset( $opciones['exclusion_notice_manual'] ) ? $opciones['exclusion_notice_manual'] : '';

		$sanitize_wc_statuses = function ( $statuses ) {
			if ( ! is_array( $statuses ) ) {
				return array();
			}
			return array_values( array_filter( array_map( 'sanitize_key', $statuses ) ) );
		};

		return array(
			'notification_email'       => sanitize_email( $opciones['notification_email'] ),
			'merchant_phone'           => sanitize_text_field( $opciones['merchant_phone'] ),
			'page_id'                  => strval( absint( $opciones['page_id'] ) ),
			'store_ip'                 => '1' === $opciones['store_ip'] ? '1' : '0',
			'store_user_agent'         => '1' === $opciones['store_user_agent'] ? '1' : '0',
			'grace_days'               => strval( max( 0, intval( $opciones['grace_days'] ) ) ),
			'withdrawal_days'          => strval( max( 14, intval( $opciones['withdrawal_days'] ) ) ),
			'deadline_source'          => in_array( $opciones['deadline_source'], array( 'completed', 'created' ), true ) ? $opciones['deadline_source'] : 'completed',
			'button_text'              => sanitize_text_field( $opciones['button_text'] ),
			'delete_data_on_uninstall' => '1' === $opciones['delete_data_on_uninstall'] ? '1' : '0',
			'order_status_map'   => array(
				'accepted'  => $sanitize_wc_statuses( isset( $raw_map['accepted'] ) ? $raw_map['accepted'] : array() ),
				'rejected'  => $sanitize_wc_statuses( isset( $raw_map['rejected'] ) ? $raw_map['rejected'] : array() ),
				'completed' => $sanitize_wc_statuses( isset( $raw_map['completed'] ) ? $raw_map['completed'] : array() ),
			),
			'email_on_status'    => array(
				'accepted'  => ! empty( $raw_email_on['accepted'] ) ? '1' : '0',
				'rejected'  => ! empty( $raw_email_on['rejected'] ) ? '1' : '0',
				'completed' => ! empty( $raw_email_on['completed'] ) ? '1' : '0',
			),
			'digital_waiver_mode'             => in_array( $raw_waiver_mode, $allowed_waiver_modes, true ) ? $raw_waiver_mode : 'disabled',
			'digital_waiver_categories'       => array_values( array_unique( array_filter( array_map( 'absint', $raw_waiver_cats ) ) ) ),
			'digital_waiver_products'         => array_values( array_unique( array_filter( array_map( 'absint', $raw_waiver_products ) ) ) ),
			'digital_waiver_custom_label'     => sanitize_text_field( $raw_waiver_label ),
			'exclusion_notice_excluded'       => sanitize_textarea_field( $raw_notice_excluded ),
			'exclusion_notice_digital'        => sanitize_textarea_field( $raw_notice_digital ),
			'exclusion_notice_personalized'   => sanitize_textarea_field( $raw_notice_personalized ),
			'exclusion_notice_manual'         => sanitize_textarea_field( $raw_notice_manual ),
		);
	}

	/**
	 * Instantiates the main APG_Withdrawal class when WooCommerce is loaded.
	 *
	 * @return void
	 */
	function apg_withdrawal_init() {
		new APG_Withdrawal();
	}
	add_action( 'woocommerce_loaded', 'apg_withdrawal_init' );

	/**
	 * Runs on plugin activation: registers CPT, endpoint and creates the default page.
	 *
	 * @return void
	 */
	function apg_withdrawal_activate() {
		$plugin = new APG_Withdrawal();
		$plugin->apg_withdrawal_registra_cpt();
		$plugin->apg_withdrawal_registra_endpoint();

		if ( function_exists( 'apg_withdrawal_maybe_create_page' ) ) {
			apg_withdrawal_maybe_create_page();
		}

		flush_rewrite_rules();
	}
	register_activation_hook( __FILE__, 'apg_withdrawal_activate' );

	/**
	 * Runs on plugin deactivation: flushes rewrite rules.
	 *
	 * @return void
	 */
	function apg_withdrawal_deactivate() {
		flush_rewrite_rules();
	}
	register_deactivation_hook( __FILE__, 'apg_withdrawal_deactivate' );
} else {
	add_action( 'admin_notices', 'apg_withdrawal_requiere_wc' );
}