=== TaxoCommerce Campaigns ===
Contributors: davidperezmartorell
Tags: woocommerce, marketing, campaigns, pricing, offers
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.13
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WooCommerce product offer campaigns, banners and an independent commercial calendar.

== Description ==

* The admin header shows the plugin's bundled `Description` and links to its `Plugin URI`, without network requests or caching. Version history uses the bundled `CHANGELOG.md`.



TaxoCommerce Campaigns manages product promotions and scheduled price campaigns with conflict checks. It includes campaign creation/editing, product assignment, price application and restoration, CSV imports/exports, promotional creatives and a commercial calendar.

* Reuses the existing seo_marketing_campaigns and seo_marketing_campaign_products tables where available.
* Supports public [taxocommerce_campaign] and [oferta] shortcodes.
* Can share approved social publishing connections with the optional TaxoCommerce Social plugin.
* Keeps existing compatible creative assets during migration.

Two campaign engines must not edit or schedule the same campaign simultaneously; only one should own campaign apply/restore jobs.

== Installation ==

1. Install WooCommerce and TaxoCommerce Campaigns.
2. Open Campaigns in WordPress administration and create a campaign for test products.
3. Verify planned prices, dates, overlap handling and restoration in a test environment.
4. Enable automated campaign schedules only after checking no other plugin owns these jobs.

== Changelog ==

= 0.1.12 =
* Local Description and Plugin URI replace REST excerpt requests and caches in the header. Issue #2494.


= 0.1.11 =
* Display the last verified official excerpt from the developer's Plugin URI during HTTP errors; avoid stale negative cache and show bundled author Description on first-install network failure. No dependency on customer content. Issue #2489.



= 0.1.10 =
* Three-zone responsive admin header, centered official developer information and own Markdown history; remove shop administrator URL configuration. Issue #2476.

= 0.1.6 =
* Added an optional, collapsed version history beneath the admin header title, reading only this plugin's bundled CHANGELOG.md. No new admin tab, Core dependency, data changes or external requests.


= 0.1.4 =
* Localized marketing campaign navigation and administration; complete ES, EN, FR, DE and NL gettext catalogues (151 messages per locale).


= 0.1.3 =
* Nombre uniforme «Autocheck» y descarga autenticada del último informe JSON, con resultados por comprobación y sin ejecutar operaciones reales.

= 0.1.2 =
* Cabecera administrativa uniforme de TaxoCommerce con nombre, icono y versión visible.
* Compatible sin Core; se conservan los controles, pestañas y permisos existentes.


= 0.1.1 =
* Corrected Plugin Check SQL identifier placeholders and sanitized admin inputs.
* Preserved campaign pricing, calendar operations and legacy API compatibility.
* Included standalone User Manual and read-only Autotest documentation.

= 0.1.0 =
* Extracted campaigns, product offers and their commercial calendar into an independent module.
* Retained canonical campaign records and compatibility with existing public template hooks.

== Historical origins (monolith; not standalone releases) ==
* [Complete legacy and release history](https://github.com/distribuidordeherramientases-wq/seo-taxonomy/tree/main/taxocommerce-campaigns) (changelog.txt / CHANGELOG.md).

* Historical source: Marketing, scheduled commercial actions and social-network reporting existed within TaxoCommerce SEO 2.3.x; this is monolith history, not a separate Campaigns version.
* The monolith's 2.3.7–2.3.9 cycles documented commercial campaigns, marketing reports, scheduling and integrations with social channels.
* Standalone 0.1.0 separated campaigns, calendar scheduling and banner administration from the SEO monolith without making Social responsible for campaign business rules.
* The extraction kept explicit campaign configuration and staged publication; it did not imply that every external provider or campaign had been activated.
* WordPress Plugin Check remediation and standalone documentation were incorporated in release 0.1.1.

== Local plugin information ==

The developer information panel shows only bundled `Description` metadata and an outbound `Plugin URI` link. No external request is made.
