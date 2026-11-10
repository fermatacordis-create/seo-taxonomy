=== TaxoCommerce Core - Catalog & Content Management for WooCommerce ===
Contributors: davidperezmartorell
Tags: woocommerce, catalog, content, management, seo
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.4.72
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage WooCommerce catalog structure and WordPress content from a modular dashboard with optional independent extensions.

== Description ==

* The administration header shows Core's local PHP `Description` and links to its developer `Plugin URI` without HTTP, optional consent actions or cached remote excerpts. The version history is read from the plugin's own `CHANGELOG.md`.



TaxoCommerce Core provides a modular foundation for WordPress and WooCommerce catalog and content management. WordPress posts, pages, terms and WooCommerce products remain the canonical data sources; Core does not duplicate them into a separate content store.

* Editorial management for products, product categories, posts and pages using native WordPress editing.
* Cached read-only inventory dashboard for products, posts, pages, categories, media, statuses and optional FAQs.
* SEO architecture based on clusters, primary hubs, secondary hubs, product categories and products.
* Native WordPress navigation menu generation with optional manual entries and safe regeneration. SEO menu countries come only from explicitly assigned page countries; Global denotes no country, with no preset menu countries.
* Unified WordPress menu manager: the same two-column loading, publication, object picker and hierarchical structure UI works in classic and block themes. Classic menus retain native WordPress nav_menu storage; block themes use wp_navigation posts. In block themes, add pages, posts, categories, product categories and custom links; rename and edit links, remove safe leaf links, delete an unreferenced navigation only after confirmation, and explicitly activate a header template part when unambiguous. Theme files are never changed; unsupported advanced navigation blocks still use the Site Editor.
* Optional SEO hierarchy menus are only proposed for populated hierarchies. No stored menu is overwritten by viewing the manager. Inline template navigation remains editable in the native Site Editor.
* Architecture reporting with unique counts, explicit manual refresh and local 15-minute caching.
* Read-only editorial guidance for products, categories, posts and pages based on useful content and product identity.
* Process and extension registries with bounded technical logs for independent modules.
* The Core Extensions catalog lists all 19 current independent TaxoCommerce modules even when absent or inactive, including Tasks; each card reports its actual WordPress installation and activation status. Retired Connections is incorporated in Insights and is not offered separately.
* Optional extension discovery for the other TaxoCommerce modules.
* Built-in, read-only User Guide tab containing a user manual and a technical manual for Core.
* Read-only Autocheck with authenticated JSON download of the last user-specific report, including site URL, Core version, pass/fail/skipped results and detailed messages for independent comparison between installations.

Editorial guidance does not create or modify content automatically. Only authorised administrators can upload an optional extension ZIP through Core.

== Languages ==

TaxoCommerce Core uses the WordPress gettext domain `taxocommerce-core` and the plugin `/languages` directory. The WordPress administration locale determines the language automatically. Bundled gettext catalogs cover Spanish (Spain), English (US), French, German and Dutch, with full coverage of the strings currently extracted in each gettext template; technical names and catalog content remain unchanged. The untranslated PHP/JavaScript surface and locale-specific administration display still require functional verification. WordPress.org language packs will be supported once translations are approved.

== Local developer information ==

The administration panel reads Core's bundled `Description` and `Plugin URI` fields without contacting the developer's website or storing remote excerpts. Legacy developer-excerpt cache entries from older versions are removed on uninstall. The plugin's normal functions and other external integrations are unaffected.

== Installation ==

1. Install and activate TaxoCommerce Core on WordPress 6.5 or newer.
2. Open the Core dashboard to configure content and SEO architecture.
3. If using WooCommerce, inspect product and category information before importing data.
4. Install any optional TaxoCommerce extensions separately and review their dependencies.
5. During migration, verify unique module ownership and avoid simultaneous writes by the original SEO plugin.

== Frequently Asked Questions ==

= Does Core need the original TaxoCommerce SEO plugin? =

No. Core operates independently; integrations with other modules are optional.

= Does Core automatically rewrite my product descriptions? =

No. Editors and guidance preserve manual author control.

= Does Core support optional extension installation? =

Yes. Administrators may explicitly upload a verified module ZIP through Core's extension panel. Native WordPress installation APIs install the package after temporary validation.

== Changelog ==

= 0.4.71 =
* Show bundled Description and Plugin URI as static header information without a developer REST request or opt-in button. Keep local version history, existing Core features and legacy cache cleanup. Issue #2494.


= 0.4.70 =
* The developer excerpt requires explicit administrator consent (button, nonce and capability); no HTTP requests occur on ordinary visits, even after cache expiry. Last verified excerpt and bundled Description remain available offline.
* External API URL, purpose and connection details documented for WordPress.org guideline 7. Issue #2491.



= 0.4.69 =
* Display the last verified official excerpt from the developer's Plugin URI during HTTP errors; avoid stale negative cache and show bundled author Description on first-install network failure. No dependency on customer content. Issue #2489.



= 0.4.68 =
* WordPress.org pre-review: replace inline admin CSS with WordPress stylesheet APIs; use the native upload handler for administrator-selected extension ZIPs. Keep explicit per-extension activation and deactivation permissions and nonces.
* Confirm `taxocommerce-core` as the requested WordPress.org permalink and existing gettext domain. No WooCommerce hard dependency: WP content functions remain available without WooCommerce. Issue #2481.


= 0.4.67 =
* Three-zone responsive admin header, centered official developer information and own Markdown history; remove shop administrator URL configuration. Issue #2476.

= 0.4.63 =
* Added an optional collapsed version history in the Core dashboard header, reading only Core's own bundled CHANGELOG.md without creating tabs or changing data or module dependencies.


= 0.4.62 =
* Complete the existing Core gettext catalogs in Spanish, English, French, German and Dutch, covering administrative actions, native menus, extension management, diagnostics and both manuals.
* Ensure local search HTML data attributes are never translated; keep the generated markup and native menu logic unchanged.
* Add regression checks for compiled MO catalogs, required interface messages and source-key integrity; release 2026-11-08. Linked to issue #2062.

= 0.4.60 =
* List TaxoCommerce Tasks in the built-in extension catalog, including when the Tasks plugin is not installed or active.
* Preserve all 19 current extension cards and correct states (active, installed but inactive, not installed), with optional administrator-initiated install/activation and no automatic installation.
* Add a regression test for all three states and gettext descriptions in ES, EN, FR, DE and NL.

= 0.4.58 =
* Normalize the existing Core diagnostics run button to Autocheck, matching every TaxoCommerce plugin. Preserve all existing check IDs, endpoint slugs, and protected JSON download behavior.


= 0.4.57 =
* Retirada la tarjeta de detección del antiguo TaxoCommerce SEO y el mensaje de convivencia obsoleto del panel Core.
* Se mantienen los indicadores de versión, WooCommerce y extensiones, además de las funciones de migración internas.


= 0.4.55 =
* Correct the read-only SEO Relations Autocheck: recognize valid editorial post-to-category and landing-to-category edges alongside Core hierarchy relations and category translations.
* Validate each edge against its declared post/term endpoints and country scope. Unknown external relation types are explicitly not checked rather than reported as damaged data.
* Include regression tests for the reported staging false positive, invalid endpoints, translated categories across countries, and mixed relation types.


= 0.4.54 =
* Resolve two Plugin Check errors in native block navigation: remove explicit suppress_filters and escape the numeric depth attribute.
* Sanitize block-navigation POST inputs while keeping native WordPress menu storage, existing selection and permissions unchanged.
* Clarify read-only anomalies query arguments and the targeted page-country query for Plugin Check.

= 0.4.53 =
* Export the last authenticated Core Autocheck report as UTF-8 JSON, including installation identity, detailed test results and explicit skipped cases, without re-running or changing store data.
* Add regression tests and versioned export schema.

= 0.4.52 =
* Make the built-in Autocheck discoverable from the menu manager without adding a WordPress sidebar submenu; preserve existing Auto Test URLs.
* Check native classic-theme menu loading and referenced block navigation in the actual theme, plus menu controller registration, without altering site content.
* Remove predefined shop markets from the menu Autocheck and report optional SEO tables as not applicable on fresh installations.

= 0.4.51 =
* Use the same menu manager layout for classic and block themes, including loading, publication, object pickers, and nested structure.
* Extend block-navigation editing with native WordPress page/post/taxonomy links, menu renaming and safe leaf-link removal. Preserve theme files and existing navigation blocks; add security and regression tests.

= 0.4.50 =
* Resolve the active classic WordPress menu from the current theme's registered locations, regardless of their names. When several distinct menus are assigned, require explicit selection rather than guessing.
* Keep the same distributable Core source on staging and the independent association WordPress through checksum-based reconciliation, transferring only files that differ. No production deployment.

= 0.4.47 =
* Generate available SEO menu countries from actual page assignments; unassigned pages use Global with no country code. Existing WordPress menus and country menus are retained. Add independent language catalogs and regression tests.

= 0.4.46 =
* Contextual anomalies: display only verified findings by default, retain an opt-in detailed view, and suppress unconfigured hierarchy or optional FAQ/Vocabulary warnings.


= 0.4.44 =

* Separate editable block navigation from classic nav menus; locate published wp_navigation references used by header templates, edit link labels/URLs, save safely with a revision check. Inline or ambiguous navigation uses the WordPress Site Editor.

= 0.4.43 =
* Add explicit Save Menu buttons for batch editing of native WordPress menu name and items, with a clear saved confirmation and unsaved-changes indicator.
* Add Delete Menu for regular WordPress menus with checkbox and dialog confirmation, preventing deletion of SEO/country-managed menus.
* Preserve separate add/remove actions, safe parent validation, current theme menu locations and WooCommerce independence.


= 0.4.42 =
* A new WordPress-only menu tab edits native menus using posts, pages, normal categories and links without WooCommerce.
* Preserve the SEO/WooCommerce country menu workflow unchanged and forbid silent creation of SEO menus from the WordPress tab.
* Extend isolated regression tests, user and technical guides.


= 0.4.41 =
* Load any existing native WordPress navigation menu into Core's menu editor without changing its country-managed menu or current theme location.
* Edit manual items (title, custom URL, parent and position) and create independent new WordPress menus from Core.
* Keep SEO-generated elements read-only, preserve country synchronization and require permissions and nonces for modifications.


= 0.4.39 =
* Package-only Plugin Check remediation: deploy and distribute without development tests or hidden FTP state files.
* Keep the full smoke/regression test suite in GitHub and CI; preserve all Core menus, country scopes, content, SEO architecture, Auto Test, and manuals.
* Verify staged package contents and clean legacy Core FTP artifacts on STAGING after deployment. No changes to WordPress content or database.


= 0.4.38 =
* Align the Logistics extension catalog link and family submenu label with the standalone module's unique admin page.
* Preserve the legacy TaxoCommerce SEO order view and all shipping settings without moving data.


= 0.4.37 =
* Move Core Auto Test into the Core Overview / User Guide / Auto Test internal tab bar rather than a WordPress sidebar submenu.
* Retain the 17 read-only checks, user transient, nonces, dashboard navigation and old URL compatibility via a safe redirect.
* No new tables or modifications to tests, catalog, SEO hierarchy, menus or extension management.


= 0.4.36 =
* Add Technical Manual alongside User Manual in the existing User Guide screen.
* Describe Core architecture, hooks, data, countries, permissions, caching, deployment and diagnostics using semantic headings and responsive tables.
* Preserve the original user manual and all operational Core screens.


= 0.4.35 =
* Add a User Guide tab with semantic H1/H2/H3 headings, table of contents, direct links and responsive presentation.
* Document Content, SEO Hierarchy, country menus, Auto Test and optional extensions without changing existing operations.


= 0.4.34 =
* Add a native Auto Test screen and dashboard shortcut for administrator-triggered, read-only functional checks.
* Report individual pass/fail/skipped results with error details, timings and version, isolating failures between checks.
* Cover runtime, WP/WooCommerce content reads, country metadata, SEO roles and relation samples, country menus, modules, vocabulary and architecture summary. Never create, delete or sync real content during tests.
* Keep the last test result in a user-specific transient for one day; writing UAT remains explicitly outside the production-safe suite.


= 0.4.33 =
* Ensure each country creates, saves and edits a dedicated native WordPress menu with a distinct country-specific name.
* Prevent stale country options from reusing and overwriting another market's menu ID; restore the correct market association by native menu name.
* Rename legacy Spanish TaxoCommerce menu in place when synchronised, preserving its menu ID and content.
* Show the selected native menu name in the editor and add regression coverage for cross-country menu ID collisions.


= 0.4.32 =
* Fix existing SEO hierarchy tables that missed the country_code migration: both seo_nodes and seo_relations are checked, without deleting or replacing existing records.
* Persist page country_code when saving or reusing a role node; synchronize active role country when page country metadata changes.
* Keep legacy relations and Spanish hierarchy intact. Relation country is also independently checked when both SEO tables already exist.
* Add isolated QA for the pre-existing-table migration and country-aware node saves.

= 0.4.31 =
* Adds a market-country selector to Core content editors for posts, pages, products and WooCommerce categories, preserving existing country metadata.
* Adds optional country_code to seo_relations and country-filtered hierarchy editing without overwriting legacy links.
* Provides independent managed menus per country and a front-end selector for TaxoCommerce-managed theme menu locations.
* The original Spanish menu and relationships remain valid; international categories are never blanket-labelled Spanish.


= 0.4.30 =
* Move Extensions from the Core submenu into responsive cards at the bottom of the Core home screen.
* Show live installed/active/ZIP states, version, descriptions and guarded management actions per extension.
* Remove redundant migration descriptions while retaining old bookmarks via a redirect.


= 0.4.29 =
* Added Core > Content > Inventory summary with read-only totals, 15-minute cache and an administrator-initiated protected refresh.
* Counted canonical WordPress/WooCommerce records without invoking Auditor scans.
* Refresh WordPress.org readiness against the current modular Core implementation.
* Preserve native content, hierarchy, menu and extension-management contracts while validating the standalone package.

= 0.4.28 =
* Consolidated external connections under TaxoCommerce Insights.
* Retained independent extension discovery and administrator-initiated installation.

= 0.4.27 =
* Clarified Supplier and Logistics responsibilities.

= 0.4.26 =
* Registered Suppliers in the extension catalogue.

= 0.4.24 =
* Registered Logistics and Billing modules.

= 0.4.12 =
* Added FAQ content screens and removed an unused technical log page.

= 0.4.10 =
* Added administrator-controlled process management.

= 0.4.9 =
* Added editing guidance for all four content types.

= 0.4.8 =
* Consolidated extension management into one screen.

= 0.4.0 =
* Added native WordPress menu management for the SEO hierarchy.

= 0.3.0 =
* Introduced the native cluster and hub hierarchy.

== Historical origins (monolith; not standalone releases) ==
* [Complete legacy and release history](https://github.com/distribuidordeherramientases-wq/seo-taxonomy/tree/main/taxocommerce-core) (changelog.txt / CHANGELOG.md).

* Historical source: the TaxoCommerce SEO monolith through 2.4.1 maintained the SEO architecture, category/product relationships, taxonomy and the original WordPress administration; these are not Core standalone versions.
* The 2.2.x legacy work included the vocabulary bridge, canonical semantic labels and the product/category relationship model.
* During 2.3.x the monolith organised clusters, primary and secondary hubs, landing pages, menus and associated WooCommerce categories/products.
* Core became the administrative and integration point for independent TaxoCommerce extensions, preserving the existing data contracts rather than migrating every service into Core.
* Later standalone work refined the Extensions panel, nested menu navigation, WordPress.org compliance and internal Auto Test. Individual learning, images and feeds remain owned by their respective modules.
* The international storefront uses explicit country-based pages, templates and navigation; the Core layer retains role/menu selection while Templates owns frontend rendering.

== Local plugin information ==

The local information panel shows Core's bundled `Description` and links to the developer's `Plugin URI`. It does not make HTTP requests.
