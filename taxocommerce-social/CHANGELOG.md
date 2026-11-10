## [0.1.14] — 2026-11-10 — Descripción visible desde el plugin (#2494)

- La descripción inmediatamente debajo del título administrativo procede de `Description:` del archivo principal, sin textos duplicados ni consultas externas.
- Se mantienen el enlace estático `Plugin URI`, el historial local y las funcionalidades anteriores.

## [0.1.13] — 2026-11-10 — Ficha local en el encabezado (#2494)

- Elimina consultas REST/caché del extracto y usa `Description` y `Plugin URI` locales.
- Conserva el enlace oficial y el historial, sin afectar al catálogo.

## [0.1.12] - 2026-11-10 - Resiliencia del extracto oficial (#2489)

- Consulta exclusivamente la API REST pública de la página indicada en Plugin URI (autor del plugin), nunca páginas de la tienda del cliente.
- Conserva el último extracto oficial comprobado; los fallos de red no borran el texto. Caché de errores reducida a 5 minutos y versión nueva de clave para ignorar fallos antiguos cacheados.
- Si nunca se ha obtenido un extracto, muestra provisionalmente la Description incluida por el autor en el plugin; permite abrir la ficha oficial. No cambia operaciones comerciales ni requiere Core.
- Pruebas para recuperación tras fallos HTTP, JSON inválido y caché negativa; compatibles con la familia completa.

## [0.1.11] — 2026-11-09 — Ficha oficial del desarrollador y cabecera centrada en tres zonas (#2476)

- La cabecera distribuye identificación a la izquierda, dos desplegables alineados y centrados (extracto oficial a la izquierda e historial local a la derecha), y versión y fecha a la derecha; distribución adaptable a móvil.
- El enlace oficial se obtiene exclusivamente de Plugin URI, definido por el autor del plugin. Eliminados el formulario de URL y su acción de guardado; los ajustes antiguos de la tienda no pueden sobrescribirlo.
- El historial se lee del CHANGELOG.md incluido, plegado de inicio. Sin dependencias obligatorias entre plugins ni cambios en productos, pedidos, datos o funcionalidades existentes.

## [0.1.10] — 2026-11-08 — Información dinámica desde ficha oficial (#2443)

- El desplegable izquierdo consulta el excerpt publicado en la página Plugin URI, con caché y configuración independiente.
- Formulario sanitizado; soporte para desactivación y funcionamiento sin servicios externos. Historial local sin cambios.

## [0.1.9] — 2026-11-08 — Extracto comercial remoto configurable en cabecera (#2443)

- Desplegable izquierdo para obtener el extracto público de una página WordPress oficial, únicamente cuando el administrador configura su URL HTTPS, con enlace a la ficha de origen.
- Desplegable derecho de historial local, independiente del servicio externo. Validación y escape, límite temporal de petición y caché para evitar demoras.
- Sin dependencia de otros plugins, sin origen comercial predefinido y sin modificación de funcionalidades existentes.

## [0.1.8] - 2026-11-08 - Autotest funcional y de codigo (#2401)

- Conserva autotest funcional y JSON; nuevo Code Check independiente del propio plugin: PHP, WordPress, seguridad. Cinco velocidades, lotes y JSON separado. Sin PCP ni Core obligatorio.

## [0.1.7] — 2026-11-08 — Historial plegable en cabecera (#2382)

- Desplegable cerrado por defecto inmediatamente debajo del título, con el historial propio de `CHANGELOG.md`; sin nueva pestaña ni dependencia de otros plugins.
- Textos del título preparados para ES/EN/FR/DE/NL, salida escapada, desplazamiento vertical y mantenimiento de menús y funciones existentes.
- El paquete instalable y la subida incremental incluyen únicamente el historial raíz necesario. Sin cambios en base de datos ni servicios.

## [0.1.6] — 2026-11-07 — Cabecera administrativa de versión y fecha (#2336)

- Muestra título, versión activa y fecha de publicación en el administrador mediante Release Date.
- La fecha no depende de la hora de consulta ni del momento de copia por FTP. Sin cambios en servicios, datos, menús ni permisos.

## [Unreleased] — Social translations (#2316)

- Traducidas las primeras 30 cadenas de interfaz, calendario, redes y Auto Test a ES/EN/FR/DE/NL.
- PO/MO sincronizados, sin cambiar el programador ni los datos sociales.
- Traducciones restantes y validación funcional pendientes.

## [Unreleased] — Internacionalización de Social Network (#2302)

- Traducidas las 143 cadenas originales y 17 cadenas adicionales de interfaz (160 en total) del catálogo vigente a español, inglés, francés, alemán y neerlandés; PO y MO sincronizados.
- Corregidas expresiones y acentuación del catálogo español, conservando términos técnicos (OAuth, WP-Cron, CSV, proveedores, nombres de redes).
- Se han incorporado a gettext las pestañas, estados de conexión, mensajes de resultados del Auto Test y tipos de contenido, conservando el comportamiento.
- Sin cambios en el programador, fechas y reglas de publicación, permisos, opciones almacenadas ni conexiones de terceros.
- Pendientes de revisión los literales que puedan permanecer fuera de gettext y la verificación funcional de idioma en WordPress.

## [Unreleased] — Regional WordPress locale fallback (#2295)

- Respeta primero los paquetes oficiales de WordPress .mo y .l10n.php para el locale exacto.
- Si falta un catálogo local regional, usa el catálogo existente del mismo idioma (es_ES, en_US, fr_FR, de_DE o nl_NL); no cambia la configuración ni el idioma del usuario.
- Sin cambios en lógica, datos, menús o dependencias del plugin. Las traducciones de contenido incompletas siguen abiertas en #2062.

## Unreleased - Familia i18n #2062


## [0.1.5] — 2026-11-03

- Nombre uniforme «Autocheck» y exportación JSON autenticada de los resultados con detalle por prueba.
- Issue #2150, PR #2151; rutas existentes conservadas y sin pruebas de escritura.

- Dominio gettext, carga de catalogos locales, etiquetas de menús y textos HTML de salida estáticos; catálogos PO/MO sincronizados con el texto fuente.

# CHANGELOG — TaxoCommerce Social

## [0.1.4] — 2026-11-03

- Cabecera visual común basada en Core: nombre, icono, descripción, versión y diseño adaptable.
- Lógica funcional, pestañas y permisos intactos. Plugin autónomo sin dependencia de Core. Issue #2137.

> Historial maestro de versiones del plugin independiente. El origen monolítico se documenta en [`changelog.txt`](changelog.txt).

## [Unreleased]

Sin novedades pendientes documentadas.

## [0.1.3]

- Corregida la parametrización SQL de consultas a publicaciones y tareas programadas, incluidos identificadores `%i` y límites `%d` (WordPress 6.7+).
- Confirmadas las lecturas GET de navegación y calendario sin operaciones de escritura; las credenciales POST siguen pasando por la validación específica de cada proveedor y el nonce administrativo.
- Documentadas de manera local las consultas SQL directas necesarias para contadores de clics, publicaciones, estados de WP-Cron, informes y exportaciones en tiempo real, sin añadir caché que altere el funcionamiento.
- Conservada la compatibilidad de la API pública de Core y actualizados los metadatos/readme para distinguir la versión corregida. Issue #1551.


## [0.1.2]

* Reorganizado el Manual en dos guías claramente independientes con H1, índice navegable y H2/H3: manual de usuario y manual técnico para futuros desarrolladores, siguiendo el patrón utilizado por TaxoCommerce Database.
* El manual de usuario incluye acceso, cinco conexiones, pasos de autorización, plantillas, programación, estados de tarea, calendario, limitaciones CSV, errores habituales y Auto Test.
* El manual técnico documenta mapa de archivos, clases y funciones, esquema y opciones persistentes, callbacks de proveedores, endpoints/nonce, hooks, WP-Cron, seguimiento, contratos CSV, pruebas offline, seguridad de tokens y procedimiento Issue/PR/CI/STAGING.
* Corregida la documentación heredada de CSV: existen APIs de backend, pero el calendario del plugin independiente no presenta actualmente controles CSV; no se han añadido funcionalidades.
* Sin cambios en conexiones, datos, comportamiento de publicaciones ni Auto Test; actualizadas pruebas de estructura documental.

## [0.1.1]

* Añadidas las pestañas Auto Test y Manual dentro de Social; conservadas Conexiones, Plantillas y Calendario.
* Auto Test de 16 pruebas locales, 15 seguras y una omitida por requerir operaciones reales, que verifica servicios, cinco proveedores, callbacks, configuración, dos tablas, lecturas acotadas, plantillas, agenda, WP-Cron, CSV, protección de secretos y contratos de publicación; sin HTTP, publicaciones ni cambios de agenda.
* Dos informes por administrador en transitorios durante 30 días, con fecha, versión, tiempos y comparación de regresiones.
* Manual integrado de usuario y manual técnico: autorización de redes, uso de plantillas, agenda, CSV, tablas canónicas, WP-Cron, opciones, seguridad y límites.
* Ampliadas pruebas de regresión PHP 7.4 y 8.4 y empaquetado sin archivos de test ni estado FTP.
* Sin nuevas tablas, modificaciones a conexiones, migraciones de datos ni cambios automáticos en publicaciones.


## [0.1.0]

* Extracted social connections, templates and calendar into a standalone plugin.
* Preserved existing settings and history without deleting them on deactivation.

## Fuentes

- [`changelog.txt`](changelog.txt), etapa monolítica.
- [`docs/PLUGIN-HISTORY-SOURCES.md`](../docs/PLUGIN-HISTORY-SOURCES.md), PR y referencias históricas.

## Actualizaciones

Para nuevas funcionalidades se edita este `CHANGELOG.md`. Antes de publicar, se debe reflejar el resumen de la versión en `readme.txt`, que es el único Changelog mostrado automáticamente por WordPress.org.
