<?php
/**
 * Ayuda contextual de las dos pantallas del plugin: la pestaña «Ayuda» nativa de WordPress, con un
 * panel por cada bloque de los ajustes y otro para el listado de solicitudes. La versión completa
 * vive en la web y se enlaza desde el lateral.
 *
 * @package APG_Withdrawal_For_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Añade los paneles de ayuda a las pantallas del plugin.
 */
class APG_Withdrawal_Help {

	/**
	 * Dirección de la documentación completa, en español.
	 */
	const DOCS_URL = 'https://artprojectgroup.es/documentacion/apg-desistimiento-para-woocommerce';

	/**
	 * Dirección de la documentación completa, en inglés.
	 */
	const DOCS_URL_EN = 'https://artprojectgroup.com/documentation/apg-desistimiento-para-woocommerce';

	/**
	 * Sustituye la ayuda de la pantalla de ajustes por los paneles del plugin.
	 *
	 * La pantalla cuelga del menú de WooCommerce, que añade sus propios paneles y su lateral: aquí
	 * se quitan, porque esta pantalla es del plugin y no de WooCommerce.
	 *
	 * @return void
	 */
	public static function add_tabs() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$screen->remove_help_tabs();

		foreach ( self::get_panels() as $id => $panel ) {
			$screen->add_help_tab(
				array(
					'id'      => 'apg_withdrawal_help_' . $id,
					'title'   => $panel['title'],
					'content' => self::render( $panel['items'] ),
				)
			);
		}

		$screen->set_help_sidebar( self::sidebar() );
	}

	/**
	 * Añade a la pantalla del listado de solicitudes su propio panel.
	 *
	 * Aquí no se borra nada: la ayuda de un listado la pone WordPress y explica cómo se filtra y
	 * se ordena una tabla, que sigue valiendo.
	 *
	 * @return void
	 */
	public static function add_list_tab() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$panels = self::get_panels();

		$screen->add_help_tab(
			array(
				'id'       => 'apg_withdrawal_help_requests',
				'title'    => $panels['requests']['title'],
				'content'  => self::render( $panels['requests']['items'] ),
				//Los paneles del núcleo van con la prioridad predeterminada, que es 10: éste va detrás.
				'priority' => 20,
			)
		);

		$screen->set_help_sidebar( $screen->get_help_sidebar() . self::sidebar() );
	}

	/**
	 * Lateral con el enlace a la documentación completa.
	 *
	 * @return string
	 */
	private static function sidebar() {
		return '<p><strong>' . esc_html__( 'More help', 'apg-withdrawal-for-woocommerce' ) . '</strong></p>' .
			'<p><a href="' . esc_url( self::documentacion() ) . '" target="_blank">' . esc_html__( 'Full documentation', 'apg-withdrawal-for-woocommerce' ) . '</a></p>';
	}

	/**
	 * Dirección de la documentación en el idioma de quien lee la pantalla.
	 *
	 * @return string
	 */
	private static function documentacion() {
		return ( 0 === strpos( get_user_locale(), 'es' ) ) ? self::DOCS_URL : self::DOCS_URL_EN;
	}

	/**
	 * Pinta un panel: un párrafo de entrada y una lista de término => explicación.
	 *
	 * @param array $items Primer elemento: texto de entrada (cadena). Después, término => explicación.
	 * @return string
	 */
	private static function render( array $items ) {
		$html = '';
		$list = '';

		foreach ( $items as $term => $text ) {
			if ( is_int( $term ) ) {
				$html .= '<p>' . esc_html( $text ) . '</p>';
			} else {
				$list .= '<li><strong>' . esc_html( $term ) . '</strong>: ' . esc_html( $text ) . '</li>';
			}
		}

		return $html . ( '' !== $list ? '<ul>' . $list . '</ul>' : '' );
	}

	/**
	 * Paneles de la pantalla de ajustes, en el orden de sus bloques, más el del listado.
	 *
	 * @return array ID del panel => array( title, items ).
	 */
	private static function get_panels() {
		return array(
			'store'      => array(
				'title' => __( 'The store and the form', 'apg-withdrawal-for-woocommerce' ),
				'items' => array(
					__( 'Who receives the withdrawal declarations and where the customer writes them. These are the data that end up in the printable Annex I.B form, the model the law requires you to make available.', 'apg-withdrawal-for-woocommerce' ),
					__( 'Notification email', 'apg-withdrawal-for-woocommerce' )       => __( 'the address that gets a copy of every request. Leave it empty and the one on the store goes.', 'apg-withdrawal-for-woocommerce' ),
					__( 'Merchant phone (optional)', 'apg-withdrawal-for-woocommerce' ) => __( 'shown next to the name, the address and the email of the store in the Annex I.B form. Empty, the phone line is left out.', 'apg-withdrawal-for-woocommerce' ),
					__( 'Withdrawal page', 'apg-withdrawal-for-woocommerce' )          => __( 'the page where the form shortcode is published. The plugin creates it on activation, and this is where you point it somewhere else if you move it.', 'apg-withdrawal-for-woocommerce' ),
					__( 'Confirmation button text', 'apg-withdrawal-for-woocommerce' ) => __( 'what the button says at the end of the form, the one that registers the declaration.', 'apg-withdrawal-for-woocommerce' ),
					__( 'Jurisdiction', 'apg-withdrawal-for-woocommerce' )             => __( 'which law is cited to the customer on the confirmation screen. Spain has not transposed Directive (EU) 2023/2673 yet, so both options apply the same requirements today; the Spanish one also cites the TRLGDCU.', 'apg-withdrawal-for-woocommerce' ),
				),
			),

			'deadline'   => array(
				'title' => __( 'The deadline and the record', 'apg-withdrawal-for-woocommerce' ),
				'items' => array(
					__( 'How long the customer has, and what is kept of each declaration. Fourteen days is the legal minimum in the European Union: you can give more, never less.', 'apg-withdrawal-for-woocommerce' ),
					__( 'Withdrawal window (days)', 'apg-withdrawal-for-woocommerce' )    => __( 'after these days the order no longer shows the withdrawal button. The form still accepts a late request, with a warning, because deciding whether it is out of time is yours to do, not the form\'s.', 'apg-withdrawal-for-woocommerce' ),
					__( 'Extra grace days', 'apg-withdrawal-for-woocommerce' )            => __( 'days on top of the window, for the time a delivery takes or simply to be generous with your customer.', 'apg-withdrawal-for-woocommerce' ),
					__( 'Deadline source', 'apg-withdrawal-for-woocommerce' )             => __( 'the date the count starts from: the completed date, which is the closest thing to the delivery date, or the order date. With an order that is not completed yet, the plugin falls back on its own.', 'apg-withdrawal-for-woocommerce' ),
					__( 'Store IP address?', 'apg-withdrawal-for-woocommerce' )           => __( 'keeps the address the declaration was sent from, as evidence of who sent it and when.', 'apg-withdrawal-for-woocommerce' ),
					__( 'Store browser identifier?', 'apg-withdrawal-for-woocommerce' )   => __( 'keeps the browser identifier, for the same reason. Both are personal data: keep them only if you are going to use them, and say so in your privacy policy.', 'apg-withdrawal-for-woocommerce' ),
					__( 'Delete all data on uninstall?', 'apg-withdrawal-for-woocommerce' ) => __( 'unchecked, uninstalling the plugin leaves the requests and the settings in place. Checked, it takes everything with it, and there is no going back.', 'apg-withdrawal-for-woocommerce' ),
				),
			),

			'handling'   => array(
				'title' => __( 'Request handling', 'apg-withdrawal-for-woocommerce' ),
				'items' => array(
					__( 'What happens the moment a declaration arrives.', 'apg-withdrawal-for-woocommerce' ),
					__( 'Order status when a request is received', 'apg-withdrawal-for-woocommerce' ) => __( 'the plugin registers its own Withdrawal requested status, so those orders can be filtered, sorted and bulk-edited with the usual WooCommerce tools. Leave it as it comes and the order is not touched.', 'apg-withdrawal-for-woocommerce' ),
					__( 'Account for the refund (IBAN)', 'apg-withdrawal-for-woocommerce' )           => __( 'asks the customer for an account when the money cannot go back the way it came. The number is checked with its international control digits, travels with the request as banking data, is included in the privacy exporter and is deleted by the eraser. Only ask for it if you really need it.', 'apg-withdrawal-for-woocommerce' ),
					__( 'Confirm guest requests by email?', 'apg-withdrawal-for-woocommerce' )       => __( 'a visitor who is not logged in gets a single-use link at the address on the order, and the request only counts once that link is opened. It keeps the date and time of the original submission, so nobody loses days of their period.', 'apg-withdrawal-for-woocommerce' ),
				),
			),

			'automation' => array(
				'title' => __( 'Automation and emails', 'apg-withdrawal-for-woocommerce' ),
				'items' => array(
					__( 'A withdrawal has two lives in parallel: the request, with its own status, and the WooCommerce order. Here the two are tied together so you do not have to update both by hand.', 'apg-withdrawal-for-woocommerce' ),
					__( 'Mark as ... when order becomes', 'apg-withdrawal-for-woocommerce' )  => __( 'one line per request status. Choose which order status moves it, and the request follows the order on its own. A refunded order marking the request as completed is the usual one.', 'apg-withdrawal-for-woocommerce' ),
					__( 'Email when changed to ...', 'apg-withdrawal-for-woocommerce' )       => __( 'which status changes write to the customer. The content of those emails is edited where all the WooCommerce ones are, in WooCommerce > Settings > Emails.', 'apg-withdrawal-for-woocommerce' ),
				),
			),

			'exclusions' => array(
				'title' => __( 'What can be withdrawn', 'apg-withdrawal-for-woocommerce' ),
				'items' => array(
					__( 'The law leaves some things out of the right of withdrawal: something made to measure, something unsealed, digital content already downloaded. Each product carries its own type in the Withdrawal tab of its data, and a category can set it for everything inside it.', 'apg-withdrawal-for-woocommerce' ),
					__( 'Withdrawal allowed', 'apg-withdrawal-for-woocommerce' )     => __( 'the usual one, and what a product inherits when neither it nor its categories say otherwise.', 'apg-withdrawal-for-woocommerce' ),
					__( 'Withdrawal excluded', 'apg-withdrawal-for-woocommerce' )    => __( 'there is no right of withdrawal for this product, and the customer is told so before buying.', 'apg-withdrawal-for-woocommerce' ),
					__( 'Digital content', 'apg-withdrawal-for-woocommerce' )        => __( 'the right is lost once the download starts, provided the customer accepted it beforehand. This is the type that triggers the waiver at checkout.', 'apg-withdrawal-for-woocommerce' ),
					__( 'Personalised product', 'apg-withdrawal-for-woocommerce' )   => __( 'made to the customer\'s measure, so it is excluded by law.', 'apg-withdrawal-for-woocommerce' ),
					__( 'Manual review required', 'apg-withdrawal-for-woocommerce' ) => __( 'neither yes nor no: the request comes in and you decide. For the cases the law leaves to the condition of the goods.', 'apg-withdrawal-for-woocommerce' ),
					__( 'Exclusion notice texts', 'apg-withdrawal-for-woocommerce' ) => __( 'what each type says to the customer, on the product page between the price and the Add to cart button, and again inside the form. Leave a field empty to keep the translated text that comes with the plugin.', 'apg-withdrawal-for-woocommerce' ),
				),
			),

			'waiver'     => array(
				'title' => __( 'Digital content waiver', 'apg-withdrawal-for-woocommerce' ),
				'items' => array(
					__( 'Downloadable content loses the right of withdrawal only if the customer accepted it before the download started, and the shop can prove it. This checkbox at checkout is that proof.', 'apg-withdrawal-for-woocommerce' ),
					__( 'When to show the checkbox', 'apg-withdrawal-for-woocommerce' ) => __( 'never, only on the products classified as digital content, or on every order. The middle one is the usual choice.', 'apg-withdrawal-for-woocommerce' ),
					__( 'Custom checkbox text', 'apg-withdrawal-for-woocommerce' )     => __( 'comes filled in with the acknowledgement text. Edit it to say it your way, or empty the field to go back to the one that comes with the plugin.', 'apg-withdrawal-for-woocommerce' ),
				),
			),

			'requests'   => array(
				'title' => __( 'The requests', 'apg-withdrawal-for-woocommerce' ),
				'items' => array(
					__( 'Every declaration your customers send is kept here, with its order, its date, the products it covers and what the customer wrote. It is the log you can show if you are ever asked to prove how you handled a withdrawal.', 'apg-withdrawal-for-woocommerce' ),
					__( 'Pending', 'apg-withdrawal-for-woocommerce' )   => __( 'it has arrived and nobody has looked at it yet. It is the status every request is born in.', 'apg-withdrawal-for-woocommerce' ),
					__( 'Accepted', 'apg-withdrawal-for-woocommerce' )  => __( 'the withdrawal is valid. From here the refund follows, through WooCommerce.', 'apg-withdrawal-for-woocommerce' ),
					__( 'Rejected', 'apg-withdrawal-for-woocommerce' )  => __( 'out of time, excluded product or whatever the reason. Write the reason down, because the customer can ask for it.', 'apg-withdrawal-for-woocommerce' ),
					__( 'Completed', 'apg-withdrawal-for-woocommerce' ) => __( 'everything is done, refund included. If you set up the automation, a refunded order leaves its request like this on its own.', 'apg-withdrawal-for-woocommerce' ),
				),
			),
		);
	}
}
