<?php
/**
 * In-plugin user and developer documentation for TaxoCommerce Suppliers.
 *
 * This screen is deliberately read-only. Keep instructions consistent with
 * TCSUP_Admin, TCSUP_CSV, TCSUP_Products, TCSUP_Purchasing and TCSUP_Schema.
 *
 * @package TaxoCommerceSuppliers
 */
defined( 'ABSPATH' ) || exit;

final class TCSUP_Manual {
	public static function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos para consultar el manual.', 'taxocommerce-suppliers' ) );
		}
		?>
		<div class="tcsup-manual">
			<h1><?php esc_html_e( 'Manual de TaxoCommerce Suppliers', 'taxocommerce-suppliers' ); ?></h1>
			<p><?php
			// translators: %s is the currently installed Suppliers plugin version.
			echo wp_kses_post( sprintf( __( 'Versión %s. Guía de operación y referencia para futuros desarrolladores del <strong>plugin independiente</strong>. Abrir esta pestaña no importa CSV, modifica precios ni ejecuta compras.', 'taxocommerce-suppliers' ), esc_html( TAXOCOMMERCE_SUPPLIERS_VERSION ) ) );
			?></p>
			<nav class="tcsup-manual-index" aria-label="<?php echo esc_attr__( 'Índice de la documentación', 'taxocommerce-suppliers' ); ?>">
				<h2><?php esc_html_e( 'Índice', 'taxocommerce-suppliers' ); ?></h2>
				<strong><?php esc_html_e( 'Manual de usuario', 'taxocommerce-suppliers' ); ?></strong>
				<a href="#tcsup-user"><?php esc_html_e( 'Introducción', 'taxocommerce-suppliers' ); ?></a>
				<a href="#tcsup-user-tabs"><?php esc_html_e( 'Pestañas y controles', 'taxocommerce-suppliers' ); ?></a>
				<a href="#tcsup-user-csv"><?php esc_html_e( 'Importar un CSV', 'taxocommerce-suppliers' ); ?></a>
				<a href="#tcsup-user-templates"><?php esc_html_e( 'Plantillas', 'taxocommerce-suppliers' ); ?></a>
				<a href="#tcsup-user-inventory"><?php esc_html_e( 'Inventario y aceptación', 'taxocommerce-suppliers' ); ?></a>
				<a href="#tcsup-user-providers"><?php esc_html_e( 'Gestión de proveedores', 'taxocommerce-suppliers' ); ?></a>
				<a href="#tcsup-user-prices"><?php esc_html_e( 'Precios', 'taxocommerce-suppliers' ); ?></a>
				<a href="#tcsup-user-images"><?php esc_html_e( 'Imágenes', 'taxocommerce-suppliers' ); ?></a>
				<a href="#tcsup-user-purchasing"><?php esc_html_e( 'Compras', 'taxocommerce-suppliers' ); ?></a>
				<a href="#tcsup-user-problems"><?php esc_html_e( 'Incidencias', 'taxocommerce-suppliers' ); ?></a>
				<strong><?php esc_html_e( 'Manual técnico / desarrolladores', 'taxocommerce-suppliers' ); ?></strong>
				<a href="#tcsup-dev"><?php esc_html_e( 'Arquitectura', 'taxocommerce-suppliers' ); ?></a>
				<a href="#tcsup-dev-files"><?php esc_html_e( 'Componentes', 'taxocommerce-suppliers' ); ?></a>
				<a href="#tcsup-dev-data"><?php esc_html_e( 'Datos y claves', 'taxocommerce-suppliers' ); ?></a>
				<a href="#tcsup-dev-import"><?php esc_html_e( 'Motor CSV', 'taxocommerce-suppliers' ); ?></a>
				<a href="#tcsup-dev-products"><?php esc_html_e( 'WooCommerce y fuentes', 'taxocommerce-suppliers' ); ?></a>
				<a href="#tcsup-dev-hooks"><?php esc_html_e( 'API y hooks', 'taxocommerce-suppliers' ); ?></a>
				<a href="#tcsup-dev-security"><?php esc_html_e( 'Seguridad', 'taxocommerce-suppliers' ); ?></a>
				<a href="#tcsup-dev-tests"><?php esc_html_e( 'Pruebas y evolución', 'taxocommerce-suppliers' ); ?></a>
			</nav>

			<h1 id="tcsup-user"><?php esc_html_e( 'Manual de usuario', 'taxocommerce-suppliers' ); ?></h1>
			<section class="tcsup-manual-section">
				<h2><?php esc_html_e( '1. Objetivo, acceso y precauciones', 'taxocommerce-suppliers' ); ?></h2>
				<h3><?php esc_html_e( '1.1 Qué resuelve', 'taxocommerce-suppliers' ); ?></h3>
				<p><?php echo wp_kses_post( __( 'Suppliers recibe catálogos CSV facilitados por proveedores, los normaliza y mantiene en un inventario intermedio. Permite revisar cada referencia antes de crear o enlazar productos WooCommerce, corregir los costes, conservar referencias de imágenes externas y consultar qué proveedor corresponde a una venta.', 'taxocommerce-suppliers' ) ); ?></p>
				<h3><?php esc_html_e( '1.2 Dónde se encuentra', 'taxocommerce-suppliers' ); ?></h3>
				<p><?php echo wp_kses_post( __( 'Abre <strong>TaxoCommerce Core → Suppliers</strong> si Core está activo, o el menú independiente <strong>TaxoCommerce Suppliers</strong>. Su dirección administrativa es <code>admin.php?page=taxocommerce-suppliers</code>. Se requiere una cuenta autorizada para gestionar WooCommerce o las opciones del sitio.', 'taxocommerce-suppliers' ) ); ?></p>
				<h3><?php esc_html_e( '1.3 Antes de operar', 'taxocommerce-suppliers' ); ?></h3>
				<p><?php echo wp_kses_post( __( 'Haz una copia de seguridad de las tablas de proveedores y de la base de datos antes de una importación importante. Importar un CSV <strong>no publica productos de inmediato</strong>, pero aceptar una fila sí puede crear un producto o modificar otro ya vinculado. Trabaja primero con pocas referencias y comprueba país, proveedor, SKU, precios e IVA. El módulo no realiza scraping ni descarga catálogos de sitios web.', 'taxocommerce-suppliers' ) ); ?></p>
			</section>

			<section id="tcsup-user-tabs" class="tcsup-manual-section">
				<h2><?php esc_html_e( '2. Pestañas y resultados de las acciones', 'taxocommerce-suppliers' ); ?></h2>
				<div class="tcsup-manual-scroll"><table class="widefat striped">
					<thead><tr><th><?php echo wp_kses_post( __( 'Pestaña', 'taxocommerce-suppliers' ) ); ?></th><th><?php echo wp_kses_post( __( 'Qué permite', 'taxocommerce-suppliers' ) ); ?></th><th><?php echo wp_kses_post( __( 'Qué se modifica y qué no', 'taxocommerce-suppliers' ) ); ?></th></tr></thead>
					<tbody>
						<tr><td><?php echo wp_kses_post( __( 'CSV Import', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Subir, analizar, asignar columnas e importar.', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Guarda el CSV original e inserta o actualiza filas del catálogo intermedio; no publica productos.', 'taxocommerce-suppliers' ) ); ?></td></tr>
						<tr><td><?php echo wp_kses_post( __( 'CSV Templates', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Descargar cabecera canónica, crear plantillas desde un archivo JSON/CSV/TXT o texto pegado, descargar, consultar y eliminar plantillas personalizadas.', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Eliminar una plantilla personalizada borra su definición, no las filas ya importadas.', 'taxocommerce-suppliers' ) ); ?></td></tr>
						<tr><td><?php echo wp_kses_post( __( 'Inventory', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Filtrar filas, aceptar, revisar, descartar o devolver a pendiente.', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( '<strong>Aceptar</strong> puede escribir un producto WooCommerce y metadatos; las otras decisiones modifican el estado de selección.', 'taxocommerce-suppliers' ) ); ?></td></tr>
						<tr><td><?php echo wp_kses_post( __( 'Gestión de proveedores', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Ver los proveedores importados, crear y editar sus fichas privadas, contactos, direcciones, plazos y observaciones.', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Solo modifica la tabla de fichas, nunca productos del inventario ni pedidos WooCommerce.', 'taxocommerce-suppliers' ) ); ?></td></tr>
						<tr><td><?php echo wp_kses_post( __( 'Prices', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Editar costes de proveedor, IVA, moneda, márgenes y precios Woo.', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Guardar actualiza los datos del proveedor; solo aplica precios WooCommerce cuando se marca <em>Apply Woo prices</em>.', 'taxocommerce-suppliers' ) ); ?></td></tr>
						<tr><td><?php echo wp_kses_post( __( 'External Images', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Editar URL y modo de imagen por referencia.', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Guarda referencias y su estado; no descarga archivos a Media.', 'taxocommerce-suppliers' ) ); ?></td></tr>
						<tr><td><?php echo wp_kses_post( __( 'Purchasing', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Ver pedidos activos y sus posibles proveedores.', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Consulta pedidos y presenta enlaces externos; no compra automáticamente ni modifica estados de pedidos.', 'taxocommerce-suppliers' ) ); ?></td></tr>
						<tr><td><?php echo wp_kses_post( __( 'Manual', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Consultar estas dos guías.', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Solo lectura.', 'taxocommerce-suppliers' ) ); ?></td></tr>
						<tr><td><?php echo wp_kses_post( __( 'Autotest', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Comprobar componentes y configuraciones bajo demanda.', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Solo diagnóstico; no repara tablas ni procesa pedidos.', 'taxocommerce-suppliers' ) ); ?></td></tr>
					</tbody>
				</table></div>
			</section>

            <section id="tcsup-user-providers" class="tcsup-manual-section">
                <h2><?php esc_html_e( 'Gestión de proveedores', 'taxocommerce-suppliers' ); ?></h2>
                <p><?php echo esc_html( __( 'La lista se crea automáticamente con los nombres distintos de proveedor que figuran en el inventario intermedio. Al importar el primer producto de un proveedor nuevo, su ficha aparecerá sin datos y podrás completarla. No se dan de alta proveedores ajenos al catálogo.', 'taxocommerce-suppliers' ) ); ?></p>
                <p><?php echo esc_html( __( 'Pulsa Crear ficha o Editar para registrar razón social, NIF, contactos comerciales, teléfonos, correos, dirección, plazo habitual de entrega y observaciones internas. Son datos privados de administración y no se publican en WooCommerce.', 'taxocommerce-suppliers' ) ); ?></p>
                <p><?php echo esc_html( __( 'Eliminar ficha borra únicamente los datos de contacto. Si el proveedor continúa en el inventario, seguirá visible y se podrá volver a rellenar. Eliminar la ficha no borra ni modifica importaciones, productos, precios o pedidos.', 'taxocommerce-suppliers' ) ); ?></p>
				<p><?php echo esc_html( __( 'Para editar muchas fichas, descarga el CSV desde Gestión de proveedores. El archivo incluye también proveedores sin ficha. Rellena las columnas conservando los nombres de proveedor y vuelve a importarlo. Se validan las identidades existentes; los nombres desconocidos se omiten. Los campos vacíos sobrescriben los valores anteriores. La importación se procesa por bloques y permite detenerse sin revertir los bloques ya completados.', 'taxocommerce-suppliers' ) ); ?></p>
            </section>
			<section id="tcsup-user-csv" class="tcsup-manual-section">
				<h2><?php esc_html_e( '3. Importación CSV paso a paso', 'taxocommerce-suppliers' ); ?></h2>
				<h3><?php esc_html_e( '3.1 Analizar el archivo', 'taxocommerce-suppliers' ); ?></h3>
				<ol>
					<li><?php echo wp_kses_post( __( 'En <strong>CSV Import</strong>, selecciona una plantilla y escribe el nombre del proveedor. Las plantillas pueden proponer uno.', 'taxocommerce-suppliers' ) ); ?></li>
					<li><?php echo wp_kses_post( __( 'Selecciona un archivo <strong>.csv</strong> y pulsa <strong>Analyze CSV</strong>. El módulo admite separadores de punto y coma, coma o tabulador; intenta detectar el apropiado.', 'taxocommerce-suppliers' ) ); ?></li>
					<li><?php echo wp_kses_post( __( 'Comprueba el nombre del archivo, el separador detectado y la vista previa de hasta ocho filas. El CSV debe disponer de cabecera y tener un tamaño admitido por WordPress.', 'taxocommerce-suppliers' ) ); ?></li>
					<li><?php echo wp_kses_post( __( 'En <strong>Review normalization</strong>, asigna las columnas de origen a los campos de TaxoCommerce. Para una importación normal son obligatorios el identificador externo y el nombre del producto. La plantilla VEVOR utiliza su propia normalización.', 'taxocommerce-suppliers' ) ); ?></li>
					<li><?php echo wp_kses_post( __( 'Opcionalmente introduce un nombre para guardar la asignación como plantilla reutilizable y pulsa <strong>Import into intermediate inventory</strong>.', 'taxocommerce-suppliers' ) ); ?></li>
				</ol>
				<h3><?php esc_html_e( '3.2 Resultado y comprobación', 'taxocommerce-suppliers' ); ?></h3>
				<p><?php echo wp_kses_post( __( 'Al finalizar, el aviso distingue filas procesadas, nuevas, actualizadas, sin cambios y con error. El resultado se examina en <strong>Inventory</strong> (o en <strong>Prices</strong> para la plantilla de actualización de costes). Revisa las referencias sin país, sin SKU o con conflictos antes de aceptarlas.', 'taxocommerce-suppliers' ) ); ?></p>
				<h3><?php esc_html_e( '3.3 Actualización exclusiva de precios', 'taxocommerce-suppliers' ); ?></h3>
				<p><?php echo wp_kses_post( __( 'Selecciona <strong>Supplier Price Update CSV</strong> cuando solo debas renovar costes de referencias existentes. Exige una identidad (ID externo o SKU) y al menos una columna de precio; para VEVOR exige <strong>ID externo y país</strong>. Este modo <strong>no crea filas nuevas</strong>. Los registros no identificados o ambiguos se contabilizan como error.', 'taxocommerce-suppliers' ) ); ?></p>
				<h3><?php esc_html_e( '3.4 Casos VEVOR', 'taxocommerce-suppliers' ); ?></h3>
				<p><?php echo wp_kses_post( __( 'Para importar un CSV regional de VEVOR, selecciona el proveedor en <strong>CSV Import</strong>. El módulo reconoce las columnas <code>Name</code>, <code>URL to product</code>, <code>categoryPath</code>, <code>URL to image</code>, <code>Currency</code> y <code>Sales</code> y determina el mercado por la URL o la columna Country. No se precarga ninguna plantilla VEVOR. Cada mercado conserva su identidad sin fusionar productos por SKU.', 'taxocommerce-suppliers' ) ); ?></p>
				<h3><?php esc_html_e( '3.5 Limitaciones reales', 'taxocommerce-suppliers' ); ?></h3>
				<p><?php echo wp_kses_post( __( 'La importación se ejecuta al confirmar el formulario: no se ofrece aquí una cola de scraping o importación XLS/XLSX. Una sesión de análisis caduca a las dos horas; si aparece <em>session expired</em>, vuelve a subir el fichero. No cierres ni repitas la operación sobre un catálogo grande sin comprobar antes su resultado.', 'taxocommerce-suppliers' ) ); ?></p>
			</section>

			<section id="tcsup-user-templates" class="tcsup-manual-section">
				<h2><?php esc_html_e( '4. Plantillas CSV', 'taxocommerce-suppliers' ); ?></h2>
				<p><?php echo wp_kses_post( __( 'La pestaña <strong>CSV Templates</strong> muestra únicamente las plantillas guardadas en esta instalación de WordPress; permite crearlas desde un archivo JSON/CSV/TXT o texto pegado, consultarlas, descargarlas y eliminarlas. <strong>CSV Import</strong> ofrece los formatos genéricos CSV canónico y actualización de precios. No se suministran plantillas de proveedores precargadas.', 'taxocommerce-suppliers' ) ); ?></p>
				<h3><?php esc_html_e( '4.1 Guardar un mapeo', 'taxocommerce-suppliers' ); ?></h3>
				<p><?php echo wp_kses_post( __( 'Durante el análisis CSV, asigna los campos, abre <strong>Save this mapping as a reusable template</strong> y escribe un nombre. Se conservan los nombres de las columnas de origen, no su posición fija: si un proveedor reordena columnas sin cambiar sus nombres, la plantilla puede reutilizarse.', 'taxocommerce-suppliers' ) ); ?></p>
				<h3><?php esc_html_e( '4.2 Crear una plantilla sin importar productos', 'taxocommerce-suppliers' ); ?></h3>
				<p><?php echo wp_kses_post( __( 'En <strong>CSV Templates</strong>, despliega <strong>Crear nueva plantilla de proveedor</strong>. Escribe un nombre y, opcionalmente, el proveedor. Sube una definición JSON, CSV o TXT (máximo 64 KiB) o pega su contenido. Las definiciones usan pares de <code>campo interno</code> y <code>columna de origen</code>; al menos <code>proveedor_id_externo</code> y <code>nombre</code> deben estar presentes.', 'taxocommerce-suppliers' ) ); ?></p>
				<p><?php echo wp_kses_post( __( 'Ejemplo JSON: <code>{"mapping":{"proveedor_id_externo":"Supplier ID","nombre":"Product Name","precio_con_iva":"Price"}}</code>. Para CSV/TXT utiliza dos columnas separadas por coma o punto y coma. Guarda la plantilla, selecciónala posteriormente en <strong>CSV Import</strong> y revisa la asignación antes de importar. Puedes descargar el JSON de respaldo junto al nombre de cualquier plantilla personalizada.', 'taxocommerce-suppliers' ) ); ?></p>
				<h3><?php esc_html_e( '4.3 Eliminar una plantilla', 'taxocommerce-suppliers' ); ?></h3>
				<p><?php echo wp_kses_post( __( 'La acción <strong>Delete</strong> solo aparece para las personalizadas. No elimina productos, filas del inventario ni archivos CSV ya almacenados. Después de cambiar un formato de proveedor, verifica el mapeo antes de importar de nuevo.', 'taxocommerce-suppliers' ) ); ?></p>
			</section>

			<section id="tcsup-user-inventory" class="tcsup-manual-section">
				<h2><?php esc_html_e( '5. Inventario intermedio y decisiones', 'taxocommerce-suppliers' ); ?></h2>
				<h3><?php esc_html_e( '5.1 Localizar una referencia', 'taxocommerce-suppliers' ); ?></h3>
				<p><?php echo wp_kses_post( __( 'En <strong>Inventory</strong>, filtra por proveedor, decisión, país y texto (nombre, SKU o ID externo). La lista muestra 50 filas por página con precio, stock, estado de selección, estado de sincronización y, si existe, enlace al producto WooCommerce.', 'taxocommerce-suppliers' ) ); ?></p>
				<h3><?php esc_html_e( '5.2 Qué significa cada decisión', 'taxocommerce-suppliers' ); ?></h3>
				<div class="tcsup-manual-scroll"><table class="widefat striped">
					<thead><tr><th><?php echo wp_kses_post( __( 'Acción', 'taxocommerce-suppliers' ) ); ?></th><th><?php echo wp_kses_post( __( 'Resultado', 'taxocommerce-suppliers' ) ); ?></th><th><?php echo wp_kses_post( __( 'Precaución', 'taxocommerce-suppliers' ) ); ?></th></tr></thead>
					<tbody>
						<tr><td><?php echo wp_kses_post( __( 'Review', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Marca la fila <code>revisar</code>.', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'No resuelve automáticamente un conflicto de identidad.', 'taxocommerce-suppliers' ) ); ?></td></tr>
						<tr><td><?php echo wp_kses_post( __( 'Discard', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Marca la fila <code>descartado</code> y sincronización ignorada.', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'No equivale a borrar un producto WooCommerce ya creado.', 'taxocommerce-suppliers' ) ); ?></td></tr>
						<tr><td><?php echo wp_kses_post( __( 'Return to pending', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Devuelve una fila a <code>pendiente</code> desde la acción masiva.', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Revisa antes los datos que motivaron su descarte.', 'taxocommerce-suppliers' ) ); ?></td></tr>
						<tr><td><?php echo wp_kses_post( __( 'Accept', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Busca el producto relacionado y lo enlaza o crea uno nuevo.', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Puede modificar precio, stock y metadatos de un producto existente.', 'taxocommerce-suppliers' ) ); ?></td></tr>
					</tbody>
				</table></div>
				<h3><?php esc_html_e( '5.3 Aceptar un producto nuevo', 'taxocommerce-suppliers' ); ?></h3>
				<p><?php echo wp_kses_post( __( 'Si no se encuentra un producto existente y hay categoría WooCommerce predeterminada válida, se crea un <strong>producto simple en borrador, oculto del catálogo</strong>, asociado a esa categoría. No se publica automáticamente ni se asigna directamente a la categoría final del árbol comercial. Debes revisar y completar el producto antes de publicarlo desde WooCommerce.', 'taxocommerce-suppliers' ) ); ?></p>
				<h3><?php esc_html_e( '5.4 Aceptar uno existente', 'taxocommerce-suppliers' ); ?></h3>
				<p><?php echo wp_kses_post( __( 'El módulo intenta localizar una vinculación previa por ID de objeto, metadatos de catálogo/proveedor o SKU compatible. Puede actualizar precios calculados con los márgenes configurados, existencia, marca, referencias externas y metadatos del proveedor. Confirma que el vínculo es correcto antes de aceptar; las referencias VEVOR por mercado no deben tratarse como un único producto.', 'taxocommerce-suppliers' ) ); ?></p>
				<h3><?php esc_html_e( '5.5 Acciones masivas', 'taxocommerce-suppliers' ); ?></h3>
				<p><?php echo wp_kses_post( __( 'Marca las filas deseadas de la página actual, elige <strong>Accept / link to WooCommerce</strong>, <strong>Mark for review</strong>, <strong>Discard</strong> o <strong>Return to pending</strong> y pulsa <strong>Apply</strong>. Consulta los recuentos de procesadas y errores. No interpretes el selector <em>Select all rows on this page</em> como selección de todo el catálogo.', 'taxocommerce-suppliers' ) ); ?></p>
			</section>

			<section id="tcsup-user-prices" class="tcsup-manual-section">
				<h2><?php esc_html_e( '6. Precios, impuestos y márgenes', 'taxocommerce-suppliers' ); ?></h2>
				<h3><?php esc_html_e( '6.1 Configurar márgenes', 'taxocommerce-suppliers' ); ?></h3>
				<p><?php echo wp_kses_post( __( 'En <strong>Prices</strong>, guarda los porcentajes <strong>Default sale margin</strong> y <strong>Default regular margin</strong>. Los valores de instalación son 20 % y 45 %, respectivamente. Al aceptar un producto con coste numérico se calcula el precio mediante <em>coste base × (1 + margen/100)</em>; el módulo toma preferentemente <code>precio_con_iva</code> y, si no está, <code>precio_sin_iva</code>. Comprueba siempre que ese origen y el tratamiento fiscal sean los adecuados.', 'taxocommerce-suppliers' ) ); ?></p>
				<h3><?php esc_html_e( '6.2 Corregir una fila', 'taxocommerce-suppliers' ); ?></h3>
				<p><?php echo wp_kses_post( __( 'La lista de 40 filas por página permite modificar <strong>Excl. tax</strong>, <strong>Incl. tax</strong>, <strong>Tax %</strong> y <strong>Currency</strong>. Pulsar <strong>Save</strong> actualiza los datos de proveedor y sus metadatos asociados cuando existe un producto vinculado; no requiere volver a importar el CSV.', 'taxocommerce-suppliers' ) ); ?></p>
				<h3><?php esc_html_e( '6.3 Aplicar precios a WooCommerce', 'taxocommerce-suppliers' ); ?></h3>
				<p><?php echo wp_kses_post( __( 'Los campos <strong>Woo regular</strong> y <strong>Woo sale</strong> están habilitados únicamente para productos vinculados. Marca explícitamente <strong>Apply Woo prices</strong> para modificar los precios del producto WooCommerce. Si no lo marcas, solo se actualizan los datos de coste/metadatos del proveedor. Revisa moneda, impuestos y descuentos antes de guardar.', 'taxocommerce-suppliers' ) ); ?></p>
			</section>

			<section id="tcsup-user-images" class="tcsup-manual-section">
				<h2><?php esc_html_e( '7. Imágenes externas', 'taxocommerce-suppliers' ); ?></h2>
				<p><?php echo wp_kses_post( __( 'En <strong>External Images</strong>, filtra la lista y pulsa <strong>Edit</strong> sobre una referencia. Elige <strong>External references</strong>, <strong>Local Media</strong> o <strong>Inherit existing behavior</strong>. Si utilizas referencias externas, pega una URL por línea y pulsa <strong>Save external images</strong>.', 'taxocommerce-suppliers' ) ); ?></p>
				<h3><?php esc_html_e( '7.1 Diferencia entre referencia y archivo local', 'taxocommerce-suppliers' ); ?></h3>
				<p><?php echo wp_kses_post( __( 'El módulo guarda URLs y metadatos de imágenes del proveedor en una tabla propia; <strong>no descarga los archivos ni crea adjuntos en la Biblioteca de Medios</strong>. Elegir Local o Inherit desactiva las referencias externas activas de esa fila, pero no genera automáticamente nuevas imágenes locales. Comprueba los derechos de uso y la disponibilidad de las URLs en el sitio de origen.', 'taxocommerce-suppliers' ) ); ?></p>
			</section>

			<section id="tcsup-user-purchasing" class="tcsup-manual-section">
				<h2><?php esc_html_e( '8. Compras a proveedores', 'taxocommerce-suppliers' ); ?></h2>
				<p><?php echo wp_kses_post( __( 'La pestaña <strong>Purchasing</strong> consulta por defecto pedidos WooCommerce <strong>pending</strong>, <strong>on-hold</strong> y <strong>processing</strong>. Muestra número de pedido, línea, cantidad, proveedor, ID/SKU, coste y, cuando existe, enlace <strong>Purchase</strong>. También presenta los casos <em>Without source</em> para que se revise su vinculación.', 'taxocommerce-suppliers' ) ); ?></p>
				<h3><?php esc_html_e( '8.1 Cómo utiliza los proveedores', 'taxocommerce-suppliers' ); ?></h3>
				<p><?php echo wp_kses_post( __( 'Primero busca una fuente aceptada para el producto de la línea; si es una variación sin fuente, intenta usar la del producto padre. Si hay varias fuentes, presenta alternativas. <strong>Purchase</strong> abre la página del proveedor en otra pestaña: no envía pedidos, no realiza pagos ni reserva stock.', 'taxocommerce-suppliers' ) ); ?></p>
				<h3><?php esc_html_e( '8.2 Qué queda fuera', 'taxocommerce-suppliers' ); ?></h3>
				<p><?php echo wp_kses_post( __( 'La valoración de peso, portes y coste de entrega se realiza en <strong>TaxoCommerce Logistics</strong>. Suppliers ofrece la relación comercial con el proveedor, pero no sustituye la gestión del pedido en WooCommerce.', 'taxocommerce-suppliers' ) ); ?></p>
			</section>

			<section id="tcsup-user-problems" class="tcsup-manual-section">
				<h2><?php esc_html_e( '9. Autotest y resolución de problemas', 'taxocommerce-suppliers' ); ?></h2>
				<div class="tcsup-manual-scroll"><table class="widefat striped">
					<thead><tr><th><?php echo wp_kses_post( __( 'Situación', 'taxocommerce-suppliers' ) ); ?></th><th><?php echo wp_kses_post( __( 'Comprobación inicial', 'taxocommerce-suppliers' ) ); ?></th><th><?php echo wp_kses_post( __( 'Actuación segura', 'taxocommerce-suppliers' ) ); ?></th></tr></thead>
					<tbody>
						<tr><td><?php echo wp_kses_post( __( 'CSV rechazado', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Extensión .csv, límite de subida, permisos de almacenamiento, cabecera y delimitador.', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Corregir el fichero y volver a analizarlo; no modificar tablas directamente.', 'taxocommerce-suppliers' ) ); ?></td></tr>
						<tr><td><?php echo wp_kses_post( __( 'Sesión CSV caducada', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Han pasado dos horas o el archivo original ya no es legible.', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Repetir Analyze CSV y revisar el mapeo.', 'taxocommerce-suppliers' ) ); ?></td></tr>
						<tr><td><?php echo wp_kses_post( __( 'Errores VEVOR', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Cabeceras oficiales, segmento p_ de URL, país, dominio e identidad.', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Corregir origen y evitar mezclar países o variantes.', 'taxocommerce-suppliers' ) ); ?></td></tr>
						<tr><td><?php echo wp_kses_post( __( 'Producto no aceptado', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'WooCommerce, categoría por defecto, SKU ocupado y mensajes de conflicto.', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Resolver identidad/categoría antes de volver a aceptar.', 'taxocommerce-suppliers' ) ); ?></td></tr>
						<tr><td><?php echo wp_kses_post( __( 'Compras sin proveedor', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Estado de aceptación, <code>object_id</code> y producto/variación del pedido.', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Revisar vínculo de catálogo y producto.', 'taxocommerce-suppliers' ) ); ?></td></tr>
						<tr><td><?php echo wp_kses_post( __( 'Imágenes que no aparecen', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Modo de imagen, URLs guardadas, consumidor de imágenes externas y sitio de origen.', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Verificar referencias; Suppliers no importa archivos a Media.', 'taxocommerce-suppliers' ) ); ?></td></tr>
						<tr><td><?php echo wp_kses_post( __( 'Autotest con error', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Componentes, hooks, opciones, tablas y columnas indicadas por el control.', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Guardar evidencia y revisar despliegue antes de ejecutar migraciones.', 'taxocommerce-suppliers' ) ); ?></td></tr>
					</tbody>
				</table></div>
				<p><?php echo wp_kses_post( __( '<strong>Autotest</strong> se ejecuta con su propio botón y nonce; inspecciona disponibilidad en modo de solo lectura y muestra Correcto / Aviso / Error. No comprueba el contenido semántico de cada CSV ni garantiza que un servidor externo sirva una imagen.', 'taxocommerce-suppliers' ) ); ?></p>
			</section>

			<h1 id="tcsup-dev"><?php esc_html_e( 'Manual técnico para futuros desarrolladores', 'taxocommerce-suppliers' ); ?></h1>
			<section class="tcsup-manual-section">
				<h2><?php esc_html_e( '10. Arquitectura y límites del módulo', 'taxocommerce-suppliers' ); ?></h2>
				<p><?php echo wp_kses_post( __( '<strong>Alcance:</strong> plugin autónomo <code>taxocommerce-suppliers/</code>, con WordPress 6.5+, PHP 7.4+ y dependencia declarada de WooCommerce. TaxoCommerce Core es opcional para el menú y registro familiar. No confundir este código con el plugin SEO monolítico ni trasladar mejoras al legado.', 'taxocommerce-suppliers' ) ); ?></p>
				<p><?php echo wp_kses_post( __( '<strong>Flujo:</strong> CSV local → análisis/mapeo temporal → fila de <code>seo_proveedores_productos</code> → decisión humana → vinculación o borrador WooCommerce → metadatos de origen e imágenes externas → consulta comercial de pedidos. No existe extracción web, crawler, ejecutor externo o pedido automático dentro de Suppliers.', 'taxocommerce-suppliers' ) ); ?></p>
				<h3><?php esc_html_e( '10.1 Arranque y activación', 'taxocommerce-suppliers' ); ?></h3>
				<p><?php echo wp_kses_post( __( 'El archivo principal registra <code>taxocommerce_suppliers_activate()</code> para crear/actualizar tablas y establecer márgenes iniciales. En <code>plugins_loaded</code> (prioridad 30), <code>taxocommerce_suppliers_boot()</code> llama a <code>TCSUP_Schema::maybe_install()</code> y <code>TCSUP_Admin::boot()</code>. Las pestañas Manual y Autotest se cargan desde sus clases propias. El registro en Core se realiza con <code>taxocommerce_core_register_modules</code> y el filtro de catálogo familiar.', 'taxocommerce-suppliers' ) ); ?></p>
			</section>

			<section id="tcsup-dev-files" class="tcsup-manual-section">
				<h2><?php esc_html_e( '11. Directorios, clases y responsabilidades', 'taxocommerce-suppliers' ); ?></h2>
				<div class="tcsup-manual-scroll"><table class="widefat striped">
					<thead><tr><th><?php echo wp_kses_post( __( 'Archivo', 'taxocommerce-suppliers' ) ); ?></th><th><?php echo wp_kses_post( __( 'Clase / cometido', 'taxocommerce-suppliers' ) ); ?></th><th><?php echo wp_kses_post( __( 'Puntos de extensión y precauciones', 'taxocommerce-suppliers' ) ); ?></th></tr></thead>
					<tbody>
						<tr><td><code>taxocommerce-suppliers.php</code></td><td><?php echo wp_kses_post( __( 'Bootstrap, versión, alta en Core y API pública.', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'No introducir lógica de UI/importación dentro del bootstrap.', 'taxocommerce-suppliers' ) ); ?></td></tr>
						<tr><td><code>includes/class-tcsup-admin.php</code></td><td><?php echo wp_kses_post( __( '<code>TCSUP_Admin</code>: menú, pestañas, filtros, formularios, márgenes y avisos.', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( '<code>PAGE</code> identifica exclusivamente al plugin independiente; conservar slugs y nonces.', 'taxocommerce-suppliers' ) ); ?></td></tr>
						<tr><td><code>includes/class-tcsup-csv.php</code></td><td><?php echo wp_kses_post( __( '<code>TCSUP_CSV</code>: campos, plantillas, análisis, transformación, importación, descarga canónica.', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Respetar identidad, prevención de duplicados, VEVOR y formato CSV.', 'taxocommerce-suppliers' ) ); ?></td></tr>
						<tr><td><code>includes/class-tcsup-products.php</code></td><td><?php echo wp_kses_post( __( '<code>TCSUP_Products</code>: aceptación, WooCommerce, marcas, costes e imágenes.', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Conservar locks de aceptación, compatibilidad SKU/país y metadatos.', 'taxocommerce-suppliers' ) ); ?></td></tr>
						<tr><td><code>includes/class-tcsup-purchasing.php</code></td><td><?php echo wp_kses_post( __( '<code>TCSUP_Purchasing</code>: consulta de ventas y proveedores vinculados.', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Usar APIs de pedidos WooCommerce; no SQL directo sobre tablas de pedidos.', 'taxocommerce-suppliers' ) ); ?></td></tr>
						<tr><td><code>includes/class-tcsup-schema.php</code></td><td><?php echo wp_kses_post( __( '<code>TCSUP_Schema</code>: esquema y actualización mediante <code>dbDelta()</code>.', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Evitar migraciones destructivas y preservar tablas compartidas/legadas.', 'taxocommerce-suppliers' ) ); ?></td></tr>
						<tr><td><code>includes/class-tcsup-manual.php</code></td><td><?php echo wp_kses_post( __( '<code>TCSUP_Manual</code>: estas guías.', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Actualizar al cambiar los controles; no añadir escrituras ni peticiones externas.', 'taxocommerce-suppliers' ) ); ?></td></tr>
						<tr><td><code>includes/class-tcsup-autotest.php</code></td><td><?php echo wp_kses_post( __( '<code>TCSUP_Autotest</code>: comprobaciones locales bajo demanda.', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'No utilizar métodos de instalación, aceptación, importación ni envío.', 'taxocommerce-suppliers' ) ); ?></td></tr>
						<tr><td><?php echo wp_kses_post( '<code>assets/admin.css</code>, <code>tests/smoke.php</code>' ); ?></td><td><?php echo wp_kses_post( __( 'Presentación y regresión de la interfaz/contratos.', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Los scripts tests se excluyen del ZIP distribuible.', 'taxocommerce-suppliers' ) ); ?></td></tr>
					</tbody>
				</table></div>
			</section>

			<section id="tcsup-dev-data" class="tcsup-manual-section">
				<h2><?php esc_html_e( '12. Modelo de datos, identidad y persistencia', 'taxocommerce-suppliers' ); ?></h2>
				<h3><?php esc_html_e( '12.1 Tabla de catálogo', 'taxocommerce-suppliers' ); ?></h3>
				<p><?php echo wp_kses_post( __( '<code>{$wpdb->prefix}seo_proveedores_productos</code> (nombre lógico; prefijo real del sitio calculado por <code>TCSUP_Schema::catalog_table()</code>). Conserva <code>proveedor</code>, <code>proveedor_id_externo</code>, <code>sku</code>, <code>source_row_key</code>, <code>pais_origen</code>, nombre, marca, categoría de origen, precios, impuestos, moneda, stock, imágenes, <code>object_id</code>, hashes, estados y fechas de importación/sincronización.', 'taxocommerce-suppliers' ) ); ?></p>
				<p><?php echo wp_kses_post( __( 'La clave única definida en esquema es <code>(proveedor, pais_origen, proveedor_id_externo, source_row_key)</code>. Para datos VEVOR con país, la importación busca esa identidad compuesta y aplica una reconciliación limitada para filas heredadas sin <code>source_row_key</code>. Para otros proveedores también verifica identificador externo y SKU, y marca conflictos si conducen a filas distintas. <strong>No eliminar país o clave de variante</strong> para simplificar consultas: se producirían colisiones entre mercados.', 'taxocommerce-suppliers' ) ); ?></p>
				<h3><?php esc_html_e( '12.2 Tabla de imágenes', 'taxocommerce-suppliers' ); ?></h3>
				<p><?php echo wp_kses_post( __( '<code>{$wpdb->prefix}seo_supplier_images</code> contiene <code>supplier</code>, <code>supplier_product_id</code>, <code>supplier_sku</code>, <code>catalog_row_id</code>, <code>product_id</code>, <code>image_url</code>, <code>image_hash</code>, posición, imagen principal, estado y marcas de tiempo. La clave única es <code>(supplier, supplier_sku, image_hash)</code>. Guardar referencias externas desactiva las anteriores y activa/inserta las vigentes; cambiar a Local/Inherit desactiva referencias activas.', 'taxocommerce-suppliers' ) ); ?></p>
				<h3><?php esc_html_e( '12.3 Opciones, transients y archivos', 'taxocommerce-suppliers' ); ?></h3>
				<div class="tcsup-manual-scroll"><table class="widefat striped">
					<thead><tr><th><?php echo wp_kses_post( __( 'Identificador', 'taxocommerce-suppliers' ) ); ?></th><th><?php echo wp_kses_post( __( 'Función', 'taxocommerce-suppliers' ) ); ?></th></tr></thead>
					<tbody>
						<tr><td><code>taxocommerce_suppliers_db_version</code></td><td><?php echo wp_kses_post( __( 'Versión del esquema, actualmente <code>1.0.0</code>; <code>maybe_install()</code> comprueba tablas y puede ejecutar dbDelta.', 'taxocommerce-suppliers' ) ); ?></td></tr>
						<tr><td><code>taxocommerce_suppliers_csv_templates</code></td><td><?php echo wp_kses_post( __( 'Asignaciones guardadas en este WordPress; los formatos genéricos CSV se definen en PHP sin plantillas de proveedores precargadas.', 'taxocommerce-suppliers' ) ); ?></td></tr>
						<tr><td><?php echo wp_kses_post( '<code>taxocommerce_suppliers_sale_margin</code> / <code>taxocommerce_suppliers_regular_margin</code>' ); ?></td><td><?php echo wp_kses_post( __( 'Márgenes comerciales (por defecto 20 y 45); valores acotados por el controlador de ajustes.', 'taxocommerce-suppliers' ) ); ?></td></tr>
						<tr><td><code>tcsup_csv_{user_id}</code></td><td><?php echo wp_kses_post( __( 'Transient de análisis, cabeceras, vista previa y ruta temporal; duración de dos horas.', 'taxocommerce-suppliers' ) ); ?></td></tr>
						<tr><td><code>wp-content/uploads/taxocommerce-suppliers/originals/</code></td><td><?php echo wp_kses_post( __( 'CSV originales subidos; no están en la carpeta instalable del plugin y no se deben confundir con tests.', 'taxocommerce-suppliers' ) ); ?></td></tr>
					</tbody>
				</table></div>
				<p><?php echo wp_kses_post( __( 'La relación WooCommerce usa, entre otros, los metadatos <code>_seo_proveedor</code>, <code>_seo_proveedor_id_externo</code>, <code>_seo_proveedor_catalogo_id</code>, <code>_seo_proveedor_pais</code>, <code>_seo_proveedor_sku</code> y <code>_seo_imagenes_externas</code>. Una migración de esquema o identidades debe considerar los consumidores externos de estas claves.', 'taxocommerce-suppliers' ) ); ?></p>
			</section>

			<section id="tcsup-dev-import" class="tcsup-manual-section">
				<h2><?php esc_html_e( '13. Motor CSV y transformaciones', 'taxocommerce-suppliers' ); ?></h2>
				<h3><?php esc_html_e( '13.1 Pasos internos', 'taxocommerce-suppliers' ); ?></h3>
				<p><?php echo wp_kses_post( __( '<code>TCSUP_CSV::handle_analyze()</code> valida el fichero, lo almacena en uploads, detecta delimitador y guarda el estado por usuario. <code>handle_import()</code> comprueba sesión/plantilla, valida columnas obligatorias, sanea datos y recorre las filas. Los métodos <code>fields()</code> y <code>builtin_templates()</code> definen el contrato canónico.', 'taxocommerce-suppliers' ) ); ?></p>
				<h3><?php esc_html_e( '13.2 Modos y deduplicación', 'taxocommerce-suppliers' ); ?></h3>
				<p><?php echo wp_kses_post( __( 'En modo <code>canonical</code> se esperan las cabeceras de los campos internos; <code>mapping</code> usa nombres del fichero; <code>vevor</code> transforma las seis cabeceras oficiales, extrae el ID numérico de 12 dígitos de <code>p_</code> e infiere el país desde el dominio cuando el CSV no lo indica. <code>price_update</code> solo actualiza fuentes identificadas de manera inequívoca y no crea filas.', 'taxocommerce-suppliers' ) ); ?></p>
				<p><?php echo wp_kses_post( __( '<code>row_hash()</code> y <code>changed_fields()</code> permiten identificar cambios. Las filas existentes conservan su identidad y se actualizan; las nuevas entran con <code>estado_seleccion=pendiente</code>. En conflictos de SKU/ID, el código puede marcar <code>revisar</code> y <code>conflicto</code>. Toda modificación del importador requiere casos de prueba de reimportación, duplicación y países.', 'taxocommerce-suppliers' ) ); ?></p>
				<h3><?php esc_html_e( '13.3 Límites operativos', 'taxocommerce-suppliers' ); ?></h3>
				<p><?php echo wp_kses_post( __( 'El código actual lee el CSV local con <code>SplFileObject</code>. No añadir llamadas de red, colas automáticas o nuevos scrapers como parte de una corrección documental. La aceptación en WooCommerce se realiza por una acción distinta de importar. El registro de resultados de la pantalla muestra contadores, no constituye un historial permanente por fila.', 'taxocommerce-suppliers' ) ); ?></p>
			</section>

			<section id="tcsup-dev-products" class="tcsup-manual-section">
				<h2><?php esc_html_e( '14. Aceptación, sincronización y compras WooCommerce', 'taxocommerce-suppliers' ); ?></h2>
				<h3><?php esc_html_e( '14.1 Selección de productos', 'taxocommerce-suppliers' ); ?></h3>
				<p><?php echo wp_kses_post( __( '<code>TCSUP_Products::accept()</code> obtiene la fila y adquiere un lock MySQL con <code>GET_LOCK</code> antes de delegar a <code>accept_unlocked()</code>; se libera con <code>RELEASE_LOCK</code>. <code>existing_product()</code> busca por <code>object_id</code>, metadatos de vínculo y, cuando es seguro, SKU. Para VEVOR con país no debe reutilizarse un producto solo por SKU; el SKU interno nuevo se forma como <code>vevor-{country}-row-{id}</code>.', 'taxocommerce-suppliers' ) ); ?></p>
				<p><?php echo wp_kses_post( __( 'Si no hay coincidencia, usa <code>WC_Product_Simple</code> y crea un borrador oculto con la categoría WooCommerce predeterminada. Para fuentes ya enlazadas puede actualizar precio, stock, marca, metadatos y enlaces de imagen. Tras guardar actualiza <code>object_id</code>, <code>hash_aplicado</code>, <code>snapshot_aplicado</code> y estado de sincronización.', 'taxocommerce-suppliers' ) ); ?></p>
				<h3><?php esc_html_e( '14.2 Márgenes y existencias', 'taxocommerce-suppliers' ); ?></h3>
				<p><?php echo wp_kses_post( __( 'Los márgenes definidos en opciones se aplican sobre el precio base, priorizando <code>precio_con_iva</code>, y se redondean con la precisión de WooCommerce. Stock numérico activa gestión de existencias y cantidad; sin cantidad se determina disponibilidad según el estado textual admitido. Estos comportamientos <strong>son efectos de aceptar</strong> y deben cubrirse por pruebas de regresión antes de cualquier cambio.', 'taxocommerce-suppliers' ) ); ?></p>
				<h3><?php esc_html_e( '14.3 Consulta de compras y compatibilidad HPOS', 'taxocommerce-suppliers' ); ?></h3>
				<p><?php echo wp_kses_post( __( '<code>TCSUP_Purchasing::collect_order_lines()</code> usa <code>wc_get_orders()</code> y los métodos de pedidos/líneas WooCommerce. El filtro <code>taxocommerce_suppliers_purchasing_order_statuses</code> define los estados activos (por defecto pending, on-hold y processing). <code>sources_for_line()</code> resuelve primero la variación/producto exacto y luego el padre; <code>sources_for_product()</code> admite estados aceptado y otros legados previstos en el código. La pantalla no crea órdenes de compra.', 'taxocommerce-suppliers' ) ); ?></p>
				<p><?php echo wp_kses_post( __( 'La consulta actual usa <code>limit = -1</code> para pedidos activos, por lo que en tiendas de gran volumen conviene evaluar su coste antes de ampliar el panel. No substituir la API WooCommerce por consultas directas a <code>wp_posts</code>: debe mantenerse la compatibilidad HPOS.', 'taxocommerce-suppliers' ) ); ?></p>
			</section>

			<section id="tcsup-dev-hooks" class="tcsup-manual-section">
				<h2><?php esc_html_e( '15. API pública, hooks y contratos', 'taxocommerce-suppliers' ); ?></h2>
				<h3><?php esc_html_e( '15.1 Funciones consumibles por otros plugins', 'taxocommerce-suppliers' ); ?></h3>
				<p><?php echo wp_kses_post( __( '<code>taxocommerce_suppliers_get_sources( $product_id )</code> devuelve filas del catálogo proveedor asociadas al producto; <code>taxocommerce_suppliers_get_external_images( $product_id )</code> devuelve referencias de imágenes activas. Comprobar <code>function_exists()</code> antes de usar estas funciones desde módulos opcionales; no asumir que las imágenes son adjuntos locales.', 'taxocommerce-suppliers' ) ); ?></p>
				<h3><?php esc_html_e( '15.2 Acciones administrativas', 'taxocommerce-suppliers' ); ?></h3>
				<div class="tcsup-manual-scroll"><table class="widefat striped">
					<thead><tr><th><?php echo wp_kses_post( __( 'Hook admin-post.php', 'taxocommerce-suppliers' ) ); ?></th><th><?php echo wp_kses_post( __( 'Controlador', 'taxocommerce-suppliers' ) ); ?></th><th><?php echo wp_kses_post( __( 'Efecto', 'taxocommerce-suppliers' ) ); ?></th></tr></thead>
					<tbody>
						<tr><td><?php echo wp_kses_post( '<code>tcsup_analyze_csv</code>, <code>tcsup_import_csv</code>' ); ?></td><td><code>TCSUP_CSV</code></td><td><?php echo wp_kses_post( __( 'Subida/análisis e inserción/actualización intermedia.', 'taxocommerce-suppliers' ) ); ?></td></tr>
						<tr><td><?php echo wp_kses_post( '<code>tcsup_save_template</code>, <code>tcsup_delete_template</code>, <code>tcsup_download_template</code>' ); ?></td><td><code>TCSUP_CSV</code></td><td><?php echo wp_kses_post( __( 'Gestión y descarga de plantillas.', 'taxocommerce-suppliers' ) ); ?></td></tr>
						<tr><td><?php echo wp_kses_post( '<code>tcsup_row_action</code>, <code>tcsup_bulk_action</code>' ); ?></td><td><code>TCSUP_Products</code></td><td><?php echo wp_kses_post( __( 'Selección y aceptación de filas; la interfaz del inventario envía las acciones individuales por <code>tcsup_bulk_action</code>.', 'taxocommerce-suppliers' ) ); ?></td></tr>
						<tr><td><?php echo wp_kses_post( '<code>tcsup_price_update</code>, <code>tcsup_images_update</code>' ); ?></td><td><code>TCSUP_Products</code></td><td><?php echo wp_kses_post( __( 'Precios de fuente/Woo y vínculos de imágenes.', 'taxocommerce-suppliers' ) ); ?></td></tr>
						<tr><td><code>tcsup_settings</code></td><td><code>TCSUP_Admin</code></td><td><?php echo wp_kses_post( __( 'Guarda porcentajes de margen.', 'taxocommerce-suppliers' ) ); ?></td></tr>
					</tbody>
				</table></div>
				<p><?php echo wp_kses_post( __( 'Registro familiar: <code>taxocommerce_core_register_modules</code> y <code>taxocommerce_core_family_extensions</code>. Filtro de compras: <code>taxocommerce_suppliers_purchasing_order_statuses</code>. Mantener las firmas de métodos y nombres de acciones para no romper integraciones de la familia.', 'taxocommerce-suppliers' ) ); ?></p>
			</section>

			<section id="tcsup-dev-security" class="tcsup-manual-section">
				<h2><?php esc_html_e( '16. Seguridad, límites y compatibilidad', 'taxocommerce-suppliers' ); ?></h2>
				<h3><?php esc_html_e( '16.1 Acceso y formularios', 'taxocommerce-suppliers' ); ?></h3>
				<p><?php echo wp_kses_post( __( 'La pantalla usa la capacidad <code>manage_woocommerce</code> si WooCommerce está activo, con acceso alternativo para <code>manage_options</code>. Cada operación que modifica estado debe comprobar capacidad y nonce con <code>check_admin_referer()</code>. Las lecturas de filtros GET se saneán y las salidas de usuario pasan por las funciones de escape de WordPress.', 'taxocommerce-suppliers' ) ); ?></p>
				<h3><?php esc_html_e( '16.2 SQL, ficheros y datos de terceros', 'taxocommerce-suppliers' ); ?></h3>
				<p><?php echo wp_kses_post( __( 'Utilizar <code>$wpdb->prepare()</code>, con <code>%i</code> para identificadores cuando proceda, y evitar interpolar entradas no fiables. No asumir que una URL externa es un archivo local. Los CSV originales residen bajo <code>uploads</code> y pueden contener información comercial: antes de ampliar los endpoints, comprobar autorización, accesibilidad pública, tipos de fichero y límites. No introducir exportaciones o rutas de descarga de originales sin petición explícita y evaluación de seguridad.', 'taxocommerce-suppliers' ) ); ?></p>
				<h3><?php esc_html_e( '16.3 Dependencias y exclusiones', 'taxocommerce-suppliers' ); ?></h3>
				<p><?php echo wp_kses_post( __( 'WooCommerce es obligatorio para aceptación de productos y consultas de compras; TaxoCommerce Core es opcional para agrupar el menú. El módulo no gestiona pesos y transportes (Logistics), no ejecuta scraping (Scrappers) y no publica automáticamente productos. No modificar el plugin monolítico ni asumir que sus clases antiguas siguen disponibles.', 'taxocommerce-suppliers' ) ); ?></p>
			</section>

			<section id="tcsup-dev-tests" class="tcsup-manual-section">
				<h2><?php esc_html_e( '17. Pruebas, mantenimiento y entrega', 'taxocommerce-suppliers' ); ?></h2>
				<h3><?php esc_html_e( '17.1 Autotest', 'taxocommerce-suppliers' ); ?></h3>
				<p><?php echo wp_kses_post( __( '<code>TCSUP_Autotest::render()</code> espera una acción POST manual con nonce <code>tcsup_autotest</code>; <code>run()</code> consulta clases, registro de acciones, opciones, tablas/columnas y API de WooCommerce. <strong>Un test correcto solo confirma disponibilidad estructural</strong>, no calidad de datos, precios, stock ni éxito de importación. No utilizar para reparación automática del esquema.', 'taxocommerce-suppliers' ) ); ?></p>
				<h3><?php esc_html_e( '17.2 Matriz mínima antes de fusionar', 'taxocommerce-suppliers' ); ?></h3>
				<div class="tcsup-manual-scroll"><table class="widefat striped">
					<thead><tr><th><?php echo wp_kses_post( __( 'Grupo', 'taxocommerce-suppliers' ) ); ?></th><th><?php echo wp_kses_post( __( 'Casos recomendados en STAGING', 'taxocommerce-suppliers' ) ); ?></th></tr></thead>
					<tbody>
						<tr><td><?php echo wp_kses_post( __( 'CSV', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Cabecera válida e inválida; separadores; plantilla personalizada; reimportación; modo precio sin altas nuevas.', 'taxocommerce-suppliers' ) ); ?></td></tr>
						<tr><td><?php echo wp_kses_post( __( 'Identidad internacional', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'VEVOR US/DE/NL/FR con igual SKU, variante distinta, país ausente, ID y URL erróneos.', 'taxocommerce-suppliers' ) ); ?></td></tr>
						<tr><td><?php echo wp_kses_post( __( 'WooCommerce', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Alta como borrador oculto; vínculo existente; colisión de SKU; categoría por defecto; márgenes y stock.', 'taxocommerce-suppliers' ) ); ?></td></tr>
						<tr><td><?php echo wp_kses_post( __( 'Imágenes', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Referencias externas nuevas, duplicadas, cambio de modo y ausencia de descargas a Media.', 'taxocommerce-suppliers' ) ); ?></td></tr>
						<tr><td><?php echo wp_kses_post( __( 'Compras', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Pedidos activos sin fuente, con fuente exacta, variación que hereda fuente del padre y enlaces válidos.', 'taxocommerce-suppliers' ) ); ?></td></tr>
						<tr><td><?php echo wp_kses_post( __( 'Seguridad / WP.org', 'taxocommerce-suppliers' ) ); ?></td><td><?php echo wp_kses_post( __( 'Permisos y nonces, escape, SQL preparado, PHP lint, smoke, Plugin Check y ZIP sin <code>tests/</code>.', 'taxocommerce-suppliers' ) ); ?></td></tr>
					</tbody>
				</table></div>
				<h3><?php esc_html_e( '17.3 Proceso de cambios', 'taxocommerce-suppliers' ); ?></h3>
				<p><?php echo wp_kses_post( __( 'Antes de editar, inspeccionar clases relacionadas y compatibilidad de tablas compartidas. Cada cambio debe disponer de Issue, PR vinculado, pruebas y nota en <code>CHANGELOG.md</code>. Ajustar <code>readme.txt</code> y versión del archivo principal antes de publicar en WordPress.org, conservar el <code>changelog.txt</code> histórico y excluir los scripts <code>tests/</code> del ZIP. La validación debe hacerse en STAGING; <strong>PRODUCCIÓN requiere autorización expresa</strong>.', 'taxocommerce-suppliers' ) ); ?></p>
				<h3><?php esc_html_e( '17.4 Cómo mantener esta documentación', 'taxocommerce-suppliers' ); ?></h3>
				<p><?php echo wp_kses_post( __( 'Si una funcionalidad cambia, actualizar tanto el procedimiento operativo (botones, riesgos, resultados) como el contrato técnico (clases, tablas, hooks y pruebas). No inventar opciones que no existan en el plugin. Conservar esta estructura de dos manuales completos en la pestaña Manual para que el siguiente desarrollador no dependa de conversaciones anteriores.', 'taxocommerce-suppliers' ) ); ?></p>
			</section>
		</div>
		<?php
	}
}
