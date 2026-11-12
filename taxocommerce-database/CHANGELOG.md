## [0.4.20] — 2026-11-10 — Cabecera en tres zonas sin contenido duplicado (#2521)

- Izquierda: nombre, descripción local del plugin y un único enlace estático `Plugin URI` bajo el texto.
- Centro: desplegable más ancho con el historial local `CHANGELOG.md`, cerrado inicialmente.
- Derecha: versión y fecha reales del propio plugin.
- Se retira la segunda caja «Información del plugin», sin cambios en servicios, menús ni consultas externas.

## [0.4.19] — 2026-11-10 — Descripción visible desde el plugin (#2494)

- La descripción inmediatamente debajo del título administrativo procede de `Description:` del archivo principal, sin textos duplicados ni consultas externas.
- Se mantienen el enlace estático `Plugin URI`, el historial local y las funcionalidades anteriores.

## [0.4.18] — 2026-11-10 — Ficha local en el encabezado (#2494)

- Elimina consultas REST/caché del extracto y usa `Description` y `Plugin URI` locales.
- Conserva el enlace oficial y el historial, sin alterar servicios existentes.

## [0.4.17] - 2026-11-10 - Resiliencia del extracto oficial (#2489)

- Consulta exclusivamente la API REST pública de la página indicada en Plugin URI (autor del plugin), nunca páginas de la tienda del cliente.
- Conserva el último extracto oficial comprobado; los fallos de red no borran el texto. Caché de errores reducida a 5 minutos y versión nueva de clave para ignorar fallos antiguos cacheados.
- Si nunca se ha obtenido un extracto, muestra provisionalmente la Description incluida por el autor en el plugin; permite abrir la ficha oficial. No cambia operaciones comerciales ni requiere Core.
- Pruebas para recuperación tras fallos HTTP, JSON inválido y caché negativa; compatibles con la familia completa.

## [0.4.16] — 2026-11-09 — Ficha oficial del desarrollador y cabecera centrada en tres zonas (#2476)

- La cabecera distribuye identificación a la izquierda, dos desplegables alineados y centrados (extracto oficial a la izquierda e historial local a la derecha), y versión y fecha a la derecha; distribución adaptable a móvil.
- El enlace oficial se obtiene exclusivamente de Plugin URI, definido por el autor del plugin. Eliminados el formulario de URL y su acción de guardado; los ajustes antiguos de la tienda no pueden sobrescribirlo.
- El historial se lee del CHANGELOG.md incluido, plegado de inicio. Sin dependencias obligatorias entre plugins ni cambios en productos, pedidos, datos o funcionalidades existentes.

## [0.4.15] — 2026-11-08 — Información dinámica desde ficha oficial (#2443)

- El desplegable izquierdo consulta el excerpt publicado en la página Plugin URI, con caché y configuración independiente.
- Formulario sanitizado; soporte para desactivación y funcionamiento sin servicios externos. Historial local sin cambios.

## [0.4.14] — 2026-11-08 — Extracto comercial remoto configurable en cabecera (#2443)

- Desplegable izquierdo para obtener el extracto público de una página WordPress oficial, únicamente cuando el administrador configura su URL HTTPS, con enlace a la ficha de origen.
- Desplegable derecho de historial local, independiente del servicio externo. Validación y escape, límite temporal de petición y caché para evitar demoras.
- Sin dependencia de otros plugins, sin origen comercial predefinido y sin modificación de funcionalidades existentes.

## [0.4.13] - 2026-11-08 - Autotest funcional y de codigo (#2401)

- Conserva autotest funcional y JSON; nuevo Code Check independiente del propio plugin: PHP, WordPress, seguridad. Cinco velocidades, lotes y JSON separado. Sin PCP ni Core obligatorio.

## [0.4.12] — 2026-11-08 — Historial plegable en cabecera (#2382)

- Desplegable cerrado por defecto inmediatamente debajo del título, con el historial propio de `CHANGELOG.md`; sin nueva pestaña ni dependencia de otros plugins.
- Textos del título preparados para ES/EN/FR/DE/NL, salida escapada, desplazamiento vertical y mantenimiento de menús y funciones existentes.
- El paquete instalable y la subida incremental incluyen únicamente el historial raíz necesario. Sin cambios en base de datos ni servicios.

## [0.4.11] — 2026-11-07 — Cabecera administrativa de versión y fecha (#2336)

- Muestra título, versión activa y fecha de publicación en el administrador mediante Release Date.
- La fecha no depende de la hora de consulta ni del momento de copia por FTP. Sin cambios en servicios, datos, menús ni permisos.

## [0.4.10] — 2026-11-07 — Registro Core sin traducciones prematuras (#2330)

- Registro de metadatos de este plugin en TaxoCommerce Core pospuesto a `init` prioridad 11 cuando Core solicita los datos durante `plugins_loaded`.
- Conserva el registro de módulos, traducciones por locale, menús, acciones, contenido y funcionamiento independiente sin modificar Core.
- Evita el aviso de WordPress 6.7+ `_load_textdomain_just_in_time` causado por la descripción traducida del módulo.
- La desaparición del aviso requiere confirmación en STAGING tras desplegar.

## [Unreleased] — Regional WordPress locale fallback (#2295)

- Respeta primero los paquetes oficiales de WordPress .mo y .l10n.php para el locale exacto.
- Si falta un catálogo local regional, usa el catálogo existente del mismo idioma (es_ES, en_US, fr_FR, de_DE o nl_NL); no cambia la configuración ni el idioma del usuario.
- Sin cambios en lógica, datos, menús o dependencias del plugin. Las traducciones de contenido incompletas siguen abiertas en #2062.

## [Unreleased] — Internacionalización del desbloqueo masivo (#2271)

- Localizadas con gettext las etiquetas, el aviso, el usuario, la fecha y la acción de desbloqueo de aceptación masiva, sin alterar los identificadores técnicos, los tokens ni la lógica de seguridad.
- Sincronizadas 11 cadenas nuevas en POT y catálogos de español, inglés, francés, alemán y neerlandés; regenerados los cinco MO.
- El resto de textos heredados de Database sigue pendiente de auditoría para garantizar una interfaz íntegramente traducible.

## [0.4.9] — 2026-11-05 (i18n Database #2213)

- Carga de textos de administración según idioma de WordPress: pestañas, KPIs, controles, títulos y ayudas vinculados a gettext; traducción nativa inglesa corregida.
- Sin cambios en las operaciones de base de datos, SQL, exportadores, nonces ni datos.

## Unreleased - Familia i18n #2062


## [0.4.8] — 2026-11-05

- Una sola pestaña «Exportar BBDD»: reúne el SQL completo de WordPress, SQL solo SEO, CSV/SQL por tabla y ZIP de las tablas SEO (CSV, SQL o ambos).
- Las rutas antiguas `tcdb_screen=table&section=export` y `tcdb_screen=clean&seo_clean_db_tab=export` siguen accediendo a la misma vista.
- Sin cambios en los exportadores existentes, permisos, nonces, estructura SQL ni datos. Issue #2202.

## [0.4.7] — 2026-11-03

- Nombre uniforme «Autocheck» y descarga autenticada del último informe JSON, con resultados por comprobación y sin ejecutar operaciones reales.
- Issue #2150; PR #2151. Mantiene las rutas internas existentes y la ejecución no destructiva.

- Dominio gettext, carga de catalogos locales, etiquetas de menús y textos HTML de salida estáticos; catálogos PO/MO sincronizados con el texto fuente.

# CHANGELOG — TaxoCommerce Database

## [0.4.6] — 2026-11-03

- Cabecera visual común basada en Core: nombre, icono, descripción, versión y diseño adaptable.
- Lógica funcional, pestañas y permisos intactos. Plugin autónomo sin dependencia de Core. Issue #2137.

> Historial maestro de versiones del plugin independiente. El origen monolítico se documenta en [`changelog.txt`](changelog.txt).

## [Unreleased]

- Inicio de internacionalización WordPress mediante gettext (`Domain Path: /languages`), catálogos por idioma y traducción de etiquetas del menú, sin cambios funcionales. Seguimiento: #2037 (plan global #2036). Cobertura restante de interfaz, Autotest y manuales pendiente de revisión por idioma.


- Limpieza controlada de STAGING (issue #1937): eliminar exclusivamente restos de pruebas de desarrollo bajo `tests/` y el antiguo `.ftp-deploy-sync-state.json` del plugin instalado. Inventario, copia de seguridad SHA-256 y verificación remota obligatorios. Se conservan las clases operativas de `includes/`, los datos y las pruebas del repositorio; sin intervención en PRODUCCIÓN.

Sin novedades pendientes documentadas.


## [0.4.5]

* Fixed Auto Test false positives: an empty complete backup is a failure; absent SEO tables are reported as not executed instead of success.
* Improved the 17-cleanup-action regression contract and documented the Auto Test in the user/developer manuals.
* No changes to cleanup, repair, export engines or SQL operations.

## [0.4.4]

* Use prepared SQL identifier placeholders for critical Database queries and fix the dynamic-role prepared statement.
* Verify nonce in the object-reset action handler; keep all original cleanups, exports and Auto Test.

## [0.4.3]

* Added read-only Database Auto Test: 17 independent checks of original services, MySQL connectivity, SEO schemas, read-only sampling, export availability, hooks, and developer manual.
* Per-user last two reports (30-day expiration), showing changes between before/after deployments. No destructive SQL or real export downloads.

## [0.4.2]

* Add a seventh Manual tab with complete user and developer documentation, readable in WordPress admin.
* Document all existing buttons and cleanup actions, file architecture, hooks, SQL data model, limitations and extension testing.
* Show the six original screens in one horizontal responsive tab bar, preserving all existing routes and actions.
* Restored the two original database services and removed duplicated or non-original tools.
* Kept the original diagnostics, repairs, cleanup actions and data exports.
* Excluded the cross-plugin Data Layer and historical rollback support.

## [0.4.1]

* Organise the six restored database panels into one navigation bar.

## [0.4.0]

* Restore Clean Database and SEO Data Table from the monolith.

## [0.3.0]

* Restore the original three Clean Database panels and legacy maintenance checks.

## [0.2.0]

* Introduce standalone database diagnostics and optional maintenance controls.

## Fuentes

- [`changelog.txt`](changelog.txt), etapa monolítica.
- [`docs/PLUGIN-HISTORY-SOURCES.md`](../docs/PLUGIN-HISTORY-SOURCES.md), PR y referencias históricas.

## Actualizaciones

Para nuevas funcionalidades se edita este `CHANGELOG.md`. Antes de publicar, se debe reflejar el resumen de la versión en `readme.txt`, que es el único Changelog mostrado automáticamente por WordPress.org.
