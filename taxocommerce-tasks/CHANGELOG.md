## [0.1.17] — 2026-11-10 — Cabecera en tres zonas sin contenido duplicado (#2521)

- Izquierda: nombre, descripción local del plugin y un único enlace estático `Plugin URI` bajo el texto.
- Centro: desplegable más ancho con el historial local `CHANGELOG.md`, cerrado inicialmente.
- Derecha: versión y fecha reales del propio plugin.
- Se retira la segunda caja «Información del plugin», sin cambios en servicios, menús ni consultas externas.

## [0.1.16] — 2026-11-10 — Descripción visible desde el plugin (#2494)

- La descripción inmediatamente debajo del título administrativo procede de `Description:` del archivo principal, sin textos duplicados ni consultas externas.
- Se mantienen el enlace estático `Plugin URI`, el historial local y las funcionalidades anteriores.

## [0.1.15] — 2026-11-10 — Ficha local en el encabezado (#2494)

- Elimina consultas REST/caché del extracto y usa `Description` y `Plugin URI` locales.
- Conserva el enlace oficial y el historial, sin alterar servicios existentes.

## [0.1.14] - 2026-11-10 - Resiliencia del extracto oficial (#2489)

- Consulta exclusivamente la API REST pública de la página indicada en Plugin URI (autor del plugin), nunca páginas de la tienda del cliente.
- Conserva el último extracto oficial comprobado; los fallos de red no borran el texto. Caché de errores reducida a 5 minutos y versión nueva de clave para ignorar fallos antiguos cacheados.
- Si nunca se ha obtenido un extracto, muestra provisionalmente la Description incluida por el autor en el plugin; permite abrir la ficha oficial. No cambia operaciones comerciales ni requiere Core.
- Pruebas para recuperación tras fallos HTTP, JSON inválido y caché negativa; compatibles con la familia completa.

## [0.1.13] — 2026-11-09 — Ficha oficial del desarrollador y cabecera centrada en tres zonas (#2476)

- La cabecera distribuye identificación a la izquierda, dos desplegables alineados y centrados (extracto oficial a la izquierda e historial local a la derecha), y versión y fecha a la derecha; distribución adaptable a móvil.
- El enlace oficial se obtiene exclusivamente de Plugin URI, definido por el autor del plugin. Eliminados el formulario de URL y su acción de guardado; los ajustes antiguos de la tienda no pueden sobrescribirlo.
- El historial se lee del CHANGELOG.md incluido, plegado de inicio. Sin dependencias obligatorias entre plugins ni cambios en productos, pedidos, datos o funcionalidades existentes.

## [0.1.12] — 2026-11-08 — Información dinámica desde ficha oficial (#2443)

- El desplegable izquierdo consulta el excerpt publicado en la página Plugin URI, con caché y configuración independiente.
- Formulario sanitizado; soporte para desactivación y funcionamiento sin servicios externos. Historial local sin cambios.

## [0.1.11] — 2026-11-08 — Extracto público remoto opcional (#2443)

- Consulta opcional por HTTPS del excerpt REST de una ficha oficial configurada; muestra el enlace a esa página en un panel izquierdo plegado.
- Mantiene el historial propio en el panel derecho. Caché, permisos y escape de salida; sin servicios obligatorios ni cambios en datos.

## [0.1.10] - 2026-11-08 - Autotest funcional y de codigo (#2401)

- Conserva autotest funcional y JSON; nuevo Code Check independiente del propio plugin: PHP, WordPress, seguridad. Cinco velocidades, lotes y JSON separado. Sin PCP ni Core obligatorio.

## [0.1.9] — 2026-11-08 — Historial plegable en cabecera (#2382)

- Desplegable cerrado por defecto inmediatamente debajo del título, con el historial propio de `CHANGELOG.md`; sin nueva pestaña ni dependencia de otros plugins.
- Textos del título preparados para ES/EN/FR/DE/NL, salida escapada, desplazamiento vertical y mantenimiento de menús y funciones existentes.
- El paquete instalable y la subida incremental incluyen únicamente el historial raíz necesario. Sin cambios en base de datos ni servicios.

## [0.1.8] — 2026-11-08 — Historial visible en instalaciones de prueba (#2367)

- Corregida la distribución del archivo raíz `CHANGELOG.md`, que anteriormente estaba excluido y ocultaba el desplegable incorporado en 0.1.7.
- El despliegue incremental y el ZIP de Plugin Check incluyen ahora este archivo, pero siguen excluyendo el resto de documentación Markdown interna.
- Nuevas pruebas verifican el manifiesto distribuible, la presencia del historial en la cabecera y su estado inicial plegado.
- No se modifican tablas, tareas, permisos ni interfaces públicas; sin dependencia de Core.

## [0.1.7] — 2026-11-08 — Historial plegado en cabecera (#2363)

- Añadido un desplegable `<details>` cerrado por defecto dentro de la cabecera y debajo del título, sin nueva pestaña.
- Se muestra `CHANGELOG.md` local con salida escapada, límite de altura y desplazamiento para no alterar la navegación.
- Etiqueta localizada ES/EN/FR/DE/NL, compatible con gettext y funcionamiento autónomo sin Core.
- Sin modificación de tareas, calendario, base de datos, permisos ni endpoints.

## [0.1.6] — 2026-11-07 — Cabecera visual uniforme para Tasks (#2336)

- Tasks utiliza la misma cabecera visual autónoma que el resto de plugins TaxoCommerce (título, versión y fecha de actualización).
- Se elimina el encabezado sencillo duplicado; se mantienen intactas las siete pestañas, navegación, tareas, categorías, calendario, manuales y autocheck.
- La cabecera se genera desde la plantilla común con gettext de WordPress para ES/EN/FR/DE/NL y funciona con o sin Core.
- Prueba de regresión de cabecera común y versión sincronizada con readme.
- Corregido el auditor GNU `.mo` de CI: acepta catálogos válidos sin tabla hash opcional; mantiene comprobaciones de tablas y punteros dañados, con pruebas adicionales. Esta corrección evita falsos bloqueos de despliegue.

## [0.1.5] — 2026-11-07 — Cabecera con versión y fecha (#2336)

- Cabecera de administración con título, versión actual y fecha de esta versión, sin modificar datos ni funcionalidades.
- Fecha de publicación persistente, independiente del reloj o FTP.

# TaxoCommerce Tasks — CHANGELOG

## 0.1.4 — 2026-11-07

- Calendario mensual con barras continuas desde fecha de inicio hasta vencimiento y segmentación por semanas, incluidos cruces entre meses.
- Distribución sin solapamientos visuales mediante carriles; continuidad entre semanas cuando es posible, barras de un día cuando solo hay una fecha.
- Leyenda por prioridad (baja/normal/alta/urgente), bloqueo rayado y completadas atenuadas; información accesible y apertura de la tarea al pulsar la barra.
- Consulta privada por fechas superpuestas al periodo visible, sin crear publicaciones ni cambiar tablas.
- CSS independiente y adaptable a pantallas pequeñas; documentación y traducciones nativas ES/EN/FR/DE/NL.
- Prueba de regresión para cruces de semana/mes, posiciones, fechas ausentes, seguridad y enlaces; Issue #2329.


## 0.1.3 — 2026-11-07

- Los manuales de usuario y técnico se muestran en sus pestañas de administración existentes, con contenido completo en ES/EN/FR/DE/NL mediante gettext literal de WordPress.
- Ampliados y compilados los catálogos de 72 a 126 entradas por idioma (POT/PO/MO); añadidas las etiquetas de los manuales y de la edición de categorías que faltaban en los catálogos anteriores.
- Compatibilidad con los paquetes oficiales de idioma y selección de locale de WordPress; los textos de cliente no se traducen ni publican.
- Pruebas de 126 mensajes por idioma, 2 manuales y cinco locales, preservando gestión de categorías, menús e independencia.
- Incidencia #2313. Sin modificación de tablas ni despliegue a producción.


## Fusión de traducciones gettext (Issue #2311)

- Incorporado gettext de WordPress para las 67 etiquetas existentes y cinco catálogos PO/MO.
- Se conservan las traducciones de respaldo y las funciones de manuales y edición de categorías de v0.1.2.
- Se mantiene la independencia de WooCommerce, los datos y la navegación opcional bajo Core.


## 0.1.2 — 2026-11-07

- Incorporados **Manual de usuario** y **Manual técnico** como pestañas de administración de lectura, completos en ES/EN/FR/DE.
- Añadida la edición de nombres de categorías privadas existentes (Editar, Guardar cambios, Cancelar), conservando su `id` y las relaciones con tareas.
- Reutilizada la acción POST protegida por nonce y `manage_options`; validación de ID y nombre único, con errores informados en la interfaz.
- Pruebas automatizadas de renombrado, altas, nombres duplicados, IDs inexistentes, formulario y cuatro idiomas; integradas en CI.
- Se conserva el menú opcional bajo Core, la autonomía sin Core y la privacidad absoluta de tareas y categorías.
- Issue #2313, seguimiento en STAGING y Focazul; producción excluida.


## 0.1.1 — 2026-11-07

- Corregida la navegación: Tasks pasa a figurar en el submenú de TaxoCommerce Core cuando Core está activo, sin generar un menú de primer nivel duplicado.
- Inscrito como módulo y extensión opcional de Core para aparecer en el catálogo de extensiones, con el mismo `admin.php?page=taxocommerce-tasks`.
- Sin Core, mantiene el menú principal propio y puede funcionar de manera independiente.
- Sin modificaciones de tablas, categorías, tareas, permisos o URLs; pruebas de menú de ambas modalidades y Plugin Check.
- Incidencia #2290; PR correctivo independiente de la implantación inicial.

## 0.1.0 — 2026-11-07

- Nuevo plugin autónomo de tareas internas, sin dependencia de WooCommerce ni TaxoCommerce Core.
- Tablas propias de tareas y categorías mediante dbDelta; sin posts, CPT, taxonomías públicas ni datos preinstalados.
- CRUD privado de tareas/categorías, prioridades, estados, responsables WordPress, inicio, vencimiento y finalización.
- Calendario mensual, filtros y panel de indicadores con gráficos CSS accesibles.
- Autocheck manual de solo lectura y descarga JSON con nonce y control de permisos.
- Etiquetas de administración internacionalizadas para ES, EN, FR y DE; WordPress gettext tiene prioridad.
- Issue #2290; validación mediante CI y WordPress Plugin Check antes de publicar.
