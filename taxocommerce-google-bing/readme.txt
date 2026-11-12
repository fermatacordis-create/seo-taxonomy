=== TaxoCommerce Insights ===
Contributors: davidperezmartorell
Tags: analytics, search-console, bing, ecommerce, reporting
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.49
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

External connections, manual provider data downloads, and the restored legacy Analysis dashboard.

== Description ==

* Admin header: plugin title, a single local `Description` with the static `Plugin URI` link below it (left); a wider collapsed `CHANGELOG.md` history (center); local version and release date (right). No duplicated information panel or external requests.

* Analysis: individual reports or a complete sequential run (mobile-friendly) using authenticated AJAX, and unified JSON download or browser-view fallback.

* The admin header shows the local bundled Description and Plugin URI metadata without external requests or excerpt caching. CHANGELOG.md provides independent version history.



TaxoCommerce Insights keeps external service connections and manual data downloads in one place.

* Connections: Google Search Console, Google Analytics, Google Merchant Center, Bing Webmaster, Amazon and Pinterest.
* Google Search downloads and explicit day-by-day synchronization of the historical Search Console tables used by Analysis, authorized via current Connections OAuth or service-account credentials.
* Google Analytics downloads; optional GA4 product add-to-cart item chart and ecommerce event comparison in saved reports.
* Bing downloads.
* Optional IndexNow notifications for public URL changes, independent of Cloudflare; disabled by default to avoid duplicate providers. Configure in Connections > Bing. Uses WP-Cron, so regular scheduled execution is required.
* Google Trends automatically compares all Spanish clusters (up to 5 per chart), then separately compares each cluster's primary hubs on page load. The underlying SEO hierarchy is dynamic, category and secondary-hub consultations remain manual; five-speed browser load controller, pause/resume/stop, and links to Google Explore. Charts are visual and do not provide machine-readable growth scores or verification of search term meaning. Historical RSS snapshots remain supported.
* Analysis restores the previous working Analista engine inside Insights, including summary, content, structure, trends, comparison and work-plan views. Its historical GSC tables are refreshed by the separate Google Search synchronization button without WP-Cron. The GA4 Analysis adapter queries the live Data API through current Connections credentials.
* User Guide contains read-only User Manual and Technical Manual explaining active features, integrations, data and maintenance.
* The main technical folder remains taxocommerce-google-bing for compatibility; the displayed product name is TaxoCommerce Insights.

Opening reports does not automatically fetch provider data. Administrators explicitly initiate connection, download, refresh or research operations. Each external source requires the corresponding authorization and provider terms.

== Installation ==

1. Install the standalone TaxoCommerce Insights ZIP.
2. Open Insights > Connections in WordPress administration.
3. Connect only the supported providers you plan to use, with credentials you control.
4. Use the test action, then request a report download/refresh.
5. Review your access permissions, privacy policy, retention and any API quota/cost.

TaxoCommerce Core is optional. WooCommerce metrics are available only if WooCommerce is installed and the relevant data exists.

== External Services and Privacy ==

Depending on which optional providers the administrator connects, this plugin communicates with Google's Search Console, Analytics, Merchant Center or YouTube APIs; Microsoft's Bing Webmaster service; Amazon services; Pinterest; and SerpApi. These providers process data under their own terms and privacy policies. No external account is connected by default.

Administrator-provided OAuth/service credentials and downloaded reports are stored locally as WordPress settings and/or module records. Secure the WordPress admin and database, grant minimum provider permissions, and inform users about analytics collection where applicable. Reporting cannot establish individual affiliate sales attribution unless the source provides it.

== Frequently Asked Questions ==

= Do reports work without connecting external services? =

Only previously saved local information may be available. Live Google/Bing metrics require an authorized connection and explicit retrieval.

= Is this the old Google/Bing plugin? =

It is the continued plugin under the historical slug taxocommerce-google-bing, renamed to Insights and focused on unified connections and provider data downloads.

== Changelog ==

= 1.1.49 =
* Corrected Analysis button contrast: Create report and Run full report are visible with blue backgrounds; individual JSON downloads remain legible when unavailable. Kept the five-column desktop layout and fixed individual AJAX progress labels without changing report calculations or exports.

= 1.1.48 =
* Analysis shows ten report cards in a five-column desktop grid, each with Create report, Download JSON and the date of the last generated report. Specialist JSON snapshots are stored only after running their reports, and downloads never request new provider data. The action plan retains its dedicated JSON.

= 1.1.47 =
* GA4 Analysis uses a self-contained window through yesterday; no longer inherits stale Search Console SQL dates. Existing GSC synchronization bridge and stored Google credentials are unchanged.


= 1.1.45 =
* Show two adjacent controls at the top of Analysis: run Plan de acción and download its latest saved JSON with its local generation date. Save only the last completed plan per site as a non-autoloaded WordPress option; authenticated nonce-protected download never recomputes reports. Remove lower-page JSON actions. Issue #2537.


= 1.1.44 =
* Shorten the merged Analysis report button to «Plan de acción». After loading it, provide a standalone downloadable JSON containing only the prioritised action plan, reusing the same computation; keep the full Insights JSON unchanged. Issue #2528.


= 1.1.43 =
* GA4 ecommerce event chart and product-level quantities added to carts and purchases, saved in the existing Analytics snapshot. Uses optional manual Google Data API retrieval; snapshots without item data remain supported. Issue #2514.

= 1.1.42 =
* Restore a three-zone admin header, with plugin title/description/link, collapsed version history and version/date, without duplicate content. Issue #2521.

= 1.1.41 =
* The Analysis action plan watches editorial recommendations when local content was updated after the latest Search Console data. No extra content crawl, no automatic verified-fix claim. Issue #2518.


= 1.1.38 =
* Local Description and Plugin URI replace the REST excerpt in the header, preserving executive reports and data integrations. Issue #2494.


= 1.1.36 =
* Display the last verified official excerpt from the developer's Plugin URI during HTTP errors; avoid stale negative cache and show bundled author Description on first-install network failure. No dependency on customer content. Issue #2489.



= 1.1.35 =
* Three-zone responsive admin header, centered official developer information and own Markdown history; remove shop administrator URL configuration. Issue #2476.

= 1.1.34 =
* Restore the white/blue Analysis dashboard styles independently of the legacy SEO Analista callback; preserve all consent JSON settings of 1.1.33.


= 1.1.33 =
* Complete JSON roundtrip for consent settings: 65 translated fields, five country-specific WordPress legal page slugs, browser banner and legal review flags. Backups are portable between staging and production without changing page IDs; legal review and five published country-matched policy pages are required before enabling tracking.


= 1.1.32 =
* Consentimientos: downloadable JSON with current settings, blank template (65 text fields in ES/EN/FR/DE/NL), and secure JSON upload to populate the form and save into wp_options. Strict schema, nonce, administrator permission, HTTPS URL, rollback backup and legal revision. JSON never activates banner or confirms legal review.


= 1.1.31 =
* One-time safe import of 65 consent form strings (ES/EN/FR/DE/NL) from bundled JSON into WordPress wp_options, with backup and a secure JSON export action. Preserves custom edits, does not activate tracking or approve legal review.


= 1.1.29 =
* Escaped plugin-owned consent ledger table identifiers (compatible with WordPress 5.8), prepared all filter values, sanitized consent form inputs and retained uncached legal records. Simplified the streamed CSV response without changing recorded decisions or the schema.


= 1.1.28 =
* Remove plugin-owned editable consent settings and legal text on actual uninstall for each site in multisite, while retaining an independent immutable consent evidence ledger for legal accountability. No removal on deactivation.


= 1.1.27 =
* Added an optional, collapsed version history beneath the admin header title, reading only this plugin's bundled CHANGELOG.md. No new admin tab, Core dependency, data changes or external requests.


= 1.1.25 =
* Add Insights → Consentimientos: editable legal banner and accept/reject/configure button texts in ES/EN/FR/DE/NL. Admin-reviewed opt-in, cookie policy URL, category consent, withdrawal and consent V2 signalling for Clarity.
* Save all legal drafts in a single WordPress option; no custom database tables or visitor records. Install creates defaults, deactivation preserves them, and final uninstall deletes only the consent option. No tracking enabled automatically.
* Protect visitor-specific, consented PHP head output against shared caches while the built-in manager is active. Allow interoperability with WP Consent API where available.


= 1.1.24 =
* Fix Insights → Analysis to load its own reports and AJAX script after deactivating the old SEO monolith, using local Insights CSS and JS only.
* Keep the eleven existing report functions and the merged executive/roadmap button. No credential, head-tag, tracking or provider setting changes.


= 1.1.23 =
* Restore Cloudflare API connection and read-only security diagnostics in Insights using the existing encrypted WordPress option and optional wp-config constant. No re-entry of credentials necessary.
* Add a Cloudflare Connections card for save, read-only test, and status; legacy monolith functions and option are preserved for compatibility.


= 1.1.22 =
* Add a dedicated WooCommerce Ecommerce tab for independent GA4 event tracking. Uses an enabled GA4 head tag and statistics consent; no duplicate gtag.js and no reliance on the legacy SEO tracking switch.
* Restore a standalone WooCommerce event collector for the product, cart, checkout, shipping, payment and purchase funnel. Disabled until explicitly enabled by the administrator.


= 1.1.21 =
* Google Analytics 4 is loaded with WordPress wp_enqueue_script and wp_add_inline_script when statistics consent is granted; avoids the redundant script in wp_head and retains existing provider settings.

= 1.1.20 =
* Add Google Search Console verification, Google Analytics 4 and Google Tag Manager head tags from pasted provider snippets without executable HTML in the admin POST.
* Show a persistent editable tag inventory, including enable/disable/delete, and prevent duplicate measurement IDs.


= 1.1.19 =
* Save Microsoft Clarity head tracking tags using only the validated project ID, avoiding host firewall rejection of executable HTML in admin POST.


= 1.1.18 =
* Restore the editable head verification and tracking tags tab in Insights, preserving IndexNow and provider connections.


= 1.1.17 =
* Added optional per-site IndexNow with public ownership key, bounded queue and manual URL notification. Bing Webmaster API credentials are not required for IndexNow.

= 1.1.16 =
* Pestaña «Autocheck» con descarga JSON ya existente y conservación de las comparaciones antes/después.

= 1.1.15 =
* Cabecera administrativa unificada de TaxoCommerce con nombre, icono y versión.
* Estilos propios sin Core, funciones y permisos intactos.


= 1.1.14 =
* Use the plugin-specific TaxoCommerce prefix for the seven GEO helper functions; update Analysis, JSON export, AJAX bridge and regression tests without changing the recommendation logic.

= 1.1.12 =
* Align the published WordPress.org stable tag with the plugin's 1.1.12 code version, retaining existing provider connections and Analysis reports.

= 1.1.11 =
* Add dedicated Auto Test tab with grouped non-destructive checks for Insights, providers, reports, Trends and Analysis.
* Preserve a per-site, per-user BEFORE baseline across updates and compare it with AFTER results; export safe JSON reports.
* Clearly identify skipped external API and OAuth integration tests instead of reporting false success.


= 1.1.10 =
* WordPress.org Plugin Check remediation: fix migrated Analista text domains and translator notes, and use a safelisted OAuth redirect.
* Document nonce-protected admin handlers; improve input validation and preserve the existing connections, Analysis reports, Trends and manuals.
* Build a clean distribution ZIP without development tests or FTP synchronization metadata and add official Plugin Check to continuous integration.


= 1.1.9 =
* Add User Guide with separate User Manual and Technical Manual, responsive layout and indexed H2/H3 sections.
* Describe active Connections, Analysis, Search Console, Analytics, Bing and Trends workflows; report persistence and OAuth compatibility.
* Keep provider calls, credential handling, reporting and all existing functions unchanged.


= 1.1.8 =
* Add an automatic Google Trends report for Spanish clusters and primary hubs, building on the independent Trends workload manager.

= 1.1.7 =
* Scope Google Trends to Spain (geo=ES) by cluster/primary hub and run work with a bounded load manager.

= 1.1.6 =
* Show explicit provider connection states (connected, configured but unverified, pending, error and disabled), per-service checks and latest test timestamps.

= 1.1.5 =
* Restored the previous Analista 3.9.0 engine as the Insights > Analysis tab instead of rebuilding it.
* Preserved its 28/60/90-day views, lazy partial analyses, action plan, comparison, structure, content, trends and JSON export.
* Adapted legacy admin URLs and assets to the standalone Insights plugin.

= 1.1.4 =
* Removed Analyst, Executive Reports, Market, their supporting code, assets, and tests.
* Kept Connections, Google Search, Google Analytics, Bing, and Google Trends.
* Removed Market-only YouTube and SerpApi provider configuration.

= 1.1.3 =
* Reuses the authorized Google Search Console OAuth property and credentials already configured in the historic plugin, without copying or modifying its secrets.
* Tries explicitly paired modern OAuth, legacy OAuth, and service-account properties during administrator-initiated report downloads; a 403 from one identity no longer masks another valid connection.
* Preserves the previous report schema and saves the property/auth source used for transparency.
* Fixes the HTTP status inspection in WP_Error and adds deterministic regression tests for fallback behavior.

= 1.1.2 =
* Added a concise commercial decision panel before the detailed Analyst diagnostics, with KPIs, event funnel bars, and specific customer actions.
* Existing Prepared Intelligence and Market reports remain read-only sources. Missing data is unavailable, not zero; supplier commissions cannot be inferred from WooCommerce purchases.
* Added smoke checks for commercial summary contracts, navigation, and absent data.

= 1.1.1 =
* Added persistent Prepared Intelligence built from saved Search, GA4, Bing, Trends and Market evidence.
* Analyst now consumes prepared report data instead of re-running deterministic Search/GA4 preprocessing on page load.
* Market aggregation is cached and refreshed when new Market evidence is saved.
* Detailed provider reports remain the auditable source while Analyst receives compact prepared summaries and candidate rows.

= 1.1.0 =
* Recovered the clear diagnostic dashboard from the former Analista inside the modern Analyst tab.
* Added visibility index, ranking distribution, keyword movement, Search evolution, catalog URL types, and architecture counters from saved snapshots.
* Search snapshots now keep previous-period queries/pages and daily position so Top 10/50/100 movement is auditable.
* Fixed GA4 batchRunReports by splitting report requests into batches of at most five.
* Added GA4 ecommerce funnel event counts for product views, add-to-cart, checkout and purchase diagnostics.
* Renamed report retrieval actions to “Actualizar datos” for clearer manual refresh semantics.

= 1.0.0 =
* Consolidated compatible external connections into Insights.
* Added unified Connections and global Analyst reporting.
* Retained historical settings and OAuth callback contracts.

== Historical origins (monolith; not standalone releases) ==
* Legacy origin: Google Search Console, Google Analytics (GA4), Bing, Merchant and Trends connections were originally implemented in the SEO monolith; the 2.4.1 monolith was not an Insights 1.x release.
* The 2.3.7 monolith documented Google Trends and market research; the 2.3.9/2.4.0 line brought reporting, traffic evidence and editorial recommendations.
* Independent Google/Bing and Connections work migrated existing provider configuration, callbacks and stored reporting without requiring customers to recreate OAuth credentials.
* Connections became a compatibility bridge; Insights consolidated provider screens, manual downloads, status diagnostics and the historical Analysis/Analista reporting engine.
* The legacy Analysis dashboards were restored after an experimental executive-report replacement proved incomplete; original reporting workflows remained the baseline.
* Standalone Insights then added country-scoped Trends reports, user/technical manuals, non-destructive Auto Test, before/after comparisons and WordPress.org security/packaging corrections.

== Local plugin information ==

The header displays locally bundled Description and a link to the developer Plugin URI, without external requests. Google, Bing and other actual data connections remain independent and documented in the sections above.
