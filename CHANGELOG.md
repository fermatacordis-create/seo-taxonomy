## 2026-11-10 - Import-Export 0.4.29: eliminar programación de colas propias (#2530)

- Quitadas las llamadas que creaban tareas programadas de importación/exportación en Action Scheduler y WP-Cron. Se mantiene la secuencia HTTP interna con regulador de carga, los controles y el watchdog activado con el tráfico.
- Se cancelan únicamente acciones programadas antiguas de los dos hooks del plugin. No se borran CSV, estados, ni otras tareas del sitio.
- El rastreador de proveedores heredado sigue usando seo_supplier_crawl_tick para avanzar: no se modifica al no existir sustituto funcional validado.
- Actualizados contrato de regresión, versiones y documentación. Ningún despliegue a PRO antes del YAML.

## 2026-11-10 - Redirects 0.1.20: control de propiedad y conflicto de PR (#2485)

- Nueva casilla para activar o ceder la gestión de redirecciones. Activada por defecto; al desmarcar, el motor deja de responder rutas y reclamar la propiedad a OPNV, sin borrar reglas ni informes.
- Conserva la cabecera unificada de tres bloques incorporada previamente, junto al contrato de interoperabilidad de David (#2516), y añade las pruebas de propiedad activada/desactivada/reactivada.
- Resuelto el conflicto con los cambios de encabezado de la versión 0.1.19, manteniendo todas las entradas previas de historial. Sin desplegar en producción.

## 2026-11-10 - Ojeador heredado: exportación SQL con lista cerrada (#2487)

- Integrada la contribución de @fermatacordis-create para `includes/ojeador/class-ojeador-admin.php`: `stream_table_json()` limita los órdenes y filtros a valores autorizados y parametriza LIMIT/OFFSET.
- La tabla se valida como identificador alfanumérico compatible con WordPress 5.8-6.1, evitando exigir el marcador `%i` introducido en WordPress 6.2.
- Cambio exclusivo del plugin monolítico anterior; TaxoCommerce Scrappers ya dispone de protección equivalente y no se modifica. Sin seguimiento funcional adicional del monolito ni despliegue a producción.

## 2026-11-10 - Distribution: gestión opcional del sitemap XML (#2484)

- Añadida casilla propia en TaxoCommerce Distribution para activar o ceder la publicación XML; sin dependencia obligatoria de OPNV ni de Core.
- Mantiene los feeds comerciales, la publicación previa y el mecanismo de propiedad por filtros. Limpia las tareas de sitemap al desactivar, actualiza las reglas públicas y evita cambios con generaciones activas.
- Incorporadas pruebas de regresión sobre propiedad y cesión a WordPress para validar compatibilidad. Producción excluida de esta entrega.

## 2026-11-10 — Extractos oficiales resistentes a errores HTTP en los 20 plugins (#2489)

- La captura de una tienda cliente evidencia «Excerpt temporarily unavailable» pese a que la API REST pública de asociacionfocazul.com sirve la ficha oficial. Ningún cliente define URLs de documentación.
- Actualizada la plantilla común independiente y sus 20 copias: uso exclusivo de Plugin URI, nuevo espacio de caché para ignorar respuestas vacías antiguas, TTL de fallos de 5 minutos, almacenamiento local del último texto remoto validado y alternativa desde la Description incluida por el desarrollador mientras falla Internet.
- Extendido el test común para errores 503, JSON malformado, recuperación, caducidad transitoria y protección contra sustitución de contenido por la web del cliente. Incrementadas las versiones y readme/CHANGELOG propios; changelog.txt conservados.
- La alternativa de emergencia es de autoría del desarrollador, nunca procede de la base de datos del comercio. El enlace a la ficha oficial se mantiene disponible.

## 2026-11-09 — Cabeceras TaxoCommerce en tres zonas (#2476)

- Actualizados los veinte plugins autónomos: identificación a la izquierda, información oficial y cambios históricos centrados en la misma línea, y versión y fecha a la derecha. Reorganización adaptable para pantallas estrechas.
- Fuente oficial del extracto: Plugin URI definido por el autor; sin formulario de modificación por el administrador de la tienda ni acciones admin_post para guardarlo. El historial permanece dentro del CHANGELOG.md propio de cada plugin y plegado por defecto.
- Plantillas de generación, test de regresión de cabeceras y versiones/readme de cada plugin actualizados. Mantener PRO fuera de este despliegue.

## 2026-11-08 — Desbloquear entregas FTPS de pruebas durante Plugin Check (#2376)

- Eliminada la exclusión mutua del pipeline completo: el chequeo oficial de 20 plugins no monopoliza la cola de las siguientes entregas a STAGING y Focazul.
- Conservada la exclusión mutua en el workflow de transferencias FTPS existente, con procesamiento ordenado de ambos sitios y verificación de sus entregas.
- Sin cambios en los controles del SHA actual, pruebas oficiales estrictas, autorizaciones de publicación a PRO ni en los plugins instalados.
- Añadida prueba de regresión que impide volver a bloquear FTPS por un chequeo completo. No se modifica el monolito.

## 2026-11-08 — FTP compartido y guardas de coexistencia legacy (Issue #2347)

- Serializadas las entregas STAGING y Focazul en su servidor FTPS compartido, conservando la comprobación independiente de ambos destinos aunque STAGING falle.
- Eliminados los reintentos anidados del shell: cada entrega utiliza tres reintentos del cliente Python, con salida sin búfer y la fase de error FTPS identificada.
- Se preserva la puerta de producción y la entrega exclusiva de archivos modificados de plugins autorizados.
- Los bootstraps históricos Ojeador e Ingeniero evitan reinicializar clases y hooks cuando Scrappers ya ha cargado el motor.
- Añadidas pruebas de serialización FTPS y de coexistencia; no se eliminan implementaciones legadas sin validar regresiones.

## 2026-11-07 — Saneamiento de cargadores históricos (Issue #2347)

- El bootstrap raíz delega en la única cadena activa de `includes/seo-includes-bootstrap.php`; se eliminan referencias obsoletas y duplicadas.
- Marketing, la cabecera pública y Clonador históricos delegan en sus implementaciones canónicas, sin rutas inexistentes ni versiones alternativas.
- Añadida prueba regresiva para las cuatro entradas de compatibilidad en CI y validación PHP.
- Sin cambios en datos, módulos autónomos ni rutas de publicación a producción; la coexistencia de clases queda para una validación separada.

## 2026-11-07 — Entrega directa de Core manteniendo los tres workflows (#2340)

- El workflow existente de STAGING/Focazul identifica los cambios de Core y/o Tasks en el propio evento push, incluso cuando una validación anterior hace que la base Git parezca idéntica a HEAD.
- Reconcilia solo los archivos instalables del plugin seleccionado mediante SHA-256 remoto, sin publicar en PRO ni instalar módulos ajenos.
- Cuando únicamente cambia el propio workflow/script, reconcilia Core para no perder correcciones de su catálogo.
- Se conservan exactamente los tres procesos de Actions existentes; no se incorpora un cuarto workflow.
- Nuevas pruebas de eventos Core, Tasks, ambos, modificaciones ajenas y rechazos de ramas distintas de staging.

## 2026-11-07 — Core: catálogo completo e índices MO opcionales (#2340)

- Core 0.4.60 registra las 19 extensiones vigentes en su catálogo local, incluyendo Tasks ausente o inactivo, sin necesidad de cargar el plugin y sin instalación automática.
- Corregido el validador de ficheros GNU gettext MO para aceptar una tabla hash opcional ausente (tamaño y desplazamiento cero), formato legítimo presente en algunos catálogos; los límites de los índices originales y traducidos siguen verificándose.
- Añadidas pruebas para los tres estados de Tasks y para hash ausente, tabla fuera de límites y desplazamiento incorrecto. No se alteraron los catálogos de otros plugins.
- Cambios destinados a STAGING y Asociación Focazul, sin publicar en producción.

## 2026-11-07 — Recuperación del despliegue de manuales Tasks 0.1.3 (Issue #2313)

- Corregido el caso en el que una validación PR satisfactoria del mismo SHA era tomada como baseline de despliegue, aunque las transferencias FTPS se hubieran omitido.
- El push directo de Tasks se reconcilia ahora contra las huellas SHA-256 reales de los ficheros instalables de ese **único plugin** en STAGING y Focazul; solo se escriben los que difieren.
- Las rutas y limitaciones de los despliegues normales, Core y producción se mantienen intactas. El cambio no habilita sincronizaciones generales ni publicaciones a PRO.
- Añadidas pruebas para baseline igual a HEAD, selección restringida a Tasks y rechazo de sincronización global en el modo dirigido.

## 2026-11-07 — Despliegue acotado de TaxoCommerce Tasks (Issue #2290)

- El menú de Tasks 0.1.1 ya estaba corregido y fusionado, pero la distribución a STAGING/Focazul quedó en cola del pipeline global.
- Habilitada la ejecución de despliegue al hacer push en el workflow existente `deploy-staging.yml`, únicamente para el plugin `taxocommerce-tasks` y los dos destinos de prueba.
- Diferenciado el evento push directo de las llamadas reutilizables del pipeline general, manteniendo la selección de todos los plugins para las rutas existentes.
- Añadidos tests del filtro específico y del rechazo de sincronizaciones completas/otros plugins; no se habilita ninguna escritura en producción.
- La entrega real y los checksums remotos se verifican por cada destino en GitHub Actions; no equivale a activar el plugin.

# Changelog

## [Unreleased] — Locales regionales en los 19 plugins TaxoCommerce (#2295)

- Los 19 plugins independientes priorizan las traducciones oficiales de WordPress en formatos .mo y .l10n.php.
- Si el locale exacto no tiene catálogo local ni paquete oficial, se reutiliza el catálogo disponible del idioma principal para variantes ES/EN/FR/DE/NL (por ejemplo en_GB -> en_US).
- Nueva prueba CLI sobre los 19 cargadores de gettext y la prioridad de los paquetes oficiales, integrada en la validación de despliegue; ninguna lógica de WooCommerce se altera.
- Los scripts de inventario y generación de traducciones consideran los 19 plugins activos, excluyendo Connections independiente, integrado en el módulo de Insights/Google-Bing. Pendientes de #2062 los textos y catálogos aún incompletos.

## GitHub Actions: tres workflows permanentes - 05/11/2026 (Issue #2205)

- Se conservan exclusivamente `.github/workflows/deploy-staging.yml` (STAGING y Focazul), `.github/workflows/despliege_de_main_a_pro.yml` (PRO solo por `workflow_dispatch`) y `.github/workflows/taxocommerce-plugin-check.yml` (Plugin Check oficial generico).
- Se retiran 12 workflows de QA, auditoria, diagnostico y operaciones puntuales. Los scripts y tests auxiliares permanecen intactos y disponibles para los workflows conservados.
- No se modifican plugins ni se despliegan archivos a instalaciones WordPress mediante este cambio. Las entradas historicas de Actions pueden seguir visibles en GitHub aunque los YAML ya no existan en `staging`.

## Restauración del despliegue modular STAGING → PRO — 03/11/2026 (#2119)

- Se recupera el procedimiento de paquetes ZIP individuales y aplicación/verificación desde WordPress de la versión histórica comprobada en la acción GitHub #41243555528 (origen 03cb678b, 31/10/2026). Aquella ejecución verificó por SHA-256 10 plugins instalados, y la nueva versión conserva la detección dinámica de todos los módulos instalados, estén activos o no.
- Se omiten deliberadamente los pasos históricos de sincronización del monolítico `seo-taxonomy`, los instaladores de plugins ausentes y cualquier limpieza o eliminación de archivos. No se publica ningún archivo fuente del monolítico.
- La cuenta FTPS existente solo se utiliza para entregar paquetes y un testigo temporal al receptor instalado (directorio `.taxocommerce-deploy`); el receptor WordPress aplica los cambios en los `WP_PLUGIN_DIR` reales. No se intenta buscar los directorios de cada plugin a través del FTP restringido.
- Se conservan los paquetes por plugin, exclusión de `tests`, ocultos, ficheros de desarrollo y rutas externas, prueba de integridad de los ZIP, comparación SHA-256 remota, versiones, y fallo bloqueante de la ejecución si la comprobación no coincide.
- Solo se ejecuta manualmente desde la rama `staging`. Pruebas nuevas en CI para impedir futuros despliegues del repositorio completo o del monolítico, sin ejecutar una subida automática a PRODUCCIÓN. El éxito histórico no sustituye una nueva validación real de producción.


## Corrección de destino FTPS de PRODUCCIÓN - 03/11/2026 (#2119)

- El workflow manual `despliege_de_main_a_pro.yml` ahora **transmite al script** el directorio FTP de PRODUCCIÓN que antes faltaba en su entorno. Su campo de ejecución `pro_plugins_dir` viene preconfigurado con `hosting160568eu-279554.webempresa.site/wp-content/plugins`, observado en WePanel; permite ajustar el directorio si la cuenta FTP tiene una raíz distinta.
- El publicador resuelve el directorio explícito de forma segura desde la carpeta de conexión FTP o sus directorios superiores. Nunca elige silenciosamente otra instalación WordPress; si la ruta configurada es inaccesible, aborta antes de subir archivos e informa de la ruta y de la raíz FTP inicial.
- Eliminada del script genérico la suposición sobre un sitio concreto. Se mantiene la prevalidación de carpetas y accesos, la selección de ficheros publicables por plugin, la comparación SHA-256 y la exclusión de `.git`, pruebas, monolítico y otros directorios ajenos.
- Añadidas pruebas para múltiples WordPress accesibles, selección explícita desde otra instalación, ruta incorrecta y conexión efectiva entre el input de GitHub Actions y el script. No se ha ejecutado una nueva subida a PRODUCCIÓN durante esta corrección.


## PRO: verificar el directorio FTP antes de copiar cualquier plugin — 03/11/2026 (#2119)

- Corregido el falso diagnóstico de «plugin ausente»: el directorio real de PRODUCCIÓN muestra carpetas que el login FTPS no encontraba. Se listan ahora las carpetas por NLST, se buscan posibles raíces WordPress y se verifica la coincidencia con los plugins de la revisión antes de elegir una instalación.
- Preferencia por el directorio de PRODUCCIÓN confirmado en Webempresa; si existen varias instalaciones ambiguas, falta acceso al sitio de PRODUCCIÓN o solo se ven tres de los veinte plugins, se cancela el despliegue **antes de transmitir el primer archivo**.
- Se comprueba por lectura la accesibilidad de todas las carpetas presentes y sus archivos principales. Los errores FTP 550 sobre carpetas visibles se notifican como problema de ruta/permisos, no como si el plugin no existiera.
- Un plugin verdaderamente ausente en el listado del destino no se instala; puede omitirse de forma explícita. No se copian monolito, .git, .github ni archivos ajenos a las carpetas TaxoCommerce.
- El manifiesto incorpora raíz FTP, lista de plugins visibles y descripción real de los errores. Pruebas nuevas con dos WordPress accesibles, 19 carpetas de PRODUCCIÓN, errores FTP 550 y origen incorrecto con tres plugins. Despliegue real a PRODUCCIÓN pendiente de verificación.


## STAGING → PRO: continuar con plugins presentes sin bloquear toda la familia — 2026-11-03 (#2119)

- La ejecución manual ya no se cancela porque un directorio `taxocommerce-*` no exista o no sea accesible en PRO (error observado en `taxocommerce-auditor`). Se actualizan los demás plugins existentes, sin crear el ausente ni cambiar la configuración del sitio.
- Cada plugin omitido se registra con su causa en el log y en `pro-release-manifest.json` (`skipped_plugins` y `modules_skipped`). La síntesis distingue actualización parcial de publicación completa, sin afirmar que todos los plugins están desplegados.
- Las transferencias continúan limitadas a archivos distribuibles rastreados en Git y al directorio individual de cada plugin bajo `wp-content/plugins`. No se sincroniza el repositorio, el monolítico, `.git`, pruebas ni archivos ocultos.
- Test de regresión del flujo completo con `taxocommerce-auditor` ausente y otros dos plugins instalados. Las incidencias reales de transferencia (por ejemplo, errores SHA-256) siguen provocando fallo del job.
- El flujo PRO sigue siendo exclusivamente manual; esta corrección de GitHub no ejecuta ninguna subida real a PRODUCCIÓN.


## Corrección de ruta FTPS de plugins en PRO - 2026-11-03 (Issue #2119)

- El despliegue manual solo actualiza archivos publicables rastreados en Git dentro de carpetas `taxocommerce-*/` bajo `wp-content/plugins/`; nunca se copia `.git`, `.github`, código de pruebas, el monolito `seo-taxonomy` ni el repositorio completo.
- Elimina la dependencia FTP del directorio `seo-taxonomy`; localiza el directorio real de plugins mediante la instalación existente de TaxoCommerce Core, con alternativa explícita `PRO_FTP_PLUGINS_DIR` cuando el login FTPS tenga una raíz no convencional.
- Antes de subir un archivo verifica que **todos** los plugins de la publicación están presentes en PRO. No crea nuevos directorios raíz de plugins ni modifica plugins ausentes; mantiene comprobación SHA-256 e idempotencia.
- Pruebas sin red para raíces FTP diversas, exclusiones de archivos, ausencia de plugins, rechazo de rutas inseguras e integridad de transferencias. No se ha ejecutado despliegue a PRODUCCIÓN durante esta corrección.


## Despliegue manual de toda la familia a PRO - 2026-11-03 (Issue #2119)

- Sustituida la sincronizacion FTPS del repositorio entero, que escribia en el FTP del monolito, por una rutina centrada exclusivamente en los directorios `taxocommerce-*` de `staging`.
- Se incluyen los 20 modulos del repositorio sin consultar instalacion, activacion, inventarios REST ni versiones; las carpetas de modulos ausentes se crean exclusivamente bajo el directorio de plugins de WordPress.
- El FTP debe iniciar en el directorio del monolito existente y la ruta padre debe validarse antes de cualquier escritura. El codigo del monolito queda intacto.
- Solo se usan archivos versionados y distribuibles; quedan excluidos tests, ocultos, datos, logs y temporales. Sin eliminaciones remotas ni cambios en configuraciones de WordPress.
- Se evita retransferir archivos identicos, se valida SHA-256 antes y despues de cada sustitucion y se guarda un manifiesto de lo realmente comprobado. Un despliegue parcial termina como fallo.
- Workflow solo manual (`workflow_dispatch`) y seleccion obligatoria de la rama `staging`, con pruebas offline de rutas, integridad, idempotencia y descubrimiento de modulos. Esta correccion no ejecuta por si sola un despliegue en PRO.

## Corrección de infraestructura FTPS - 2026-11-01 (Issue #1993)

- El despliegue incremental a PRO tolera carpetas temporales FTPS ya existentes (error 550 de `mkdir -p`) y verifica el acceso real mediante `cd` antes de transferir archivos.
- Restaurada la comprobación estricta tras la creación tolerante; añadida prueba de regresión de workflow sin realizar conexiones FTPS ni despliegues.
- Corrección limitada al mecanismo CI de transferencia: sin cambios en plugins, datos, uploads ni ejecución en producción. La causa precisa del fallo de la ejecución #152 sigue pendiente de los registros completos.


> **Histórico del monolito cerrado.** TaxoCommerce SEO 2.4.1 es la última versión planificada de la línea monolítica. A partir del milestone 2.4.2, los cambios funcionales y sus versiones se documentan en el `readme.txt` de cada plugin independiente. Este archivo raíz queda congelado salvo una corrección crítica que afecte específicamente al monolito.

## Corrección crítica de despliegue - 2026-10-31 (Issue #1973)

- Desactivado el despliegue automático del monolito en el flujo modular de PRODUCCIÓN.
- Generación de paquetes incrementales exclusivamente con archivos distribuibles modificados desde el último despliegue de PRODUCCIÓN verificado; si falta una referencia fiable, se aborta.
- Preparación de ZIP de módulos no instalados solo cuando su código ha cambiado.
- Recuperada la ubicación temporal FTPS/REST que funcionó en la ejecución #41243555528; validación del destino mediante lectura remota, copia previa y sustitución atómica de un único receptor PHP cuando es necesario.
- El receptor admite la lista explícita de módulos modificados, conserva compatibilidad con clientes previos y verifica SHA-256. Los archivos de desarrollo, las eliminaciones automáticas y los datos de usuarios quedan fuera del despliegue.

## [2.4.1] - 2026-10-25

### Cierre de la arquitectura monolítica

- Última versión planificada de **TaxoCommerce SEO** como plugin monolítico.
- Se completa el cambio operativo hacia la familia modular TaxoCommerce, con Core y extensiones independientes.
- El desarrollo funcional ordinario deja de incorporarse al monolito.
- Cada plugin independiente mantiene desde ahora su propia versión y su propio changelog en el `readme.txt` de su carpeta.
- Los milestones globales posteriores, empezando por **2.4.2**, coordinan trabajo entre plugins y no representan una nueva versión del monolito.
- El monolito sólo podrá recibir correcciones críticas de compatibilidad, migración o seguridad cuando sean estrictamente necesarias.

### Versionado posterior

- Las versiones de Core, Images, Auditor, Suppliers, Insights, Distribution y el resto de extensiones evolucionan de forma independiente.
- Una modificación en un plugin no obliga a incrementar la versión de los demás.
- El repositorio raíz conserva este changelog como histórico de la etapa monolítica.


## [2.4.0] - 2026-10-13

Primera release de la línea **TaxoCommerce SEO 2.4.x**.

### Resumen ejecutivo del ciclo 2.4.0

- **Contenidos y SEO:** evolución de Auditor por cinco ámbitos independientes (productos, categorías, posts, páginas/landings y FAQs), prioridades editoriales, evidencia y exportación JSON; mejoras de Analista, Solucionador, Ingeniero y Comparador.
- **Mercado y comercio:** reorganización de la capa Mercado (Analista, Ingeniero y Ojeador), observación de competencia y oportunidades, e integración progresiva de fuentes comerciales.
- **Catálogo y proveedores:** ampliación de importación/exportación CSV, estados de publicación y papelera, flujos de proveedores y piloto internacional VEVOR con categorías, idiomas y enlaces de afiliación.
- **Dependiente y Academia:** estadísticas y trazabilidad del uso, diagnósticos de aprendizaje y continuidad de procesos de entrenamiento.
- **Operación:** gestor central de procesos, workers, herramientas de diagnóstico y validación, además de correcciones de compatibilidad y preparación para WordPress.org.
- **Modularización:** inicio de la separación del monolito en TaxoCommerce Core y extensiones de Import-Export, Imágenes y System Check; diseño de Vocabulary y FAQs como módulos diferenciados.

**Alcance:** este resumen recoge los trabajos desarrollados y la evolución funcional del ciclo, no certifica que cada flujo esté libre de incidencias. La migración completa de Vocabulary/Auditor a taxonomías nativas, la retirada de tablas `seo_*` y la validación final de WordPress.org continúan pendientes y corresponden a trabajos posteriores (2.4.1).

### Identidad pública y WordPress.org

- El nombre público pasa a ser **TaxoCommerce SEO - Catalog, Trends, Content & Feeds**.
- La versión global del plugin pasa de 2.3.10 a **2.4.0**.
- Se prepara el permalink público solicitado a WordPress.org: **taxocommerce-seo**.
- El dominio de traducción público pasa a **taxocommerce-seo**.
- Se actualizan README, readme público, Wiki, manuales y textos visibles del panel.
- Se conservan por compatibilidad las constantes, tablas, opciones, metadatos, nombres de clases, funciones y slugs operativos internos históricos cuando cambiarlos pudiera romper instalaciones existentes.

### Import / Export

- Páginas y entradas pueden exportarse incluyendo **Papelera (`trash`)** y reimportarse para enviar contenido existente a la papelera mediante el flujo nativo de WordPress.
- La importación acepta `trash`, `papelera` y `basura`; la exportación escribe siempre el valor canónico `trash`.
- Un CSV con estado de papelera no crea contenido nuevo ni realiza borrado definitivo; la simulación informa de la acción y un objeto en papelera puede restaurarse al importar otro estado válido.
- La receta VEVOR 1.6.0 simplifica las importaciones manuales: conserva la imagen principal externa del feed e importa directamente al catálogo intermedio, sin pasar por GitHub/Python ni por el Gestor de workers cuando no se necesita enriquecimiento F1-F12/M1-M12.
- Los callbacks GitHub/Python y Google/Python validan la estructura del CSV estándar antes del `sideload` seguro y habilitan el fallback MIME solo después de esa validación, evitando rechazos de CSV válidos introducidos por el endurecimiento de subidas para WordPress.org.

### Consolidación funcional

- La release consolida el trabajo ya integrado en staging desde 2.3.10, incluyendo la evolución de Dependiente, Ingeniero, Auditor, Ojeador, Analista, importaciones y el Gestor de workers.
- Dependiente incorpora estadísticas separadas de GA4, Search Console y uso interno, con exportación JSON para análisis.
- Ingeniero consolida investigación L1 técnica, L2 práctica y L3 actualidad con trazabilidad y propuestas editoriales diferenciadas.
- Import / Export queda integrado con el Gestor central para encadenar la cola general sin depender exclusivamente de Action Scheduler/WP-Cron.
- Se mantienen compatibilidad de datos y continuidad de las instalaciones procedentes de la serie 2.3.x.


## [2.3.10] - 2026-10-06

- Ojeador 0.10.0 añade la pestaña **Novedades**, un radar externo que usa **Clusters + Hubs primarios** como frontera comercial para descubrir categorías, subfamilias y tecnologías que todavía no están representadas en el catálogo.
- El radar usa **Google Trends** para señales crecientes y valida cada candidato con **Google Shopping + Google Search** mediante la conexión compartida SerpApi → ScraperAPI; conserva fuentes, cobertura, score explicable y estado humano sin crear categorías/productos automáticamente.
- Novedades distingue **Nueva categoría/familia**, **Hueco en catálogo**, **Producto/tecnología en tendencia** y **Expansión estratégica**, con exportación JSON auditable.

- Dependiente V3 registra las **elecciones explícitas del cliente** como evidencia supervisada: `category_choice` para categorías sugeridas y `click` para productos, conservando consulta, posición y recorrido mediante `search_uuid`.
- La evidencia se guarda aunque todos los términos de la consulta ya sean conocidos; sirve para aprender rutas/ranking y no activa reglas semánticas automáticamente.

- El informe de la **Actualización continua de Academia** pasa a schema 2 y exporta los fallos reales de cada módulo con pregunta, respuesta esperada, resultados devueltos, diagnóstico, fuente formativa y contexto de la entidad.
- Añadido `learning_diagnostics` para separar fallos de aprendizaje de errores técnicos y agrupar por módulo, diagnóstico, tipo de fuente y tipo de pregunta; en M1 las categorías se tratan como referencia formativa protegida y las sugerencias de revisión se marcan como hipótesis, no como causa demostrada.
- El diagnóstico se reconstruye desde las ejecuciones ya persistidas, por lo que puede analizar el run en curso sin repetir la formación.

- Auditor 0.11.0 añade una **cola editorial priorizada** por auditoría: P1 CORREGIR_AHORA, P2 ESPERAR_ENRIQUECIMIENTO, P3 MIGRAR_A_SOLUCIONADOR, P4 REVISAR y P5 INFORMATIVO.
- Cada tarea de Auditor incluye `task_id`, estado, dependencia, evidencia, recomendación e impacto esperado; las contradicciones de identidad que necesitan proveedor quedan bloqueadas en `NEEDS_SOURCE_VERIFICATION` y las métricas de tráfico quedan pendientes de Analista.

- Separado **Ingeniero** de Dependiente como servicio propio en **Contenidos**, con pestañas Investigación, Editorial, Datos y fuentes y Pruebas.
- Añadido el proceso editorial técnico de Ingeniero con dossiers trazables, source_hash, cobertura neutral, acciones CREATE/IMPROVE/MERGE/NO_ACTION/NEEDS_REVIEW y borradores sólo tras aprobación humana.
- Solucionador deja de consumir Ingeniero como fuente o requisito de conocimiento; ambos procesos reutilizan la API neutral de cobertura sin mezclar sus decisiones.
- Los borradores de Ingeniero usan el rol estable **ingeniero_qa_specialized**, relación **post_to_category**, Vocabulary canónico y metadatos de dossier/hash; nunca se autopublican.
- Añadida batería funcional del proceso editorial de Ingeniero y documentación de la nueva arquitectura.

- Integradas en las plantillas públicas de producto y categoría las capas de **Comparador, Dependiente, Comentarista e Ingeniero**, ocultando completamente cada bloque cuando no existe contenido publicado válido.
- Añadido un rol editorial estable en Entradas para distinguir posts de **Preguntas habituales** e **Información técnica** sin inferirlo por el título; las plantillas consumen la relación explícita `post_to_category`.
- Retirada la salida pública heredada de FAQs en estas plantillas para evitar duplicar el conocimiento migrado a los nuevos bloques editoriales.

Los cambios validados en `staging` que todavía no formen parte de una publicación de producción se documentan aquí. Al cerrar una release semanal, estas entradas se trasladan a la versión fechada correspondiente.

## [2.3.9] - 2026-09-30

Release estable que consolida el ciclo de parches **2.3.8.x** desplegado entre el 26 y el 30 de septiembre de 2026. Reúne las mejoras funcionales, correcciones y ajustes que fueron entrando sucesivamente como 2.3.8.1, 2.3.8.2 y siguientes hasta el cierre del ciclo.

### Dependiente, Academia e Intérprete

- Añadido diagnóstico en vivo de la **Lección 9** del Intérprete, con fase actual, conservación semántica, ruido, patrones consolidados, regresión y exportación JSON sin alterar el algoritmo de aprendizaje.
- Añadida **parada segura** para Dependiente e Intérprete, integrada con el gestor de procesos.
- Corregida la finalización y persistencia de la **Lección 10**, incluyendo conservación de la marca de currículo `semantic-v2`, recuperación tardía tras despliegues y restauración conservadora de cursos completados.
- Añadido banco de **preguntas directas/manuales** para entrenar Dependiente V3.
- Incorporado aprendizaje autónomo desde preguntas manuales, manteniendo trazabilidad de resultados y sin sustituir el conocimiento existente.
- Añadida una **cola persistente de archivos de preguntas** para procesar lotes sucesivos sin perder el estado.
- Añadido informe por lote de preguntas con evaluadas, aprendidas, fallidas, errores, confianza, cobertura, diagnóstico y descarga JSON específica por archivo.

### Ingeniero, Clasificador y Semántica

- Creado **Ingeniero** como servicio técnico dentro de Dependiente para investigar conocimiento externo por categoría, separado del catálogo y de las respuestas públicas.
- Añadidas fuentes trazables, niveles de confianza, evidencias breves y relación conocimiento → fuente → URL original.
- Corregido el tratamiento de **HTTP 429 de SerpApi** para pausar Ingeniero sin avanzar la categoría ni perder trabajo pendiente.
- Añadida importación y exportación de conocimiento de Ingeniero en **CSV y JSON**, con remapeo de fuentes y estados de revisión.
- Añadida aprobación individual y masiva de categorías/conocimiento en revisión antes de considerarlo activo.
- Reorganizada la navegación de **Semántica** para mostrar **Clasificador** como sección propia junto a Etiquetas y Atributos.
- Integrado Clasificador con el conocimiento de Ingeniero para detectar posibles **nuevos atributos, valores y conceptos semánticos** por categoría.
- Clasificador distingue propuestas **Candidato nuevo / Posible equivalente / Ya cubierto** y puede trabajar con conocimiento activo o provisional en revisión.
- Añadida tabla global **Ingeniero → vocabulario** y acciones de aceptación individual o masiva que amplían los maestros canónicos sin asignar automáticamente datos a productos.
- El **Clonador PRO → STAGING** incorpora fuentes y conocimiento persistente de Ingeniero, manteniendo separados colas, cursores, configuración y consumo de SerpApi.

### Auditor, Analista y control de calidad

- Analista 3.8.3 corrige la contradicción entre **HACER AHORA** y acciones de vigilar/esperar/investigar: una tarea inmediata exige acción primaria ejecutable con verbo, objeto, destino, responsable y URL validada.
- La tarjeta separa **Estado de la señal / Prioridad de trabajo / Tipo de intervención / Bloqueo** y `task_decision` pasa a ser la fuente única para contador, sección, etiqueta y JSON.
- INVESTIGAR añade fuente de verificación y criterio de salida; las tareas ejecutadas no se duplican y el seguimiento usa un ID estable. JSON schema 9 y pruebas T01–T06.

- Analista 3.8.2 corrige el exceso de **INVESTIGAR**: intenta resolver primero categorías/entidades locales y reserva ese bucket a modelo, entidad o destino realmente no resueltos.
- Las oportunidades SEO bloqueadas por stock/precio/proveedor/margen se mantienen como **HACER DESPUÉS** con condición explícita de desbloqueo y bucket potencial, sin alterar artificialmente el score; GA4 queda como señal de medición separada y no bloquea por sí sola.
- Cada tarea expone comprobaciones concretas, valor actual, owner, `unlock_condition`, `bucket_if_unblocked` y diagnóstico agregado de gates; JSON schema 8.

- Analista 3.8.1 endurece el **plan de acción bajo demanda**: valida consulta ↔ entidad ↔ URL, detecta conflictos de modelo, penaliza muestras mínimas y separa prioridad de confianza antes de emitir instrucciones.
- Añadidos buckets **INVESTIGAR** y **ESPERAR DATOS**, acciones atómicas, owner/dependencias, preparación comercial explícita y contrato auditable con baseline y revisiones 28/60/90; HACER_AHORA conserva el máximo de 10 sin rellenarlo artificialmente.
- Analista 3.8.1 comprueba metadatos efectivos de plugin/plantilla antes de declarar title/meta ausentes, consulta cobertura publicada de Dependiente/Ingeniero/Comparador antes de sugerir nueva URL y añade 7 pruebas de aceptación más diagnóstico de Action Scheduler.

- Analista 3.8.0 incorpora **Informe competitivo profundo**: dominios configurables, muestreo SERP automático con SerpApi → ScraperAPI, auditoría pública acotada, matriz 1–5 con confianza de evidencia, fortalezas/brechas, plan priorizado y exportación JSON.
- Search Console, GA4 y Bing se mantienen exclusivos del dominio propio; los competidores se evalúan sólo con evidencia pública observable y los datos ausentes quedan como N/D.

- Auditor 0.13.0 añade `contexto_de_decision`: consume exclusivamente snapshots/lecturas persistidas de Analista, Ojeador, Dependiente, Comparador y proveedores, sin abrir nuevas llamadas externas durante la auditoría.
- Los estados de contexto distinguen `fresh`, `partial`, `stale` y `unavailable`; un dato ausente se conserva como `null` y no como cero.
- La prioridad precomputada por Analista puede ordenar tareas dentro de la misma P1–P5, pero no cambia la clase interna del hallazgo ni sustituye a Solucionador.

- Dividido **Auditor** en auditorías independientes por bloques: Productos, Categorías y arquitectura, Posts, Páginas/Landings, FAQs y Motor/índice de Dependiente.
- Separados explícitamente **Auditor de contenidos** y **Auditor de Academia** para evitar mezclar diagnósticos de catálogo con aprendizaje.
- Añadida una **puntuación interna de calidad SEO** por entidad, con desglose de relevancia, rigor, diferenciación, arquitectura semántica y preparación técnica.
- Mejorado el informe de **Anomalías** para reducir falsos positivos en páginas funcionales/corporativas y añadir exportación JSON.
- Añadido equilibrio semiautomático de categorías: propuestas de **división** para categorías heterogéneas y de **concentración** para categorías pequeñas, siempre con revisión/aprobación previa.
- Separado el diagnóstico por tamaño de las propuestas ejecutables: se muestran categorías con más de 10 productos y categorías con 1–4 productos aunque todavía no exista una operación segura.
- Mejorada la precisión de **Analista** y de los informes SEO para diferenciar mejor métricas de Google Search Console, GA4 y Bing.
- Alineados los informes de GSC/GA4, excluyendo pruebas administrativas cuando corresponde y completando métricas de páginas.
- Incorporada en **Estado del servidor → MySQL** una auditoría SQL de solo lectura con inventario, índices, EXPLAIN, métricas, histórico, gráficos y exportación JSON.

### Marketing, campañas y redes sociales

- Completada la gestión de campañas con edición, vaciado seguro, eliminación, restauración de precios y cancelación de tareas programadas.
- Ampliada la importación/exportación de campañas a registros de campaña y producto, con resolución por `product_id`/SKU, modo fusión y sustitución explícita.
- Añadida autodetección de filas de producto para mantener compatibilidad con CSV antiguos sin `record_type`.
- Las campañas y publicaciones sociales reutilizan imágenes reales de producto desde **Media o proveedor externo**, sin importar imágenes externas a la biblioteca.
- Las creatividades de campaña incorporan producto, precio anterior, precio de oferta y descuento, con fallback seguro cuando una imagen remota no puede componerse.
- Añadido **Calendario** mensual de redes sociales con entradas, landings y campañas, filtro por red, detección de huecos/colisiones y vista responsive.
- Añadidos **Informes de Marketing** para campañas y redes sociales con visitas, interacciones, pedidos, facturación, conversión y atribución.
- Implementada atribución campaña → publicación → visita → pedido con ventana de última interacción de 30 días y sin almacenar PII adicional.
- Reservadas las **18:00** para ofertas de campañas y las **20:00** para Noticias.
- Añadida prioridad editorial para Noticias y soporte del campo `prioridad` en Programador/CSV.
- Las piezas editoriales del mismo día ya no bloquean una oferta comercial; solo otra oferta de campaña ocupa el hueco comercial de las 18:00.

### Frontend, contenidos y navegación

- Añadidas plantillas específicas para **categorías de entradas** del blog, con variantes desktop/móvil y resolución automática desde el gestor de plantillas.
- Las entradas del blog pueden mostrar hasta **8 productos relacionados** a partir de las categorías comerciales enlazadas explícitamente.
- Reorientadas varias plantillas para priorizar **producto y catálogo** y compactar los bloques de asistencia/Dependiente.
- Ajustados cabecera, ficha de producto y carrito para mejorar la jerarquía comercial y aprovechar mejor el primer pliegue.
- Creada la sección administrativa **Contenidos**, agrupando Productos, Categorías, Páginas, Entradas e Imágenes sin cambiar sus rutas internas.
- **Comentarista** se ha movido a **Herramientas**.
- **Solucionador** pasa a presentarse como **Editor** y queda integrado en Contenidos, manteniendo sus slugs y datos internos por compatibilidad.

### Comparador, carrito y documentos comerciales

- Ampliado el **Comparador de Dependiente** de 4 a 6 productos.
- Añadida exportación de comparativas a **PDF A4 apaisado** reutilizando Dompdf.
- El comparador utiliza clasificación canónica y atributos técnicos de TaxoCommerce SEO, mostrando diferencias relevantes y manteniendo fallback de WooCommerce cuando aporta datos adicionales.
- Corregidas las imágenes del carrito para admitir imágenes externas activas de proveedores y variaciones.
- Recuperados **Presupuesto** y **Proforma borrador** en la plantilla de carrito DHT.
- Restablecido el **Sistema documental** como interruptor maestro para Presupuesto/Proforma/Factura después de validar el flujo correcto.
- Permitida la descarga de Presupuesto/Proforma cuando el transporte todavía no está calculado, marcando el total como provisional.
- Mejorados Presupuesto y Proforma con resumen comercial, características relevantes, acompañamiento de compra y presentación más legible.
- Los documentos de cliente muestran la **referencia interna/object_id** en lugar del SKU real del proveedor, manteniendo el SKU para la operativa interna.
- Los PDF pueden utilizar imagen externa del proveedor cuando el producto no dispone de imagen en Media.
- Corregida la interpretación fiscal para catálogos cuyo **PVP ya incluye IVA**: ficha, carrito y checkout mantienen el precio final visible y desglosan el impuesto sin volver a sumarlo.
- La sincronización del modo de precios con IVA queda separada de la gestión automática de tasas para evitar duplicar IVA aunque el gestor fiscal esté desactivado.

### Proveedores e importación

- Añadida receta CSV para **Decathlon** dentro del flujo de catálogo intermedio de proveedores.

### Documentación y proceso de publicación

- Añadido `readme.txt` público actualizado para WordPress y documentado el proceso de versiones **feature/fix → staging → main → producción**.
- Ampliada la documentación de Marketing, campañas, Clasificador, Ingeniero, Categorías, Contenidos, Dependiente y sistema documental.
- Añadido un mapa general de servicios y flujo de información en la documentación canónica/Wiki.
- Documentado el sistema completo de **Facturas, Proformas y Presupuestos**, incluyendo fiscalidad, numeración, snapshots, transporte pendiente e imágenes externas.

## [2.3.8.1] - 2026-09-26

### Marketing y campañas

- Completada la gestión de campañas existentes con edición explícita mediante **Guardar cambios**.
- Añadidos **Vaciar productos** y **Eliminar campaña** con restauración previa de precios aplicados y cancelación de tareas programadas.
- Ampliada la importación/exportación a un formato completo con registros `campaign` y `product`, incluyendo producto, SKU, precio de campaña y posición.
- La importación completa mantiene compatibilidad con los CSV/JSON antiguos que solo contienen calendario.
- Añadido modo de importación por **fusión** (predeterminado) y opción explícita para **sustituir completamente los productos** de las campañas incluidas.
- Los productos importados se resuelven por `product_id` y, como respaldo, por SKU; se mantienen las validaciones de solapamiento entre campañas.

## [2.3.7] - 2026-09-25

Release centrada en la evolución comercial del sistema, el análisis de mercado, la automatización de campañas, la integración de inventarios con proveedores, la mejora de Dependiente y Solucionador, la optimización de informes y varias mejoras relacionadas con feeds comerciales, opiniones externas y Google Trends.

### Marketing y campañas comerciales

- Creado el nuevo **servicio de campañas comerciales**.
- Integrado **Analista** para proponer campañas de marketing a partir de señales de catálogo, demanda y rendimiento.
- Añadidas **plantillas para campañas publicitarias** y espacios promocionales en la tienda.
- Incorporada la generación de **imágenes para campañas**.
- Añadida exportación e importación de campañas y programación mediante **JSON**.
- Integradas las campañas comerciales en el **programador de redes sociales**.
- Añadidos **precios de oferta y periodo de vigencia** a los feeds comerciales.

### Ojeador y análisis de mercado

- Mejorados los indicadores de **Ojeador** para presentar comparativas de precios más claras.
- Incorporado un sistema de clasificación visual mediante **semáforo de precios**:
  - precio competitivo;
  - precio dentro de mercado;
  - precio poco competitivo.
- Añadida detección de **productos estrella** y oportunidades comerciales.
- Añadida una nueva capa de **análisis de mercado y acciones prioritarias**.
- Mejorada la identificación de productos sobre los que puede resultar interesante crear una oferta.
- Simplificada la lectura de indicadores comerciales para facilitar decisiones de precio y promoción.

### Inventarios y proveedores

- Creado un sistema de **inventarios comerciales por proveedor**.
- Incorporada la posibilidad de seleccionar y enviar determinados productos a proveedores.
- Añadidos procesos automáticos de actualización y sincronización de inventarios con proveedores.
- Preparada la infraestructura para utilizar disponibilidad, precio y catálogo de proveedores como parte del análisis comercial.

### Dependiente V3 y cobertura del catálogo

- Intérprete / Lingüista L9 incorpora diagnóstico en vivo sin modificar el algoritmo de aprendizaje: fase actual, repaso L8, conservación semántica, evidencias de ruido, patrones consolidados, regresión final, últimos 30 casos y exportación JSON del estado en curso.

- Conectado **Dependiente V3** con el sistema actual.
- Reforzada la cobertura editorial y semántica utilizada por Dependiente.
- Mejorada la integración de categorías, productos y conocimiento para responder a búsquedas de usuario.
- Preparada la base para continuar evolucionando la retroalimentación entre Dependiente y las búsquedas realizadas.

### Solucionador

- Creado el nuevo servicio **Solucionador**.
- Añadida exportación del informe de Solucionador en formato **JSON**.
- Integrado el nuevo servicio dentro de la arquitectura de análisis y ayuda a la selección de productos.

### Comentarista y opiniones externas

- Corregido un error en el sistema de **importación/exportación de Comentarista**.
- Añadida integración de **valoraciones externas de productos**.
- Mejorado el tratamiento de productos sin opiniones para evitar problemas relacionados con `aggregateRating` y datos estructurados.
- Preparada la infraestructura para incorporar progresivamente información externa de opiniones y valoraciones.

### Informes y rendimiento

- Optimizada la carga de **SEO Reports**, evitando generar automáticamente informes pesados al entrar en la pantalla.
- Los informes pasan a generarse bajo demanda mediante un botón **Crear informe**.
- Añadida opción **Cerrar informe**.
- Añadida descarga individual de informes en formato **JSON**.
- Reducida la carga inicial y el consumo de recursos en la sección de Informes.

### Google Trends

- Corregida la integración de **Google Trends**.
- Mejorado el tratamiento de respuestas y señales obtenidas desde Trends.
- Reforzada su integración con Analista y los sistemas de detección de oportunidades.

### Feeds comerciales

- Añadido soporte para:
  - precio normal;
  - precio de oferta;
  - fecha de inicio de oferta;
  - fecha de finalización de oferta.
- Preparados estos datos para su utilización en feeds comerciales y plataformas externas.

### Estabilidad y mantenimiento

- Realizadas correcciones adicionales en servicios de importación, análisis y generación de informes.
- Mejorada la separación entre procesos pesados y carga de las pantallas administrativas.
- Reforzada la generación de JSON como formato de intercambio y revisión externa.

### Issues principales

- #299 — Añadir precios de oferta y vigencia a feeds comerciales.
- #298 — Añadir imágenes de los posts/campañas y exportación JSON.
- #297 — Enlazar Analista para proponer campañas de marketing.
- #296 — Crear plantillas para campañas publicitarias.
- #295 — Crear sistema de fidelización de clientes.
- #293 — Crear servicio de campañas comerciales.
- #292 — Agregar procesos automáticos de inventarios con proveedores.
- #291 — Crear semáforo para visualizar mejor las ofertas de Ojeador.
- #290 — Añadir productos estrella y oportunidades de oferta.
- #289 — Mejorar indicadores de Ojeador con comparativas más claras.
- #288 — Sistema de envío de productos a proveedores e inventarios comerciales.
- #287 — Añadir capa de análisis de mercado y acciones prioritarias a Ojeador.
- #286 — Conectar Dependiente V3 y reforzar cobertura editorial.
- #285 — Añadir JSON al servicio Solucionador.
- #284 — Crear servicio Solucionador.
- #283 — Corregir importación/exportación de Comentarista.
- #282 — Optimizar carga de informes de `seo-reports`.
- #281 — Corregir Google Trends.
- #243 — Agregar valoraciones externas de productos.
- #196 — Corregir ausencia de opiniones y `aggregateRating`.
## [2.3.6] - 2026-09-23

Release centrada en la evolución del sistema de análisis y aprendizaje, la integración con Google Shopping, la mejora de Dependiente, la auditoría SEO, el control del catálogo y diversas tareas de estabilidad y mantenimiento.

### Ojeador y Google Shopping

- Incorporado el nuevo servicio **Ojeador** para obtener y analizar información externa de mercado.
- Mejorada la priorización de las consultas para concentrar recursos en categorías y áreas con mejores señales de rendimiento.
- Adaptadas las búsquedas a la nomenclatura y clasificación utilizada por **Google Product Taxonomy**.
- Mejorados los KPI y la información de seguimiento de Ojeador.
- Añadidos logs y exportación JSON para facilitar el diagnóstico y el análisis de resultados.
- Eliminado el límite artificial de consultas/descargas de Google Shopping.
- Corregidos errores detectados durante la ejecución del servicio Ojeador.

### Academia y Entrenador

- Incorporada la **Lección 9**, incluyendo sistema de preguntas.
- Incorporada y revisada la **Lección 10** como fase de repaso y consolidación.
- Optimizada la ejecución de la Lección 10.
- Mejorado el Entrenador para gestionar de forma más eficiente los procesos relacionados con la nueva fase de aprendizaje.
- Ajustada la lógica de aprendizaje y revisión para reducir trabajo redundante.

### Dependiente

- Desplegada una nueva evolución de **Dependiente**.
- Mejorada la coordinación entre Dependiente y los filtros del catálogo.
- Añadido sistema de feedback para aprovechar mejor las consultas y respuestas de los clientes.
- Incorporada edición de las plantillas utilizadas por Dependiente.
- Añadido mecanismo para eliminar conocimiento aprendido cuando sea necesario.
- Mejorada la integración y presencia de Dependiente dentro del sistema.
- Incorporado **Comentarista** en las fichas de producto.

### SEO, auditoría y validación

- Ampliados los chequeos del plugin con nuevos controles SEO y técnicos.
- Incorporadas comprobaciones derivadas de incidencias y recomendaciones detectadas durante las revisiones con Google.
- Corregida la generación de `canonical` en plantillas de categorías.
- Mejorado el tratamiento de errores HTTP internos:
  - los timeouts de transporte ya no se interpretan automáticamente como errores SEO;
  - los resultados no concluyentes se diferencian de errores reales.
- Mejorada la priorización del plan de acción SEO.
- Revisado y mejorado el sistema de análisis de tiempos de carga de páginas.
- Corregidos problemas en el escaneo externo de páginas que podían producir respuestas HTTP 503.
- Alineada parte de la terminología y clasificación del catálogo con los esquemas utilizados por Google.

### FAQs

- Modernizado el sistema de FAQs.
- Mejorada la gestión y preparación de las FAQs para auditoría, análisis y evolución posterior.

### Redirecciones

- Mejorado el gestor de redirects.
- Añadidas funciones de **exportación e importación**.
- Añadido JSON de análisis para poder estudiar:
  - actividad de las reglas;
  - número de hits;
  - cadenas;
  - ciclos;
  - incidencias estructurales;
  - posibles redirecciones sospechosas.

### Redes sociales

- Mejorado el sistema de redes sociales.
- Añadidas funciones de exportación e importación para facilitar la gestión y automatización de publicaciones.

### Inventario y catálogo

- Incorporado un inventario de relación **categoría-producto** para analizar la distribución real del catálogo.
- Añadida exportación JSON del inventario para análisis externo.
- Mejoradas las plantillas con el objetivo de reforzar su orientación comercial.
- Adaptada la portada para poder utilizar información estadística y priorizar dinámicamente los contenidos con mayor interés.

### Importación, proveedores e imágenes

- Corregida la rotación y limpieza de archivos generados por Importar/Exportar.
- Mejorada la eliminación automática de archivos antiguos ya procesados.
- Corregida la gestión de imágenes para evitar almacenar innecesariamente en Media imágenes que deben permanecer asociadas al proveedor.
- Mejorado el Clonador PRO para ampliar la cobertura de los elementos transferidos entre Producción y Staging.

### Rendimiento y estabilidad

- Revisados los tiempos anómalos detectados durante los escaneos de páginas.
- Mejorada la interacción entre los workers externos, WordPress y los sistemas de caché.
- Reducidos falsos positivos provocados por peticiones HTTP internas.
- Mejorada la estabilidad de las tareas de auditoría y diagnóstico.

### Mantenimiento

- Eliminados datos antiguos de componentes SEO ya obsoletos.
- Limpiadas tablas heredadas de plugins y sistemas anteriores que ya no forman parte de la arquitectura actual.
- Eliminadas copias y componentes redundantes detectados durante las validaciones de integridad.
- Mejorada la consistencia general del plugin antes del despliegue a Producción.

### Resumen de la release

Esta versión consolida varios de los servicios principales de SEO System:

- **Ojeador** amplía la inteligencia competitiva y la integración con Google Shopping.
- **Academia y Entrenador** avanzan en el aprendizaje progresivo del sistema.
- **Dependiente** gana nuevas capacidades de respuesta, feedback y gestión de conocimiento.
- **Auditoría y validación** reducen falsos positivos y amplían los controles técnicos y SEO.
- **Inventario, redirects, FAQs y redes sociales** incorporan nuevas herramientas de análisis y exportación.
- Se refuerzan la estabilidad, el mantenimiento y la observabilidad general del sistema.

## [2.3.5]- 2026-09-17

### Resumen

Actualización amplia de STAGING centrada en la evolución de **Dependiente**, el **Intérprete**, la formación semántica mediante **Lingüista/Academia**, mejoras de arquitectura comercial y plantillas, y nuevas herramientas de importación de proveedores.

Esta versión supone una evolución importante del sistema de consulta asistida, manteniendo separadas las funciones de decisión en frontend de los procesos administrativos, informes y aprendizaje.

---

### Dependiente e Intérprete

- Evolución del servicio **Intérprete** con nuevas versiones y mejoras sucesivas en el análisis de consultas.
- Incorporación de diálogo inteligente de **desambiguación** antes de emitir una recomendación cuando la intención del usuario no es suficientemente precisa.
- El diálogo entre **Intérprete y Dependiente** pasa a ser acumulativo, conservando el contexto generado durante la conversación.
- Mejora de la coordinación entre ambos sistemas para evitar respuestas aisladas y aprovechar las decisiones tomadas en pasos anteriores.
- Priorización de familias de producto concretas al generar preguntas de aclaración.
- Corrección de casos en los que Dependiente no encontraba correctamente posts o contenido relacionado.
- Corrección de la carga de imágenes en el frontend de Dependiente.
- Mejoras generales del comportamiento y presentación del Dependiente en frontend.

### Academia, Lingüista y aprendizaje

- Incorporación y evolución del módulo **Lingüista** como soporte semántico del Intérprete.
- Integración de los procesos de formación de Lingüista en el gestor de workers.
- Añadida exportación de la evolución y estado de Lingüista en formato JSON para diagnóstico y seguimiento.
- Incorporada **actualización incremental de la Academia de Dependiente**, evitando regeneraciones completas cuando solo es necesario procesar información nueva o modificada.
- Mejoras en el flujo de aprendizaje para mantener actualizados los datos utilizados por Dependiente sin sustituir el conocimiento histórico existente.

### Arquitectura comercial y contenido

- Nuevas recomendaciones de arquitectura para **Clusters, Hubs primarios y Hubs secundarios**.
- Mejoras de las plantillas para incorporar señales y recomendaciones de marketing.
- Ajustes de frontend y helpers de plantillas para mantener coherencia entre STAGING y producción.
- Actualización de informes cuya validación de relaciones entre posts y categorías había quedado obsoleta.

### Proveedores e importación

- Incorporado soporte para un **importador externo**.
- Actualización de la integración con **Rubix** para procesos de scraping.
- Añadido lanzador específico para Rubix.
- Eliminada una receta antigua de importación de Emuca que ya no formaba parte del flujo vigente.
- Mejoras en la gestión e inventario reducido utilizado durante procesos de importación.
- Mejorado el exportador reducido de productos.

### Mantenimiento y limpieza

- Eliminación de archivos de backup antiguos y residuos que ya no debían formar parte del árbol activo del plugin.
- Limpieza progresiva de código legacy derivado de anteriores versiones de Dependiente e Intérprete.
- Ajustes para reducir duplicidades y mantener una única implementación canónica de cada componente.

### Compatibilidad

- WordPress 7.1
- WooCommerce 11.1.x
- PHP 8.4.x

### Notas de despliegue

Los cambios se han desarrollado y validado primero en **STAGING**.

Antes de promover esta versión a producción se recomienda:

1. Ejecutar el chequeo completo del plugin.
2. Confirmar que no existen funciones, clases o métodos duplicados.
3. Validar Dependiente, Intérprete y Academia desde administración.
4. Comprobar producto, categoría, buscador y frontend de Dependiente.
5. Revisar que los procesos de proveedores y workers no tengan tareas bloqueadas.
6. Promover `staging` hacia `main` mediante Pull Request después de revisar `Files changed`.


## [2.3.4] 2026-09-13 Reorganización interna y consolidación de `seo-taxonomy`


Se realizó una revisión general de la arquitectura del plugin con el objetivo de consolidar los módulos activos, eliminar implementaciones heredadas y reducir la coexistencia de código duplicado.

La estructura de `includes/` fue reorganizada progresivamente para mantener los servicios dentro de sus directorios específicos. Se eliminaron directorios y archivos legacy relacionados con Analista, Dependiente, Clonador, Productos, Social Network e imágenes, manteniendo como referencia las implementaciones canónicas de cada subsistema.

También se actualizaron los mecanismos de carga y bootstrap para adaptarlos a la nueva organización interna y evitar la inclusión simultánea de versiones antiguas y modernas de un mismo servicio.

La revisión de integridad posterior confirmó la eliminación de duplicados de código: las funciones globales, tipos y métodos duplicados pasaron a cero en el entorno de staging.

Durante el mismo ciclo se introdujeron mejoras adicionales en distintos componentes del sistema, entre ellas:

* evolución del servicio Dependiente y corrección de interrupciones relacionadas con cambios de catálogo;
* mejoras en Academia y ampliación de contenidos y FAQs;
* correcciones y ampliaciones del Auditor de datos, incluyendo reducción de falsos positivos;
* mejoras en plantillas y en su sistema de comprobación y sincronización;
* actualización de filtros de producto;
* incorporación de migas de pan en la cabecera;
* nuevas herramientas para identificar y eliminar imágenes locales procedentes de proveedores;
* ajustes en el cargador de plantillas, autoload y archivos de versión;
* diversas correcciones de estabilidad y mantenimiento.

Tras estos cambios, el plugin mantiene operativos los principales subsistemas de proveedores, catálogo, Data Layer y WooCommerce. Permanecen pendientes determinadas incidencias funcionales y de configuración del entorno de staging, principalmente relacionadas con la portada, la resolución de las páginas de tienda, carrito y checkout, y varias comprobaciones SEO y semánticas que se abordarán de forma independiente.


## [2.3.3] - 2026-09-08

Release correspondiente al **Milestone 2.3.3**, centrada en SEO técnico y datos estructurados, importación y sincronización de datos, logística y facturación, calidad del dato, plantillas y estabilidad general del plugin.

### Añadido

- Añadido JSON-LD para portada, categorías y productos, incluyendo la base para `Organization`, `WebSite`, `Product` y navegación estructurada. (#173)
- Creado un sistema de clonación/sincronización para trasladar datos entre producción y staging. (#174)
- Añadido soporte para importar desde PRO a STAGING páginas, categorías, entradas y productos. (#146)
- Añadida asociación de las etiquetas de páginas con el vocabulario SEO. (#152)
- Añadida asociación de las etiquetas de FAQs con el vocabulario SEO. (#165)
- Añadido control propio de costes de envío para complementar o sustituir la lógica estándar de WooCommerce. (#150)
- Añadido nombre descriptivo de los portes según la regla logística aplicada. (#157)
- Añadido control sobre la intensidad/frecuencia de tareas programadas de WooCommerce. (#153)
- Implementado tracking de compras WooCommerce en GA4. (#71)
- Ampliado el sistema de facturas, presupuestos y proformas para contemplar portes e impuestos. (#151)
- Mejorado el tratamiento de impuestos y costes asociados a envíos a Canarias, Ceuta y Melilla. (#131)

### Mejorado

- Mejorado el sistema de actualización de productos durante los procesos de importación. (#163)
- Mejorado el comparador de tablas y datos de productos. (#154)
- Revisadas las plantillas para mostrar la información con una jerarquía más consistente. (#141)
- Mejorada la paridad entre STAGING y PRO para reducir diferencias en plantillas y comportamiento. (#155)
- Revisada la separación entre STAGING y PRO para evitar canibalización e indexación no deseada. (#161)
- Mejorados los informes y controles basados en información procedente de Google, Trends y otras fuentes. (#126)
- Ampliada la revisión y disponibilidad de información asociada a imágenes. (#28)
- Reforzados los controles de calidad del dato orientados a detectar contenido pobre, repetitivo o poco útil. (#147)
- Revisados pesos y dimensiones de productos para detectar datos incorrectos. (#162)
- Mejorada la sincronización de atributos y etiquetas entre STAGING y PRO. (#145)
- Reorganizado el sistema de chequeos en una estructura de carpetas específica y más mantenible. (#172)

### Corregido

- Corregida la incidencia que indicaba erróneamente que el motor propio de Import / Export no estaba disponible. (#148)
- Corregidos problemas del sistema de logística y transporte. (#156)
- Corregidos modos/configuraciones de MySQL que podían provocar errores. (#158)
- Reclasificados avisos de MySQL que no representan errores reales para que se muestren como información. (#159)
- Ajustados parámetros de control de WooCommerce para reducir avisos y errores innecesarios. (#160)
- Recuperado el comparador que había dejado de mostrarse o funcionar correctamente. (#139)
- Recuperado el pie específico de VEVOR. (#138)
- Corregido/eliminado el mensaje de afiliación de Amazon mostrado indebidamente en el pie de páginas. (#137)
- Revisados y corregidos problemas detectados a partir del informe de Semrush. (#64)

### Datos, SEO y compatibilidad

- Se ha reforzado la integración entre Vocabulary, etiquetas, FAQs, categorías y páginas.
- Se han mejorado las comprobaciones de integridad del contenido y del catálogo.
- Se han revisado diferencias de codificación y estructura de base de datos entre entornos; el issue #167 quedó cerrado como duplicado.
- Se mantienen las comprobaciones de compatibilidad con WordPress, WooCommerce y los subsistemas propios de importación, proveedores y plantillas.

### Notas de despliegue

- Versión del plugin: **2.3.3**.
- Rama de integración: `staging`.
- Rama de producción: `main`.
- La versión fue promovida mediante Pull Request de `staging` a `main` y desplegada posteriormente a producción.
- 
## 2.3.2 - Consolidación operativa, Dependiente y catálogo
- Evoluciona Dependiente con entrenamiento, conocimiento persistente, importación/exportación de conocimiento y mejores respuestas ante búsquedas sin resultados.
- Amplía la integración de producto con Amazon y otros proveedores, incorporando productos alternativos y mejorando la disponibilidad de resultados en consultas y comparativas.
- Refuerza el sistema de clasificación semántica y vocabulario, utilizando diccionarios y datos canónicos para mantener coherencia entre productos, etiquetas, atributos y categorías.
- Mejora los procesos de importación, exportación y sincronización de proveedores, incluyendo peso, dimensiones, imágenes, enlaces, control de procesos y exportaciones de apoyo para futuras cargas.
- Optimiza la gestión de imágenes de producto, incluyendo conversión a WebP, reducción de peso, sincronización y tratamiento de imágenes externas.
- Incorpora mejoras comerciales en carrito, checkout, portes, presupuestos y facturación, incluyendo cálculo de envío basado en las características del producto y generación de documentación para clientes.
- Amplía los informes y herramientas SEO, con nuevos filtros, exportaciones JSON, inventarios de contenidos, comparativas y controles para reducir canibalización y detectar contenido sin clasificar.
- Refuerza el mantenimiento interno mediante limpieza de base de datos, rotación de logs, eliminación programada de imports antiguos y mejoras de observabilidad.
- Corrige múltiples incidencias de interfaz y funcionamiento en Dependiente, comparadores, checkout, carrito, búsqueda SEO, estadísticas y conexiones con proveedores.
- Prepara y valida el plugin para el entorno actualizado de WordPress 7.1.

## 2.2.8 - Cierre de etiquetas legacy de producto

- Elimina el fallback público a `wp_seo_nodes` para etiquetas de producto: la ficha solo muestra APLICACIÓN, PLATAFORMA y SUBTIPO canónicos.
- Bloquea nuevas escrituras de `seo_nodes/product/product` desde el importador legacy y desde la pantalla antigua de clasificación de productos.
- El importador mantiene la columna `etiquetas` por compatibilidad de formato, pero ya no la persiste como etiqueta legacy de producto.
- La exportación V2 rellena `etiquetas` con las facetas canónicas públicas (APLICACIÓN / PLATAFORMA / SUBTIPO), no con keywords legacy.
- Los informes de contenido consideran la clasificación semántica canónica (TIPO y facetas) en lugar de depender de las keywords legacy.
- Las propuestas de categoría que usan señales de producto leen las facetas canónicas públicas.
- La pantalla Etiquetas pasa de modo transición a modo canónico y conserva el contador legacy como control de regresión.

## 2.2.7 - Guardado semantico seguro

- El editor de APLICACION, PLATAFORMA y SUBTIPO ya no reescribe asignaciones sin cambios.
- Un simple Actualizar conserva source y confidence de las asignaciones automaticas existentes.
- Solo los terminos realmente anadidos por el usuario pasan a source=manual/confidence=1.0000.
- Solo los terminos realmente retirados se desactivan.

## 2.2.6 - Hotfix filtro SUBTIPO en Etiquetas
- Corrige el error crítico al abrir TaxoCommerce SEO → Etiquetas después de cargar SUBTIPO.
- El selector de filtros inicializa ahora el grupo `subtipo` igual que ROL, APLICACIÓN y PLATAFORMA.
- No modifica datos, vocabulario ni asignaciones existentes.

## 2.2.5 - Etiquetas canónicas visibles y editor integrado
- La ficha pública prioriza APLICACIÓN, PLATAFORMA y SUBTIPO canónicos en «Aplicaciones y características».
- Las keywords legacy quedan como fallback únicamente cuando el producto no tiene ninguna etiqueta canónica pública.
- La edición semántica se integra como pestaña «Etiquetas semánticas» dentro de «Datos del producto» de WooCommerce.
- TIPO y Ámbito/ROL siguen siendo solo lectura; APLICACIÓN, PLATAFORMA y SUBTIPO son editables y guardan source=manual.
- No elimina etiquetas legacy ni atributos técnicos.

## 2.2.4 - Edición manual del vocabulario semántico de producto

- Añade metabox `Clasificación semántica / Etiquetas` en la ficha nativa de producto.
- TIPO y Ámbito/ROL permanecen de solo lectura para proteger la identidad canónica.
- APLICACIÓN, PLATAFORMA y SUBTIPO se pueden seleccionar, limpiar y crear manualmente.
- Las decisiones guardadas manualmente usan `source=manual` y `confidence=1.0000`.
- La pantalla Etiquetas añade filtro por SUBTIPO y enlaza directamente al bloque de clasificación del producto.
- Control de integridad muestra también asignaciones inválidas de SUBTIPO.
- No elimina etiquetas legacy ni modifica atributos técnicos.

## 2.2.3 - Etiquetas / vocabulary control view

- Añade la pestaña visible **TaxoCommerce SEO > Etiquetas**.
- Vista de producto en solo lectura con Ámbito/ROL, TIPO, APLICACIÓN, PLATAFORMA, SUBTIPO y etiquetas SEO legacy en la misma fila.
- Filtros por producto/SKU/ID, TIPO, ROL, APLICACIÓN, PLATAFORMA y cobertura de facetas.
- Añade explorador del vocabulario canónico por grupo, con términos, productos, asignaciones, confianza y fuentes.
- Añade una pestaña Control que comprueba cobertura TIPO/ROL, duplicados, asignaciones inválidas y coherencia TIPO -> ROL.
- No modifica datos de catálogo ni elimina etiquetas antiguas; esta versión sirve para validar visualmente el resultado de la migración antes de continuar con SUBTIPO y la retirada del modelo legacy.

## 2.2.2 - Canonical product role reads

- Product semantic validation now reads the canonical ROL derived from active TIPO -> `wp_seo_type_role_map`; `seo_nodes/product/ambito` remains fallback only.
- Product CSV export no longer reads `seo_nodes/product/ambito` directly; public column `ambito` is still emitted from canonical ROL.
- Category `ambito` remains legacy/editorial and is explicitly not derived from product roles.
- Fixes a category semantic-validation regression where a product-role lookup could be referenced in category scope evaluation.
- No database migration is included in this release.

# Changelog

## 2.2.1 - Vocabulary bridge
- ROL canónico de producto leído desde TIPO -> wp_seo_type_role_map, con fallback materializado/legacy.
- La cabecera CSV pública `ambito` se conserva, pero exporta el ROL canónico del producto.
- Las importaciones legacy no pueden contradecir un ROL canónico existente; si no existe todavía, pueden crear un ROL provisional de compatibilidad.
- `consumible` pasa a ser un ámbito/rol válido en importación, validación e informes.
- Los atributos SEO JSON admiten explícitamente el scope especial `global`.
- Las categorías mantienen su `ambito` legacy en esta fase; no se derivan del ROL de sus productos.

# CHANGELOG

Todos los cambios relevantes de **TaxoCommerce SEO** se documentan en este
archivo.

El proyecto utiliza versionado **SemVer (MAJOR.MINOR.PATCH)**.

-   **MAJOR** → Cambios importantes de arquitectura o funcionamiento.
-   **MINOR** → Nuevas funcionalidades compatibles.
-   **PATCH** → Correcciones y mejoras internas.

------------------------------------------------------------------------

## 2.2.0 - Supplier Import / Sync V2
- Separada la seleccion comercial del estado de sincronizacion.
- Actualizacion automatica por proveedor + SKU conservando object_id.
- Proteccion de titulo, descripcion, excerpt, categorias, atributos y etiquetas en actualizaciones.
- Productos nuevos a la categoria Nuevos productos.
- Modo de imagen persistente local/external; externo como opcion recomendada para nuevas importaciones.
- Reconstruccion autoritativa de galerias externas por SKU.
- Fallback visual: imagen local -> imagen externa -> logo de tienda.
- Runs auditables y deteccion segura de bajas solo en catalogos completos sin errores.
- Bajas reversibles y reactivacion del mismo object_id.
- CSV del crawler limitado a registros vistos en el ciclo actual.


# \[2.0.0\] - Próxima versión

## Arquitectura

Esta versión introduce la nueva arquitectura **SEO Persistence Layer**,
cuyo objetivo es centralizar todas las operaciones de escritura sobre la
base de datos y las entidades gestionadas por el plugin.

A partir de esta versión, las modificaciones persistentes dejarán de
realizarse directamente desde los distintos módulos y pasarán por una
capa común responsable de validar, registrar, auditar y proteger todas
las operaciones críticas.

## Añadido

-   Nueva arquitectura SEO Persistence Layer.
-   API centralizada para operaciones de escritura.
-   Historial de operaciones.
-   Auditoría completa de modificaciones.
-   Preparación para snapshots de datos.
-   Sistema de rollback seguro.
-   Simulación de operaciones (Dry Run).
-   Gestión de transacciones.
-   Registro del contexto de ejecución.
-   Preparación para migraciones versionadas.
-   Inicio del sistema oficial de publicación de versiones.

## Mejorado

-   Base para futuras actualizaciones seguras.
-   Preparación del plugin para mantenimiento sin pérdida de datos.
-   Infraestructura para operaciones masivas protegidas.

------------------------------------------------------------------------

# \[1.5.0\]

## Auditoría y validación

-   Auditoría estructural del sistema SEO.
-   Validación de Clusters, Hubs Primarios, Hubs Secundarios y
    Categorías.
-   Detección de anomalías estructurales.
-   Validación de imágenes, plantillas y contenido.
-   Informes de integridad.
-   Exportación de tablas SEO.
-   Herramientas de limpieza y validación de base de datos.

------------------------------------------------------------------------

# \[1.4.0\]

## Arquitectura SEO

-   Implantación de la estructura basada en Clusters.
-   Incorporación de Hubs Primarios.
-   Incorporación de Hubs Secundarios.
-   Relaciones semánticas entre niveles.
-   Gestión independiente de nodos SEO.
-   Generación de páginas estructurales.

------------------------------------------------------------------------

# \[1.3.0\]

## Clasificación

-   Sistema de clasificación SEO mediante relaciones.
-   Asignación automática de categorías.
-   Gestión de taxonomía SEO independiente.
-   Relaciones entre categorías y estructura editorial.

------------------------------------------------------------------------

# \[1.2.0\]

## Automatización

-   Importación y sincronización de datos.
-   Gestión de atributos SEO.
-   Sistema de redirecciones.
-   Diccionario SEO.
-   Gestión de etiquetas.

------------------------------------------------------------------------

# \[1.1.0\]

## Infraestructura

-   Organización modular del plugin.
-   Bootstrap inicial.
-   Primeras herramientas administrativas.
-   Base de tablas internas del sistema SEO.

------------------------------------------------------------------------

# \[1.0.0\]

## Primera versión

Primera versión funcional de **TaxoCommerce SEO**.

Incluye la infraestructura inicial del proyecto y las primeras
herramientas para gestionar relaciones SEO, páginas estructurales y
organización semántica del catálogo.

