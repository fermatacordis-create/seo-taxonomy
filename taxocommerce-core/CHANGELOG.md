## [0.4.78] — 2026-11-11 — Corrige chequeo local y paquete WordPress.org (#2565)

- Corrige los 1.022 errores y la advertencia de discrepancia de dominio de traducción identificados al comprobar Core instalado en `taxocommerce-core/`. El plugin instalado recupera el dominio nativo `taxocommerce-core`, sin modificar menús, tablas ni funciones.
- El empaquetado de WordPress.org transforma **una copia** del mismo código: carpeta, archivo principal, dominio gettext y catálogos adoptan el slug `taxocommerce-core-catalog-content-management-for-woocommerce`, reservado durante la revisión.
- La CI exige Plugin Check estricto independiente de **ambas distribuciones** (0 errores, 0 advertencias) y deja de examinar 20 plugins cuando solo cambia uno.
- Se conservan las traducciones ES, EN, FR, DE y NL, los datos y los identificadores internos de cada tienda; no se toca otro plugin.

## [0.4.77] — 2026-11-11 — Dominio gettext y slug de WordPress.org (#2560)

- Se alinean las llamadas de traducción y la cabecera con el slug asignado `taxocommerce-core-catalog-content-management-for-woocommerce`, eliminando las causas de los 1.053 errores `TextDomainMismatch` comunicados.
- Se incluyen catálogos de los cinco idiomas ES/EN/FR/DE/NL con el dominio oficial sin alterar los textos traducidos; los catálogos anteriores quedan disponibles para las instalaciones GitHub heredadas.
- Se mantiene la ruta técnica de las instalaciones actuales, así como los slugs de administración, las tablas y las opciones, evitando desactivaciones.
- Se valida y empaqueta Core para WordPress.org bajo su carpeta oficial, sin modificar los demás plugins ni el comportamiento de contenidos, menús o pedidos.

## [0.4.76] — 2026-11-11 — Preparar documentación WordPress.org (#2557)

- Se simplifica `readme.txt`, conservando descripción, funcionalidades, instalación, preguntas frecuentes e historial reciente; el histórico completo sigue en `CHANGELOG.md` y `changelog.txt`.
- Se documenta la versión 0.4.75 omitida en `changelog.txt` y se alinea la versión 0.4.76 con el `Stable tag` de WordPress.org.
- No se modifica el slug ni el text domain: su aceptación debe confirmarse con WordPress.org mediante el correo de revisión.
- Sin cambios de comportamiento, formularios, datos, permisos ni interfaces del plugin.

## [0.4.75] — 2026-11-11 — Descarga completa JSON de Core → Contenidos → Anomalías

- Nuevo botón «Descargar JSON de todas las anomalías» junto a «Mostrar todas las comprobaciones», siempre visible cuando la arquitectura está configurada.
- La exportación recupera en tiempo real las mismas secciones con incidencias que genera el informe de Anomalías. Incluye relaciones SEO, hubs, categorías, entradas/landings, contadores de categorías y FAQs cuando existan.
- Solo lectura, sin duplicar reglas SQL ni incluir código HTML, formularios ni nonces en el JSON. No limita la cantidad de líneas de evidencia ni depende de «Mostrar todas».
- Conserva la exportación JSON editorial ya existente, separada del informe completo.
- Nueva regresión para el contenido exportado y la ausencia de tokens de seguridad; incluida en las validaciones de despliegue.
- No modifica categorías, sus relaciones, productos ni otros módulos.

## [0.4.74] — 2026-11-10 — Cabecera en tres zonas sin contenido duplicado (#2521)

- Izquierda: nombre, descripción local del plugin y un único enlace estático `Plugin URI` bajo el texto.
- Centro: desplegable más ancho con el historial local `CHANGELOG.md`, cerrado inicialmente.
- Derecha: versión y fecha reales del propio plugin.
- Se retira la segunda caja «Información del plugin», sin cambios en servicios, menús ni consultas externas.

## [0.4.73] — 2026-11-10 — Suprimir solo el aviso Action Scheduler (#2510)

- Se desactiva exclusivamente la comprobación visual de acciones vencidas que genera el aviso superior «Action Scheduler: past-due actions».
- No se modifica ni detiene Action Scheduler, WordPress Cron, WooCommerce, sus colas, otros avisos ni funcionalidades comerciales.

## [0.4.72] — 2026-11-10 — Descripción visible desde el plugin (#2494)

- La descripción inmediatamente debajo del título administrativo procede de `Description:` del archivo principal, sin textos duplicados ni consultas externas.
- Se mantienen el enlace estático `Plugin URI`, el historial local y las funcionalidades anteriores.

## [0.4.71] — 2026-11-10 — Ficha estática sin conexión remota (#2494)

- El encabezado muestra únicamente `Description` y `Plugin URI` de Core, sin consultas REST, botones de consentimiento ni caché de extractos.
- Mantiene el enlace a la ficha oficial, historial local y todas las funciones, sin dependencias nuevas.
- `uninstall.php` conserva la eliminación de los cachés históricos de versiones anteriores.

## [0.4.70] — 2026-11-10 — Consentimiento en la ficha del desarrollador (#2491)

- La portada Core no consulta servidores externos al abrirse, aunque expire el caché.
- La carga del extracto exige acción explícita, `manage_options` y nonce válido; no hay llamadas con peticiones no autorizadas.
- Se conserva el extracto de la caché, el último extracto verificado por #2489 y el fallback a Description de autor sin llamadas externas.
- `readme.txt` declara el endpoint, propósito y datos recibidos por el proveedor; se conserva el enlace y la cabecera de tres zonas.
- Regresión nueva para permisos, nonce, caché y funcionamiento offline.
- Al desinstalar, elimina exclusivamente la opción del último extracto verificado y sus cachés Core (incluidos los históricos); conserva los datos canónicos de WordPress y WooCommerce.

## [0.4.69] - 2026-11-10 - Resiliencia del extracto oficial (#2489)

- Consulta exclusivamente la API REST pública de la página indicada en Plugin URI (autor del plugin), nunca páginas de la tienda del cliente.
- Conserva el último extracto oficial comprobado; los fallos de red no borran el texto. Caché de errores reducida a 5 minutos y versión nueva de clave para ignorar fallos antiguos cacheados.
- Si nunca se ha obtenido un extracto, muestra provisionalmente la Description incluida por el autor en el plugin; permite abrir la ficha oficial. No cambia operaciones comerciales ni requiere Core.
- Pruebas para recuperación tras fallos HTTP, JSON inválido y caché negativa; compatibles con la familia completa.

## [0.4.68] — 2026-11-09 — WordPress.org pre-review: styles, ZIP uploads, security (#2481)

- Move three in-page CSS blocks (Autocheck, classic hierarchy, SEO hierarchy) to the existing enqueued `assets/admin.css` while preserving selectors, styles and responsive rules.
- Replace admin-head sidebar `<style>` with `wp_add_inline_style()` on the already-enqueued Core style handle; do not change registered menu entries or user permissions.
- Replace direct `move_uploaded_file()` handling of administrator-selected plugin ZIPs with native `wp_handle_upload()`, maintaining ZIP and expected plugin basename checks, capabilities, nonce and Plugin_Upgrader installation.
- Keep explicit administrator-initiated extension installation, activation and deactivation with nonce/capability validation; WooCommerce remains optional for native WP content functionality.
- WordPress.org approval request: propose `taxocommerce-core` as the reserved directory slug matching the existing plugin basename and gettext domain. This request must be explicitly stated in the review email.

## [0.4.67] — 2026-11-09 — Ficha oficial del desarrollador y cabecera centrada en tres zonas (#2476)

- La cabecera distribuye identificación a la izquierda, dos desplegables alineados y centrados (extracto oficial a la izquierda e historial local a la derecha), y versión y fecha a la derecha; distribución adaptable a móvil.
- El enlace oficial se obtiene exclusivamente de Plugin URI, definido por el autor del plugin. Eliminados el formulario de URL y su acción de guardado; los ajustes antiguos de la tienda no pueden sobrescribirlo.
- El historial se lee del CHANGELOG.md incluido, plegado de inicio. Sin dependencias obligatorias entre plugins ni cambios en productos, pedidos, datos o funcionalidades existentes.

## [0.4.66] — 2026-11-08 — Información dinámica desde ficha oficial (#2443)

- El desplegable izquierdo consulta el excerpt publicado en la página Plugin URI, con caché y configuración independiente.
- Formulario sanitizado; soporte para desactivación y funcionamiento sin servicios externos. Historial local sin cambios.

## [0.4.65] — 2026-11-08 — Extracto público remoto opcional (#2443)

- Consulta opcional por HTTPS del excerpt REST de una ficha oficial configurada; muestra el enlace a esa página en un panel izquierdo plegado.
- Mantiene el historial propio en el panel derecho. Caché, permisos y escape de salida; sin servicios obligatorios ni cambios en datos.

## [0.4.64] - 2026-11-08 - Autotest en dos subpestanas (#2401)

- Mantiene el Autotest funcional de Core. Incorpora Code Check autonomo (PHP, WordPress, seguridad), por lotes y JSON.
- Analizador vendorizado sin depender de otros plugins o PCP; diagnostico de solo lectura y permisos verificados.

## [0.4.63] — 2026-11-08 — Historial plegable de Core (#2382)

- La cabecera del panel principal de Core muestra «Historial de versiones y mejoras» cerrado por defecto y obtenido de su propio `CHANGELOG.md`.
- Traducción ES/EN/FR/DE/NL mediante gettext y respaldo por idioma, contenido escapado y altura limitada.
- Conserva indicadores, navegación y funcionalidades anteriores; no crea tablas ni depende del historial de otra instalación.

## [0.4.62] — 2026-11-08 — Catálogos gettext completos para Core en cinco idiomas (#2062)

- Completados los catálogos `es_ES`, `en_US`, `fr_FR`, `de_DE` y `nl_NL` con 791 mensajes gettext traducibles y cabecera por idioma, sin claves vacías ni diferencias respecto al POT.
- 1.032 traducciones nuevas de las 344 cadenas pendientes en francés, alemán y neerlandés; 502 identidades de origen español añadidas al catálogo ES para formalizar la cobertura de textos fuente. Se conservan las traducciones previas, nombres técnicos y parámetros de formato.
- Regenerados los cinco archivos MO y ampliada la prueba automática de integridad y ejemplos reales de traducción.
- Se mantiene el atributo HTML `data-tc-search="local"` fuera de gettext, evitando cambios de comportamiento al seleccionar otro idioma.
- Versión y fecha actualizadas a 0.4.62 / 2026-11-08. Sin modificar datos, productos, posts, taxonomías, opciones, menús publicados ni otros plugins.
- La cobertura completa de POT/PO no acredita textos PHP/JS no extraídos ni la validación visual. Pendiente Plugin Check final, merge, despliegue homogéneo de Core en STAGING/Focazul y comprobación funcional por idioma. Producción excluida.

### Parte de 0.4.62 — Corregir internacionalización de atributo de navegación (#2062)

- Corregido `class-taxocommerce-core-hierarchy-classic.php`: el atributo técnico `data-tc-search="local"` se imprime como HTML fijo, no mediante gettext. Evita que una traducción pueda romper el buscador de categorías/elementos.
- Eliminada esta falsa cadena traducible del POT y los cinco PO, y recompilados cinco MO coherentes (792 entradas por idioma). Se conserva el HTML emitido y no se modifica el procesamiento de relaciones ni los datos.
- Revisar sintaxis PHP, Plugin Check, regresiones y funcionamiento real en STAGING/Focazul antes de fusionar o desplegar. Nunca PRODUCCIÓN.

### Parte de 0.4.62 — Cobertura de inventarios y administración Core (#2062)

- Traducidas otras 100 cadenas de Core para FR, DE y NL, y explicitadas las correspondientes versiones ES: 400 entradas gettext adicionales. Incluyen etiquetas de navegación, filtros, inventario de contenido, arquitectura SEO, estados de edición, diagnósticos, permisos, campos de producto, WooCommerce y opciones de sistema.
- Se conservan todas las traducciones EN existentes; los mensajes restantes seguirán comprobándose antes de publicar. Sin cambios en los datos, los menús reales o la lógica PHP/JS.
- Cinco catálogos GNU MO regenerados. Continuar con el resto de claves y ejecutar CI, Plugin Check y validación del idioma del administrador.

### Parte de 0.4.62 — Interfaz de extensiones Core multidioma (#2062)

- Añadidas 139 traducciones adicionales de 52 mensajes en cinco idiomas para el catálogo de extensiones, menús, descripciones, instalación ZIP, seguridad de carga, activación y desactivación, sin sobrescribir traducciones previas.
- Sin modificaciones de los servicios ni archivos PHP/JS del plugin. Sin cambios en contenidos editoriales, productos o configuración de sitios.
- Actualizados 5 PO y regenerados 5 MO (793 entradas cada uno). Pendiente continuar con cadenas restantes, pruebas Plugin Check e inspección real por idioma.

### Parte de 0.4.62 — Traducción de administración y menús Core (Issue #2062)

- Completadas 332 traducciones nuevas de 72 mensajes originales en ES/EN/FR/DE/NL, sin sobrescribir traducciones existentes. Incluye Anomalías, avisos de integridad, navegación de bloques, gestión de menús nativos, seguridad de borrado, atributos de WooCommerce y documentación de usuario y técnica.
- Catálogos `.po` y `.mo` sincronizados, 793 registros gettext por idioma, con `msgid` y marcadores de formato conservados.
- Pendiente ampliar el resto de mensajes, auditar PHP/JS sin gettext, validar carga según locale y ejecutar Plugin Check y pruebas en STAGING/Focazul. No desplegar a PRODUCCIÓN.

## [0.4.61] — 2026-11-07 — Menú padre TaxoCommerce Core estable (#2351)

- Se restablece el título del menú principal TaxoCommerce Core al final del registro de extensiones, sin alterar los submenús, rutas, iconos ni capacidades.
- Prueba de regresión que simula un rótulo erróneo «Marketing campaigns» y comprueba la corrección del padre.
- Esta versión participa en el despliegue reconciliado por SHA-256 a STAGING y Focazul; ninguna modificación en producción.

## [0.4.60] — 2026-11-07 — Catálogo completo de extensiones, incluidas las ausentes (#2340)

- Registrado Tasks en el catálogo nativo de Core: aparece aunque el propio plugin esté desactivado o no instalado, como sucede en Focazul.
- Conservadas las tarjetas de los 19 plugins vigentes y los estados reales de WordPress: activa, instalada/inactiva y no instalada. El antiguo Connections sigue integrado en Insights y no se duplica.
- El administrador puede gestionar instalación/activación mediante formularios existentes con permisos y nonce; no se instala ningún plugin automáticamente.
- Texto del catálogo incorporado a gettext POT/PO/MO en ES/EN/FR/DE/NL. Pruebas de ausencia, inactividad, activación, recuentos y duplicados.
- Sin cambios de bases de datos, del plugin Tasks ni de instalaciones de producción.

## [0.4.59] — 2026-11-07 — Cabecera con versión y fecha (#2336)

- Cabecera de administración con título, versión actual y fecha de esta versión, sin modificar datos ni funcionalidades.
- Fecha de publicación persistente, independiente del reloj o FTP.

## [Unreleased] — Traducción de menús y diagnósticos Core (#2062)

- Traducidas 131 cadenas de interfaz en cada catálogo EN/FR/DE/NL (524 entradas): navegación de cabeceras, atributos WooCommerce, editor y seguridad del gestor de menús, anomalías y relaciones SEO.
- Regenerados los catálogos MO en los cuatro idiomas, conservando 791 claves por locale y todos los identificadores de código. Continúan pendientes 125 cadenas en EN, 532 en FR y 533 en DE/NL; el catálogo ES aún contiene 687 `msgstr` vacíos, aunque varios textos fuente ya están en castellano.
- No se modifican las entradas, páginas, productos, datos de tiendas ni la lógica PHP. Pendiente la extracción/cotejo de nuevas cadenas PHP/JS, Plugin Check y validación visual por idioma en STAGING/Focazul.

## [Unreleased] — Reutilización segura de traducciones (#2307)

- Completadas únicamente entradas gettext PO vacías con traducciones idénticas y no ambiguas ya existentes en otros catálogos TaxoCommerce.
- Regenerados los MO de los idiomas modificados. Se conservan los textos, identificadores técnicos, funciones y datos anteriores sin cambios.
- Esta corrección es parcial: los textos restantes, las cadenas fuera de gettext y la validación visual por idioma siguen abiertos en #2062.

## [Unreleased] — Regional WordPress locale fallback (#2295)

- Respeta primero los paquetes oficiales de WordPress .mo y .l10n.php para el locale exacto.
- Si falta un catálogo local regional, usa el catálogo existente del mismo idioma (es_ES, en_US, fr_FR, de_DE o nl_NL); no cambia la configuración ni el idioma del usuario.
- Sin cambios en lógica, datos, menús o dependencias del plugin. Las traducciones de contenido incompletas siguen abiertas en #2062.

## Unreleased - Familia i18n #2062

- Dominio gettext, carga de catalogos locales, etiquetas de menús y textos HTML de salida estáticos; catálogos PO/MO sincronizados con el texto fuente.

# CHANGELOG — TaxoCommerce Core - Catalog & Content Management for WooCommerce

> Historial de las versiones del plugin **independiente**. Las funciones anteriores a su separación están documentadas en [`changelog.txt`](changelog.txt).
> Fuente inicial: entradas verificadas del `readme.txt` en GitHub. Solo se atribuye una fecha si la fuente la acredita.

## [Unreleased]

## [0.4.58] — 2026-11-03

- Botón de ejecución de Core uniformado a «Ejecutar autocheck», reutilizando la traducción ya compilada. Conserva las rutas, resultados, descarga JSON y pruebas del Autocheck anterior; issue #2150 / PR #2153.


- Plugin Check (#2149): corregido el indicador de Stable tag para Core 0.4.57; retirada la lectura de error_reporting() dentro del Autocheck manual (sin cambiar la configuración PHP); comprobaciones de nonce conservadas en acciones, consultas SQL y filtros de metadatos documentados y revisados. Verificación del paquete y regresiones previstas.

- Core 0.4.57 (#2147): eliminada del panel la tarjeta «TaxoCommerce SEO actual / No detectado» y el texto de convivencia con el monolito; se conservan versión, estado de WooCommerce y extensiones registradas, sin cambiar integraciones de migración.

- Categorías nativas (#2123): el editor de Core lee y guarda la descripción mediante la taxonomía de WooCommerce y el extracto en `wp_termmeta.seo_excerpt`, conservando textos existentes si el campo no se envía. Las categorías no consultan `seo_nodes`.
- Jerarquía (#2123): roles de páginas leídos desde `wp_postmeta._taxocommerce_core_seo_role` en el árbol, informes, selector y auditoría. Importación idempotente sin sobrescribir roles nativos desde los nodos históricos activos de `seo_nodes`; `seo_relations` mantiene los enlaces y el antiguo nodo se conserva como espejo para otras extensiones.
- Regresión sin WordPress para edición nativa de categorías y migración de roles, integrada en el workflow de validación de STAGING. No se despliega en producción.

- Corrección de navegación (#2109): se conservan en `$submenu` las rutas internas de administración de WordPress y se ocultan solo visualmente, evitando el error de acceso denegado en Contenidos, Jerarquía, Menús y Manual.
- AutoCheck manual (#2126): ejecución exclusivamente voluntaria desde el panel Core con rutas WordPress cargadas; nuevas comprobaciones de permisos/callbacks, integridad básica de archivos y versión, lectura MySQL, categorías y roles. Los avisos PHP producidos durante cada chequeo y las pruebas no ejecutadas aparecen en la interfaz y en JSON. Ninguna escritura de datos de tienda ni automatización añadida.

- Navegación lateral (#2109): WordPress muestra solo Core y los plugins de TaxoCommerce instalados; Contenidos, Jerarquía SEO, Gestor de menús y Manual siguen accesibles desde Core y mediante sus URLs directas, sin aparecer como entradas del desplegable de WordPress. Sin cambios en funciones, permisos ni datos.

- Despliegue compartido (#2088): STAGING y Focazul utilizan la misma cuenta FTPS con raíz `public_html` y subdirectorios de WordPress diferentes (`staging.distribuidordeherramientas.es/` y `asociacionfocazul/`). Se mantienen las transferencias incrementales, la verificación de integridad y las restricciones de plugins por destino. Sin cambios en la funcionalidad de Core.

- Infraestructura de actualizaciones (#2086): STAGING y Focazul reciben únicamente los archivos de plugin modificados desde el último release satisfactorio. Solo se actualizan plugins ya instalados; Focazul únicamente Core. Sin FTP cuando no hay cambios y sin copiar plugins ajenos. Verificación SHA-256 por fichero y fallo conjunto de Core si uno de sus dos destinos falla. Sin cambios de lógica funcional en Core.

- Despliegue (#2084): Core se transfiere a STAGING y Asociación Focazul en trabajos independientes; el resultado conjunto exige éxito en ambos sitios. Fallos de System Check u otros plugins no bloquean Core en Focazul. Sin modificar el código funcional del plugin.

- Anomalías (#2071): filtrado adicional de relaciones SEO para no activar el informe estructural por relaciones ajenas a clústeres, hubs y landings; regresión en tienda sin jerarquía. Despliegue del módulo Core a STAGING y Focazul mediante el flujo de módulos modificados.

## [0.4.54] - 2026-11-03

- Plugin Check (#2121): se corrigen los dos errores de navegación de bloques (eliminación de `suppress_filters=true` redundante y escape de profundidad en HTML) y se sanea el POST de confirmación/bloques de objetos.
- Se justifican específicamente los parámetros GET de solo lectura de Anomalías y el filtro `meta_key` del selector administrativo de países, sin cambiar datos, consultas ni validación de permisos.
- Igualdad del plugin genérico para STAGING y Focazul; quedan pendientes de comprobación externa la ejecución de Plugin Check y la prueba visual de la navegación real. No se despliega automáticamente a PRO.

## [0.4.53]

- Autocheck (#2113): el botón «Descargar resultados (JSON)» permite obtener el **último informe real del usuario** sin volver a ejecutar el chequeo. El archivo incluye sitio e idioma WordPress, versión de Core con la que se ejecutó, fecha, duración, resultados y mensajes detallados de todas las pruebas (pass/fail/skip). Los casos omitidos cuentan como incompletos, nunca como UAT superadas.
- Descarga autenticada desde admin-post.php, con permiso y nonce independientes, informe por usuario, caducidad y formato JSON versionado. Nombre de archivo con dominio de instalación, sin escritura en páginas, categorías ni menús. Tests offline de permisos, validez del informe, coincidencia con pantalla y JSON parseable, y ejecución en CI.
- No se confunde la exportación con ejecución de pruebas funcionales E2E de escritura: se requiere instrumentar por separado la automatización de clics y de persistencia de cambios en STAGING y Focazul. Mismo plugin en ambas instalaciones.

## [0.4.51]

- Gestor de menús (#2081, #2069): se unifica la **presentación** del editor de menús clásicos y de navegaciones de bloques de WordPress. Ambos usan la misma pantalla y CSS: cargar/crear, nombre, controles de publicación arriba y abajo, panel izquierdo de páginas/entradas/enlaces/categorías y panel derecho con estructura desplegable y jerarquía visible. La interfaz se adapta al idioma de WordPress y al tipo real de menú, no a clientes concretos.
- En temas de bloques, el panel izquierdo añade ahora páginas, entradas y categorías mediante bloques nativos core/navigation-link con IDs y URLs de esta instalación. El editor guarda el nombre y los textos/URLs, permite eliminar enlaces de hoja (incluidos los anidados) sin borrar submenús completos ni bloques desconocidos; los menús de bloques no referenciados en cabeceras pueden eliminarse tras confirmación expresa; comprueba permisos, nonces, hash de edición concurrente, tipos y límites, y conserva la activación explícita en cabeceras. Los menús clásicos y las propuestas SEO conservan sus rutas de datos y funciones existentes.
- Tests específicos nuevos de interfaz para tema de bloques con las clases reales del plugin; pruebas de objetos, enlaces anidados y borrado seguro. Mantener #2081 abierto para funciones estructurales avanzadas de navegación por bloques y validación visual autenticada en STAGING y Focazul.
- Core se distribuye desde una fuente GitHub idéntica a ambos entornos de prueba mediante el workflow dual con verificación de checksums; PRODUCCIÓN no se modifica.

## [0.4.50]

- Gestor de menús portable (#2069): la selección automática de un menú clásico utiliza únicamente las ubicaciones declaradas por el tema activo y los menús realmente existentes. Se elimina la lista de identificadores de ubicación supuestos (`primary`, `menu-1`, `header`). Cuando hay varios menús publicados distintos, el editor exige seleccionar uno; no edita el primero arbitrariamente. La navegación de bloques se mantiene independiente y sin cambios. Pruebas para temas con ubicaciones arbitrarias, menús ambiguos y selección explícita.
- Igualdad de instalaciones (#2065): después de cada despliegue de Core, se comparan todos los archivos distribuibles versionados del mismo commit con los dos WordPress de prueba. Solo se transfieren los que no coincidan, atómicamente y con lectura SHA-256 remota. No se editan sitios por separado ni se copian datos; Focazul no recibe otros plugins y PRODUCCIÓN no se modifica.

## [0.4.49]

- Traducciones (#2094): recompilados desde los ficheros PO originales los cinco catálogos binarios MO (es_ES, en_US, fr_FR, de_DE, nl_NL), que tenían índices de tablas incompatibles con el lector gettext de WordPress y provocaban avisos repetitivos de `unpack()` y `Undefined array key`.
- Se verifican de forma estructural los 100 catálogos binarios de la familia antes de transferir Core a STAGING y Focazul. No se cambian textos traducidos ni el código del núcleo de WordPress.

## [0.4.48]

- Países (#2092): se utiliza la misma lista dinámica de países de páginas en menús y formularios de Core; Global es el país vacío por defecto, sin asignación a ES y sin países ficticios. La edición de páginas admite códigos de dos letras sin catálogo predefinido; las categorías pueden volver a Global eliminando su metadato en lugar de generar un error.
- Los filtros de asignación de páginas en Jerarquía SEO respetan Global como metadato ausente; la vista de jerarquía no asume España. Sin borrar ni renombrar menús, contenidos o relaciones existentes.

## [0.4.47]

- Menús (#2090): solo se ofrecen países con páginas asignadas explícitamente en `_seo_pais_origen`; Global (`''`) es la opción sin país, nunca una sustitución de ES. Se respetan menús creados anteriormente y los menús nativos de WordPress. Los árboles de jerarquía de menús se aíslan por asignaciones explícitas de páginas y categorías; se descartan enlaces entre países distintos.
- Edición de páginas: Global es la selección inicial si no hay país almacenado y permite desasignarlo. Los códigos de país importados de dos letras se preservan aunque no pertenezcan al antiguo conjunto de cinco mercados. Catálogos PO/MO/POT actualizados y pruebas de regresión independientes.

## [0.4.46]

- Anomalías (#2071): se muestran por defecto solo incidencias reales de jerarquías configuradas. El botón «Mostrar todas las comprobaciones» permite consultar resultados correctos. FAQs/Vocabulary opcionales sin configurar no se presentan como errores; las relaciones históricas no activan una jerarquía vacía.
- Pruebas de regresión para el filtro, jerarquías no configuradas y relaciones heredadas; se conserva el despliegue dual independiente del PR #2085.

## [0.4.45]

- Gestor de menús: búsqueda de navegaciones de bloques en todas las plantillas y cabeceras del tema, no solo en front-page/home/index. Las navegaciones existentes de WordPress sin referencia de cabecera verificable también se muestran como opciones, sin declararlas activas ni seleccionarlas automáticamente.
- Gestor unificado sin pestaña independiente de menús clásicos. En temas de bloques se carga el listado de navegaciones nativas (`wp_navigation`), se pueden crear nuevas, editar enlaces y añadir enlaces de primer nivel. Los menús clásicos de temas clásicos mantienen sus herramientas anteriores.
- Activación de una navegación de bloques en cabeceras del tema (ficheros o plantillas personalizadas) cuando hay una sola navegación identificable; se guarda la personalización mediante `wp_template_part` de WordPress sin modificar ficheros físicos. No se tocan cabeceras ambiguas.
- La opción de generar/sincronizar desde jerarquía SEO solo aparece con un árbol real; la ejecución rechaza una jerarquía vacía antes de borrar elementos generados. Issue #2069.
- Acceso directo al Editor del sitio para la navegación seleccionada y aviso desde la pestaña SEO en temas de bloques, evitando confundir el menú clásico cargado con el menú publicado en la cabecera.
- La selección es solo lectura; el guardado mantiene verificaciones de nonce, permisos y contenido concurrente. No se modifica automáticamente el menú publicado. Issue #2069.
- Anomalías (#2071): un sitio sin jerarquía SEO ni relaciones configuradas muestra un estado neutro en lugar de una auditoría vacía. Los informes de Vocabulary y FAQ no anuncian errores por ausencia de tablas de módulos opcionales. La categoría predeterminada de WooCommerce se excluye de las alertas de falta de asignación y de categorías vacías. Se conservan las comprobaciones cuando existen definiciones y datos.

## [0.4.44]

- Gestor Menús WordPress: edición explícita de las entradas `wp_navigation` referenciadas desde plantillas de cabecera de temas de bloques. Guarda los atributos de los enlaces sin alterar bloques no compatibles, comprueba nonce/permisos y rechaza cambios concurrentes. Los menús clásicos siguen siendo independientes; se evita afirmar que su guardado actualiza la cabecera. Menús embebidos y situaciones ambiguas se derivan al Editor del sitio. Issue #2060.

- Gestor de menús WordPress: aviso explícito en temas de bloques cuando el menú clásico editado no es necesariamente el que se muestra en la cabecera, con acceso al Editor del sitio. En temas clásicos informa de las ubicaciones asignadas al menú seleccionado. Sin modificación automática de menús o navegación publicada. Issue #2058.

## [0.4.43]

- Botones visibles «Guardar menú» (superior/inferior) en la pestaña Menús WordPress: guardan de forma explícita nombre, títulos, URLs, orden y jerarquía; aviso de cambios pendientes y mensaje de resultado.
- Nuevo «Eliminar menú» para un menú nativo seleccionado, con casilla y diálogo de confirmación. Protege todos los menús SEO por país y menús con elementos automáticos; informa si está asignado al tema. La eliminación usa la API nativa WordPress.
- Validación previa de todos los elementos, pertenencia y ausencia de ciclos, nonces y capacidades, sin guardar al abrir un menú. Se conserva el comportamiento anterior de «Menús SEO / WooCommerce», y los botones de añadir/quitar elementos siguen guardando inmediatamente.
- Pruebas de regresión, manuales actualizados, Issue #2056. No se toca el monolito ni se publica en producción.


## [0.4.42]

- Nueva pestaña Menús WordPress para editar menús clásicos con entradas, páginas, categorías y enlaces, sin WooCommerce ni restricciones de país.
- Separada de Menús SEO / WooCommerce. Cargar y seleccionar no modifica menús, ubicación del tema ni datos; las escrituras usan nonce y capacidades y requieren menú explícito.
- Conservadas la jerarquía SEO, los menús por país y las API existentes. Manual de usuario/técnico y pruebas sin WooCommerce. Issue #2051; sin publicar en producción.


## [0.4.41]

- Añadido selector de menús nativos de WordPress con indicación de ubicaciones del tema: permite **cargar y editar el menú actual**, sin sustituir el menú generado para cada país ni alterar el front-end al cargarlo.
- Permite crear un menú WordPress nuevo y editar sus elementos manuales desde Core: título, URL de enlaces personalizados, estructura padre/hijo y posición. Se mantienen protegidos los elementos generados por la jerarquía SEO.
- Las acciones de escritura usan permisos y nonce, y rechazan elementos externos al menú, padres de otro menú, bucles de jerarquía e identificadores de menú inválidos.
- Se preservan las funciones existentes de sincronización SEO por país, enlace al editor nativo, activación del menú en una ubicación y restauración de ubicaciones; sin alterar el monolítico ni producción.
- Añadidas pruebas aisladas del comportamiento de carga, edición, creación e independencia de menús. Issue #2047.


- Eliminados los dos scripts de pruebas no ejecutados por CI (`anomalies-smoke.php` y `family-menu-smoke.php`); las otras 11 pruebas permanecen fuera del ZIP y se ejecutan con los workflows. Las comprobaciones automáticas y los archivos ocultos quedan excluidos del paquete instalable (PR #1935).

- Limpieza controlada de STAGING (issue #1937): eliminar exclusivamente restos de pruebas de desarrollo bajo `tests/` y el antiguo `.ftp-deploy-sync-state.json` del plugin instalado. Inventario, copia de seguridad SHA-256 y verificación remota obligatorios. Se conservan las clases operativas de `includes/`, los datos y las pruebas del repositorio; sin intervención en PRODUCCIÓN.

- Auditoría de calidad unificada de los 20 módulos independientes: Plugin Check automático tras cada integración en STAGING, informes por módulo y resumen consolidado con errores, advertencias y archivos de diagnóstico; el monolítico permanece excluido (issue #1921).
- El comprobador de CI analiza paquetes distribuibles y no sustituye las comprobaciones de la instalación WordPress en PRO; ningún despliegue a PRO se ejecuta desde este control.
- Endurecido el despliegue de la familia modular: las pruebas de desarrollo y el estado FTP se mantienen fuera de las instalaciones WordPress.
- Preparada una limpieza FTPS excepcional, con inventario, copia de seguridad y verificación de hashes, limitada a `taxocommerce-*/tests/` y al archivo oculto de sincronización. El monolítico `seo-taxonomy` queda expresamente excluido.
- Seguimiento de la intervención y sus comprobaciones en el issue #1918. No se modifica el código operativo ni se incrementa la versión de Core por un ajuste de infraestructura.

## [0.4.40]

- Internacionalización nativa del administrador: menús, pestañas, botones, descripciones de módulos, editor, parte de la jerarquía y mensajes. Se utiliza el locale de WordPress y el dominio `taxocommerce-core`; no hay selector propio.
- Catálogo POT y ficheros PO/MO de `es_ES`, `en_US`, `fr_FR`, `de_DE` y `nl_NL`. Español priorizado para STAGING; traducciones de textos largos a otros idiomas todavía parciales (issue #2025).
- Conservados identificadores, contratos técnicos, relaciones SEO, contenidos, acciones, y estructura de datos. No afecta al monolítico ni a los plugins independientes de la familia.
- Añadida validación automática de carga de traducciones compiladas antes de desplegar.

## [0.4.39]

- Package-only Plugin Check remediation: distribute without development tests or hidden FTP state files.
- Retain full regression tests in CI and keep Core menus, international scopes, SEO architecture and data unchanged.



## [0.4.38]

* Align the Logistics extension catalog link and family submenu label with the standalone module's unique admin page.
* Preserve the legacy TaxoCommerce SEO order view and all shipping settings without moving data.

## [0.4.37]

* Move Core Auto Test into the Core Overview / User Guide / Auto Test internal tab bar rather than a WordPress sidebar submenu.
* Retain the 17 read-only checks, user transient, nonces, dashboard navigation and old URL compatibility via a safe redirect.
* No new tables or modifications to tests, catalog, SEO hierarchy, menus or extension management.

## [0.4.36]

* Add Technical Manual alongside User Manual in the existing User Guide screen.
* Describe Core architecture, hooks, data, countries, permissions, caching, deployment and diagnostics using semantic headings and responsive tables.
* Preserve the original user manual and all operational Core screens.

## [0.4.35]

* Add a User Guide tab with semantic H1/H2/H3 headings, table of contents, direct links and responsive presentation.
* Document Content, SEO Hierarchy, country menus, Auto Test and optional extensions without changing existing operations.

## [0.4.34]

* Add a native Auto Test screen and dashboard shortcut for administrator-triggered, read-only functional checks.
* Report individual pass/fail/skipped results with error details, timings and version, isolating failures between checks.
* Cover runtime, WP/WooCommerce content reads, country metadata, SEO roles and relation samples, country menus, modules, vocabulary and architecture summary. Never create, delete or sync real content during tests.
* Keep the last test result in a user-specific transient for one day; writing UAT remains explicitly outside the production-safe suite.

## [0.4.33]

* Ensure each country creates, saves and edits a dedicated native WordPress menu with a distinct country-specific name.
* Prevent stale country options from reusing and overwriting another market's menu ID; restore the correct market association by native menu name.
* Rename legacy Spanish TaxoCommerce menu in place when synchronised, preserving its menu ID and content.
* Show the selected native menu name in the editor and add regression coverage for cross-country menu ID collisions.

## [0.4.32]

* Fix existing SEO hierarchy tables that missed the country_code migration: both seo_nodes and seo_relations are checked, without deleting or replacing existing records.
* Persist page country_code when saving or reusing a role node; synchronize active role country when page country metadata changes.
* Keep legacy relations and Spanish hierarchy intact. Relation country is also independently checked when both SEO tables already exist.
* Add isolated QA for the pre-existing-table migration and country-aware node saves.

## [0.4.31]

* Adds a market-country selector to Core content editors for posts, pages, products and WooCommerce categories, preserving existing country metadata.
* Adds optional country_code to seo_relations and country-filtered hierarchy editing without overwriting legacy links.
* Provides independent managed menus per country and a front-end selector for TaxoCommerce-managed theme menu locations.
* The original Spanish menu and relationships remain valid; international categories are never blanket-labelled Spanish.

## [0.4.30]

* Move Extensions from the Core submenu into responsive cards at the bottom of the Core home screen.
* Show live installed/active/ZIP states, version, descriptions and guarded management actions per extension.
* Remove redundant migration descriptions while retaining old bookmarks via a redirect.

## [0.4.29]

* Added Core > Content > Inventory summary with read-only totals, 15-minute cache and an administrator-initiated protected refresh.
* Counted canonical WordPress/WooCommerce records without invoking Auditor scans.
* Refresh WordPress.org readiness against the current modular Core implementation.
* Preserve native content, hierarchy, menu and extension-management contracts while validating the standalone package.

## [0.4.28]

* Consolidated external connections under TaxoCommerce Insights.
* Retained independent extension discovery and administrator-initiated installation.

## [0.4.27]

* Clarified Supplier and Logistics responsibilities.

## [0.4.26]

* Registered Suppliers in the extension catalogue.

## [0.4.24]

* Registered Logistics and Billing modules.

## [0.4.12]

* Added FAQ content screens and removed an unused technical log page.

## [0.4.10]

* Added administrator-controlled process management.

## [0.4.9]

* Added editing guidance for all four content types.

## [0.4.8]

* Consolidated extension management into one screen.

## [0.4.0]

* Added native WordPress menu management for the SEO hierarchy.

## [0.3.0]

* Introduced the native cluster and hub hierarchy.

## Referencias

- [Origen monolítico y transición](changelog.txt)
- [Índice de evidencias de los 20 plugins](../docs/PLUGIN-HISTORY-SOURCES.md)

## Mantenimiento

Este es el historial maestro que se actualiza al introducir novedades. Antes de publicar cada versión en WordPress.org, su resumen debe trasladarse al apartado `== Changelog ==` de `readme.txt`; WordPress.org no presenta este Markdown automáticamente.
