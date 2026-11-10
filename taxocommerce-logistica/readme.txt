=== TaxoCommerce Logistics ===
Contributors: davidperezmartorell
Tags: woocommerce, logistics, shipping, weight, orders
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.2.17
License: MIT

Manage shipping costs, package weight and dimensions in WooCommerce with manual rules and shipment audits.

== Description ==

* The admin header shows the plugin's bundled `Description` and links to its `Plugin URI`, without network requests or caching. Version history uses the bundled `CHANGELOG.md`.



TaxoCommerce Logistics is the shipping-cost domain for the TaxoCommerce family.

Features:

* Active WooCommerce order weight audit.
* Missing product-weight detection.
* Shipping method and charged shipping-cost visibility.
* Shipping rules by region, order amount, weight, dimensions, and volume.
* Fixed, per-kg, and per-unit shipping cost formulas.
* JSON import and export for shipping rules.
* WooCommerce shipping rate integration.
* Optional integration with TaxoCommerce Core.

The plugin reuses the historical seo_transporte_costes_settings option, so existing shipping rules remain intact.
It retains shipping as its core function. Its optional Supplier purchases screen displays read-only supplier references and external purchase links supplied by TaxoCommerce Suppliers; purchasing itself remains outside Logistics.

== Languages ==

The WordPress administration locale automatically selects the gettext language. Bundled Spanish (es_ES), English (en_US), French (fr_FR), German (de_DE) and Dutch (nl_NL) PO/MO files cover every string in the current translation template. This does not translate store products, orders or editorial content.

== Changelog ==

= 0.2.16 =
* Local Description and Plugin URI replace REST excerpt requests and caches in the header. Issue #2494.


= 0.2.15 =
* Display the last verified official excerpt from the developer's Plugin URI during HTTP errors; avoid stale negative cache and show bundled author Description on first-install network failure. No dependency on customer content. Issue #2489.



= 0.2.14 =
* Three-zone responsive admin header, centered official developer information and own Markdown history; remove shop administrator URL configuration. Issue #2476.

= 0.2.11 =
* Translate the 23 newly introduced supplier purchasing UI labels, notices and hints in all five bundled languages.
* Preserve independent shipping operation, optional Suppliers integration, order data and existing checkout behavior.
* Regenerate gettext MO files and extend the CLI regression test for compiled catalogs. Issue #2062.


= 0.2.10 =
* Internationalize shipping-rule forms and diagnostics in ES/EN/FR/DE/NL without altering supplier purchases.


= 0.2.9 =
* Collapsed history directly under the admin title, reading the bundled CHANGELOG.md.


= 0.2.8 =
* Complete the current Logistics gettext catalog for five languages, including shipping rules, audit guidance, permissions, documentation and status messages.
* Regenerate the five MO catalogs and add a read-only regression test for compiled translations. No changes to shipping rates or WooCommerce checkout.

= 0.2.6 =
* Pestaña «Autocheck» con descarga JSON ya existente, conservación de controles de seguridad y de comparaciones de regresiones.

= 0.2.5 =
* Cabecera administrativa uniforme de TaxoCommerce con nombre, icono y versión visible.
* Compatible sin Core; controles, pestañas y permisos intactos.


= 0.2.4 =
* Integrate the Logistics menu into TaxoCommerce Core when installed, keeping the standalone fallback when Core is absent.
* Preserve the Shipping Rules, Auto Test and Manual tabs and the existing shipping-option storage.
* Remove the redundant separate WordPress sidebar entry while Core is active.


= 0.2.3 =
* Fix escaped output of list tags in the user and technical manuals.
* Standardize the built-in documentation tab as Manual, with Manual de usuario and Manual técnico, preserving its existing URLs.
* Keep Auto Test with 35 read-only checks and before/after comparisons.


= 0.2.2 =
* Add User Guide tab with an indexed User Manual (10 chapters) and Technical Manual (11 chapters), using the same layout as the TaxoCommerce family.
* Explain real shipping regions, fees, rule priorities, missing dimensions, order audits, JSON configuration backups and the before/after Auto Test.
* Keep documentation read-only: it never loads order audits, calculates live shipping, changes settings or imports files.


= 0.2.1 =
* Add separate Auto Test tab with 35 read-only checks in eight functional areas and pre/post deployment comparison.
* Check WooCommerce shipping hooks, rates, region mapping, weight/dimensions, fixed/variable/free cost rules, saved settings and import/export controllers without changing real orders or shipping rules.
* Store BEFORE and latest diagnostics by site and administrator; export JSON, detect new failures and lost check coverage.
* Mark checkout, tax, real imports and cache tests as unverified until run against staging.


= 0.2.0 =
* Move sale-to-supplier purchasing to TaxoCommerce Suppliers.
* Keep Logistics focused on order weights and shipping costs.
* Add active-order shipping weight and charged-cost audit.

= 0.1.0 =
* Migrated the existing Order Management and Shipping modules into an independent TaxoCommerce plugin.

== Historical origins (monolith; not standalone releases) ==
* [Complete legacy and release history](https://github.com/distribuidordeherramientases-wq/seo-taxonomy/tree/main/taxocommerce-logistica) (changelog.txt / CHANGELOG.md).

* Legacy origin: shipping, transport-rate settings and WooCommerce checkout integration were originally functions of the TaxoCommerce SEO monolith; former monolith versions are not Logistics releases.
* The original implementation stored transport configuration in seo_transporte_costes_settings; extracting Logistics preserved existing shipping rules.
* The independent plugin was created by a one-to-one migration together with Billing, with original shipping logic retained rather than replaced.
* Supplier purchasing operations were moved from Logistics into Suppliers so shipping rules and procurement have separate owners.
* Later standalone releases added grouped Auto Test checks, before/after comparison, user/technical manuals, and integration into the Core family menu without claiming independent checkout behavior.

== Local plugin information ==

The developer information panel shows only bundled `Description` metadata and an outbound `Plugin URI` link. No external request is made.
