## [0.1.20] — 2026-11-13 — Code Check equiparado a Core 0.4.82 (#2626)

- Code Check equiparado a Core 0.4.82: reglas autónomas PHP/WordPress/seguridad, clasificación por gravedad, detalles de archivos y recomendaciones, resumen y JSON; sin modificar el Autotest funcional. Issue #2626.
- Continúa siendo una revisión estática independiente del Plugin Check oficial, con ejecución por bloques y control de CPU.

## [0.1.19] — 2026-11-10 — Cabecera en tres zonas sin contenido duplicado (#2521)

- Izquierda: nombre, descripción local del plugin y un único enlace estático `Plugin URI` bajo el texto.
- Centro: desplegable más ancho con el historial local `CHANGELOG.md`, cerrado inicialmente.
- Derecha: versión y fecha reales del propio plugin.
- Se retira la segunda caja «Información del plugin», sin cambios en servicios, menús ni consultas externas.

## [0.1.18] - 2026-11-10 - Selección de gestión de sitemaps (#2484)

- Nueva casilla en Distribution > Sitemaps para habilitar o ceder la publicación XML sin instalar otros plugins; mantiene por defecto el comportamiento anterior.
- Si se desmarca, Distribution deja de publicar XML y anunciarlo en robots.txt, permite el sitemap nativo de WordPress y cancela exclusivamente sus trabajos pendientes. No elimina los XML publicados ni afecta a feeds comerciales.
- Se impide cambiar de propietario con una generación activa: antes debe detenerse con el control existente. Se conserva el filtro de interoperabilidad aportado por OPNV Systems.
- Nonce, permisos, validación de entradas, reglas de reescritura y pruebas de regresión de propiedad, activación y desactivación.

## [0.1.17] — 2026-11-10 — Descripción visible desde el plugin (#2494)

- La descripción inmediatamente debajo del título administrativo procede de `Description:` del archivo principal, sin textos duplicados ni consultas externas.
- Se mantienen el enlace estático `Plugin URI`, el historial local y las funcionalidades anteriores.

## [0.1.16] — 2026-11-10 — Ficha local en el encabezado (#2494)

- Elimina consultas REST/caché del extracto y usa `Description` y `Plugin URI` locales.
- Conserva el enlace oficial y el historial, sin alterar servicios existentes.

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

- El desplegable izquierdo consulta el excerpt publicado en la página Plugin URI, con caché y configuración independiente.
- Formulario sanitizado; soporte para desactivación y funcionamiento sin servicios externos. Historial local sin cambios.

## [0.1.12] — 2026-11-08 — Extracto público remoto opcional (#2443)

- Consulta opcional por HTTPS del excerpt REST de una ficha oficial configurada; muestra el enlace a esa página en un panel izquierdo plegado.
- Mantiene el historial propio en el panel derecho. Caché, permisos y escape de salida; sin servicios obligatorios ni cambios en datos.

## [0.1.11] - 2026-11-08 - Autotest funcional y de codigo (#2401)

- Conserva autotest funcional y JSON; nuevo Code Check independiente del propio plugin: PHP, WordPress, seguridad. Cinco velocidades, lotes y JSON separado. Sin PCP ni Core obligatorio.

## [0.1.10] — 2026-11-08 — Historial plegable en cabecera (#2382)

- Desplegable cerrado por defecto inmediatamente debajo del título, con el historial propio de `CHANGELOG.md`; sin nueva pestaña ni dependencia de otros plugins.
- Textos del título preparados para ES/EN/FR/DE/NL, salida escapada, desplazamiento vertical y mantenimiento de menús y funciones existentes.
- El paquete instalable y la subida incremental incluyen únicamente el historial raíz necesario. Sin cambios en base de datos ni servicios.

## [0.1.9] — 2026-11-07 — Cabecera administrativa de versión y fecha (#2336)

- Muestra título, versión activa y fecha de publicación en el administrador mediante Release Date.
- La fecha no depende de la hora de consulta ni del momento de copia por FTP. Sin cambios en servicios, datos, menús ni permisos.

## [Unreleased] — Catálogos gettext completos ES/EN/FR/DE/NL (#2062)

- Completadas las 327 cadenas de cada uno de los cinco catálogos (PO), incluidos menús, botones, feeds, sitemaps, configuración, avisos, seguimiento de procesos y manuales. Las 97 cadenas restantes de FR/DE/NL se han traducido; en EN se han traducido los 79 textos de origen castellano y conservado los 146 ya escritos en inglés.
- Regenerados los MO de EN/FR/DE/NL. Los 327 `msgid` de cada catálogo coinciden con el POT actual; no quedan `msgstr` vacíos ni discrepancias de marcadores printf detectadas.
- No se han modificado productos, entradas, páginas, datos comerciales ni lógica PHP/JS. Pendiente verificar la carga efectiva de locales y la interfaz en WordPress, además de Plugin Check y pruebas de integración antes de fusionar o desplegar.

## [Unreleased] — Ampliación de idiomas de la interfaz (#2062, #2328)

- Completadas 196/196/194 cadenas gettext anteriormente vacías en FR/DE/NL y 68 cadenas fuente castellanas en EN; incluye menús, canales comerciales, sitemaps, progreso, control del generador, permisos y títulos de manuales.
- Regenerados los MO `en_US`, `fr_FR`, `de_DE` y `nl_NL`. En los PO actuales aún faltan 225 traducciones en EN y 97 en cada FR/DE/NL (sobre 327 claves); el castellano ya no tiene `msgstr` vacíos.
- No se modifican productos, posts, páginas, proveedores ni lógica funcional. Pendientes las traducciones restantes, la revisión del código PHP/JS y la validación real de WordPress/Plugin Check.

## [Unreleased] — Traducciones gettext (taxocommerce-distribution)

- Completadas las entradas de traducción vacías detectadas en los catálogos de EN/FR/DE/NL; idioma ES ya tenía traducción.
- No se modifica la lógica, el contenido de tienda ni los ajustes del plugin. Seguimiento global #2062.

## [Unreleased] — Reutilización segura de traducciones (#2307)

- Completadas únicamente entradas gettext PO vacías con traducciones idénticas y no ambiguas ya existentes en otros catálogos TaxoCommerce.
- Regenerados los MO de los idiomas modificados. Se conservan los textos, identificadores técnicos, funciones y datos anteriores sin cambios.
- Esta corrección es parcial: los textos restantes, las cadenas fuera de gettext y la validación visual por idioma siguen abiertos en #2062.

## [Unreleased] — Regional WordPress locale fallback (#2295)

- Respeta primero los paquetes oficiales de WordPress .mo y .l10n.php para el locale exacto.
- Si falta un catálogo local regional, usa el catálogo existente del mismo idioma (es_ES, en_US, fr_FR, de_DE o nl_NL); no cambia la configuración ni el idioma del usuario.
- Sin cambios en lógica, datos, menús o dependencias del plugin. Las traducciones de contenido incompletas siguen abiertas en #2062.

## Unreleased - Familia i18n #2062

## [0.1.8] — 2026-11-07 — Bing sitemap (Issue #2259)
- Nueva revisión diaria de los sitemaps XML mediante WP-Cron y el trabajador incremental existente (250 registros por bloque, cinco velocidades); ninguna segunda implementación ni escritura durante la petición de la página.
- Evita regeneraciones simultáneas y respeta el generador monolítico cuando sea el propietario activo de la ruta. Limpia su evento recurrente al desactivar el plugin.
- Prueba del evento diario, de la recuperación de una cola ocupada, de la coexistencia legacy y del apagado; el resultado de Bing solo puede comprobarse con los XML publicados y un nuevo rastreo.
- La categoría VEVOR FR - Peinture del ejemplo de Bing tiene productos publicados; esta versión no crea URL específicas ni modifica categorías de otras tiendas.

## [0.1.7] — 2026-11-04

- Issue #2172: interfaz administrativa de Distribution traducible al español, incluidos controles de feeds; traducciones gettext es_ES completas y catálogos binarios coherentes.
- Eliminada la pestaña Overview redundante. Sus indicadores de solo lectura aparecen debajo de la cabecera común y las URL antiguas abren Feeds.
- Conservados los motores de feeds, sitemaps, automatización, acciones protegidas y Autocheck manual. Despliegue en STAGING y Focazul; no producción.



## [0.1.6] — 2026-11-04

- WordPress Plugin Check (#2161): resueltos cinco errores (comentario de traductores y escape de mensajes/nombres de ficheros lanzados como excepciones) y un aviso (eliminado error_log redundante).
- Se conserva el error persistido en el estado del trabajador, la limpieza de temporales y la publicación atómica de XML. Sin cambios en los chequeos manuales existentes.
- No implica modificación de producción ni demuestra la aprobación del comprobador oficial antes de ejecutarlo de nuevo.

## [0.1.5] — 2026-11-03

- Unifica «Autocheck» y permite descargar JSON autenticado de los resultados de la última ejecución, sin modificar servicios ni contenidos.
- Issue #2150, PR #2151; rutas de pestaña y acciones previas conservadas.

- Dominio gettext, carga de catalogos locales, etiquetas de menús y textos HTML de salida estáticos; catálogos PO/MO sincronizados con el texto fuente.

# CHANGELOG — TaxoCommerce Distribution - Feeds & Sitemaps for WooCommerce

> Historial maestro de versiones del plugin independiente. El origen monolítico se documenta en [`changelog.txt`](changelog.txt).

## [Unreleased]

Sin novedades pendientes documentadas.

## [0.1.4] — 2026-11-03

- Corrige el informe de fecha: separa inicio de ejecución, actividad de los lotes y última publicación completada; el XML antiguo no se presenta como publicación nueva.
- Los nuevos archivos hijos llevan identificador de generación único. El índice anterior y sus archivos siguen intactos hasta que el nuevo índice validado se publica al final; reanuda trabajos iniciados con nombres antiguos migrándolos dentro del directorio temporal.
- Detecta fallos de programación de WP-Cron y de almacenamiento, muestra errores explícitos y conserva los sitemaps publicados.
- El informe durante el proceso no suma archivos todavía ausentes del índice anterior; regresaión aislada de publicación, programación fallida y continuidad tras la actualización.


## [0.1.3] — 2026-11-03

- Cabecera de administración con estilo común TaxoCommerce Core, nombre, icono, descripción y versión, sin dependencias entre plugins (#2137 / PR #2138).

- Versión visible en el encabezado de Distribution y actualizada en la cabecera del plugin y en readme.txt.
- Motor de sitemap sustituido por ventanas incrementales de 250 elementos (consulta por cursor para productos), con progreso, bloqueo de concurrencia, reanudación y botón Detener.
- Regulador de cinco velocidades compatible con los perfiles de los demás plugins TaxoCommerce; telemetría local de CPU PHP, memoria y duración, con pausas automáticas por presión.
- Mantiene los grupos y la validación XML del motor anterior, los sitemaps publicados durante el procesamiento y la sustitución final del índice; sin modificar el motor de feeds ni la versión monolítica.
- Seguimiento: #2134; pendiente QA funcional en STAGING.


- Revisados los 24 avisos de Plugin Check: mensajes de estado sin mutaciones, sanitización de avisos de sitemap y justificación localizada de consultas SQL de inventario y metadatos para evitar caché obsoleta.
- Seguimiento: #1545. Validación de Plugin Check y STAGING pendiente.

## [0.1.2]

- Reorganizada la pestaña Manual en **dos documentos completos y diferenciados**: Manual de usuario y Manual técnico para futuros desarrolladores, con índice accesible, secciones independientes y diseño adaptable.
- Ampliado el manual de usuario con secuencia operativa segura; ampliado el técnico con mapa de archivos y funciones, ciclos de trabajo, almacenamiento, opciones, hooks, coexistencia con el monolito, seguridad, pruebas y procedimiento de mantenimiento.
- Documentación contrastada con el código de Distribution. No se modifica el motor de feeds, sitemaps, Autocheck ni ningún archivo del monolito. Seguimiento: #1978.


## [0.1.1]

- Añadida pestaña **Manual**, con guía de usuario y documentación técnica de los feeds comerciales, sitemaps, automatización, rutas públicas, coexistencia y resolución de incidencias.
- Añadida pestaña **Autocheck** con ejecución manual, protección de permisos/nonce y controles agrupados de motor, canales, eventos registrados, opciones de solo lectura y metadatos de los archivos publicados.
- El Autocheck no llama a inicializadores de almacenamiento que podrían crear ficheros, no genera feeds ni XML, no programa workers y no realiza peticiones externas ni escrituras.
- Incorporada prueba aislada sin WordPress real para detectar efectos secundarios y verificar ambas pestañas; sincronizada la documentación WordPress.org. Issue #1978.


## [0.1.0]

* Initial modular migration of commercial feeds and sitemap generation.
* Added independent bounded feed worker.
* Added safe legacy coexistence and settings migration.
* Added Distribution admin tabs for overview, feeds, sitemaps and automation.

## Fuentes

- [`changelog.txt`](changelog.txt), etapa monolítica.
- [`docs/PLUGIN-HISTORY-SOURCES.md`](../docs/PLUGIN-HISTORY-SOURCES.md), PR y referencias históricas.

## Actualizaciones

Para nuevas funcionalidades se edita este `CHANGELOG.md`. Antes de publicar, se debe reflejar el resumen de la versión en `readme.txt`, que es el único Changelog mostrado automáticamente por WordPress.org.
