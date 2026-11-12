=== TaxoCommerce Social ===
Contributors: davidperezmartorell
Tags: social-media, scheduling, pinterest, publishing, calendar
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.16
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage authorized social connections, publication templates and a content calendar.

== Description ==

* Admin header: plugin title, a single local `Description` with the static `Plugin URI` link below it (left); a wider collapsed `CHANGELOG.md` history (center); local version and release date (right). No duplicated information panel or external requests.

* The admin header shows the plugin's bundled `Description` and links to its `Plugin URI`, without network requests or caching. Version history uses the bundled `CHANGELOG.md`.

* One collapsible version-history control, without a duplicate plugin-information box.


TaxoCommerce Social supports optional Facebook, Instagram, LinkedIn, Pinterest and X connections, manual publication planning, CSV-based scheduling and queue/history views.

* Administration areas: Connections, Templates, Calendar, Auto Test and Manual.
* Auto Test: 16 local checks of providers, routes, tables, scheduler and security, without remote requests, publishing or file operations. Two recent results per administrator are stored for 30 days.
* Two distinct, indexed in-plugin manuals: step-by-step user guide and detailed technical reference for future developers, including architecture, hooks, data models, security and CI.
* Restores CSV import/export controls in Calendar: template, content, agenda and history exports; upload validation and explicit import confirmation. Existing manual publishing controls remain unchanged.
* Existing social settings and publication/schedule tables are reused on migrating sites.
* Dedicated local calendar; offer/campaign planning belongs to TaxoCommerce Campaigns.
* No social account is connected or published to by default. Administrators must authorize a provider and perform the action.

The plugin interacts with selected social network services when an administrator connects an account or starts publishing. External platforms operate under their own access tokens, API quotas and terms; configure only accounts you are authorized to manage.

== Installation ==

1. Install and activate TaxoCommerce Social.
2. Open Social > Connections and authorize only the desired providers.
3. Configure a publication template and inspect your social platform permissions.
4. Test a single scheduled publication before enabling automated operation.

== Privacy ==

Social publishing sends authorized content and associated account data to the selected external social network. Store tokens securely, review provider policies and avoid including personal information in generated posts without permission. Disconnect accounts when the integration is not required.

== Changelog ==

= 0.1.16 =
* Restore CSV import/export in the standalone Calendar tab, register secure administration actions, and retain the CSV validation/confirmation steps. Manual schedules and existing records are not modified by this update.


= 0.1.13 =
* Local Description and Plugin URI replace REST excerpt requests and caches in the header. Issue #2494.


= 0.1.12 =
* Display the last verified official excerpt from the developer's Plugin URI during HTTP errors; avoid stale negative cache and show bundled author Description on first-install network failure. No dependency on customer content. Issue #2489.



= 0.1.11 =
* Three-zone responsive admin header, centered official developer information and own Markdown history; remove shop administrator URL configuration. Issue #2476.

= 0.1.7 =
* Added an optional, collapsed version history beneath the admin header title, reading only this plugin's bundled CHANGELOG.md. No new admin tab, Core dependency, data changes or external requests.


= 0.1.5 =
* Nombre uniforme «Autocheck» y exportación JSON autenticada de los resultados con detalle por prueba.

= 0.1.4 =
* Cabecera administrativa uniforme de TaxoCommerce con nombre, icono y versión visible.
* Compatible sin Core; se conservan los controles, pestañas y permisos existentes.


= 0.1.3 =
* Security and quality: prepared SQL identifiers in Social and Scheduler, verified read-only parameters, documented real-time DB operations and preserved provider connections.


= 0.1.2 =
* Expand the in-plugin Manual into two separately headed, indexed user and future-developer guides with practical procedures and source-grounded technical contracts.
* Clarify that historical CSV backend handlers exist without a visible standalone Calendar CSV screen.
* Keep providers, credentials, existing tabs, scheduler, database and read-only Auto Test unchanged.


= 0.1.1 =
* Add in-plugin Auto Test with 16 safe local checks, two reports per administrator and version-to-version comparison.
* Integrate user and technical manuals without changing existing provider connections, publication or scheduling behavior.
* Add PHP 7.4/8.4 regression test and exclude development tests and FTP state from installable ZIP.


= 0.1.0 =
* Extracted social connections, templates and calendar into a standalone plugin.
* Preserved existing settings and history without deleting them on deactivation.

== Historical origins (monolith; not standalone releases) ==
* [Complete legacy and release history](https://github.com/distribuidordeherramientases-wq/seo-taxonomy/tree/main/taxocommerce-social) (changelog.txt / CHANGELOG.md).

* Legacy origin: social-network publishing, scheduling and commercial reporting were features of TaxoCommerce SEO before the independent Social plugin; those monolith versions are not Social standalone releases.
* The monolith 2.3.6 and 2.3.9 cycles documented social channels and marketing/reporting connections alongside commercial campaigns.
* Standalone 0.1.0 moved network integrations and scheduled publication into an independent module with its own bootstrap, navigation and Core registration.
* Staging and deployment work subsequently introduced a separate ZIP workflow and reconciled the canonical shared storage used by the former monolith and the new plugin.
* Marketing campaign planning and banners belong to Campaigns, while Social owns its authorised publishing actions; no external-network publication is implied merely by installing the module.

== Local plugin information ==

The developer information panel shows only bundled `Description` metadata and an outbound `Plugin URI` link. No external request is made.
