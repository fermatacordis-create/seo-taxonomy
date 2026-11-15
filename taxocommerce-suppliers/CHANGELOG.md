## [0.2.27] — 2026-11-13 — Importación/exportación masiva de fichas de proveedores

- CSV de la pestaña Gestión de proveedores con los 18 campos canónicos, incluyendo observaciones privadas; exporta también los proveedores sin ficha para facilitar su cumplimentación masiva.
- Importación por bloques de 25/50/100/200/350 fichas con límite temporal de 7 s por petición, progreso, cancelación, errores y rechazo de nombres ausentes en inventario. Máximo 50 MiB o límite inferior del alojamiento.
- Los valores vacíos sustituyen los anteriores, por lo que se recomienda exportar una copia antes de importar. No se crean proveedores ajenos al inventario ni se modifican productos, costes, existencias o pedidos.
- CSV UTF-8 con BOM para Excel; los textos exportados se protegen frente a fórmulas de hoja de cálculo. Compatible con los idiomas ES/EN/FR/DE/NL.

## [0.2.26] — 2026-11-13 — Gestión dinámica de proveedores

- Nueva pestaña de gestión con listado y búsqueda de proveedores derivados de `seo_proveedores_productos`, actualizado automáticamente tras importar nuevos CSV.
- Fichas de empresa, identificación fiscal, teléfono, correo, web, comercial, dirección, plazo de entrega y observaciones internas libres; altas, edición y eliminación de la ficha con permisos y nonce.
- Tabla independiente `seo_proveedores_fichas` (migración automática). La ficha no altera el nombre del proveedor, productos, inventario, precios ni pedidos. Las bajas no afectan a las fuentes del catálogo.
- Actualiza documentación, pruebas de regresión y catálogos locales ES/EN/FR/DE/NL.

## [0.2.25] — 2026-11-13 — Code Check equiparado a Core 0.4.82 (#2626)

- Code Check equiparado a Core 0.4.82: reglas autónomas PHP/WordPress/seguridad, clasificación por gravedad, detalles de archivos y recomendaciones, resumen y JSON; sin modificar el Autotest funcional. Issue #2626.
- Continúa siendo una revisión estática independiente del Plugin Check oficial, con ejecución por bloques y control de CPU.

## [0.2.24] — 2026-11-13 — Enlace a tienda del proveedor desde Inventario (#2610)

- La tabla Inventario muestra una columna «Supplier product» con acceso directo a la ficha del proveedor, sin que exista pedido WooCommerce.
- Emplea la URL de origen y recurre a la canónica cuando falta o no es válida, como en Compras. Abre el enlace externo en otra pestaña de forma segura; si no hay URL válida, muestra un guion.
- Reutiliza las cadenas ya traducidas en ES/EN/FR/DE/NL, sin nuevas dependencias ni cambios de base de datos, precios, productos, enlaces de pedidos o importaciones.

## [0.2.23] — 2026-11-12 — Inventario global e importación/exportación CSV de precios históricos (#2600)

- Nueva pestaña «Historial de precios»: vista global paginada de movimientos de catálogo, proveedor, SKU, precio anterior, precio actual y variación, con filtros por proveedor, búsqueda y fechas.
- Exportación CSV de todos los registros filtrados por lotes AJAX, sin limitarla a los 50 registros visibles ni depender de cron.
- Importación CSV en lotes con cinco velocidades; verifica la identidad del catálogo, omite filas sin coincidencia, rechaza fechas/precios inválidos y detecta duplicados. Nunca cambia los precios actuales ni los márgenes.
- Añade validaciones de permisos y nonce, archivo temporal fuera del directorio público y límites de tamaño. Mantiene compatibilidad con los cinco idiomas ES/EN/FR/DE/NL mediante POT/PO/MO sincronizados.
- No altera el esquema histórico ni las funcionalidades existentes de Suppliers.

## [0.2.22] — 2026-11-12 — Catálogos i18n sincronizados en cinco idiomas (#2062)

- Completa la traducción de las 335 entradas neerlandesas pendientes: recupera 179 claves que faltaban y traduce 156 mensajes que estaban vacíos. Se conservan todos los identificadores técnicos, tokens de formato y etiquetas HTML.
- Añade 29 cadenas gettext de la interfaz que existían en PHP pero no figuraban en la plantilla POT: historial de precios, controles del autochequeo de código, fechas, origen, encabezado y enlace oficial. Traduce esas 29 cadenas en ES/EN/FR/DE/NL (145 entradas).
- Los cinco PO y el POT coinciden en 560 claves; los cinco PO quedan sin msgstr vacíos. Se recompilan los cinco MO conservando la cabecera gettext.
- No se modifica la lógica de catálogo, precios, compras, historial, WooCommerce, datos ni APIs. Se preserva la versión funcional 0.2.21 anterior.
- Persisten las tareas generales del Issue #2062: revisar literales PHP/JS fuera de gettext y validar los cinco idiomas en WordPress real en STAGING/Focazul antes de dar por finalizada la internacionalización completa.

## [0.2.21] — 2026-11-12 — Historial retrospectivo ordenado por fecha (#2596)

- Consultas de precio vigente y vista histórica ordenadas por `fecha DESC, id DESC`, de modo que una importación retrospectiva no reemplace el último estado comercial al comparar.
- Sin cambios en tarifas, margen, productos ni datos existentes.

## [0.2.20] — 2026-11-12 — Historial de precios vinculado al catálogo (#2589)

- Añade `seo_proveedores_productos_historico` con migración automática, fecha, costes del proveedor, PVP normal, precio de oferta y precio efectivo.
- Registra cambios de CSV, ediciones manuales y guardados de WooCommerce; conserva la primera captura como referencia de inicio sin inventar fechas anteriores.
- Incluye consulta de solo lectura «Price history» por producto en Inventario y chequeo de tabla en Autotest.
- No altera el cálculo existente de márgenes ni aplica descuentos.

## [0.2.19] — 2026-11-10 — Cabecera en tres zonas sin contenido duplicado (#2521)

- Izquierda: nombre, descripción local del plugin y un único enlace estático `Plugin URI` bajo el texto.
- Centro: desplegable más ancho con el historial local `CHANGELOG.md`, cerrado inicialmente.
- Derecha: versión y fecha reales del propio plugin.
- Se retira la segunda caja «Información del plugin», sin cambios en servicios, menús ni consultas externas.

## [0.2.18] — 2026-11-10 — Descripción visible desde el plugin (#2494)

- La descripción inmediatamente debajo del título administrativo procede de `Description:` del archivo principal, sin textos duplicados ni consultas externas.
- Se mantienen el enlace estático `Plugin URI`, el historial local y las funcionalidades anteriores.

## [0.2.17] — 2026-11-10 — Ficha local en el encabezado (#2494)

- Elimina consultas REST/caché del extracto y usa `Description` y `Plugin URI` locales.
- Conserva el enlace oficial y el historial, sin alterar servicios existentes.

## [0.2.16] - 2026-11-10 - Resiliencia del extracto oficial (#2489)

- Consulta exclusivamente la API REST pública de la página indicada en Plugin URI (autor del plugin), nunca páginas de la tienda del cliente.
- Conserva el último extracto oficial comprobado; los fallos de red no borran el texto. Caché de errores reducida a 5 minutos y versión nueva de clave para ignorar fallos antiguos cacheados.
- Si nunca se ha obtenido un extracto, muestra provisionalmente la Description incluida por el autor en el plugin; permite abrir la ficha oficial. No cambia operaciones comerciales ni requiere Core.
- Pruebas para recuperación tras fallos HTTP, JSON inválido y caché negativa; compatibles con la familia completa.

## [0.2.15] — 2026-11-09 — Ficha oficial del desarrollador y cabecera centrada en tres zonas (#2476)

- La cabecera distribuye identificación a la izquierda, dos desplegables alineados y centrados (extracto oficial a la izquierda e historial local a la derecha), y versión y fecha a la derecha; distribución adaptable a móvil.
- El enlace oficial se obtiene exclusivamente de Plugin URI, definido por el autor del plugin. Eliminados el formulario de URL y su acción de guardado; los ajustes antiguos de la tienda no pueden sobrescribirlo.
- El historial se lee del CHANGELOG.md incluido, plegado de inicio. Sin dependencias obligatorias entre plugins ni cambios en productos, pedidos, datos o funcionalidades existentes.

## [0.2.14] — 2026-11-08 — Información dinámica desde ficha oficial (#2443)

- El desplegable izquierdo consulta el excerpt publicado en la página Plugin URI, con caché y configuración independiente.
- Formulario sanitizado; soporte para desactivación y funcionamiento sin servicios externos. Historial local sin cambios.

## [0.2.13] — 2026-11-08 — Extracto público remoto opcional (#2443)

- Consulta opcional por HTTPS del excerpt REST de una ficha oficial configurada; muestra el enlace a esa página en un panel izquierdo plegado.
- Mantiene el historial propio en el panel derecho. Caché, permisos y escape de salida; sin servicios obligatorios ni cambios en datos.

## [0.2.12] - 2026-11-08 - Autotest funcional y de codigo (#2401)

- Conserva autotest funcional y JSON; nuevo Code Check independiente del propio plugin: PHP, WordPress, seguridad. Cinco velocidades, lotes y JSON separado. Sin PCP ni Core obligatorio.

## [0.2.11] — 2026-11-08 — Historial plegable en cabecera (#2382)

- Desplegable cerrado por defecto inmediatamente debajo del título, con el historial propio de `CHANGELOG.md`; sin nueva pestaña ni dependencia de otros plugins.
- Textos del título preparados para ES/EN/FR/DE/NL, salida escapada, desplazamiento vertical y mantenimiento de menús y funciones existentes.
- El paquete instalable y la subida incremental incluyen únicamente el historial raíz necesario. Sin cambios en base de datos ni servicios.

## [0.2.10] — 2026-11-07 — Cabecera administrativa de versión y fecha (#2336)

- Muestra título, versión activa y fecha de publicación en el administrador mediante Release Date.
- La fecha no depende de la hora de consulta ni del momento de copia por FTP. Sin cambios en servicios, datos, menús ni permisos.

## [Unreleased] — Ampliación del catálogo neerlandés (#2328)

- Añadidas 92 cadenas gettext ausentes a `nl_NL` para etiquetas administrativas y documentación del plugin; regenerado el MO neerlandés.
- La cobertura de claves continúa incompleta: quedan 179 entradas presentes en `es_ES` que faltan en `nl_NL`; no se afirma traducción íntegra ni validación visual.
- Sin modificar PHP funcional, datos de proveedores ni opciones de instalación.

## [Unreleased] — Revisión i18n (seguimiento #2062)

- Completada una entrada gettext previamente vacía en NL; conserva el mismo identificador fuente y no modifica la lógica de la tienda.
- Pendiente recompilar catálogos MO y validar cobertura frente al POT, en especial discrepancias entre idiomas; no supone traducción integral del plugin.

## [Unreleased] — Reutilización segura de traducciones (#2307)

- Completadas únicamente entradas gettext PO vacías con traducciones idénticas y no ambiguas ya existentes en otros catálogos TaxoCommerce.
- Regenerados los MO de los idiomas modificados. Se conservan los textos, identificadores técnicos, funciones y datos anteriores sin cambios.
- Esta corrección es parcial: los textos restantes, las cadenas fuera de gettext y la validación visual por idioma siguen abiertos en #2062.

## [Unreleased] — Regional WordPress locale fallback (#2295)

- Respeta primero los paquetes oficiales de WordPress .mo y .l10n.php para el locale exacto.
- Si falta un catálogo local regional, usa el catálogo existente del mismo idioma (es_ES, en_US, fr_FR, de_DE o nl_NL); no cambia la configuración ni el idioma del usuario.
- Sin cambios en lógica, datos, menús o dependencias del plugin. Las traducciones de contenido incompletas siguen abiertas en #2062.

### Plugin Check 2.1.0: corrección de i18n (Issues #2219 y #2223)

- Corregidos los cuatro errores de comentarios para traductores en los textos con marcadores de formato.
- Eliminadas de gettext las 24 etiquetas formadas exclusivamente por identificadores técnicos con `<code>`, sin cambiar su presentación ni traducciones del contenido funcional.
- Internacionalizados los resultados del Autocheck; se preserva una sola cabecera de resumen y los contratos de JSON, importación y plantillas.
- Añadida regresión para impedir que se vuelvan a traducir identificadores de código sin texto.

## [0.2.9] — 2026-11-05 — Internacionalización completa ES/EN/FR/DE (#2219)

- Manual de usuario y técnico traducido en los cuatro idiomas: 49 párrafos, 121 celdas, 19 cabeceras, 5 elementos de lista y 19 enlaces de navegación.
- Textos de inventario, plantillas, imágenes, compras y Autocheck pasados a gettext; PO/POT/MO completos con pruebas de regresión.
- Conserva el aislamiento de plantillas y los formatos de importación genéricos incorporados en 0.2.8 (#2218). No se restauran plantillas de proveedor precargadas.
- No altera consultas SQL, permisos, nonces, persistencia, aceptación o logística. Neerlandés sigue como idioma adicional sin cobertura completa garantizada.

## [0.2.8] — 2026-11-05 — Aislamiento de plantillas por instalación (Issue #2218)

- Eliminados los perfiles comerciales VEVOR, SATKIT y TodoTaladros que venían incorporados en PHP y aparecían automáticamente en todas las tiendas.
- «Plantillas CSV» muestra exclusivamente las asignaciones personalizadas guardadas en la opción local de cada WordPress; una instalación nueva muestra una lista vacía y permite crear, importar y exportar plantillas propias.
- Los formatos genéricos CSV canónico y actualización de precios siguen disponibles en el importador, sin contarse como plantillas de proveedor.
- Se mantiene la compatibilidad con el importador regional existente mediante selección explícita del proveedor y comprobación de cabeceras, sin precargar una plantilla comercial.
- Ajustes de Autocheck y pruebas de regresión para aislamiento entre tiendas, importación y protección de plantillas personalizadas.

## [0.2.7] — 2026-11-05 — Internacionalización (#2062)

- Se reutiliza el mecanismo gettext nativo de TaxoCommerce Core, sin introducir un cargador alternativo.
- Se preparan para gettext las pestañas y textos de administración, etiquetas de campos CSV, mensajes de error y resultados, compras, Autocheck y el manual.
- Actualización de catálogos por idioma; preservadas las claves, formularios, nonces, estados de proveedor y contratos WooCommerce.

## Unreleased - Familia i18n #2062

## [0.2.6] — 2026-11-05

- Issue #2198: formulario plegado en CSV Templates para crear plantillas reutilizables desde JSON o CSV/TXT subidos o texto pegado.
- Validación de correspondencias canónicas, campos obligatorios, límite de tamaño, nonce, permisos y protección frente a sobrescrituras accidentales. Exportación JSON de las plantillas personalizadas.
- Reutilización del almacenamiento actual de WordPress sin ejecutar código, importar productos ni modificar las plantillas integradas. Pruebas de regresión offline.



## [0.2.5] — 2026-11-03

- Pestaña homogénea «Autocheck» y descarga JSON del último informe, por usuario y sitio, con control de permisos y nonce.
- Issue #2150, PR #2151; rutas existentes conservadas y sin pruebas de escritura.

- Dominio gettext, carga de catalogos locales, etiquetas de menús y textos HTML de salida estáticos; catálogos PO/MO sincronizados con el texto fuente.

# CHANGELOG — TaxoCommerce Suppliers

## [0.2.4] — 2026-11-03

- Cabecera visual común basada en Core: nombre, icono, descripción, versión y diseño adaptable.
- Lógica funcional, pestañas y permisos intactos. Plugin autónomo sin dependencia de Core. Issue #2137.

> Historial maestro de versiones del plugin independiente. El origen monolítico se documenta en [`changelog.txt`](changelog.txt).

## [Unreleased]

## [0.2.3]

- Comprobación de nonce dentro de cada acción POST de importación CSV, plantillas, precios, imágenes y decisiones sobre proveedores, manteniendo permisos y contratos de formulario.
- Saneamiento previo de importes y de la asignación de columnas CSV sin cambiar la semántica del valor «-1»; saneamiento de URLs de imágenes externas.
- Preparado el identificador de la tabla de imágenes de proveedor mediante `%i`; consultas SQL directas del inventario propio justificadas solamente en las instrucciones afectadas y sin introducir cachés obsoletas.
- Mantenidos los nombres públicos de clases `TCSUP_*` por compatibilidad, con justificación local del prefijo, y las búsquedas WooCommerce limitadas por metadatos.
- Plugin Check y pruebas PHP/ZIP estrictas requeridas antes de fusionar. Sin cambios en monolítico ni publicación en producción.


## [0.2.2]

- Reestructurado y ampliado el Manual integrado: dos guías completas y diferenciadas, usuario y desarrolladores, con índice, H1/H2/H3, tablas, funcionamiento y riesgos reales.
- Documentados flujos de CSV, decisiones, creación de borradores WooCommerce, precios, imágenes, compras, arquitectura, claves, hooks, permisos, pruebas y despliegue.
- Ampliadas las regresiones smoke y los estilos adaptables del manual; sin cambios de lógica de negocio ni del monolítico.


- Documentación integrada de usuario y técnica en la pestaña Manual del módulo independiente.
- Autotest manual de solo lectura para componentes, hooks, integración WooCommerce, plantillas, ajustes y esquema, con permisos y nonce.
- Conservadas las seis pestañas anteriores y añadidas Manual y Autotest; ampliada la verificación smoke.

## [0.2.1]

- Incorporadas las pestañas Manual y Autotest dentro de TaxoCommerce Suppliers.


## [0.2.0]

* Move WooCommerce sale-to-supplier purchasing from Logistics into Suppliers.
* Add Purchasing dashboard with supplier source, cost, purchase URL, and missing-source detection.

## [0.1.0]

* Initial independent Suppliers module.

## Fuentes

- [`changelog.txt`](changelog.txt), etapa monolítica.
- [`docs/PLUGIN-HISTORY-SOURCES.md`](../docs/PLUGIN-HISTORY-SOURCES.md), PR y referencias históricas.

## Actualizaciones

Para nuevas funcionalidades se edita este `CHANGELOG.md`. Antes de publicar, se debe reflejar el resumen de la versión en `readme.txt`, que es el único Changelog mostrado automáticamente por WordPress.org.
