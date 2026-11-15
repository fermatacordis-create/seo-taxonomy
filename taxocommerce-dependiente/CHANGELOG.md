## [0.3.44] — 2026-11-13 — Code Check equiparado a Core 0.4.82 (#2626)

- Code Check equiparado a Core 0.4.82: reglas autónomas PHP/WordPress/seguridad, clasificación por gravedad, detalles de archivos y recomendaciones, resumen y JSON; sin modificar el Autotest funcional. Issue #2626.
- Continúa siendo una revisión estática independiente del Plugin Check oficial, con ejecución por bloques y control de CPU.

## [0.3.43] — 2026-11-13 — Traducciones del manual de usuario y controles de aprendizaje (#2062)

- Añade 46 traducciones por idioma en ES/EN/FR/DE/NL (230 entradas) del manual: inicio del aprendizaje, prioridades, lecciones, intérprete, mantenimiento M12, calidad y reevaluación, buscador, conocimiento portable, backups, logs y preguntas frecuentes.
- Conserva numeración de las secciones 7–11 en los cinco idiomas y los identificadores técnicos, y recompila los MO sin entradas vacías.
- Refuerza el test de gettext para detectar la ausencia de la sección de búsqueda en la documentación traducida, sin modificar lógica, datos, cron, aprendizaje, WooCommerce ni importación.
- Restan 136/137/138/136/136 entradas gettext pendientes (ES/EN/FR/DE/NL); no se declara completada la interfaz ni validado cada literal de PHP/JS.

## [0.3.42] — 2026-11-12 — Traducciones de conocimiento, copias y controles de Dependiente (#2062)

- Traduce 87 cadenas adicionales en los cinco idiomas ES/EN/FR/DE/NL (435 entradas gettext). Incluye restauración, backup, conocimiento portable, importación/fusión, aprendizaje, categorías de documentación y títulos de manuales.
- Mantiene intactas las reglas de aprendizaje, la base de datos, procesos L1-L10/M11/M12, seguridad, API y los datos existentes. Los MO se regeneran con solo las cadenas traducidas y cabeceras válidas.
- Amplía el test i18n para exigir la traducción de controles y documentación en los cinco idiomas.
- Persisten 182/183/184/182/182 cadenas vacías en los PO (ES/EN/FR/DE/NL), principalmente textos descriptivos largos, más los textos todavía no extraídos desde PHP/JS.

## [0.3.41] — 2026-11-10 — Cabecera en tres zonas sin contenido duplicado (#2521)

- Izquierda: nombre, descripción local del plugin y un único enlace estático `Plugin URI` bajo el texto.
- Centro: desplegable más ancho con el historial local `CHANGELOG.md`, cerrado inicialmente.
- Derecha: versión y fecha reales del propio plugin.
- Se retira la segunda caja «Información del plugin», sin cambios en servicios, menús ni consultas externas.

## [0.3.40] — 2026-11-10 — Descripción visible desde el plugin (#2494)

- La descripción inmediatamente debajo del título administrativo procede de `Description:` del archivo principal, sin textos duplicados ni consultas externas.
- Se mantienen el enlace estático `Plugin URI`, el historial local y las funcionalidades anteriores.

## [0.3.39] — 2026-11-10 — Ficha local en el encabezado (#2494)

- Elimina consultas REST/caché del extracto y usa `Description` y `Plugin URI` locales.
- Conserva el enlace oficial y el historial, sin alterar servicios existentes.

## [0.3.38] - 2026-11-10 - Resiliencia del extracto oficial (#2489)

- Consulta exclusivamente la API REST pública de la página indicada en Plugin URI (autor del plugin), nunca páginas de la tienda del cliente.
- Conserva el último extracto oficial comprobado; los fallos de red no borran el texto. Caché de errores reducida a 5 minutos y versión nueva de clave para ignorar fallos antiguos cacheados.
- Si nunca se ha obtenido un extracto, muestra provisionalmente la Description incluida por el autor en el plugin; permite abrir la ficha oficial. No cambia operaciones comerciales ni requiere Core.
- Pruebas para recuperación tras fallos HTTP, JSON inválido y caché negativa; compatibles con la familia completa.

## [0.3.37] — 2026-11-09 — Ficha oficial del desarrollador y cabecera centrada en tres zonas (#2476)

- La cabecera distribuye identificación a la izquierda, dos desplegables alineados y centrados (extracto oficial a la izquierda e historial local a la derecha), y versión y fecha a la derecha; distribución adaptable a móvil.
- El enlace oficial se obtiene exclusivamente de Plugin URI, definido por el autor del plugin. Eliminados el formulario de URL y su acción de guardado; los ajustes antiguos de la tienda no pueden sobrescribirlo.
- El historial se lee del CHANGELOG.md incluido, plegado de inicio. Sin dependencias obligatorias entre plugins ni cambios en productos, pedidos, datos o funcionalidades existentes.

## [0.3.36] — 2026-11-09 — Ampliación i18n del panel Dependiente (#2062)

- Completadas 87 cadenas de control en ES/EN/FR/DE/NL para aprendizaje L1–L10, M11/M12, autotest, diagnósticos y copia de seguridad.
- Recompilados cinco catálogos MO/PO, respetando el catálogo original y marcadores gettext/printf; prueba de regresión del catálogo incluida en CI.
- Preservados los cambios de 0.3.35 de ficha oficial y cabecera, sin alterar aprendizaje, datos ni procesos.
- Sigue pendiente la traducción de las demás cadenas y la auditoría de literales fuera de gettext.

## [0.3.35] — 2026-11-08 — Información dinámica desde ficha oficial (#2443)

- El desplegable izquierdo consulta el excerpt publicado en la página Plugin URI, con caché y configuración independiente.
- Formulario sanitizado; soporte para desactivación y funcionamiento sin servicios externos. Historial local sin cambios.

## [0.3.34] — 2026-11-08 — Extracto público remoto opcional (#2443)

- Consulta opcional por HTTPS del excerpt REST de una ficha oficial configurada; muestra el enlace a esa página en un panel izquierdo plegado.
- Mantiene el historial propio en el panel derecho. Caché, permisos y escape de salida; sin servicios obligatorios ni cambios en datos.

## [0.3.33] - 2026-11-08 - Autotest funcional y de codigo (#2401)

- Conserva autotest funcional y JSON; nuevo Code Check independiente del propio plugin: PHP, WordPress, seguridad. Cinco velocidades, lotes y JSON separado. Sin PCP ni Core obligatorio.

## [0.3.32] — 2026-11-08 — Historial plegable en cabecera (#2382)

- Desplegable cerrado por defecto inmediatamente debajo del título, con el historial propio de `CHANGELOG.md`; sin nueva pestaña ni dependencia de otros plugins.
- Textos del título preparados para ES/EN/FR/DE/NL, salida escapada, desplazamiento vertical y mantenimiento de menús y funciones existentes.
- El paquete instalable y la subida incremental incluyen únicamente el historial raíz necesario. Sin cambios en base de datos ni servicios.

## [0.3.31] — 2026-11-07 — Cabecera administrativa de versión y fecha (#2336)

- Muestra título, versión activa y fecha de publicación en el administrador mediante Release Date.
- La fecha no depende de la hora de consulta ni del momento de copia por FTP. Sin cambios en servicios, datos, menús ni permisos.

## [Unreleased] — Reutilización segura de traducciones (#2307)

- Completadas únicamente entradas gettext PO vacías con traducciones idénticas y no ambiguas ya existentes en otros catálogos TaxoCommerce.
- Regenerados los MO de los idiomas modificados. Se conservan los textos, identificadores técnicos, funciones y datos anteriores sin cambios.
- Esta corrección es parcial: los textos restantes, las cadenas fuera de gettext y la validación visual por idioma siguen abiertos en #2062.

## [Unreleased] — Regional WordPress locale fallback (#2295)

- Respeta primero los paquetes oficiales de WordPress .mo y .l10n.php para el locale exacto.
- Si falta un catálogo local regional, usa el catálogo existente del mismo idioma (es_ES, en_US, fr_FR, de_DE o nl_NL); no cambia la configuración ni el idioma del usuario.
- Sin cambios en lógica, datos, menús o dependencias del plugin. Las traducciones de contenido incompletas siguen abiertas en #2062.

## Unreleased - Familia i18n #2062


## [0.3.30] — 2026-11-06 (Issue #2252)

- La regresión lingüística de M11 utiliza índices por primera palabra y concepto de acción, sin recorrer las más de 33.000 expresiones activas por cada consulta. La memoria se invalida tras altas o actualizaciones.
- Cada expresión de la regresión activa o la fase de hipótesis guarda un marcador antes de evaluarse y confirma el cursor y los contadores al terminar, sin doble cómputo desde el supervisor.
- Ante dos interrupciones consecutivas del mismo identificador, se registra el caso como deuda de evaluación (rechazado y en cuarentena), se avanza y se mantiene el progreso de L1–L10; errores PHP recuperables quedan asociados al identificador.
- Se registran identificador y duración de evaluaciones lentas, preservando el curso anterior, las cinco velocidades y la memoria aprendida.
- Prueba autónoma con 33.409 expresiones y recuperación de cursor 348 tras interrupciones; sin modificar datos de ninguna tienda.

## [0.3.29] — 2026-11-06

- Mostrar 100 % de progreso para snapshots de lecciones vacías (`skipped_empty`) ya cerradas, con 0/0 elementos y sin aprobar exámenes ni crear aprendizaje ficticio.
- Los estados pendientes, en ejecución y con error conservan su progreso real. No cambia el motor L1-L10 ni el mantenimiento incremental M12. Issue #2243.

## [0.3.28] — 2026-11-05

- Las lecciones con cero fuentes se registran como `skipped_empty`, sin puntuación ficticia ni conocimiento inventado; la formación continúa en la siguiente lección.
- L1 puede aprender categorías sin productos. Se elimina la restricción introducida en 0.3.27 para arrancar cursos manuales sin productos.
- Rescate autónomo y limitado de WP-Cron cuando el supervisor no está disponible: máximo cuatro ticks por visita autorizada al panel y 2 segundos por petición; respeta bloqueo del trabajador y no reinicia el curso.
- El panel distingue «Sin material» y «Lección omitida; no evaluada», conservando snapshots y auditoría.

## [0.3.27] — 2026-11-05

- Guardas de Inicio/Reinicio: sin productos publicados no se inicia una formación imposible y no se borran los datos del cliente.
- El watchdog informa del catálogo vacío en cursos recién iniciados y cambia el estado a error, conservando snapshots ya existentes.
- El panel actualiza el progreso también durante la foto fija y el aprendizaje (20 segundos; no interrumpe la edición de formularios).
- Pruebas de tienda vacía, con producto y recuperación sin pérdida de datos.

## [0.3.26] — 2026-11-05

- Primera instalación con al menos un producto WooCommerce publicado: **programa** el aprendizaje L1–L10 por lotes desde la tienda local, tras crear los esquemas vacíos. No ejecuta un escaneo masivo durante la activación.
- Sin productos publicados: queda en reposo sin inventar conocimiento.
- Reactivaciones, actualizaciones y sitios con conocimiento o cursores previos: no reinician aprendizaje ni borran datos.
- Prueba aislada de arranque, ausencia de productos, registros preexistentes y errores SQL; se mantienen los controles Iniciar/Detener y el regulador existente.

## [0.3.25] — 2026-11-05

- Instalación nueva: crea el esquema del léxico y sus evidencias **sin insertar las 17 expresiones de herramientas de prueba**, que procedían del desarrollo de la tienda original.
- Retirado del plugin independiente el método de semilla sectorial. Se conserva el aprendizaje L1–L10 y M11 desde los productos, categorías, etiquetas y Vocabulary **del WordPress del cliente**, cuando el administrador lo inicia.
- Actualizaciones no eliminan los conocimientos ni las evidencias ya existentes. Los registros históricos de Focazul no se borran automáticamente.
- Prueba de regresión ejecutable: primera instalación vacía, reconciliación del esquema sin pérdida de registros y existencia de los lectores del catálogo local. Issue #2195.

## [0.3.24] — 2026-11-03

- Unifica «Autocheck» y permite descargar JSON autenticado de los resultados de la última ejecución, sin modificar servicios ni contenidos.
- Issue #2150, PR #2151; rutas de pestaña y acciones previas conservadas.

- Dominio gettext, carga de catalogos locales, etiquetas de menús y textos HTML de salida estáticos; catálogos PO/MO sincronizados con el texto fuente.

# CHANGELOG — TaxoCommerce Assistant

## [0.3.23] — 2026-11-03

- Cabecera común de la familia basada en Core: icono, nombre, descripción, versión y diseño adaptable.
- Navegación y funciones originales conservadas, sin dependencia de Core. Issue #2137.

> Historial maestro de versiones del plugin independiente. El origen monolítico se documenta en [`changelog.txt`](changelog.txt).

## Versiones independientes

### Pendiente de publicación

* Sanitize the admin-post L1-L10 quality thresholds recursively with WordPress `map_deep()` and `sanitize_text_field()` before applying existing strict numeric validation; resolves the Plugin Check unsanitized `$_POST['thresholds']` warning.
* Add a regression guard for the sanitized control input. Preserve saved thresholds, lesson scores, user permissions, nonces and explicit quality-acceptance behavior.

### 0.3.22

* Allow administrators to set an independent Top-8 quality threshold for each L1-L10 lesson (0–100%), preserving previous defaults.
* Provide explicit, audited acceptance of an existing failed lesson once its actual result meets the new chosen threshold.
* Preserve exam scores, failed examples and source content while advancing without retraining completed lessons.

### 0.3.21

* Replace the dynamic L8/L10 UNION review query with a fixed, fully prepared SQL statement; preserve per-lesson sample caps and pagination.
* The packaged plugin passed strict Plugin Check with zero errors and zero warnings after this change.

### 0.3.20

* Document the safety of prepared L8/L10 review SQL and the atomic, owner-checked release of learning worker locks.
* Retain learning state and concurrency safety while addressing strict Plugin Check findings.

### 0.3.19

* Keep CI test scripts and hidden FTP state outside WordPress.org release packages.
* Validate learning priority input and safely quote internal learning table identifiers.

### 0.3.18

* Add a responsive User Guide tab with separate User Manual and Technical Manual inside Dependiente.
* Explain learning, quality recovery, Interpreter M11, M12, search and feedback, backups, plugin architecture and data contracts.
* Preserve all training controls, imports, exports, cron and frontend behavior.



* Auto Test reports functional readiness separately from educational outcomes and historical process errors. M11, learning and M12 remain green when endpoints, workers and state contracts are available, with diagnostic detail.
* A bounded search that returns no candidates is marked as inconclusive instead of reporting a broken search engine solely from its result set.
* No training, feedback, content or backup mutations are performed by Auto Test.

### 0.3.17

* Add 51 non-destructive Auto Test checks in ten groups, covering runtime, schema, learning sources, quality, interpreter, search, feedback, M12 and backup.

### 0.3.16

* Port the original Interpreter 1.2.0 runtime and full Linguist course into the independent Assistant while preserving legacy functionality and training state.

### 0.3.15

* Raised the adaptive maximum-speed learning ceiling from 79 to 600 items per chunk, starting from 47 and growing only when duration and memory allow it.
* Avoid reducing a fast worker simply because its own PHP process has high CPU utilisation: it is not the hosting account CPU.
* The manager can execute up to four bounded learning ticks per callback on maximum speed, subject to its runtime budget, stop flag and locks.
* The previous learning snapshots, results, cursor and quality gates remain unchanged.

### 0.3.14

* M11 validates 180 distributed L9 queries with the current family/product evaluator instead of 100 obsolete stored queries.
* Resume M11 regression in CPU-limited batches without restarting any of the ten lessons.
* Add M11 audit JSON, progress counters, failure evidence, revision and version tracking.

### 0.3.13

* Record evaluator revision, actual PHP plugin version and L9 query provenance in quality JSON reports.
* Flag old L9/L10 audit results as stale and allow repeat of only the current quality examination.
* Preserve existing L1-L9 snapshots and the 90% quality gate for L10.

### 0.3.12

* Rebuild L9 evaluation needs deterministically from sealed learned facts, instead of reusing historical legacy questions.
* Treat non-unique colour/material hints as family requests; retain exact-product requests for model/measure/capacity.
* Fix generic repair-token ranking bias; include per-result evidence and per-run query provenance in L9 audit.
* Re-evaluation does not clear L1-L8 memory or change source catalog content.

### 0.3.11

* Stabilise the L9 evaluation sample and expose the actual plugin version in diagnostics.

### 0.3.10

* Make L6 FAQ reading tolerant of optional columns in legacy and modern seo_faq tables.
* Diagnose empty, inactive, incompatible, or unreadable FAQ sources in the learning log without modifying FAQ content.
* Verify FAQ source compatibility with an executable regression for both database layouts.

### 0.3.9

* Make FAQ schema compatibility queries explicit and fully prepared.
* Remove dynamic SQL condition fragments from semantic and identity lookups.
* Harden streamed knowledge export output for WordPress Plugin Check.

### 0.3.8

* L6 reads both current and historical `seo_faq` schemas during migration.
* Falls back to `0 AS product_cat_id` when the legacy FAQ table has not yet been upgraded.
* Reports SQL read failures explicitly instead of misreporting them as an empty training source.

### 0.3.7

* Standalone auditable L1-L10 learning and M11/M12 semantic search.
* Knowledge transfer and local backup interfaces.
* Explicit shortcode for parallel testing.

### 0.3.6

* Extend structured learning audit reports and correction queues consistently across lessons L1–L10.

### 0.3.0

* Launch the independent learning architecture with L1–L10 snapshots, M11 Interpreter and M12 continuous update.

## Fuentes

- [`changelog.txt`](changelog.txt), etapa monolítica.
- [`docs/PLUGIN-HISTORY-SOURCES.md`](../docs/PLUGIN-HISTORY-SOURCES.md), PR y referencias históricas.

## Actualizaciones

Para nuevas funcionalidades se edita este `CHANGELOG.md`. Antes de publicar, se debe reflejar el resumen de la versión en `readme.txt`, que es el único Changelog mostrado automáticamente por WordPress.org.
