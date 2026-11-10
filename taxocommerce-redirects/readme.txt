=== TaxoCommerce Redirects - SEO Redirect Manager ===
Contributors: davidperezmartorell
Tags: redirects, redirection, seo, woocommerce, delete
Requires at least: 6.5
Requires PHP: 7.4
Tested up to: 7.1
Stable tag: 0.1.18
License: GPLv2 or later

Manage SEO redirects and assisted content deletion for WordPress and WooCommerce.

== Description ==

* The admin header reads this plugin's local `Description` and links to `Plugin URI`, without external requests or caching. The version history is bundled in `CHANGELOG.md`.

* Collapsible local plugin information beside the independent version history.


Independent WordPress/WooCommerce 301/302 manager with assisted deletion.

== Installation ==

Install and activate the plugin. Export existing redirect rules before uninstalling or replacing another redirect manager. When the former plugin is active, do not enable two handlers for the same rule.

== Standalone or combined with Core ==

* WordPress-only: posts and pages.
* When WooCommerce exists: native products and product_cat categories.
* When TaxoCommerce Core exists: four additional delete buttons are inserted
  into Core product, category, post and page editors/lists without modifying Core.
* No requires-plugin declaration, external APIs, modifications to WordPress core,
  theme functions.php, .htaccess or Nginx/Apache configuration.

== Existing legacy data and functions ==

* Reuses the existing prefix + seo_redirects table; creates it only if missing.
* When legacy SEO plugin is active, disables only the old send_headers callback
  for this request, to prevent two runtime engines acting on the same rule.
* Honors stored 301 AND 302; resolves internal relative destinations correctly.
* Old admin can still inspect shared rows. Deactivation and updates never delete rules.
* Removing the plugin from WordPress deletes the shared seo_redirects table and its rules. Export before uninstalling.
* Manager has add/edit/delete, live-use hits, most-recent hit, search, pagination,
  warnings on cycles/duplicates/chains, target link and rule test link.
* Compatible JSON backup/import (redirects array), analysis export, optional
  historical hit counts and 25 MB / 50000 input row cap. Existing rules are not
  replaced during import.

== Unified assisted-delete workflow ==

1. Access from WordPress classic editor, Gutenberg More menu, native lists,
   WooCommerce taxonomy editor, or optional Core editors and lists.
2. Review title and original URL.
3. Choose a 301 to a chosen valid same-site URL OR deletion without redirect.
   Cancel is always available.
4. Server rechecks specific WordPress capabilities, nonce, original permalink,
   collisions and cycles. A new 301 is stored BEFORE deletion. If a deletion
   fails, the new rule is removed.
5. Posts, pages and products move to WordPress Trash. A permanent delete from
   Trash is only possible with extra explicit irreversible-deletion confirmation.
6. Product category deletes are permanent and require no products, no children,
   no native Core Hub link, and must not be WooCommerce's default category.

== Deliberate coexistence limitation ==

While the old TaxoCommerce SEO category pre_delete_term guard is installed,
this new plugin BLOCKS product category deletion. The old monolith mandates a
301 to the unique secondary Hub and may modify the shared table after a delete.
Bypassing that guard could create dangling SEO relationships or unexpected
deletions. The new button/form remains visible and explains the limitation.
Use the old manager for that operation until the old deletion service is
retired and all native relationships have been verified.

== Explicit exclusions ==

* Bulk deletion intentionally remains the native WordPress action; redirect
  confirmation is per object. Bulk approvals are a separate future requirement.
* Deletions by third-party REST API clients are not intercepted. Gutenberg
  edits use an API-fetch guard and show the same confirmation link.
* Redirect targets must be within the current site. Existing external redirects
  are flagged in diagnostics and are not silently executed.
* Reports are structural, not remote HTTP availability tests and not Google/Bing
  quality measurements. A green rule can still point to a missing URL.
* Old monolith will regain control if this plugin is deactivated.
* Deploying by FTPS makes the plugin available in WordPress Plugins; activation
  requires separate authenticated wp-admin or WP-CLI access.

== Changelog ==

= 0.1.17 =
* Local Description and Plugin URI replace REST excerpt requests and caches in the admin header. Issue #2494.


= 0.1.16 =
* Display the last verified official excerpt from the developer's Plugin URI during HTTP errors; avoid stale negative cache and show bundled author Description on first-install network failure. No dependency on customer content. Issue #2489.



= 0.1.15 =
* Three-zone responsive admin header, centered official developer information and own Markdown history; remove shop administrator URL configuration. Issue #2476.

= 0.1.11 =
* Added an optional, collapsed version history beneath the admin header title, reading only this plugin's bundled CHANGELOG.md. No new admin tab, Core dependency, data changes or external requests.


= 0.1.9 =
* Pestaña «Autocheck» con descarga JSON ya existente, conservación de controles de seguridad y de comparaciones de regresiones.

= 0.1.8 =
* Cabecera administrativa común de TaxoCommerce con icono, descripción, versión visible y pestañas originales conservadas.
* Presentación autónoma, sin dependencia obligatoria de Core.


= 0.1.7 =
* Add User Guide tab with separate User Manual and Technical Manual, indexed sections, accessible navigation, responsive typography and links to Redirects/Auto Test.
* Document existing 301/302 functionality, administration, rules diagnostics, JSON backups, assisted deletion, data schema, legacy coexistence and the destructive uninstall caveat.
* Manual rendering is read-only and does not load the redirects table or invoke any handlers.


= 0.1.6 =
* Add native Auto Test tab with 26 read-only checks in 8 groups for redirects, schema, assisted deletion, integration, and safety.
* Save a per-user, per-site BEFORE baseline and compare it with AFTER results, including lost coverage, new errors and resolved issues.
* Allow downloading private diagnostic JSON; no redirects, hit counters, or content changes are made by tests.
* Keep the Redirects manager and all existing rules, legacy integration, and assisted-delete workflows unchanged.


= 0.1.5 =
* WordPress.org release metadata cleanup and English plugin description.
* Confirmed bounded assisted-delete queries keep WordPress filters enabled.

= 0.1.4 =
* Retirada la integracion opcional del motor historico de rollback de Database. CRUD de redirecciones vuelve al flujo autonomo original, sin dependencia ni comprobaciones de Database.
* Conservado el boton Abrir y la navegacion por Core cuando esta instalado.

= 0.1.3 =
* Version experimental retirada de la integracion de rollback.

= 0.1.2 =
* Integracion de menu y accion Abrir en el catalogo Core.


= 0.1.1 =
* Verified activation lifecycle and explicit uninstall cleanup for seo_redirects.

= 0.1.0 =
* Independent runtime, administration, import/export and 4+4 assisted deletion.

== Historical origins (monolith; not standalone releases) ==
* [Complete legacy and release history](https://github.com/distribuidordeherramientases-wq/seo-taxonomy/tree/main/taxocommerce-redirects) (changelog.txt / CHANGELOG.md).

* Legacy origin: SEO redirection records and redirect administration existed within TaxoCommerce SEO before extraction; the monolith version numbers are not standalone Redirects versions.
* The monolith 2.3.6 documented redirect handling as part of its SEO maintenance cycle.
* Standalone 0.1.0 extracted redirect management, preserving existing redirect data and the original role in WordPress navigation.
* The independent module introduced an assisted deletion workflow with distinct selection and confirmation phases (4+4) instead of unguarded mass removal.
* Subsequent fixes removed duplicate staging deployments, kept SEO taxonomy query filters active, addressed Plugin Check and added a dedicated user/technical guide.

== External Services ==


The local information panel shows `Description` and the `Plugin URI` link from the main PHP plugin header, without an HTTP request.
