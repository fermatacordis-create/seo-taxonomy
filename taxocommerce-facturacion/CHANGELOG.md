## [0.1.22] — 2026-11-21 — Safe gross-amount rectifying invoices (#2926)

- Extend issued invoice rectification by substitution to corrected net product amounts, with server-calculated VAT and grand total, preserving the existing VAT-only mode.
- Preserve original issued invoice/PDF and WooCommerce order; create a separately numbered RECT PDF linked to the original and display the change in gross. No automatic payment, refund, order edit or receipt duplication.
- Validate every product line, uniform supported VAT rates, mandatory reason, original document and fiscal quarter; reject shipping/fees/discounts and ambiguous mixed VAT.
- Notify optional Accounting consumers via the established filter and action; original payment remains linked and any additional due is tracked there.
- Regression tests cover amount corrections, multiple lines, VAT-only and invalid amounts. STAGING and Focazul only; never PRO.

### Entrega de emergencia — 2026-11-20 — #2864

- STAGING y Asociación Focazul tenían una instalación incompleta de Billing: faltaba `includes/class-seo-facturas-special-rates.php` y fallaba el arranque de WordPress.
- Una entrega de Billing reconcilia todos sus archivos instalables contra Git por SHA-256, subiendo solo los ausentes o diferentes; no instala plugins faltantes, no toca otros módulos ni despliega a producción.
- Sin modificaciones funcionales en la facturación ni en los documentos emitidos.

## [0.1.21] — 2026-11-20 — Immutable VAT rectifying invoices by substitution (#2860)

- Issue independent numbered RECT fiscal documents with a mandatory justification, original invoice number/date, old and corrected base/VAT/total, and a permanently linked, downloadable PDF; unchanged gross means no extra charge to the customer.
- Limit the initial correction workflow to a single applicable 0/4/10/21% VAT rate and unchanged gross; reject fees, charged shipping, discounts, and repeated rectifications rather than invent line-specific values. Preserve original PDF, order, payments and seller snapshot.
- Add a nonce- and capability-guarded control in the issued invoice register, a separate rectification register, manual PDF retry and customer email. A read-only optional WordPress filter exposes the effective issued correction to other plugins without a hard dependency.
- Add a separate rectifications table and upgrade DB schema to 0.2.0; first issue uses independent RECT numbering. Never auto-correct historic fiscal documents.

## [0.1.20] — 2026-11-17 — Comunicación de factura emitida a contabilidad (#2778)

- Tras completar el PDF y guardar el snapshot fiscal inmutable de una factura emitida, dispara la acción opcional `taxocommerce_billing_invoice_issued` con identificador de pedido y documento emitido.
- Cualquier plugin puede suscribirse sin que Billing dependa de Accounting ni se modifiquen pedidos, PDFs o documentos históricos.
- No duplica eventos para una factura previamente emitida; Accounting recibe la nueva emisión y conserva una revisión fiscal previa a la aprobación.

## [0.1.19] — 2026-11-17 — Transporte calculado en presupuestos y proformas

- Recalcula el transporte usando el destino introducido en el formulario de carrito, resuelve provincia española a codigo WooCommerce y restaura la direccion original de sesion.
- Detecta envio calculado desde WC_Cart y la tarifa seleccionada, en lugar de confundirlo con el indicador de WC_Customer.
- Muestra coste de transporte sin IVA y, cuando aplica, total del transporte con IVA incluido para evitar confusion con el total del documento.
- Si un producto requiere envio y no hay tarifa para la direccion completa, impide emitir el PDF con coste ficticio. No modifica tarifas, pedidos ni pesos/dimensiones de productos.
- Normaliza Stable tag de readme.txt a la version publicada.

## [0.1.18] — 2026-11-17 — Catálogo completo IGIC e IPSI (#2762)

- Añade tipos IGIC Canarias (0 / 3 / 7 / 9,5 / 15 / 20 %) y IPSI Ceuta/Melilla (0 / 0,5 / 1 / 2 / 4 / 8 / 10 %), editables.
- Registra clases fiscales WooCommerce (`igic-*`, `ipsi-*`) y aplica el tipo según la clase del producto y el destino.
- Sin clase específica en Canarias/Ceuta/Melilla se usa el tipo general. Sigue fuera del flujo VIES/ISP.
- Los porcentajes son orientativos; el comercio/asesor debe confirmar la normativa vigente.

## [0.1.17] — 2026-11-17 — B2B UE (VIES/ISP) + etiquetas IGIC/IPSI (#2761 #2762)

- Añade validación VIES opcional e inversión del sujeto pasivo (IVA 0 %) para empresas de otro Estado UE, con mención legal en PDF y metadatos de pedido.
- Canarias se etiqueta como IGIC y Ceuta/Melilla como IPSI (tipos configurables); quedan fuera del flujo VIES/ISP.
- Accounting importa pedidos con régimen ISP como `treatment=intra` (draft) copiando el NIF-IVA.
- Fuera de alcance: OSS, IOSS, SII/Verifactu, presentación oficial 303/349, catálogo completo de tipos IGIC/IPSI.

## [0.1.16] — 2026-11-16 — IVA incluido en los tres documentos comerciales

- Repara una sola vez la configuración fiscal para tiendas con PVP IVA incluido cuando WooCommerce no dispone de una tasa española aplicable, sin modificar precios ni pedidos previos.
- Presupuesto y proforma del carrito calculan el IVA según la dirección introducida por el comprador; se restauran los datos de sesión después del cálculo.
- Factura y proforma asociadas a pedidos se alimentan de los impuestos originales de WooCommerce. Si falta el IVA esperado para un producto ordinario sujeto a impuesto, se rechaza emitir un documento nuevo y se muestra un error explícito en vez de certificar una base falsa.
- Conserva las clases fiscales especiales y los documentos ya emitidos. El transporte sigue las tasas configuradas en WooCommerce.

## [0.1.15] — 2026-11-13 — Code Check equiparado a Core 0.4.82 (#2626)

- Code Check equiparado a Core 0.4.82: reglas autónomas PHP/WordPress/seguridad, clasificación por gravedad, detalles de archivos y recomendaciones, resumen y JSON; sin modificar el Autotest funcional. Issue #2626.
- Continúa siendo una revisión estática independiente del Plugin Check oficial, con ejecución por bloques y control de CPU.

## [0.1.14] — 2026-11-10 — Cabecera en tres zonas sin contenido duplicado (#2521)

- Izquierda: nombre, descripción local del plugin y un único enlace estático `Plugin URI` bajo el texto.
- Centro: desplegable más ancho con el historial local `CHANGELOG.md`, cerrado inicialmente.
- Derecha: versión y fecha reales del propio plugin.
- Se retira la segunda caja «Información del plugin», sin cambios en servicios, menús ni consultas externas.

## [0.1.13] — 2026-11-10 — Descripción visible desde el plugin (#2494)

- La descripción inmediatamente debajo del título administrativo procede de `Description:` del archivo principal, sin textos duplicados ni consultas externas.
- Se mantienen el enlace estático `Plugin URI`, el historial local y las funcionalidades anteriores.

## [0.1.12] — 2026-11-10 — Ficha local en el encabezado (#2494)

- Elimina consultas REST/caché del extracto y usa `Description` y `Plugin URI` locales.
- Conserva el enlace oficial y el historial, sin alterar servicios existentes.

## [0.1.11] - 2026-11-10 - Resiliencia del extracto oficial (#2489)

- Consulta exclusivamente la API REST pública de la página indicada en Plugin URI (autor del plugin), nunca páginas de la tienda del cliente.
- Conserva el último extracto oficial comprobado; los fallos de red no borran el texto. Caché de errores reducida a 5 minutos y versión nueva de clave para ignorar fallos antiguos cacheados.
- Si nunca se ha obtenido un extracto, muestra provisionalmente la Description incluida por el autor en el plugin; permite abrir la ficha oficial. No cambia operaciones comerciales ni requiere Core.
- Pruebas para recuperación tras fallos HTTP, JSON inválido y caché negativa; compatibles con la familia completa.

## [0.1.10] — 2026-11-09 — Ficha oficial del desarrollador y cabecera centrada en tres zonas (#2476)

- La cabecera distribuye identificación a la izquierda, dos desplegables alineados y centrados (extracto oficial a la izquierda e historial local a la derecha), y versión y fecha a la derecha; distribución adaptable a móvil.
- El enlace oficial se obtiene exclusivamente de Plugin URI, definido por el autor del plugin. Eliminados el formulario de URL y su acción de guardado; los ajustes antiguos de la tienda no pueden sobrescribirlo.
- El historial se lee del CHANGELOG.md incluido, plegado de inicio. Sin dependencias obligatorias entre plugins ni cambios en productos, pedidos, datos o funcionalidades existentes.

## [0.1.9] — 2026-11-08 — Información dinámica desde ficha oficial (#2443)

- El desplegable izquierdo consulta el excerpt publicado en la página Plugin URI, con caché y configuración independiente.
- Formulario sanitizado; soporte para desactivación y funcionamiento sin servicios externos. Historial local sin cambios.

## [0.1.8] — 2026-11-08 — Extracto público remoto opcional (#2443)

- Consulta opcional por HTTPS del excerpt REST de una ficha oficial configurada; muestra el enlace a esa página en un panel izquierdo plegado.
- Mantiene el historial propio en el panel derecho. Caché, permisos y escape de salida; sin servicios obligatorios ni cambios en datos.

## [0.1.7] - 2026-11-08 - Autotest funcional y de codigo (#2401)

- Conserva autotest funcional y JSON; nuevo Code Check independiente del propio plugin: PHP, WordPress, seguridad. Cinco velocidades, lotes y JSON separado. Sin PCP ni Core obligatorio.

## [0.1.6] — 2026-11-08 — Historial plegable en cabecera (#2382)

- Desplegable cerrado por defecto inmediatamente debajo del título, con el historial propio de `CHANGELOG.md`; sin nueva pestaña ni dependencia de otros plugins.
- Textos del título preparados para ES/EN/FR/DE/NL, salida escapada, desplazamiento vertical y mantenimiento de menús y funciones existentes.
- El paquete instalable y la subida incremental incluyen únicamente el historial raíz necesario. Sin cambios en base de datos ni servicios.

## [0.1.5] — 2026-11-07 — Cabecera administrativa de versión y fecha (#2336)

- Muestra título, versión activa y fecha de publicación en el administrador mediante Release Date.
- La fecha no depende de la hora de consulta ni del momento de copia por FTP. Sin cambios en servicios, datos, menús ni permisos.

## [Unreleased] — Catálogos i18n completos de Billing (#2276)

- Traducidas las 68 entradas gettext actuales a español, inglés, francés, alemán y neerlandés, incluidos Autotest y manual de usuario/técnico.
- PO y MO compilados en la carpeta languages del propio plugin; se mantienen el dominio nativo de WordPress y las rutas técnicas originales.
- Sin modificar facturas, proformas, presupuestos, datos fiscales, PDF, permisos ni WooCommerce.
- Pendiente la revisión de todos los textos de código fuera de gettext, calidad visual y selección de idioma en WordPress real.

## [Unreleased] — Regional WordPress locale fallback (#2295)

- Respeta primero los paquetes oficiales de WordPress .mo y .l10n.php para el locale exacto.
- Si falta un catálogo local regional, usa el catálogo existente del mismo idioma (es_ES, en_US, fr_FR, de_DE o nl_NL); no cambia la configuración ni el idioma del usuario.
- Sin cambios en lógica, datos, menús o dependencias del plugin. Las traducciones de contenido incompletas siguen abiertas en #2062.

## Unreleased - Familia i18n #2062


## [0.1.4] — 2026-11-03

- Unifica «Autocheck» y permite descargar JSON autenticado de los resultados de la última ejecución, sin modificar servicios ni contenidos.
- Issue #2150, PR #2151; rutas de pestaña y acciones previas conservadas.

- Dominio gettext, carga de catalogos locales, etiquetas de menús y textos HTML de salida estáticos; catálogos PO/MO sincronizados con el texto fuente.

# CHANGELOG — TaxoCommerce Billing

## [0.1.3] — 2026-11-03

- Cabecera visual común basada en Core: nombre, icono, descripción, versión y diseño adaptable.
- Lógica funcional, pestañas y permisos intactos; sin dependencia de Core. Issue #2137.

> Historial de las versiones del plugin **independiente**. Las funciones anteriores a su separación están documentadas en [`changelog.txt`](changelog.txt).
> Fuente inicial: entradas verificadas del `readme.txt` en GitHub. Solo se atribuye una fecha si la fuente la acredita.

## [Unreleased]

- Inicio de internacionalización WordPress mediante gettext (`Domain Path: /languages`), catálogos por idioma y traducción de etiquetas del menú, sin cambios funcionales. Seguimiento: #2038 (plan global #2036). Cobertura restante de interfaz, Autotest y manuales pendiente de revisión por idioma.


- Normalizadas con prefijo `taxocommerce_billing_` las variables locales de las plantillas PDF de presupuesto y factura, sin alterar claves de snapshots ni estructura de salida.
- Parametrizada también la consulta de tasas de WooCommerce (`%i`) y justificados localmente los accesos directos a tablas propias/legadas, la lectura sin caché de snapshots y las escrituras que deben ser inmediatas. Issue #1546.
- Se conserva la comprobación de firma y caducidad de enlaces de descarga, los nonces administrativos, las series fiscales y la generación de PDF. Validación de Plugin Check y regresión en CI pendiente.

- Se parametrizan los identificadores de tablas SQL con `%i` en Documents y Snapshot (requiere WordPress 6.2 o superior); valores de consulta continúan enlazados con `prepare()`.
- Documentadas con exclusiones PHPCS individualizadas las lecturas GET sin efectos secundarios en administración y los enlaces temporales HMAC de descarga del cliente; se mantienen las verificaciones de capacidad y nonce existentes en acciones administrativas.
- Correcciones parciales de la auditoría Plugin Check #1546: pendientes la revisión de consultas directas y caché, y la validación funcional y del ZIP antes de fusionar.

## [0.1.2]

- Corregida la identidad administrativa del plugin independiente: ruta propia `admin.php?page=taxocommerce-facturacion` y ocho pestañas visibles, incluidas Manual y Autotest, sin depender de `page=seo-facturas` del monolito.
- Conservada la lógica documental existente sin nuevos handlers ni cambios en las clases del monolito. Se oculta exclusivamente la navegación duplicada del controlador heredado al mostrar las pestañas de Billing.
- Añadida una regresión que simula la carga previa de la clase legada y comprueba la navegación, la compatibilidad y la visualización de Manual/Autotest. Relacionado con #1970.

## [0.1.1]

- Añadida pestaña Manual en Billing con guía de usuario y referencia técnica de los flujos existentes de empresa, fiscalidad, facturas, proformas, presupuestos y documentos, WooCommerce, Dompdf y solución de incidencias.
- Añadida pestaña Autotest manual de solo lectura, protegida por permisos administrativos y nonce. Comprueba componentes, hooks, opciones, tabla documental y disponibilidad del motor PDF, sin generar facturas, modificar pedidos ni enviar correos.
- Añadida regresión aislada en CI sin acceso a servicios externos. Scripts `tests/` excluidos del ZIP. Issue #1970.


## [0.1.0]

* Migrated the existing document module into an independent TaxoCommerce plugin.

## Referencias

- [Origen monolítico y transición](changelog.txt)
- [Índice de evidencias de los 20 plugins](../docs/PLUGIN-HISTORY-SOURCES.md)

## Mantenimiento

Este es el historial maestro que se actualiza al introducir novedades. Antes de publicar cada versión en WordPress.org, su resumen debe trasladarse al apartado `== Changelog ==` de `readme.txt`; WordPress.org no presenta este Markdown automáticamente.
