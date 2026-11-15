=== TaxoCommerce Database ===
Contributors: davidperezmartorell
Tags: woocommerce, mysql, database, backup, seo
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.4.21
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Standalone migration of the original Clean Database and SEO Data Table tools from TaxoCommerce SEO.

== Description ==

* Admin header: plugin title, a single local `Description` with the static `Plugin URI` link below it (left); a wider collapsed `CHANGELOG.md` history (center); local version and release date (right). No duplicated information panel or external requests.

* The admin header shows the plugin's bundled `Description` and links to its `Plugin URI`, without network requests or caching. Version history uses the bundled `CHANGELOG.md`.

* One collapsible version-history control, without a duplicate plugin-information box.


Version 0.4.5 preserves the two original database tools:

* Clean Database: Data Errors, Database Cleanup and a unified Export tab that combines complete WordPress SQL backups with SEO-table CSV/SQL/ZIP exports.
* Validation and repair of SEO nodes, relationships, scopes, categories and bulk supplier acceptance locks.
* All 17 original WordPress, WooCommerce and Action Scheduler cleanup actions, without a total deletion limit of 100 records or typed confirmation words. Cleanup remains manual and synchronous as in the original plugin.
* Full WordPress database SQL backup and SEO-only SQL backup.
* SEO Data Table: SEO table inventory, searchable and sortable explorer, per-table CSV/SQL exports and ZIP archives of all SEO tables in CSV, SQL or both.

The only deliberate omission is the cross-plugin Data Layer and its operation history and rollback features. SEO table repairs use standalone SQL transactions. No other plugin needs this module in order to record its database changes. Backups provide the recovery mechanism.

The newer, additional MySQL diagnostics, replacement table explorer, optimization, CSV importer, separate backups and maintenance services have been removed. The original administrator permissions, nonce verification and normal delete confirmation dialogs remain.

== Installation ==

1. Install and activate TaxoCommerce Database.
2. Open Database in its own menu, or under TaxoCommerce Core if Core is active.
3. Use the original Clean Database and SEO Data Table tools.
4. Download an SQL backup before any irreversible cleanup operation.

== Changelog ==

= 0.4.18 =
* Local Description and Plugin URI replace REST excerpt requests and caches in the header. Issue #2494.


= 0.4.17 =
* Display the last verified official excerpt from the developer's Plugin URI during HTTP errors; avoid stale negative cache and show bundled author Description on first-install network failure. No dependency on customer content. Issue #2489.



= 0.4.16 =
* Three-zone responsive admin header, centered official developer information and own Markdown history; remove shop administrator URL configuration. Issue #2476.

= 0.4.12 =
* Added an optional, collapsed version history beneath the admin header title, reading only this plugin's bundled CHANGELOG.md. No new admin tab, Core dependency, data changes or external requests.


= 0.4.8 =
* Merge the two admin export tabs into one while retaining full-site SQL, SEO-only SQL, per-table CSV/SQL and bundled SEO CSV/SQL/ZIP downloads.
* Keep existing export routes, security checks and old bookmarked export-tab URLs compatible. No changes to export engines, stored data, or backup formats.


= 0.4.7 =
* Nombre uniforme «Autocheck» y descarga autenticada del último informe JSON, con resultados por comprobación y sin ejecutar operaciones reales.

= 0.4.6 =
* Cabecera administrativa uniforme de TaxoCommerce con nombre, icono y versión visible.
* Compatible sin Core; se conservan los controles, pestañas y permisos existentes.


= 0.4.5 =
* Fixed Auto Test false positives: an empty complete backup is a failure; absent SEO tables are reported as not executed instead of success.
* Improved the 17-cleanup-action regression contract and documented the Auto Test in the user/developer manuals.
* No changes to cleanup, repair, export engines or SQL operations.

= 0.4.4 =
* Use prepared SQL identifier placeholders for critical Database queries and fix the dynamic-role prepared statement.
* Verify nonce in the object-reset action handler; keep all original cleanups, exports and Auto Test.

= 0.4.3 =
* Added read-only Database Auto Test: 17 independent checks of original services, MySQL connectivity, SEO schemas, read-only sampling, export availability, hooks, and developer manual.
* Per-user last two reports (30-day expiration), showing changes between before/after deployments. No destructive SQL or real export downloads.

= 0.4.2 =
* Add a seventh Manual tab with complete user and developer documentation, readable in WordPress admin.
* Document all existing buttons and cleanup actions, file architecture, hooks, SQL data model, limitations and extension testing.
* Show the six original screens in one horizontal responsive tab bar, preserving all existing routes and actions.
* Restored the two original database services and removed duplicated or non-original tools.
* Kept the original diagnostics, repairs, cleanup actions and data exports.
* Excluded the cross-plugin Data Layer and historical rollback support.

= 0.4.1 =
* Organise the six restored database panels into one navigation bar.

= 0.4.0 =
* Restore Clean Database and SEO Data Table from the monolith.

= 0.3.0 =
* Restore the original three Clean Database panels and legacy maintenance checks.

= 0.2.0 =
* Introduce standalone database diagnostics and optional maintenance controls.

== Historical origins (monolith; not standalone releases) ==
* [Complete legacy and release history](https://github.com/distribuidordeherramientases-wq/seo-taxonomy/tree/main/taxocommerce-database) (changelog.txt / CHANGELOG.md).

* Legacy origin: Clean Database and SEO Data Table were administration tools of TaxoCommerce SEO before being extracted; the monolith release identifiers 2.2.x–2.4.1 are not Database plugin versions.
* The original clean-up workflow inspected persisted SEO, catalogue and maintenance data and required explicit administrator actions for destructive operations.
* The SQL/data-table explorer and original cleanup checks formed two distinct functional areas; their historic behavior and existing tables were preserved during extraction.
* The standalone migration was corrected to restore complete legacy Clean Database and SEO Data Table functions instead of replacing them with a simplified Data Layer design.
* Later standalone releases brought six sibling tabs, documentation, 17 grouped Auto Test checks and a before/after baseline without removing the legacy cleanup actions.

== Local plugin information ==

The developer information panel shows only bundled `Description` metadata and an outbound `Plugin URI` link. No external request is made.
