=== TaxoCommerce Templates ===
Contributors: davidperezmartorell
Tags: templates, roles, pages, woocommerce
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.4.33
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Register WordPress and WooCommerce request templates, maintain SEO role assignments and audit existing PHP template associations.

== Description ==

* Admin header: plugin title, a single local `Description` with the static `Plugin URI` link below it (left); a wider collapsed `CHANGELOG.md` history (center); local version and release date (right). No duplicated information panel or external requests.

* The admin header shows the plugin's bundled `Description` and links to its `Plugin URI`, without network requests or caching. Version history uses the bundled `CHANGELOG.md`.



Version 0.4.21 adds a guarded, opt-in SEO content reconciliation tab: compare native WooCommerce category descriptions against the optional legacy SEO table and synchronize selected, unchanged rows after an administrator nonce check. It never runs automatically.


TaxoCommerce Templates is a standalone request-template routing and management plugin for WordPress and WooCommerce. It supports three areas:

* Request types: WordPress front page, posts, pages, archives, taxonomies, search, 404, custom post types and registered public taxonomies, as well as classic WooCommerce shop/cart/checkout/account/order pages and selected native WooCommerce PHP template hooks.
* Editorial roles: retain cluster, primary and secondary hub, landing and mail-template assignments using compatible `seo_nodes`, `seo_templates` and `seo_mail_templates` records. Mobile and desktop variants belong to one primary assignment.
* Template library: inspect built-in source code read-only; create, duplicate, download and edit custom PHP templates; upload PHP files subject to syntax checking and a 1 MiB limit; back up custom files before replacement.

Custom templates are stored in a separate WordPress content library rather than the plugin folder. Built-in source files are never overwritten from the editor.

**Important:** PHP source upload and execution are advanced trusted-administrator features that may not meet the security and policy requirements for new plugins distributed through the public WordPress.org directory. This version is a private migration and staging-validation build, **not an approved public-directory release**. Do not submit it to WordPress.org until the executable PHP editing feature has been removed, split off or explicitly accepted by the directory reviewers.

== Installation ==

1. Install and activate on a test WordPress installation; TaxoCommerce Core is not required.
2. Inspect all request types and imported associations in the audit area.
3. Leave the new template routing engine disabled until existing templates and dependencies are validated in STAGING.
4. Verify that the old routing engine is not active for the same request during migration.
5. Enable only individual tested request assignments. A syntactically valid PHP file may still cause runtime failures.

== Security ==

The PHP template editor requires administrator permissions and WordPress permission to edit PHP/plugin code. Validate every uploaded file and keep hosting backups. Library files are stored outside the plugin directory under `wp-content/taxocommerce-templates-library`; review web-server execution restrictions and filesystem permissions carefully. Never permit untrusted users to upload or edit executable PHP.

AJAX/JSON endpoints and WooCommerce action callbacks are not themselves request templates; this module must not insert HTML into JSON responses. Block-based `wp_template` content should be edited with the native WordPress Site Editor.

== Migration and Compatibility ==

The plugin preserves existing role/request records and copies compatible historic template resources without directly loading the old monolith's PHP files. It does not reassign page IDs or automatically alter existing `seo_nodes` relations. Pilot-specific international VEVOR template routes remain with their existing owner until explicitly migrated.

The association-audit panel compares old and new file locations, roles without templates, missing desktop/mobile variants and mail templates, and exports a CSV inventory. A file inventory or PHP lint pass does not establish visual or functional equivalence.

== Developer Notes ==

Use `tct_request_definitions` to extend named request definitions and `tct_request_candidates` to customise frontend request resolution precedence. Request types not using `template_include` or the native WooCommerce template hooks need their own dedicated integration.

== Manuals ==

Open the Manual tab in Templates to read two integrated guides without executing diagnostics or changing settings:

* User manual: safe policy activation, route assignment, filters, countries, desktop/mobile drafts, trusted PHP library, existing SEO roles, migration audit and voluntary Autocheck.
* Technical manual: class/file map, tables and options, hooks and routing precedence, security, WordPress/WooCommerce fallbacks, plugin integration, QA, maintenance and troubleshooting.

Both guides are read-only, administrator-only, and ship inside the standalone plugin. Editing PHP remains a trusted-administrator capability and is not made safer by reading a manual.

== Changelog ==

= 0.4.30 =
* Local Description and Plugin URI replace REST excerpt requests and caches in the header. Issue #2494.


= 0.4.29 =
* Display the last verified official excerpt from the developer's Plugin URI during HTTP errors; avoid stale negative cache and show bundled author Description on first-install network failure. No dependency on customer content. Issue #2489.



= 0.4.28 =
* Three-zone responsive admin header, centered official developer information and own Markdown history; remove shop administrator URL configuration. Issue #2476.

= 0.4.25 =
* Country-scoped cookie-policy and consent-preferences links in the Spanish shared footer, VEVOR international footer and international cluster footer.
* Route the five country-specific WordPress policy pages to a matching legal page shell; show public policy links only after publication. Preserve the Insights consent manager and never modify the visitor's decision.


= 0.4.23 =
* Added an optional, collapsed version history beneath the admin header title, reading only this plugin's bundled CHANGELOG.md. No new admin tab, Core dependency, data changes or external requests.


= 0.4.19 =
* For long native document titles, omit the redundant site name while retaining post/category names. Provide descriptive ALT text for informative homepage image links on desktop and mobile.

= 0.4.18 =
* Avoid duplicated SEO meta descriptions in TaxoCommerce public templates; preserve the first external SEO plugin description and provide a contextual fallback only if none is emitted. Keep a single canonical link.

= 0.4.16 =
* Fix English/Spanish admin localization and compiled gettext catalogs; preserve Core-style loading on init.

= 0.4.15 =
* Second partial Plugin Check remediation: prepared role and audit SQL identifiers, scalar guards for admin input. Remaining warnings and WordPress.org editor blocker still pending.

= 0.4.14 =
* Initial targeted review of Plugin Check warnings: prepare table identifiers in key SQL routes and audit queries, validate editor input types, and version the international cluster stylesheet.
* Further security, caching and performance warnings remain under issue #2189; this is not an approved WordPress.org release.


= 0.4.13 =
* Prepare SQL table identifiers with WordPress %i placeholders across 16 template and compatibility files, addressing 32 Plugin Check SQL errors without changing output or assignments. Add regression and PHP lint checks.
* WordPress.org release remains blocked pending the full review and separation of private executable-PHP editing.

= 0.4.12 =
* Fix 12 filesystem Plugin Check errors using WordPress filesystem APIs while preserving editing, imports, downloads and CSV exports. The executable PHP editor remains private-only and blocks WordPress.org publication.

= 0.4.11 =
* Add the integrated Manual tab with detailed User Manual and Technical Manual, anchored contents, troubleshooting and a read-only permissioned smoke test.
* Document existing behaviour only; no public template filters, stored route assignments or business operations changed.


= 0.4.10 =
* Nueva pestaña «Autocheck» independiente: comprueba clases, rutas, tablas y políticas sin cambiar plantillas; exporta JSON autenticado.

= 0.4.9 =
* Cabecera común de administración basada en TaxoCommerce Core, sin dependencia de Core.
* Se conservan pestañas, permisos y procesos existentes.


= 0.4.0 =
* Standalone request and WooCommerce template routing.
* Existing editorial role and assignment compatibility.
* Independent custom PHP template library, variants and audit reports.

== Historical origins (monolith; not standalone releases) ==
* Legacy origin: the WordPress/WooCommerce theme templates, content-role routing, cluster/hub page presentation and product/category layouts were developed within TaxoCommerce SEO; old monolith releases are not Templates versions.
* The monolith 2.3.x–2.4.0 line included frontend content integration, editorial roles, product/category information blocks, menu assignments and canonical content relationships.
* Standalone Templates evolved into an independent template library and editor with explicit assignment and compatibility checks for existing WordPress pages.
* International storefront work introduced country-specific presentation for Spain, United States, Germany, Netherlands and France, with separate menus and country metadata.
* Follow-up work aligned the United States template with the shared international layout and investigated header menu fallback where raw categories appeared instead of configured menus.
* WordPress.org preparation addressed translation strings and template/FAQ rendering, while private PHP editing remained a controlled administrator-only capability.
* Version 0.4.0 is the current standalone series; earlier monolith milestones must not be represented as fabricated 0.x Templates releases.

== Local plugin information ==

The developer information panel shows only bundled `Description` metadata and an outbound `Plugin URI` link. No external request is made.
