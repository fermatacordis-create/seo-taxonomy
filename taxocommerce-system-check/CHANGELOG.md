## [0.2.45] — 2026-11-20 — Checkout-safe Core registration (#2848)

- Defer optional Core module description translation until WordPress `init` (priority 11) instead of performing gettext at `plugins_loaded`. This prevents System Check from emitting an early-loading notice that pollutes WooCommerce AJAX JSON during checkout.
- Retain diagnostics, manual Autocheck buttons, menus, module registry and all existing services. No checkout hooks, changes to other plugins or production deployment.
- Add a regression contract for the initialization order and preservation of Core metadata; confirm the final WooCommerce checkout flow in STAGING before closing the issue.

## Documentation — 2026-11-18 — Distribution and standalone description (#2787)

- Reordered the public readme.txt around installed functionality, optional integrations and safe administration actions. TaxoCommerce Core is optional, and the previous legacy engine is not a user-facing requirement.
- Removed obsolete presentation of historical implementation notes from the front of the readme, keeping the original text in the archival section below without deleting history. No runtime code changes.
## [0.2.44] — 2026-11-18 — Traducciones de interfaz de System Check (#2789)

- Corrige la mezcla de idiomas en la navegación, botones de exportación, comprobaciones básicas y opciones de CPU en Plugin Validation y el piloto Core.
- Los estados visibles de progreso e informes se traducen según el idioma de WordPress sin modificar los identificadores internos ni los contratos JSON/AJAX.
- Actualiza POT y cinco PO/MO (ES/EN/FR/DE/NL) con 555 entradas por idioma; corrige el inglés de «Código» y «Funcionalidades» y el encabezado español de Plugin Validation.
- Añade una prueba de regresión de catálogos compilados. No altera los motores de chequeo, procesos ni servicios de otros plugins.

## [0.2.43] — 2026-11-16 — Chequeo secuencial de todos los plugins activos (#2755)

- Botón «Chequear todos» junto al control de CPU en System Check → Plugin Validation; utiliza exclusivamente las filas de módulos activos inventariados por Core y el mismo endpoint de cada chequeo individual.
- Ejecución secuencial, un plugin cada vez, con avance global, recuento de incidencias y errores de ejecución, parada y reanudación cooperativas. Un fallo de comunicación pausa la cola con detalle visible; no se descarta silenciosamente ningún plugin.
- Conserva los informes, logs, controles y JSON de cada plugin; no inicia procesos en segundo plano, no ejecuta operaciones destructivas ni modifica otros módulos.

## [0.2.42] — 2026-11-16 — Estado y logs de validación individual recuperables (#2748)

- El chequeo de Billing/Facturación y otros autochecks que devuelven directamente una lista de filas se normaliza correctamente; no se clasifican como informe inválido.
- Los chequeos por plugin generan historial de ejecución acotado (inicio, lotes, errores, parada y reanudación), consultable en «Ver resultados» y en el JSON propio. Los fallos funcionales y los avisos de código conservan su detalle.
- Mejoras para móvil: avance visible en la fila del plugin, botón «Parar/Reanudar» en la misma fila y mensaje comprensible cuando la respuesta AJAX no es JSON válido (por avisos PHP o HTML del servidor).
- Se recupera la última selección tras recargar la página. La parada es cooperativa y un lote completado no reactiva un proceso detenido; una nueva ejecución no puede ser sobrescrita por la respuesta de un lote antiguo.
- Los chequeos del usuario se muestran en System Check → Procesos como tareas AJAX gestionadas por el navegador, identificadas expresamente como tales y sin confundirse con procesos PHP activos ni Action Scheduler. Desde Procesos también se puede solicitar la parada.
- No se cambia el motor de autocheck de Core ni otros plugins, no se ejecutan operaciones reales, no se modifica el monolítico ni se toca producción. Pruebas de regresión y compatibilidad WordPress/Plugin Check.

## [0.2.41] — 2026-11-16 — Chequeo individual por plugin y detalle de incidencias (#2742)

- La pestaña Plugin Validation permite ejecutar el autocheck del plugin seleccionado en su propia fila, con resultados independientes por administrador, sitio y plugin.
- Reutiliza los motores nativos de Autocheck de funcionalidades y código cuando están disponibles, respetando las pruebas UAT de escritura que no deben ejecutarse; si no hay adaptador funcional nativo, muestra claramente un chequeo básico, no una integración profunda ficticia.
- Los detalles indican cada prueba fallida con su mensaje y las incidencias de código con fichero, línea, regla y recomendación, distinguiendo errores confirmados de advertencias de análisis estático que exigen revisión humana.
- Se puede descargar un JSON completo del plugin seleccionado sin repetir chequeos. Escaneo de PHP en lotes con cinco velocidades de CPU; cancelación/reanudación entre peticiones.
- El piloto previo de Core permanece accesible en el desplegable de compatibilidad. No cambia el código ni el comportamiento de otros plugins ni ejecuta reparaciones.

## [0.2.40] — 2026-11-15 — Evitar fatal durante actualizaciones FTPS parciales (#2711)

- Corrección urgente del error `Class TaxoCommerce_System_Check_Process_Details not found` observado en STAGING al abrir Procesos.
- La pantalla carga el módulo de diagnóstico bajo demanda si existe; si aún no ha llegado durante una transferencia incremental, continúa mostrando los indicadores y controles de cancelación anteriores con un aviso de diagnóstico temporalmente no disponible, sin error fatal.
- La carga inicial del plugin tolera temporalmente la ausencia del archivo hasta completar la actualización. No cambia las protecciones de paradas ni las consultas de Action Scheduler y WP-Cron.
- Prueba de regresión que simula la llegada no atómica de archivos (primero el gestor y después el módulo de detalles) y verifica la recuperación. No se despliega a producción.

## [0.2.39] — 2026-11-15 — Diagnóstico y logs por tarea en Procesos (#2699)

- Procesos incorpora una columna de diagnóstico para tareas de WP-Cron, Action Scheduler y trabajadores de larga duración. Cada consulta detallada está limitada a una tarea y requiere permisos de administrador y nonce.
- Los logs nativos de Action Scheduler se muestran bajo demanda (últimas 20 entradas) y se ocultan valores que parezcan credenciales. Enlace al último intento fallido desde el resumen de colas.
- Señal de posible tarea huérfana cuando el hook no tiene callback registrado en la petición actual; no confundir prefijos `seo_` con dependencia demostrada del antiguo monolítico.
- WP-Cron no conserva historial individual por defecto; no se inventan causas de fallos ni se exponen los argumentos de los jobs.
- Se mantienen las protecciones de cancelación y paradas cooperativas; ningún kill de PHP/SO ni ejecución arbitraria de hooks. Se corrigen la firma de cancelación individual WP-Cron (botón y validador diferían) y la normalización de identificadores `export:usuario:uuid` para permitir la parada cooperativa de exportaciones ya soportada.
- Pruebas de regresión de logs, lectura, control de acceso, máscara de credenciales y colas. Sin cambios en Sistema, plugins ajenos o producción.

## [0.2.38] — 2026-11-15 — Diagnóstico de colas en Procesos (#2694)

- Amplía **solo la pestaña «Procesos»** con un bloque de diagnóstico WP-Cron y Action Scheduler de solo lectura. «Sistema», «MySQL» y «Servidor» permanecen intactos.
- WP-Cron: hook y antigüedad del evento que acumula el mayor retraso (más de 15 minutos), y aviso de dependencia de un cron externo si está desactivado WP-Cron nativo.
- Action Scheduler: tarea pendiente más antigua, último intento fallido (hook y fecha), número de tareas completadas/fallidas durante la última hora y distinción entre cola atrasada con actividad, errores recientes o ausencia de actividad concluyente.
- No se deduce falsamente que el ejecutor esté detenido. No se mide duración de PHP si el motor no la expone. No se ejecutan ni modifican tareas, ni se amplían los permisos de cancelación preexistentes.
- Los procesos de auditoría HTTP muestran además el límite efectivo de URLs por lote (1–8), el progreso registrado y la posición orientativa de la siguiente URL pendiente, también tras una pausa. No cambia la selección, el orden, la reanudación ni la cancelación del motor.
- Tres consultas de lectura acotadas sobre Action Scheduler, sin dependencias entre plugins. Pruebas aisladas y actualización de catálogos ES/EN/FR/DE/NL.

## [0.2.37] — 2026-11-14 — Piloto de validación de familia con carga adaptativa (#2685)

- Plugin Validation añade indicadores separados de funcionalidades y código y un orquestador manual de cinco velocidades.
- La primera integración ejecuta casos de lectura del autocheck Core por lotes y después reutiliza el escáner de código del propio Core por archivos. No ejecuta el test de escritura UAT.
- Pausa, reanudación, resultados parciales por usuario y ajuste adaptativo con tiempo, memoria y CPU PHP disponible. Los demás módulos figuran como no integrados hasta su validación.
- Seguridad: capacidad manage_options y nonce por acción AJAX. Sin tareas automáticas, sin alteración del monolítico ni de los motores de otros plugins.
- Prueba de regresión: tests/family-runner-pilot-smoke.php. Pendientes validación en WordPress real y extensión a los demás módulos.

## [0.2.36] — 2026-11-13 — Code Check homogéneo con Core 0.4.82 (#2626)

- Code Check añade las reglas nativas de Core 0.4.82, clasificación por errores/avisos/limpios/no verificados, recomendaciones y JSON, conservando sus chequeos especiales de PHP mínimo, rutas, duplicidades y seguridad y sin modificar el Autocheck funcional. Issue #2626.
- Las reglas se ejecutan dentro del propio plugin, sin depender de Core, Plugin Check ni de un servidor externo. Conserva el procesamiento por lotes y la descarga del informe técnico previo.

## [0.2.35] — 2026-11-13 — Completar los cinco catálogos de System Check (#2062)

- Completa las 356 entradas gettext que estaban vacías: 61 ES, 110 EN, 62 FR, 62 DE y 61 NL. Audita llamadas gettext en los 23 archivos PHP e incorpora 66 cadenas adicionales que faltaban en el POT (330 traducciones nuevas), principalmente de Autocheck, Procesos y cabecera. Los cinco PO quedan con 420 claves, sin vacíos, iguales al POT.
- Traduce mensajes de diagnósticos, seguridad, registros PHP, permisos, enlaces, auditoría y guías. Los nombres técnicos y los parámetros se conservan; en las traducciones se evitan referencias heredadas a «SEO System» y cifras exclusivas de una instalación.
- Recompila los cinco MO con 421 entradas por idioma, contando cabecera, y sincroniza la versión del POT. La prueba local verifica claves, valores, offsets y contenido de MO en los cinco idiomas.
- No modifica consultas, auditorías, datos, ajustes, eventos, interfaces ni dependencias. La cobertura completa de textos PHP/JS no incluidos en gettext y la prueba real en WordPress de los cinco idiomas siguen pendientes en #2062.

## [0.2.34] — 2026-11-12 — Traducciones de diagnóstico, registros y MySQL (#2062)

- Traduce 67 textos administrativos adicionales en cada uno de los cinco idiomas ES/EN/FR/DE/NL (335 entradas gettext), incluidos controles de servidor, configuración SQL, recursos, registros PHP, estados e informes.
- Reconstruye los cinco archivos MO respetando los índices y la cabecera GNU gettext, omitiendo cadenas vacías para conservar el fallback de WordPress.
- Mantiene intactos los chequeos, servicios, datos, permisos, operaciones de red y lógica del plugin. Amplía la comprobación offline de textos de interfaz en Auto Test.
- Todavía quedan 61/110/62/62/61 entradas PO vacías en ES/EN/FR/DE/NL; no se declara completada la internacionalización ni validado cada literal PHP/JS.

## [0.2.33] — 2026-11-12 — Traducciones parciales y sincronización gettext (#2062)

- Completa 45 etiquetas y mensajes adicionales en cada idioma ES/EN/FR/DE/NL: estados, informes, servidor, MySQL, seguridad y ejecución de comprobaciones (225 traducciones).
- Recupera 47 claves gettext ausentes del catálogo en_US y sincroniza los cinco catálogos con 354 claves; incorpora 5 claves que faltaban en la plantilla POT.
- Regenera los cinco archivos MO con cabecera gettext y 354 entradas traducibles por idioma, conservando los textos existentes.
- Cambios limitados a catálogos, versión, documentación e inventario; no modifica los diagnósticos ni declara completada la internacionalización de la interfaz. Quedan cadenas vacías y requieren revisión los textos que no usan gettext.

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


### Archive of former readme.txt descriptive and historical notes (preserved, 2026-11-18)

The following text is preserved verbatim from the previous public readme for historical traceability; it is not current installation guidance.

```text
=== TaxoCommerce System Check ===
Contributors: davidperezmartorell
Tags: diagnostics, security, performance, maintenance, monitoring
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.2.43
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Server, WordPress and plugin diagnostics with administrator-controlled reports and local checks.

== Description ==

Version 0.2.43 adds a Check all plugins button next to CPU usage in Plugin Validation. It runs the active modules registered by Core sequentially through the existing per-plugin AJAX diagnostics; progress, cooperative Stop/Resume, individual results, logs and JSON remain available. Execution runs in the administrator browser, not a background scheduler.

Version 0.2.42 fixes individual-plugin result formats (including Billing), provides per-row progress and Stop/Resume controls, records bounded execution events, and identifies browser-driven checks in Processes. Diagnostics remain read-only; no active PHP request is forcibly terminated.

Version 0.2.41 adds per-plugin diagnostics in Plugin Validation: select an active plugin, run available read-only native functional and code checks, inspect specific failures and file-level review findings, and export a JSON report without rerunning. If a plugin does not expose deep functional tests, only basic availability checks are claimed. No content writes or full official WordPress Plugin Check are performed.

Version 0.2.40 prevents a fatal error when the Processes diagnostic class is temporarily unavailable during an incremental FTPS update. The previous process list and controls remain usable until the transfer finishes.

Version 0.2.39 adds administrator-only, on-demand job diagnostics inside Processes. Each Action Scheduler task has a bounded, redacted log view; WP-Cron jobs show callback availability without inventing execution history. Existing protected cancellation and cooperative stop remain unchanged, with the WP-Cron event signature corrected. No general PHP kill or arbitrary run-now action is provided.

Version 0.2.38 adds read-only queue diagnostics **inside Procesos**, above the existing application-task manager: oldest overdue WP-Cron event, oldest pending Action Scheduler hook, latest failed attempt, activity during the last hour and cautious interpretation of backlogs versus recurring errors. Reads are limited, neither schedule nor cancel tasks, and do not infer that a runner is stopped from a single snapshot. No background execution duration is recorded or displayed. Active local HTTP audit workers additionally show the configured 1–8 URL batch size, completed/total count and the approximate next pending position (including paused runs). These are progress indicators, not an exposed stored cursor or a promise that a stopped job will resume. The Sistema/MySQL/Servidor sections and existing cancellation permissions are unchanged.


Version 0.2.37 introduces a manual, phased family validation pilot for Core: read-only functionality cases followed by code-file inspection, with five adaptive workload settings, pause/resume and separate KPI columns. Other active modules remain clearly not integrated. Runs are never started by opening the page.

Version 0.2.35 completes all 420 entries in each of the five existing gettext catalogs (es_ES, en_US, fr_FR, de_DE and nl_NL), including administrative controls, diagnostic messages and explanatory text. WordPress uses the active site/user locale. A full source-code audit for strings outside gettext and end-to-end language validation remain necessary before claiming full interface localization.


## Actualización 0.2.33 — idiomas

Se han ampliado las traducciones locales en es_ES, en_US, fr_FR, de_DE y nl_NL. El catálogo de inglés incorpora las claves que le faltaban y se han regenerado los archivos MO. La cobertura completa de la interfaz y la carga efectiva de traducciones siguen pendientes de validación.


* Admin header: plugin title, a single local `Description` with the static `Plugin URI` link below it (left); a wider collapsed `CHANGELOG.md` history (center); local version and release date (right). No duplicated information panel or external requests.

* The admin header shows the plugin's bundled `Description` and links to its `Plugin URI`, without network requests or caching. Version history uses the bundled `CHANGELOG.md`.



TaxoCommerce System Check provides a standalone administration entry for diagnostics, and can integrate its menu with TaxoCommerce Core when installed.

* WordPress, PHP and hosting/system status screens.
* Processes tab: read and cancel eligible application jobs from Action Scheduler/WP-Cron, with optional cooperative controls for installed workers. Does not kill operating-system or running PHP processes.
* Plugin validation and local PHP checks.
* Autocheck is a single main tab with two subtabs: Functionality Check (the original on-demand functional tests and JSON) and Code Check (the native source audit and its own JSON).
* The Code Check subtab inspects only System Check's own PHP files. It performs native PHP syntax parsing, checks known PHP 8 features against the declared PHP minimum, verifies resolvable local include paths, detects duplicate named declarations, checks selected WordPress structure conventions, and flags potential nonce, capability, input, output, REST and SQL risks for manual review. No other plugin is required. It displays counts by category/status and actionable file findings (including missing structure items and suspicious call locations), offers five bounded CPU speeds, stop/resume and private JSON export. It requires no other checker plugin, WP-CLI, GitHub or process-execution functions. The local rules are not the official WordPress Plugin Check, and untested security/publication rules remain explicitly unverified.
* Optional HTTP URL audit of site content, when started explicitly by an administrator.
* On-demand diagnostic reports and optional administrator-configured notification emails.
* Dedicated bounded processing for longer URL checks. Neither external GitHub runners nor artificial traffic generation are necessary.
* Historical administration URLs are kept for compatibility.

Some historical diagnostic callbacks are reused when the old TaxoCommerce SEO plugin is still present. Standalone behavior must be tested with the old plugin disabled before publication.

== Installation ==

1. Install and activate TaxoCommerce System Check.
2. Open System Check in WordPress administration (or the Core submenu if Core is installed).
3. Run a system or plugin check and review the findings.
4. Start URL audits or enable scheduled email reports only when you intend to use them.

== Privacy ==

System diagnostics can include server configuration, URLs and error details. Treat exported results as potentially sensitive. No diagnostic report should be sent to a developer automatically: optional email reports must be explicitly enabled and target an address chosen by the site administrator. HTTP checks make requests to the site's specified URLs and must be used within hosting limits.

== Changelog ==

= 0.2.30 =
* Local Description and Plugin URI replace REST excerpt requests and caches in the header. Issue #2494.


= 0.2.29 =
* Display the last verified official excerpt from the developer's Plugin URI during HTTP errors; avoid stale negative cache and show bundled author Description on first-install network failure. No dependency on customer content. Issue #2489.



= 0.2.28 =
* Three-zone responsive admin header, centered official developer information and own Markdown history; remove shop administrator URL configuration. Issue #2476.

= 0.2.25 =
* Move Code Check into the main Autocheck tab as a second subtab, preserving the functional Autotest and the deeper standalone code audit.
* Preserve legacy Code Check links and keep server diagnostics in the separate System tab.


= 0.2.24 =
* Collapsed history directly under the admin title, reading the bundled CHANGELOG.md.


= 0.2.23 =
* Expand standalone Code Check with local include integrity, duplicate class/function detection, PHP minimum-version indicators and security review signals (nonces, permissions, escaping, sanitization, SQL and REST).
* Preserve three existing categories, 5 CPU speeds, stop/resume, and private JSON. Unverified flows, older PHP compatibility and official WordPress Plugin Check remain explicitly unverified.


= 0.2.22 =
* Separate Autocheck (existing functionality tests) and Code Check (own-source PHP/WordPress/security review) into two primary administration tabs. Preserve old Code Check links and all report actions.
* Add category-level counts and actionable per-file evidence in Code Check; do not present local static checks as official WordPress Plugin Check certification. No mandatory checker dependencies or schema changes.


= 0.2.21 =
* Remove duplicate admin GET parsing and narrowly document the nonce-free, read-only asset selector to pass strict WordPress Plugin Check.
* Preserve the two Autocheck subtabs (Functionality Check and Code Check) and their native, independent PHP/WordPress/security checks.


= 0.2.20 =
* Split Autocheck into Functionality Check and Code Check subtabs, both only for System Check itself.
* Add three native, read-only code checks (PHP syntax, WordPress conventions, security patterns) without installing or invoking any other checker.
* Code Check supports five CPU speeds, stop/resume and a user-scoped JSON report. Complex rule coverage remains explicitly unverified.


= 0.2.18 =
* Add a bounded, administrator-only Processes tab with application-task inventory, protected selective cancellation and cooperative stop controls. Preserve WooCommerce critical jobs and never claim to terminate active PHP or operating-system processes.


= 0.2.17 =
* Keep the most recent Autocheck JSON download available on returning to the tab, until the user- and site-scoped report expires. Export does not rerun tests.
* Validate PHP diagnostic rows, active module results, HTTP callback/schedule contracts and JSON serialization. Include structured evidence and executed/total coverage in Autocheck output.
* Read the last saved server snapshot without starting another scan: missing or older than 24 hours is reported as not executed, malformed data as failure. Environment warnings do not imply a broken plugin.

= 0.2.16 =
* Load bundled server diagnostics when legacy SEO is installed but inactive, or active without its Server Status module; avoid duplicate headers and reloading existing global engines.


= 0.2.15 =
* Pestaña homogénea «Autocheck» y descarga JSON del último informe, por usuario y sitio, con control de permisos y nonce.

= 0.2.14 =
* Cabecera administrativa unificada de TaxoCommerce con nombre, icono y versión.
* Estilos propios sin Core, funciones y permisos intactos.


= 0.2.13 =
* Bind both Action Scheduler table identifiers using %i when retrieving the highest-volume pending action group.
* Add source-regression checks to prevent restoring interpolated SQL and synchronize the standalone Auto Test version.
* Preserve live diagnostics and all existing historical callbacks; production publication requires separate approval.

= 0.2.12 =
* Fix remaining Plugin Check SQL preparation findings by binding canonical table identifiers and preserving bound user values in billing, semantic checks, health scans and external images.
* Document narrowly scoped live database diagnostics without introducing stale caches or changing public legacy SEO APIs.
* Add regression assertions and validate installable ZIP with WordPress Plugin Check before release.


= 0.2.11 =
* Prepare the canonical table identifiers in all eight MySQL EXPLAIN diagnostics with the WordPress 6.2+ %i placeholder.
* Extend offline SQL and malformed-input safety tests to PHP 7.4 and PHP 8.4; ignore array-valued HTTP audit filters and actions without changing valid requests or legacy public APIs.
* This patch does not claim to resolve all remaining WordPress Plugin Check warnings; the compatibility and direct-database warnings continue to be tracked in GitHub issue #1958.


= 0.2.9 =
* Refresh WordPress.org metadata for the standalone System Check package.
* Confirm current gettext calls use the standalone text domain and Action Scheduler table identifiers use prepared `%i` placeholders.

= 0.2.8 =
* Standalone system and plugin diagnostics with compatibility routes.
* Local PHP checks and optional bounded HTTP URL audits.
* Administrator-controlled reports and notifications.

== Historical origins (monolith; not standalone releases) ==
* Legacy origin: server diagnostics, plugin validation and HTTP/URL checks existed under TaxoCommerce SEO administration; the monolith's release numbers are not standalone System Check versions.
* The old validation panel mixed site status, plugin-specific checks and URL/HTTP observations; its historical outputs were not automatically proof that newer modules had passed.
* During modularisation System Check was isolated from SEO_SYSTEM_* globals and constants to prevent collisions with the still-installed monolith.
* Standalone diagnostics distinguished the active plugin modules from installed but inactive extensions and retained administrator-controlled bounded scans and reports.
* Later WordPress.org work corrected text-domain usage and prepared SQL identifiers; the historical original checker remained distinct from each plugin's own Auto Test.

== Local plugin information ==

The developer information panel shows only bundled `Description` metadata and an outbound `Plugin URI` link. No external request is made.

```
