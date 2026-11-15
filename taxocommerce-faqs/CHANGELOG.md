## [0.1.20] — 2026-11-14 — Autocheck inmediato y cabecera también en Contenidos de Core (#2675)

- El Autocheck funcional adopta el formato de Import-Export: ejecución manual inmediata de las 25 comprobaciones, dos botones (ejecutar y descargar el último JSON), 6 grupos plegables y resultados con indicadores visuales.
- Retirados de la interfaz los controles «Guardar estado ANTES» / «Comprobar DESPUÉS» y sus paneles de comparación. Se conserva el método de comparación únicamente por compatibilidad y no se modifican los informes antiguos guardados por usuario y sitio.
- Los envíos históricos con parámetros de fase o grupo se tratan como ejecución normal completa; los JSON contienen solo datos técnicos y conservan permisos y nonce.
- Se reconoce también la ruta de FAQs dentro de Core (`taxocommerce-core-content&tc_type=faqs`) para mostrar la misma cabecera en FAQs, Autocheck y Manual. El resto de las secciones de Core no se ve afectado.
- Code Check, CRUD, importación/exportación CSV, SQL y datos de FAQs no cambian. Producción excluida del despliegue.

## [0.1.19] — 2026-11-13 — Code Check equiparado a Core 0.4.82 (#2626)

- Code Check equiparado a Core 0.4.82: reglas autónomas PHP/WordPress/seguridad, clasificación por gravedad, detalles de archivos y recomendaciones, resumen y JSON; sin modificar el Autotest funcional. Issue #2626.
- Continúa siendo una revisión estática independiente del Plugin Check oficial, con ejecución por bloques y control de CPU.

## [0.1.18] — 2026-11-10 — Cabecera en tres zonas sin contenido duplicado (#2521)

- Izquierda: nombre, descripción local del plugin y un único enlace estático `Plugin URI` bajo el texto.
- Centro: desplegable más ancho con el historial local `CHANGELOG.md`, cerrado inicialmente.
- Derecha: versión y fecha reales del propio plugin.
- Se retira la segunda caja «Información del plugin», sin cambios en servicios, menús ni consultas externas.

## [0.1.17] — 2026-11-10 — Descripción visible desde el plugin (#2494)

- La descripción inmediatamente debajo del título administrativo procede de `Description:` del archivo principal, sin textos duplicados ni consultas externas.
- Se mantienen el enlace estático `Plugin URI`, el historial local y las funcionalidades anteriores.

## [0.1.16] — 2026-11-10 — Información local del plugin (#2494)

- Encabezado local mediante `Description` y `Plugin URI`, sin REST ni caché.
- Enlace, historial y funcionalidad conservados.

## [0.1.15] - 2026-11-10 - Resiliencia del extracto oficial (#2489)

- Consulta exclusivamente la API REST pública de la página indicada en Plugin URI (autor del plugin), nunca páginas de la tienda del cliente.
- Conserva el último extracto oficial comprobado; los fallos de red no borran el texto. Caché de errores reducida a 5 minutos y versión nueva de clave para ignorar fallos antiguos cacheados.
- Si nunca se ha obtenido un extracto, muestra provisionalmente la Description incluida por el autor en el plugin; permite abrir la ficha oficial. No cambia operaciones comerciales ni requiere Core.
- Pruebas para recuperación tras fallos HTTP, JSON inválido y caché negativa; compatibles con la familia completa.

## [0.1.14] — 2026-11-09 — Ficha oficial del desarrollador y cabecera centrada en tres zonas (#2476)

- La cabecera distribuye identificación a la izquierda, dos desplegables alineados y centrados (extracto oficial a la izquierda e historial local a la derecha), y versión y fecha a la derecha; distribución adaptable a móvil.
- El enlace oficial se obtiene exclusivamente de Plugin URI, definido por el autor del plugin. Eliminados el formulario de URL y su acción de guardado; los ajustes antiguos de la tienda no pueden sobrescribirlo.
- El historial se lee del CHANGELOG.md incluido, plegado de inicio. Sin dependencias obligatorias entre plugins ni cambios en productos, pedidos, datos o funcionalidades existentes.

## [0.1.13] — 2026-11-08 — Información dinámica desde ficha oficial (#2443)

- Plugin URI identifica la ficha oficial; el desplegable izquierdo consulta su excerpt de forma segura, con caché y opción de personalizar/desactivar.
- Corregida la sanitización del formulario y la compatibilidad con las comprobaciones; el historial local se conserva sin cambios.

## [0.1.12] — 2026-11-08 — Extracto comercial remoto configurable en cabecera (#2443)

- Desplegable izquierdo para obtener el extracto público de una página WordPress oficial, únicamente cuando el administrador configura su URL HTTPS, con enlace a la ficha de origen.
- Desplegable derecho de historial local, independiente del servicio externo. Validación y escape, límite temporal de petición y caché para evitar demoras.
- Sin dependencia de otros plugins, sin origen comercial predefinido y sin modificación de funcionalidades existentes.

## [0.1.11] - 2026-11-08 - Autotest funcional y de codigo (#2401)

- Conserva autotest funcional y JSON; nuevo Code Check independiente del propio plugin: PHP, WordPress, seguridad. Cinco velocidades, lotes y JSON separado. Sin PCP ni Core obligatorio.

## [0.1.10] — 2026-11-08 — Historial plegable en cabecera (#2382)

- Desplegable cerrado por defecto inmediatamente debajo del título, con el historial propio de `CHANGELOG.md`; sin nueva pestaña ni dependencia de otros plugins.
- Textos del título preparados para ES/EN/FR/DE/NL, salida escapada, desplazamiento vertical y mantenimiento de menús y funciones existentes.
- El paquete instalable y la subida incremental incluyen únicamente el historial raíz necesario. Sin cambios en base de datos ni servicios.

## [0.1.9] — 2026-11-07 — Cabecera administrativa de versión y fecha (#2336)

- Muestra título, versión activa y fecha de publicación en la cabecera del administrador mediante metadatos locales (Release Date).
- La fecha no depende de la hora de consulta ni del momento de copia por FTP. No altera servicios, datos, menús ni permisos.
- Se conserva el funcionamiento independiente y se comprueba con el test de cabeceras de la familia.

## [Unreleased] — Catálogos gettext completos ES/EN/FR/DE/NL (#2062)

- Completadas las 57 traducciones de interfaz, mensajes y manuales pendientes en cada idioma (285 entradas en total), manteniendo íntegros los identificadores técnicos y las advertencias sobre operaciones destructivas.
- Regenerados los cinco archivos MO desde los correspondientes PO. Confirmadas 115 claves en POT y en cada PO, cero traducciones vacías, cero cadenas marcadas como fuzzy, sin diferencias PO/MO y sin discrepancias de marcadores de formato detectadas.
- No se modifican datos de preguntas frecuentes, productos, páginas, entradas, categorías, base de datos ni lógica del plugin. Pendientes ejecución de Plugin Check, comprobación real del idioma de WordPress y pruebas visuales en instalaciones autorizadas.

## [Unreleased] — Catálogos i18n FAQs (#2319)

- Completadas 31 entradas gettext que estaban vacías en cada catálogo PO (es_ES, en_US, fr_FR, de_DE, nl_NL), principalmente mensajes de Autocheck y títulos de manuales.
- Sin cambios de lógica funcional ni datos. Pendiente recompilar MO, comprobar traducciones multilínea y verificar el idioma efectivo en WordPress antes de integrar/desplegar.

## [Unreleased] — Internacionalización de interfaz FAQs (#2281)

- Traducidas 27 entradas de interfaz, menús, campos y CSV a es_ES, en_US, fr_FR, de_DE y nl_NL.
- Regenerados los cinco MO y conservada la compatibilidad del text domain; sin modificar el modelo de preguntas ni la tabla compartida.
- Pendientes la traducción del resto del manual y pruebas de cambio de idioma en WordPress. No se declara cobertura completa.

## [Unreleased] — Regional WordPress locale fallback (#2295)

- Respeta primero los paquetes oficiales de WordPress .mo y .l10n.php para el locale exacto.
- Si falta un catálogo local regional, usa el catálogo existente del mismo idioma (es_ES, en_US, fr_FR, de_DE o nl_NL); no cambia la configuración ni el idioma del usuario.
- Sin cambios en lógica, datos, menús o dependencias del plugin. Las traducciones de contenido incompletas siguen abiertas en #2062.

## Unreleased - Familia i18n #2062


## [0.1.8] — 2026-11-03

- Pestaña «Autocheck» con descarga JSON ya existente y conservación de las comparaciones antes/después.
- Issue #2150, PR #2151; rutas de pestaña y acciones previas conservadas.

- Dominio gettext, carga de catalogos locales, etiquetas de menús y textos HTML de salida estáticos; catálogos PO/MO sincronizados con el texto fuente.

# CHANGELOG — TaxoCommerce FAQs

## [0.1.7] — 2026-11-03

- Cabecera común basada en TaxoCommerce Core: identidad, icono, descripción y versión con estilo responsive.
- Enlaces, pestañas, permisos y lógica interna conservados; implementación autónoma sin requerir Core. Issue #2137.

> Historial maestro de versiones del plugin independiente. El origen monolítico se documenta en [`changelog.txt`](changelog.txt).

## [Unreleased]

Sin novedades pendientes documentadas.


## [0.1.6]

* Correct prepared SQL identifiers and validation in the FAQ editor, CSV and Vocabulary operations.
* Exclude development tests and FTP sync state from installed plugin, and clean obsolete files on staging.
* Retain all FAQ records and existing editor, Auto Test and Manual functionality.

## [0.1.5]

* Add an in-plugin Auto Test tab with 25 read-only checks across six groups.
* Add a separate read-only Manual tab with eleven user-guide chapters and six technical chapters, following the family layout.
* Store before/after deployment baselines per user and site, compare regressions and export sanitized JSON.
* Never change FAQ rows, run migrations, or import/export files in diagnostic mode; live operations remain manual.

## [0.1.4]

* Replace direct PHP CSV file handles with WordPress filesystem reads and streamed CSV encoding.
* Preserve quoted multiline FAQ answers during bounded CSV import.
* Keep current prepared SQL and English WordPress.org readme metadata.

## [0.1.3]

* Standalone FAQ editor and WooCommerce category validation.
* CSV exchange and visibility/usage KPIs.

## Fuentes

- [`changelog.txt`](changelog.txt), etapa monolítica.
- [`docs/PLUGIN-HISTORY-SOURCES.md`](../docs/PLUGIN-HISTORY-SOURCES.md), PR y referencias históricas.

## Actualizaciones

Para nuevas funcionalidades se edita este `CHANGELOG.md`. Antes de publicar, se debe reflejar el resumen de la versión en `readme.txt`, que es el único Changelog mostrado automáticamente por WordPress.org.
