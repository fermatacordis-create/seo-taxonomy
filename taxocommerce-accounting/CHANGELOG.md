## 0.1.34 — 2026-11-21 — Complete Accounting admin translation inventory (#2917)

- Extract and translate 156 missing static gettext strings across invoice validation, WooCommerce synchronization, depreciation, supplier CSV, corrections, settlements, original documents, Hacienda and Treasury in es_ES, en_US, fr_FR, de_DE and nl_NL.
- Remove two customer-specific bank account examples from Treasury help text in favour of generic instructions. No financial logic, database data, classifications or payment behavior changed.
- Rebuild all five compiled MO catalogs; verify 467 unique entries per catalog with no missing/empty strings or printf placeholder mismatches.
- Only authorized staging deployments; production is excluded.

## 0.1.33 — 2026-11-21 — Issued invoice amount rectification reconciliation (#2926)

- Accept verified immutable rectification of gross and VAT via the optional WordPress filter, retaining third-party independence and the VAT-only path.
- On Billing's issued-rectification event, move an untouched original sale to draft with corrected figures and its linked RECT reference; preserve existing bank receipts and require new fiscal validation. Existing manual edits and overpaid settlements are not overwritten.
- A corrected invoice can differ from its unchanged historical WooCommerce order; never update orders or create a second sale or payment. STAGING and Focazul only.

## 0.1.32 — 2026-11-21 — Centralize fixed assets in Assets (#2922)

- Move new depreciable asset registration, in-service date and useful life from Expenses to Assets; current expenses remain separate.
- Show historical fixed assets from Expenses in Assets without migrating or duplicating ledger records; preserve corrections and linked payments.
- Include both new asset entries and legacy fixed expenses in the existing read-only straight-line depreciation report. Fiscal VAT investment-goods rules remain separate.
- No bank, VAT, invoice or historical balance changes. STAGING and Focazul only.

## 0.1.31 — 2026-11-21 — Opening-balance loan funding visibility in reports (#2919)

- Reclassify already-received loans linked to existing bank opening balances as dated bank inflows **for reporting only**, keeping the real monthly bank balances and every movement unchanged.
- Add a separate financial overview below the fiscal summary showing initial financing, current bank balance and outstanding loan debt. Financing does not enter sales or VAT calculations.
- Extend the general JSON export additively with `financial_summary` and the monthly cash flow, preserving `entries`. Exclude future, unlinked and mismatched opening loans from virtual receipts.
- Add isolated regression for January 2026 (362.57 EUR loan, 191.17 EUR expenses, 171.40 EUR balance) and November (17.26 EUR balance); i18n for new UI text. STAGING and Focazul only, never PRO.

## 0.1.30 — 2026-11-21 — Accounting review and settlements interface localization (#2917)

- Localize 49 previously missing administrative gettext strings covering incident review, accounting corrections and actual payment/receipt entry across es_ES, en_US, fr_FR, de_DE and nl_NL.
- Update POT, five PO and compiled MO catalogs, preserving existing accounting controls, financial calculations, stored records and transaction behavior.
- Continue reviewing the remaining missing gettext keys in subsequent batches; no production deployment.

## 0.1.29 — 2026-11-21 — Correct fully paid invoice status (#2913)

- Stop interpreting historical imported `[scope=review]` notes as a fresh pending workflow after an entry has been formally validated; retain every original note without changing VAT, deduction eligibility, or invoice data.
- Show the «Payment not linked» notice only when an actual payment balance remains unlinked, not when the settlement is complete.
- Cover fully linked, partial, missing, formal tax review, and overpayment scenarios with the isolated traffic-light regression; no actual bank or fiscal data changed.

## 0.1.23 — 2026-11-20 — Reopen validated expense as draft with audited notes (#2894)

- Fix «No data changed» when an already validated entry is intentionally returned to draft with unchanged monetary/document fields; fiscal status change is now detected and saved transactionally with a mandatory audited reason.
- Add editable Notes to the existing correction form, preserving the original notes unless changed; retain the immutable WooCommerce invoice, supplier attachment, payments, and all other rows.
- Preserve no-op protection for unchanged existing drafts, add regression coverage for status-only reversal, notes and audit log, and keep production untouched.

## 0.1.22 — 2026-11-20 — Actionable pending reviews and incidents (#2888)

- Make monthly pending-review numbers clickable; open a single record directly or a safely filtered monthly action list when more than one needs review.
- Add a separate monthly incident counter for issued-invoice mismatches (error) and verifiable document/zero-VAT warnings, each linked to existing correction or validation controls; add contextual links on ledger rows and Autocheck.
- A validated non-domestic tax treatment is not incorrectly considered pending; missing invoice warnings are suppressed when an immutable issued invoice is already linked. Keep existing amounts, invoices, forms, tax totals and payments unchanged.
- Add isolated XSS/filter/navigation regression to the mandatory STAGING preflight. No PRO deploy.

## 0.1.21 — 2026-11-20 — Expense classification interface translations (#2884)

- Localize expense classification, current expenses, depreciable fixed assets, service date, useful life and straight-line depreciation guidance in five language catalogs (ES/EN/FR/DE/NL).
- Preserve the existing `current` and `fixed_asset` values, underlying accounting calculations, records and business behavior. No production deployment.

## 0.1.20 — 2026-11-20 — Expense classification and annual fixed-asset depreciation (#2881)

- In Expenses, classify each new entry as current expense (default) or depreciable fixed asset; assets require in-service date and useful life of 1–50 years.
- Calculate read-only straight-line depreciation prorated by actual days across calendar years; rounded cumulative cents clear the final year exactly. Cost includes nonrecoverable input VAT. Show annual allowance and remaining book value under Expenses, without creating extra bank movements, tax deductions, invoice edits or VAT adjustments.
- Keep old expenses as current by default, purchases/merchandise and existing Assets tab unchanged; allow audited expense reclassification via the existing correction workflow, and export stored asset metadata.
- Upgrade own ledger schema to v7 non-destructively, add standalone regression and STAGING CI gate. No PRO deploy.

## 0.1.19 — 2026-11-20 — Treasury tab locale key (#2878)

- Add the previously omitted `Treasury` main administration tab label to POT and five gettext locales; compile matching MO catalogs.
- Leave all bookkeeping, accounting logic, configuration and data untouched; no production deployment.

## 0.1.18 — 2026-11-20 — Treasury administration translations (#2876)

- Extract 91 previously missing Treasury gettext strings into the POT and translate all corresponding PO entries for es_ES, en_US, fr_FR, de_DE and nl_NL.
- Rebuild five binary MO catalogs to restore the correct WordPress-language display across balances, cash/bank movements and loan forms.
- Do not alter money calculations, invoice handling, bank movements or loan behavior; no production deployment.

## 0.1.17 — 2026-11-20 — Optional immutable rectification reconciliation (#2860)

- Accept an optional WordPress filter for the latest legally issued, same-total rectifying invoice linked to a WooCommerce sale. Prefer the rectification for VAT validation, while preserving the original document and paid amount. Confirm currency, positive finite values and base + VAT = original gross before applying.
- No mandatory dependency on Billing and no new payments, duplicate sales, auto-approval or order changes. Issued documents require explicit fiscal validation; existing protected drafts and historical corrections remain unchanged.

## 0.1.16 — 2026-11-20 — Fixed-rate loan financing and reconciled repayments (#2857)

- Add a calculated loan mode with financed principal, upfront down payment, nominal annual interest (TIN), duration in months (1–360), optional final balloon principal, start date and first payment date. The monthly amortisation table separates principal and interest and rounds the last instalment to clear the balance.
- Preserve existing manual loans and their editable outstanding balance and instalment amount. New calculated loans only reduce their outstanding principal when an authorised administrator explicitly confirms a real instalment; every payment writes an atomic linked bank/cash movement, with per-instalment uniqueness and idempotency.
- Loan funding may optionally post one confirmed bank receipt at creation. No double booking of the financed purchase, upfront down payment, or future instalments. Money loans and their interest are generally VAT-exempt under article 20(1)(18) of Spain's VAT law; loan interest never alters Model 303 automatically. Contractual fees and APR/TAE require independent verification.
- Add migration to schema v6, deterministic financing regression tests (10% TIN, 12 months, final cent, zero-rate and balloon cases), and maintain WordPress/WooCommerce independence.

## 0.1.15 — 2026-11-20 — Conciliación de cobros y aportaciones iniciales (#2845)

- Tesorería permite **conciliar manualmente cobros reales de ventas**, devoluciones y pagos de proveedores desde la misma lista de importes pendientes, con fecha/cuenta elegidas y confirmación expresa.
- Configuración: **cuenta bancaria para cobros WooCommerce** y fecha mínima; desactivado por defecto. Al sincronizar pedidos realmente pagados, solo crea el recibo bancario si consta fecha de pago, número de factura, totales coincidentes y cuenta bancaria real. Respeta el filtro de fecha y los enlaces existentes.
- Se detectan movimientos manuales previos con la referencia de la factura en el sentido correcto (cobro/pago) y se mantienen las protecciones de importes pendientes, idempotencia y bloqueo transaccional.
- **Conversión opcional del saldo inicial en ingreso fechado**, sin duplicar el saldo total. No se cambian las cuentas salvo confirmación y sin tocar los préstamos.
- Las facturas fiscales **no se presumen pagadas** por validación; las compras sin comprobante de pago siguen requiriendo confirmación explícita. No se alteran base, IVA, documentos o registros ajenos.
- Regresiones de STAGING: saldos iniciales Cash/Bank de 500 EUR cada uno; Bank in 1.043,06 EUR y Bank out 939,46 EUR solo tras cobro/pago confirmado. Revisión de cero duplicados, no modificación de WooCommerce y compatibilidad con Plugin Check.

## 0.1.14 — 2026-11-20 — Conciliación bancaria de gastos realmente pagados (#2843)

- **Registrar o validar una factura** ahora permite marcar opcionalmente «Factura pagada» y seleccionar banco/caja, importe y fecha real: tras confirmar, el pago crea su salida en Tesorería sin repetir los datos de la factura.
- Nueva sección **Tesorería → Conciliar facturas ya pagadas**: lista limitada de asientos validados con saldos pendientes, selección individual y fecha de pago editable. Solo genera movimientos tras marcar una confirmación expresa; nunca asume que todas las facturas están pagadas.
- Cada pago utiliza el enlace transaccional existente y comprueba el importe pendiente para evitar duplicados en reenvíos. La conciliación también omite los pagos manuales ya registrados con la misma cuenta y referencia, incluidos pagos parciales. El resultado muestra cuántos pagos se han contabilizado, omitido o fallado.
- **No se modifican** bases, IVA, documentos ni préstamos. El saldo inicial de banco no se recalcula: las aportaciones reales de socios deben documentarse por separado.
- El CSV sigue importando **borradores no pagados** por defecto. No se infiere que un cliente haya pagado solo porque WooCommerce haya creado un pedido; los cobros también requieren confirmación y cuenta real.
- Pruebas de seguridad, pagos parciales, no duplicación, 362,57 € iniciales y escenario con Satkit de 12,55 €. Ninguna modificación directa de los datos de producción.

## 0.1.13 — 2026-11-18 — Pagos y cobros de facturas asociados a Tesorería (#2817)

- Cada asiento de venta, devolución, compra, gasto o activo ofrece **Registrar pago / Registrar cobro** con selección explícita de cuenta bancaria o caja, fecha real e importe, incluyendo pagos parciales.
- El movimiento se incorpora al registro existente de Tesorería y se enlaza al asiento fiscal sin cambiar base, IVA, estado ni documentos. El resumen muestra importe pagado/cobrado y pendiente.
- Se añade tabla propia de vínculos y protección contra doble envío (clave de idempotencia única, bloqueo por asiento y transacción atómica). El importe nunca puede superar el saldo pendiente.
- Las facturas importadas o validadas **no generan movimientos automáticamente sin confirmación de que están pagadas**. No se presume el pago de facturas antiguas ni se duplican los saldos iniciales; los pagos ya anotados manualmente deben conciliarse antes de registrar otro.
- La misma lógica cubre efectivo y múltiples bancos, con entrada para ventas y salida para compras, gastos y devoluciones. No modifica WooCommerce, Billing, movimientos existentes o datos de producción.
- Añadido smoke test aislado; pendiente QA operativa con extractos reales antes de considerar completada la conciliación.

## 0.1.12 — 2026-11-18 — Facturas de proveedores y servicios con originales cifrados (#2795)

- Nuevo importador/exportador CSV de **compras a proveedores** en «Facturas recibidas», separado de las facturas de **gastos y servicios** en «Gastos» y de su acceso anterior en «Tesorería».
- Se reutilizan las mismas validaciones de CSV, plantilla UTF-8, límites de 500 filas/2 MB, deduplicación entre libros y descarga por ejercicio; cada documento se incorpora como borrador de compra o gasto y nunca valida deducciones ni registra movimientos de Tesorería.
- Una vez importado el CSV, cada registro permite adjuntar el original PDF, JPG/JPEG, PNG o WEBP (hasta 10 MB), con varios documentos por asiento. Se verifican tipo real y firma de archivo; se almacena cifrado en wp-content/uploads/taxocommerce-accounting/encrypted-invoices/, no en la biblioteca multimedia pública.
- Descarga autenticada mediante capacidad y nonce, validación de hash y contenido, sin URL pública del original. Se conservan los PDF anteriores y la clave derivada de las sales de WordPress; las copias deben incluir tanto BD como uploads y sales.
- El CSV no adjunta automáticamente archivos ni pretende realizar OCR o deducibilidad automática. Después del importador, utilizar «Adjuntar documento» en la fila del asiento.
- Regresiones para compras y gastos diferenciados, tipos de archivo no permitidos, cifrado y compatibilidad previa; Plugin Check WordPress.org obligatorio. Sin cambios de producción.

## 0.1.11 — 2026-11-17 — Importación/exportación CSV de gastos desde Tesorería (#2785)

- Sección en Tesorería para descargar plantilla, importar y exportar por ejercicio CSV de gastos de asociación/tienda (hosting, dominios, software, certificados, tasas y Correos), sin duplicar libros ni tratar esos documentos como compras de mercancía.
- Cada fila se incorpora exclusivamente al registro **Expenses** como borrador revisable; conserva fecha original, emisor, documento, base, IVA, porcentaje de IVA propuesto y tratamiento. No aprueba deducciones, no modifica el IVA de documentos emitidos y no crea movimientos de caja ni banco.
- Identificación de destino association/commercial/shared/review en las notas del mismo asiento, sin tablas o columnas adicionales; el exportador recupera esa clasificación.
- CSV UTF-8 compatible con Excel español (punto y coma, también admite CSV con coma), límite 2 MB/500 filas por importación, validación completa previa, duplicados omitidos y exportación protegida frente a fórmulas CSV.
- Se mantiene la adjunción cifrada de justificantes PDF desde la pestaña Expenses, usando el mismo registro manual de gastos. Pruebas de seguridad y compatibilidad con los flujos existentes.

## 0.1.10 — 2026-11-17 — Pestaña Tesorería: caja, bancos y préstamos (#2782)

- Una nueva pestaña independiente **Tesorería** presenta indicadores de caja, saldos bancarios combinados, fondos disponibles, deuda declarada pendiente y cuotas mensuales previstas.
- Alta de cajas y múltiples cuentas bancarias con saldo inicial, movimientos de entrada/salida y traspasos internos atómicos entre cuentas; resumen mensual de entradas, salidas y saldos sin mezclarlo con los indicadores de IVA.
- Préstamos sencillos con importe inicial, entidad acreedora, cuota, próximo vencimiento y saldo pendiente editable según extracto del prestamista. No estima intereses, no descuenta mensualidades automáticamente y no registra cobros ni pagos ficticios.
- Las facturas comerciales de WooCommerce/Billing no generan entradas o salidas de tesorería hasta registrar efectivamente un movimiento. Preservados los registros y cálculos fiscales.
- Datos en tablas propias y permisos WordPress, acciones con nonces, validación de importes y fechas; pruebas de saldos y transferencias.

## 0.1.9 — 2026-11-17 — Traspaso automático de factura emitida a Accounting (#2778)

- Captura automática mediante una acción estándar de WordPress tras la emisión de una factura de Billing: asiento de venta en borrador, sin reimportación manual ni dependencia obligatoria entre plugins.
- Para nuevas ventas utiliza número, base imponible, IVA y total del snapshot fiscal inmutable de la factura si está disponible; si no hay Billing, conserva la importación convencional de WooCommerce.
- Reconcilia únicamente borradores intactos que se importaron antes de emitir su factura. No modifica documentos originales, pedidos, asientos validados ni correcciones auditadas.
- Impide aprobar borradores que discrepen de los importes de una factura emitida y avisa cuando ya existen ventas validadas con IVA cero.
- No recalcula ni inventa cuotas de IVA: la prueba simulada de 621,60 € con 107,88 € de IVA corresponde exclusivamente al asiento corregido de prueba; el documento histórico conserva los 0 € de IVA emitidos.

## 0.1.8 — 2026-11-17 — Guía para IVA deducible y revisión de ventas con IVA cero (#2773)

- Se conservan los importes de ventas importados desde WooCommerce, sin reescribir automáticamente documentos emitidos ni asientos validados.
- Las ventas WooCommerce con base positiva e IVA cero muestran aviso de revisión: para validarlas como operación doméstica se exige confirmación expresa tras contrastar la factura y el tratamiento; no se aplica artificialmente el 21 % a cualquier producto.
- En la incorporación manual de facturas de proveedores, el porcentaje de IVA deducible no comienza en 0 por defecto y debe indicarse expresamente. Se explica la diferencia entre **tipo de IVA** y **porcentaje deducible** (0 %, 100 % u otro legalmente aplicable).
- Vista previa automática del total de factura (base + cuota) y del importe de IVA potencialmente deducible. Solo ayuda a revisar los datos, no decide el tratamiento fiscal.
- Pruebas de regresión y validación de sintaxis JavaScript. Sin cambio de registros existentes, WooCommerce, Billing ni producción.

## 0.1.7 — 2026-11-17 — Corrección controlada de asientos validados (#2771)

- Botones **Corregir asiento** e **Historial** en Ventas, Facturas recibidas y los demás registros contables.
- Edición acotada de fecha, referencia, cliente/proveedor, NIF, concepto, base imponible, IVA y porcentaje deducible, con motivo obligatorio; el total se calcula como base más IVA.
- Toda corrección se registra en una tabla propia con datos antes/después, usuario, fecha y motivo; escritura atómica de asiento e historial (sin sobrescribir el historial anterior). La modificación devuelve el asiento a **borrador** para nueva validación y lo excluye de totales fiscales validados hasta entonces.
- La factura emitida de Billing, el PDF del proveedor y los pedidos WooCommerce no se modifican. Si el documento fiscal original es incorrecto, debe corregirse en origen por el procedimiento adecuado, normalmente mediante factura rectificativa; ajustar la contabilidad no lo sustituye.
- Control de permisos, nonce específico, validación de importes y fechas, comprobación del número de factura de Billing en servidor y protección ante edición simultánea.
- Prueba PHP independiente para recálculo, reversión, trazabilidad, retención del original y facturas de proveedor. Compatibilidad con WordPress.org y despliegue acotado a STAGING.

## [0.1.6] — 2026-11-17 — Import ISP → treatment intra (#2761)

- Al importar pedidos WooCommerce con régimen `intra_eu_reverse_charge` / VIES válido, asigna `treatment=intra` y copia el NIF-IVA (sigue en draft).
## 0.1.6 — 2026-11-17 — Asociación automática de factura emitida y venta (#2767)

- Accounting obtiene de manera opcional el número de la factura **emitida** en Billing utilizando el identificador exacto de pedido WooCommerce. No hay dependencia obligatoria de otro plugin TaxoCommerce.
- Para pedidos nuevos se registra la referencia si ya existe; para ventas antiguas todavía en borrador se propone el número en pantalla sin modificar datos previos. En el momento de aprobar la venta se vuelve a comprobar en el servidor la factura real del pedido y no se acepta como referencia un valor cambiado desde el navegador.
- Si Billing no está instalado o no hay factura emitida, se conserva la referencia manual para otros sistemas de facturación. Proformas, presupuestos, facturas de proveedor y devoluciones no se confunden con la factura de venta.
- El enlace de la referencia **no aprueba automáticamente** IVA, facturas incorrectas ni asientos contables. Se conservan las revisiones fiscales, la numeración y los datos existentes.
- Añadida prueba de regresión PHP del vínculo, su ausencia cuando falta Billing y validación del identificador exacto de pedido. Plugin Check y CI requeridos.

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
