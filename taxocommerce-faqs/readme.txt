=== TaxoCommerce FAQs ===
Contributors: davidperezmartorell
Tags: faq, woocommerce, questions, csv, vocabulary
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.18
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage product and page FAQs in WordPress and WooCommerce with CSV exchange, status reporting and semantic labels.

== Description ==

* Admin header: plugin title, a single local `Description` with the static `Plugin URI` link below it (left); a wider collapsed `CHANGELOG.md` history (center); local version and release date (right). No duplicated information panel or external requests.

* The admin header reads this plugin's local `Description` and links to `Plugin URI`, without external requests or caching. The version history is bundled in `CHANGELOG.md`.

* One collapsible version-history control, without a duplicate plugin-information box.


TaxoCommerce FAQs works with an existing canonical FAQ table on migration and optionally integrates its admin screens into TaxoCommerce Core.

* Manage FAQs attached to pages, hubs, WooCommerce product categories and products.
* Create, edit, enable and delete FAQ records individually.
* Review volume, publication status, destinations and read metrics.
* Export and import up to 500 FAQs per CSV file in UTF-8.
* Reuse existing compatible semantic-label assignments without creating duplicate taxonomies.

If TaxoCommerce Core is active, the FAQs tab is available within Core's Content area. Otherwise the module provides its own administration page. New FAQs require an existing WooCommerce product category. Older FAQs without a category remain visible for review; enabling the plugin does not automatically publish them.

== Installation ==

1. Install and activate TaxoCommerce FAQs on a supported WordPress site with the relevant WooCommerce categories.
2. On first activation, the plugin creates the prefixed `seo_faq` table if missing. Existing FAQ records are preserved.
3. Inspect your content and use a CSV export before large imports or migrating from the legacy SEO plugin.
4. Ensure only one active engine controls FAQ writes on migration.

== Frequently Asked Questions ==

= Does deactivation delete my FAQs? =

No. Deactivation and ordinary plugin updates preserve FAQ records.

= What happens on uninstall? =

**Warning:** Uninstalling via WordPress permanently removes the plugin's prefixed `seo_faq` table and the FAQ-type assignments in `seo_object_vocabulary`, even if those records are shared with the original TaxoCommerce SEO plugin. Export your FAQ data first. Other vocabulary masters, product/post assignments, product categories and the separate historical `seo_faq_metrics` table are not removed.

On multisite, the cleanup is limited to sites marked as initialized by this module. Removing files manually by FTP does not run `uninstall.php`.

== Changelog ==

= 0.1.16 =
* Local Description and Plugin URI replace REST excerpt requests and caches in the admin header. Issue #2494.


= 0.1.15 =
* Display the last verified official excerpt from the developer's Plugin URI during HTTP errors; avoid stale negative cache and show bundled author Description on first-install network failure. No dependency on customer content. Issue #2489.



= 0.1.14 =
* Three-zone responsive admin header, centered official developer information and own Markdown history; remove shop administrator URL configuration. Issue #2476.

= 0.1.10 =
* Added an optional, collapsed version history beneath the admin header title, reading only this plugin's bundled CHANGELOG.md. No new admin tab, Core dependency, data changes or external requests.


= 0.1.8 =
* Pestaña «Autocheck» con descarga JSON ya existente y conservación de las comparaciones antes/después.

= 0.1.7 =
* Cabecera administrativa común de TaxoCommerce con icono, descripción, versión visible y pestañas originales conservadas.
* Presentación autónoma, sin dependencia obligatoria de Core.


= 0.1.6 =
* Correct prepared SQL identifiers and validation in the FAQ editor, CSV and Vocabulary operations.
* Exclude development tests and FTP sync state from installed plugin, and clean obsolete files on staging.
* Retain all FAQ records and existing editor, Auto Test and Manual functionality.


= 0.1.5 =
* Add an in-plugin Auto Test tab with 25 read-only checks across six groups.
* Add a separate read-only Manual tab with eleven user-guide chapters and six technical chapters, following the family layout.
* Store before/after deployment baselines per user and site, compare regressions and export sanitized JSON.
* Never change FAQ rows, run migrations, or import/export files in diagnostic mode; live operations remain manual.


= 0.1.4 =
* Replace direct PHP CSV file handles with WordPress filesystem reads and streamed CSV encoding.
* Preserve quoted multiline FAQ answers during bounded CSV import.
* Keep current prepared SQL and English WordPress.org readme metadata.

= 0.1.3 =
* Standalone FAQ editor and WooCommerce category validation.
* CSV exchange and visibility/usage KPIs.

== Historical origins (monolith; not standalone releases) ==
* [Complete legacy and release history](https://github.com/distribuidordeherramientases-wq/seo-taxonomy/tree/main/taxocommerce-faqs) (changelog.txt / CHANGELOG.md).

* Legacy origin: frequently asked questions were collected and linked with products and categories in the TaxoCommerce SEO monolith; legacy releases 2.3.x–2.4.1 do not belong to the standalone FAQs version series.
* The monolith 2.3.6 release documented the FAQ subsystem and its relationship with catalogue content and the assistant's knowledge sources.
* Standalone 0.1.x extracted FAQ storage and administration while maintaining compatibility with existing seo_faq records and explicit content relationships.
* Later migrations protected existing data during installation/uninstallation, updated CSV handling and maintained the Assistant L6 compatibility path.
* Version 0.1.5 added a dedicated Auto Test and user/technical guide; 0.1.6 concentrated on Plugin Check and clean distribution.

== Local plugin information ==

The header shows `Description` once and the static `Plugin URI` link directly below it, without an HTTP request.
