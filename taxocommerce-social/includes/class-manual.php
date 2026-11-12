<?php
/**
 * Independent user and developer manuals for TaxoCommerce Social.
 *
 * This file only renders static documentation. It must never call provider APIs,
 * schedule work, read tokens, install tables or modify publication settings.
 */
defined( 'ABSPATH' ) || exit;

function tcsocial_manual_render() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Sin permisos para consultar los manuales.', 'taxocommerce-social' ) );
    }
    ?>
    <div class="tcsocial-manual" style="max-width:1180px" id="tcsocial-manual-index">
        <div class="seo-social-card">
            <h2>Documentación de TaxoCommerce Social · v<?php echo esc_html( TAXOCOMMERCE_SOCIAL_VERSION ); ?></h2>
            <p>Dos manuales independientes y actualizables con el plugin. Consultarlos no conecta redes, envía publicaciones ni modifica ajustes.</p>
            <nav aria-label="Índice de manuales">
                <h3>Índice de contenidos</h3>
                <p><strong><a href="#tcsocial-manual-user">Manual de usuario</a></strong>: funcionamiento y procedimientos de administración.</p>
                <ol>
                    <li><a href="#tcsocial-user-start">Acceso y requisitos</a></li>
                    <li><a href="#tcsocial-user-connections">Conexiones y redes</a></li>
                    <li><a href="#tcsocial-user-templates">Plantillas y variables</a></li>
                    <li><a href="#tcsocial-user-calendar">Programar y consultar el calendario</a></li>
                    <li><a href="#tcsocial-user-csv">CSV: disponibilidad y precauciones</a></li>
                    <li><a href="#tcsocial-user-results">Resultados, errores y Auto Test</a></li>
                </ol>
                <p><strong><a href="#tcsocial-manual-tech">Manual técnico para futuros desarrolladores</a></strong>: arquitectura, APIs, seguridad y mantenimiento.</p>
                <ol>
                    <li><a href="#tcsocial-dev-architecture">Punto de entrada y componentes</a></li>
                    <li><a href="#tcsocial-dev-storage">Persistencia y compatibilidad histórica</a></li>
                    <li><a href="#tcsocial-dev-providers">Contratos de proveedores</a></li>
                    <li><a href="#tcsocial-dev-hooks">Acciones, filtros, permisos y nonces</a></li>
                    <li><a href="#tcsocial-dev-cron">Flujo de publicación y WP-Cron</a></li>
                    <li><a href="#tcsocial-dev-csv">Importación y exportación CSV</a></li>
                    <li><a href="#tcsocial-dev-autotest">Auto Test, seguridad y diagnóstico</a></li>
                    <li><a href="#tcsocial-dev-workflow">Cómo mantener, probar y publicar cambios</a></li>
                </ol>
            </nav>
        </div>

        <section class="seo-social-card" id="tcsocial-manual-user" aria-labelledby="tcsocial-user-heading">
            <h1 id="tcsocial-user-heading">Manual de usuario</h1>
            <p>Destinado al administrador que gestiona las cuentas sociales y programa publicaciones. No requiere conocimientos de PHP ni acceso al servidor.</p>

            <h2 id="tcsocial-user-start">1. Acceso y requisitos</h2>
            <h3>1.1 Dónde se encuentra</h3>
            <p>Desde WordPress, entra en <strong>TaxoCommerce Core → Social</strong>, si Core está activo, o en <strong>TaxoCommerce Social</strong> cuando funciona de forma independiente. Requiere un usuario con permisos <code>manage_options</code>.</p>
            <p>La navegación principal mantiene <strong>Conexiones</strong>, <strong>Plantillas</strong> y <strong>Calendario</strong>. Las pestañas <strong>Auto Test</strong> y <strong>Manual</strong> se encuentran al final. Las campañas comerciales se administran en el plugin Campaigns, no en esta agenda.</p>
            <h3>1.2 Qué hace y qué no hace</h3>
            <p>Social permite configurar redes autorizadas, preparar textos y programar <strong>publicaciones únicas</strong> de entradas y páginas/landings. Instalar el plugin no publica ni conecta una cuenta automáticamente. La recurrencia y la republicación periódica no están disponibles en el Programador actual.</p>

            <h2 id="tcsocial-user-connections">2. Conexiones y redes</h2>
            <h3>2.1 Procedimiento para conectar una cuenta</h3>
            <ol>
                <li>Abre <strong>Conexiones</strong> y selecciona una de las cinco redes.</li>
                <li>Introduce únicamente los identificadores y credenciales de una cuenta que tengas autorización para administrar.</li>
                <li>Guarda la conexión. Si aparece la opción, activa la prueba posterior al guardado y revisa el resultado.</li>
                <li>Comprueba las autorizaciones en el servicio externo antes de programar una publicación real.</li>
            </ol>
            <div style="overflow-x:auto"><table class="widefat striped"><thead><tr><th>Red</th><th>Requisito de uso</th><th>Comprobación práctica</th></tr></thead><tbody>
                <tr><td>Facebook</td><td>Página y token con permisos adecuados</td><td>Comprueba la página seleccionada y el modo enlace/foto</td></tr>
                <tr><td>Instagram</td><td>Cuenta profesional; puede reutilizar la conexión a Facebook Pages</td><td>Verifica la cuenta vinculada y la imagen necesaria</td></tr>
                <tr><td>LinkedIn</td><td>Aplicación OAuth y permisos de publicación de organización</td><td>Comprueba la organización y la autorización de la cuenta</td></tr>
                <tr><td>Pinterest</td><td>Aplicación autorizada y tablero de destino</td><td>Comprueba tablero e imagen del Pin</td></tr>
                <tr><td>X</td><td>Aplicación y autorización OAuth 2.0</td><td>Comprueba acceso de escritura y restricciones del plan de API</td></tr>
            </tbody></table></div>
            <h3>2.2 Qué significa «conectado»</h3>
            <p>La etiqueta de conexión refleja la configuración local guardada, no una garantía de que el token siga vigente o que la API acepte publicaciones. Un error de permisos, de imagen o de límite de API requiere revisar el proveedor. <strong>No pegues tokens, secretos ni datos privados en informes o capturas.</strong></p>
            <h3>2.3 Desconectar</h3>
            <p>Utiliza <strong>Desconectar</strong> en la red correspondiente para restablecer sus datos de conexión locales. Comprueba también, si procede, la revocación del acceso en la plataforma externa. No borres directamente opciones de WordPress para desconectar.</p>

            <h2 id="tcsocial-user-templates">3. Plantillas de publicación</h2>
            <h3>3.1 Crear o modificar un formato</h3>
            <p>Abre <strong>Plantillas</strong>, edita el texto de <strong>entradas</strong> y <strong>páginas</strong> de la red deseada y guarda. El plugin dispone de formatos predeterminados para las cinco redes. Entre las variables admitidas están <code>{titulo}</code>, <code>{extracto}</code>, <code>{fecha}</code>, <code>{url}</code>, <code>{sitio}</code>, <code>{autor}</code>, <code>{tipo}</code>, <code>{categorias}</code> e <code>{imagen}</code>.</p>
            <p>El texto que se envía depende de las características de cada proveedor, su formato y los recursos multimedia del contenido. <strong>Guardar una plantilla no envía ninguna publicación.</strong></p>
            <h3>3.2 Texto personalizado al programar</h3>
            <p>En el formulario del Programador puedes introducir una plantilla opcional para la tarea. Si la dejas vacía se utiliza la plantilla vigente de cada red cuando se publica. Si seleccionas varias redes y escribes un texto específico, comprueba que es adecuado para todas.</p>

            <h2 id="tcsocial-user-calendar">4. Calendario y publicaciones programadas</h2>
            <h3>4.1 Programar manualmente</h3>
            <ol>
                <li>Abre <strong>Calendario → Programador</strong> y selecciona una entrada o landing publicada. El selector muestra hasta 150 contenidos recientes.</li>
                <li>Elige fecha y hora según la zona horaria configurada en WordPress.</li>
                <li>Marca una o varias redes conectadas y, opcionalmente, introduce una plantilla y una nota interna.</li>
                <li>Pulsa <strong>Programar publicación</strong> y comprueba que aparece en <strong>Cola y historial</strong>.</li>
            </ol>
            <h3>4.2 Estados y acciones permitidas</h3>
            <div style="overflow-x:auto"><table class="widefat striped"><thead><tr><th>Estado</th><th>Significado</th><th>Acción disponible</th></tr></thead><tbody>
                <tr><td>Programada</td><td>Esperando su ejecución WP-Cron</td><td>Cancelar</td></tr>
                <tr><td>Publicando</td><td>Tarea en ejecución</td><td>Consultar el resultado posterior</td></tr>
                <tr><td>Publicada</td><td>El proveedor ha devuelto éxito</td><td>Abrir el enlace remoto, si existe</td></tr>
                <tr><td>Fallida</td><td>Se produjo un error de ejecución o del proveedor</td><td>Revisar error y Reintentar ahora</td></tr>
                <tr><td>Cancelada</td><td>La ejecución fue cancelada</td><td>Reintentar ahora, si corresponde</td></tr>
            </tbody></table></div>
            <h3>4.3 Vista de agenda</h3>
            <p>La agenda mensual y su vista compacta permiten filtrar por red, revisar publicaciones previstas y localizar coincidencias de horario y proveedor. Una tarea guardada no es una publicación confirmada; revisa siempre el estado final, especialmente si WordPress recibe pocas visitas o WP-Cron está retrasado.</p>

            <h2 id="tcsocial-user-csv">5. CSV: disponibilidad y precauciones</h2>
            <h3>5.1 Estado de la interfaz</h3>
            <p>En <strong>Calendario</strong> se ha recuperado el bloque <strong>Programación masiva con Excel / CSV</strong> y sus controles de descarga, validación y confirmación. Sigue disponible el programador manual independiente. La validación no crea tareas: revisa el resultado antes de pulsar <strong>Importar programaciones</strong>.</p>
            <p>Los encabezados del formato heredado son <code>contenido_id</code>, <code>titulo</code>, <code>redes</code>, <code>fecha_hora</code> y <code>prioridad</code>. El importador admite alias y separadores habituales de Excel/LibreOffice; estos detalles se documentan para una futura integración o para instalaciones que todavía expongan la pantalla heredada.</p>
            <h3>5.2 Procedimiento únicamente si existe una interfaz CSV disponible</h3>
            <ol>
                <li>Prepara un archivo <code>.csv</code>, <code>.txt</code> o <code>.tsv</code> de hasta <strong>2 MB</strong>, con los IDs de contenido, las redes y las fechas deseadas.</li>
                <li>Sube el archivo mediante la opción de <strong>previsualización</strong>. Este primer paso valida el formato sin programar publicaciones.</li>
                <li>Revisa los registros aceptados, avisos y errores. La previsualización temporal caduca a los <strong>20 minutos</strong>.</li>
                <li>Confirma únicamente cuando los datos sean correctos. En esta fase sí se crean o reprograman tareas.</li>
            </ol>
            <h3>5.3 Contratos de exportación conservados</h3>
            <p>El backend incluye descargas de plantilla vacía, contenidos publicables, agenda social e historial de publicaciones. Estos endpoints no equivalen a botones visibles en la interfaz modular actual. Los archivos, cuando se generen desde una interfaz autorizada, pueden contener información editorial y enlaces; protégelos como datos operativos.</p>

            <h2 id="tcsocial-user-results">6. Resultados, incidencias y Auto Test</h2>
            <h3>6.1 Interpretar resultados</h3>
            <p>La tabla de publicaciones puede recoger identificadores externos, enlaces, visitas atribuidas y métricas como reacciones o comentarios. La disponibilidad de métricas varía por proveedor y permisos. Una métrica sin datos no implica necesariamente cero actividad.</p>
            <h3>6.2 Ejecutar Auto Test de Social</h3>
            <p>En <strong>Auto Test</strong> pulsa <strong>Ejecutar Auto Test</strong>. Ejecuta <strong>16 comprobaciones</strong> locales: 15 controles seguros y una omisión deliberada de operaciones externas. Muestra Correcto, Error o No ejecutado; conserva los dos últimos resultados por administrador durante 30 días y señala las regresiones entre ejecuciones.</p>
            <p>No valida contraseñas, tokens, autorizaciones remotas, publicaciones reales ni tareas de producción. Si falta una tabla, informa del problema pero no la crea. Una red sin conectar no supone un fallo técnico del conector.</p>
            <h3>6.3 Diagnóstico rápido</h3>
            <ul>
                <li><strong>Falta una red:</strong> revisa que su módulo esté cargado y activa Auto Test.</li>
                <li><strong>Conexión rechazada:</strong> revisa permisos de la plataforma y usa su prueba de conexión, no Auto Test.</li>
                <li><strong>Publicación retrasada:</strong> revisa WP-Cron, la zona horaria y el estado de la tarea.</li>
                <li><strong>Publicación fallida:</strong> consulta el mensaje de la cola, las imágenes y el permiso de publicar en esa red.</li>
                <li><strong>CSV no disponible:</strong> verifica que la versión instalada exponga un formulario. El módulo independiente actual conserva las funciones de backend, pero no muestra esos controles en Calendario.</li>
            </ul>
            <p><a href="#tcsocial-manual-index">Volver al índice</a></p>
        </section>

        <section class="seo-social-card" id="tcsocial-manual-tech" aria-labelledby="tcsocial-dev-heading">
            <h1 id="tcsocial-dev-heading">Manual técnico para futuros desarrolladores</h1>
            <p>Referencia de mantenimiento del plugin <strong>independiente</strong> <code>taxocommerce-social/</code>. Se basa en los componentes y contratos actualmente existentes. No presupone servicios nuevos ni dependencia del plugin monolítico.</p>

            <h2 id="tcsocial-dev-architecture">1. Arquitectura y punto de entrada</h2>
            <h3>1.1 Requisitos y arranque</h3>
            <p>La raíz <code>taxocommerce-social/taxocommerce-social.php</code> declara versión y rutas (<code>TAXOCOMMERCE_SOCIAL_VERSION</code>, <code>TAXOCOMMERCE_SOCIAL_PATH</code>, <code>TAXOCOMMERCE_SOCIAL_URL</code>). Requiere WordPress 6.7 o superior y PHP 7.4 o superior. Core es opcional: <code>taxocommerce_social_register_with_core()</code> registra el módulo en <code>taxocommerce_core_register_modules</code>; sin Core, <code>tcsocial_admin_menu()</code> crea su menú propio.</p>
            <h3>1.2 Inventario de archivos y responsabilidades</h3>
            <div style="overflow-x:auto"><table class="widefat striped"><thead><tr><th>Archivo</th><th>Responsabilidad principal</th></tr></thead><tbody>
                <tr><td><code>taxocommerce-social.php</code></td><td>Bootstrap, carga de includes, instalación de tablas en activación, registro con Core</td></tr>
                <tr><td><code>includes/core.php</code></td><td>Ajustes, proveedores, plantillas, publicaciones, seguimiento de visitas, API CSV y controladores generales</td></tr>
                <tr><td><code>includes/facebook.php</code>, <code>instagram.php</code>, <code>linkedin.php</code>, <code>pinterest.php</code>, <code>x.php</code></td><td>Adaptadores de cada red y callbacks de autorización/publicación</td></tr>
                <tr><td><code>includes/programador.php</code></td><td>Tabla de agenda, altas, cancelación, reintentos y ejecución WP-Cron</td></tr>
                <tr><td><code>includes/calendar.php</code></td><td>Vista mensual, vista compacta y detección de coincidencias</td></tr>
                <tr><td><code>includes/admin.php</code></td><td>Menú, navegación y despacho de las cinco pestañas</td></tr>
                <tr><td><code>includes/class-auto-test.php</code></td><td>Diagnóstico local no destructivo, resultado e historial transitorio</td></tr>
                <tr><td><code>includes/class-manual.php</code></td><td>Ambos manuales estáticos, sin conexiones a proveedores</td></tr>
            </tbody></table></div>
            <h3>1.3 Navegación y retrocompatibilidad</h3>
            <p>Ruta actual: <code>wp-admin/admin.php?page=taxocommerce-social&amp;tab=connections|templates|calendar|auto-test|manual</code>. <code>tcsocial_admin_current_tab()</code> mantiene compatibilidad con <code>social_subtab</code> antiguo. No cambies los slugs ni registres pantallas duplicadas en el monolítico.</p>

            <h2 id="tcsocial-dev-storage">2. Persistencia y esquema canónico</h2>
            <h3>2.1 Opción de configuración</h3>
            <p><code>TCSOCIAL_NETWORK_SETTINGS_OPTION</code> equivale a <code>seo_social_network_settings_v1</code>. <code>tcsocial_network_get_settings()</code> combina opciones existentes con valores predeterminados sin sobrescribirlas; <code>tcsocial_network_save_settings()</code> las guarda. Contienen plantillas y estados de conexión, además de secretos cifrados: nunca deben aparecer en logs o en un JSON diagnóstico.</p>
            <h3>2.2 Tablas existentes</h3>
            <div style="overflow-x:auto"><table class="widefat striped"><thead><tr><th>Tabla</th><th>Campos importantes</th><th>Servicio responsable</th></tr></thead><tbody>
                <tr><td><code>{prefix}seo_social_publications</code></td><td><code>id</code>, <code>content_id</code>, <code>provider</code>, <code>status</code>, <code>remote_id</code>, <code>remote_url</code>, métricas y marcas de tiempo</td><td><code>tcsocial_network_maybe_install_tables()</code></td></tr>
                <tr><td><code>{prefix}seo_social_schedule</code></td><td><code>id</code>, <code>content_id</code>, <code>provider</code>, <code>scheduled_at</code>, <code>template</code>, <code>status</code>, <code>publication_id</code>, <code>error_message</code></td><td><code>tcsocial_programador_maybe_install_table()</code></td></tr>
            </tbody></table></div>
            <p>Las versiones de esquema se almacenan bajo <code>seo_social_network_db_version</code> y <code>seo_social_programador_db_version</code>. Los instaladores existentes usan <code>dbDelta()</code> en los hooks de activación y de inicio pertinentes. <strong>No añadas tablas nuevas ni cambies claves históricas</strong> para funciones que ya disponen de almacenamiento canónico. Ni el Manual ni Auto Test ejecutan migraciones por abrirse.</p>

            <h2 id="tcsocial-dev-providers">3. Adaptadores de las cinco redes</h2>
            <h3>3.1 Registro del proveedor</h3>
            <p>Los módulos añaden su definición a <code>tcsocial_network_providers</code>; <code>tcsocial_network_get_providers()</code> obtiene el registro mediante <code>apply_filters()</code>. Cada definición expone <code>label</code>, <code>sanitize_connection_callback</code>, <code>test_callback</code>, <code>publish_callback</code> y <code>render_connection_callback</code>. <code>sync_callback</code> es opcional y solo se utiliza cuando el proveedor lo ofrece.</p>
            <h3>3.2 Responsabilidades del adaptador</h3>
            <p>El saneamiento recibe los datos del formulario y la conexión guardada, valida campos propios, cifra los nuevos secretos y devuelve la configuración aceptada o <code>WP_Error</code>. La prueba de conexión y la publicación pueden llamar a APIs externas y deben conservar la gestión de HTTP, límites, errores y renovación de credenciales que corresponda a cada servicio. No invoques estas callbacks desde Auto Test.</p>
            <h3>3.3 Compatibilidad entre proveedores</h3>
            <p>Instagram puede reutilizar Facebook Pages; LinkedIn, Pinterest y X aplican sus contratos OAuth propios. Los permisos de publicación y los formatos de imagen/enlace difieren. No generalices un campo de credenciales o una modalidad de publicación a las cinco redes.</p>

            <h2 id="tcsocial-dev-hooks">4. Hooks, controladores y seguridad</h2>
            <h3>4.1 Endpoints administrativos</h3>
            <div style="overflow-x:auto"><table class="widefat striped"><thead><tr><th>Acción <code>admin_post_*</code></th><th>Uso</th><th>Nonce principal</th></tr></thead><tbody>
                <tr><td><code>tcsocial_network_save_connection</code></td><td>Guardar y opcionalmente probar la conexión</td><td><code>tcsocial_network_save_connection</code></td></tr>
                <tr><td><code>tcsocial_network_disconnect</code></td><td>Desconectar proveedor</td><td><code>tcsocial_network_disconnect</code></td></tr>
                <tr><td><code>tcsocial_network_save_templates</code></td><td>Guardar plantillas</td><td><code>tcsocial_network_save_templates</code></td></tr>
                <tr><td><code>tcsocial_programador_create</code></td><td>Crear una tarea</td><td><code>tcsocial_programador_create</code></td></tr>
                <tr><td><code>tcsocial_programador_cancel</code></td><td>Cancelar una tarea</td><td><code>tcsocial_programador_cancel_{id}</code></td></tr>
                <tr><td><code>tcsocial_programador_retry</code></td><td>Reintentar una tarea</td><td><code>tcsocial_programador_retry_{id}</code></td></tr>
                <tr><td><code>tcsocial_run_auto_test</code></td><td>Ejecutar diagnóstico no destructivo</td><td><code>tcsocial_auto_test_run</code>, campo <code>tcsocial_auto_nonce</code></td></tr>
            </tbody></table></div>
            <p>El permiso de administración principal es <code>manage_options</code>. Mantén los controles <code>current_user_can()</code>, <code>check_admin_referer()</code>, saneamiento de POST y <code>esc_html()</code>/<code>esc_attr()</code>/<code>esc_url()</code> para todas las salidas. Cada controlador valida los datos de su ámbito; no sustituyas nonces específicos por uno global.</p>
            <h3>4.2 Filtros y extensiones existentes</h3>
            <p><code>tcsocial_network_providers</code> registra conectores y <code>tcsocial_network_post_types</code> permite extender los tipos de contenido. El sistema también utiliza hooks de publicación y seguimiento como <code>tcsocial_network_attributed_visit</code>, <code>tcsocial_programador_job_published</code> y <code>tcsocial_programador_job_failed</code>. Preserva los nombres de estos contratos cuando refactorices el plugin.</p>

            <h2 id="tcsocial-dev-cron">5. Flujo de publicaciones y WP-Cron</h2>
            <h3>5.1 Crear, ejecutar y registrar</h3>
            <p><code>tcsocial_programador_create_job()</code> valida el contenido/proveedor y prepara una fila <code>scheduled</code>; registra un evento único <code>tcsocial_programador_run_job</code> con el ID. <code>tcsocial_programador_run_job()</code> coordina la publicación y actualiza su estado, resultado o error. La publicación efectiva delega en <code>tcsocial_network_publish_content()</code> y en la callback del proveedor. Los hooks <code>tcsocial_network_publish_scheduled</code> y <code>tcsocial_network_publish_manual_scheduled</code> se conservan por compatibilidad con otros flujos.</p>
            <h3>5.2 Cancelación y reintento</h3>
            <p><code>tcsocial_programador_handle_cancel()</code> intenta cancelar el evento WP-Cron y marca como cancelada una tarea todavía programada. <code>tcsocial_programador_handle_retry()</code> solo reprograma estados <code>failed</code> o <code>cancelled</code> y agenda una nueva ejecución. No reinicies una publicación confirmada sin investigar primero el identificador remoto para evitar duplicados.</p>
            <h3>5.3 Calendario y rendimiento</h3>
            <p><code>tcsocial_calendar_events()</code> adapta la agenda exportable, <code>tcsocial_calendar_group_by_day()</code> agrupa fechas y <code>tcsocial_calendar_has_same_network_collision()</code> identifica coincidencias de red. La interfaz compacta es una vista del mismo calendario, no un programador alternativo. El selector del Programador muestra hasta 150 contenidos recientes y su tabla consulta hasta 100 tareas.</p>

            <h2 id="tcsocial-dev-csv">6. Contratos CSV y validación</h2>
            <h3>6.1 Esquema y exportaciones (disponibles en Calendario)</h3>
            <p><code>tcsocial_network_scheduler_import_columns()</code> mantiene alias para <code>content_id</code>, <code>providers</code>, <code>scheduled_at</code>, <code>priority</code> y <code>title</code>. <code>tcsocial_network_handle_scheduler_export()</code> expone cuatro modos: <code>template</code>, <code>content</code>, <code>agenda</code> e <code>history</code>. Los CSV se transmiten como descargas; no cambies los encabezados públicos sin una migración documentada.</p>
            <h3>6.2 Fases de importación desde Calendario</h3>
            <p><code>tcsocial_network_handle_scheduler_import_preview()</code> valida la carga de archivo, tipo <code>csv/txt/tsv</code>, tamaño máximo de 2 MB y estructura. Guarda los datos interpretados en un transitorio de usuario durante 20 minutos. <code>tcsocial_network_handle_scheduler_import_confirm()</code> exige nonce y token de previsualización, ignora filas erróneas y programa o reprograma las válidas. Preserva ambas fases al corregir importadores: <strong>previsualizar no puede programar tareas</strong>.</p>

            <h2 id="tcsocial-dev-autotest">7. Auto Test y diagnóstico seguro</h2>
            <h3>7.1 Contrato de Auto Test</h3>
            <p><code>includes/class-auto-test.php</code> implementa <code>TCSocial_Auto_Test::run_suite()</code> y <code>admin_post_tcsocial_run_auto_test</code>. Se ejecutan 16 casos: 15 verificaciones de disponibilidad y una omisión expresa de operaciones reales. El historial de dos resultados por usuario se conserva en el transitorio <code>tcsocial_auto_test_{user_id}</code> durante 30 días; <code>run_suite()</code> no guarda resultados por sí solo.</p>
            <p>Solo se permiten consultas MySQL <code>SELECT 1</code>, presencia de tablas y lectura limitada <code>SELECT id ... LIMIT 1</code>. No ejecuta <code>dbDelta()</code>, publica, agenda, descifra tokens ni hace HTTP. Un informe completamente correcto no acredita permisos OAuth ni disponibilidad de las plataformas.</p>
            <h3>7.2 Protección de secretos y privacidad</h3>
            <p><code>tcsocial_network_encrypt_secret()</code> y <code>tcsocial_network_decrypt_secret()</code> protegen tokens con las claves del sitio. Nunca escribas credenciales, <code>access_token_enc</code>, secretos descifrados ni cabeceras de autenticación en logs o informes; utiliza estados genéricos y mensajes saneados. Las URLs de atribución de visitas y métricas se gestionan en <code>core.php</code>.</p>
            <h3>7.3 Diagnóstico de incidencias</h3>
            <ul>
                <li><strong>Ruta o proveedor ausente:</strong> verifica orden de carga, filtros registrados y que el módulo funciona sin el monolito.</li>
                <li><strong>Tabla ausente:</strong> revisa activación, versión de esquema y errores de <code>dbDelta()</code>; haz una copia antes de repararla.</li>
                <li><strong>Tarea atascada:</strong> inspecciona WP-Cron, estado <code>seo_social_schedule</code> y error registrado; no dupliques manualmente el envío.</li>
                <li><strong>API rechazada:</strong> comprueba autorizaciones de red sin mostrar tokens; Auto Test no realiza pruebas remotas.</li>
                <li><strong>CSV erróneo:</strong> contrasta los alias del esquema y el paso de simulación antes de tocar el programador.</li>
            </ul>

            <h2 id="tcsocial-dev-workflow">8. Desarrollo, validación y despliegue</h2>
            <h3>8.1 Procedimiento para futuras mejoras</h3>
            <ol>
                <li>Localiza la función y sus llamadas en <code>taxocommerce-social/includes/</code>; comprueba las dependencias antes de modificarla.</li>
                <li>Registra o reutiliza un Issue en GitHub, crea una rama y limita el cambio al plugin independiente.</li>
                <li>Conserva opciones, tablas, filtros, nonces y compatibilidad con Core y sin Core. No añadas nuevas funciones al monolito.</li>
                <li>Actualiza el manual de usuario cuando cambien pantallas o flujos, y el técnico cuando cambien contratos, rutas o persistencia.</li>
                <li>Actualiza <code>CHANGELOG.md</code>, cabecera de versión y <code>readme.txt</code>; conserva <code>changelog.txt</code> histórico.</li>
                <li>Ejecuta CI, revisa PR y realiza merge a STAGING solo con pruebas sin bloqueantes; verifica la interfaz y el Auto Test en WordPress real.</li>
                <li>Prepara ZIP limpio para WordPress.org. No despliegues a PRODUCCIÓN sin autorización expresa.</li>
            </ol>
            <h3>8.2 Pruebas automatizadas y limitaciones</h3>
            <p>El workflow <code>.github/workflows/taxocommerce-social.yml</code> valida PHP 7.4 y 8.4, bootstrap, tablas históricas, pruebas offline y empaquetado. El smoke test <code>tests/auto-test-manual-smoke.php</code> comprueba los controles, navegación y ausencia de llamadas externas/escrituras. Los ZIP deben excluir <code>tests/</code>, archivos auxiliares y <code>.ftp-deploy-sync-state.json</code>, manteniendo <code>readme.txt</code>.</p>
            <p><strong>Pruebas pendientes de entorno:</strong> los smoke tests no sustituyen una instalación WordPress limpia, una ejecución de WP-Cron, una importación CSV validada ni una publicación de prueba autorizada. Todo ello debe probarse en STAGING con datos y cuentas seguros antes de solicitar un despliegue real.</p>
            <p><a href="#tcsocial-manual-index">Volver al índice</a></p>
        </section>
    </div>
    <?php
}
