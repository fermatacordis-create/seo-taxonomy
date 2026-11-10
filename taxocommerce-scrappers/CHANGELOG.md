## [0.1.20] — 2026-11-10 — Descripción visible desde el plugin (#2494)

- La descripción inmediatamente debajo del título administrativo procede de `Description:` del archivo principal, sin textos duplicados ni consultas externas.
- Se mantienen el enlace estático `Plugin URI`, el historial local y las funcionalidades anteriores.

## [0.1.19] — 2026-11-10 — Ficha local en el encabezado (#2494)

- Elimina consultas REST/caché del extracto y usa `Description` y `Plugin URI` locales.
- Conserva el enlace oficial y el historial, sin alterar servicios existentes.

## [0.1.18] - 2026-11-10 - Resiliencia del extracto oficial (#2489)

- Consulta exclusivamente la API REST pública de la página indicada en Plugin URI (autor del plugin), nunca páginas de la tienda del cliente.
- Conserva el último extracto oficial comprobado; los fallos de red no borran el texto. Caché de errores reducida a 5 minutos y versión nueva de clave para ignorar fallos antiguos cacheados.
- Si nunca se ha obtenido un extracto, muestra provisionalmente la Description incluida por el autor en el plugin; permite abrir la ficha oficial. No cambia operaciones comerciales ni requiere Core.
- Pruebas para recuperación tras fallos HTTP, JSON inválido y caché negativa; compatibles con la familia completa.

## [0.1.17] — 2026-11-09 — Ficha oficial del desarrollador y cabecera centrada en tres zonas (#2476)

- La cabecera distribuye identificación a la izquierda, dos desplegables alineados y centrados (extracto oficial a la izquierda e historial local a la derecha), y versión y fecha a la derecha; distribución adaptable a móvil.
- El enlace oficial se obtiene exclusivamente de Plugin URI, definido por el autor del plugin. Eliminados el formulario de URL y su acción de guardado; los ajustes antiguos de la tienda no pueden sobrescribirlo.
- El historial se lee del CHANGELOG.md incluido, plegado de inicio. Sin dependencias obligatorias entre plugins ni cambios en productos, pedidos, datos o funcionalidades existentes.

## [0.1.16] — 2026-11-08 — Información dinámica desde ficha oficial (#2443)

- El desplegable izquierdo consulta el excerpt publicado en la página Plugin URI, con caché y configuración independiente.
- Formulario sanitizado; soporte para desactivación y funcionamiento sin servicios externos. Historial local sin cambios.

## [0.1.15] — 2026-11-08 — Extracto público remoto opcional (#2443)

- Consulta opcional por HTTPS del excerpt REST de una ficha oficial configurada; muestra el enlace a esa página en un panel izquierdo plegado.
- Mantiene el historial propio en el panel derecho. Caché, permisos y escape de salida; sin servicios obligatorios ni cambios en datos.
## [0.1.15] — 2026-11-08 — Catálogo gettext completo en cinco idiomas (#2062)

- Traducidas las 233 entradas `msgid` actuales de Scrappers en es_ES, en_US, fr_FR, de_DE y nl_NL: Autotest, consultas externas, límites de API, investigación, revisión editorial, importación/exportación y Merchant.
- Catálogos PO sincronizados con POT sin entradas vacías ni diferencias en marcadores `printf`. Compilados cinco MO y comprobada la lectura binaria inversa de los 234 registros por idioma (incluida la cabecera gettext).
- Metadatos y `readme.txt` actualizados a 0.1.15, conservando `changelog.txt`. No se modifican cron, credenciales, consultas a proveedores, datos de WooCommerce ni comportamiento funcional.
- Pendientes la revisión de literales visibles PHP/JS no extraídos por gettext, Plugin Check, regresiones y validación del idioma real en los WordPress de prueba. Los recuentos parciales de notas históricas inferiores no describen el estado actual.

## [0.1.14] - 2026-11-08 - Autotest funcional y de codigo (#2401)

- Conserva autotest funcional y JSON; nuevo Code Check independiente del propio plugin: PHP, WordPress, seguridad. Cinco velocidades, lotes y JSON separado. Sin PCP ni Core obligatorio.
## [Unreleased] — Ampliación del panel de Ingeniero y Ojeador en cinco idiomas (#2062)

- Segunda tanda: 366 traducciones adicionales sobre 74 cadenas de interfaz y paneles (ES 73, EN 74, FR 73, DE 73, NL 73): fuentes, conocimiento, evaluación editorial, aprobación humana, importación/exportación, investigación, Merchant, estados y acciones.
- Acumulado en este PR: 649 entradas gettext añadidas (ES 129, EN 132, FR 130, DE 129 y NL 129). Se regeneran los cinco archivos MO sin cambiar lógica ni datos.
- Pendientes en PO: ES 80, EN 79, FR/DE/NL 78 por idioma. Se requiere completar y comprobar interfaz real antes de dar por terminada la internacionalización de Scrappers.

## [Unreleased] — Traducciones de interfaz y manuales Scrappers (#2062)

- Incorporadas 283 entradas gettext antes vacías: ES 56, EN 58, FR 57, DE 56 y NL 56. Cobertura parcial de 58 cadenas de interfaz: Autotest, menús Ojeador/Ingeniero, funciones de investigación, avisos, controles de procesos, navegación y documentación para usuarios.
- PO y MO regenerados y sincronizados en los cinco idiomas. Se mantienen 233 mensajes traducibles y cabecera por catálogo, los identificadores, los nombres de API y los parámetros de formato.
- Cambios exclusivamente de traducción, sin alterar PHP/JS, las cuotas de SerpApi/ScraperAPI, trabajos programados, opciones, entradas, productos o configuración de tiendas.
- Pendiente: ES 153, EN 153, FR 151, DE 151, NL 151 cadenas PO vacías; revisar literales fuera de gettext y completar validaciones de Plugin Check, CI y visualización por idioma en STAGING/Focazul.

## [0.1.13] — 2026-11-08 — Historial plegable en cabecera (#2382)

- Desplegable cerrado por defecto inmediatamente debajo del título, con el historial propio de `CHANGELOG.md`; sin nueva pestaña ni dependencia de otros plugins.
- Textos del título preparados para ES/EN/FR/DE/NL, salida escapada, desplazamiento vertical y mantenimiento de menús y funciones existentes.
- El paquete instalable y la subida incremental incluyen únicamente el historial raíz necesario. Sin cambios en base de datos ni servicios.

## [0.1.12] — 2026-11-08 — Limpieza del ciclo de vida (#2359)

- Al desactivar Scrappers se cancelan sus cinco hooks WP-Cron y la única acción Action Scheduler del grupo Ingeniero.
- No se borran tablas, configuraciones, productos ni evidencia; los datos siguen disponibles al reactivar el plugin.
- Se preservan las tareas de cualquier instalación temporal que todavía comparta los hooks históricos; no se instala ni se modifica el plugin antiguo.
- Añadida prueba regresiva PHP aislada de desactivación, alcance de limpieza y conservación de datos. No se modifican los motores de análisis ni las conexiones.

## [0.1.11] — 2026-11-07 — Cabecera administrativa de versión y fecha (#2336)

- Muestra título, versión activa y fecha de publicación en el administrador mediante Release Date.
- La fecha no depende de la hora de consulta ni del momento de copia por FTP. Sin cambios en servicios, datos, menús ni permisos.

## [0.1.10] — 2026-11-07 — Registro Core sin traducciones prematuras (#2330)

- Traducciones de nombre y descripción de módulo ejecutadas después de `init` al integrarse opcionalmente en TaxoCommerce Core.
- Se mantiene compatibilidad del plugin independiente, menús, hooks, Ojeador e Ingeniero.
- Pendiente de comprobar ausencia de avisos reales en STAGING tras la transferencia.

## [Unreleased] — Reutilización segura de traducciones (#2307)

- Completadas únicamente entradas gettext PO vacías con traducciones idénticas y no ambiguas ya existentes en otros catálogos TaxoCommerce.
- Regenerados los MO de los idiomas modificados. Se conservan los textos, identificadores técnicos, funciones y datos anteriores sin cambios.
- Esta corrección es parcial: los textos restantes, las cadenas fuera de gettext y la validación visual por idioma siguen abiertos en #2062.

## [Unreleased] — Regional WordPress locale fallback (#2295)

- Respeta primero los paquetes oficiales de WordPress .mo y .l10n.php para el locale exacto.
- Si falta un catálogo local regional, usa el catálogo existente del mismo idioma (es_ES, en_US, fr_FR, de_DE o nl_NL); no cambia la configuración ni el idioma del usuario.
- Sin cambios en lógica, datos, menús o dependencias del plugin. Las traducciones de contenido incompletas siguen abiertas en #2062.

## Unreleased - Familia i18n #2062


## [0.1.9] — 2026-11-03

- Nombre uniforme «Autocheck» y exportación JSON autenticada del último diagnóstico, sin ejecutar operaciones de escritura o procesos comerciales.
- Issue #2150, PR #2151; rutas internas y ejecuciones existentes conservadas.

- Dominio gettext, carga de catalogos locales, etiquetas de menús y textos HTML de salida estáticos; catálogos PO/MO sincronizados con el texto fuente.

## Unreleased

- [i18n #2062] Los menús de administración, las pestañas y los textos principales de Scrappers utilizan gettext con el dominio `taxocommerce-scrappers`, sin modificar sus identificadores internos. Pendiente: completar catálogos y auditar el resto de pantallas heredadas.

# CHANGELOG — TaxoCommerce Scrappers

## [0.1.8] — 2026-11-03

- Cabecera visual común basada en Core: nombre, icono, descripción, versión y diseño adaptable.
- Lógica funcional, pestañas y permisos intactos; sin dependencia de Core. Issue #2137.

> Historial maestro de versiones del plugin independiente. El origen monolítico se documenta en [`changelog.txt`](changelog.txt).

## [Unreleased]

- STAGING (#2096): restauración controlada por FTPS de los cinco catálogos MO de Scrappers que estaban corruptos en el servidor, utilizando copias previamente verificadas del repositorio. Comprobación de tablas binarias y SHA-256 tras cada transferencia, sin alterar las traducciones, el código del plugin ni los datos de WordPress. Sin publicar en Focazul ni PRODUCCIÓN.

- Inicio de internacionalización WordPress mediante gettext (`Domain Path: /languages`), catálogos por idioma y traducción de etiquetas del menú, sin cambios funcionales. Seguimiento: #2040 (plan global #2036). Cobertura restante de interfaz, Autotest y manuales pendiente de revisión por idioma.


Sin novedades adicionales pendientes.

## [0.1.7]

- Corregida la posición de anotaciones PHP_CodeSniffer que en 0.1.6 dejaban sin cobertura siete comprobaciones de SQL preparado y una validación de entrada. Consolidación de los identificadores de controles exclusivamente junto a la operación afectada.
- No se modifica el SQL ejecutado, la política de datos actuales ni el contrato de Ojeador/Ingeniero. Corregido después de un merge de 0.1.6 realizado antes de concluir Plugin Check.
- Repetir lint PHP, smoke, Autotest y Plugin Check del ZIP completo antes de fusionar.

## [0.1.6]

- Auditoría individual de los 208 avisos restantes de WordPress Plugin Check sobre Scrappers 0.1.5, sin desactivar reglas de análisis globales.
- Justificados en cada llamada los accesos SQL directos a tablas específicas de investigación/Ojeador/Ingeniero (con lecturas en tiempo real y escrituras), comprobaciones de esquema y uso administrativo de resultados actuales. No se añade caché sin una política fiable de invalidación.
- Documentadas en cada punto las lecturas GET de navegación y los datos de formularios leídos después de `guard()` (capacidad y nonce), los nombres de extensión heredados y las búsquedas acotadas por `product_cat`.
- Sin cambio de funciones, nombres públicos, consultas de negocio, datos, credenciales, configuraciones, procesos ni monolítico. Vinculado al Issue #2010.
- Las excepciones de Plugin Check son específicas y revisables; el número de advertencias no implica por sí solo que existiesen 208 fallos funcionales.

## [0.1.5]

- Preparada correctamente la tabla de ejecución en la migración de Ojeador mediante `%i` y `$wpdb->prepare`, sin modificar las condiciones ni las transiciones de estado.
- Ajustada la anotación SQL del exportador JSON a la llamada realmente analizada, con filtros y ordenaciones de lista blanca, y documentada la vinculación de los IDs de Ingeniero al `prepare`.
- Ampliado el smoke para verificar que la migración de Ojeador no interpola la tabla SQL ni permite volver al patrón antiguo.
- Se mantienen intactas las APIs heredadas, los workers y las credenciales. Persisten los avisos de acceso directo a tablas propias que no pueden reemplazarse por APIs de WordPress sin alterar funcionalidad; no se han deshabilitado comprobaciones globales. Issue #2010.

## [0.1.4]

- Contrastado el informe de Plugin Check de PRO del 01/11/2026 (6 errores y 290 avisos) con la rama `staging`: los cinco errores de dominio de traducción y la consulta insegura de vocabulario ya estaban corregidos en `0.1.3` (CI Plugin Check: 0 errores, 217 avisos).
- Ampliadas las pruebas de regresión para examinar todos los PHP de ejecución en `includes/` y asegurar que `assign_category_vocabulary()` usa `%i` para ambas tablas y `%d` para la categoría. Las pruebas no se empaquetan para WordPress.org.
- Publicación exclusivamente en STAGING mediante el workflow habitual; la instalación instalada en PRO conserva el estado anterior hasta una autorización expresa para producción. Issue #2010.

## [0.1.3]

- Revisión de Plugin Check a partir de los resultados del plugin instalado en PRO (5 errores heredados de textdomain y avisos SQL/nonce). Confirmado que los 5 usos heredados del dominio ya estaban corregidos en STAGING desde 0.1.2; se conserva `taxocommerce-scrappers`.
- Sustituidas interpolaciones de identificadores SQL por `%i` en consultas de Ingeniero y Ojeador; parámetros de paginación preparados y exportación JSON con fragmentos ORDER BY/WHERE limitados a una lista fija de confianza.
- Documentados los filtros GET puramente informativos. Se mantienen las validaciones nonce para cambios de estado, y las operaciones de investigación, compras de API y tareas existentes.
- Corregidos nueve errores de escape de enlaces HTML del Manual con `wp_kses_post()`; ajustadas dos consultas SQL del análisis de mercado para superar errores de Plugin Check sin cambiar sus filtros.
- Ampliadas pruebas smoke para impedir regresiones de texto y consultas; no se han alterado las tablas ni el monolítico. Issue #2010.

## [0.1.2]

- Incorporada una pestaña Manual con guía de usuario y referencia técnica de Connections, Ojeador e Ingeniero, incluidos permisos, tablas, hooks, configuración, prevención de costes y diagnóstico.
- Añadido Autotest manual y no destructivo por grupos: componentes, tablas, configuración local de proveedores y registro de controladores. No consulta APIs externas ni inicia procesos, no modifica la base de datos y nunca expone claves.
- Pruebas de regresión sin conexión, incluyendo protección de credenciales y comportamiento de estado. Los scripts de desarrollo permanecen excluidos del ZIP distribuible.
- Corregidos cinco usos del dominio de traducción heredado `taxocommerce-seo` en Ojeador e Ingeniero, sustituidos por `taxocommerce-scrappers` para superar el control de internacionalización del plugin independiente.
- Parametrizados los identificadores SQL de las tablas de vocabulario mediante `%i` en la consulta de asignación de categorías, conservando los filtros y resultados. Seguimiento de Plugin Check: #1926. Manual y Autotest: #1964.


## [0.1.1]

- Corregido el fallo fatal en la activación independiente de Scrappers al no estar definida la interfaz del proceso Ingeniero. Contrato compatible y protegido frente a redeclaración cuando coexiste con el monolítico.
- Se conserva el supervisor existente, sin duplicar procesos ni modificar el monolítico. Issue #1926.

## [0.1.1]

- Corregido el fallo fatal en la activación independiente de Scrappers al no estar definida la interfaz del proceso Ingeniero. Contrato compatible y protegido frente a redeclaración cuando coexiste con el monolítico.
- Se conserva el supervisor existente, sin duplicar procesos ni modificar el monolítico. Issue #1926.


## [0.1.0]

* Created standalone TaxoCommerce Scrappers plugin.
* Copied the legacy Ojeador 0.10.0 engine.
* Copied the legacy Ingeniero 0.5.0 engine.
* Centralized SerpApi and ScraperAPI configuration in Connections.
* Added Ojeador and Ingeniero as tabs of the standalone plugin.
* Registered Scrappers in TaxoCommerce Core for local ZIP installation/activation.

## Fuentes

- [`changelog.txt`](changelog.txt), etapa monolítica.
- [`docs/PLUGIN-HISTORY-SOURCES.md`](../docs/PLUGIN-HISTORY-SOURCES.md), PR y referencias históricas.

## Actualizaciones

Para nuevas funcionalidades se edita este `CHANGELOG.md`. Antes de publicar, se debe reflejar el resumen de la versión en `readme.txt`, que es el único Changelog mostrado automáticamente por WordPress.org.
