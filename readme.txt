=== APG Withdrawal for WooCommerce ===
Contributors: artprojectgroup
Donate link: https://artprojectgroup.es/tienda/donacion
Tags: withdrawal, right of withdrawal, woocommerce, refund, consumer rights
Requires at least: 6.0
Tested up to: 7.0
Stable tag: 0.5.0
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

= Where should I place the withdrawal link? =
The withdrawal form page is auto-created on activation and contains the `[apg_withdrawal_form]` shortcode. To comply with Article 11a of Directive 2011/83/EU (added by Directive 2023/2673), the link to that page should be prominently visible and easy to find on the storefront. The plugin gives you several tools to place it; deciding *where* to place it is the merchant's (or their web designer's) responsibility:

* The fixed URL of the auto-created page, available in *WooCommerce → Withdrawal → Withdrawal page*.
* The `[apg_withdrawal_link]` shortcode, with optional `label`, `class` and `target` attributes, to drop the link inside any post, page, footer widget or HTML block.
* The matching *Withdrawal link* Gutenberg block for sites built with the Full Site Editor.
* The *Withdrawal request* action that is automatically added to every eligible order in the *My Account → Orders* table.

Typical recommended placements:

* The site footer, so the link is reachable from any page.
* The *My Account* menu (the per-order action is already added; you can also add a top-level menu item linking to the public form).
* The Terms and Conditions / Privacy Policy pages, alongside the rest of the consumer information required by Article 6.1.h of Directive 2011/83/EU.
* The order processing / completed emails (the plugin already injects the link there automatically via `woocommerce_email_after_order_table`).

= How long should I keep the withdrawal request records? =
The plugin does not delete withdrawal request records automatically. As a general recommendation, keep them for at least **5 years** after their creation — the typical statute of limitations for consumer and contractual actions in many EU jurisdictions. Always check the applicable retention period in your country before deleting old records or running the plugin's CSV export + uninstall flow.

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
= 0.5.0 =
* Compliance with Directive (EU) 2023/2673 (amends Directive 2011/83/EU on consumer rights). The plugin now covers the additional obligations introduced by the new Article 11a (online withdrawal function) plus the related pre-contractual and burden-of-proof requirements.
* Category-level *Withdrawal type* term meta on `product_cat`, with automatic inheritance for products that keep the "Withdrawal allowed (default)" value. When a product belongs to several categories with conflicting types, the most restrictive type wins (priority order: `excluded` > `personalized` > `digital` > `manual` > `allowed`).
* New `[apg_withdrawal_notice]` shortcode, matching `apg-withdrawal/notice` Gutenberg block and `woocommerce_single_product_summary` injection (priority 20, between the price and the Add to Cart button) that automatically displays the exclusion notice on the product page when the effective withdrawal type is not `allowed`.
* New plugin settings section "Exclusion notice texts" with one editable textarea per non-default type (`excluded`, `digital`, `personalized`, `manual`) and a translated default text per type. Optional per-product override field on the *Withdrawal* product data tab to customise the notice for a single product.
* "Digital content waiver" settings section simplified to a single excluding selector with three modes — `Never (disabled)`, `On products classified as digital content`, `On every order` — driven exclusively by the per-product / per-category withdrawal type. Legacy installations with mode `virtual` are migrated to `digital`; mode `specific` is migrated to `digital` and the previously selected categories / products are automatically marked with `_apg_withdrawal_type = digital` to preserve their behaviour. The legacy `digital_waiver_categories` / `digital_waiver_products` settings stop being honoured at the UI level (a one-time silent migration runs on `init`, flagged by the `apg_withdrawal_migrated_to_0_5` option).
* New printable Annex I.B model withdrawal form served at `?apg_withdrawal_model_form=1` with `@media print` styling, pre-populated with the store name, address, email (from WooCommerce settings) and an optional merchant phone (new `Merchant phone (optional)` plugin setting). The public withdrawal request form links to it as "Download the official model withdrawal form (Annex I.B)".
* New `[apg_withdrawal_link]` shortcode and `apg-withdrawal/link` Gutenberg block to render a link to the public withdrawal form with optional `label`, `class` and `target` attributes. The default label uses the literal wording suggested by Article 11a(1) ("Withdraw from the contract here"). The My Account per-order action label has been updated to the same default for new installs.
* Customer acknowledgement email now includes a verifiable SHA-256 hash of the receipt content (computed over name + email + order + scope + products + details + UTC timestamp) and the UTC timestamp used for verification. Hash and timestamp are also persisted in post meta (`_apg_withdrawal_receipt_hash`, `_apg_withdrawal_receipt_hash_timestamp`) and exposed in the CSV export.
* Digital-content waiver consent at checkout is now persisted as a structured log (`_apg_withdrawal_digital_waiver_log` order meta) that includes the exact label shown to the customer, UTC timestamp, IP, user agent and checkout type (`classic` or `block`). The legacy `_apg_withdrawal_digital_waiver` boolean meta is also written for backwards compatibility.
* Email delivery indicator: every status-change email and the initial customer acknowledgement now record whether `wp_mail()` was invoked, whether it returned success (= "accepted by the mailer", not actual recipient delivery), the UTC timestamp and any error captured through `wp_mail_failed`. The information is surfaced in the request detail screen and exported as two additional CSV columns.
* GDPR integration: the plugin now registers a personal-data exporter and a personal-data eraser with the native WordPress privacy tools. The eraser **anonymises** withdrawal requests (replaces name, email, phone, IP, user agent and customer-supplied free text with `[redacted]`) and keeps the record itself plus the `_apg_withdrawal_wc_order_id` reference for legal evidence, in line with the burden of proof in Article 16 bis(8). The same anonymisation is also triggered automatically when a WordPress user is deleted (via *Users → Delete*, a customer-facing "Delete my account" button shipped by third-party plugins such as `apg-gdpr-texts-for-forms`, or any other path), so the withdrawal records never outlive the user account with personal data attached.
* CSV export now defends against spreadsheet formula injection: every cell whose first character is `=`, `+`, `-`, `@`, tab or carriage return is prefixed with an apostrophe before being written via `fputcsv`.
* New FAQ entries documenting where the withdrawal link should be placed by the merchant or the web designer and recommending a minimum 5-year retention period for withdrawal request records.

= 0.4.0 =
* New setting "Custom checkbox text" in the Digital content waiver section: lets the merchant override the default acknowledgement label rendered at checkout with a custom plain-text string. Leaving the field empty keeps the default translatable text.
* The default page auto-created by the plugin now uses the title "Exercise the right of withdrawal" (translated to "Ejercer derecho de desistimiento" in Spanish) and lets WordPress derive its slug from the title. Existing pages are not modified — only new installations get the new title and slug.
* Internal: corrected the allowed-modes whitelist in the settings sanitiser (`disabled`, `virtual`, `all`, `specific`) so the values now match the actual mode selector.

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
= 0.5.0 =
* Directive (EU) 2023/2673 compliance: category-level withdrawal type with inheritance, product page exclusion notices, printable Annex I.B form, SHA-256 receipt hash, waiver consent log, email delivery indicator, GDPR exporter / eraser and CSV injection protection.

== Thanks ==
Thanks to everyone who uses the plugin, helps improve it, makes a donation or encourages us with their comments.

If you find this plugin useful, you can support its development with a [small donation](https://artprojectgroup.es/tienda/donacion).

== External Services ==
This plugin connects to the WordPress.org Plugins API to fetch information about the plugin (such as the rating). It sends the plugin slug when requesting data. More information: https://wordpress.org/about/privacy/
