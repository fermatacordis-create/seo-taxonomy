#!/usr/bin/env python3
"""Verify/regenerate self-contained TaxoCommerce admin headers.

One canonical source lives under .github/templates. Each plugin ships its own
copy, so it works even if Core and every other TaxoCommerce plugin are absent.
"""
import argparse
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
TEMPLATE = ROOT / ".github/templates/tc-family-admin-branding.php.tpl"
INFO_TEMPLATE = ROOT / ".github/templates/tc-family-official-excerpt.php.tpl"
PLUGINS = {
    "auditor": ("dashicons-chart-area", "Audita la calidad del contenido, los indicadores y los informes de WooCommerce."),
    "redirects": ("dashicons-randomize", "Gestiona redirecciones y eliminaciones asistidas con controles de seguridad."),
    "faqs": ("dashicons-editor-help", "Organiza las preguntas frecuentes de productos, categorías y páginas."),
    "images": ("dashicons-format-image", "Audita y optimiza las imágenes de tu catálogo y biblioteca multimedia."),
    "social": ("dashicons-share", "Gestiona conexiones sociales, publicaciones, plantillas y calendario."),
    "database": ("dashicons-database", "Consulta y mantén las tablas de datos de WordPress y WooCommerce."),
    "campaigns": ("dashicons-megaphone", "Organiza campañas comerciales, ofertas, creatividades y calendario."),
    "suppliers": ("dashicons-store", "Gestiona catálogos, compras, precios y relaciones con proveedores."),
    "scrappers": ("dashicons-search", "Investiga productos y mercados con Ojeador e Ingeniero."),
    "logistica": ("dashicons-car", "Gestiona pesos, dimensiones y costes de envío de WooCommerce."),
    "vocabulary": ("dashicons-tag", "Administra el vocabulario semántico y los atributos del catálogo."),
    "facturacion": ("dashicons-media-spreadsheet", "Gestiona facturas, proformas y presupuestos en WooCommerce."),
    "dependiente": ("dashicons-businessperson", "Configura el asistente de ventas, su aprendizaje y sus pruebas."),
    "google-bing": ("dashicons-chart-bar", "Conecta servicios externos y consulta informes de análisis."),
    "distribution": ("dashicons-rss", "Publica feeds comerciales y sitemaps XML de tu catálogo."),
    "import-export": ("dashicons-upload", "Importa y exporta contenidos y productos mediante procesos controlados."),
    "system-check": ("dashicons-shield", "Comprueba el estado de WordPress, el servidor y los plugins."),
    "templates": ("dashicons-layout", "Gestiona plantillas, enrutado de páginas y reglas de presentación."),
    "tasks": ("dashicons-clipboard", "Private task management, calendar and indicators."),
}

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--check", action="store_true", help="Only check consistency; never write.")
    args = parser.parse_args()
    template = TEMPLATE.read_text(encoding="utf-8")
    official_template = INFO_TEMPLATE.read_text(encoding="utf-8")
    errors = []
    for slug, (icon, description) in PLUGINS.items():
        # Every independent plugin exposes its own bundled changelog.
        # Keep the source generated from the canonical template, never from Core.
        history_css = history_php = history_html = ""
        history_css = ".tc-family-history{margin-top:11px;max-width:680px;border:1px solid #dfe6ef;border-radius:7px;background:#fff;color:#26354c}.tc-family-history summary{padding:9px 12px;cursor:pointer;font-size:13px;font-weight:600}.tc-family-history summary:focus-visible{outline:2px solid #1769de;outline-offset:2px}.tc-family-history__content{max-height:290px;overflow:auto;padding:12px;border-top:1px solid #e4e9f0;white-space:pre-wrap;overflow-wrap:anywhere;font:12px/1.6 monospace}"
        history_php = "    $tc_history_file = dirname( __DIR__ ) . '/CHANGELOG.md';\n    $tc_history = is_readable( $tc_history_file ) ? file_get_contents( $tc_history_file ) : '';\n    $tc_history_labels = array(\n        'es' => 'Historial de versiones y mejoras',\n        'en' => 'Version history and improvements',\n        'fr' => 'Historique des versions et améliorations',\n        'de' => 'Versionsverlauf und Verbesserungen',\n        'nl' => 'Versiegeschiedenis en verbeteringen',\n    );\n    $tc_history_label = __( 'Version history and improvements', '__TC_DOMAIN__' );\n    if ( 'Version history and improvements' === $tc_history_label && isset( $tc_history_labels[ $tc_language ] ) ) {\n        $tc_history_label = $tc_history_labels[ $tc_language ];\n    }\n"
        history_html = "            <?php if ( is_string( $tc_history ) && '' !== trim( $tc_history ) ) : ?>\n                <details class=\"tc-family-history\">\n                    <summary><?php echo esc_html( $tc_history_label ); ?></summary>\n                    <div class=\"tc-family-history__content\"><?php echo esc_html( $tc_history ); ?></div>\n                </details>\n            <?php endif; ?>\n"
        result = (template
                  .replace("__TC_DOMAIN__", "taxocommerce-" + slug)
                  .replace("__TC_ICON__", icon)
                  .replace("__TC_HISTORY_CSS__", history_css)
                  .replace("__TC_HISTORY_PHP__", history_php)
                  .replace("__TC_HISTORY_HTML__", history_html)
                  .replace("__TC_DOMAIN__", "taxocommerce-" + slug))
        info_path = ROOT / ("taxocommerce-" + slug) / "includes/official-excerpt.php"
        info_result = official_template.replace("__TC_DOMAIN__", "taxocommerce-" + slug)
        if args.check:
            if not info_path.is_file() or info_path.read_text(encoding="utf-8") != info_result:
                errors.append(slug + ":official")
        else:
            info_path.parent.mkdir(parents=True, exist_ok=True)
            info_path.write_text(info_result, encoding="utf-8")
        path = ROOT / ("taxocommerce-" + slug) / "includes/admin-branding.php"
        if args.check:
            if not path.is_file() or path.read_text(encoding="utf-8") != result:
                errors.append(slug)
        else:
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text(result, encoding="utf-8")
    core_info_path = ROOT / "taxocommerce-core/includes/official-excerpt.php"
    core_info = official_template.replace("__TC_DOMAIN__", "taxocommerce-core")
    if args.check:
        if not core_info_path.is_file() or core_info_path.read_text(encoding="utf-8") != core_info:
            errors.append("core:official")
    else:
        core_info_path.write_text(core_info, encoding="utf-8")
    if errors:
        raise SystemExit("Branding files differ from canonical template: " + ", ".join(errors))
    print(str(len(PLUGINS)) + " independent TaxoCommerce admin headers " + ("match." if args.check else "regenerated."))

if __name__ == "__main__":
    main()
