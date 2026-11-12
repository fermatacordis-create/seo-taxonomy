## [0.2.32] — 2026-11-10 — Cabecera en tres zonas sin contenido duplicado (#2521)

- Izquierda: nombre, descripción local del plugin y un único enlace estático `Plugin URI` bajo el texto.
- Centro: desplegable más ancho con el historial local `CHANGELOG.md`, cerrado inicialmente.
- Derecha: versión y fecha reales del propio plugin.
- Se retira la segunda caja «Información del plugin», sin cambios en servicios, menús ni consultas externas.

## [0.2.31] — 2026-11-10 — Descripción visible desde el plugin (#2494)

- La descripción inmediatamente debajo del título administrativo procede de `Description:` del archivo principal, sin textos duplicados ni consultas externas.
- Se mantienen el enlace estático `Plugin URI`, el historial local y las funcionalidades anteriores.

## [0.2.30] — 2026-11-10 — Ficha local en el encabezado (#2494)

- Elimina consultas REST/caché del extracto y usa `Description` y `Plugin URI` locales.
- Conserva el enlace oficial y el historial, sin alterar servicios existentes.

## [0.2.29] - 2026-11-10 - Resiliencia del extracto oficial (#2489)

- Consulta exclusivamente la API REST pública de la página indicada en Plugin URI (autor del plugin), nunca páginas de la tienda del cliente.
- Conserva el último extracto oficial comprobado; los fallos de red no borran el texto. Caché de errores reducida a 5 minutos y versión nueva de clave para ignorar fallos antiguos cacheados.
- Si nunca se ha obtenido un extracto, muestra provisionalmente la Description incluida por el autor en el plugin; permite abrir la ficha oficial. No cambia operaciones comerciales ni requiere Core.
- Pruebas para recuperación tras fallos HTTP, JSON inválido y caché negativa; compatibles con la familia completa.

## [0.2.28] — 2026-11-09 — Ficha oficial del desarrollador y cabecera centrada en tres zonas (#2476)

- La cabecera distribuye identificación a la izquierda, dos desplegables alineados y centrados (extracto oficial a la izquierda e historial local a la derecha), y versión y fecha a la derecha; distribución adaptable a móvil.
- El enlace oficial se obtiene exclusivamente de Plugin URI, definido por el autor del plugin. Eliminados el formulario de URL y su acción de guardado; los ajustes antiguos de la tienda no pueden sobrescribirlo.
- El historial se lee del CHANGELOG.md incluido, plegado de inicio. Sin dependencias obligatorias entre plugins ni cambios en productos, pedidos, datos o funcionalidades existentes.

## [0.2.27] — 2026-11-08 — Información dinámica desde ficha oficial (#2443)

- El desplegable izquierdo consulta el excerpt publicado en la página Plugin URI, con caché y configuración independiente.
- Formulario sanitizado; soporte para desactivación y funcionamiento sin servicios externos. Historial local sin cambios.
- Corregidos dos avisos de Plugin Check para selectores GET de navegación, conservando las verificaciones de nonce de acciones que modifican datos.

## [0.2.26] — 2026-11-08 — Extracto público remoto opcional (#2443)

- Consulta opcional por HTTPS del excerpt REST de una ficha oficial configurada; muestra el enlace a esa página en un panel izquierdo plegado.
- Mantiene el historial propio en el panel derecho. Caché, permisos y escape de salida; sin servicios obligatorios ni cambios en datos.

## [0.2.25] — 2026-11-08 — Dos subpestañas en un solo Autocheck (#2401)

- Eliminada la pestaña principal redundante «Code Check». «Autocheck» muestra «Funcionalidades» y «Código» con motores independientes.
- Conservados por completo los chequeos de servidor PHP, MySQL, WordPress, CPU, memoria y procesos en «Sistema», sin confundirlos con el Autotest del plugin.
- Los enlaces anteriores a Code Check siguen abriéndose, dentro de Autocheck. Mantiene sus tres análisis, limitación CPU, stop/reanudación y JSON.
- Sin requerir PCP, Core, otros módulos ni el monolito.

## [0.2.24] — 2026-11-08 — Historial plegable en cabecera (#2382)

- Historial propio en un desplegable cerrado bajo el título, sin nuevas pestañas ni cambios en los chequeos de código.
- Lectura del CHANGELOG.md local y visualización escapada.

## [0.2.23] — 2026-11-08 — Auditoría propia de código ampliada (#2384)

- Conserva el Autocheck funcional y las tres columnas PHP, WordPress y Seguridad en Code Check, con cinco niveles de limitación de CPU, pausa y JSON.
- Comprueba rutas locales resolubles de include/require sin ejecutar archivos: destino ausente (fallo), referencia exterior (revisión) e inclusión dinámica (no verificado).
- Detecta nombres declarados de clases y funciones globales y avisa de duplicidades posibles entre archivos; no asume que ambos se cargan simultáneamente.
- Detecta construcciones y funciones de PHP 8 que podrían superar el mínimo declarado PHP 7.4. El parser nativo sigue validando solo la versión de PHP actualmente instalada.
- Añade indicios de revisión sobre entrada sin sanitización evidente, salida sin escape evidente, consultas SQL sin prepare, REST sin permission_callback y endpoints AJAX/admin sin verificación identificable de nonce/capacidades.
- Los resultados registran códigos de regla y líneas, sin atribuir una auditoría completa de seguridad o equivalencia con PCP. No ejecuta código externo ni requiere Plugin Check, WP-CLI o otros módulos.
- Solo plugin nuevo System Check, sin modificaciones en monolito ni en el resto de la familia.

## [0.2.22] — 2026-11-08 — Separar Autocheck funcional y Chequeo del código (#2372)

- Se mantienen los chequeos funcionales existentes de Autocheck, sin subpestañas. Chequeo del código pasa a pestaña principal independiente, junto a Sistema, Plugin Validation, HTTP y Procesos.
- Los enlaces antiguos `tcs_subtab=code` siguen funcionando y las acciones del proceso vuelven a la pestaña nueva.
- La pestaña de código muestra los resultados reales del motor autónomo por archivo (PHP, estructura WordPress y patrones de seguridad), con conteos por estado, archivos y líneas de hallazgos.
- Se conservan velocidades, pausa/reanudación, permisos, nonce, informes JSON y alcance exclusivo del propio plugin; no requiere Plugin Check ni otros plugins.
- Las reglas no ejecutadas y el chequeo oficial de WordPress.org siguen identificados como no verificados. Sin cambios en datos ni despliegue a producción.

## [0.2.21] — 2026-11-08 — Corregir avisos PCP y facilitar despliegue en Focazul (#2361)

- Elimina la lectura duplicada de parámetros GET en el registro de recursos de Autocheck. Limita la excepción PHPCS de nonce a la selección de pestañas de solo lectura.
- Mantiene los dos chequeos autónomos «Funcionalidades» y «Code Check», sus tres categorías (PHP, normas WordPress y seguridad), limitación CPU y exportación JSON.
- Versiona 0.2.21 para una actualización verificable del plugin independiente. Sin dependencias obligatorias de WordPress Plugin Check ni cambios del monolito.

## [0.2.20] — 2026-11-08 — Autocheck independiente de funcionalidades y código (#2361)

- Dos subpestañas en Autocheck: «Functionality Check» conserva el autocheck existente; «Code Check» inspecciona únicamente los archivos PHP del plugin System Check.
- Code Check ejecuta tres análisis propios: parser de sintaxis PHP con TOKEN_PARSE, convenciones básicas de WordPress y búsqueda de funciones PHP de riesgo sin ejecutar ningún fragmento inspeccionado.
- No necesita ni instala PCP, WP-CLI, GitHub ni ningún otro plugin de chequeo. No se afirma equivalencia con las pruebas completas de WordPress.org.
- Cinco velocidades de CPU con lotes de 1 a 4 archivos, detener/reanudar, resultados separados y descarga JSON privada por usuario/sitio.
- Controles avanzados de seguridad (nonces, sanitización, escape y vulnerabilidades de dependencias) aparecen como no verificados, nunca como superados.
- Cambios limitados a System Check y a su CI. Sin cambios en los plugins antiguos o en el resto de la familia.

## [Unreleased] — Internacionalización de la interfaz de System Check (#2062)

- Traducidas 71 cadenas visibles de administración, validación, permisos, informes y escaneo en es_ES, en_US, fr_FR, de_DE y nl_NL: 355 entradas gettext nuevas.
- Regenerados los cinco catálogos GNU MO con 308 entradas por idioma; sin cambios en identificadores gettext, funcionalidades, datos, permisos, rutas, cron o consultas.
- Cobertura todavía parcial: quedan cadenas en informes, configuraciones y otras pantallas por traducir, así como comprobar textos fuera de gettext y la carga real por locale en WordPress.
- Validación pendiente: comprobación de PO/MO, Plugin Check oficial y pruebas funcionales en STAGING y Focazul. No desplegar a producción.

## [0.2.19] — 2026-11-07 — Cabecera administrativa de versión y fecha (#2336)

- Muestra título, versión activa y fecha de publicación en el administrador mediante Release Date.
- La fecha no depende de la hora de consulta ni del momento de copia por FTP. Sin cambios en servicios, datos, menús ni permisos.

## [Unreleased] — Revisión i18n (seguimiento #2062)

- Completada una entrada gettext previamente vacía en ES/EN/FR/DE/NL; conserva el mismo identificador fuente y no modifica la lógica de la tienda.
- Pendiente recompilar catálogos MO y validar cobertura frente al POT, en especial discrepancias entre idiomas; no supone traducción integral del plugin.

## [Unreleased] — Reutilización segura de traducciones (#2307)

- Completadas únicamente entradas gettext PO vacías con traducciones idénticas y no ambiguas ya existentes en otros catálogos TaxoCommerce.
- Regenerados los MO de los idiomas modificados. Se conservan los textos, identificadores técnicos, funciones y datos anteriores sin cambios.
- Esta corrección es parcial: los textos restantes, las cadenas fuera de gettext y la validación visual por idioma siguen abiertos en #2062.

## [Unreleased] — Primera fase de internacionalización (#2274)

- Traducidas 42 cadenas principales de administración, accesibilidad, diagnósticos y manual en cinco idiomas: es_ES, en_US, fr_FR, de_DE y nl_NL.
- Regenerados los catálogos binarios MO correspondientes. Sin cambios en los chequeos ni en sus resultados.
- Continúan pendientes las demás cadenas del POT, la auditoría de literales fuera de gettext y las pruebas reales de locale. No declarar internacionalización completa.

## [Unreleased] — Regional WordPress locale fallback (#2295)

- Respeta primero los paquetes oficiales de WordPress .mo y .l10n.php para el locale exacto.
- Si falta un catálogo local regional, usa el catálogo existente del mismo idioma (es_ES, en_US, fr_FR, de_DE o nl_NL); no cambia la configuración ni el idioma del usuario.
- Sin cambios en lógica, datos, menús o dependencias del plugin. Las traducciones de contenido incompletas siguen abiertas en #2062.

## [0.2.18] — 2026-11-06 — Supervisión de procesos de WordPress (#2250)

- Nueva pestaña Procesos: inventario acotado de Action Scheduler y WP-Cron (100 registros por estado), más los procesos conocidos de Dependiente, Import-Export (incluidas exportaciones CSV) y auditorías HTTP si están activos.
- Cancelación protegida por permisos y nonce de acciones pendientes, eventos programados y parada cooperativa de los workers integrados; botón global limitado a tareas consideradas cancelables, excluyendo infraestructura crítica de WordPress/WooCommerce.
- No se ejecutan comandos de sistema ni se interrumpen peticiones PHP en curso. Las operaciones se verifican posteriormente en el estado del proceso; la solicitud no certifica la detención física.
- Sin dependencia obligatoria de otros plugins TaxoCommerce; filtros para que otras extensiones registren sus workers y política de cancelación.

## [0.2.17] — 2026-11-05 — Descarga persistente de Autocheck (#2234)

- La descarga JSON del último Autocheck se mantiene visible al volver a la pestaña mientras el informe del usuario y sitio siga disponible (caducidad existente de una hora).
- Primera visita y después de caducar: no se ofrece descarga sin resultados; nunca se inicia una prueba por descargar.
- Se preservan el nonce, permisos de administrador, los estados pass/fail/skip y las pruebas; ampliada la regresión de interfaz y persistencia.
- Autocheck más funcional: valida datos, identificadores y estados del diagnóstico PHP, contratos de módulos registrados, callbacks y planificación del auditor HTTP, además del endpoint y la serialización JSON.
- Examina exclusivamente el último diagnóstico de servidor guardado: no evaluable si no existe o supera 24 horas; fallo si el informe está corrupto. No realiza escaneos ni altera el entorno.
- El JSON incluye evidencias estructuradas y porcentaje de cobertura real (omite las pruebas no ejecutadas); sin crear pedidos, peticiones HTTP, correos ni tareas. (#2236)

## [0.2.16] — 2026-11-05 — Corrección de arranque y cabecera (#2230)

- El motor local de Server Status ya no se bloquea por la mera presencia en disco del antiguo seo-taxonomy. Si el monolito está activo pero no carga Server Status, se incorpora únicamente el módulo de servidor sin recargar Validation o Reporting.
- La cabecera visual común de admin-branding.php se muestra una sola vez en Sistema, Plugin Validation, HTTP, Autocheck y Manual; pestañas, exportación JSON y controles permanecen en su sitio.
- Prueba de regresión autónoma para el arranque, una única cabecera y compatibilidad con las rutas existentes; la ejecución de Plugin Check sigue separada del despliegue.
- No se alteran consultas, informes, tablas, credenciales, permisos, nonces o datos del sitio.

## Unreleased - Familia i18n #2062


## [0.2.15] — 2026-11-03

- Pestaña homogénea «Autocheck» y descarga JSON del último informe, por usuario y sitio, con control de permisos y nonce.
- Issue #2150, PR #2151; rutas existentes conservadas y sin pruebas de escritura.

- Dominio gettext, carga de catalogos locales, etiquetas de menús y textos HTML de salida estáticos; catálogos PO/MO sincronizados con el texto fuente.

# CHANGELOG — TaxoCommerce System Check

## [0.2.14] — 2026-11-03

- Cabecera común de la familia basada en Core: icono, nombre, descripción, versión y diseño adaptable.
- Navegación y funciones originales conservadas, sin dependencia de Core. Issue #2137.

> Historial de las versiones independientes del plugin. El origen de los motores de diagnóstico está en el monolito TaxoCommerce SEO; no se asignan versiones retrospectivas a esa etapa.

## [Unreleased]

## [0.2.13]

* Sustituidas por consultas preparadas con identificadores `%i` las dos variantes del resumen de acciones pendientes de Action Scheduler (con y sin tabla de grupos), sin cambiar la información devuelta ni aplicar caché.
* Reforzado el test de regresión para detectar la reintroducción de identificadores interpolados en ese diagnóstico; sincronizada la versión de las pruebas Auto Test y del paquete.
* Contrastadas las advertencias del informe de Plugin Check proporcionado con el código de STAGING: las antiguas interpolaciones notificadas ya habían sido sustituidas en los PR #2020 y #2016; la instalación que generó el informe debe actualizarse para evaluar el paquete nuevo.
* No se modifica el monolítico ni se despliega a producción. Issue #1958.

## [0.2.12]

* Integrados los cambios del PR #2016 sobre la corrección posterior del PR #2020: conservadas las consultas preparadas y las lecturas de diagnóstico actuales; añadida una prueba de regresión del orden de parámetros del UPDATE de Health Scan.

* Resueltos los casos pendientes de preparación SQL en informes de facturación, vocabulario, Health Scan y consulta de imágenes externas, manteniendo los identificadores mediante `%i` y los parámetros vinculados.
* Las consultas de diagnóstico de estado actual conservan resultados en tiempo real. Las excepciones a avisos de acceso directo/caché se acotan a las operaciones concretas y se justifican, sin ocultar alertas de seguridad generales.
* Verificada la procedencia de listas de parámetros SQL variables: enlaces de imágenes y escaneos usan plantillas de cláusulas e identificadores restringidas, con cada dato enlazado mediante `$wpdb->prepare()`; no se introducen datos SQL arbitrarios.
* Justificadas localmente las lecturas sin caché que comprueban el estado actual de datos; se conservan los mensajes de error y la respuesta de las comprobaciones.
* Ajustada la ubicación de las excepciones PHPCS a instrucciones individuales, evitando modificar el comportamiento de los avisos del resto de consultas del archivo.
* Actualizados los tests y el empaquetado; no se cambian las API históricas `seo_*`, las tablas ni el monolítico.
* PR y chequeo WordPress.org requeridos antes de desplegar; PRODUCCIÓN no incluida.


## [0.2.11]

* Corregidos identificadores `%i` de Facturación (sin doble entrecomillado), sondeo de servicios, consultas de imágenes y escritura del inventario Health Scan; filtros de diagnóstico protegidos contra parámetros no escalares.

* Compatible con WordPress.org sin renombrar APIs públicas heredadas: las excepciones de prefijos se acotan exclusivamente a archivos del motor histórico `seo_*`, que sigue compartiendo interfaces con el monolítico. Ninguna supresión global en el repositorio.
* Anotadas individualmente las consultas directas necesarias para verificar estados actuales del servidor y las operaciones sincronizadas del programador; no se introduce caché que altere los diagnósticos.
* Documentadas las consultas preparadas con fragmentos SQL de lista generados a partir de estructuras internas y datos vinculados por `$wpdb->prepare()`, sin omitir el chequeo de seguridad para SQL no validado.

* Seguridad SQL: preparados con `%i` los identificadores de las ocho consultas `EXPLAIN` de `seo_system_mysql_audit_explain_plans()`; se mantienen el nombre canónico de cada tabla, los filtros y los índices inspeccionados.
* Endurecidas las entradas `tcs_task`, `tcs_filter`, `tcs_search` y `tcs_paged` contra parámetros GET/POST no escalares, sin cambiar las acciones válidas ni las rutas.
* Nueva prueba CLI de contratos SQL y entradas y matriz de CI PHP 7.4/8.4; ZIP sin archivos `tests/` ni archivos ocultos como antes.
* Continúan pendientes y documentadas las advertencias de funciones/consultas heredadas (issue #1958). Se exige una nueva revisión del paquete y comprobación funcional en STAGING antes de considerar WordPress.org.
* Sin cambios en el plugin monolítico ni publicación en PRODUCCIÓN.


* Corregir la introspección del esquema de facturación con `%i` sin concatenar identificadores SQL; pasar explícitamente listas de parámetros a `wpdb::prepare()` en las consultas semánticas y en la recuperación de Health Scan.
* Restringir los selectores de navegación a entradas escalares, conservar únicamente las excepciones documentadas de lectura y actualizar las etiquetas del `readme.txt` a términos admitidos por WordPress.org.
* El escaneo ampliado del paquete de GitHub detecta numerosas advertencias pendientes de prefijos heredados (`seo_*`) y consultas diagnósticas: esta versión no se considera validada para WordPress.org hasta resolverlas con comprobación de compatibilidad.

* WordPress Plugin Check: endurecer preparación SQL de tablas WordPress y TaxoCommerce mediante placeholders `%i`, preparar límites `%d`, IDs de conjuntos `%d` y parámetros de filtro, preservando consultas semánticas, Data Layer, Health Scan y auditorías HTTP.
* Sanear y validar el tipo de parámetros GET/POST del diagnóstico; mantener los nonces exigidos para acciones y documentar las lecturas de navegación que no modifican datos.
* Usar la API `wp_count_posts()` para recuentos de productos y páginas publicados.
* Generar el ZIP instalable exclusivamente con archivos operativos, sin `tests/`, ficheros ocultos ni respaldos. Las pruebas CLI continúan en el repositorio y en GitHub Actions.
* Mantener los procesos, informes, tablas históricas y compatibilidad con WordPress 6.2+ / PHP 7.4. Issue #1958; sin despliegue a PRO.

## Versiones independientes

### 0.2.10

* Add a dedicated, administrator-triggered **Auto Test** tab with grouped, non-destructive checks for startup, permissions, PHP diagnostics, module registration, HTTP audit hooks and exports.
* Distinguish checks that pass, fail or are not executed / not applicable; no HTTP probes, mail, cron execution, database writes or report persistence are performed.
* Add a **Manual** tab with indexed Spanish User Manual and Technical Manual (H2/H3), describing the current System, Plugin Validation and HTTP screens, data/privacy controls, limitations, compatibility and diagnostics.
* Add smoke regression tests and execute them in System Check CI.

### 0.2.9

* Update WordPress.org metadata for the standalone System Check package.
* Keep gettext calls in the standalone text domain and use prepared SQL table identifiers where required by the existing checker.

### 0.2.8

* Provide standalone system and plugin diagnostics with compatibility routes.
* Include independent PHP checks and optional bounded local HTTP URL audits.
* Preserve administrator-controlled exports, reports and notification settings.

## Origen anterior a las versiones independientes

Los diagnósticos de servidor, la validación de plugins y las auditorías de URL proceden de TaxoCommerce SEO. No se documentan aquí supuestas versiones independientes anteriores a la separación.
