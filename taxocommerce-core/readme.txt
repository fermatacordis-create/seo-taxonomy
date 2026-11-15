=== TaxoCommerce Core - Catalog & Content Management for WooCommerce ===
Contributors: davidperezmartorell
Tags: woocommerce, catalog, content, management, seo
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.4.85
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage WooCommerce catalog structure and WordPress content from a modular dashboard with optional independent extensions.

== Description ==

* Hides only the Action Scheduler overdue-actions admin notice. Scheduled actions and other notices remain unchanged.



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
* The Core Extensions catalog preserves its built-in family entries and automatically detects new installed TaxoCommerce plugins (including inactive plugins) using the native WordPress plugin inventory. It also discovers locally uploaded family ZIP packages even when not yet installed. Each card reports the actual installation and activation status; existing entries are deduplicated.
* Discovery, activation, deactivation, family ZIP downloads and links to the native WordPress installer are available from each Core extension card. Installation and activation still use the permission-checked native WordPress plugin screens; no custom ZIP installer is included.
* Built-in, read-only User Guide tab containing a user manual and a technical manual for Core.
* Complete read-only JSON download from Core > Content > Anomalies, including all structural, editorial, category, hub and optional FAQ finding sections from the live report. The existing editorial-only JSON remains separate.
* Autonomous native Code Check: bounded PHP syntax, WordPress structure, source-level security and manual WordPress.org review signals (nonces, capabilities, uploads, lifecycle, buffers, inline CSS, prefixes, sessions and SQL). Includes source location and JSON export; does not run or require the official Plugin Check. Findings remain review warnings, not a certification.
* Read-only Autocheck with authenticated JSON download of the last user-specific report, including site URL, Core version, pass/fail/skipped results and detailed messages for independent comparison between installations.

Editorial guidance does not create or modify content automatically. Only authorised administrators can install optional extension ZIPs through WordPress' native Plugins > Add Plugin screen. Core can provide an authenticated download of a previously stored local ZIP.

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

= 0.4.78 =
* Kept installed Core translations compatible with the short plugin directory and prepared a separate checked WordPress.org archive with its assigned long slug; both undergo strict Plugin Check.

= 0.4.77 =
* Aligned WordPress translations and five bundled locales with the assigned WordPress.org slug; retained existing admin, catalog and data identifiers.

= 0.4.76 =
* Prepared concise WordPress.org release information and synchronised local changelogs. No runtime functionality changed.

= 0.4.75 =
* Added full, read-only anomaly report JSON download; preserved the existing editorial JSON.

= 0.4.74 =
* Simplified the administration header to local description, history and version/date.

= 0.4.73 =
* Hid only Action Scheduler's overdue-actions notice; scheduled work is unchanged.

= 0.4.72 =
* Used bundled Description below the plugin's administration title.

= 0.4.71 =
* Replaced remote developer-excerpt requests with static local metadata and link.

= 0.4.68 =
* Used WordPress stylesheet APIs and native upload handler for optional extension ZIPs.

= 0.4.62 =
* Completed bundled Spanish, English, French, German and Dutch translation catalogs.

= 0.4.58 =
* Standardised the read-only Autocheck and protected JSON download.

== Earlier releases ==

Full standalone and legacy histories are retained in the bundled CHANGELOG.md and changelog.txt. Core does not require the original TaxoCommerce SEO monolith.
