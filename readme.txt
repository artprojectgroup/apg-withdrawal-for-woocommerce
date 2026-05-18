=== APG Withdrawal for WooCommerce ===
Contributors: artprojectgroup
Donate link: https://artprojectgroup.es/tienda/donacion
Tags: withdrawal, right of withdrawal, woocommerce, refund, consumer rights
Requires at least: 6.0
Tested up to: 7.0
Stable tag: 0.3.0
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
* Optional digital-content waiver checkbox at checkout (both classic shortcode and block-based checkout): a configurable selector chooses when to display it — never, only on virtual products (or per-product `_apg_withdrawal_type = digital`), on every order, or on selected categories and/or selected products. The customer's choice is persisted to order meta as legal evidence.
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
= 0.3.0 =
* New: digital-content withdrawal waiver checkbox at checkout. Customers buying digital content or virtual services see an optional acknowledgement that requesting the immediate supply waives their right of withdrawal (EU consumer protection requirement). The checkbox is informational; ticking it is not mandatory and does not block order placement.
* The checkbox is injected in both checkouts: classic shortcode (via `woocommerce_checkout_before_terms_and_conditions` with priority 999) and block-based (via JavaScript that reinserts itself with a `MutationObserver` to remain right before the native terms checkbox, after any other custom one).
* In the block checkout, a generic cleanup pass removes content injected next to our wrapper by third-party plugins whose selectors over-match (e.g. plugins using `.wp-block-woocommerce-checkout-terms-block .wc-block-components-checkbox` plus jQuery `.after()`), avoiding duplicated privacy or marketing notices.
* The customer's choice is persisted to order meta `_apg_withdrawal_digital_waiver` (`'1'` or `'0'`) on both checkouts: the classic checkout reads the POST value on `woocommerce_checkout_create_order`, the block checkout injects the value into the StoreAPI request body under `extensions['apg-withdrawal']['digital_waiver']` and the server hook `woocommerce_store_api_checkout_update_order_from_request` writes the same meta.
* The block-checkout script reacts to cart changes mid-checkout: it watches StoreAPI cart mutations and, via a nonced AJAX endpoint (`apg_withdrawal_check_cart_waiver`), re-checks server-side whether the current cart still qualifies, inserting or removing the checkbox without a full page reload.
* New settings section "Digital content waiver" with a single SelectWoo selector for when to show the checkbox: never (default), only on virtual products, on every order, or on products in selected categories or selected products (these two can be combined). Category and product selectors load only when relevant. The "Only on virtual products" mode also matches products with the per-product `_apg_withdrawal_type = digital` setting, so virtual flag and explicit digital classification are treated as equivalent triggers.

= 0.2.0 =
* The frontend form now inherits the native WooCommerce stylesheet (notices, fields, buttons) without requiring custom CSS overrides.
* Notices rendered with `wc_print_notice()` so they pick up the correct WooCommerce template for both block themes (`block-notices/*.php`) and classic themes (`notices/*.php`).
* Dynamic notices (order-not-found feedback and product warning) are pre-rendered server-side via `wc_print_notice()` and toggled by JavaScript, instead of being built by hand with legacy markup that breaks on block themes.
* Order-not-found feedback follows the native WooCommerce pattern: notice at the top of the form plus `woocommerce-invalid` class on the email field.
* Buttons use `wc_wp_theme_get_element_class_name( 'button' )` for theme and block-theme compatibility.
* Removed inline CSS injected from JavaScript in favour of native WooCommerce notice classes.
* Spanish translation updated to informal "tú" treatment as recommended by the WooCommerce style guide.

= 0.1.0 =
* Initial release.

== Upgrade Notice ==
= 0.3.0 =
* New: digital-content withdrawal waiver checkbox at checkout, with a settings section to choose when to display it (disabled by default).

= 0.2.0 =
* Frontend form aligned with native WooCommerce notices, fields and buttons. Custom CSS overrides for the form may no longer be necessary.

== Thanks ==
Thanks to everyone who uses the plugin, helps improve it, makes a donation or encourages us with their comments.

If you find this plugin useful, you can support its development with a [small donation](https://artprojectgroup.es/tienda/donacion).

== External Services ==
This plugin connects to the WordPress.org Plugins API to fetch information about the plugin (such as the rating). It sends the plugin slug when requesting data. More information: https://wordpress.org/about/privacy/
