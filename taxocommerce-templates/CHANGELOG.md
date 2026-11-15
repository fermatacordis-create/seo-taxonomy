## [0.4.33] — 2026-11-13 — Code Check equiparado a Core 0.4.82 (#2626)

- Code Check equiparado a Core 0.4.82: reglas autónomas PHP/WordPress/seguridad, clasificación por gravedad, detalles de archivos y recomendaciones, resumen y JSON; sin modificar el Autotest funcional. Issue #2626.
- Continúa siendo una revisión estática independiente del Plugin Check oficial, con ejecución por bloques y control de CPU.

## [0.4.32] — 2026-11-10 — Cabecera en tres zonas sin contenido duplicado (#2521)

- Izquierda: nombre, descripción local del plugin y un único enlace estático `Plugin URI` bajo el texto.
- Centro: desplegable más ancho con el historial local `CHANGELOG.md`, cerrado inicialmente.
- Derecha: versión y fecha reales del propio plugin.
- Se retira la segunda caja «Información del plugin», sin cambios en servicios, menús ni consultas externas.

## [0.4.31] — 2026-11-10 — Descripción visible desde el plugin (#2494)

- La descripción inmediatamente debajo del título administrativo procede de `Description:` del archivo principal, sin textos duplicados ni consultas externas.
- Se mantienen el enlace estático `Plugin URI`, el historial local y las funcionalidades anteriores.

## [0.4.30] — 2026-11-10 — Ficha local en el encabezado (#2494)

- Elimina consultas REST/caché del extracto y usa `Description` y `Plugin URI` locales.
- Conserva el enlace oficial y el historial, sin alterar servicios existentes.

## [0.4.29] - 2026-11-10 - Resiliencia del extracto oficial (#2489)

- Consulta exclusivamente la API REST pública de la página indicada en Plugin URI (autor del plugin), nunca páginas de la tienda del cliente.
- Conserva el último extracto oficial comprobado; los fallos de red no borran el texto. Caché de errores reducida a 5 minutos y versión nueva de clave para ignorar fallos antiguos cacheados.
- Si nunca se ha obtenido un extracto, muestra provisionalmente la Description incluida por el autor en el plugin; permite abrir la ficha oficial. No cambia operaciones comerciales ni requiere Core.
- Pruebas para recuperación tras fallos HTTP, JSON inválido y caché negativa; compatibles con la familia completa.

## [0.4.28] — 2026-11-09 — Ficha oficial del desarrollador y cabecera centrada en tres zonas (#2476)

- La cabecera distribuye identificación a la izquierda, dos desplegables alineados y centrados (extracto oficial a la izquierda e historial local a la derecha), y versión y fecha a la derecha; distribución adaptable a móvil.
- El enlace oficial se obtiene exclusivamente de Plugin URI, definido por el autor del plugin. Eliminados el formulario de URL y su acción de guardado; los ajustes antiguos de la tienda no pueden sobrescribirlo.
- El historial se lee del CHANGELOG.md incluido, plegado de inicio. Sin dependencias obligatorias entre plugins ni cambios en productos, pedidos, datos o funcionalidades existentes.

## [0.4.27] — 2026-11-08 — Información dinámica desde ficha oficial (#2443)

- El desplegable izquierdo consulta el excerpt publicado en la página Plugin URI, con caché y configuración independiente.
- Formulario sanitizado; soporte para desactivación y funcionamiento sin servicios externos. Historial local sin cambios.

## [0.4.26] — 2026-11-08 — Extracto público remoto opcional (#2443)

- Consulta opcional por HTTPS del excerpt REST de una ficha oficial configurada; muestra el enlace a esa página en un panel izquierdo plegado.
- Mantiene el historial propio en el panel derecho. Caché, permisos y escape de salida; sin servicios obligatorios ni cambios en datos.

## [0.4.25] — 2026-11-08 — Política de cookies y preferencias en pies de página ES/US/FR/DE/NL (#2434)

- Pie español compartido: enlace a Política de cookies y, cuando Insights esté activo, a Preferencias de cookies.
- Piloto VEVOR y clústeres internacionales: enlaces del país correspondiente, en el idioma del mercado y sin remitir a la página legal española.
- Plantilla internacional de contenido legal para páginas US/FR/DE/NL, con pie de página consistente. Página española en plantilla corporativa actual.
- Enlaces exclusivamente a páginas publicadas cuyo slug y país coincidan; los borradores no originan enlaces rotos.
- Las preferencias reabren el banner existente de Insights sin otorgar ni cambiar permisos.
- Prueba de regresión para países, visibilidad de borradores y preferencias. Sin alterar productos, pedidos ni cookies almacenadas.

## [0.4.24] - 2026-11-08 - Autotest funcional y de codigo (#2401)

- Conserva autotest funcional y JSON; nuevo Code Check independiente del propio plugin: PHP, WordPress, seguridad. Cinco velocidades, lotes y JSON separado. Sin PCP ni Core obligatorio.

## [0.4.23] — 2026-11-08 — Historial plegable en cabecera (#2382)

- Desplegable cerrado por defecto inmediatamente debajo del título, con el historial propio de `CHANGELOG.md`; sin nueva pestaña ni dependencia de otros plugins.
- Textos del título preparados para ES/EN/FR/DE/NL, salida escapada, desplazamiento vertical y mantenimiento de menús y funciones existentes.
- El paquete instalable y la subida incremental incluyen únicamente el historial raíz necesario. Sin cambios en base de datos ni servicios.

## [0.4.22] — 2026-11-07 — Cabecera administrativa de versión y fecha (#2336)

- Muestra título, versión activa y fecha de publicación en el administrador mediante Release Date.
- La fecha no depende de la hora de consulta ni del momento de copia por FTP. Sin cambios en servicios, datos, menús ni permisos.

## [Unreleased] — Catálogos de idioma completos para el enrutador (#2328)

- Incorporadas 42 claves gettext que faltaban en los catálogos FR/DE/NL para políticas, enrutamiento, diseñador y asignaciones (126 traducciones nuevas).
- Traducidas 4 cadenas plurales (singular/plural) en cada uno de los cinco idiomas (20 mensajes plurales completos).
- Añadidas otras 47/46/46 traducciones iniciales de etiquetas en FR/DE/NL, respectivamente, y completadas las 99 cadenas restantes en cada idioma (297 traducciones adicionales), incluyendo avisos, configuraciones y textos de los manuales.
- Regenerados los cinco catálogos MO (es_ES, en_US, fr_FR, de_DE, nl_NL) a partir de los PO actualizados. Los cinco PO actuales tienen 201 claves de gettext, cuatro mensajes plurales y ninguna traducción vacía; el catálogo coincide con su POT. No se han comprobado todavía todos los textos PHP/JS fuera de gettext ni la interfaz real de WordPress. Las cinco variantes tienen 201 mensajes más la cabecera gettext, con claves idénticas.
- Sin cambios de lógica PHP, datos, permisos, rutas o configuración. Pendiente validación externa con Plugin Check y comprobación visual por locale.

## [Unreleased] — Traducciones gettext (taxocommerce-templates)

- Completadas las entradas de traducción vacías detectadas en los catálogos de EN/FR/DE/NL; idioma ES ya tenía traducción.
- No se modifica la lógica, el contenido de tienda ni los ajustes del plugin. Seguimiento global #2062.

## [Unreleased] — Reutilización segura de traducciones (#2307)

- Completadas únicamente entradas gettext PO vacías con traducciones idénticas y no ambiguas ya existentes en otros catálogos TaxoCommerce.
- Regenerados los MO de los idiomas modificados. Se conservan los textos, identificadores técnicos, funciones y datos anteriores sin cambios.
- Esta corrección es parcial: los textos restantes, las cadenas fuera de gettext y la validación visual por idioma siguen abiertos en #2062.


## [0.4.21] — 2026-11-07 — Reconciliación editorial de categorías (#2298)

- Nueva pestaña de mantenimiento SEO dentro de Templates que compara, sin modificar datos, la descripción nativa de WooCommerce con la descripción histórica efectivamente utilizada desde `seo_nodes`.
- Sincronización optativa y selectiva por categoría, protegida por permisos de administrador, nonce y huella de ambas versiones; solo se actualiza el registro histórico existente y se conserva el resto de la tabla.
- Compatible con tiendas sin WooCommerce o sin `seo_nodes`: no realiza escrituras ni altera contenido. No introduce claves ni categorías específicas de una instalación.
- No se sincronizan automáticamente todas las categorías ni se ejecutan migraciones durante la activación. Requiere revisión visual y pruebas en STAGING/Focazul antes de producción.

## [0.4.20] — 2026-11-07 — SEO piloto internacional FR/DE/NL/US (#2259)

- El piloto VEVOR de los cuatro mercados usa una cabecera SEO independiente de las funciones globales del antiguo SEO Taxonomy, sin modificar el diseño ni el contenido de WordPress.
- Publica una única canonical propia (incluida la paginación), mantiene hreflang y scripts de wp_head, elimina descripciones y verificaciones Pinterest duplicadas y evita el segundo viewport.
- La descripción de reserva procede de contenido existente en el idioma de la página o de la ficha, solo cuando ningún proveedor SEO genera una descripción válida.
- Prueba offline de regresión FR/DE/NL/US y su ejecución obligatoria en el workflow de validación STAGING. PRO no se modifica.

## [Unreleased] — Regional WordPress locale fallback (#2295)

- Respeta primero los paquetes oficiales de WordPress .mo y .l10n.php para el locale exacto.
- Si falta un catálogo local regional, usa el catálogo existente del mismo idioma (es_ES, en_US, fr_FR, de_DE o nl_NL); no cambia la configuración ni el idioma del usuario.
- Sin cambios en lógica, datos, menús o dependencias del plugin. Las traducciones de contenido incompletas siguen abiertas en #2062.

## [0.4.19] — 2026-11-06 — Bing: títulos de archivo y ALT de portada (#2259)

- Cuando el título nativo de WordPress supera 69 caracteres, omite la coletilla del nombre del sitio; no renombra categorías, entradas, páginas ni sus URLs. Solo cuando están activas las políticas de Templates.
- Las imágenes enlazadas de categorías, clústeres, hubs y artículos de portada reciben un texto alternativo contextual y escapado en variantes de escritorio y móvil, en vez de alt vacío.
- Prueba de regresión aislada que verifica títulos cortos sin cambios, preservación del título principal, ausencia de afectación al administrador y ALT informativos.
- Requiere comprobación del HTML real en STAGING y producción cuando se autorice instalar esta versión. Los avisos históricos de Bing pueden persistir hasta nuevo rastreo.

## [0.4.18] — 2026-11-06 — Meta descriptions para Bing (#2259)

- Elimina el meta description genérico de la portada cuando wp_head ya añade otro. Conserva una sola descripción SEO por documento, sin interferir en otras etiquetas ni scripts de wp_head.
- Genera descripción de reserva solo si WordPress/SEO no proporciona ninguna, a partir del extracto/contenido de la página, descripción de taxonomía o lema de portada; no genera etiquetas en 404/búsquedas.
- Preserva la canonical de la política ya existente de Templates y comprueba regresiones con y sin proveedor SEO.
- No modifica productos, categorías ni páginas existentes; los avisos por URL exacta de Bing requieren CSV para identificar el contenido afectado.

## [0.4.17] — 2026-11-05 — Corrección de advertencias WPC (#2189)

- Centralizados los accesos a tablas propias mediante `TCT_DB`, con caché de lectura limitada a la petición, claves efímeras e invalidación tras escrituras. Conserva los mismos SQL, datos, filtros y contratos públicos; no depende de otros plugins TaxoCommerce.
- Los filtros de taxonomías, metadatos y exclusiones siguen sujetos a los límites funcionales existentes y se documentan con excepciones PHPCS localizadas, sin desactivar reglas globales.
- El `IN` del registro de rutas emplea exclusivamente marcadores `%s` generados y argumentos enlazados con `wpdb::prepare` (excepción PHPCS acotada al bloque).
- Prueba offline de reutilización de caché y lectura inmediata tras actualización; versión 0.4.17. Requiere verificación real WPC estricta y UAT antes de fusionar; no se declara publicación WordPress.org apta mientras exista el editor PHP ejecutable (#1522).

## [Unreleased] — 2026-11-05 — Corrección WPC y verificación independiente (#2189)

- Reestructurada la salida HTML de la etiqueta de rol en `class-tct-routing-admin.php` para que la traducción y el escape se ejecuten en ramas explícitas. No cambian las rutas ni las asociaciones.
- Informe WPC del 05/11/2026 a las 19:09: 1 ERROR y 503 WARNING antes de este cambio. El workflow genérico debe verificar si el ERROR desaparece; las advertencias SQL/caché/rendimiento continúan pendientes. No se declara apto para WordPress.org.
- Solo validación por PR, sin despliegue a producción ni activación de políticas.

## [Unreleased] — 2026-11-05 — Pruebas de regresión y despliegue (Issue #2212)

- Reparadas dos pruebas offline obsoletas/rotas que bloqueaban el despliegue incremental de todos los plugins: comprobación de pestañas actualizada al gettext nativo de WordPress, y corrección de sintaxis en la prueba de advertencias de Plugin Check. Sin modificar funcionalidad ni archivos PHP instalables de Templates.

## [0.4.16] — 2026-11-05

- Internacionalización EN/ES nativa de la administración, cabeceras, enrutamiento y políticas; diccionarios `.po/.mo` completos y validados. No cambia reglas ni rutas.

## [0.4.15] — 2026-11-05

- Segundo lote parcial de avisos #2189: identificadores SQL con `%i` en las asignaciones de página, relaciones jerárquicas, ejemplos de auditoría y catálogo de roles; defensas para entradas de administración no escalares y comprobación del tipo del nonce en el metabox.
- Ampliación del lote: 34 consultas SQL en 17 plantillas de clústeres, hubs, categorías, productos, tienda, blog y piloto VEVOR ahora enlazan identificadores de tablas mediante `%i` y `$wpdb->prepare()`. Se revisan además SQL de afiliación, catálogo y utilidades, sin alterar las relaciones funcionales.
- Comprobaciones estrictas de tipo en parámetros GET y saneamiento de interruptores; los parámetros de navegación GET (solo lectura) y los POST bajo un nonce existente se documentan con excepciones de análisis estrictamente localizadas, nunca globales.
- Pruebas PHP y regresiones de enrutamiento conservadas; queda pendiente cerrar las advertencias de consultas directas/caché, seguridad y rendimiento detectadas en GitHub Actions.
- Sin cambios funcionales previstos en las asignaciones. No se declara cero avisos ni compatibilidad WordPress.org, pendiente Plugin Check/UAT y resolución del editor PHP privado (#1522).

## [0.4.14] — 2026-11-05

- Primer lote de advertencias del nuevo Plugin Check (#2189): identificadores SQL preparados con `%i` en el registro de peticiones, router, asociaciones, guardado de roles y editor; conserva los filtros `IN` parametrizados y las reglas existentes.
- Los nombres entrantes del editor comprueban el tipo escalar antes de sanearse; el código PHP original se conserva únicamente bajo la protección previa de permisos y nonce. La edición de PHP ejecutable continúa siendo una función privada y un bloqueo para WordPress.org (#1522).
- El estilo de clúster internacional declara versión para evitar caché antigua. La navegación administrativa de solo lectura se documenta separada de las acciones POST protegidas.
- Pendientes avisos de seguridad, acceso SQL/caché y rendimiento en otras rutas; no se afirma 0 warnings ni se altera producción. Requiere Plugin Check real y UAT tras el despliegue.

## [0.4.13] — 2026-11-04

- Corregidos los **32 errores SQL** detectados por Plugin Check (22:23:34) en 16 archivos: correo heredado y plantillas front, shop, landing, página, post, producto, categoría y soluciones, tanto móvil como escritorio (#2187).
- Consultas con identificadores de tablas enlazados mediante `$wpdb->prepare()` y marcadores `%i` (WordPress 6.5+). Se conservan filtros, parámetros numéricos, JOIN, ordenación, resultados y rutas existentes; también el SQL construido de entradas relacionadas.
- Prueba de regresión de los 32 puntos y comprobación de sintaxis de las plantillas ejecutables en CI. No se crean trabajos automáticos de AutoCheck ni se modifica producción.
- Quedan pendientes un nuevo Plugin Check del plugin desplegado, advertencias de seguridad/rendimiento y la separación del editor PHP privado antes de WordPress.org.

## [0.4.12] — 2026-11-04

- Corregidas las 12 incidencias ERROR del informe Plugin Check en cuatro archivos: auditoría CSV, lectura de router, editor de archivos y descarga de plantillas mediante las APIs de WordPress (`WP_Filesystem`, `wp_delete_file`).
- Conservadas descarga de PHP, exportación CSV, resolución de variantes, control de permisos, validación PHP, confirmación de sobrescritura, copias de seguridad y editor privado. No se ejecuta ningún AutoCheck automáticamente.
- Prueba funcional de sistema de archivos y regresión incluida en CI. Issue #2182; publicación en WordPress.org sigue pendiente por editor de PHP ejecutable y advertencias.

## [0.4.11] — 2026-11-04

- Pestaña **Manual** propia con manual de usuario, manual técnico e incidencias: navegación, políticas, enrutamiento, módulos, mercados, edición PHP, roles, auditoría, Autocheck, arquitectura, hooks, almacenamiento, seguridad, migración y UAT.
- Sin escrituras ni ejecuciones automáticas. Prueba de humo independiente de lectura, permisos y navegación. Issue #2159.
- No se modifican plantillas públicas, reglas, permisos existentes ni datos.

## Unreleased - Familia i18n #2062


## [0.4.10] — 2026-11-03

- Nueva pestaña «Autocheck» independiente: comprueba clases, rutas, tablas y políticas sin cambiar plantillas; exporta JSON autenticado.
- Issue #2150, PR #2151; rutas existentes conservadas y sin pruebas de escritura.

- Dominio gettext, carga de catalogos locales, etiquetas de menús y textos HTML de salida estáticos; catálogos PO/MO sincronizados con el texto fuente.

# CHANGELOG — TaxoCommerce Templates

## [0.4.9] — 2026-11-03

- Cabecera visual común basada en Core, con nombre, icono, descripción y versión desde la cabecera real.
- Rutas, pestañas, permisos y funciones conservados, sin dependencia obligatoria entre plugins. Issue #2137.

## 0.4.8 — 2026-11-01
- Protección de **consentimiento explícito tras actualización**: la opción `taxocommerce_templates_review_consent_v1` permanece a cero al instalar una versión nueva, por lo que las políticas públicas no se activan aunque el interruptor heredado `taxocommerce_templates_render_enabled` estuviera activado.
- La casilla visible **Aplicación de políticas** sigue siendo el único control de administración. Al guardar marcada establece el consentimiento y habilita las reglas; desmarcada retira primero el consentimiento de forma segura. No se pierden diseños, países, archivos, relaciones ni borradores.
- Todos los resolutores de plantillas de WordPress/WooCommerce, el enrutamiento PHP heredado, las vistas por país, los correos y el estado mostrado en el administrador utilizan el mismo estado efectivo, evitando discrepancias.
- Se mantiene la posibilidad de editar plantillas con el plugin instalado y políticas suspendidas. Las reglas VEVOR especiales del monolito no se modifican.
- Pruebas de regresión: actualización con opción antigua activa, nueva autorización explícita, suspensión de emergencia y conservación de configuraciones. No hay activación automática del nuevo enrutador ni despliegue a PRODUCCIÓN en este cambio.


## 0.4.7 — 2026-11-01
- **Interruptor maestro de políticas** visible en las dos pestañas de Templates: permite mantener el plugin activo y gestionar plantillas sin aplicarlas al frontend.
- Se reutiliza la opción histórica `taxocommerce_templates_render_enabled`; su valor se conserva durante la actualización. En instalaciones nuevas comienza desactivada.
- Con la casilla desmarcada, el plugin no registra filtros públicos de sustitución de plantillas para WordPress (`template_include`), comentarios, archivos y fragmentos WooCommerce ni clases de correo; tampoco carga el helper ES de escaparate ni ejecuta rutas por país mediante sus resolutores.
- La casilla es un control de emergencia que funciona incluso si las tablas `seo_templates`/`seo_nodes` no están disponibles. Tiene control de permisos administrativos y nonce.
- Se retiran los interruptores duplicados y ocultos: solo existe un control superior, con estado explícito y botón de guardar. El editor, los roles, los diseños, la biblioteca y los archivos almacenados siguen disponibles mientras las políticas están suspendidas.
- Al activar la casilla se recupera el comportamiento previo, respetando las rutas individuales, borradores y prioridad VEVOR. No se modifica ninguna plantilla pública, país ni asignación guardada.
- Ampliadas pruebas de regresión para confirmar que el estado OFF devuelve el archivo original de WordPress/WooCommerce, preserva correos, comentarios, el piloto VEVOR y los diseños almacenados.


## 0.4.6 — 2026-10-31
- Instancias de plantilla identificables con nombres autogenerados editables para una misma petición/rol, sin sobrescribir las rutas históricas de `seo_templates`.
- Cada plantilla guarda su identificador, petición/rol, archivo PHP opcional, países ES/US/DE/NL/FR y composiciones separadas por **país y dispositivo** mediante la opción `tct_template_designs_v1` (`autoload=false`); no se añade ninguna tabla SQL.
- El botón **Editar** de Enrutamiento abre exclusivamente la configuración guardada de esa plantilla. **Crear otra plantilla** permite múltiples diseños para el mismo tipo y rol, asignados a países distintos.
- El enrutamiento público puede usar plantillas PHP activadas explícitamente por país y rol, respetando el interruptor general y las rutas VEVOR con prioridad; detecta y rechaza solapamientos de países entre dos instancias activas de la misma ruta.
- Las composiciones visuales sin archivo PHP quedan como borradores hasta implementar y validar el renderizador modular. Los diseños nuevos no reemplazan páginas públicas ni se activan automáticamente.
- Se reutilizan los módulos y controles del compositor anterior, preservando los borradores FRONT existentes, las asociaciones legadas, la biblioteca y los formularios técnicos.
- Pruebas ampliadas para identidad, países, independencia de configuraciones, seguridad y fallback.


## 0.4.5 — 2026-10-31
- Enrutamiento abre por defecto **Mis plantillas**: solo asociaciones con archivo y estado activo, incluidas aquellas cuyo archivo falta para poder corregirlas.
- Filtros de trabajo **Mis plantillas**, **Por configurar** y **Todas**, conservando búsqueda, familia y paginación. Los fragmentos internos de WooCommerce quedan fuera de la vista de pendientes y solo se enumeran en el inventario completo, sin perder ninguna opción.
- El total ahora describe plantillas asignadas, rutas pendientes u opciones de catálogo, según el filtro, en lugar de llamar «relaciones» a las posibles rutas.
- Las instalaciones nuevas se inicializan **sin archivos asignados y con rutas inactivas**; permanecen disponibles todas las sugerencias para que el administrador las configure progresivamente. En instalaciones existentes no se modifica ni desactiva ninguna relación guardada.
- Se reutilizan los gestores y la tabla `seo_templates`; no se alteran los renderizadores públicos, el motor VEVOR ni las plantillas existentes. Se amplían las pruebas de regresión de vistas y migración.


## 0.4.4 — 2026-10-31
- La administración pasa a **Enrutamiento** y **Diseñador de plantillas**. Auditoría queda accesible como diagnóstico técnico, sin pestaña principal.
- Nueva tabla editable y filtrable que combina peticiones WordPress/WooCommerce, roles SEO registrados, asignación de archivo y estado del motor. Incluye los tipos públicos de contenido y taxonomías que registra WordPress.
- Cada relación se modifica desde el administrador y se guarda en `seo_templates`, mediante nonce, permisos y validación de archivos; se permite devolver una ruta al respaldo nativo de WordPress.
- Los enlaces de cada fila llevan al Diseñador para consultar o crear su archivo. Se mantienen los accesos anteriores y los formularios de asignación de páginas a roles, así como la biblioteca y el compositor FRONT.
- Se aclara que las rutas internacionales VEVOR tienen una prioridad especializada y que el enrutamiento configurado no equivale a haber comprobado la plantilla finalmente ejecutada. No se activa automáticamente el motor.
- Sin modificaciones de plantillas públicas, contenidos, catálogos, imágenes externas VEVOR ni reglas de campaña. Pruebas smoke ampliadas.


## 0.4.3 — 2026-10-31
- Unificada la administración de plantillas en solo dos pestañas: **Gestor de plantillas** y **Auditoría**.
- El gestor organiza el compositor FRONT, asociaciones de rol/archivo, biblioteca PHP y rutas WordPress/WooCommerce en secciones de una sola pantalla, que se abren con un clic. La sección no seleccionada no ejecuta consultas costosas.
- Se mantienen operativos los enlaces anteriores (`tab=composer`, `tab=roles`, `tab=editor`, `tab=requests`), sus filtros, permisos, redirecciones, subida y edición PHP.
- Conservadas las bases de datos, la composición FRONT y sus borradores, la asignación de roles y las plantillas públicas sin alteración. No se activa el renderizador modular.
- Añadidas comprobaciones de regresión para la navegación consolidada.


## 0.4.2 — 2026-10-31
- Clústeres de US/DE/NL/FR: presentación internacional adaptable con idioma, menú por país y contenido relacionado filtrado por mercado; sin elementos comerciales españoles ni descarga de imágenes de proveedor.
- Los clústeres españoles mantienen las plantillas originales de móvil y ordenador.
- Pruebas de aislamiento por país, navegación, marcado estructurado y coexistencia con el compositor FRONT.


## 0.4.1 — 2026-10-30
- Se añade el **compositor FRONT** como piloto administrativo dentro de TaxoCommerce Templates.
- Inventario cerrado de módulos con altas/bajas, ordenación mediante arrastre y controles de teclado, visibilidad y etiqueta opcional.
- Configuración independiente por **mercado (ES/US/DE/NL/FR)** y por dispositivo (**ordenador/móvil**), con nombre y rol automático `front_page`.
- Guardado de borradores con capacidades de administrador, nonce, saneamiento y lista blanca de módulos.
- **No activa el nuevo renderizador ni altera la portada existente, las asignaciones, las campañas o las imágenes VEVOR.** La activación pública queda para una fase posterior, después de probar la interfaz en staging.
- Se incluyen pruebas de validación de composiciones e invariantes del FRONT original.

> Este fichero documenta únicamente cambios verificables desde la versión 0.4.1. No reconstruye ni atribuye versiones históricas sin evidencia.
