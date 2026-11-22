=== TaxoCommerce System Check ===
Contributors: davidperezmartorell
Tags: diagnostics, security, performance, maintenance, monitoring
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.2.45
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Independent WordPress, PHP and server diagnostics with administrator-controlled checks and reports.

== Description ==

TaxoCommerce System Check helps administrators investigate their WordPress installation without requiring any other TaxoCommerce plugin.

= Key Features =

* Inspect WordPress, PHP, hosting and server configuration from the administration area.
* Review WP-Cron and Action Scheduler application jobs, their available diagnostics and eligible cancellation controls; active PHP requests cannot be forcibly terminated.
* Run System Check's own functionality and code checks, review findings and export JSON reports.
* Run optional, bounded HTTP URL audits only when explicitly started by an administrator.
* When other TaxoCommerce plugins are active and registered by TaxoCommerce Core, run their available individual checks or use "Check all plugins" to process them sequentially, with progress and individual logs.

Checks are administrator-initiated and generally read-only. A job cancellation or another explicitly selected administrative action may change its corresponding queue state. System Check does not silently execute the checks of other plugins or alter WooCommerce products.

== Installation ==

1. Upload the standalone `taxocommerce-system-check` ZIP through Plugins > Add New > Upload Plugin, or install it from the WordPress plugin directory when available.
2. Activate the plugin, then open System Check in WordPress administration.
3. Run the system diagnostics or System Check's own Autocheck to inspect the local installation.
4. If TaxoCommerce Core is also installed, use Plugin Validation for the active family modules that Core registers.

== Compatibility and optional integrations ==

* Standalone: System, PHP and WordPress diagnostics, local functionality/code checks, HTTP URL audits and available WP-Cron/Action Scheduler process information. No TaxoCommerce Core installation is required.
* Optional TaxoCommerce Core: provides the active family-module inventory used to launch available checks in those plugins. Each plugin's own functionality and code checks remain its responsibility.
* Optional WooCommerce and Action Scheduler: their specific data or jobs are shown only when available. They are not required for the independent WordPress/server checks.

The built-in code review is not the official WordPress Plugin Check. The family-wide run is driven by the administrator's browser; a closed or inactive tab does not keep running an independent PHP background worker.

== Privacy ==

System diagnostics may include configuration, URLs and error details; treat exported JSON and logs as potentially sensitive. No diagnostic report is automatically sent to the developer. Optional notifications require an administrator-selected destination. An HTTP URL audit sends requests to the URLs selected by the administrator and must respect hosting limits.

== Frequently Asked Questions ==

= Does it require TaxoCommerce Core? =

No. Core is optional and enables the active-family plugin inventory for combined checks; System Check's own diagnostics work without it.

= Does "Check all plugins" run checks on every WordPress plugin? =

No. It runs the available checks of the active TaxoCommerce modules registered by Core, not arbitrary third-party plugins.

= Does System Check run an official Plugin Check inside WordPress? =

No. Its local source checks and reports are separate from the official Plugin Check used in the development and release pipeline.

== Changelog ==

= 0.2.43 =
* Add an administrator-triggered sequential "Check all plugins" action for active TaxoCommerce modules registered by Core; retain individual logs, reports and JSON exports.

= 0.2.42 =
* Improve per-plugin results, progress, errors, execution logs, cooperative Stop/Resume and visibility of browser-driven checks in Processes.

= 0.2.41 =
* Add per-plugin diagnostics and detailed native functionality/code results with independent reports.

For the complete version history, see the bundled `CHANGELOG.md` and `changelog.txt`.
