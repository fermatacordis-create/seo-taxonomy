# Facturas y presupuestos

Ruta: **TaxoCommerce SEO → Herramientas → Facturas y presupuestos**

Esta pantalla centraliza el sistema documental del ecommerce. WooCommerce sigue siendo la fuente de verdad de carrito, pedido, impuestos, transporte, estados y pago; el módulo documental toma esos importes y genera los PDF correspondientes.

> **Importante:** los valores descritos como **por defecto** proceden del código actual. Si un entorno ya tiene opciones guardadas en WordPress, esas opciones prevalecen sobre los defaults. STAGING y producción pueden, por tanto, tener valores distintos.

## Pestañas

**Datos empresa**, **Fiscalidad**, **Facturas**, **Proformas**, **Presupuestos** y **Documentos**.

## Modelo general

Hay tres tipos principales de documento:

| Documento | Momento | Pedido WooCommerce | Persistencia | Validez |
| --- | --- | --- | --- | --- |
| **Presupuesto** | Desde el carrito, antes de comprar | No crea pedido | Efímero | Comercial, no fiscal |
| **Proforma borrador** | Desde el carrito | No crea pedido | Efímera | Borrador, no fiscal |
| **Proforma definitiva** | Pedido existente todavía no pagado | Sí | Sí, con snapshot | Comercial, no fiscal |
| **Factura** | Pedido que WooCommerce considera pagado | Sí | Sí, con snapshot | Documento fiscal |

La **Factura no se genera desde el carrito**. El carrito puede ofrecer Presupuesto y Proforma borrador; la factura se emite cuando WooCommerce confirma el pago o el pedido entra en un estado pagado.

## Interruptor maestro

En **Datos empresa → Sistema documental** existe el interruptor maestro.

Comportamiento vigente:

- si **Sistema documental** está desactivado, no se ofrecen Presupuesto ni Proforma borrador y no se emiten Facturas/Proformas definitivas;
- si está activado, cada tipo de documento respeta además su propio interruptor;
- **Presupuestos activos** controla Presupuesto;
- **Proformas activas** controla Proforma borrador y Proforma definitiva;
- **Facturas activas** controla Factura.

Este comportamiento se restauró expresamente después de una prueba temporal en la que Presupuesto/Proforma podían ignorar el interruptor maestro.

## Datos empresa

Se configuran una vez y se reutilizan en los documentos:

- **Sistema documental**;
- Razón social;
- Nombre comercial;
- NIF/CIF;
- Dirección;
- Código postal;
- Localidad;
- Provincia / región;
- País;
- Teléfono;
- Email;
- Web;
- Logo;
- Pie común;
- claves posibles de metadatos para localizar el NIF/CIF del cliente.

### Valores por defecto de código

- Sistema documental: **desactivado**;
- País: **ES**;
- Email: usa el email de administración de WordPress si no hay otro guardado;
- Web: usa la URL del sitio;
- Metas NIF/CIF cliente:
  `_billing_nif,_billing_cif,_billing_vat,billing_nif,billing_cif,billing_vat`.

**Guardar datos de empresa** persiste estos valores en WordPress.

## Fiscalidad

El PDF **no realiza un segundo cálculo fiscal independiente**. Los documentos copian los importes calculados por WooCommerce.

Controles:

- **Gestión fiscal desde Facturación**;
- **Los precios del catálogo ya incluyen IVA**;
- **Vender solo en España**;
- **IVA sobre transporte**;
- IVA Península;
- IVA Baleares;
- IVA Canarias;
- IVA Ceuta;
- IVA Melilla;
- nota para destinos especiales.

### Valores por defecto

| Opción | Valor |
| --- | ---: |
| Gestión fiscal desde Facturación | Desactivada |
| Los precios del catálogo ya incluyen IVA | **Activado** |
| Vender solo en España | Activado |
| IVA sobre transporte | Activado |
| Península | 21 % |
| Baleares | 21 % |
| Canarias | 0 % |
| Ceuta | 0 % |
| Melilla | 0 % |

Detección de zonas:

- Baleares: provincia **PM** o CP **07**;
- Canarias: Las Palmas / Santa Cruz de Tenerife o CP **35 / 38**;
- Ceuta: provincia **CE** o CP **51**;
- Melilla: provincia **ML** o CP **52**.

Canarias usa el catálogo **IGIC** (0 / 3 / 7 / 9,5 / 15 / 20 %, editables) y Ceuta/Melilla el catálogo **IPSI** (0 / 0,5 / 1 / 2 / 4 / 8 / 10 %, editables por ciudad). Se aplican según la **clase fiscal WooCommerce** del producto (`igic-general`, `ipsi-4`, …); sin clase se usa el tipo general. Los porcentajes son orientativos: conviene revisión profesional.

### B2B intracomunitario (opcional)

Si se habilita **B2B UE con VIES** y se desactiva «Vender solo en España»:

- el checkout pide NIF-IVA y marca de empresa UE;
- un NIF-IVA válido en VIES aplica **IVA 0 % (ISP)** con mención legal en el PDF;
- Canarias, Ceuta y Melilla **no** entran en este flujo;
- OSS, IOSS, SII/Verifactu y presentación oficial 303/349 quedan fuera de alcance.

La nota por defecto de destinos especiales avisa de que despacho, transporte u otros gastos en destino pueden no estar incluidos.

Al guardar con la gestión fiscal activa, el módulo activa el cálculo de impuestos de WooCommerce, sincroniza sus tasas y también sincroniza si los precios almacenados **incluyen IVA**.

### Evitar IVA duplicado

En este proyecto los PVP del catálogo se consideran precios finales con IVA. Por tanto debe mantenerse activada **Los precios del catálogo ya incluyen IVA**.

Esta regla es **independiente de “Gestión fiscal desde Facturación”**. Aunque el gestor de tasas esté desactivado, WooCommerce debe seguir interpretando el precio almacenado como PVP con IVA.

Cuando esta regla está activa, el módulo alinea además WooCommerce para **mostrar precios con IVA tanto en tienda como en carrito/checkout**. Así, un producto mostrado a 301,08 € IVA incluido debe seguir totalizando 301,08 € durante la compra; el IVA se desglosa dentro de ese importe y no se añade encima.

Si WooCommerce interpreta esos mismos PVP como precios **sin impuestos**, añadirá de nuevo el IVA al crear carrito/pedido. La Factura no corrige ese error: copia los importes del pedido, por lo que reflejaría el total inflado.

**No modifica documentos ni pedidos ya emitidos**. El ajuste afecta a los cálculos posteriores de WooCommerce.

## Numeración

Las series de Factura, Proforma y Presupuesto deben ser diferentes entre sí.

Formato:

`SERIE-AÑO-SECUENCIA`

Ejemplo con seis dígitos:

`PRE-2026-000005`

La secuencia se mantiene por **tipo + serie + año**.

Límites de dígitos configurables: **3 a 10**.

## Facturas

La Factura se emite para un pedido que WooCommerce considera **pagado**.

### Valores por defecto

- Facturas activas: **sí**;
- Título PDF: **FACTURA**;
- Serie: **FAC**;
- Dígitos: **6**;
- Generación automática: **sí**;
- Adjuntar a emails WooCommerce: **sí**;
- Emails:
  - `customer_processing_order`;
  - `customer_completed_order`;
- Referencia del pedido: **sí**;
- Método de pago: **sí**;
- Referencia interna del producto: **sí**.

La clave técnica histórica sigue llamándose `invoice_show_sku`, pero el documento de cliente muestra el **ID interno de WordPress (`object_id`)**, no el SKU del proveedor.

### Cuándo se genera automáticamente

- `woocommerce_payment_complete`;
- cuando el pedido cambia a uno de los estados que WooCommerce considera pagados, normalmente `processing` o `completed`.

Si el pedido no está pagado, el sistema bloquea la factura salvo que exista un filtro explícito que autorice ese caso.

## Proformas

Hay dos comportamientos distintos.

### Proforma borrador desde carrito

- no crea pedido;
- no usa la numeración PRO definitiva;
- genera una referencia temporal del tipo:
  `PRO-BORRADOR-YYYYMMDD-HHMMSS-TOKEN`;
- no acredita pago;
- no es factura fiscal;
- no reserva stock;
- puede variar hasta que se cree el pedido.

### Proforma definitiva

Se vincula a un pedido WooCommerce existente que todavía **no está pagado**.

Por defecto no se genera una proforma definitiva para un pedido que WooCommerce ya considera pagado.

### Valores por defecto

- Proformas activas: **sí**;
- Título: **FACTURA PROFORMA**;
- Serie: **PRO**;
- Dígitos: **6**;
- Generación automática: **sí**;
- Estado WooCommerce: **on-hold**;
- Adjuntar a emails: **sí**;
- Email: **customer_on_hold_order**;
- Referencia del pedido: **sí**;
- Método de pago: **sí**;
- Referencia interna del producto: **sí**;
- Mostrar instrucciones de pago: **no**.

Opcionalmente pueden configurarse:

- Beneficiario;
- IBAN;
- Bizum;
- instrucciones de pago;
- pie exclusivo de Proforma.

## Presupuestos

El Presupuesto se genera directamente desde el carrito.

No crea:

- pedido;
- cliente;
- historial persistente de presupuestos.

### Valores por defecto

- Presupuestos activos: **no**;
- Título: **PRESUPUESTO**;
- Texto botón: **Descargar presupuesto**;
- Serie: **PRE**;
- Dígitos: **6**;
- Validez: **15 días**;
- Visitantes sin iniciar sesión: **sí**;
- pedir Empresa / nombre: **sí**;
- pedir NIF/CIF: **sí**;
- pedir Persona de contacto: **sí**;
- pedir Email: **sí**;
- Email obligatorio: **no**;
- mostrar referencia interna del producto: **sí**;
- mostrar impuestos: **sí**;
- mostrar transporte: **sí**;
- mostrar descuentos: **sí**;
- incluir imagen de producto: **no** por defecto;
- enviar copia por email: **no**;
- límite por sesión/hora: **20**.

Límites configurables:

- validez: **1 a 365 días**;
- límite por sesión/hora: **1 a 200**.

Existe además una protección mínima de **3 segundos** entre generaciones dentro de la misma sesión.

### Condiciones por defecto

`Este documento constituye un presupuesto comercial y no tiene validez fiscal. Los precios quedan sujetos al plazo de validez indicado y a disponibilidad de stock.`

## Transporte pendiente

Presupuesto y Proforma borrador pueden generarse aunque WooCommerce todavía no haya calculado los portes.

En ese caso:

- el snapshot marca `shipping_pending`;
- el PDF muestra **Transporte pendiente de calcular**;
- el importe destacado se presenta como **TOTAL PROVISIONAL**;
- se avisa de que el importe final se confirmará al indicar el destino completo de envío;
- el destino se presenta como **Destino indicado por el cliente** y no como destino definitivo usado para el cálculo.

Cuando el transporte ya está calculado, el documento puede mostrar **Destino usado para el cálculo**.

## Ficha comercial en Presupuesto y Proforma

Presupuesto y Proforma incorporan una ficha comercial para conservar el contexto con el que se tomó la decisión de compra.

Puede incluir:

- imagen;
- nombre;
- referencia;
- resumen;
- categoría;
- hasta 10 características relevantes;
- datos técnicos y semánticos, como Tipo, Aplicación, Rol, Plataforma/Subtipo, marca, potencia, presión, tensión, capacidad, peso y dimensiones.

La presentación evita repetir descripciones largas y elimina truncamientos o elipsis artificiales.

La **Factura se mantiene deliberadamente sobria** y no incorpora esta ficha comercial ni anexos de comparación.

## Imágenes de producto en PDF

Prioridad de resolución:

1. imagen local en Media;
2. imagen activa de `seo_supplier_images`;
3. fallback de Supplier Sync.

Dompdf mantiene `isRemoteEnabled=false`. Cuando la imagen está en una URL externa, el sistema la descarga de forma controlada y la convierte a **data URI** antes de renderizar:

- timeout: **8 s**;
- tamaño máximo: **3 MB**;
- redirecciones limitadas.

No es necesario importar esa imagen a Media para que aparezca en el PDF.

## Referencia de producto mostrada al cliente

Los documentos de cliente **no deben mostrar el SKU real del proveedor**.

Comportamiento actual:

- el SKU real se conserva internamente para importación, logística y relación con proveedores;
- Factura, Proforma y Presupuesto muestran el **ID interno de WordPress (`object_id`)** cuando la opción de referencia está activa;
- la interfaz administrativa llama a esta opción **Referencia interna del producto**.

## Comparativa de sesión

Si el usuario ha abierto una comparativa durante la selección, Presupuesto o Proforma borrador pueden incorporar un anexo de comparación.

Reglas:

- se usa la última comparativa abierta durante la sesión;
- el navegador la conserva temporalmente en `sessionStorage`;
- sólo se añade si el usuario realmente abrió una comparativa;
- admite hasta **6 productos**;
- no se genera una comparación de forma automática si el usuario no la solicitó.

El motor PDF es el mismo Dompdf que utilizan los demás documentos.

## Snapshots y persistencia

### Factura y Proforma definitiva

Se guardan en la tabla documental con:

- pedido WooCommerce;
- tipo;
- serie;
- número;
- estado;
- fecha;
- estado del pedido;
- método de pago;
- snapshot;
- HTML renderizado;
- ruta PDF;
- hash del fichero;
- errores;
- fecha de envío por email.

El snapshot congela la información utilizada al emitir el documento. Cambiar configuración posteriormente **no reescribe el documento ya emitido**.

### Presupuesto y Proforma borrador

Se generan en el momento desde el carrito y son **efímeros**. No aparecen en el registro de Documentos.

## Documentos

La pestaña **Documentos** muestra los documentos persistentes asociados a pedidos:

- Documento;
- Pedido Woo;
- Tipo;
- Fecha;
- Estado;
- Email manual;
- Acciones.

Desde aquí pueden aparecer acciones como:

- descargar PDF;
- reintentar PDF;
- reenviar.

Actualmente el registro persistente contiene **Facturas y Proformas definitivas**. Los Presupuestos de carrito y Proformas borrador no se almacenan.

## Motor PDF

Los documentos se generan con **Dompdf** incluido en el módulo de facturación.

La pestaña de Presupuestos muestra el estado del motor PDF. Si Dompdf no está disponible, la descarga no puede generarse.

## Compatibilidad de carrito

El bloque documental puede aparecer en:

- carrito clásico de WooCommerce;
- WooCommerce Cart Block;
- plantilla DHT mediante su hook específico;
- shortcode de respaldo:
  `[seo_facturas_presupuesto]`.

La plantilla DHT usa un control de renderizado independiente para evitar que el procesamiento previo del Cart Block o de WooCommerce impida mostrar los botones reales del carrito.

## Resumen operativo

Flujo recomendado:

1. Configurar **Datos empresa**.
2. Revisar **Fiscalidad** y confirmar que WooCommerce calcula correctamente IVA y transporte.
3. Activar **Sistema documental**.
4. Configurar y activar individualmente Facturas, Proformas y/o Presupuestos.
5. Mantener las series **FAC**, **PRO** y **PRE** separadas.
6. Probar Presupuesto desde carrito con y sin transporte calculado.
7. Probar Proforma borrador desde carrito.
8. Crear un pedido de prueba pendiente para validar Proforma definitiva.
9. Marcar/pagar un pedido de prueba para validar Factura.
10. Verificar adjuntos de email y pestaña **Documentos**.
11. Confirmar que los PDFs muestran referencia interna y no SKU de proveedor.
12. Revisar el PDF final antes de pasar cambios de configuración a producción.
