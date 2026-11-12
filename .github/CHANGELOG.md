## 2026-11-12 — PRO: corregido HTTP 401 al seleccionar cambios (#2570)

- Los releases #194 y #195 llegaban correctamente al inventario REST de PRO, pero fallaban antes de transferir archivos porque el selector descargaba logs protegidos de GitHub Actions con un token rechazado (401).
- El selector obtiene el SHA de la ultima ejecución **completada y correcta** del workflow PRO desde los metadatos estructurados de GitHub, con rama `staging`; deja de consultar logs de jobs.
- Si el token de lectura recibe un 401, reintenta una sola vez el endpoint público sin credenciales; un error 403 o la ausencia de un SHA válido sigue bloqueando el despliegue.
- Se conservan selección diferencial por archivos, permisos, inventario de plugins instalados, comprobación de ancestro Git y verificación SHA-256. Sin cambios en WordPress, FTP ni plugins. La subida a PRO no se inicia para probar este ajuste.

# Registro de cambios de la infraestructura QA

## 2026-11-04 — Despliegue automático restringido a diferencias reales (#2184)

- Se elimina la sincronización automática de 20 plugins cuando cambia solamente el workflow de CI o el script de despliegue. Un push normal despliega exclusivamente los archivos instalables modificados desde la última base verificada.
- La reconciliación completa se mantiene disponible únicamente mediante ejecución manual del workflow con `full_sync=true` explícito. Se añaden regresiones para ambos comportamientos, sin tocar producción.

## 2026-11-04 — Aislamiento FTPS de Focazul (#2164)

- El constructor de rutas remoto impide cualquier destino distinto de `taxocommerce-core` en Asociación Focazul. STAGING mantiene sus módulos instalados y autorizados; PRO permanece fuera del flujo. Se reutiliza la prueba existente que falló en el PR #2155 (`test_shared_ftp_site_roots_are_isolated`), sin cambiar archivos instalables de plugins ni ejecutar ningún AutoCheck automáticamente.

## 2026-11-03 — Auditoría comparativa de Core en STAGING y Focazul (#2107)

- Auditoria read-only desde GitHub de dos instalaciones que reciben el MISMO plugin Core: comparar hashes SHA-256 de todos los archivos instalables con el commit fuente y detectar divergencias sin reparar/copiar contenidos de WordPress.
- Revisar sitio público, enlaces y posibles referencias comerciales en el código genérico; los menús, categorías, páginas, países, proveedores y datos son independientes en cada WordPress.
- Verificar estructura común de las pantallas del editor de menús vía acceso autenticado **solo cuando** haya usuarios de prueba y secretos de GitHub por sitio. Si faltan, estado NOT_CHECKED; no se presenta un éxito ficticio. Pruebas offline del auditor y tests Core de CI.
- Producir informe en resumen del workflow y artifacts JSON/Markdown con PASS / FAIL / WARNING / NOT_CHECKED. Se ejecuta tras los despliegues de STAGING y Focazul, o por workflow_dispatch independiente; además se ejecutó auditoría read-only desde rama de QA.
- No hay cambios de código funcional de Core, ni instalaciones/deploys a PRODUCCIÓN. No cambia la versión 0.4.51 ni el histórico congelado del monolito.
