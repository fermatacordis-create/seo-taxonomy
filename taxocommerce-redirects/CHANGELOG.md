## [0.1.24] — 2026-11-14 — Informes de Autocheck homogéneos (#2682)

- Informes funcionales de Redirects, Insights y Logistics con estructura común a Import-Export: versión, fecha UTC, duración, WordPress, PHP, resúmenes, grupos, estado, etiqueta, mensaje y tiempo por prueba.
- Los JSON nuevos normalizan metadatos y los informes ya guardados se normalizan únicamente al leerlos; se preservan sus campos originales para compatibilidad.
- Vista funcional uniforme: resúmenes, colores, estados, grupos y tiempos. Se conservan las dos subpestañas, el Code Check autónomo, permisos y acciones de descarga.
- Sin cambios en motores, reglas, pedidos, conexiones, información comercial ni tablas. Pruebas de regresión y WordPress Plugin Check.

## [0.1.23] — 2026-11-14 — Autocheck funcional unificado (#2673)

- Interfaz simplificada al patrón de la familia: «Ejecutar Autocheck» y «Descargar resultados (JSON)»; retirados los botones y paneles ANTES/DESPUÉS y los botones por grupo.
- Se mantienen las 26 verificaciones funcionales, los resultados por administrador y sitio, los permisos, nonce y el chequeo de código autónomo en su subpestaña.
- Los controladores históricos siguen compatibles para preservar datos y contratos anteriores. Sin cambios en reglas 301/302, importación, motor ni tablas SEO.

## [0.1.22] — 2026-11-13 — Cadenas directas y sugerencia de categoría (#2647)

- El motor resuelve redirecciones encadenadas hacia el destino terminal en una sola respuesta HTTP, conservando el código original y la protección frente a ciclos. No altera registros durante las visitas.
- El gestor permite consolidar individualmente las reglas con cadenas, mostrando el destino final, validándolo de nuevo y requiriendo confirmación, nonce y permisos. No consolida de forma automática equivalencias semánticamente incorrectas.
- Al borrar un producto publicado, se propone opcionalmente la categoría WooCommerce más específica que mantiene otros productos. Se conserva la edición manual y la opción de no redirigir.
- No se generan reglas para 404 históricos sin origen/destino comprobables ni se modifica producción.

## [0.1.21] — 2026-11-13 — Code Check equiparado a Core 0.4.82 (#2626)

- Code Check equiparado a Core 0.4.82: reglas autónomas PHP/WordPress/seguridad, clasificación por gravedad, detalles de archivos y recomendaciones, resumen y JSON; sin modificar el Autotest funcional. Issue #2626.
- Continúa siendo una revisión estática independiente del Plugin Check oficial, con ejecución por bloques y control de CPU.

## [0.1.20] - 2026-11-10 - Activación del motor y compatibilidad con otros gestores (#2485)

- Casilla administrativa «TaxoCommerce gestiona las redirecciones», activada por defecto. Guardado protegido mediante nonce y permisos de WordPress.
- Al desactivar, Redirects deja de atender rutas, reclamar el control a OPNV y guardar cambios de slug remitidos por otro gestor. Se conservan reglas, herramientas de importación/exportación e informes.
- Conservadas las mejoras de coordinación de PR #2516 y la cabecera en tres zonas de v0.1.19. Añadida la prueba automática de activación, desactivación y reactivación.

## [0.1.19] — 2026-11-10 — Cabecera en tres zonas sin contenido duplicado (#2521)

- Izquierda: nombre, descripción local del plugin y un único enlace estático `Plugin URI` bajo el texto.
- Centro: desplegable más ancho con el historial local `CHANGELOG.md`, cerrado inicialmente.
- Derecha: versión y fecha reales del propio plugin.
- Se retira la segunda caja «Información del plugin», sin cambios en servicios, menús ni consultas externas.

## [0.1.18] — 2026-11-10 — Descripción visible desde el plugin (#2494)

- La descripción inmediatamente debajo del título administrativo procede de `Description:` del archivo principal, sin textos duplicados ni consultas externas.
- Se mantienen el enlace estático `Plugin URI`, el historial local y las funcionalidades anteriores.

## [0.1.17] — 2026-11-10 — Información local del plugin (#2494)

- Encabezado local mediante `Description` y `Plugin URI`, sin REST ni caché.
- Enlace, historial y funcionalidad conservados.

## [0.1.16] - 2026-11-10 - Resiliencia del extracto oficial (#2489)

- Consulta exclusivamente la API REST pública de la página indicada en Plugin URI (autor del plugin), nunca páginas de la tienda del cliente.
- Conserva el último extracto oficial comprobado; los fallos de red no borran el texto. Caché de errores reducida a 5 minutos y versión nueva de clave para ignorar fallos antiguos cacheados.
- Si nunca se ha obtenido un extracto, muestra provisionalmente la Description incluida por el autor en el plugin; permite abrir la ficha oficial. No cambia operaciones comerciales ni requiere Core.
- Pruebas para recuperación tras fallos HTTP, JSON inválido y caché negativa; compatibles con la familia completa.

## [0.1.15] — 2026-11-09 — Ficha oficial del desarrollador y cabecera centrada en tres zonas (#2476)

- La cabecera distribuye identificación a la izquierda, dos desplegables alineados y centrados (extracto oficial a la izquierda e historial local a la derecha), y versión y fecha a la derecha; distribución adaptable a móvil.
- El enlace oficial se obtiene exclusivamente de Plugin URI, definido por el autor del plugin. Eliminados el formulario de URL y su acción de guardado; los ajustes antiguos de la tienda no pueden sobrescribirlo.
- El historial se lee del CHANGELOG.md incluido, plegado de inicio. Sin dependencias obligatorias entre plugins ni cambios en productos, pedidos, datos o funcionalidades existentes.

## [0.1.14] — 2026-11-08 — Extracto dinámico desde la página oficial (#2443)

- La cabecera obtiene el excerpt de la página oficial definida por Plugin URI, sin copiar textos al plugin ni depender de Core. Puede personalizarse o desactivarse desde cada instalación.
- Corregida la lectura/sanitización del URL y la compatibilidad de los chequeos estáticos. Caché y fallback cuando la fuente no responde. Historial CHANGELOG.md intacto.

## [0.1.13] — 2026-11-08 — Extracto comercial remoto configurable en cabecera (#2443)

- Desplegable izquierdo para obtener el extracto público de una página WordPress oficial, únicamente cuando el administrador configura su URL HTTPS, con enlace a la ficha de origen.
- Desplegable derecho de historial local, independiente del servicio externo. Validación y escape, límite temporal de petición y caché para evitar demoras.
- Sin dependencia de otros plugins, sin origen comercial predefinido y sin modificación de funcionalidades existentes.

## [0.1.12] - 2026-11-08 - Autotest funcional y de codigo (#2401)

- Conserva autotest funcional y JSON; nuevo Code Check independiente del propio plugin: PHP, WordPress, seguridad. Cinco velocidades, lotes y JSON separado. Sin PCP ni Core obligatorio.

## [0.1.11] — 2026-11-08 — Historial plegable en cabecera (#2382)

- Desplegable cerrado por defecto inmediatamente debajo del título, con el historial propio de `CHANGELOG.md`; sin nueva pestaña ni dependencia de otros plugins.
- Textos del título preparados para ES/EN/FR/DE/NL, salida escapada, desplazamiento vertical y mantenimiento de menús y funciones existentes.
- El paquete instalable y la subida incremental incluyen únicamente el historial raíz necesario. Sin cambios en base de datos ni servicios.

## [Unreleased] — Finalización de los catálogos de Redirects (#2062)

- Añadidas las 53 traducciones restantes del manual técnico por idioma (265 entradas adicionales). Las 144 cadenas no vacías de cada catálogo PO quedan traducidas en ES/EN/FR/DE/NL; 145 entradas por archivo incluyendo la cabecera.
- Regenerados los cinco MO; se conservan los nombres y referencias técnicas, los contratos JSON y advertencias de borrado. No se modifican código funcional, redirecciones ni datos.
- Pendientes auditoría de textos fuera de gettext, Plugin Check y pruebas reales por idioma antes de declarar aptitud WordPress.org. Producción no autorizada.

## [Unreleased] — Traducciones de interfaz y documentación (#2062)

- Internacionalizadas las 57 primeras cadenas todavía vacías del manual del usuario en ES/EN/FR/DE/NL (285 traducciones), con instrucciones sobre 301/302, reglas, copias JSON, borrado asistido, advertencias y diagnósticos.
- Regenerados los cinco archivos MO con 145 entradas por idioma; sin modificar reglas, tablas, datos, rutas, motor de redirecciones ni PHP/JS.
- Continúa pendiente la traducción del manual técnico y la comprobación real de interfaz/idiomas en WordPress; no desplegar a producción.

## [0.1.10] — 2026-11-07 — Cabecera administrativa de versión y fecha (#2336)

- Muestra título, versión activa y fecha de publicación en la cabecera del administrador mediante metadatos locales (Release Date).
- La fecha no depende de la hora de consulta ni del momento de copia por FTP. No altera servicios, datos, menús ni permisos.
- Se conserva el funcionamiento independiente y se comprueba con el test de cabeceras de la familia.

## [Unreleased] — Revisión i18n (seguimiento #2062)

- Completada una entrada gettext previamente vacía en ES/EN/FR/DE/NL; conserva el mismo identificador fuente y no modifica la lógica de la tienda.
- Pendiente recompilar catálogos MO y validar cobertura frente al POT, en especial discrepancias entre idiomas; no supone traducción integral del plugin.

## [Unreleased] — Primera fase i18n Redirects (#2286)

- Traducidas 33 cadenas iniciales de menús, Auto Test y navegación de manual en es_ES, en_US, fr_FR, de_DE y nl_NL.
- Regenerados los cinco catálogos MO sin modificar el motor de redirecciones, las reglas 301/302 ni la eliminación asistida.
- Pendientes las 111 cadenas restantes del POT y los textos fuera de gettext, así como revisar los metadatos con la URL del cliente histórico antes de publicar en WordPress.org.

## [Unreleased] — Regional WordPress locale fallback (#2295)

- Respeta primero los paquetes oficiales de WordPress .mo y .l10n.php para el locale exacto.
- Si falta un catálogo local regional, usa el catálogo existente del mismo idioma (es_ES, en_US, fr_FR, de_DE o nl_NL); no cambia la configuración ni el idioma del usuario.
- Sin cambios en lógica, datos, menús o dependencias del plugin. Las traducciones de contenido incompletas siguen abiertas en #2062.

## Unreleased - Familia i18n #2062


## [0.1.9] — 2026-11-03

- Pestaña «Autocheck» con descarga JSON ya existente, conservación de controles de seguridad y de comparaciones de regresiones.
- Issue #2150, PR #2151; rutas internas y ejecuciones existentes conservadas.

- Dominio gettext, carga de catalogos locales, etiquetas de menús y textos HTML de salida estáticos; catálogos PO/MO sincronizados con el texto fuente.

# CHANGELOG — TaxoCommerce Redirects - SEO Redirect Manager

## [0.1.8] — 2026-11-03

- Cabecera común basada en TaxoCommerce Core: identidad, icono, descripción y versión con estilo responsive.
- Enlaces, pestañas, permisos y lógica interna conservados; implementación autónoma sin requerir Core. Issue #2137.

> Historial maestro de versiones del plugin independiente. El origen monolítico se documenta en [`changelog.txt`](changelog.txt).

## [Unreleased]

Sin novedades pendientes documentadas.


## [0.1.7]

* Add User Guide tab with separate User Manual and Technical Manual, indexed sections, accessible navigation, responsive typography and links to Redirects/Auto Test.
* Document existing 301/302 functionality, administration, rules diagnostics, JSON backups, assisted deletion, data schema, legacy coexistence and the destructive uninstall caveat.
* Manual rendering is read-only and does not load the redirects table or invoke any handlers.

## [0.1.6]

* Add native Auto Test tab with 26 read-only checks in 8 groups for redirects, schema, assisted deletion, integration, and safety.
* Save a per-user, per-site BEFORE baseline and compare it with AFTER results, including lost coverage, new errors and resolved issues.
* Allow downloading private diagnostic JSON; no redirects, hit counters, or content changes are made by tests.
* Keep the Redirects manager and all existing rules, legacy integration, and assisted-delete workflows unchanged.

## [0.1.5]

* WordPress.org release metadata cleanup and English plugin description.
* Confirmed bounded assisted-delete queries keep WordPress filters enabled.

## [0.1.4]

* Retirada la integracion opcional del motor historico de rollback de Database. CRUD de redirecciones vuelve al flujo autonomo original, sin dependencia ni comprobaciones de Database.
* Conservado el boton Abrir y la navegacion por Core cuando esta instalado.

## [0.1.3]

* Version experimental retirada de la integracion de rollback.

## [0.1.2]

* Integracion de menu y accion Abrir en el catalogo Core.

## [0.1.1]

* Verified activation lifecycle and explicit uninstall cleanup for seo_redirects.

## [0.1.0]

* Independent runtime, administration, import/export and 4+4 assisted deletion.

## Fuentes

- [`changelog.txt`](changelog.txt), etapa monolítica.
- [`docs/PLUGIN-HISTORY-SOURCES.md`](../docs/PLUGIN-HISTORY-SOURCES.md), PR y referencias históricas.

## Actualizaciones

Para nuevas funcionalidades se edita este `CHANGELOG.md`. Antes de publicar, se debe reflejar el resumen de la versión en `readme.txt`, que es el único Changelog mostrado automáticamente por WordPress.org.
