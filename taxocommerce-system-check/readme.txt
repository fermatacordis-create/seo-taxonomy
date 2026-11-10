=== TaxoCommerce System Check ===
Contributors: davidperezmartorell
Tags: diagnostics, security, performance, maintenance, monitoring
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.2.31
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Server, WordPress and plugin diagnostics with administrator-controlled reports and local checks.

== Description ==

* The admin header shows the plugin's bundled `Description` and links to its `Plugin URI`, without network requests or caching. Version history uses the bundled `CHANGELOG.md`.



TaxoCommerce System Check provides a standalone administration entry for diagnostics, and can integrate its menu with TaxoCommerce Core when installed.

* WordPress, PHP and hosting/system status screens.
* Processes tab: read and cancel eligible application jobs from Action Scheduler/WP-Cron, with optional cooperative controls for installed workers. Does not kill operating-system or running PHP processes.
* Plugin validation and local PHP checks.
* Autocheck is a single main tab with two subtabs: Functionality Check (the original on-demand functional tests and JSON) and Code Check (the native source audit and its own JSON).
* The Code Check subtab inspects only System Check's own PHP files. It performs native PHP syntax parsing, checks known PHP 8 features against the declared PHP minimum, verifies resolvable local include paths, detects duplicate named declarations, checks selected WordPress structure conventions, and flags potential nonce, capability, input, output, REST and SQL risks for manual review. No other plugin is required. It displays counts by category/status and actionable file findings (including missing structure items and suspicious call locations), offers five bounded CPU speeds, stop/resume and private JSON export. It requires no other checker plugin, WP-CLI, GitHub or process-execution functions. The local rules are not the official WordPress Plugin Check, and untested security/publication rules remain explicitly unverified.
* Optional HTTP URL audit of site content, when started explicitly by an administrator.
* On-demand diagnostic reports and optional administrator-configured notification emails.
* Dedicated bounded processing for longer URL checks. Neither external GitHub runners nor artificial traffic generation are necessary.
* Historical administration URLs are kept for compatibility.

Some historical diagnostic callbacks are reused when the old TaxoCommerce SEO plugin is still present. Standalone behavior must be tested with the old plugin disabled before publication.

== Installation ==

1. Install and activate TaxoCommerce System Check.
2. Open System Check in WordPress administration (or the Core submenu if Core is installed).
3. Run a system or plugin check and review the findings.
4. Start URL audits or enable scheduled email reports only when you intend to use them.

== Privacy ==

System diagnostics can include server configuration, URLs and error details. Treat exported results as potentially sensitive. No diagnostic report should be sent to a developer automatically: optional email reports must be explicitly enabled and target an address chosen by the site administrator. HTTP checks make requests to the site's specified URLs and must be used within hosting limits.

== Changelog ==

= 0.2.30 =
* Local Description and Plugin URI replace REST excerpt requests and caches in the header. Issue #2494.


= 0.2.29 =
* Display the last verified official excerpt from the developer's Plugin URI during HTTP errors; avoid stale negative cache and show bundled author Description on first-install network failure. No dependency on customer content. Issue #2489.



= 0.2.28 =
* Three-zone responsive admin header, centered official developer information and own Markdown history; remove shop administrator URL configuration. Issue #2476.

= 0.2.25 =
* Move Code Check into the main Autocheck tab as a second subtab, preserving the functional Autotest and the deeper standalone code audit.
* Preserve legacy Code Check links and keep server diagnostics in the separate System tab.


= 0.2.24 =
* Collapsed history directly under the admin title, reading the bundled CHANGELOG.md.


= 0.2.23 =
* Expand standalone Code Check with local include integrity, duplicate class/function detection, PHP minimum-version indicators and security review signals (nonces, permissions, escaping, sanitization, SQL and REST).
* Preserve three existing categories, 5 CPU speeds, stop/resume, and private JSON. Unverified flows, older PHP compatibility and official WordPress Plugin Check remain explicitly unverified.


= 0.2.22 =
* Separate Autocheck (existing functionality tests) and Code Check (own-source PHP/WordPress/security review) into two primary administration tabs. Preserve old Code Check links and all report actions.
* Add category-level counts and actionable per-file evidence in Code Check; do not present local static checks as official WordPress Plugin Check certification. No mandatory checker dependencies or schema changes.


= 0.2.21 =
* Remove duplicate admin GET parsing and narrowly document the nonce-free, read-only asset selector to pass strict WordPress Plugin Check.
* Preserve the two Autocheck subtabs (Functionality Check and Code Check) and their native, independent PHP/WordPress/security checks.


= 0.2.20 =
* Split Autocheck into Functionality Check and Code Check subtabs, both only for System Check itself.
* Add three native, read-only code checks (PHP syntax, WordPress conventions, security patterns) without installing or invoking any other checker.
* Code Check supports five CPU speeds, stop/resume and a user-scoped JSON report. Complex rule coverage remains explicitly unverified.


= 0.2.18 =
* Add a bounded, administrator-only Processes tab with application-task inventory, protected selective cancellation and cooperative stop controls. Preserve WooCommerce critical jobs and never claim to terminate active PHP or operating-system processes.


= 0.2.17 =
* Keep the most recent Autocheck JSON download available on returning to the tab, until the user- and site-scoped report expires. Export does not rerun tests.
* Validate PHP diagnostic rows, active module results, HTTP callback/schedule contracts and JSON serialization. Include structured evidence and executed/total coverage in Autocheck output.
* Read the last saved server snapshot without starting another scan: missing or older than 24 hours is reported as not executed, malformed data as failure. Environment warnings do not imply a broken plugin.

= 0.2.16 =
* Load bundled server diagnostics when legacy SEO is installed but inactive, or active without its Server Status module; avoid duplicate headers and reloading existing global engines.


= 0.2.15 =
* Pestaña homogénea «Autocheck» y descarga JSON del último informe, por usuario y sitio, con control de permisos y nonce.

= 0.2.14 =
* Cabecera administrativa unificada de TaxoCommerce con nombre, icono y versión.
* Estilos propios sin Core, funciones y permisos intactos.


= 0.2.13 =
* Bind both Action Scheduler table identifiers using %i when retrieving the highest-volume pending action group.
* Add source-regression checks to prevent restoring interpolated SQL and synchronize the standalone Auto Test version.
* Preserve live diagnostics and all existing historical callbacks; production publication requires separate approval.

= 0.2.12 =
* Fix remaining Plugin Check SQL preparation findings by binding canonical table identifiers and preserving bound user values in billing, semantic checks, health scans and external images.
* Document narrowly scoped live database diagnostics without introducing stale caches or changing public legacy SEO APIs.
* Add regression assertions and validate installable ZIP with WordPress Plugin Check before release.


= 0.2.11 =
* Prepare the canonical table identifiers in all eight MySQL EXPLAIN diagnostics with the WordPress 6.2+ %i placeholder.
* Extend offline SQL and malformed-input safety tests to PHP 7.4 and PHP 8.4; ignore array-valued HTTP audit filters and actions without changing valid requests or legacy public APIs.
* This patch does not claim to resolve all remaining WordPress Plugin Check warnings; the compatibility and direct-database warnings continue to be tracked in GitHub issue #1958.


= 0.2.9 =
* Refresh WordPress.org metadata for the standalone System Check package.
* Confirm current gettext calls use the standalone text domain and Action Scheduler table identifiers use prepared `%i` placeholders.

= 0.2.8 =
* Standalone system and plugin diagnostics with compatibility routes.
* Local PHP checks and optional bounded HTTP URL audits.
* Administrator-controlled reports and notifications.

== Historical origins (monolith; not standalone releases) ==
* Legacy origin: server diagnostics, plugin validation and HTTP/URL checks existed under TaxoCommerce SEO administration; the monolith's release numbers are not standalone System Check versions.
* The old validation panel mixed site status, plugin-specific checks and URL/HTTP observations; its historical outputs were not automatically proof that newer modules had passed.
* During modularisation System Check was isolated from SEO_SYSTEM_* globals and constants to prevent collisions with the still-installed monolith.
* Standalone diagnostics distinguished the active plugin modules from installed but inactive extensions and retained administrator-controlled bounded scans and reports.
* Later WordPress.org work corrected text-domain usage and prepared SQL identifiers; the historical original checker remained distinct from each plugin's own Auto Test.

== Local plugin information ==

The developer information panel shows only bundled `Description` metadata and an outbound `Plugin URI` link. No external request is made.
