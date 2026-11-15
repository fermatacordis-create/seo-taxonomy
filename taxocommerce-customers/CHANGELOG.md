# TaxoCommerce Customers — Historial

## 0.1.1 — 2026-11-14

- Privacidad: supresión de los campos personales replicados en CRM y marcador técnico con hash HMAC no reversible; los nuevos eventos de compra no restauran esos datos.
- Permisos: capacidades independientes para gestión, importación, exportación, borrado y lectura de notas privadas; concedidas a administrador y gestor de tienda en activación/actualización.
- Sincronización histórica: siempre mediante clic explícito; reanudación por posición dentro de página con tamaño fijo para evitar saltos al cambiar de velocidad.
- CSV: exportación privada en archivo temporal del servidor por bloques, descarga firmada, comprobación de permisos, protección CSV injection y limpieza tras descargar/cancelar.
- Regresión: nuevas comprobaciones de supresión, no repoblación y exportación sin Blob del navegador.
- Se mantienen pedidos, usuarios, pagos y consentimientos bajo sus sistemas propietarios. Producción excluida del despliegue.

## 0.1.0 — 2026-11-13

- Nuevo plugin CRM autónomo: compradores registrados e invitados de WooCommerce, contactos manuales de correo y WhatsApp.
- Fichas privadas de cliente, búsqueda, notas comerciales y seguimiento de compradores sin duplicar pedidos.
- Sincronización de pedidos por lotes y actualización por eventos de WooCommerce, compatible con HPOS.
- Importación/exportación masiva CSV por bloques, cinco velocidades, progreso y detención.
- Autotest funcional y chequeo estático de código, manuales y catálogos ES/EN/FR/DE/NL.
- Datos aislados en tablas propias. Sin publicaciones en el frontend, sin automatizar campañas ni consentimientos.

- Primera instalación por FTPS autorizada solo en STAGING y Focazul; no se permite crear el plugin en producción. Se transfieren todos los archivos instalables verificados.
