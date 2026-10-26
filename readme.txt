=== APG Withdrawal for WooCommerce ===
Contributors: artprojectgroup
Donate link: https://artprojectgroup.es/tienda/donacion
Tags: withdrawal, right of withdrawal, woocommerce, refund, consumer rights
Requires at least: 6.0
Tested up to: 7.2
Stable tag: 0.7.4
Requires PHP: 7.4
WC requires at least: 7.0
WC tested up to: 11.2.1
License: GNU General Public License v3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Add to WooCommerce an online withdrawal workflow with customer form, My Account integration and admin request log.

== Description ==
**APG Withdrawal for WooCommerce** adds to your WooCommerce store a complete online right of withdrawal workflow compliant with EU consumer protection legislation.

= Features =
* Customer withdrawal form via the `[apg_withdrawal_form]` shortcode.
* Partial withdrawal down to the unit: the customer ticks the order lines and adjusts how many units of each one they are withdrawing from.
* Configurable withdrawal window (days) and deadline source (completed or created date).
* Optional extra grace days on top of the standard withdrawal window.
* Active request detection: hides the withdrawal button if a request is already open for the order.
* Optional digital-content waiver checkbox at checkout (both classic shortcode and block-based checkout): a single excluding selector chooses when to display it — never, on products classified as digital content, or on every order. **Which products count is decided exclusively by the per-product / per-category "Withdrawal type", never by whether the product is virtual or downloadable**: the product's own type is read first and, when it keeps the default `allowed`, it is inherited from its categories with the most restrictive one winning. The customer's choice is persisted to order meta as a structured log — the exact label shown, UTC timestamp, IP, user agent and checkout type — as legal evidence.
* Admin request log with full request details (custom post type), status views with counts, bulk status changes and sortable status column.
* Own WooCommerce order status *Withdrawal requested*, optionally applied to the order as soon as a declaration is received, so affected orders can be filtered, sorted and bulk-edited with the native tools.
* Optional refund account (IBAN) validated with the international control digits (ISO 7064 MOD-97-10).
* Optional double opt-in for guests: the request is held until the customer opens a single-use link sent to the email address on the order, while keeping the date and time of the original submission.
* Jurisdiction setting (EU baseline / Spain) that governs the statutory reference shown to the customer.
* REST API under `apg-withdrawal/v1` to list, read, create and progress requests from headless storefronts and back-office integrations.
* IP address and browser identifier storage options for legal evidence.
* Email notification to the store admin on every new request.
* Automatic customer acknowledgement email on submission.
* Customer status update emails when the request is accepted, rejected or completed.
* Automation: updates the withdrawal request status automatically when the linked WooCommerce order changes status.
* My Account integration: customers can view their withdrawal request history.
* CSV export of withdrawal requests, honouring the status currently selected in the list, with protection against spreadsheet formula injection.
* 100% compatible with HPOS (High-Performance Order Storage).
* Warning when WooCommerce 11.1's own order withdrawal feature is switched on at the same time, so the shop never offers customers two unconnected forms.
* Public form built to WCAG 2.1 AA — the level EN 301 549 requires today — with the WCAG 2.2 target-size criterion already met: grouped and labelled controls, live regions for dynamic notices and focus moved to each new step.
* One-click WooCommerce refund built from the request: the declared lines and quantities, capped by whatever is still refundable on the order. Always started by hand, never automatically. Works with Redsys and Bizum out of the box.
* WPML and Polylang ready, both through `wpml-config.xml` and through runtime string registration.

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

= WooCommerce 11.1 has its own withdrawal feature. Can I use both? =
You can, but you should not. WooCommerce 11.1 added *Order withdrawal* under WooCommerce → Settings → Advanced → Features, off by default. With both running, your customers reach two different forms at two different addresses, each keeping its own record and sending its own acknowledgement, and neither knows about the other: the same declaration can be registered twice and answered twice. The plugin detects the core feature and warns you, with a link to the screen where you turn it off.

The core feature covers the basics: a form, a review step, acknowledgement emails, matching to the order and a note on it. This plugin adds what a shop needs around that — withdrawal type per product and per category with inheritance and the exclusion notice on the product page, the Article 16(m) digital-content waiver at checkout with its structured log, the printable Annex I.B model form, the SHA-256 receipt hash for the burden of proof of Article 16a(8), the WooCommerce refund panel driven by the declared lines, the GDPR exporter, eraser and anonymisation, and the REST API. Pick one of the two and turn the other off.

= Can guest customers submit a withdrawal request? =
Yes. A logged-in customer gets their data pre-filled and picks from a dropdown of their own orders. A guest types the order number and the email address recorded on the order, which is the pair the plugin validates before accepting any declaration — the same proof WooCommerce asks for in its own order tracking. The plugin never lists a customer's orders to a visitor who has only supplied an email address.

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
* The order processing / completed emails (the plugin already injects the link there automatically via `woocommerce_email_after_order_table`, except on orders whose products are all excluded from the right of withdrawal).

= What happens if my store does not offer the withdrawal function? =
Article 11a of Directive 2011/83/EU has applied throughout the EU since 19 June 2026, regardless of whether each Member State has transposed it. Spain had not done so at the time of this release — the European Commission issued a reasoned opinion against Spain in June 2026 — but the obligation applies by direct effect, and non-compliance is penalised through the general regime of the TRLGDCU, whose fines reach 100,000 € for minor infringements, 1,000,000 € for serious ones and 4,000,000 € (or 4% of the turnover in Spain, whichever is higher) for very serious ones. Check the regime applicable in your own country: this note is informative and is not legal advice.

= Can I ask the customer for a bank account? =
Yes, in *WooCommerce → Withdrawal → Request handling → Account for the refund (IBAN)*, either as an optional or as a required field. It is disabled by default. The number is validated with the international control digits (ISO 7064 MOD-97-10) before being accepted, is stored alongside the request, is included in the privacy exporter and is deleted outright by the privacy eraser. Only enable it when you genuinely cannot refund through the original payment method.

= How do I stop somebody else from submitting a withdrawal in a customer's name? =
Enable *Confirm guest requests by email?*. A visitor who is not logged in then has their request held while a single-use link is sent to the address recorded on the order; only opening it registers the declaration. The request keeps the date and time of the original submission, so the customer never loses days of their withdrawal period. Logged-in customers are unaffected, since WordPress has already authenticated them.

= Is the plugin accessible? Does it help with the European Accessibility Act? =
The public form targets **WCAG 2.1 level AA**, which is what EN 301 549 — the standard the European Accessibility Act points to — requires today: the line-item selector is a labelled `fieldset` of native checkboxes and number inputs operable with a keyboard, dynamic notices are live regions, and focus moves to each new step of the flow. It also already satisfies the WCAG 2.2 target-size criterion (2.5.8), which arrives with version 4.1.1 of that standard, and the guest confirmation link is the pattern WCAG 2.2 recommends for accessible authentication (3.3.8), since it asks the customer for no cognitive test.

Two caveats. First, the Accessibility Act (Directive (EU) 2019/882, in Spain Law 11/2023) has applied to e-commerce **since 28 June 2025**, and it binds *the shop*, not a single plugin: colour contrast, focus visibility and page structure come from your theme, and the responsibility for the store as a whole is the merchant's. Second, companies with fewer than 10 employees **and** a turnover below 2 million euros are exempt from the obligations on services. Check your own situation: this note is informative and is not legal advice.

= Can I refund the customer from the withdrawal request? =
Yes. Each request shows a *Refund* panel with the amount the declaration implies — the declared lines at their declared quantities, plus the standard delivery cost when the withdrawal covers the whole contract, as Article 13(1) of Directive 2011/83/EU requires — always capped by whatever is still refundable on the order, so a refund you already issued by hand is never handed out twice. Nothing happens until you press the button. By default the refund is only *recorded* in WooCommerce, without moving money or touching stock; two checkboxes let you send it through the payment gateway (when the gateway supports it) and restock the returned products. The `apg_withdrawal_refund_plan` filter lets you adjust the figures, for instance to deduct the diminished value of returned goods.

= Does it work with Redsys and Bizum? =
Yes, and nothing extra needs installing. Bizum runs on Redsys for e-commerce, and the WooCommerce implementations of Redsys — José Conti's paid gateway, Codection's, the official unified module — issue refunds through the standard WooCommerce refund API. The refund panel uses that same API, so ticking *Send the money back through Redsys* reaches your virtual POS directly. The panel names the gateway it is about to use, so you always know where the money is going.

Three things worth knowing. Redsys accepts **both full and partial refunds**, so a partial withdrawal is returned as declared. **When the customer sees the money depends on their bank**, typically between 2 and 31 days — the 14-day limit in Article 13(1) of Directive 2011/83/EU applies to you *issuing* the refund, not to their bank crediting it. And if you use a Redsys gateway that does not implement refunds, such as the free Light edition, the panel says so and records the refund in WooCommerce without moving money, so you can issue it from your bank's back office and keep the accounting straight.

When a gateway rejects a refund, the reason it gives — the SIS code, in the case of Redsys — is shown in the error notice instead of a generic failure, so you have something to act on.

= Does the plugin have a REST API? =
Yes, under the `apg-withdrawal/v1` namespace. `GET /requests` and `GET /requests/<id>` list and read requests, and `PUT /requests/<id>` moves one through the workflow; all three require the `manage_woocommerce` capability. `POST /requests` registers a declaration coming from a headless storefront and is public, exactly like the public form: it goes through the same processor, so the order must match the email address, the rate limiter applies and the guest email verification kicks in when you enabled it.

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
= 0.7.4 =
* A withdrawal request already on file is no longer registered a second time when the customer submits the form more than once.
* An order already withdrawn from in full no longer accepts further requests, whether for the whole order again or for some of its products.
* The anti-spam field no longer drags the page sideways on right-to-left sites (Arabic, Hebrew, Persian).
* Orders whose products are all excluded from the right of withdrawal no longer carry the withdrawal link in their emails, with a new `apg_withdrawal_show_order_link` filter to override it.
* Compatibility with WooCommerce 11.2.1 verified.

= 0.7.3 =
* The Withdrawal tab of My Account now opens with its own heading, like every other account section, instead of starting straight into a notice. New `apg_withdrawal_account_title` filter to change it or remove it.
* The form now loads its stylesheet when it is inserted with the shortcode in a widget or a block template, where it used to ask for a handle that only exists in the admin and came out unstyled.
* Compatibility with WooCommerce 11.1.2 verified.

= 0.7.2 =
* The public form no longer exposes other people's orders: an email address in the page URL listed that customer's purchase history, and an order number on its own showed the contents and the total of any order.
* Withdrawal requests move to WooCommerce's order capabilities, so the Editor role can no longer read or change the consumer data they hold.
* The rate limiter can no longer be bypassed with a forged proxy header, and the review step now has a ceiling of its own.
* The review screen works again without JavaScript: it was printed through a filter that strips forms, leaving the customer nothing to submit.
* Quick Edit on a product category no longer resets its withdrawal type to "allowed".
* The bulk-action and refund result notices are signed by the redirect that emits them and verified before being shown.
* Output escaping and nonce verification reworked: the plugin now ships with no security exceptions marked on its output.
* Warning when WooCommerce 11.1's own order withdrawal feature is switched on at the same time, with a link to the screen where you turn it off, plus a new FAQ entry on what each of the two covers.
* Compatibility with WooCommerce 11.1 verified.

= 0.7.1 =
* Spanish translation reviewed against the wording approved on translate.wordpress.org: 27 strings corrected for terminology and current orthography — "artículo" in lower case as Spanish requires, "al finalizar la compra" instead of the anglicism "checkout", "servicio de envío" instead of "mailer", "solo" without the accent the RAE dropped. Since 0.6.1 the bundled translation takes precedence over the centralised language pack, so these corrections only reach the shop through a plugin update — which is what this release is for. The model withdrawal form of Annex I.B was deliberately left untouched, pending a check of its wording against Annex B of the TRLGDCU. No code changes.

= 0.7.0 =
* Partial withdrawal down to the unit. The "Specific products only" scope no longer works as an all-or-nothing selector per line: the form now renders one row per order line with a checkbox and a quantity input capped at the units originally ordered, so a customer who bought three units and is returning one declares exactly that. The units are stored in `_apg_withdrawal_quantities`, travel through the confirmation screen, and are shown in the acknowledgement email, the request detail screen and the CSV export as "Product x 1 of 3". They are also folded into the SHA-256 receipt hash as `item:units` pairs, so the digest covers the quantities and not just which lines were selected. Requests registered before this release keep working and are read as "all the units of the selected lines", which is what they meant.
* Withdrawal status workflow in the requests list. The post-status views ("All | Published"), which carried no meaning for this post type, are replaced with one link per withdrawal status showing its count, resolved in a single grouped query. The status column becomes sortable, a bulk action per status lets a batch of requests be moved through the workflow at once — going through `apg_withdrawal_change_status()`, so each one still gets its log entry and, when enabled, its customer notification — and the *Export CSV* button now exports the status currently on screen instead of always the whole log.
* Own WooCommerce order status `wc-apg-withdrawal` ("Withdrawal requested"), registered for both the legacy and the HPOS storage, available in the order editor dropdown, in the orders list views, as a bulk action and inside the plugin's own Automation mapping. A new setting, *Order status when a request is received*, optionally moves the linked order to any status — the new one or an existing one — as soon as a declaration is registered. Disabled by default, so updating changes nothing until you opt in.
* Optional refund account (IBAN) on the public form, configurable as hidden (default), optional or required. The number is normalised, checked against the ISO 13616 structure and the ISO 7064 MOD-97-10 control digits before being accepted, and surfaces in the confirmation screen, the acknowledgement email, the request detail screen and the CSV export. It is exposed by the privacy exporter and, unlike the other personal fields, is deleted outright rather than redacted by the eraser: a placeholder in an IBAN field would read as a malformed account number, and the account has no evidentiary value for the declaration itself.
* Optional double opt-in for guest declarations. The public form already refuses any order whose billing email does not match the address typed by the visitor, but for a guest checkout that pair may be known to somebody other than the consumer. With *Confirm guest requests by email?* enabled, a submission from a visitor who is not logged in is parked server-side and a single-use link is emailed to the address on the order; only opening it registers the declaration. Critically, the moment that counts legally is the one at which the consumer sent the form, so the original timestamp travels inside the parked payload and becomes the request's date and the timestamp covered by the receipt hash — the customer never loses days of their withdrawal period to their own inbox. Tokens are stored as SHA-256 digests, are single use and expire after 24 hours (filterable via `apg_withdrawal_verification_ttl`).
* Jurisdiction layer. A new *Jurisdiction* setting (`EU` baseline / `ES`) governs the statutory reference shown on the confirmation screen and in the acknowledgement email. Spain has not transposed Directive (EU) 2023/2673 yet, so both options currently apply the same requirements and the Spanish one additionally cites the TRLGDCU; the point of the layer is that every jurisdiction-specific branch goes through `apg_withdrawal_jurisdiction_supports()`, so the national extras can be switched on in one place once they reach the BOE instead of being retrofitted across the codebase. Both the jurisdiction and the feature matrix are filterable.
* REST API under the `apg-withdrawal/v1` namespace. `GET /requests` (paginated, filterable by status and order), `GET /requests/<id>` and `PUT /requests/<id>` require `manage_woocommerce`; `POST /requests` is public so decoupled storefronts can register a declaration, and routes through the very same processor as the public form — order/email match, rate limiting and, when enabled, guest email verification all apply, and a parked submission answers `202` with `pending_verification`. The refund account is only echoed back to a caller that can manage WooCommerce. A new `apg_withdrawal_request_registered` action fires once a declaration is fully registered.
* Refund the customer straight from the withdrawal request. Each request gains a *Refund* panel that works out what the declaration implies — the declared lines at their declared quantities, plus the standard delivery cost when the withdrawal covers the whole contract, as Article 13(1) of Directive 2011/83/EU requires — and caps every figure by what is still refundable on the order, so a refund issued by hand beforehand can never be duplicated. Nothing is ever refunded automatically: the merchant presses the button, and by default the refund is only recorded in WooCommerce without moving money or touching stock, with opt-in checkboxes to push it through the payment gateway (offered only when the gateway supports refunds) and to restock. Creating it requires the same capability WooCommerce itself asks for to refund an order, writes an order note and is filterable through `apg_withdrawal_refund_plan`.
* Redsys and Bizum reach the refund panel with no extra integration, because those gateways implement the standard WooCommerce refund API and the panel drives that API. The panel now names the gateway it is about to use instead of talking about an anonymous "payment gateway", spells out for Redsys and Bizum that partial refunds are accepted and that the customer's bank takes 2 to 31 days to credit the money — the Article 13(1) deadline binds the trader issuing the refund, not the bank crediting it — and, when the active gateway cannot refund programmatically (the free Redsys Light edition, for instance), says so and records the refund without moving money. Gateway detection is filterable through `apg_withdrawal_gateway_info`.
* A refund the gateway refuses now surfaces the reason the gateway gave — the SIS code, for Redsys — instead of a generic failure notice.
* Fixed: the printable Annex I.B model form left nowhere to actually write. The minimum height sat on the field container, which the caption already filled, so each rule ended up flush under its own label. The writing space now belongs to the ruled lines themselves and each field gets as many as its answer needs — three for the description of the goods and for the consumer's address, two for the signature, one for the dates and the name.
* Fixed: the Annex I.B page no longer opens the print dialog by itself. Auto-printing on load was both an unrequested context change and the root of a Safari bug where a cancelled print left the browser printing blank sheets from then on. Printing now always starts from the button, after the web fonts and one paint have settled. A repaint on `afterprint` — and on the print media query ceasing to match, because Safari is inconsistent about which of the two it emits when the dialog is dismissed — also fixes the page being left blank after closing the dialog. The public form's link is labelled "Open the model form" and announces that it opens in a new tab.
* Annex I.B printing polish: A4 page box with 16 mm margins, and captions kept on the same sheet as their ruled lines.
* Accessibility pass on the public form, targeting WCAG 2.1 AA. The line-item selector is now a labelled `fieldset` of native checkboxes and number inputs instead of a multi-select widget, so it is operable with a keyboard and announceable by screen readers; the dynamic notices became live regions (`role="alert"` for the order error, `role="status"` for the product warning); and focus is moved to the newly injected content after every AJAX step, which previously left it on a button that no longer existed (3.2.2 and 4.1.3). The line rows are also spaced so they already satisfy the WCAG 2.2 target-size criterion (2.5.8), which is not part of WCAG 2.1 AA but arrives with version 4.1.1 of EN 301 549 — the standard the European Accessibility Act points to, and which has bound e-commerce since 28 June 2025.
* `wpml-config.xml` shipped with the plugin, so WPML and Polylang pick up the six merchant-configurable strings and the withdrawal-type metas without any code running. The runtime `wpml_register_single_string` registration added in 0.6.0 stays in place for setups that read strings that way.
* Declared compatibility with WooCommerce 11.0.
* New FAQ entries on the penalty regime for not offering the withdrawal function, the refund account, the guest double opt-in and the REST API.

= 0.6.1 =
* Bundled translation now takes precedence over the centralised language pack: when the plugin ships a `.mo` (or `.l10n.php`) for the current locale under `/languages`, the `lang_dir_for_domain` filter returns that folder unconditionally, instead of only falling back to it when WordPress could not find anything in `wp-content/languages/plugins/`. Result: the locale shipped by the plugin (currently `es_ES`) is always rendered with the strings packaged in the current release, so new wording and changelog-driven copy adjustments reach users on the same day as the plugin update without waiting for translate.wordpress.org to regenerate its language pack. Locales for which the plugin does not bundle a translation continue to be loaded from the centralised system pack as before.

= 0.6.0 =
* Fixed: the plugin's bundled Spanish translation (and any future locale shipped under `/languages`) was not being loaded on sites where translate.wordpress.org had not yet generated a language pack. WordPress's just-in-time loader only inspects `wp-content/languages/plugins/`, so the `.mo` file shipped inside the plugin was ignored. The plugin now hooks the WordPress 6.6+ `lang_dir_for_domain` filter to return the bundled `/languages` folder when no language pack is available, without resorting to the WP.org-discouraged `load_plugin_textdomain()` call. On WordPress 6.5 and below the bundled translation will still not load until a language pack is published, but the plugin keeps working in the source language.
* WooCommerce 10.9 transactional-email log integration: the three `WC_Email` classes shipped by the plugin (customer acknowledgement, admin notification, status update) are picked up automatically by the new built-in `EmailLogger`. Each log entry is enriched via `woocommerce_email_log_context` with the withdrawal request id, scope and the SHA-256 receipt hash plus its UTC timestamp, so the log entry can be cross-checked against the email actually delivered to the customer. The filter is silently ignored on older WooCommerce versions.
* Article 11a label safety net: a new helper (`apg_withdrawal_label_is_ambiguous()`) flags withdrawal labels that fail the "unambiguous wording" requirement of Article 11a of Directive 2011/83/EU. Used in two places: (a) the configurable confirmation button label (`Settings → Button text`) now surfaces an admin warning if the merchant saves an ambiguous wording (e.g. "Contáctanos", "Gestionar pedido", "Volver"); the choice is not blocked, only flagged; (b) the `[apg_withdrawal_link]` shortcode and the matching `apg-withdrawal/link` Gutenberg block emit a `_doing_it_wrong()` notice (visible with `WP_DEBUG`) when an ambiguous custom `label` attribute is used. Lists of "passes" and "blacklist" terms are filterable via `apg_withdrawal_label_unambiguous_terms` and `apg_withdrawal_label_ambiguous_terms`.
* Annex I.B link redesign on the public form: replaced the single long inline link with a short descriptive sentence ("If you prefer, you can use the official model (Annex I.B).") plus a "Print" button styled with the native WooCommerce button classes. The button appends `?print=1` to the Annex I.B URL so the browser print dialog opens automatically on page load (the existing on-page Print button still works for users who reach the URL directly).
* Annex I.B addressee block polish: the merchant state and country are now rendered with their human-readable names (via `WC()->countries->get_countries()` and `get_states()`) instead of the raw `XX:YY` ISO code stored in `woocommerce_default_country`, each on its own paragraph. Every paragraph in the addressee block ends with a period, as expected in formal correspondence.
* WPML / Polylang integration for merchant-configurable strings: the confirmation button text, the digital-content waiver custom label and the four per-type exclusion notices are now registered with WPML's String Translation (Polylang ships a compatibility layer for the same `wpml_register_single_string` action) on `init`. When neither plugin is active the registration is a no-op and the original value is rendered unchanged.
* Rate limiting + honeypot on the public form: a new `apg_withdrawal_is_rate_limited()` helper throttles repeated submissions by IP + email pair (default policy: 5 attempts per 10 minutes, both filterable via `apg_withdrawal_rate_limit_max` and `apg_withdrawal_rate_limit_window`). A hidden honeypot field is rendered in both steps of the form and silently swallows automated submissions before any persistence happens. Client IP detection respects common reverse-proxy headers and is filterable via `apg_withdrawal_client_ip`.
* Verified Article 11a continuous visibility throughout the 14-day window: the My Account → Orders "Withdraw from the contract here" action is shown for every order whose withdrawal deadline is still open, regardless of the WooCommerce status, with the deadline source automatically falling back from `completed_date` to `created_date` for orders that have not yet been marked complete. No code change required.

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
= 0.7.4 =
* Clicking the confirmation button more than once registered one request per click. A repeated submission is now recognised and answered with the request already on file.

== Thanks ==
Thanks to everyone who uses the plugin, helps improve it, makes a donation or encourages us with their comments.

If you find this plugin useful, you can support its development with a [small donation](https://artprojectgroup.es/tienda/donacion).

== External Services ==
This plugin connects to the WordPress.org Plugins API to fetch information about the plugin (such as the rating). It sends the plugin slug when requesting data. More information: https://wordpress.org/about/privacy/
