## 2026-11-15 — Despliegue simple GitHub STAGING → PRODUCCIÓN (#2719)

- Se elimina del workflow de producción cualquier inventario o filtrado de plugins instalados, activo/inactivo, comprobación de cabeceras Core/WooCommerce y llamadas REST heredadas del monolítico.
- Git compara `staging` con el último despliegue correcto a producción y selecciona todos los archivos distribuibles añadidos/modificados de los módulos `taxocommerce-*`. Los archivos no cambiados no se transfieren; las eliminaciones de origen no borran ficheros en PRO.
- Se restauran los secretos FTPS anteriores de GitHub: `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`; no se exigen los nuevos `PRO_FTPS_*`.
- La copia va directamente a `wp-content/plugins` de la cuenta FTP de PRODUCCIÓN, con subcarpetas por plugin; compara SHA-256 del archivo y realiza una transferencia solo cuando difiere, con restauración de la versión previa si el archivo no queda verificado. No instala, activa o desactiva plugins vía WordPress.
- Cuando no hay cambios finaliza correctamente sin conexión FTP. La existencia del directorio de plugins y los permisos de la cuenta FTP deben permitir la transferencia real; **no se considera desplegado hasta que el workflow lo confirme**.

## 2026-11-15 — STAGING a PRODUCCIÓN: ejecución manual sin selector de plugin (#2717)

- Eliminado el parámetro opcional `target_plugin` del formulario manual del workflow `despliege_de_main_a_pro.yml`: al pulsar **Run workflow** se procesan todos los archivos modificados de plugins ya instalados, sin elegir uno.
- Se mantiene `staging` como único origen autorizado y la comprobación de rama de GitHub Actions. El selector nativo de rama de GitHub no se puede ocultar mediante YAML.
- Se conserva la entrada opcional para el workflow reutilizable automático, que depende de ella; sin cambios en sus llamadas ni en las garantías de inventario, SHA, reversión o despliegue.
- Prueba de regresión del formulario manual añadida. No se ha ejecutado un despliegue de producción: el acceso FTPS sigue pendiente de validación en el Issue #2696.

## 2026-11-15 — Verificación directa del WordPress PRO con la cuenta FTPS de STAGING (#2696)

- La cuenta FTPS existente se utiliza en un alojamiento compartido cuya raíz de login contiene las instalaciones de STAGING y Focazul como subdirectorios. El sondeo de producción se acota ahora estrictamente a `./wp-content/plugins`, en la raíz de esa misma cuenta, validando las versiones reales de Core y WooCommerce.
- No se rastrean otros sitios ni se necesita esperar un inventario de 90 directorios. Se conserva el comportamiento general anterior para cuentas no compartidas. Es una prueba **solo lectura**, no un despliegue.

## 2026-11-15 — Verificación del acceso FTPS de STAGING a la instalación PRO (#2696)

- Prueba de solo lectura reutilizando los secretos existentes `STAGING_FTP_SERVER/USERNAME/PASSWORD`, sin activar el monolítico ni configurar una cuenta nueva. La prueba solo lee las cabeceras de Core 0.4.84 y WooCommerce 11.3.0 para identificar la carpeta real de producción y exige una única coincidencia.
- No cambia el desplegador ni autoriza escrituras: si el acceso existente no acredita inequívocamente la identidad de producción, la transferencia sigue bloqueada.

## 2026-11-15 — Credenciales FTPS dedicadas al despliegue de producción (#2696)

- El workflow de producción y el sondeo de solo lectura usan exclusivamente `PRO_FTPS_HOST`, `PRO_FTPS_USER` y `PRO_FTPS_PASSWORD`. Esto evita mezclar el FTPS del antiguo receptor/monolítico con el acceso al verdadero `wp-content/plugins`, sin modificar secretos de STAGING.
- El segundo juego de credenciales históricas del auditor `TC_FTP_*` ya no está disponible en GitHub; su sondeo falló por credenciales ausentes. La configuración dedicada debe autorizar únicamente el destino de plugins de producción.
- Si no existe la cuenta nueva o no superan las pruebas de identidad de Core/WooCommerce, se bloquea sin modificar ficheros de producción.

## 2026-11-15 — Diagnóstico de credenciales FTPS alternativas de GitHub (#2696)

- El sondeo estrictamente de solo lectura verifica el juego de secretos `TC_FTP_HOST/USER/PASS` ya utilizado en el auditor de producción, para determinar si alcanza WP_PLUGIN_DIR cuando las credenciales del antiguo handoff `FTP_SERVER/USERNAME/PASSWORD` están confinadas al monolítico.
- La modificación no cambia el transporte de producción, no lee datos privados fuera de las cabeceras de versión, no escribe ficheros y no reactiva el monolítico.

## 2026-11-15 — Despliegue a PRO autónomo y sin monolítico (#2696)

- El flujo `STAGING a PRODUCCION` ya no utiliza `/taxocommerce/v1/deploy-active-modules` ni la función PHP del plugin SEO monolítico. El origen queda limitado al SHA aprobado de `staging`.
- Nuevo transporte FTPS con inventario de plugins físicamente instalados, prueba de identidad de la carpeta de plugins con Core/WooCommerce, TLS, paths acotados, copia temporal comprobada por SHA-256, copia de seguridad reversible y verificación final de cada fichero.
- Un preflight compara la fuente con el último release correcto y finaliza con éxito («sin cambios») sin contactar a producción cuando no hay ficheros desplegables diferentes.
- No se instala ningún plugin nuevo, no se reactiva el monolítico, no se modifica el estado de activación y no se borran archivos ajenos. Las pruebas CI simulan actualizaciones y restauraciones sin acceso remoto.
- **Bloqueo real previo al uso:** el FTPS configurado en GitHub no ha podido acreditar acceso a la carpeta física `wp-content/plugins`; el nuevo flujo falla cerrado antes de escribir hasta configurar una cuenta FTPS que sí lo permita. No equivale a despliegue verificado en PRO.

## 2026-11-15 — PRO: descubrimiento acotado de la carpeta FTPS de WordPress (#2696)

- El diagnóstico FTP inicial solo reconocía ubicaciones fijas. Se amplía a un descubrimiento MLSD de solo lectura, con límites de profundidad y cantidad de directorios, sin abrir ni modificar archivos de otros sitios.
- Se comprueban las versiones de los plugins Core y WooCommerce contra las verificadas en el WordPress de producción; se requiere coincidencia en una única carpeta. Los fallos muestran contadores, no rutas ni credenciales.
- La validación no equivale a autorizar despliegues: los dos workflows #207/#208 no se han reintentado y no se ha reactivado el monolítico.

## 2026-11-15 — Despliegue PRO: diagnóstico del receptor REST heredado (#2696)

- Confirmados los fallos de las ejecuciones #207 y #208: el receptor `taxocommerce/v1/deploy-active-modules` está registrado exclusivamente en el monolítico, ahora desactivado; ambos endpoints REST responden 404 antes de transferir archivos.
- Se añade una comprobación FTPS estrictamente de solo lectura para verificar que las credenciales acceden al directorio real de plugins: contrasta las cabeceras de Core y WooCommerce con el inventario obtenido por WordPress, sin mostrar rutas/secretos y bloqueando si no existe una ubicación única.
- El diagnóstico no sustituye todavía el despliegue REST ni permite saltarse el inventario de producción; no escribe ni elimina ficheros. Debe comprobarse la capacidad real de acceso antes de plantear la migración de canal.

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
