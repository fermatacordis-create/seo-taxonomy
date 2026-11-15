## 0.1.5 — 2026-11-15 — Pestaña activa destacada (#2720)

- La pestaña activa del menú de Accounting y la subpestaña de Hacienda se distinguen con fondo azul y texto blanco, con estado de foco visible al navegar con teclado.
- CSS propio, cargado únicamente en la pantalla del plugin mediante admin_enqueue_scripts; sin cambiar las otras pantallas de WordPress, el código fiscal o las traducciones.
- Prueba de regresión para encolado y marcado visual de la navegación. Sin cambios funcionales ni de datos.

## 0.1.4 — 2026-11-15 — Preparación fiscal acumulada y sincronización opcional (#2707)

- Hacienda muestra en los modelos 303 y 390 los importes acumulados del periodo (bases de ventas y compras, IVA repercutido, soportado deducible y diferencia indicativa) calculados desde el registro fiscal actualizado al abrir la pestaña. No se confunden con las casillas oficiales ni con la deuda tributaria final.
- Incorporada importación periódica **opcional, desactivada por defecto** de pedidos pagados desde WooCommerce mediante WP-Cron cada hora, con un máximo de tres bloques de 25 pedidos por ejecución. Se guarda cursor, último chequeo y fecha de última vuelta completa; no se confunde la importación con la validación fiscal.
- Los pedidos importados y devoluciones permanecen en borrador. No se editan pedidos, facturas ni asientos validados. El plugin no importa automáticamente facturas de proveedores; cada factura debe registrarse y verificarse.
- Hacienda indica el estado de sincronización y si está desactivada o todavía no se ha completado. Aviso sobre el desglose por tipos impositivos, compensaciones de periodos anteriores y otros datos fiscales no recogidos todavía.
- Corregida la tabla de Autocheck para mostrar los resultados devueltos por la función de pruebas.
- Pruebas de bases de cálculo y paginación del proceso automático en CI, documentación WordPress.org y verificación con Plugin Check.
- Sin PDF adicional ni integración de envío a la AEAT. El resultado es una herramienta preparatoria, no una autoliquidación oficial completa.

## 0.1.3 — 2026-11-15 — Preparación fiscal sin PDFs ni envíos (#2702)

- En Hacienda se incorpora un control de cobertura por ejercicio y, para el modelo 303, por trimestre: total de asientos, pendientes de validar, tratamientos fiscales especiales, referencias documentales ausentes y NIF no informados (estos últimos solo orientativos).
- Las alertas distinguen un periodo vacío de una declaración a cero y advierten de importaciones incompletas, fechas de inicio y operaciones no incluidas en el registro.
- Accesos a revisión de ventas y compras, aclaración de la posible aplicación de los modelos 130 y 200 según situación fiscal y enlace a la información oficial de la AEAT.
- Se conservan los libros e informes CSV/JSON existentes. Ningún PDF adicional, envío a Hacienda, generación de modelo oficial o modificación de pedidos/asientos.
- Pruebas de regresión de periodos y recuentos; queda por verificar funcionamiento real y ausencia de errores en STAGING.

## 0.1.2 — 2026-11-14 — Pestaña Hacienda y descargas preparatorias (#2687)

- Nueva pestaña «Hacienda» con subpestañas de los cuatro libros registro de IVA y modelos 303, 390, 347, 349 y 200. Mantiene compatible la antigua ruta de «Declaraciones fiscales».
- Las pestañas de libros de IVA y 303 enlazan además a las **plantillas XLSX oficiales vacías** que publica la AEAT para personas jurídicas y formato general. El plugin no las rellena.
- Cada vista dispone de informe interno descargable en CSV/JSON con advertencia inequívoca **«borrador no presentable»** y enlace al trámite o documentación oficial de la AEAT. No se ofrecen falsos formatos .303/.390/.347/.349/.200 ni XLSX Pre303/LSI que no se ajusten al diseño oficial.
- Modelo 303: selección trimestral; modelo 390: resumen anual. Ambos calculan importes orientativos de operaciones nacionales validadas mediante agregación SQL de **todas las filas** del periodo, sin el límite histórico de 400 filas del panel de Resumen; borradores y regímenes no nacionales se indican para revisión.
- Libros/347/349/200: vista previa de hasta 20 movimientos y exportación paginada de hasta 20 000 filas por vista. Bloquea exportaciones de mayor tamaño en lugar de generar informes incompletos. No computa umbrales, claves tributarias ni contabilidad completa.
- Permisos WordPress, nonce, parámetros controlados, protección CSV y JSON y enlaces oficiales de la AEAT. Sin conexión remota ni envíos; sin modificar pedidos WooCommerce, facturas originales, proveedores u otros plugins.
- Actualizaciones de i18n ES/EN/FR/DE/NL, pruebas de regresión y Plugin Check. Los informes no sustituyen la validación por asesoría profesional ni una presentación efectiva.

## 0.1.1 — 2026-11-14 — Facturas originales de compras y proveedores (#2681)

- Las compras, gastos, activos e intracomunitarias registradas manualmente ahora permiten adjuntar uno o varios PDF originales del proveedor, con enlaces de descarga protegidos y estado pendiente si no existe documento digital.
- Validación de subida de WordPress, firma PDF, 10 MB máximo, permisos `manage_woocommerce` y nonces. Los PDF se cifran mediante AES-256-GCM, se archivan como archivos opacos con nombres aleatorios y solo pueden recuperarse por una descarga autenticada, verificando integridad y relación con el asiento.
- Nueva tabla de documentos exclusiva del plugin, migración no destructiva desde 0.1.0. No cambia importación WooCommerce, ventas, proveedores, importes, IVA, validaciones ni exportaciones; tampoco extrae automáticamente datos de las facturas ni genera documentos de Hacienda.
- Pruebas aisladas de cifrado/descifrado, alteración, permisos y vínculos, PHP y WordPress Plugin Check. La copia de seguridad debe incluir los archivos cifrados, la BBDD y las claves salt de WordPress para preservar el descifrado.

## 0.1.0 — 2026-11-13 — Initial fiscal register (#2644)

- Independent WooCommerce accounting plugin with native WordPress admin, optional TaxoCommerce Core menu, own table and configuration.
- Manual, batched and repeat-safe import of paid WooCommerce orders after the merchant's configurable fiscal start date.
- Draft-only imported sales and refunds, to be reconciled with issued invoices and VAT before validation.
- Manual received invoices and expense, asset and intracommunity categorisation with tax bases, VAT, deductible proportion, issuer and invoice reference.
- Monthly domestic VAT working balance, provisional Model 303 view and CSV/JSON internal exports.
- Read-only Autocheck for actual missing invoices, drafts, special-treatment operations and schema health.
- Strict privilege/nonce checks and no modifications to source orders, suppliers, billing, logistics or production data.
- No tax submissions to AEAT; initial release excludes official format exports, comprehensive books and automatic deduction policy.
