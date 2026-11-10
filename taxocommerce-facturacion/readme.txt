=== TaxoCommerce Billing ===
Contributors: davidperezmartorell
Tags: woocommerce, invoices, proforma, quotes, pdf
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.13
License: MIT

WooCommerce invoices, proformas and quotations migrated from TaxoCommerce SEO.

== Description ==

* The admin header shows the plugin's bundled `Description` and links to its `Plugin URI`, without network requests or caching. Version history uses the bundled `CHANGELOG.md`.



TaxoCommerce Billing keeps the existing document workflow while moving it into an independent plugin.

Features:

* Company and tax settings.
* Invoices and proforma invoices.
* Cart quotations for classic Cart and Cart Block.
* Order document registry and snapshots.
* Document numbering and series.
* PDF generation using the same Dompdf 3.1.6 engine as the historical module.
* Email attachments and resending.
* WooCommerce order metabox and customer documents.
* Integrated user and technical Manual on the Billing settings screen.
* On-demand, read-only Autotest to check module availability, saved settings, document table, PDF loader and WooCommerce hooks without issuing documents or sending emails.

The plugin reuses the existing seo_facturas_* options and seo_factura_documents table.
It does not create a parallel document database and does not include a destructive uninstall routine.

== Installation ==

1. Install and activate WooCommerce and TaxoCommerce Billing.
2. Open TaxoCommerce > Billing when TaxoCommerce Core is active (otherwise use the Billing menu).
3. Review Datos empresa and fiscal settings, then enable the document system only after verifying company details and document series.
4. Configure invoices, proformas and cart quotations as required. Review the integrated Manual and run Autotest before using real customer orders.
5. Confirm PDF generation and permitted customer downloads in a staging environment before live billing.

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
* Unifica «Autocheck» y permite descargar JSON autenticado de los resultados de la última ejecución, sin modificar servicios ni contenidos.

= 0.1.3 =
* Cabecera administrativa uniforme de TaxoCommerce con nombre, icono y versión visible.
* Compatible sin Core; controles, pestañas y permisos intactos.


= 0.1.2 =
* Billing administration now has a unique route, separate from the monolithic plugin, with eight visible tabs including Manual and Autotest.
* Preserved existing invoice, proforma and quotation functionality while removing duplicate legacy navigation from the independent screen.
* Added an isolated routing regression test covering coexistence with the legacy controller.

= 0.1.1 =
* Added in-plugin user and technical manuals for Billing administration and WooCommerce integrations.
* Added a read-only Autotest, restricted to administrators, without document generation, database modification or outgoing requests.
* Added isolated regression coverage; developer tests remain excluded from release ZIPs.

= 0.1.0 =
* Migrated the existing document module into an independent TaxoCommerce plugin.

== Historical origins (monolith; not standalone releases) ==
* [Complete legacy and release history](https://github.com/distribuidordeherramientases-wq/seo-taxonomy/tree/main/taxocommerce-facturacion) (changelog.txt / CHANGELOG.md).

* Historical source: document generation, invoices and quotations were maintained in the TaxoCommerce SEO monolith before the standalone Billing module; this section is not a Billing release.
* The legacy implementation used seo_facturas_* options and the seo_factura_documents table; the standalone extraction retained those stores instead of resetting customer documents.
* Earlier checkout and tax corrections avoided adding VAT twice to retail prices already expressed with tax included.
* The original document enable/disable control was restored after an earlier regression; billing behavior depends on those explicit administrative settings.
* Standalone 0.1.0 was a one-to-one extraction of the legacy billing/document functions, keeping existing options and document data rather than redesigning accounting.

== Local plugin information ==

The developer information panel shows only bundled `Description` metadata and an outbound `Plugin URI` link. No external request is made.
