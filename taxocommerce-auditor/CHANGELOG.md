## [0.3.20] — 2026-11-10 — Descripción visible desde el plugin (#2494)

- La descripción inmediatamente debajo del título administrativo procede de `Description:` del archivo principal, sin textos duplicados ni consultas externas.
- Se mantienen el enlace estático `Plugin URI`, el historial local y las funcionalidades anteriores.

## [0.3.19] - 2026-11-10 - Cabecera local sin peticiones REST (#2494)

- El encabezado utiliza solo Description y Plugin URI del propio plugin, sin HTTP ni caché de extractos.
- Se conserva íntegro el control de CPU de Auditor 0.3.18 y el historial de versiones.

## [0.3.18] - 2026-11-10 - CPU real del proceso PHP y techos de 40-500 (#2496, #2500)

- Amplía el regulador adaptativo de Auditor 0.3.17: los cinco niveles permiten hasta 40, 90, 180, 300 y 500 objetos, pero cada ejecución inicia con una consulta conservadora y solo aumenta cuando tiempo, memoria y CPU del trabajador PHP dejan margen.
- Si getrusage() está disponible, la CPU de usuario y sistema del proceso PHP se mide en milisegundos por lote y también influye en el ajuste de frecuencia del navegador. No se presenta ni se infiere la CPU global del alojamiento compartido.
- Mantiene el límite temporal de cada nivel, un solo lote AJAX por petición, los cursores e índices persistidos, pausas, JSONL completo y bloqueo de concurrencia. La consulta y el umbral de finalización mantienen el mismo tamaño efectivo.
- El texto de progreso deja de mencionar WP-Cron; las comunicaciones fallidas siguen siendo diagnosticables, y se conserva WP-Cron exclusivamente como mecanismo auxiliar opcional.
- Ajustadas las pruebas PHP/JS, Autocheck, manual, documentación, versión y traducciones. No hay modificaciones de contenidos ni de otros plugins.

## [0.3.17] — 2026-11-10 — Control adaptativo de carga y diagnóstico sin WP-Cron (#2498)

- Eliminados de la pantalla de Auditor los avisos amarillos por WP-Cron desactivado o retrasado: no gobiernan la cadena AJAX y daban una impresión equivocada de bloqueo.
- Se conserva el aviso real de error de red, HTTP, nonce o procesamiento, con reintentos controlados. WP-Cron sigue siendo solo una alternativa opcional para la ejecución con la pestaña cerrada.
- El controlador de carga mide la duración completa de cada lote (incluidas las consultas de WordPress) y estima la presión de memoria PHP cuando se conoce su límite. Ajusta el tamaño del lote siguiente en el rango seguro de la velocidad 1–5 elegida, sin reiniciar cursor ni JSONL.
- La interfaz ajusta también automáticamente la pausa entre respuestas según duración del lote, latencia observada y memoria: acelera en lotes ligeros y retrocede cuando están cerca de su presupuesto o con presión elevada. Los niveles 1–5 siguen siendo límites superiores, no porcentajes de CPU.
- Se muestra el tamaño previsto del lote y la duración anterior como diagnóstico no intrusivo. Auditor no puede conocer el porcentaje global real de CPU en un alojamiento compartido.
- Autotest, pruebas PHP y JavaScript de lotes rápidos/lentos, categorías, cron deshabilitado, pausa y reintentos; manual y traducciones nativas ES/EN/FR/DE/NL actualizados.
- Issue #2498; no modifica catálogo, precios, stock, contenidos ni relaciones comerciales.

## [0.3.16] - 2026-11-10 - Resiliencia del extracto oficial (#2489)

- Consulta exclusivamente la API REST pública de la página indicada en Plugin URI (autor del plugin), nunca páginas de la tienda del cliente.
- Conserva el último extracto oficial comprobado; los fallos de red no borran el texto. Caché de errores reducida a 5 minutos y versión nueva de clave para ignorar fallos antiguos cacheados.
- Si nunca se ha obtenido un extracto, muestra provisionalmente la Description incluida por el autor en el plugin; permite abrir la ficha oficial. No cambia operaciones comerciales ni requiere Core.
- Pruebas para recuperación tras fallos HTTP, JSON inválido y caché negativa; compatibles con la familia completa.

## [0.3.15] — 2026-11-09 — Ficha oficial del desarrollador y cabecera centrada en tres zonas (#2476)

- La cabecera distribuye identificación a la izquierda, dos desplegables alineados y centrados (extracto oficial a la izquierda e historial local a la derecha), y versión y fecha a la derecha; distribución adaptable a móvil.
- El enlace oficial se obtiene exclusivamente de Plugin URI, definido por el autor del plugin. Eliminados el formulario de URL y su acción de guardado; los ajustes antiguos de la tienda no pueden sobrescribirlo.
- El historial se lee del CHANGELOG.md incluido, plegado de inicio. Sin dependencias obligatorias entre plugins ni cambios en productos, pedidos, datos o funcionalidades existentes.

## [0.3.14] — 2026-11-09 — Auditoría automática sin botón Siguiente lote (#2475)

- Retirado «Procesar siguiente lote» de las cinco pestañas de auditoría; cada respuesta AJAX continúa automáticamente la cola con el control independiente de cinco velocidades, presupuesto por petición y retroceso en caso de error.
- El monitor AJAX se encola también desde la propia pantalla de Auditor, además del hook habitual de administración. Esto evita que una integración con un menú externo impida cargar el trabajador de navegador.
- Mientras una auditoría esté en curso, en cola o pausada, no se ofrece iniciar otra del mismo ámbito. Las peticiones de inicio procedentes de pestañas antiguas vuelven al estado vigente sin descartar el cursor ni los archivos JSONL.
- Se mantienen Pausar/Reanudar, el handler manual legado para compatibilidad, las cinco velocidades, bloqueo atómico, controles de permisos y nonce, exportaciones y WP-Cron auxiliar; no hay dependencia obligatoria entre plugins.
- Los fallos de AJAX muestran ahora el error HTTP u origen de conexión y se reintentan con espera progresiva. Incluye prueba JavaScript de lotes encadenados, renovaciones de nonce, pausa, reintentos y prueba PHP para verificar ausencia del botón en todos los ámbitos.
- Con la pantalla cerrada o suspendida, la continuidad requiere un trabajador del servidor: ningún script de navegador puede garantizar ejecución en segundo plano.

## [0.3.13] — 2026-11-09 — Auditoría continua AJAX independiente de WP-Cron (#2444)

- Productos, categorías, entradas, páginas y FAQ: el navegador encadena lotes AJAX autenticados y acotados sin esperar a WP-Cron ni exigir pulsaciones manuales entre lotes.
- Cinco velocidades independientes con tamaños de 5/10/20/30/40 objetos, presupuestos de 2/3/5/8/12 segundos y pausas de 6000/3500/2000/1000/300 ms; no representan cuotas de CPU garantizadas.
- Conservadas pausa, reanudación, bloqueos atómicos, cursores de progreso, exportación JSONL y WP-Cron como respaldo opcional; con el navegador cerrado debe existir un trabajador de servidor.
- Nuevo Autotest y prueba funcional de continuidad, incluida corrección de la prueba de velocidad; mantiene los cambios de 0.3.12 de la ficha oficial.

## [0.3.12] — 2026-11-08 — Extracto dinámico desde la página oficial (#2443)

- La cabecera obtiene el excerpt de la página oficial definida por Plugin URI, sin copiar textos al plugin ni depender de Core. Puede personalizarse o desactivarse desde cada instalación.
- Corregida la lectura/sanitización del URL y la compatibilidad de los chequeos estáticos. Caché y fallback cuando la fuente no responde. Historial CHANGELOG.md intacto.

## [0.3.11] — 2026-11-08 — Extracto comercial remoto configurable en cabecera (#2443)

- Desplegable izquierdo para obtener el extracto público de una página WordPress oficial, únicamente cuando el administrador configura su URL HTTPS, con enlace a la ficha de origen.
- Desplegable derecho de historial local, independiente del servicio externo. Validación y escape, límite temporal de petición y caché para evitar demoras.
- Sin dependencia de otros plugins, sin origen comercial predefinido y sin modificación de funcionalidades existentes.
## [0.3.11] — 2026-11-08 — Recuperación de auditorías detenidas y diagnóstico WP-Cron (#2444)

- Mantiene WP-Cron como motor de fondo y añade una recuperación por AJAX autenticado mientras la pestaña de Auditor esté abierta; cubre los cinco ámbitos, con las cinco velocidades configurables.
- Las auditorías largas muestran progreso actualizado, hora del último lote, próximo evento WP-Cron y avisos si el programador está retrasado o deshabilitado.
- Recupera lotes vencidos sin reiniciar el cursor ni el archivo JSONL; el bloqueo atómico por ámbito evita que el mismo lote sea procesado por WP-Cron y AJAX simultáneamente.
- El monitor respeta la velocidad de cada ámbito, ejecuta un solo lote por petición y aplica espera progresiva cuando la conexión falla. Se conserva el botón manual, pausa, reanudación, filtros y exportaciones.
- No depende de Action Scheduler, otros plugins ni servicios externos. La ejecución con el navegador cerrado sigue requiriendo WP-Cron o una tarea cron real del alojamiento.
- Autocheck, pruebas de regresión, documentación y catálogos de idioma actualizados; el Issue #2444 se cierra únicamente después de validación funcional en STAGING y Focazul.

## [0.3.10] - 2026-11-08 - Autotest de funcionalidad y codigo (#2401)

- Autotest mantiene sus pruebas funcionales y su JSON; incorpora una subpestana Code Check propia e independiente.
- PHP, WordPress y seguridad con lotes, 5 limites de CPU, detener/reanudar y exportacion JSON privada. PCP no obligatorio.
- El analizador procede de una plantilla comun vendorizada y solo lee archivos del propio plugin.
## [0.3.10] — 2026-11-08 — Cinco velocidades de auditoría por ámbito (#2405)

- Control de carga propio e independiente en productos, categorías, entradas, páginas y FAQ, con niveles 1–5 por pestaña. No necesita Core, Images ni otros plugins.
- El control ajusta los elementos por lote (5/10/20/30/40), separación WP-Cron (90/60/35/20/12 segundos) y presupuesto máximo para análisis por lote (2/3/5/8/12 segundos); no pretende medir ni reservar un porcentaje de la CPU del alojamiento.
- Configuración protegida con manage_options y nonce, sin reiniciar cursor, muestras o fichero JSONL. Se conserva la reanudación, el modo completo y filtrado y el paso manual.
- Cursor de categorías avanza únicamente por elementos procesados cuando un lote se corta por tiempo; el fin de cola se calcula con el límite vigente al abrir la consulta, evitando falsos finales al cambiar la velocidad.
- Contratos Autocheck y prueba de regresión ampliados, sin tareas programadas durante Autocheck.

## [0.3.9] — 2026-11-08 — Historial plegable en cabecera (#2382)

- Desplegable cerrado por defecto inmediatamente debajo del título, con el historial propio de `CHANGELOG.md`; sin nueva pestaña ni dependencia de otros plugins.
- Textos del título preparados para ES/EN/FR/DE/NL, salida escapada, desplazamiento vertical y mantenimiento de menús y funciones existentes.
- El paquete instalable y la subida incremental incluyen únicamente el historial raíz necesario. Sin cambios en base de datos ni servicios.

## [0.3.8] — 2026-11-07 — Cabecera administrativa de versión y fecha (#2336)

- Muestra título, versión activa y fecha de publicación en la cabecera del administrador mediante metadatos locales (Release Date).
- La fecha no depende de la hora de consulta ni del momento de copia por FTP. No altera servicios, datos, menús ni permisos.
- Se conserva el funcionamiento independiente y se comprueba con el test de cabeceras de la familia.

## [0.3.7] — 2026-11-07 — Registro Core sin traducciones prematuras (#2330)

- Registro de metadatos de este plugin en TaxoCommerce Core pospuesto a `init` prioridad 11 cuando Core solicita los datos durante `plugins_loaded`.
- Conserva el registro de módulos, traducciones por locale, menús, acciones, contenido y funcionamiento independiente sin modificar Core.
- Evita el aviso de WordPress 6.7+ `_load_textdomain_just_in_time` causado por la descripción traducida del módulo.
- La desaparición del aviso requiere confirmación en STAGING tras desplegar.

## [Unreleased] — Regional WordPress locale fallback (#2295)

- Respeta primero los paquetes oficiales de WordPress .mo y .l10n.php para el locale exacto.
- Si falta un catálogo local regional, usa el catálogo existente del mismo idioma (es_ES, en_US, fr_FR, de_DE o nl_NL); no cambia la configuración ni el idioma del usuario.
- Sin cambios en lógica, datos, menús o dependencias del plugin. Las traducciones de contenido incompletas siguen abiertas en #2062.

## [Unreleased] — Auditor translation quality (#2257)

- Corrected mistranslations in English, French and German manuals, including paused audits, processing batches and WordPress security nonces.
- Rebuilt the corresponding native GNU gettext MO catalogs without changing audit logic or stored data.
- Preserved WordPress locale selection and the existing five-language catalogs.

## [0.3.6] — 2026-11-06 — Internacionalización Auditor (#2062)

- Revisión de textos visibles, botones, estados, pestañas, informes, KPI, autochequeo, manual y mensajes de reglas con gettext nativo de WordPress.
- Conservados los identificadores de API, filtros, CSS, SQL, las funciones de auditoría y los formatos de exportación JSON.
- Actualizados los diccionarios por idioma y las pruebas de regresión. Cambios exclusivamente en TaxoCommerce Auditor.

## Unreleased - Familia i18n #2062


## [0.3.5] — 2026-11-03

- Nombre uniforme «Autocheck» y descarga autenticada del último informe JSON, con resultados por comprobación y sin ejecutar operaciones reales.
- Issue #2150; PR #2151. Mantiene las rutas internas existentes y la ejecución no destructiva.

- Dominio gettext, carga de catalogos locales, etiquetas de menús y textos HTML de salida estáticos; catálogos PO/MO sincronizados con el texto fuente.

# CHANGELOG — TaxoCommerce Auditor - Content Quality for WooCommerce

## [0.3.4] — 2026-11-03

- Cabecera común basada en TaxoCommerce Core: identidad, icono, descripción y versión con estilo responsive.
- Enlaces, pestañas, permisos y lógica interna conservados; implementación autónoma sin requerir Core. Issue #2137.

> Historial de las versiones del plugin **independiente**. Las funciones anteriores a su separación están documentadas en [`changelog.txt`](changelog.txt).
> Fuente inicial: entradas verificadas del `readme.txt` en GitHub. Solo se atribuye una fecha si la fuente la acredita.

## [Unreleased]

Sin novedades pendientes documentadas.

## [0.3.3] - 2026-11-01

* Consolidada la publicación independiente actualizada tras el informe de Plugin Check de Playground (2 errores y 6 avisos correspondientes a una revisión antigua).
* Confirmado el escape de errores de términos, `suppress_filters=false`, validación y saneamiento de los tres filtros `POST`, y el máximo de cinco etiquetas del `readme.txt`.
* Documentados con excepciones PHPCS específicas y justificadas los dos filtros `tax_query`: lotes de hasta 40 IDs por cursor y muestra exacta de hasta 201 IDs solo para categorías de como máximo 200 productos. Se mantienen filtros, límites, prioridades, contenido y solo lectura.
* Añadidas pruebas de regresión del código distribuible para impedir reintroducir los ocho hallazgos, sin modificar el monolito ni desplegar PRO. Referencias: Issue #2011.
* Revisadas las 11 consultas directas preparadas a las tablas canónicas de FAQs/Comparador/Dependiente/Suppliers; se justifican puntualmente los avisos DirectQuery/NoCaching donde la lectura debe ser fresca y no existe API de datos WordPress equivalente. Sin caches persistentes ni cambios en consultas. Issue #2021.


## [0.3.2]

* Incorporadas las pestañas Auto Test y Manual dentro del menú existente de Auditor, después de los cinco ámbitos de contenido.
* Auto Test de 16 pruebas agrupadas: servicios, hooks, productos, categorías, FAQs, reglas, prioridades, filtros, lotes, contratos JSONL, estados, informes, historial y documentación; no se ejecutan trabajos, escrituras ni exportaciones reales.
* Dos resultados por administrador en transitorio durante 30 días, con fecha, versión, duración, estados y comparación de regresiones.
* Manual integrado de usuario y técnico: cinco ámbitos, KPIs, filtros, auditoría completa, tareas WP-Cron, prioridades, vistas del informe, JSONL, accesos y seguridad.
* Compatibilidad con las rutas y las reglas anteriores, sin cambios en las auditorías, exportaciones ni datos de negocio; smoke tests PHP 7.4/8.4 y exclusión de tests del ZIP.


## [0.3.1]

* Refresh WordPress.org readiness against the current standalone Auditor code.
* Keep large read-only audit exports streamed from bounded local JSONL storage.

## [0.3.0]

* Added complete and filtered audits with full JSON exports.
* Added per-scope KPI panels and prioritised findings.
* Added optional locally stored decision context and native FAQ auditing.

## [0.2.0]

* Restore the original Auditor interface and legacy quality checks using native WordPress/WooCommerce entities across five audit domains.

## [0.1.0]

* Initial independent five-domain Auditor with bounded scans, native catalogue data and Vocabulary compatibility.

## Referencias

- [Origen monolítico y transición](changelog.txt)
- [Índice de evidencias de los 20 plugins](../docs/PLUGIN-HISTORY-SOURCES.md)

## Mantenimiento

Este es el historial maestro que se actualiza al introducir novedades. Antes de publicar cada versión en WordPress.org, su resumen debe trasladarse al apartado `== Changelog ==` de `readme.txt`; WordPress.org no presenta este Markdown automáticamente.
