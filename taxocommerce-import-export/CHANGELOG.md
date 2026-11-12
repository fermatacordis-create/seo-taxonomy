## [0.4.29] — 2026-11-10 — Sin tareas programadas propias de importación/exportación (#2530)

- Eliminada la creación de acciones de Action Scheduler y WP-Cron para exportación CSV y cola de importación; conservados el procesamiento por bloques, autollamadas internas autenticadas, control adaptativo, pausas y cancelación.
- El watchdog reanuda un trabajo interrumpido al recibir nuevas peticiones a WordPress; ya no existe recuperación automática mediante tareas programadas si fallan las autollamadas internas.
- Limpieza puntual de los hooks de calendario anteriores del plugin, sin borrar datos, colas, CSV ni acciones de WooCommerce u otros plugins.
- seo_supplier_crawl_tick del monolito no se altera: necesita sustitución de su mecanismo de ejecución antes de retirarlo.

## [0.4.28] — 2026-11-10 — Cabecera en tres zonas sin contenido duplicado (#2521)

- Izquierda: nombre, descripción local del plugin y un único enlace estático `Plugin URI` bajo el texto.
- Centro: desplegable más ancho con el historial local `CHANGELOG.md`, cerrado inicialmente.
- Derecha: versión y fecha reales del propio plugin.
- Se retira la segunda caja «Información del plugin», sin cambios en servicios, menús ni consultas externas.

## [0.4.27] — 2026-11-10 — Descripción visible desde el plugin (#2494)

- La descripción inmediatamente debajo del título administrativo procede de `Description:` del archivo principal, sin textos duplicados ni consultas externas.
- Se mantienen el enlace estático `Plugin URI`, el historial local y las funcionalidades anteriores.

## [0.4.26] — 2026-11-10 — Ficha local en el encabezado (#2494)

- Elimina consultas REST/caché del extracto y usa `Description` y `Plugin URI` locales.
- Conserva el enlace oficial y el historial, sin alterar servicios existentes.

## [0.4.25] - 2026-11-10 - Resiliencia del extracto oficial (#2489)

- Consulta exclusivamente la API REST pública de la página indicada en Plugin URI (autor del plugin), nunca páginas de la tienda del cliente.
- Conserva el último extracto oficial comprobado; los fallos de red no borran el texto. Caché de errores reducida a 5 minutos y versión nueva de clave para ignorar fallos antiguos cacheados.
- Si nunca se ha obtenido un extracto, muestra provisionalmente la Description incluida por el autor en el plugin; permite abrir la ficha oficial. No cambia operaciones comerciales ni requiere Core.
- Pruebas para recuperación tras fallos HTTP, JSON inválido y caché negativa; compatibles con la familia completa.

## [0.4.24] — 2026-11-09 — Ficha oficial del desarrollador y cabecera centrada en tres zonas (#2476)

- La cabecera distribuye identificación a la izquierda, dos desplegables alineados y centrados (extracto oficial a la izquierda e historial local a la derecha), y versión y fecha a la derecha; distribución adaptable a móvil.
- El enlace oficial se obtiene exclusivamente de Plugin URI, definido por el autor del plugin. Eliminados el formulario de URL y su acción de guardado; los ajustes antiguos de la tienda no pueden sobrescribirlo.
- El historial se lee del CHANGELOG.md incluido, plegado de inicio. Sin dependencias obligatorias entre plugins ni cambios en productos, pedidos, datos o funcionalidades existentes.

## [0.4.23] — 2026-11-08 — Información dinámica desde ficha oficial (#2443)

- El desplegable izquierdo consulta el excerpt publicado en la página Plugin URI, con caché y configuración independiente.
- Formulario sanitizado; soporte para desactivación y funcionamiento sin servicios externos. Historial local sin cambios.

## [0.4.22] — 2026-11-08 — Extracto público remoto opcional (#2443)

- Consulta opcional por HTTPS del excerpt REST de una ficha oficial configurada; muestra el enlace a esa página en un panel izquierdo plegado.
- Mantiene el historial propio en el panel derecho. Caché, permisos y escape de salida; sin servicios obligatorios ni cambios en datos.

## [0.4.21] — 2026-11-08 — Protección contra textos vacíos en Import-Export (#2412)

- Recupera el texto original de botones, opciones y leyendas cuando un catálogo MO externo, antiguo o cacheado devuelve una traducción vacía.
- Protege exclusivamente el dominio taxocommerce-import-export, también traducciones de contexto y plurales; no altera datos, consultas ni tareas de exportación.
- Se mantienen los cinco catálogos MO reparados en 0.4.20 y se añade prueba CLI al pipeline.
- Pendiente validación de despliegue en STAGING, Focazul y PRO (esta última solo con autorización del workflow).

## [0.4.20] - 2026-11-08 - Autotest funcional y de codigo (#2401)

- Conserva autotest funcional y JSON; nuevo Code Check independiente del propio plugin: PHP, WordPress, seguridad. Cinco velocidades, lotes y JSON separado. Sin PCP ni Core obligatorio.
## [0.4.20] — 2026-11-08 — Corregir interfaz de exportación vacía por catálogos MO incompletos (#2404)

- Regenerados cinco MO válidos excluyendo las traducciones vacías que WordPress mostraba como botones y selectores sin texto.
- WordPress recupera el texto fuente cuando no hay traducción; se mantienen las traducciones existentes y el selector de Páginas para descargar sus contenidos en CSV.
- Test de regresión incorporado al workflow de STAGING. Los PO incompletos se conservan para el trabajo de traducción independiente; no se alteran páginas, productos ni pedidos.

## [0.4.19] — 2026-11-08 — Historial plegable en cabecera (#2382)

- Desplegable cerrado por defecto inmediatamente debajo del título, con el historial propio de `CHANGELOG.md`; sin nueva pestaña ni dependencia de otros plugins.
- Textos del título preparados para ES/EN/FR/DE/NL, salida escapada, desplazamiento vertical y mantenimiento de menús y funciones existentes.
- El paquete instalable y la subida incremental incluyen únicamente el historial raíz necesario. Sin cambios en base de datos ni servicios.

## [0.4.18] — 2026-11-07 — Cabecera administrativa de versión y fecha (#2336)

- Muestra título, versión activa y fecha de publicación en el administrador mediante Release Date.
- La fecha no depende de la hora de consulta ni del momento de copia por FTP. Sin cambios en servicios, datos, menús ni permisos.

## [Unreleased] — Revisión i18n (seguimiento #2062)

- Completada una entrada gettext previamente vacía en ES/EN/FR/DE/NL; conserva el mismo identificador fuente y no modifica la lógica de la tienda.
- Pendiente recompilar catálogos MO y validar cobertura frente al POT, en especial discrepancias entre idiomas; no supone traducción integral del plugin.

## [Unreleased] — Reutilización segura de traducciones (#2307)

- Completadas únicamente entradas gettext PO vacías con traducciones idénticas y no ambiguas ya existentes en otros catálogos TaxoCommerce.
- Regenerados los MO de los idiomas modificados. Se conservan los textos, identificadores técnicos, funciones y datos anteriores sin cambios.
- Esta corrección es parcial: los textos restantes, las cadenas fuera de gettext y la validación visual por idioma siguen abiertos en #2062.

## [Unreleased] — Regional WordPress locale fallback (#2295)

- Respeta primero los paquetes oficiales de WordPress .mo y .l10n.php para el locale exacto.
- Si falta un catálogo local regional, usa el catálogo existente del mismo idioma (es_ES, en_US, fr_FR, de_DE o nl_NL); no cambia la configuración ni el idioma del usuario.
- Sin cambios en lógica, datos, menús o dependencias del plugin. Las traducciones de contenido incompletas siguen abiertas en #2062.

## [0.4.17] — 2026-11-06 (Issue #2248)

- Corregida la cancelación de exportaciones CSV creadas antes de 0.4.16 sin token de autollamada: limpieza inmediata si no hay escritor activo, con bloqueo exclusivo y aislamiento por usuario.
- Las cancelaciones en curso se completan al quedar libre el escritor; la lectura de estado y el watchdog recuperan bloqueos huérfanos, sin borrar el CSV mientras se está escribiendo.
- Los trabajos fallidos o antiguos pueden regenerar su token en la reanudación; el siguiente bloque se despacha después de liberar el bloqueo para evitar pérdidas de continuidad.
- Pruebas de ejecución simulada con archivos reales: cancelación heredada, escritor activo, lock caducado, consulta de estado y separación de usuarios. Sin despliegue a producción.

## [0.4.16] — 2026-11-06 (Issue #2245)

- La exportación CSV deja de depender de AJAX repetido del navegador: un worker del servidor continúa por bloques mediante autollamadas internas del servidor, con recuperación mediante Action Scheduler de WooCommerce y WP-Cron.
- Se conservan los cinco perfiles de velocidad y el regulador adaptativo de tiempo y memoria; el progreso se consulta sin ejecutar filas desde la pantalla.
- Estado recuperable al volver a la pantalla, cancelación serializada con bloqueo del escritor, reintento manual tras errores y descarga autenticada del CSV terminado.
- Caducidad de estado y archivos temporales ampliada a 48 horas para exportaciones largas.
- WP-Cron/Action Scheduler requieren que WordPress pueda ejecutar tareas programadas; sin tráfico ni cron externo no se garantiza una cadencia de procesamiento.

## Unreleased - Familia i18n #2062


## [0.4.14] — 2026-11-03

- Pestaña «Autocheck» con descarga JSON ya existente, conservación de controles de seguridad y de comparaciones de regresiones.
- Issue #2150, PR #2151; rutas internas y ejecuciones existentes conservadas.

- Dominio gettext, carga de catalogos locales, etiquetas de menús y textos HTML de salida estáticos; catálogos PO/MO sincronizados con el texto fuente.

# CHANGELOG — TaxoCommerce Import-Export - Import & Export for WooCommerce

## [0.4.15] — 2026-11-04

- Issue #2157: Autocheck reducido a dos acciones manuales, «Ejecutar Autocheck» y «Descargar resultados (JSON)», igual que Distribution. Conserva los 35 casos, la última ejecución por usuario/sitio y el esquema JSON existente con fecha, versión y resultados.
- Eliminados de la interfaz y de las nuevas escrituras los pasos y comparaciones ANTES/DESPUÉS, los botones de chequeos por grupo y la doble descarga. La información histórica no se elimina y sigue siendo válida para comparaciones manuales entre JSON.
- Eliminada la segunda cabecera de administración; se conserva la cabecera común autónoma, pestañas y funcionalidades originales.
- Regresión aislada verifica dos controles, aislamiento de informes por sitio, fecha visible y no ejecución de importaciones, además de las verificaciones de carga y cola anteriores.


## [0.4.13] — 2026-11-03

- Cabecera común de la familia basada en Core: icono, nombre, descripción, versión y diseño adaptable.
- Navegación y funciones originales conservadas, sin dependencia de Core. Issue #2137.

> Historial maestro de versiones del plugin independiente. El origen monolítico se documenta en [`changelog.txt`](changelog.txt).

## [Unreleased]

## [0.4.12] - 2026-11-03 (#2127)

- Incorporado el CSV independiente `wp_categories` / `wp_category` para taxonomía WordPress `category`, junto a `product_categories` / `product_category` de WooCommerce. Importación individual y cola CSV multiarchivo; exportación directa y progresiva con filtro propio de categorías nativas.
- Se transportan nombre, slug, padre, descripción HTML nativa y extracto adicional `seo_excerpt` (lectura de `excerpt` como respaldo); `parent_slug` conserva la jerarquía entre sitios con IDs diferentes. No se crean tablas ni se requiere Core.
- Se anexan `excerpt` y `parent_slug` al final de los CSV WooCommerce para mantener el orden de las ocho columnas anteriores, incluidos país e imagen. Los CSV existentes que no traen campos adicionales siguen siendo admitidos.
- Se busca primero por slug dentro de la taxonomía seleccionada y se evita sobrescribir términos ajenos cuando hay colisiones de identificadores; el padre ausente da error explícito. Las columnas omitidas preservan descripción y extracto existentes.
- Añadidas pruebas sin base de datos para ida/vuelta, jerarquía, colisiones, extractos, campos omitidos y ambos tipos; CI y Auto Test extendidos. STAGING únicamente; no se despliega a Focazul ni a producción.

- Corregidos dos avisos de internacionalización de mensajes de errores CSV y saneada la entrada de descarga de registros, conservando nonce y validaciones de ruta.
- Justificadas las consultas existentes a taxonomías y metadatos para filtros de exportación: no se alteran consultas, cursores, rendimiento real ni datos.
- Auditoría global de Plugin Check y seguimiento en el issue #1925.

### Pendiente (sin publicar)

* El gestor de archivos muestra un resumen de errores junto a cada CSV en Fallidos y permite consultar los detalles guardados y descargar su JSON mediante un enlace autenticado.
* Los fallos generales del trabajador generan un registro JSON privado del archivo afectado; los registros antiguos ausentes se identifican sin inventar un motivo.
* Se limita la descarga al archivo CSV fallido exacto, con comprobaciones de permisos, nonce, ruta y tamaño del registro.

### 0.4.10
Sin novedades pendientes documentadas.


## [0.4.10]

* Add a read-only Manual tab with user and technical documentation matching TaxoCommerce family conventions.
* Keep the existing Auto Test name and checks unchanged; cover manual routing, access and content in CI.

## [0.4.9]

* Add Auto Test tab with independent, non-destructive checks for CSV, country fields, queued/individual imports, export jobs, Media ZIP, workload profiles and security.
* Save a per-user and per-site baseline before deployment, compare after deployment by check ID, and export sanitized JSON results.
* Explicitly mark end-to-end imports, real Media restoration, downloads and background execution as manual tests, without touching catalogue or active queue.

## [0.4.8]

* The batch importer now displays a read-only progress bar for the CSV currently being processed, based on its saved byte offset and actual file size, with the bytes left and number of CSVs pending.
* The progress bar updates on each existing AJAX response. No extra worker, extra row queries, cursor reset or import scheduling changes are introduced.
* The UI distinguishes file bytes read from products actually created or updated. A byte-based estimate cannot guarantee that the processed-row counter is correct.

## [0.4.7]

* Allows deleting an individual pending CSV while another file is processing. A brief, atomic filesystem mutex protects the claim-to-processing transition without locking the whole WooCommerce worker. The active file remains untouched and queue state remains consistent.
* Avoids WC_Product::save() on unchanged existing products while still updating country/supplier metadata, without skipping saves for new or modified products.
* Defers WordPress taxonomy recounts for each product block and flushes in a finally block; never disables WooCommerce hooks or product lookup synchronization.
* Records actual lookup, save and metadata SQL queries/time per batch, including skipped unchanged saves, for diagnosing slow imports. x10 row-size targets remain but do not imply x10 throughput.
* Adds filesystem regression for deletion during another worker, mutex races and queue stability, plus product no-op smoke.

## [0.4.6]

* Added a throttled watchdog for active queues that have not advanced for 90 seconds; attempts a safe loopback re-dispatch from normal WordPress requests without an open admin tab.
* Stored Unix heartbeat times independently of the dashboard timezone and exposed an explicit stalled-queue message.
* File uploads must still be in the private import queue and explicitly started; no arbitrary server files are imported automatically.

## [0.4.5]

* Add country_code to complete/reduced products, categories, posts and pages CSV exports.
* Import country_code (also pais_origen) and persist _seo_pais_origen; old CSVs retain their current assignments.
* Blank exported countries remain blank (no assumed ES assignment), with strict ES/US/FR/DE/NL validation on import.
* Importador CSV: escala x10 de los tamaños de lote en las cinco velocidades, tanto tamaño inicial como máximo, exclusivamente para la importación. Los tamaños de exportación no cambian.
* En trabajos ya iniciados, convierte el objetivo anterior de lotes (p. ej. 5->50, 6->60) sin borrar offsets. Los siguientes lotes pueden duplicar filas cuando completan dentro del presupuesto de tiempo y memoria (50->100->200).
* Ventana de ejecución por petición: hasta 25 segundos por defecto, siempre con margen respecto del límite PHP, y objetivos configurables de hasta 2000 filas en velocidad máxima. Un único trabajador; sin paralelismo inseguro.
* El tamaño se reduce si la memoria PHP está alta o cuando la duración real de los guardados de WooCommerce lo requiere. El porcentaje de CPU de PHP no representa la CPU global del servidor.
* Información adicional por lote: filas objetivo y reales, duración, tiempo medio de guardado y consultas SQL por fila. Aumentar el tamaño objetivo no implica que se multiplique por diez la velocidad de guardado de cada fila.

## [0.4.4]

* Increase CSV batch targets with adaptive sizing and measurable save/SQL diagnostics.

## [0.4.3]

* Pending CSV list includes a delete action for each queued file, with confirmation, exact-file nonce, capability check, and safe path validation.
* A delete is refused while the queue worker holds the lock or once the file has entered processing. Processing, imported and failed CSVs remain untouched.
* Deleting an individually queued CSV also updates its status; deleting the last pending file marks an otherwise empty active queue idle.

## [0.4.2]

* CSV imports: each successful block immediately dispatches a new signed local HTTP request; Action Scheduler and WP-Cron are recovery/watchdog paths, not pacing dependencies.
* Browser-side AJAX assistance drives the queue while the admin is open; live rows/sec, duration, next block and adaptation reason.
* Adaptive regulator starts with 10 rows at level 3, grows/shrinks according to measured duration and memory, does not interpret PHP-request CPU usage as global server pressure.
* Individual CSV import starts immediately after upload; explicitly queued/paused files still require the administrator to press Start.
* If the hosting blocks internal loopback and WordPress cron is disabled, unattended execution cannot be guaranteed without a real server cron.

## [0.4.1]

* Product CSV export resumes after interruptions by truncating incomplete blocks back to the last persisted byte offset, preventing duplicate rows.
* Export progress now distinguishes examined records, written rows, skipped products and completed blocks.
* Product export supports scheduled (`future`) status alongside publish, draft, pending, private and trash.
* Safely rejects in-flight jobs created before the new byte-checkpoint format instead of corrupting the CSV.

## [0.4.0]

* Added a dedicated Exportación tab.
* Added resumable AJAX export jobs processed in bounded blocks.
* Added full and reduced portable product export modes.
* Added product hierarchy/category/status filters using modular Core metadata.
* Added progress, retry, cancel and authenticated completed-file downloads.
* Kept the reduced inventory intentionally non-importable by the normal round-trip importer.

## [0.3.0]

* Migrated the sequential multi-file CSV queue into the modular Import-Export plugin.
* Added the Importación por lotes tab with multi-upload, start, pause, stop/resume and manual next-block controls.
* Added an independent bounded Action Scheduler/WP-Cron worker with no dependency on the legacy central process manager.
* Added built-in mini-importers for products, product categories, posts and pages.
* Added extension hooks for other TaxoCommerce plugins to register detectors and mini-importers.
* Added imported/failed file buckets and JSON sidecar diagnostics.

## [0.2.0]

* Added Media image ZIP import/export.
* Moved Media transport responsibility out of TaxoCommerce Images.
* Avoided early translation calls during Core registration.

## [0.1.1]

* Core family integration pilot.

## [0.1.0]

* Initial CSV import/export pilot.

## Fuentes

- [`changelog.txt`](changelog.txt), etapa monolítica.
- [`docs/PLUGIN-HISTORY-SOURCES.md`](../docs/PLUGIN-HISTORY-SOURCES.md), PR y referencias históricas.

## Actualizaciones

Para nuevas funcionalidades se edita este `CHANGELOG.md`. Antes de publicar, se debe reflejar el resumen de la versión en `readme.txt`, que es el único Changelog mostrado automáticamente por WordPress.org.
