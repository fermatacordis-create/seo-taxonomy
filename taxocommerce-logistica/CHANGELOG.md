## [0.2.17] — 2026-11-10 — Descripción visible desde el plugin (#2494)

- La descripción inmediatamente debajo del título administrativo procede de `Description:` del archivo principal, sin textos duplicados ni consultas externas.
- Se mantienen el enlace estático `Plugin URI`, el historial local y las funcionalidades anteriores.

## [0.2.16] — 2026-11-10 — Ficha local en el encabezado (#2494)

- Elimina consultas REST/caché del extracto y usa `Description` y `Plugin URI` locales.
- Conserva el enlace oficial y el historial, sin alterar servicios existentes.

## [0.2.15] - 2026-11-10 - Resiliencia del extracto oficial (#2489)

- Consulta exclusivamente la API REST pública de la página indicada en Plugin URI (autor del plugin), nunca páginas de la tienda del cliente.
- Conserva el último extracto oficial comprobado; los fallos de red no borran el texto. Caché de errores reducida a 5 minutos y versión nueva de clave para ignorar fallos antiguos cacheados.
- Si nunca se ha obtenido un extracto, muestra provisionalmente la Description incluida por el autor en el plugin; permite abrir la ficha oficial. No cambia operaciones comerciales ni requiere Core.
- Pruebas para recuperación tras fallos HTTP, JSON inválido y caché negativa; compatibles con la familia completa.

## [0.2.14] — 2026-11-09 — Ficha oficial del desarrollador y cabecera centrada en tres zonas (#2476)

- La cabecera distribuye identificación a la izquierda, dos desplegables alineados y centrados (extracto oficial a la izquierda e historial local a la derecha), y versión y fecha a la derecha; distribución adaptable a móvil.
- El enlace oficial se obtiene exclusivamente de Plugin URI, definido por el autor del plugin. Eliminados el formulario de URL y su acción de guardado; los ajustes antiguos de la tienda no pueden sobrescribirlo.
- El historial se lee del CHANGELOG.md incluido, plegado de inicio. Sin dependencias obligatorias entre plugins ni cambios en productos, pedidos, datos o funcionalidades existentes.

## [0.2.13] — 2026-11-08 — Información dinámica desde ficha oficial (#2443)

- El desplegable izquierdo consulta el excerpt publicado en la página Plugin URI, con caché y configuración independiente.
- Formulario sanitizado; soporte para desactivación y funcionamiento sin servicios externos. Historial local sin cambios.
- Corregidos los avisos estrictos de Plugin Check: comentarios para los marcadores de traducción y documentación de compatibilidad del filtro de envío heredado, sin alterar sus resultados ni integraciones.

## [0.2.12] — 2026-11-08 — Extracto público remoto opcional (#2443)

- Consulta opcional por HTTPS del excerpt REST de una ficha oficial configurada; muestra el enlace a esa página en un panel izquierdo plegado.
- Mantiene el historial propio en el panel derecho. Caché, permisos y escape de salida; sin servicios obligatorios ni cambios en datos.

## [0.2.11] - 2026-11-08 - Autotest funcional y de codigo (#2401)

- Conserva autotest funcional y JSON; nuevo Code Check independiente del propio plugin: PHP, WordPress, seguridad. Cinco velocidades, lotes y JSON separado. Sin PCP ni Core obligatorio.
## [0.2.11] — 2026-11-08 — Traducciones de compras a proveedores en Logistics (#2062)

- Incorporadas al POT y a los PO/MO de ES/EN/FR/DE/NL las 23 cadenas de la pestaña Supplier purchases añadida por #2380, que no figuraban en los catálogos de la versión 0.2.10.
- Cada catálogo tiene 262 mensajes traducibles y cabecera gettext; revisión de 0 vacíos, 0 claves ausentes y 0 discrepancias printf.
- Prueba CLI ampliada para comprobar traducciones compiladas de la pestaña. No se modifican el código de compras, las reglas de envío, los pedidos, los datos ni los parámetros de WooCommerce. Integración con Suppliers opcional.
- Versión 0.2.11 y `readme.txt` actualizados, conservando `changelog.txt` y el historial anterior. Pendiente validación real por idioma en STAGING y Focazul y auditoría de otras cadenas fuera de gettext.

## [0.2.10] — 2026-11-08 — Internacionalización completa de formularios y controles Logistics (#2062)

- Traducidos 98 textos PHP de interfaz, pedidos, reglas e informes Autocheck mediante gettext en ES/EN/FR/DE/NL.
- Mantiene la pestaña de compras a proveedores y el historial plegable previamente integrados.

## [0.2.9] — 2026-11-08 — Historial plegable en cabecera (#2382)

- Desplegable del historial de este plugin, cerrado por defecto bajo el título; no interfiere con la compra a proveedores ni traducciones.

## [Unreleased] — Acceso directo a compra de productos del proveedor desde Logistics (#2379)

- Nueva pestaña «Supplier purchases» con relación entre cada línea del pedido WooCommerce y las fuentes del catálogo de Suppliers, incluidas variaciones, fuentes alternativas, referencias, SKU, coste y enlaces externos «Comprar».
- Acceso a la compra desde la tabla de pedidos; el pedido # seleccionado abre solamente las líneas correspondientes.
- Dependencia opcional: si Suppliers está inactivo, Logistics conserva sus funciones de transporte y presenta un aviso informativo. No se crean tablas ni se altera el checkout.
- Pendiente validar la coincidencia de pedidos reales y enlaces en STAGING/Focazul, además de Plugin Check.

## [0.2.8] — 2026-11-08 — Catálogos Logistics completos en cinco idiomas (#2062)

- Traducidas las 80 cadenas que faltaban en ES, FR, DE y NL, y las 76 pendientes en EN: **396 entradas nuevas**. Textos de administración, resultados de autocheck, gestión de transporte, importación/exportación, permisos y manuales de usuario y técnicos.
- Cinco catálogos PO con 141 mensajes traducibles y cabecera; 0 vacíos, 0 claves ausentes respecto al POT, 0 marcadores printf diferentes. Regenerados los cinco catálogos MO.
- Añadida comprobación de regresión de las traducciones MO desde el autotest CLI existente. Versión 0.2.8 y fecha de publicación 2026-11-08 actualizadas en cabecera y `readme.txt`; historial `changelog.txt` conservado.
- Sin cambios de reglas, tarifas, pedidos, productos, costes, SQL, ajustes ni flujos de checkout. Independencia respecto a Core preservada.
- Queda pendiente revisar literales no extraídos por gettext y validar visualmente el idioma del administrador en STAGING/Focazul. Nunca producción sin autorización.

## [Pre-0.2.8] — Traducciones administrativas y manuales Logistics (#2062)

- Incorporadas 270 traducciones de interfaz, diagnósticos, documentación y ayudas de envíos en es_ES, en_US, fr_FR, de_DE y nl_NL (49 EN, 56 FR, 55 DE, 55 NL y 55 ES), sin sobrescribir traducciones existentes.
- Los catálogos PO conservan 142 entradas por idioma y se han regenerado los cinco MO correspondientes. Se mantienen intactos los datos, el motor de transporte, el checkout, las opciones y los identificadores gettext.
- Trabajo parcial: faltan párrafos extensos del manual y una auditoría de textos fuera de gettext, Plugin Check y pruebas en WordPress para cada idioma. Producción no autorizada.

## [0.2.7] — 2026-11-07 — Cabecera administrativa de versión y fecha (#2336)

- Muestra título, versión activa y fecha de publicación en el administrador mediante Release Date.
- La fecha no depende de la hora de consulta ni del momento de copia por FTP. Sin cambios en servicios, datos, menús ni permisos.

## [Unreleased] — Revisión i18n (seguimiento #2062)

- Completada una entrada gettext previamente vacía en ES/EN/FR/DE/NL; conserva el mismo identificador fuente y no modifica la lógica de la tienda.
- Pendiente recompilar catálogos MO y validar cobertura frente al POT, en especial discrepancias entre idiomas; no supone traducción integral del plugin.

## [Unreleased] — Reutilización segura de traducciones (#2307)

- Completadas únicamente entradas gettext PO vacías con traducciones idénticas y no ambiguas ya existentes en otros catálogos TaxoCommerce.
- Regenerados los MO de los idiomas modificados. Se conservan los textos, identificadores técnicos, funciones y datos anteriores sin cambios.
- Esta corrección es parcial: los textos restantes, las cadenas fuera de gettext y la validación visual por idioma siguen abiertos en #2062.

## [Unreleased] — Regional WordPress locale fallback (#2295)

- Respeta primero los paquetes oficiales de WordPress .mo y .l10n.php para el locale exacto.
- Si falta un catálogo local regional, usa el catálogo existente del mismo idioma (es_ES, en_US, fr_FR, de_DE o nl_NL); no cambia la configuración ni el idioma del usuario.
- Sin cambios en lógica, datos, menús o dependencias del plugin. Las traducciones de contenido incompletas siguen abiertas en #2062.

## Unreleased - Familia i18n #2062


## [0.2.6] — 2026-11-03

- Pestaña «Autocheck» con descarga JSON ya existente, conservación de controles de seguridad y de comparaciones de regresiones.
- Issue #2150, PR #2151; rutas internas y ejecuciones existentes conservadas.

- Dominio gettext, carga de catalogos locales, etiquetas de menús y textos HTML de salida estáticos; catálogos PO/MO sincronizados con el texto fuente.

# CHANGELOG — TaxoCommerce Logistics

## [0.2.5] — 2026-11-03

- Cabecera visual común basada en Core: nombre, icono, descripción, versión y diseño adaptable.
- Lógica funcional, pestañas y permisos intactos; sin dependencia de Core. Issue #2137.

> Historial maestro de versiones del plugin independiente. El origen monolítico se documenta en [`changelog.txt`](changelog.txt).

## [Unreleased]

- Inicio de internacionalización WordPress mediante gettext (`Domain Path: /languages`), catálogos por idioma y traducción de etiquetas del menú, sin cambios funcionales. Seguimiento: #2039 (plan global #2036). Cobertura restante de interfaz, Autotest y manuales pendiente de revisión por idioma.


* WordPress Plugin Check: corregidas las 12 advertencias de `includes/seo-transporte.php` sin cambiar las reglas, costes ni rutas de checkout.
* Mantener parámetros GET de enrutado y avisos como lecturas inofensivas y justificar sus excepciones de nonce solo en esos puntos.
* Exigir los nonces nativos `update-order-review` o `woocommerce-process_checkout` para aceptar destino enviado por POST; datos de destino saneados por campo y respaldo con paquete/sesión cuando no hay petición válida.
* Añadir regresión CLI de checkout (nonce ausente, inválido, revisión y finalización válidas, campos mal formados), excluirla del ZIP e impedir que Plugin Check ignore advertencias en CI.
* Issue #1947. Sin cambios en el plugin monolítico ni despliegue a producción.


## [0.2.4]

* Integrate the Logistics menu into TaxoCommerce Core when installed, keeping the standalone fallback when Core is absent.
* Preserve the Shipping Rules, Auto Test and Manual tabs and the existing shipping-option storage.
* Remove the redundant separate WordPress sidebar entry while Core is active.

## [0.2.3]

* Fix escaped output of list tags in the user and technical manuals.
* Standardize the built-in documentation tab as Manual, with Manual de usuario and Manual técnico, preserving its existing URLs.
* Keep Auto Test with 35 read-only checks and before/after comparisons.

## [0.2.2]

* Add User Guide tab with an indexed User Manual (10 chapters) and Technical Manual (11 chapters), using the same layout as the TaxoCommerce family.
* Explain real shipping regions, fees, rule priorities, missing dimensions, order audits, JSON configuration backups and the before/after Auto Test.
* Keep documentation read-only: it never loads order audits, calculates live shipping, changes settings or imports files.

## [0.2.1]

* Add separate Auto Test tab with 35 read-only checks in eight functional areas and pre/post deployment comparison.
* Check WooCommerce shipping hooks, rates, region mapping, weight/dimensions, fixed/variable/free cost rules, saved settings and import/export controllers without changing real orders or shipping rules.
* Store BEFORE and latest diagnostics by site and administrator; export JSON, detect new failures and lost check coverage.
* Mark checkout, tax, real imports and cache tests as unverified until run against staging.

## [0.2.0]

* Move sale-to-supplier purchasing to TaxoCommerce Suppliers.
* Keep Logistics focused on order weights and shipping costs.
* Add active-order shipping weight and charged-cost audit.

## [0.1.0]

* Migrated the existing Order Management and Shipping modules into an independent TaxoCommerce plugin.

## Fuentes

- [`changelog.txt`](changelog.txt), etapa monolítica.
- [`docs/PLUGIN-HISTORY-SOURCES.md`](../docs/PLUGIN-HISTORY-SOURCES.md), PR y referencias históricas.

## Actualizaciones

Para nuevas funcionalidades se edita este `CHANGELOG.md`. Antes de publicar, se debe reflejar el resumen de la versión en `readme.txt`, que es el único Changelog mostrado automáticamente por WordPress.org.
