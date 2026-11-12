=== TaxoCommerce Suppliers ===
Contributors: davidperez
Tags: woocommerce, suppliers, csv, inventory, prices
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.2.19
License: GPLv2 or later

CSV-only supplier catalogue management for the TaxoCommerce family.

== Description ==

* Admin header: plugin title, a single local `Description` with the static `Plugin URI` link below it (left); a wider collapsed `CHANGELOG.md` history (center); local version and release date (right). No duplicated information panel or external requests.

* The admin header shows the plugin's bundled `Description` and links to its `Plugin URI`, without network requests or caching. Version history uses the bundled `CHANGELOG.md`.



TaxoCommerce Suppliers owns the supplier intake and intermediate supplier catalogue workflow.

Included:
* CSV-only supplier imports.
* Reusable CSV mapping templates.
* Generic canonical CSV import format and price-only update format; no provider templates preinstalled.
* Compatibility with existing regional CSV feed parsing without preloading third-party supplier presets.
* Intermediate inventory with pending, review, accepted, and discarded decisions.
* WooCommerce product linking/creation on acceptance.
* Supplier price correction and optional WooCommerce price updates.
* External supplier image references stored without downloading to Media.
* Purchasing view that matches active WooCommerce sales to accepted supplier sources.
* Compatibility with the existing seo_proveedores_productos and seo_supplier_images tables.
* Public service taxocommerce_suppliers_get_sources() for purchasing and other family plugins.
* Full end-user Manual and technical developer reference with separate headings, indexed sections, operating tables, contracts and regression checklist in the Manual tab.
* Read-only, manually triggered Autotest of services, administration hooks, WooCommerce, settings and schema.

Explicitly excluded:
* Scraping.
* Crawlers.
* GitHub Actions runners.
* Cloud Run jobs.
* Automatic supplier website extraction.

== Changelog ==

= 0.2.17 =
* Local Description and Plugin URI replace REST excerpt requests and caches in the header. Issue #2494.


= 0.2.16 =
* Display the last verified official excerpt from the developer's Plugin URI during HTTP errors; avoid stale negative cache and show bundled author Description on first-install network failure. No dependency on customer content. Issue #2489.



= 0.2.15 =
* Three-zone responsive admin header, centered official developer information and own Markdown history; remove shop administrator URL configuration. Issue #2476.

= 0.2.11 =
* Added an optional, collapsed version history beneath the admin header title, reading only this plugin's bundled CHANGELOG.md. No new admin tab, Core dependency, data changes or external requests.


= 0.2.9 =
* Full Spanish, English, French and German gettext translations for Suppliers administration and both manuals; no supplier presets are reintroduced.

= 0.2.8 =
* No preloaded supplier-specific templates. Only locally saved mappings appear in CSV Templates, while generic CSV import formats and existing regional feed compatibility remain available.


= 0.2.7 =
* Native WordPress gettext localization aligned with Core (POT, PO, MO and user locale). Administrative labels, manual, purchasing and CSV fields prepared for translations.


= 0.2.6 =
* Collapsible CSV Templates form: import declarative JSON, CSV or TXT mapping definitions or paste their contents, persist mappings as reusable named templates, and export user-created mappings as JSON.
* File contents are treated as data only; rejects unknown fields, duplicate definitions and files over 64 KiB. No supplier products are imported during template creation.


= 0.2.5 =
* Pestaña homogénea «Autocheck» y descarga JSON del último informe, por usuario y sitio, con control de permisos y nonce.

= 0.2.4 =
* Cabecera administrativa uniforme de TaxoCommerce con nombre, icono y versión visible.
* Compatible sin Core; se conservan los controles, pestañas y permisos existentes.


= 0.2.3 =
* Verify POST nonces in their mutation handlers and sanitize numeric prices, CSV column mappings and supplier image URLs.
* Prepare the supplier-image table identifier with %i and document live custom-table reads and writes at their exact SQL statements.
* Preserve stable public TCSUP_ classes and bounded WooCommerce metadata lookups; add security regression assertions.

= 0.2.2 =
* Expand the integrated Manual into complete, separately indexed user and developer guides with real controls, side effects, architecture, schemas, hooks and test procedures.
* Preserve all existing operations and read-only Autotest.

= 0.2.1 =
* Add in-plugin Manual (user and technical) and read-only Autotest tabs.
* Preserve the existing six administration tabs and extend smoke coverage.

= 0.2.0 =
* Move WooCommerce sale-to-supplier purchasing from Logistics into Suppliers.
* Add Purchasing dashboard with supplier source, cost, purchase URL, and missing-source detection.

= 0.1.0 =
* Initial independent Suppliers module.

== Historical origins (monolith; not standalone releases) ==
* [Complete legacy and release history](https://github.com/distribuidordeherramientases-wq/seo-taxonomy/tree/main/taxocommerce-suppliers) (changelog.txt / CHANGELOG.md).

* Legacy origin: supplier catalogue recipes, CSV feeds, stock/availability imports and commercial supplier relations were maintained in TaxoCommerce SEO; legacy 2.2.x–2.4.1 are not Suppliers standalone versions.
* The monolith 2.2.0 cycle documented Supplier Import / Sync V2; 2.3.5–2.3.9 introduced successive supplier/inventory and catalogue import improvements.
* The original catalogue workflow used supplier data and an intermediate product inventory before WooCommerce publication, with availability and images kept attributable to their suppliers.
* Standalone Suppliers 0.1.0 established CSV-based supplier catalogue handling and integration with the family of modular plugins.
* Standalone 0.2.0 moved purchasing functions out of Logistics and into Suppliers, separating procurement from delivery and transport calculation.

== Local plugin information ==

The developer information panel shows only bundled `Description` metadata and an outbound `Plugin URI` link. No external request is made.
