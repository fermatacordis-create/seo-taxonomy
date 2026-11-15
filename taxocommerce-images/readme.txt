=== TaxoCommerce Images - Image Management for WooCommerce ===
Contributors: davidperezmartorell
Tags: images, media, thumbnails, optimization, webp
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.2.30
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Image management for WordPress and WooCommerce with Media as the canonical source of truth.

== Description ==

* Admin header: plugin title, a single local `Description` with the static `Plugin URI` link below it (left); a wider collapsed `CHANGELOG.md` history (center); local version and release date (right). No duplicated information panel or external requests.

* The admin header shows the plugin's bundled `Description` and links to its `Plugin URI`, without network requests or caching. Version history uses the bundled `CHANGELOG.md`.

* One collapsible version-history control, without a duplicate plugin-information box.


TaxoCommerce Images is an independent member of the TaxoCommerce family.

Core principles:

* WordPress Media is the source of truth.
* No custom image tables or supplier image indexes are required.
* WordPress and WooCommerce native APIs remain authoritative.

Current functionality includes:

* Media dashboard.
* On-demand read-only Auto Test, 17 checks, user-specific temporary report (no new tables).
* Bounded image audit in batches.
* Reports for missing ALT text, unattached images, missing physical files, oversized dimensions/bytes, missing registered thumbnails and generic titles.
* Product-without-image KPI when WooCommerce is active.
* Metadata editing: title, ALT, caption and description.
* Registered thumbnail-size inventory.
* Thumbnail regeneration.
* Reversible original-image resizing with a local backup.
* Upload scaling and image quality settings.
* Optional integration into TaxoCommerce Core.

== Installation ==

1. Upload the TaxoCommerce Images ZIP from Plugins > Add New > Upload Plugin, or install it from WordPress.org after approval.
2. Activate the standalone plugin in Plugins. WooCommerce and TaxoCommerce Core are optional integrations.
3. Open the Images administration menu to review the Media inventory and run the read-only Auto Test.
4. Before starting real image transformations, inspect the workload settings and back up the WordPress Media library.
5. The read-only unused/duplicate scan does not require WP-Cron; it uses signed local HTTP requests. Keep the admin tab open if the host blocks those loopbacks. Other background tools may still require a hosting cron.

== Background execution ==

* The read-only unused/duplicate Media scan now uses a signed, bounded local HTTP worker without WordPress Cron, with an authenticated admin-tab fallback for hosts that reject internal requests. It retains the persisted cursor and adaptive workload regulation.
* For cleanup reporting, expensive references across products, posts and metadata are checked once per bounded 512-image window rather than again for each 1-5-image hashing batch. Each new scan invalidates its cache; suspected unused or duplicate Media are never deleted automatically.
* Other image tasks retain their existing persisted WordPress Cron workers.
* For other tasks, WordPress Cron requires site visits unless a hosting cron invokes wp-cron.php. The unused/duplicate scan is independent of WP-Cron but uninterrupted unattended execution still depends on successful local loopback requests. No Action Scheduler is required.
* Each task stores its own cursor, state, owner, errors and last heartbeat. Administrators can pause, resume or stop from Images without leaving the tab open.
* The reported CPU percentage is limited to the PHP request; it is not the server-wide CPU utilization.

== Changelog ==

= 0.2.29 =
* Fixed the complete unused/duplicate Media scan: safe server-side continuation without WP-Cron, browser fallback if the host blocks loopbacks, bounded reusable reference checks, persistent cursor and no automatic deletion. Issue #2580.

= 0.2.26 =
* Local Description and Plugin URI replace REST excerpt requests and caches in the header. Issue #2494.


= 0.2.25 =
* Display the last verified official excerpt from the developer's Plugin URI during HTTP errors; avoid stale negative cache and show bundled author Description on first-install network failure. No dependency on customer content. Issue #2489.



= 0.2.24 =
* Three-zone responsive admin header, centered official developer information and own Markdown history; remove shop administrator URL configuration. Issue #2476.

= 0.2.20 =
* Added an optional, collapsed version history beneath the admin header title, reading only this plugin's bundled CHANGELOG.md. No new admin tab, Core dependency, data changes or external requests.


= 0.2.17 =
* Nombre uniforme «Autocheck» y exportación JSON autenticada del último diagnóstico, sin ejecutar operaciones de escritura o procesos comerciales.

= 0.2.16 =
* Cabecera administrativa común de TaxoCommerce con icono, descripción, versión visible y pestañas originales conservadas.
* Presentación autónoma, sin dependencia obligatoria de Core.


= 0.2.15 =
* Updated the prefixes of 64 internal legacy helper functions and their callers to avoid namespace collisions.
* Preserved four previous WebP and optimizer filter hooks for third-party compatibility, adding new fully prefixed alternatives.
* Removed the costly post ID exclusion from duplicate lookup and kept protection against retrying the same attachment.
* Reviewed and documented the live and atomic SQL operations used for Media reference auditing, conversion rollback, cursor control and safe locks; these operations must not reuse stale cached results.
* Added WordPress.org installation instructions without removing image-management functionality.

= 0.2.14 =
* Correct plugin packaging: exclude developer tests and FTP synchronization metadata from installed plugin.
* Sanitize submitted workload priority and explicitly verify nonces in the two image-assignment form handlers.
* No changes to image processing, audits, WebP transformations or background jobs.

= 0.2.13 =
* Add read-only Manual tab with comprehensive administrator and developer documentation, headings and anchor index.
* Preserve all existing image processing actions, tabs, workers, permissions, and Media behavior.

= 0.2.12 =
* Added an Auto Test tab with 17 independent image-management checks, the Core-style traffic-light display and a temporary per-user result.
* Checks do not mutate images, jobs, attachments or image metadata.

= 0.2.11 =
* Added server-side persistent background jobs for audit, labels, duplicates, assignment, optimizer and WebP.
* Preserved the 1-5 workload regulation and locking; browser is not responsible for chaining batches.
* Added independent state polling, pause, resume and stop controls.
* No FTP or production deployment is implied by the GitHub change.


= 0.2.10 =
* Media audit in Images > Anomalías has a dedicated five-speed adaptive load regulator, independent from assignment, labels and cleanup jobs.
* Automatically sizes batches from 25 to 1200 attachments depending on the selected speed and observed time, request-local CPU and memory pressure; no longer fixed at 75.
* The audit keeps its scan cursor and existing findings when the administrator changes speed. No attachments are modified or removed.
* With the audit tab open, a sequential AJAX continuation processes the next batch immediately. Pause and resume controls apply to this browser tab; the manual Next batch action remains available.
* Live progress shows processed images, last batch size and duration, next batch goal, throughput and PHP load. Host-wide CPU is not estimated by the PHP worker.
* Existing per-attachment anomaly checks remain unchanged.

= 0.2.9 =
* Changed unused/duplicate analysis and labels/attributes review into full-library jobs that continue automatically until the complete Media snapshot is scanned.
* Kept adaptive 1-5 CPU/memory regulation as an internal batching mechanism only; batch size no longer reads as the scope of the report.
* Added explicit KPI/report counts for total/scanned images, used/unused media, exact SHA-256 duplicates, duplicate keepers, missing ALT, titles to review, dimensions, missing files and review cases.
* Added report filters for actionable findings, unused images, duplicate groups, used images, review cases and all rows.
* Duplicate detection remains exact original-file SHA-256 comparison; names, dimensions and file sizes alone never classify a duplicate.

= 0.2.8 =
* Added independent adaptive workload control for bounded local image scans.
* Added request-local CPU, memory and query observations without hosting-level CPU dependencies.
* Kept Images workload priority independent from Dependiente and other modules.

= 0.2.7 =
* Added explicit WordPress Media selection for individual image assignment.
* Preserved the rule that products never borrow another product's image automatically.

= 0.2.5 =
* Removed the hosting-dependent CPU checker and /proc/stat gating.
* Long-running audits use bounded batches, persistent cursors, request pacing and mutation locks.
* Image anomaly, label/attribute and unused/duplicate scans register in TaxoCommerce Core Processes.

= 0.2.4 =
* Previous CPU checker release; superseded by 0.2.5 because hosting-level CPU metrics are not portable from WordPress.


= 0.2.3 =
* Products only reuse their own gallery; missing product images stay reported.
* 25-row reports, full KPIs, and resumable cursors past unresolved rows.
* Replaced HTTP inspection with local ALT, title and dimensions review.
* Full-library duplicate/unused scan, persistent results and exact original hashes.
* No new permanent JPG backups or administrator history.
* Automatic deletion/consolidation is not enabled; cleanup remains read-only.


= 0.2.1 =
* Four separately paginated reports: Pages, Posts, Categories and Products without assigned images.
* Each candidate is restricted to an actual category relation: first its category thumbnail, then local Media belonging to products in that category.
* Assign a selected Media image individually or apply the first valid category candidate automatically to up to 25 missing items.
* Upload a local image through WordPress Media and associate it with one chosen item.
* Preserve the existing images and avoid all supplier-image catalogs, remote imports and random global Media suggestions.
* Works standalone, with WooCommerce categories and products when that plugin is active.


= 0.2.0 =
* Restored isolated image reference audit from the historical plugin (review only; no deletion).
* Restored legacy recompression of originals and thumbnails, in small administrator-triggered batches.
* Restored JPG/PNG-to-WebP conversion and redirects for known internal legacy links.
* Added future-thumbnail exclusion settings, without removing existing thumbnails.
* Added bounded HTTP availability checks for images linked from published pages, posts and products.
* Removed all supplier-image indexing and provider image inventory from Images.
* This release must pass independent WordPress Plugin Check and staging validation before distribution.


= 0.1.3 =
* Read-only Media audit now uses bounded, resumable ID cursors.
* Detect missing physical thumbnail files and missing dimensions metadata.
* Keep unattached images distinct from images actually unused.
* A bounded AJAX continuation runs the audit automatically, with manual fallback.


= 0.1.1 =
* Removed external URL / Media import from the Images extension.
* Media import/export is delegated to TaxoCommerce Import-Export.
* Images now focuses only on audit, metadata, thumbnails and optimization.

= 0.1.0 =
* Initial modular pilot.

== Historical origins (monolith; not standalone releases) ==
* [Complete legacy and release history](https://github.com/distribuidordeherramientases-wq/seo-taxonomy/tree/main/taxocommerce-images) (changelog.txt / CHANGELOG.md).

* TaxoCommerce SEO 2.3.x linked supplier image imports, catalogue records and Media metadata; the 2.4.1 monolith version is not an Images release.
* Images 0.2.0 extracted WebP, optimisation, image references, HTTP checks and thumbnails. Media ZIP import/export moved to Import-Export.
* Later standalone versions added category-based image assignment, external-provider distinctions, inventory audits, adaptive background batches and Auto Test.

== Local plugin information ==

The developer information panel shows only bundled `Description` metadata and an outbound `Plugin URI` link. No external request is made.
