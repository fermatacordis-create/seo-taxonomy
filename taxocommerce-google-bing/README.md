# TaxoCommerce Insights

TaxoCommerce Insights is the reporting and decision-support layer of the TaxoCommerce family.

The plugin keeps its historical technical slug `taxocommerce-google-bing` for compatibility, but its displayed product name is **TaxoCommerce Insights**.

## Responsibilities

Insights owns external connections, reporting, market research, and strategic analysis.

Active areas:

- **Connections** — Google, Bing, Merchant, Amazon, Pinterest, YouTube, and SerpApi credentials/OAuth used by Insights and other TaxoCommerce modules.

- **Analyst** — executive market opportunities from measured Search Console, GA4 and Trends growth, official Merchant competitive visibility when eligible and assistant-referral comparison; specialist legacy SEO/Market diagnostics remain separately available. Read-only saved data, with explicit caveats about off-site affiliate conversions.
- **Executive Report** — consolidated local view across Search Console, GA4, Bing, Trends, and Market.
- **Google Search** — clicks, impressions, CTR, average position, countries, devices, queries, and pages.
- **Google Analytics** — sessions, active/new/returning users, views, engagement, channels, countries, devices, key events, and purchases.
- **Bing** — search traffic, queries, pages, crawl health, and index visibility.
- **Google Trends** — manual trend exploration and saved trend snapshots.
- **Market** — category-first demand evidence from Merchant and saved Trends, with Search Console and Bing signals. The Competition report does not crawl competitors or use paid SERP services.

## Connections

Connections are now configured directly in **TaxoCommerce Insights → Connections**.

The unified screen owns:

- Google Search Console / GA4 service account.
- Google Search Console OAuth.
- Google Merchant Center.
- Microsoft Bing Webmaster.
- Market providers: YouTube Data API and SerpApi.
- Amazon Associates / Creators API.
- Pinterest Business / OAuth / board selection.
- Pinterest Merchant domain verification and catalog feed URL.

The historical option names and public compatibility helpers are deliberately preserved. Existing credentials are read in place; users do not have to reconnect services after the consolidation.

The former `TaxoCommerce Connections` plugin is retained temporarily only as a compatibility bridge. When Insights is active it does not register a separate family module or connection screen; historical URLs redirect to `Insights → Connections`.

## Compatibility

The plugin directory, option names, internal tab keys, and callback contracts remain compatible with the former Google/Bing module. Internal identifiers such as `informe` and `mercado` are retained as technical keys only; the visible UI is English.

Opening report tabs never downloads business data automatically. External requests happen only through explicit download, refresh, or research actions.

## Language

English is the base source and UI language. Future localization should use standard WordPress gettext translations while preserving historical technical slugs and option names.

## Validation

The GitHub Actions workflow validates PHP syntax, local-only report rendering, executive aggregation, Trends behavior, Market independence, and package deployment to STAGING.

## Version 1.0.0

- Merged the standalone TaxoCommerce Connections capability into TaxoCommerce Insights.
- Added a visible **Connections** tab to Insights.
- Preserved all existing Google/Bing/Merchant option names and Google OAuth callback contracts.
- Embedded Amazon Associates / Creators and Pinterest Business connection services with their existing option names and legacy helper APIs.
- Added YouTube and SerpApi Market-provider credentials to the same Connections tab.
- Removed standalone Connections from the Core family extension catalog.
- Converted the old TaxoCommerce Connections plugin into a temporary compatibility bridge that redirects historical administration URLs to Insights.
- No credential migration or reconnection is required.

## Version 0.9.1

- Makes Analyst explicitly global: Market performs individual category/product research; Analyst aggregates the saved results.
- Adds a Global Market Summary with research coverage, pending/error counts, product signals, unique products/clusters, observed brands, and observed sellers.
- Aggregates repeated Merchant/offer product signals across all analyzed categories.
- Adds market/catalog gap prioritization by contrasting accumulated product signals with local WooCommerce category depth.
- Keeps detailed category evidence, Merchant leaders, and observed competitor domains below the global summary as supporting evidence.
- No provider calls are made by Analyst; all aggregation is local over saved Market data.

## Version 0.9.0

- Added **Analyst** inside Insights rather than creating another plugin.
- Rebuilt the useful former Analista workflow on top of current saved Search, Analytics, Bing, Trends, Market, and Merchant data.
- Restored a short work queue with **NOW / NEXT / INVESTIGATE / WATCH** buckets.
- NOW requires direct demand evidence; Market/Trends-only signals remain exploratory until confirmed.
- Added a stable local-only `TaxoCommerce_Google_Bing_Analyst::report()` contract for the future TaxoCommerce Editor.
- Analyst never downloads provider data when opened or exported.
- Market comparison is explicitly evidence-based and never presented as an external ranking unless the source actually provides one.

## Version 0.8.0

- Renamed the displayed product from TaxoCommerce Google/Bing to TaxoCommerce Insights.
- Removed connection ownership from the Insights navigation.
- Redirected the old Connections tab to TaxoCommerce Connections.
- Kept existing internal slugs/options for backward compatibility.
- Standardized active Insights UI text to English.

## Etiquetas dinámicas de cabecera (Head tags)

La pestaña **Etiquetas de cabecera** de Insights permite a un administrador con permisos `manage_options` y `unfiltered_html` pegar el **fragmento HTML completo** facilitado por Google, Bing, Microsoft Clarity u otro proveedor, sin editar `header.php`, archivos de tema ni plantillas.

- **Verificación**: admite exclusivamente etiquetas `<meta ...>` (por ejemplo, Search Console o Bing Webmaster) y las imprime en `wp_head` cuando la entrada está activa. No requieren cookies.
- **Seguimiento**: admite el código `<script>...</script>` del proveedor, pero solo se imprime con consentimiento de estadísticas afirmativo mediante WP Consent API (`wp_has_consent('statistics')`) o una integración explícita con el filtro `taxocommerce_insights_head_tracking_allowed`. Si no hay consentimiento conocido, no se imprime el script.
- Cada entrada dispone de nombre, tipo, editor de código, activación/desactivación y eliminación; el límite es de 20 entradas de hasta 12 KB. El sistema rechaza HTML de página, iframes y código PHP.
- No se copian scripts arbitrarios de fuentes desconocidas. En multisitio, el permiso `unfiltered_html` queda normalmente restringido a superadministradores.
- **Evitar duplicados:** si GA4 ya está habilitado mediante la integración existente, no se debe pegar de nuevo el mismo código de Google Analytics en esta pantalla. La integración de Analytics no se migra ni se sobrescribe.
- Las etiquetas se aplican a la web pública; no se insertan en las pantallas de administración ni en feeds. Cada instalación WordPress mantiene sus propias opciones.

Prueba de humo autocontenida: `php taxocommerce-google-bing/tests/head-tags-smoke.php` (sin llamadas a proveedores).
