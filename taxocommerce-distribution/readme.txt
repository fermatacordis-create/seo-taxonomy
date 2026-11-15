=== TaxoCommerce Distribution - Feeds & Sitemaps for WooCommerce ===
Contributors: davidperezmartorell
Tags: woocommerce, merchant, sitemap, product-feed, bing
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 0.1.20
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Publish WooCommerce commercial feeds and professional XML sitemaps with bounded background processing.

== Description ==

* Admin header: plugin title, a single local `Description` with the static `Plugin URI` link below it (left); a wider collapsed `CHANGELOG.md` history (center); local version and release date (right). No duplicated information panel or external requests.

* An administrator checkbox in Distribution > Sitemaps enables or relinquishes TaxoCommerce XML sitemap publication. With it disabled, native WordPress or another plugin can publish sitemaps; commercial feeds are unaffected. The existing WordPress filter continues to support third-party ownership.

* The admin header shows the plugin's bundled `Description` and links to its `Plugin URI`, without network requests or caching. Version history uses the bundled `CHANGELOG.md`.



TaxoCommerce Distribution separates technical catalog publication from marketing, templates and supplier imports.

Distribution retains the proven production behavior of the previous TaxoCommerce SEO modules:

* Google Merchant Center XML feed.
* Microsoft Merchant Center / Bing Shopping feed.
* Pinterest Catalogs CSV feed.
* Universal CSV and JSON catalogs.
* Adaptive, cursor-based feed generation in bounded worker windows.
* Atomic feed publication: partial files never replace the last valid feed.
* Product, product-category, post, page, cluster and hub XML sitemaps.
* All sitemap groups are generated asynchronously in bounded 250-item windows (products use an ID cursor). 
* Independent five-speed load controller (Suave/Baja/Normal/Alta/Máxima) with PHP CPU/memory/time back-pressure, progress, stop and recovery.
* XML validation before publication.
* Atomic sitemap publication and stable root-domain URLs.
* Automatic sitemap regeneration after catalog/content changes and a daily review using WP-Cron, when Distribution owns the endpoint.
* Safe coexistence mode with the legacy monolithic plugin during migration.
* Two full, independently structured guides within the Manual tab: a step-by-step User Manual and a Developer Technical Manual with code map, lifecycle, hooks, options, security, migration, tests, and safe extension procedure.
* On-demand, read-only Autocheck of components, registrations, configuration and local publication files, without regeneration, background jobs or network calls.

== Compatibility ==

Public filenames and storage remain compatible with the existing installation:

* google-merchant.xml
* microsoft-merchant.txt
* pinterest-catalog.csv
* catalogo-universal.csv
* catalogo-universal.json
* /sitemap.xml and its child sitemap files

When the legacy plugin is active, it retains scheduled ownership to avoid duplicate workers. Manual generation from Distribution remains available for parity testing. Once the legacy owner is removed, Distribution schedules its own feeds and sitemap updates.

== Language ==

English is the base source and UI language. Future localization should use standard WordPress gettext translations without changing public feed filenames or compatibility keys.

== Installation ==

1. Install WooCommerce and activate TaxoCommerce Distribution.
2. Open TaxoCommerce Core > Distribution, or Distribution in the WordPress menu without Core.
3. Review Overview and configure channels in Commercial Feeds before generating any inventory.
4. Review Sitemaps and Automation; during coexistence with the legacy plugin, avoid duplicate scheduled writers.
5. Open Manual for user and technical instructions. Run Autocheck to validate availability without generating files or using external services.
6. Validate staging feeds before registering production URLs with Google, Microsoft or Pinterest.

== Changelog ==

= 0.1.16 =
* Local Description and Plugin URI replace REST excerpt requests and caches in the header. Issue #2494.


= 0.1.15 =
* Display the last verified official excerpt from the developer's Plugin URI during HTTP errors; avoid stale negative cache and show bundled author Description on first-install network failure. No dependency on customer content. Issue #2489.



= 0.1.14 =
* Three-zone responsive admin header, centered official developer information and own Markdown history; remove shop administrator URL configuration. Issue #2476.

= 0.1.10 =
* Added an optional, collapsed version history beneath the admin header title, reading only this plugin's bundled CHANGELOG.md. No new admin tab, Core dependency, data changes or external requests.


= 0.1.8 =
* Schedule a daily sitemap rebuild using the existing incremental 250-item worker. Avoid duplicate scheduled jobs and skip this schedule when a legacy sitemap provider owns the endpoint. WordPress-Cron requires site traffic or an external cron trigger.

= 0.1.7 =
* Spanish admin translations for Distribution, including commercial feeds and native WordPress gettext files.
* Remove the redundant Overview tab and retain its read-only operational indicators below the shared header.
* Keep existing feeds, sitemap generators, manual Autocheck and legacy Overview URLs working.


= 0.1.6 =
* Resolve five official Plugin Check errors: add translator guidance for sitemap progress and escape exception messages and filenames.
* Remove a redundant error_log call; sitemap errors remain available in the saved generation state.
* Keep feed and sitemap generation, publication, schedules and Autocheck unchanged.

= 0.1.5 =
* Unifica «Autocheck» y permite descargar JSON autenticado de los resultados de la última ejecución, sin modificar servicios ni contenidos.

= 0.1.4 =
* Separate the last successfully published sitemap date from the generation start and latest heartbeat.
* Publish generation-scoped child XML files before replacing the validated root index so the previous index never references partially replaced children.
* Detect WP-Cron schedule failures and keep the last valid public sitemaps available. Existing in-progress jobs resume with safe child names.
* Report only already-published XML entries during regeneration and include regression checks for both date and publication safety.


= 0.1.3 =
* Cabecera administrativa homogénea con el diseño de Core, versión visible e integración autónoma sin depender de Core.
* Added visible plugin version to the admin header.
* Added a five-speed independent sitemap workload regulator, progress and stop controls.
* Replaced synchronous sitemap generation with restartable 250-item background windows; validated XML is published only after generation, and the main index is replaced last.
* Preserved public sitemap routes and legacy coexistence. No production deployment included.

= 0.1.2 =
* Expanded the integrated Manual into two clearly separate, indexed and responsive documents: User Manual and Developer Technical Manual.
* Added a developer file/function responsibility matrix, generation lifecycle, WordPress options and hook references, coexistence, security and maintenance checklist.
* Clarified safe user workflows for existing feeds and sitemaps without changing operational logic.

= 0.1.1 =
* Added internal Manual with user and technical instructions for existing Distribution functions.
* Added grouped Autocheck, manually executed with permissions and nonce; checks read-only availability of feeds, sitemaps, worker hooks, settings and published files.
* Added isolated no-write regression test. Development tests remain outside the distributable ZIP.

= 0.1.0 =
* Initial modular migration of commercial feeds and sitemap generation.
* Added independent bounded feed worker.
* Added safe legacy coexistence and settings migration.
* Added Distribution admin tabs for overview, feeds, sitemaps and automation.

== Historical origins (monolith; not standalone releases) ==
* [Complete legacy and release history](https://github.com/distribuidordeherramientases-wq/seo-taxonomy/tree/main/taxocommerce-distribution) (changelog.txt / CHANGELOG.md).

* Legacy origin: sitemap output and commercial product feeds were developed in TaxoCommerce SEO before the Distribution 0.1.0 extraction; previous SEO releases are not Distribution versions.
* The monolith 2.3.7 cycle documented commercial feeds alongside catalogue, market observations and Google Shopping integrations.
* Distribution 0.1.0 separated feed generation and sitemap responsibilities into an independent module while keeping established content/catalogue relationships.
* Modular compatibility work retained a fallback for Core role data so distribution does not require the legacy monolith at runtime.
* Follow-up work addressed WordPress.org translation comments and package validation; these are maintenance changes to the same 0.1.0 line, not claimed additional releases.

== Local plugin information ==

The developer information panel shows only bundled `Description` metadata and an outbound `Plugin URI` link. No external request is made.
