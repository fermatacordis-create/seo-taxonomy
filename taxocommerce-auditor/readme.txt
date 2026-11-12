=== TaxoCommerce Auditor - Content Quality for WooCommerce ===
Contributors: davidperezmartorell
Tags: woocommerce, audit, seo, content, quality
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.3.22
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Audit WordPress and WooCommerce content quality, structure, duplicate risks and FAQs with per-type metrics and full JSON exports.

== Description ==

* Admin header: plugin title, a single local `Description` with the static `Plugin URI` link below it (left); a wider collapsed `CHANGELOG.md` history (center); local version and release date (right). No duplicated information panel or external requests.

* The admin panel reads the local PHP Description and Plugin URI metadata with no external request or cache; bundled CHANGELOG.md provides the version history.

* Collapsible plugin information and independent version history are available without configuring external services.


TaxoCommerce Auditor provides independent read-only content reviews across five scopes:

* Products: descriptions, titles, SKUs, categories, attributes, vocabulary and possible duplicate content.
* Product categories and SEO architecture: taxonomy completeness, native relationships and evidence-based restructuring candidates.
* Posts: titles, slugs, content patterns and potential overlap with landing pages.
* Pages and landings: content structure, WordPress-native SEO roles, hierarchy and potentially overlapping pages.
* FAQs: ownership, questions, answers, target product categories and semantic consistency. The TaxoCommerce FAQs module is optional.

Each scope has volume and quality indicators, severity counts, a prioritised review queue and complete exportable JSON findings. A selected workload speed is a hard upper bound: the next batch's item count adapts to actual PHP execution time and observed PHP memory pressure. Browser AJAX pacing also adapts to measured response latency. These are safe load proxies and **not** a measurement of server-wide CPU utilisation. WP-Cron warnings are not displayed in the active audit, but network and authentication errors remain visible. Audits run as consecutive, bounded authenticated AJAX batches while the administrator keeps the audit screen open. Each successful response triggers the next batch without waiting for WP-Cron or manual clicks. The admin screen reliably loads its footer worker even under a custom WordPress menu, and active or paused scopes cannot start duplicate audits. The manual next-batch button is not shown; only Pause and Resume remain available during a run. Each of the five scopes has five independent adaptive workload speeds, adjusting batch ceilings (40/90/180/300/500), per-request time budgets (2/3/5/8/12 seconds), and pauses between AJAX responses (6000/3500/2000/1000/300 milliseconds). Each audit starts conservatively and grows only when the time/memory/optional PHP-worker CPU feedback allows; these figures are not a measurement of total host CPU usage. Pause, resume, filtered/full audits, progress and JSONL exports are preserved. WP-Cron remains an optional background fallback; when the admin tab is closed or a mobile browser suspends it, continued processing requires a working server-side scheduler.

Complete audit results are streamed as JSON Lines files in WordPress uploads. Only a bounded sample is kept in WordPress options. Large exports stream the full results instead of loading the catalogue into memory.

The plugin reads canonical WordPress posts, taxonomies and metadata, supported WooCommerce product APIs, optional Core hierarchy data and locally saved data from other TaxoCommerce modules. It does not initiate external requests or automatically change, delete or redirect content.

* In-plugin Auto Test: 16 grouped checks of plugin availability, route registration, five scopes, read-only report and history access, quality rules and export contracts. No audit jobs, downloads, or content modifications occur during tests.
* Only two reports per administrator are held for 30 days to compare results between updates; absent old audit reports are not a failure.
* Integrated user and technical manuals explain all five content areas, filtered and full audits, priorities, JSONL reports, WP-Cron, security and limits.

== Installation ==

1. Install the standalone plugin on a supported WordPress/WooCommerce site.
2. Activate TaxoCommerce Auditor and open its administrator panel.
3. Choose a content scope and run a filtered audit or a full bounded audit.
4. Inspect findings, download JSON where needed, and apply corrections manually.
5. Verify cron execution and PHP upload-directory write permissions for full reports.

== Frequently Asked Questions ==

= Does an audit change content automatically? =

No. Every audit is read-only and offers recommendations without applying changes.

= Is the original TaxoCommerce SEO plugin required? =

No. Core and other TaxoCommerce modules provide optional integrations.

= Does it publish information to Google or another provider? =

No. It reads already-saved local evidence; no third-party requests are started.

== Changelog ==

= 0.3.19 =
* Use Description and Plugin URI from the local plugin header, without HTTP or cached REST excerpts. Issue #2494.


= 0.3.18 =
* Conservative adaptive batch limits up to 500 objects; optional PHP CPU time from getrusage and memory/latency feedback, without WP-Cron notices in the active screen.


= 0.3.17 =
* Automatically adjust the next AJAX batch size and pause using actual execution time, request latency and PHP memory pressure, within the configured speed cap. Remove misleading WP-Cron warnings from the audit screen; preserve genuine errors and retries.


= 0.3.16 =
* Display the last verified official excerpt from the developer's Plugin URI during HTTP errors; avoid stale negative cache and show bundled author Description on first-install network failure. No dependency on customer content. Issue #2489.



= 0.3.15 =
* Three-zone responsive admin header, centered official developer information and own Markdown history; remove shop administrator URL configuration. Issue #2476.

= 0.3.14 =
* Remove the manual next-batch button and prevent duplicate starts. Guarantee automatic authenticated AJAX chaining from all five scope screens, including custom admin menus; expose failed requests and preserve progress, pause/resume and throttling.


= 0.3.13 =
* Continuous bounded AJAX audits across five scopes without waiting for WP-Cron, retaining five speeds, safe retries, progress, pause/resume and JSONL exports.


= 0.3.11 =
* Recover overdue audit batches from the authenticated admin screen without restarting cursors; atomic per-scope worker locks, live cron diagnostics, and safe retry pacing.


= 0.3.10 =
* Independent five-level workload controller for products, categories, posts, pages and FAQs, including full/filtered audits; safe cursor and cron rescheduling.


= 0.3.9 =
* Added an optional, collapsed version history beneath the admin header title, reading only this plugin's bundled CHANGELOG.md. No new admin tab, Core dependency, data changes or external requests.


= 0.3.6 =
* Internationalization of Auditor UI using native WordPress gettext, with updated locale dictionaries.
* Preserves audit engine, reporting, exports, settings, permissions and existing plugin functions.


= 0.3.5 =
* Nombre uniforme «Autocheck» y descarga autenticada del último informe JSON, con resultados por comprobación y sin ejecutar operaciones reales.

= 0.3.4 =
* Cabecera administrativa común de TaxoCommerce con icono, descripción, versión visible y pestañas originales conservadas.
* Presentación autónoma, sin dependencia obligatoria de Core.


= 0.3.3 =
* Restore a clean standalone package for Plugin Check: existing output escaping, validated POST filters, enabled WordPress query filters and five readme tags.
* Document two justified, bounded native category queries with exact PHPCS annotations; retain audit selection, cursor limits, results and read-only semantics.
* Add source-level regression guards for the eight findings reported from an outdated Playground package.
* Document each required, prepared read-only SQL query against optional canonical module tables without changing results, freshness or storage.


= 0.3.2 =
* Add internal Auto Test tab with 16 functional checks, no audit execution, and previous-result comparison.
* Add integrated user and technical manual covering five scopes, safe operation, background process and JSONL report delivery.
* Keep existing read-only audit rules, scope navigation and export endpoints unchanged.
* Extend PHP 7.4/8.4 offline smoke tests and keep tests out of installable ZIP packages.


= 0.3.1 =
* Refresh WordPress.org readiness against the current standalone Auditor code.
* Keep large read-only audit exports streamed from bounded local JSONL storage.

= 0.3.0 =
* Added complete and filtered audits with full JSON exports.
* Added per-scope KPI panels and prioritised findings.
* Added optional locally stored decision context and native FAQ auditing.

= 0.2.0 =
* Restore the original Auditor interface and legacy quality checks using native WordPress/WooCommerce entities across five audit domains.

= 0.1.0 =
* Initial independent five-domain Auditor with bounded scans, native catalogue data and Vocabulary compatibility.

== Historical origins (monolith; not standalone releases) ==
* [Complete legacy and release history](https://github.com/distribuidordeherramientases-wq/seo-taxonomy/tree/main/taxocommerce-auditor) (changelog.txt / CHANGELOG.md).

* The monolithic TaxoCommerce SEO 2.3.x contained the first Auditor quality reports; these historical releases are not standalone Auditor versions.
* The historic review covered products, categories, posts, landing pages and FAQs, with separate quality findings and evidence.
* Standalone Auditor 0.1.0 established five groups of checks based on native WordPress and WooCommerce data.
* Standalone 0.2.0 restored the original Auditor checks and interface; 0.3.0 expanded complete audit results, JSON export, KPIs and decision context.
* The independent reports were kept separate from the external traffic reporting now owned by Insights.

== External Services ==

The administration header reads only the local Description and Plugin URI fields. No external services are contacted for the plugin information header.
