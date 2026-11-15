=== TaxoCommerce Vocabulary ===
Contributors: davidperezmartorell
Tags: woocommerce, taxonomy, attributes, classification, csv
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.3.22
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Canonical semantic vocabulary, product characteristics and assisted classification for WooCommerce.

== Description ==

* Admin header: plugin title, a single local `Description` with the static `Plugin URI` link below it (left); a wider collapsed `CHANGELOG.md` history (center); local version and release date (right). No duplicated information panel or external requests.

* The admin header shows the plugin's bundled `Description` and links to its `Plugin URI`, without network requests or caching. Version history uses the bundled `CHANGELOG.md`.



TaxoCommerce Vocabulary helps site administrators maintain canonical labels, technical attributes and assignments to WooCommerce content.

* Uses canonical existing vocabulary, role-mapping and technical attribute storage on migrating installations.
* Provides a classifier with proposals based on product titles, descriptions, WooCommerce tags, brands, categories, SKU and optional supplier metadata; classification is not applied automatically.
* Recovers controlled term aliases and technical unit extraction from the original classifier, with reviewable source evidence, without a monolith dependency.
* Supports CSV management of semantic and attribute masters and portable JSON import/export of assignments.
* Mandatory import simulation and MERGE/MIRROR modes make bulk updates reviewable.
* Numeric source IDs are audit-only: portable references are resolved against the target site's real objects.
* Integrated Auto Test: 16 read-only and explicitly skipped checks, two results per user for 30 days, and comparison between runs.
* In-plugin Manual: separate user and technical guides for screens, data model, security, and migration risks.

== Installation ==

1. Install and activate TaxoCommerce Vocabulary alongside WooCommerce.
2. Check existing masters and assignments before changing anything.
3. Export a backup before running an import.
4. Simulate JSON imports, review unresolved objects and then approve intentional changes.

== Changelog ==

= 0.3.19 =
* Local Description and Plugin URI replace REST excerpt requests and caches in the header. Issue #2494.


= 0.3.18 =
* Display the last verified official excerpt from the developer's Plugin URI during HTTP errors; avoid stale negative cache and show bundled author Description on first-install network failure. No dependency on customer content. Issue #2489.



= 0.3.17 =
* Three-zone responsive admin header, centered official developer information and own Markdown history; remove shop administrator URL configuration. Issue #2476.

= 0.3.13 =
* Added an optional, collapsed version history beneath the admin header title, reading only this plugin's bundled CHANGELOG.md. No new admin tab, Core dependency, data changes or external requests.


= 0.3.10 =
* Extend the existing simulated MERGE/MIRROR portable JSON flow to WordPress posts and pages, including SEO landings represented as pages. Resolve by portable slug/path rather than source IDs; keep current product/category import contracts.

= 0.3.9 =
* Restore independent semantic extraction from product content, tags, brand and SKU, including technical units and controlled aliases. Preserve reviewable proposals and JSON; no automatic product changes.


= 0.3.8 =
* Nombre uniforme «Autocheck» y exportación JSON autenticada de los resultados con detalle por prueba.

= 0.3.7 =
* Cabecera administrativa uniforme de TaxoCommerce con nombre, icono y versión visible.
* Compatible sin Core; controles, pestañas y permisos intactos.


= 0.3.6 =
* Validate uploaded portable JSON metadata and repeat nonce verification at input readers.
* Document bounded taxonomy queries and scoped read-only admin URL nonce exceptions.
* Document narrowly scoped PHPCS exceptions for live custom-table queries without cross-plugin cache invalidation; keep all real SQL identifier preparation checks enabled.

= 0.3.5 =
* Prepare canonical vocabulary SQL identifiers with WordPress %i placeholders across admin, classifier, CSV and portable JSON import/export.
* Validate uploaded CSV metadata and sanitize classifier filters before use.
* Keep existing manual, Auto Test, data model and CSV/JSON behaviour; exclude tests and FTP metadata from the release ZIP.


= 0.3.4 =
* Add Auto Test tab: runtime, hooks, WooCommerce, MySQL, all nine canonical tables, bounded reads, classifier, portable interface and manual; destructive operations explicitly skipped.
* Keep two per-user self-test reports for 30 days with version, results and regression comparison.
* Add in-plugin user and technical manuals, preserving the five existing tabs and CSV/JSON behaviour.
* Add PHP 7.4/8.4 regression test and ensure development tests are excluded from release ZIP.


= 0.3.3 =
* Replace direct PHP filesystem access in CSV import/export with WordPress-compatible handling.
* Render import forms directly with escaped values instead of concatenating raw form markup.

= 0.3.2 =
* Portable semantic backups, object identity resolution and import simulation.
* MERGE/MIRROR assignment controls and fingerprinted exports.

= 0.3.0 =
* Canonical storage reuse, independent classifier and CSV master exchange.

== Historical origins (monolith; not standalone releases) ==
* [Complete legacy and release history](https://github.com/distribuidordeherramientases-wq/seo-taxonomy/tree/main/taxocommerce-vocabulary) (changelog.txt / CHANGELOG.md).

* Legacy origin: the monolith 2.2.1–2.2.8 cycles implemented the Vocabulary bridge, canonical product tags, semantic label editing and mapping between terms and catalogue records.
* The old SEO relationships and vocabulary tables were an input to taxonomy, category mapping, catalogue search and Dependiente semantic interpretation.
* Monolith 2.3.x evolved semantic classification and evidence-based relationships; vocabulary data had to remain stable while the plugins were separated.
* Standalone Vocabulary 0.3.x centralised semantic labels and catalogue relation administration with controlled import/export and integrity checks.
* Later standalone fixes tightened CSV upload limits and SQL identifier preparation, and resolved WordPress.org Plugin Check issues without inventing or recreating the historical taxonomy data.
* Vocabulary, Core and Assistant retain separate responsibilities: semantic terms, shared family administration and learning/search respectively.

== Local plugin information ==

The developer information panel shows only bundled `Description` metadata and an outbound `Plugin URI` link. No external request is made.
