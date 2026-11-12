=== TaxoCommerce Import-Export - Import & Export for WooCommerce ===
Contributors: davidperezmartorell
Tags: woocommerce, csv, import, export, media
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 0.4.29
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Import and export catalog, content and Media for WordPress/WooCommerce.

== Description ==

* CSV batch imports and exports continue through private internal HTTP requests with adaptive load limiting. This plugin no longer creates its own Action Scheduler or WP-Cron jobs. If a loopback fails, recovery requires a later WordPress request or an explicit administrator retry.

* Admin header: plugin title, a single local `Description` with the static `Plugin URI` link below it (left); a wider collapsed `CHANGELOG.md` history (center); local version and release date (right). No duplicated information panel or external requests.

* The admin header shows the plugin's bundled `Description` and links to its `Plugin URI`, without network requests or caching. Version history uses the bundled `CHANGELOG.md`.



TaxoCommerce Import-Export is an independent member of the TaxoCommerce family.

Version 0.4.1 includes:

* Dedicated Exportación tab with resumable block-based CSV generation.
* Selectable exports for full products, reduced portable product inventory, product categories, posts and pages.
* Product export filters for status, Cluster, primary Hub, secondary Hub and multiple product categories.
* Reduced portable inventory with identity, hierarchy, WooCommerce tags/attributes, brand, Vocabulary and canonical SEO attributes; excludes long content, prices, stock, supplier and images.
* Export jobs resume after reloading the admin screen and expose retry, cancel and authenticated download controls.
* Products CSV import/export.
* Safe partial correction CSV imports: only present columns are updated.
* Automatic delimiter detection and safe product-type inference for correction CSVs.
* Brand/provider round-trip fields for products.
* Product categories CSV import/export, including the optional seo_excerpt term metadata and portable parent_slug.
* Native WordPress post categories (taxonomy category) CSV import/export, with name, slug, parent, portable parent_slug, optional additional excerpt (seo_excerpt termmeta), native term description, and country_code. Works for one-row individual CSVs and multi-file batch queues; categories are independent of product_cat.\n* Posts CSV import/export.
* Pages CSV import/export.
* Media image ZIP export with original files, metadata and relationships.
* Media image ZIP import into real WordPress attachments.
* Featured-image, WooCommerce gallery and product-category image relationship restoration.
* WordPress-generated thumbnails are rebuilt on import instead of transported.
* Optional integration into TaxoCommerce Core.
* Sequential multi-file batch queue with natural filename ordering.
* Independent bounded worker owned by Import-Export, without the legacy central process manager.
* Pause after current file, safe stop/resume from byte offset, imported/failed buckets and JSON sidecar diagnostics.
* Extensible mini-importer registry so FAQs, Redirects, suppliers or other modules can register their own CSV handlers.

== Media package ==

A Media export contains:

* manifest.json
* media.csv
* files/<old-attachment-id>/<original-file>

The package transports original image files plus title, ALT, caption, description, post_parent and known image relationships.

== Changelog ==

= 0.4.26 =
* Local Description and Plugin URI replace REST excerpt requests and caches in the header. Issue #2494.


= 0.4.25 =
* Display the last verified official excerpt from the developer's Plugin URI during HTTP errors; avoid stale negative cache and show bundled author Description on first-install network failure. No dependency on customer content. Issue #2489.



= 0.4.24 =
* Three-zone responsive admin header, centered official developer information and own Markdown history; remove shop administrator URL configuration. Issue #2476.

= 0.4.21 =
* Runtime gettext fallback to source labels if a stale or third-party language pack contains empty strings. ES/EN/FR/DE/NL included, no data changes.


= 0.4.20 =
* Rebuild ES/EN/FR/DE/NL MO catalogs excluding empty translations, restoring readable export type, filters and action buttons. Preserve real translations and Pages CSV export behavior.
* Add CI check for non-empty MO entries and Pages selector. No catalog data or WordPress content is changed.


= 0.4.19 =
* Added an optional, collapsed version history beneath the admin header title, reading only this plugin's bundled CHANGELOG.md. No new admin tab, Core dependency, data changes or external requests.


= 0.4.17 =
* Corrección de cancelación de exportaciones antiguas sin token; limpieza segura con bloqueo exclusivo y recuperación del estado al consultar progreso.
* El siguiente lote se despacha después de liberar el bloqueo de escritura, evitando paradas provocadas por una autollamada demasiado temprana.

= 0.4.16 =
* Exportaciones CSV procesadas en segundo plano en servidor mediante autollamadas internas del servidor, con Action Scheduler/WP-Cron para recuperación, incluso si el navegador se cierra. La pantalla solo consulta progreso.
* Mantiene cinco velocidades, regulación adaptativa, trabajo por bloques, recuperación, cancelación y descarga segura.
* Para procesar sin visitas en instalaciones sin cron activo, configurar el cron del alojamiento que invoque wp-cron.php.

= 0.4.15 =
* Autocheck manual unificado con dos acciones: «Ejecutar Autocheck» y «Descargar resultados (JSON)» del último informe.
* Eliminada la interfaz y el almacenamiento nuevo ANTES/DESPUÉS y los botones por grupo; se mantienen los 35 casos de prueba, sus estados, fecha de ejecución y protección por usuario y sitio.
* Eliminada la cabecera interior duplicada, manteniendo la cabecera común TaxoCommerce y las pestañas actuales.
* Los informes históricos no se borran; el último informe previo sigue disponible.


= 0.4.14 =
* Pestaña «Autocheck» con descarga JSON ya existente, conservación de controles de seguridad y de comparaciones de regresiones.

= 0.4.13 =
* Cabecera administrativa unificada de TaxoCommerce con nombre, icono y versión.
* Estilos propios sin Core, funciones y permisos intactos.


= 0.4.12 =
* Add native WordPress post-category CSV export and import (single CSV and multi-file batch), separately from WooCommerce product categories.
* Include additional term excerpt, native HTML description and portable parent slug; resolve target terms by slug before IDs to avoid collisions between stores.
* Extend WooCommerce category exports with backward-compatible appended excerpt and parent_slug columns, preserving historic CSV schemas and existing imports.
* Add independent WordPress-category filters, Auto Test coverage and regression tests. No activation, deletion or production deployment.

= 0.4.11 =
* Correct two internationalized diagnostic strings with translator guidance and sanitize the failed-file name before nonce/path validation.
* Document justified indexed taxonomy and metadata lookups used to preserve existing export filters.


= 0.4.10 =
* Add a read-only Manual tab with user and technical documentation matching TaxoCommerce family conventions.
* Keep the existing Auto Test name and checks unchanged; cover manual routing, access and content in CI.

= 0.4.9 =
* Add Auto Test tab with independent, non-destructive checks for CSV, country fields, queued/individual imports, export jobs, Media ZIP, workload profiles and security.
* Save a per-user and per-site baseline before deployment, compare after deployment by check ID, and export sanitized JSON results.
* Explicitly mark end-to-end imports, real Media restoration, downloads and background execution as manual tests, without touching catalogue or active queue.

= 0.4.8 =
* The batch importer now displays a read-only progress bar for the CSV currently being processed, based on its saved byte offset and actual file size, with the bytes left and number of CSVs pending.
* The progress bar updates on each existing AJAX response. No extra worker, extra row queries, cursor reset or import scheduling changes are introduced.
* The UI distinguishes file bytes read from products actually created or updated. A byte-based estimate cannot guarantee that the processed-row counter is correct.

= 0.4.7 =
* Allows deleting an individual pending CSV while another file is processing. A brief, atomic filesystem mutex protects the claim-to-processing transition without locking the whole WooCommerce worker. The active file remains untouched and queue state remains consistent.
* Avoids WC_Product::save() on unchanged existing products while still updating country/supplier metadata, without skipping saves for new or modified products.
* Defers WordPress taxonomy recounts for each product block and flushes in a finally block; never disables WooCommerce hooks or product lookup synchronization.
* Records actual lookup, save and metadata SQL queries/time per batch, including skipped unchanged saves, for diagnosing slow imports. x10 row-size targets remain but do not imply x10 throughput.
* Adds filesystem regression for deletion during another worker, mutex races and queue stability, plus product no-op smoke.

= 0.4.6 =
* Added a throttled watchdog for active queues that have not advanced for 90 seconds; attempts a safe loopback re-dispatch from normal WordPress requests without an open admin tab.
* Stored Unix heartbeat times independently of the dashboard timezone and exposed an explicit stalled-queue message.
* File uploads must still be in the private import queue and explicitly started; no arbitrary server files are imported automatically.

= 0.4.5 =
* Add country_code to complete/reduced products, categories, posts and pages CSV exports.
* Import country_code (also pais_origen) and persist _seo_pais_origen; old CSVs retain their current assignments.
* Blank exported countries remain blank (no assumed ES assignment), with strict ES/US/FR/DE/NL validation on import.
* Importador CSV: escala x10 de los tamaños de lote en las cinco velocidades, tanto tamaño inicial como máximo, exclusivamente para la importación. Los tamaños de exportación no cambian.
* En trabajos ya iniciados, convierte el objetivo anterior de lotes (p. ej. 5->50, 6->60) sin borrar offsets. Los siguientes lotes pueden duplicar filas cuando completan dentro del presupuesto de tiempo y memoria (50->100->200).
* Ventana de ejecución por petición: hasta 25 segundos por defecto, siempre con margen respecto del límite PHP, y objetivos configurables de hasta 2000 filas en velocidad máxima. Un único trabajador; sin paralelismo inseguro.
* El tamaño se reduce si la memoria PHP está alta o cuando la duración real de los guardados de WooCommerce lo requiere. El porcentaje de CPU de PHP no representa la CPU global del servidor.
* Información adicional por lote: filas objetivo y reales, duración, tiempo medio de guardado y consultas SQL por fila. Aumentar el tamaño objetivo no implica que se multiplique por diez la velocidad de guardado de cada fila.

= 0.4.4 =
* Increase CSV batch targets with adaptive sizing and measurable save/SQL diagnostics.

= 0.4.3 =
* Pending CSV list includes a delete action for each queued file, with confirmation, exact-file nonce, capability check, and safe path validation.
* A delete is refused while the queue worker holds the lock or once the file has entered processing. Processing, imported and failed CSVs remain untouched.
* Deleting an individually queued CSV also updates its status; deleting the last pending file marks an otherwise empty active queue idle.

= 0.4.2 =
* CSV imports: each successful block immediately dispatches a new signed local HTTP request; Action Scheduler and WP-Cron are recovery/watchdog paths, not pacing dependencies.
* Browser-side AJAX assistance drives the queue while the admin is open; live rows/sec, duration, next block and adaptation reason.
* Adaptive regulator starts with 10 rows at level 3, grows/shrinks according to measured duration and memory, does not interpret PHP-request CPU usage as global server pressure.
* Individual CSV import starts immediately after upload; explicitly queued/paused files still require the administrator to press Start.
* If the hosting blocks internal loopback and WordPress cron is disabled, unattended execution cannot be guaranteed without a real server cron.

= 0.4.1 =
* Product CSV export resumes after interruptions by truncating incomplete blocks back to the last persisted byte offset, preventing duplicate rows.
* Export progress now distinguishes examined records, written rows, skipped products and completed blocks.
* Product export supports scheduled (`future`) status alongside publish, draft, pending, private and trash.
* Safely rejects in-flight jobs created before the new byte-checkpoint format instead of corrupting the CSV.

= 0.4.0 =
* Added a dedicated Exportación tab.
* Added resumable AJAX export jobs processed in bounded blocks.
* Added full and reduced portable product export modes.
* Added product hierarchy/category/status filters using modular Core metadata.
* Added progress, retry, cancel and authenticated completed-file downloads.
* Kept the reduced inventory intentionally non-importable by the normal round-trip importer.

= 0.3.0 =
* Migrated the sequential multi-file CSV queue into the modular Import-Export plugin.
* Added the Importación por lotes tab with multi-upload, start, pause, stop/resume and manual next-block controls.
* Added an independent bounded Action Scheduler/WP-Cron worker with no dependency on the legacy central process manager.
* Added built-in mini-importers for products, product categories, posts and pages.
* Added extension hooks for other TaxoCommerce plugins to register detectors and mini-importers.
* Added imported/failed file buckets and JSON sidecar diagnostics.

= 0.2.0 =
* Added Media image ZIP import/export.
* Moved Media transport responsibility out of TaxoCommerce Images.
* Avoided early translation calls during Core registration.

= 0.1.1 =
* Core family integration pilot.

= 0.1.0 =
* Initial CSV import/export pilot.

== Historical origins (monolith; not standalone releases) ==
* [Complete legacy and release history](https://github.com/distribuidordeherramientases-wq/seo-taxonomy/tree/main/taxocommerce-import-export) (changelog.txt / CHANGELOG.md).

* Monolith: Supplier Import / Sync V2 (2.2.0); supplier CSV/XLSX catalogues (2.3.x); VEVOR CSV and trash-aware content (2.4.0).
* Standalone: CSV pilot (0.1.x), Media ZIP (0.2.0), multi-file queue (0.3.0), then 0.4.x resumable exports, country codes, adaptive batches and Auto Test.

== Local plugin information ==

The developer information panel shows only bundled `Description` metadata and an outbound `Plugin URI` link. No external request is made.
