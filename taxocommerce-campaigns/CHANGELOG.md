## [0.1.14] — 2026-11-10 — Cabecera en tres zonas sin contenido duplicado (#2521)

- Izquierda: nombre, descripción local del plugin y un único enlace estático `Plugin URI` bajo el texto.
- Centro: desplegable más ancho con el historial local `CHANGELOG.md`, cerrado inicialmente.
- Derecha: versión y fecha reales del propio plugin.
- Se retira la segunda caja «Información del plugin», sin cambios en servicios, menús ni consultas externas.

## [0.1.13] — 2026-11-10 — Descripción visible desde el plugin (#2494)

- La descripción inmediatamente debajo del título administrativo procede de `Description:` del archivo principal, sin textos duplicados ni consultas externas.
- Se mantienen el enlace estático `Plugin URI`, el historial local y las funcionalidades anteriores.

## [0.1.12] — 2026-11-10 — Ficha local en el encabezado (#2494)

- Elimina consultas REST/caché del extracto y usa `Description` y `Plugin URI` locales.
- Conserva el enlace oficial y el historial, sin alterar servicios existentes.

## [0.1.11] - 2026-11-10 - Resiliencia del extracto oficial (#2489)

- Consulta exclusivamente la API REST pública de la página indicada en Plugin URI (autor del plugin), nunca páginas de la tienda del cliente.
- Conserva el último extracto oficial comprobado; los fallos de red no borran el texto. Caché de errores reducida a 5 minutos y versión nueva de clave para ignorar fallos antiguos cacheados.
- Si nunca se ha obtenido un extracto, muestra provisionalmente la Description incluida por el autor en el plugin; permite abrir la ficha oficial. No cambia operaciones comerciales ni requiere Core.
- Pruebas para recuperación tras fallos HTTP, JSON inválido y caché negativa; compatibles con la familia completa.

## [0.1.10] — 2026-11-09 — Ficha oficial del desarrollador y cabecera centrada en tres zonas (#2476)

- La cabecera distribuye identificación a la izquierda, dos desplegables alineados y centrados (extracto oficial a la izquierda e historial local a la derecha), y versión y fecha a la derecha; distribución adaptable a móvil.
- El enlace oficial se obtiene exclusivamente de Plugin URI, definido por el autor del plugin. Eliminados el formulario de URL y su acción de guardado; los ajustes antiguos de la tienda no pueden sobrescribirlo.
- El historial se lee del CHANGELOG.md incluido, plegado de inicio. Sin dependencias obligatorias entre plugins ni cambios en productos, pedidos, datos o funcionalidades existentes.

## [0.1.9] — 2026-11-08 — Información dinámica desde ficha oficial (#2443)

- El desplegable izquierdo consulta el excerpt publicado en la página Plugin URI, con caché y configuración independiente.
- Formulario sanitizado; soporte para desactivación y funcionamiento sin servicios externos. Historial local sin cambios.

## [0.1.8] — 2026-11-08 — Extracto público remoto opcional (#2443)

- Consulta opcional por HTTPS del excerpt REST de una ficha oficial configurada; muestra el enlace a esa página en un panel izquierdo plegado.
- Mantiene el historial propio en el panel derecho. Caché, permisos y escape de salida; sin servicios obligatorios ni cambios en datos.

## [0.1.7] - 2026-11-08 - Autotest: Funcionalidades y Codigo (#2401)

- La pestaña Autotest conserva sus pruebas funcionales y su informe JSON. Nueva subpestana Codigo de solo lectura para este plugin.
- Tres categorias PHP, WordPress y seguridad, cinco niveles de CPU, pausa/reanudar y descarga JSON independiente. Sin PCP ni Core obligatorio.

## [0.1.6] — 2026-11-08 — Historial plegable en cabecera (#2382)

- Desplegable cerrado por defecto inmediatamente debajo del título, con el historial propio de `CHANGELOG.md`; sin nueva pestaña ni dependencia de otros plugins.
- Textos del título preparados para ES/EN/FR/DE/NL, salida escapada, desplazamiento vertical y mantenimiento de menús y funciones existentes.
- El paquete instalable y la subida incremental incluyen únicamente el historial raíz necesario. Sin cambios en base de datos ni servicios.

## [0.1.5] — 2026-11-07 — Cabecera administrativa de versión y fecha (#2336)

- Muestra título, versión activa y fecha de publicación en el administrador mediante Release Date.
- La fecha no depende de la hora de consulta ni del momento de copia por FTP. Sin cambios en servicios, datos, menús ni permisos.

## [Unreleased] — Regional WordPress locale fallback (#2295)

- Respeta primero los paquetes oficiales de WordPress .mo y .l10n.php para el locale exacto.
- Si falta un catálogo local regional, usa el catálogo existente del mismo idioma (es_ES, en_US, fr_FR, de_DE o nl_NL); no cambia la configuración ni el idioma del usuario.
- Sin cambios en lógica, datos, menús o dependencias del plugin. Las traducciones de contenido incompletas siguen abiertas en #2062.

## [Unreleased] — Etiquetas de diseños localizables (#2239)

- Las tres etiquetas de diseños de imágenes sociales ahora utilizan gettext y el catálogo de TaxoCommerce Campaigns.
- Se aprovechan traducciones ES/EN/FR/DE/NL ya presentes en POT/PO/MO. Sin cambios en gráficos, colores, imágenes o procesos de campaña.

## [0.1.4] — 2026-11-05 — Traducciones de campañas de marketing (#2239)

- Menú y pestañas con nombre funcional «Campañas de marketing» en español y equivalentes naturales en inglés, francés, alemán y neerlandés.
- Completados y compilados los cinco catálogos `.po/.mo` nativos, con 151 mensajes por idioma en lugar de archivos vacíos. Actualizada la plantilla `.pot`.
- Internacionalizados los encabezados, textos administrativos, formularios de campañas, calendarios, banners y controles de Autocheck. Corregidas erratas ortográficas en las traducciones españolas sin alterar claves de datos, acciones ni URLs.
- Añadidas pruebas de integridad de los catálogos y conservadas las integraciones y reglas comerciales. Publicación sujeta a Plugin Check y validación funcional en STAGING/Focazul; sin despliegue en producción.

## Unreleased - Familia i18n #2062


## [0.1.3] — 2026-11-03

- Nombre uniforme «Autocheck» y descarga autenticada del último informe JSON, con resultados por comprobación y sin ejecutar operaciones reales.
- Issue #2150; PR #2151. Mantiene las rutas internas existentes y la ejecución no destructiva.

- Dominio gettext, carga de catalogos locales, etiquetas de menús y textos HTML de salida estáticos; catálogos PO/MO sincronizados con el texto fuente.

# CHANGELOG — TaxoCommerce Campaigns

## [0.1.2] — 2026-11-03

- Cabecera visual común basada en Core: nombre, icono, descripción, versión y diseño adaptable.
- Lógica funcional, pestañas y permisos intactos. Plugin autónomo sin dependencia de Core. Issue #2137.

> Historial de las versiones del plugin **independiente**. Las funciones anteriores a su separación están documentadas en [`changelog.txt`](changelog.txt).
> Fuente inicial: entradas verificadas del `readme.txt` en GitHub. Solo se atribuye una fecha si la fuente la acredita.

## [Unreleased]

Sin novedades pendientes documentadas.

## [0.1.1] — 2026-11-01

- Identificada explícitamente la versión 0.1.1 para distinguir el paquete corregido de la 0.1.0 instalada anteriormente; sincronizados la cabecera, la constante y el Stable tag de WordPress.org.
- Revisados los avisos de Plugin Check del módulo Campaigns: identificadores de tablas SQL con `%i` en `$wpdb->prepare()`, entradas de administración y comentarios PHPCS localizados para operaciones sobre tablas propias sin API alternativa.
- Revisadas también las consultas de Autotest y conservadas explícitamente las APIs públicas históricas de integración con Core, Templates y GD (sin renombrarlas).
- Conservadas las reglas de precios, programación y productos. Seguimiento: #1544; QA automatizada superada, pendiente validación funcional en STAGING.

- Incorporadas pestañas Manual (usuario y técnico) y Autotest interno de solo lectura; comprueba carga, tablas, versión de esquema y dependencias sin modificar campañas, precios ni programación.
- Ampliados y separados claramente el manual operativo para usuarios y la guía técnica para desarrolladores futuros: navegación, procedimientos comerciales, estructura del código, tablas y migraciones, hooks, compatibilidad, seguridad, pruebas y diagnóstico, siguiendo el formato de los plugins Suppliers, Billing y Scrappers.


## [0.1.0]

* Extracted campaigns, product offers and their commercial calendar into an independent module.
* Retained canonical campaign records and compatibility with existing public template hooks.

## Referencias

- [Origen monolítico y transición](changelog.txt)
- [Índice de evidencias de los 20 plugins](../docs/PLUGIN-HISTORY-SOURCES.md)

## Mantenimiento

Este es el historial maestro que se actualiza al introducir novedades. Antes de publicar cada versión en WordPress.org, su resumen debe trasladarse al apartado `== Changelog ==` de `readme.txt`; WordPress.org no presenta este Markdown automáticamente.
