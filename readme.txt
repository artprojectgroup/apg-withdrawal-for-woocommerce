=== APG Withdrawal for WooCommerce ===
Contributors: artprojectgroup
Donate link: https://artprojectgroup.es/tienda/donacion
Tags: withdrawal, right of withdrawal, woocommerce, refund, consumer rights
Requires at least: 6.0
Tested up to: 7.0
Stable tag: 0.1.0
Requires PHP: 7.4
WC requires at least: 7.0
WC tested up to: 10.8.0
License: GNU General Public License v3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Add to WooCommerce an online withdrawal workflow with customer form, My Account integration and admin request log.

== Description ==
**APG Withdrawal for WooCommerce** adds to your WooCommerce store a complete online right of withdrawal workflow compliant with EU consumer protection legislation.

= Features =
* Customer withdrawal form via the `[apg_withdrawal_form]` shortcode.
* Configurable withdrawal window (days) and deadline source (completed or created date).
* Optional extra grace days on top of the standard withdrawal window.
* Active request detection: hides the withdrawal button if a request is already open for the order.
* Admin request log with full request details (custom post type).
* IP address and browser identifier storage options for legal evidence.
* Email notification to the store admin on every new request.
* Automatic customer acknowledgement email on submission.
* Customer status update emails when the request is accepted, rejected or completed.
* Automation: updates the withdrawal request status automatically when the linked WooCommerce order changes status.
* My Account integration: customers can view their withdrawal request history.
* CSV export of all withdrawal requests.
* 100% compatible with HPOS (High-Performance Order Storage).

= Translations =
* English: by [Art Project Group](https://artprojectgroup.es/) (default language).
* Spanish: by [Art Project Group](https://artprojectgroup.es/).

= More information =
You can learn more about **APG Withdrawal for WooCommerce** on our [official website](https://artprojectgroup.es/plugins-para-woocommerce/apg-withdrawal-for-woocommerce), and follow the development on [GitHub](https://github.com/artprojectgroup/apg-withdrawal-for-woocommerce).

== Installation ==
1. Install the plugin in one of the following ways:
 * Upload the `apg-withdrawal-for-woocommerce` folder to the `/wp-content/plugins/` directory via FTP.
 * Upload the full ZIP file via *Plugins -> Add New -> Upload* in the WordPress administration panel.
 * Search for **APG Withdrawal for WooCommerce** in *Plugins -> Add New* and click *Install Now*.
2. Activate the plugin through the *Plugins* menu in the WordPress administration panel.
3. Configure the plugin in *WooCommerce -> Withdrawal* or through the *Settings* link on the plugins page.
4. Add the `[apg_withdrawal_form]` shortcode to the page configured as the withdrawal page in the settings.

== Frequently Asked Questions ==
= How do I configure the plugin? =
In the plugin settings you can configure the notification email, the withdrawal page, the withdrawal window in days, the deadline source (completed or created date), the extra grace days and which data to store (IP address, browser identifier).

= Is the plugin compatible with HPOS? =
Yes. The plugin is fully compatible with WooCommerce High-Performance Order Storage.

= Can guest customers submit a withdrawal request? =
Yes. The form supports both logged-in customers (with pre-filled data and order selector) and guests (with email lookup of their orders).

= Where can I get support? =
**APG Withdrawal for WooCommerce** is a free plugin. **Art Project Group** does not provide free technical support, but offers a paid [technical support](https://artprojectgroup.es/tienda/ticket-de-soporte) service for installation and configuration.

== Screenshots ==
1. Plugin settings page with general options, automation rules and customer email notifications.
2. Customer withdrawal form on the public page (guest checkout).
3. Withdrawal form integrated in the My Account area with order selector.
4. My Account orders list with the *Withdrawal request* action per order.
5. Admin withdrawals list with status, scope and order reference.
6. Edit withdrawal screen with full request details and status history.

== Changelog ==
= 0.1.0 =
* Initial release.

== Upgrade Notice ==
= 0.1.0 =
* Initial release.

== Thanks ==
Thanks to everyone who uses the plugin, helps improve it, makes a donation or encourages us with their comments.

If you find this plugin useful, you can support its development with a [small donation](https://artprojectgroup.es/tienda/donacion).

== External Services ==
This plugin connects to the WordPress.org Plugins API to fetch information about the plugin (such as the rating). It sends the plugin slug when requesting data. More information: https://wordpress.org/about/privacy/
