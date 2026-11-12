## [0.2.28] — 2026-11-10 — Cabecera en tres zonas sin contenido duplicado (#2521)

- Izquierda: nombre, descripción local del plugin y un único enlace estático `Plugin URI` bajo el texto.
- Centro: desplegable más ancho con el historial local `CHANGELOG.md`, cerrado inicialmente.
- Derecha: versión y fecha reales del propio plugin.
- Se retira la segunda caja «Información del plugin», sin cambios en servicios, menús ni consultas externas.

## [0.2.27] — 2026-11-10 — Descripción visible desde el plugin (#2494)

- La descripción inmediatamente debajo del título administrativo procede de `Description:` del archivo principal, sin textos duplicados ni consultas externas.
- Se mantienen el enlace estático `Plugin URI`, el historial local y las funcionalidades anteriores.

## [0.2.26] — 2026-11-10 — Ficha local en el encabezado (#2494)

- Elimina consultas REST/caché del extracto y usa `Description` y `Plugin URI` locales.
- Conserva el enlace oficial y el historial, sin afectar al catálogo.

## [0.2.25] - 2026-11-10 - Resiliencia del extracto oficial (#2489)

- Consulta exclusivamente la API REST pública de la página indicada en Plugin URI (autor del plugin), nunca páginas de la tienda del cliente.
- Conserva el último extracto oficial comprobado; los fallos de red no borran el texto. Caché de errores reducida a 5 minutos y versión nueva de clave para ignorar fallos antiguos cacheados.
- Si nunca se ha obtenido un extracto, muestra provisionalmente la Description incluida por el autor en el plugin; permite abrir la ficha oficial. No cambia operaciones comerciales ni requiere Core.
- Pruebas para recuperación tras fallos HTTP, JSON inválido y caché negativa; compatibles con la familia completa.

## [0.2.24] — 2026-11-09 — Ficha oficial del desarrollador y cabecera centrada en tres zonas (#2476)

- La cabecera distribuye identificación a la izquierda, dos desplegables alineados y centrados (extracto oficial a la izquierda e historial local a la derecha), y versión y fecha a la derecha; distribución adaptable a móvil.
- El enlace oficial se obtiene exclusivamente de Plugin URI, definido por el autor del plugin. Eliminados el formulario de URL y su acción de guardado; los ajustes antiguos de la tienda no pueden sobrescribirlo.
- El historial se lee del CHANGELOG.md incluido, plegado de inicio. Sin dependencias obligatorias entre plugins ni cambios en productos, pedidos, datos o funcionalidades existentes.

## [0.2.23] — 2026-11-08 — Información dinámica desde ficha oficial (#2443)

- Plugin URI identifica la ficha oficial; el desplegable izquierdo consulta su excerpt de forma segura, con caché y opción de personalizar/desactivar.
- Corregida la sanitización del formulario y la compatibilidad con las comprobaciones; el historial local se conserva sin cambios.

## [0.2.22] — 2026-11-08 — Extracto comercial remoto configurable en cabecera (#2443)

- Desplegable izquierdo para obtener el extracto público de una página WordPress oficial, únicamente cuando el administrador configura su URL HTTPS, con enlace a la ficha de origen.
- Desplegable derecho de historial local, independiente del servicio externo. Validación y escape, límite temporal de petición y caché para evitar demoras.
- Sin dependencia de otros plugins, sin origen comercial predefinido y sin modificación de funcionalidades existentes.

## [0.2.21] — 2026-11-08 — Autotest con funcionalidad y código (#2401)

- Autotest conserva sus pruebas funcionales existentes y añade una segunda subpestaña «Código», que revisa solo Images.
- Tres análisis propios de solo lectura: PHP, WordPress y seguridad; comprueba sintaxis, estructura, rutas locales literales, símbolos duplicados y riesgos que precisan revisión.
- Limitación CPU con cinco niveles, detener/reanudar, JSON privado, sin dependencia de Plugin Check, Core u otros plugins TaxoCommerce.
- Motor independiente vendorizado desde plantilla versionada, para evitar dependencias comerciales y de instalación. Prueba aislada de regresión añadida.

## [0.2.20] — 2026-11-08 — Historial plegable en cabecera (#2382)

- Desplegable cerrado por defecto inmediatamente debajo del título, con el historial propio de `CHANGELOG.md`; sin nueva pestaña ni dependencia de otros plugins.
- Textos del título preparados para ES/EN/FR/DE/NL, salida escapada, desplazamiento vertical y mantenimiento de menús y funciones existentes.
- El paquete instalable y la subida incremental incluyen únicamente el historial raíz necesario. Sin cambios en base de datos ni servicios.

## [Unreleased] — Corrección final de estados i18n Images (#2062)

- Revisados los 5 catálogos contra POT y marcadores de formato. Traducido el estado «En curso» en DE y NL, que seguía sin msgstr, y regenerados ambos MO.
- Los cinco PO de Images están sin entradas gettext simples vacías y con claves idénticas al POT. Pendiente revisión de literales no extraídos y validación visual en WordPress.

## [Unreleased] — Finalización de los catálogos de Images (#2062)

- Añadidas otras 36 traducciones por locale (180 entradas adicionales) a los cinco idiomas y regenerados los MO. Las 257 cadenas no vacías de cada catálogo PO tienen traducción; se conservan 258 entradas por archivo incluyendo la cabecera gettext.
- Conservados todos los formatos de sustitución printf y los identificadores de origen. Se traducen mensajes largos, informes, control de lotes, asignación automática y advertencias operativas.
- El catálogo no demuestra cobertura de los textos PHP/JS que todavía no usen gettext ni la carga efectiva por locale. Verificar Plugin Check y funcionamiento en WordPress antes de publicar; no tocar producción.

## [Unreleased] — Traducciones de interfaz y documentación (#2062)

- Internacionalización de 136/137 cadenas por idioma en los cinco catálogos ES/EN/FR/DE/NL (682 traducciones nuevas). Pestañas de medios, gestión de imágenes, estados, informes, miniaturas, metadatos, auditoría, optimización y procesos por lotes.
- Regenerados y sincronizados cinco archivos GNU MO con 258 entradas por catálogo. Se mantienen las claves gettext y los marcadores de formato sin modificar PHP/JS, imágenes, ajustes, datos ni trabajos en segundo plano.
- Continúa pendiente el resto de las cadenas de interfaz, algunas descripciones extensas y cualquier literal fuera de gettext. Requiere Plugin Check estricto y pruebas visuales de idioma en STAGING/Focazul; no desplegar a producción.

## [0.2.19] — 2026-11-07 — Cabecera administrativa de versión y fecha (#2336)

- Muestra título, versión activa y fecha de publicación en el administrador mediante Release Date.
- La fecha no depende de la hora de consulta ni del momento de copia por FTP. Sin cambios en servicios, datos, menús ni permisos.

## [Unreleased] — Revisión i18n (seguimiento #2062)

- Completada una entrada gettext previamente vacía en ES/EN/FR/DE/NL; conserva el mismo identificador fuente y no modifica la lógica de la tienda.
- Pendiente recompilar catálogos MO y validar cobertura frente al POT, en especial discrepancias entre idiomas; no supone traducción integral del plugin.

## [Unreleased] — Reutilización segura de traducciones (#2307)

- Completadas únicamente entradas gettext PO vacías con traducciones idénticas y no ambiguas ya existentes en otros catálogos TaxoCommerce.
- Regenerados los MO de los idiomas modificados. Se conservan los textos, identificadores técnicos, funciones y datos anteriores sin cambios.
- Esta corrección es parcial: los textos restantes, las cadenas fuera de gettext y la validación visual por idioma siguen abiertos en #2062.


## [Unreleased] — Internationalisation des images (#2315)

- Traducción inicial de 65 cadenas críticas de Image Management en español, inglés, francés, alemán y neerlandés: errores, permisos, operaciones sobre Media, asignación automática y Auto Test.
- Se conservan los parámetros numerados de `sprintf` y la lógica funcional.
- Pendientes las restantes entradas del catálogo, los textos JavaScript y las pruebas de idioma real.

## [Unreleased] — Regional WordPress locale fallback (#2295)

- Respeta primero los paquetes oficiales de WordPress .mo y .l10n.php para el locale exacto.
- Si falta un catálogo local regional, usa el catálogo existente del mismo idioma (es_ES, en_US, fr_FR, de_DE o nl_NL); no cambia la configuración ni el idioma del usuario.
- Sin cambios en lógica, datos, menús o dependencias del plugin. Las traducciones de contenido incompletas siguen abiertas en #2062.

## Unreleased - Familia i18n #2062


## [0.2.18] — 2026-11-04

- Eliminada la segunda cabecera duplicada en el administrador de Images. Se conserva únicamente la cabecera homogénea con nombre, descripción y versión.
- Sin cambios en pestañas, regulación de velocidad, auditoría, inventario, Autocheck ni datos. Issue #2178.

## [0.2.17] — 2026-11-03

- Nombre uniforme «Autocheck» y exportación JSON autenticada del último diagnóstico, sin ejecutar operaciones de escritura o procesos comerciales.
- Issue #2150, PR #2151; rutas internas y ejecuciones existentes conservadas.

- Dominio gettext, carga de catalogos locales, etiquetas de menús y textos HTML de salida estáticos; catálogos PO/MO sincronizados con el texto fuente.

# CHANGELOG — TaxoCommerce Images - Image Management for WooCommerce

## [0.2.16] — 2026-11-03

- Cabecera común basada en TaxoCommerce Core: identidad, icono, descripción y versión con estilo responsive.
- Enlaces, pestañas, permisos y lógica interna conservados; implementación autónoma sin requerir Core. Issue #2137.

> Historial maestro de versiones del plugin independiente. El origen monolítico se documenta en [`changelog.txt`](changelog.txt).

## [Unreleased]

- Limpieza controlada de STAGING (issue #1937): eliminar exclusivamente restos de pruebas de desarrollo bajo `tests/` y el antiguo `.ftp-deploy-sync-state.json` del plugin instalado. Inventario, copia de seguridad SHA-256 y verificación remota obligatorios. Se conservan las clases operativas de `includes/`, los datos y las pruebas del repositorio; sin intervención en PRODUCCIÓN.

* Corregida la limpieza del despliegue en producción: retirar únicamente `tests/early-i18n-smoke.php` y `tests/functional-contract.php` si permanecen en el directorio real de Images después de actualizar el ZIP.
* Verificar nombres, firmas y ausencia en el paquete antes de eliminarlos; crear y verificar una copia ZIP en el directorio temporal privado de WordPress. Conservar las pruebas en GitHub y los demás archivos y datos del plugin.
* Ningún cambio en las funcionalidades de Images, en la versión 0.2.15 ni en el proceso de validación de WordPress.org.


## [0.2.15]

* Updated the prefixes of 64 internal legacy helper functions and their callers to avoid namespace collisions.
* Preserved four previous WebP and optimizer filter hooks for third-party compatibility, adding new fully prefixed alternatives.
* Removed the costly post ID exclusion from duplicate lookup and kept protection against retrying the same attachment.
* Reviewed and documented the live and atomic SQL operations used for Media reference auditing, conversion rollback, cursor control and safe locks; these operations must not reuse stale cached results.
* Added WordPress.org installation instructions without removing image-management functionality.

## [0.2.14]

* Correct plugin packaging: exclude developer tests and FTP synchronization metadata from installed plugin.
* Sanitize submitted workload priority and explicitly verify nonces in the two image-assignment form handlers.
* No changes to image processing, audits, WebP transformations or background jobs.

## [0.2.13]

* Add read-only Manual tab with comprehensive administrator and developer documentation, headings and anchor index.
* Preserve all existing image processing actions, tabs, workers, permissions, and Media behavior.

## [0.2.12]

* Added an Auto Test tab with 17 independent image-management checks, the Core-style traffic-light display and a temporary per-user result.
* Checks do not mutate images, jobs, attachments or image metadata.

## [0.2.11]

* Added server-side persistent background jobs for audit, labels, duplicates, assignment, optimizer and WebP.
* Preserved the 1-5 workload regulation and locking; browser is not responsible for chaining batches.
* Added independent state polling, pause, resume and stop controls.
* No FTP or production deployment is implied by the GitHub change.

## [0.2.10]

* Media audit in Images > Anomalías has a dedicated five-speed adaptive load regulator, independent from assignment, labels and cleanup jobs.
* Automatically sizes batches from 25 to 1200 attachments depending on the selected speed and observed time, request-local CPU and memory pressure; no longer fixed at 75.
* The audit keeps its scan cursor and existing findings when the administrator changes speed. No attachments are modified or removed.
* With the audit tab open, a sequential AJAX continuation processes the next batch immediately. Pause and resume controls apply to this browser tab; the manual Next batch action remains available.
* Live progress shows processed images, last batch size and duration, next batch goal, throughput and PHP load. Host-wide CPU is not estimated by the PHP worker.
* Existing per-attachment anomaly checks remain unchanged.

## [0.2.9]

* Changed unused/duplicate analysis and labels/attributes review into full-library jobs that continue automatically until the complete Media snapshot is scanned.
* Kept adaptive 1-5 CPU/memory regulation as an internal batching mechanism only; batch size no longer reads as the scope of the report.
* Added explicit KPI/report counts for total/scanned images, used/unused media, exact SHA-256 duplicates, duplicate keepers, missing ALT, titles to review, dimensions, missing files and review cases.
* Added report filters for actionable findings, unused images, duplicate groups, used images, review cases and all rows.
* Duplicate detection remains exact original-file SHA-256 comparison; names, dimensions and file sizes alone never classify a duplicate.

## [0.2.8]

* Added independent adaptive workload control for bounded local image scans.
* Added request-local CPU, memory and query observations without hosting-level CPU dependencies.
* Kept Images workload priority independent from Dependiente and other modules.

## [0.2.7]

* Added explicit WordPress Media selection for individual image assignment.
* Preserved the rule that products never borrow another product's image automatically.

## [0.2.5]

* Removed the hosting-dependent CPU checker and /proc/stat gating.
* Long-running audits use bounded batches, persistent cursors, request pacing and mutation locks.
* Image anomaly, label/attribute and unused/duplicate scans register in TaxoCommerce Core Processes.

## [0.2.4]

* Previous CPU checker release; superseded by 0.2.5 because hosting-level CPU metrics are not portable from WordPress.

## [0.2.3]

* Products only reuse their own gallery; missing product images stay reported.
* 25-row reports, full KPIs, and resumable cursors past unresolved rows.
* Replaced HTTP inspection with local ALT, title and dimensions review.
* Full-library duplicate/unused scan, persistent results and exact original hashes.
* No new permanent JPG backups or administrator history.
* Automatic deletion/consolidation is not enabled; cleanup remains read-only.

## [0.2.1]

* Four separately paginated reports: Pages, Posts, Categories and Products without assigned images.
* Each candidate is restricted to an actual category relation: first its category thumbnail, then local Media belonging to products in that category.
* Assign a selected Media image individually or apply the first valid category candidate automatically to up to 25 missing items.
* Upload a local image through WordPress Media and associate it with one chosen item.
* Preserve the existing images and avoid all supplier-image catalogs, remote imports and random global Media suggestions.
* Works standalone, with WooCommerce categories and products when that plugin is active.

## [0.2.0]

* Restored isolated image reference audit from the historical plugin (review only; no deletion).
* Restored legacy recompression of originals and thumbnails, in small administrator-triggered batches.
* Restored JPG/PNG-to-WebP conversion and redirects for known internal legacy links.
* Added future-thumbnail exclusion settings, without removing existing thumbnails.
* Added bounded HTTP availability checks for images linked from published pages, posts and products.
* Removed all supplier-image indexing and provider image inventory from Images.
* This release must pass independent WordPress Plugin Check and staging validation before distribution.

## [0.1.3]

* Read-only Media audit now uses bounded, resumable ID cursors.
* Detect missing physical thumbnail files and missing dimensions metadata.
* Keep unattached images distinct from images actually unused.
* A bounded AJAX continuation runs the audit automatically, with manual fallback.

## [0.1.1]

* Removed external URL / Media import from the Images extension.
* Media import/export is delegated to TaxoCommerce Import-Export.
* Images now focuses only on audit, metadata, thumbnails and optimization.

## [0.1.0]

* Initial modular pilot.

## Fuentes

- [`changelog.txt`](changelog.txt), etapa monolítica.
- [`docs/PLUGIN-HISTORY-SOURCES.md`](../docs/PLUGIN-HISTORY-SOURCES.md), PR y referencias históricas.

## Actualizaciones

Para nuevas funcionalidades se edita este `CHANGELOG.md`. Antes de publicar, se debe reflejar el resumen de la versión en `readme.txt`, que es el único Changelog mostrado automáticamente por WordPress.org.
