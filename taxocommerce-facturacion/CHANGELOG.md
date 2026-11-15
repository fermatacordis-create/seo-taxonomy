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
