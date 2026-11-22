<?php
/**
 * Render HTML/PDF para facturas, proformas y presupuestos.
 */

defined('ABSPATH') || exit;

final class SEO_Facturas_PDF {

    public static function render_html(array $snapshot) {
        $type = sanitize_key((string) ($snapshot['document']['type'] ?? ''));
        $template = (SEO_Facturas_Documents::TYPE_RECTIFICATION === $type)
            ? SEO_FACTURAS_PATH . 'templates/rectification.php'
            : ((SEO_Facturas_Documents::TYPE_QUOTE === $type)
                ? SEO_FACTURAS_PATH . 'templates/quote.php'
                : SEO_FACTURAS_PATH . 'templates/document.php');

        if (!is_readable($template)) {
            return new WP_Error('seo_facturas_template_missing', 'No se encuentra la plantilla del documento.');
        }

        ob_start();
        include $template;
        return (string) ob_get_clean();
    }

    /**
     * Convierte HTML en binario PDF sin guardarlo. Se usa para presupuestos
     * efimeros generados desde el carrito y tambien internamente por create_pdf().
     */
    public static function render_binary($html, $document_number = '', $context_id = 0, $orientation = 'portrait') {
        $html = (string) $html;
        $document_number = (string) $document_number;
        $context_id = absint($context_id);
        $orientation = in_array((string) $orientation, array('portrait', 'landscape'), true) ? (string) $orientation : 'portrait';

        if ('' === trim($html)) {
            return new WP_Error('seo_facturas_pdf_invalid_html', 'El HTML del documento esta vacio.');
        }

        $custom_binary = apply_filters(
            'seo_facturas_pdf_binary',
            null,
            $html,
            $context_id,
            $document_number,
            $orientation
        );

        if (is_wp_error($custom_binary)) {
            return $custom_binary;
        }
        if (is_string($custom_binary) && '' !== $custom_binary) {
            return $custom_binary;
        }

        self::load_dompdf();
        if (!class_exists('Dompdf\\Dompdf')) {
            return new WP_Error(
                'seo_facturas_pdf_engine_missing',
                'Dompdf no esta disponible dentro de TaxoCommerce Facturación.'
            );
        }

        try {
            $options_class = 'Dompdf\\Options';
            $dompdf_class = 'Dompdf\\Dompdf';

            $options = new $options_class();
            $options->set('isRemoteEnabled', false);
            $options->set('isHtml5ParserEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');

            $dompdf = new $dompdf_class($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', $orientation);
            $dompdf->render();
            $binary = $dompdf->output();

            if (!is_string($binary) || '' === $binary) {
                return new WP_Error('seo_facturas_pdf_empty', 'El motor PDF no devolvio contenido.');
            }

            return $binary;
        } catch (Throwable $e) {
            return new WP_Error('seo_facturas_pdf_exception', $e->getMessage());
        }
    }

    public static function create_pdf($document_id, $document_number, $issued_at, $html) {
        $document_id = absint($document_id);
        $document_number = (string) $document_number;
        $issued_at = (string) $issued_at;
        $html = (string) $html;

        if (!$document_id || '' === $document_number || '' === $html) {
            return new WP_Error('seo_facturas_pdf_invalid_input', 'Datos insuficientes para crear el PDF.');
        }

        $binary = self::render_binary($html, $document_number, $document_id);
        if (is_wp_error($binary)) {
            return $binary;
        }

        return self::store_binary($document_id, $document_number, $issued_at, $binary);
    }

    public static function engine_status() {
        self::load_dompdf();
        if (class_exists('Dompdf\\Dompdf')) {
            return array(
                'available' => true,
                'label'     => 'Dompdf disponible',
            );
        }

        return array(
            'available' => false,
            'label'     => 'Dompdf no disponible',
        );
    }

    private static function load_dompdf() {
        if (class_exists('Dompdf\\Dompdf')) {
            return;
        }

        $autoloaders = array(
            // Instalacion mediante Composer dentro del modulo.
            SEO_FACTURAS_PATH . 'vendor/autoload.php',

            // Instalacion manual con el ZIP empaquetado oficial de Dompdf.
            // autoload.inc.php carga internamente dompdf/vendor/autoload.php.
            SEO_FACTURAS_PATH . 'dompdf/autoload.inc.php',

            // Fallback defensivo por si se ha subido el autoloader de la
            // distribucion manual pero falta autoload.inc.php.
            SEO_FACTURAS_PATH . 'dompdf/vendor/autoload.php',
        );


        foreach ($autoloaders as $autoload) {
            if (is_readable($autoload)) {
                require_once $autoload;
                if (class_exists('Dompdf\\Dompdf')) {
                    return;
                }
            }
        }
    }

    private static function store_binary($document_id, $document_number, $issued_at, $binary) {
        $base = self::private_base_dir();
        if (is_wp_error($base)) {
            return $base;
        }

        $year = self::year_from_date($issued_at);
        $dir = trailingslashit($base) . $year;
        if (!wp_mkdir_p($dir)) {
            return new WP_Error('seo_facturas_pdf_dir', 'No se puede crear el directorio privado de facturas.');
        }

        self::protect_directory($base);
        self::protect_directory($dir);

        $safe_number = sanitize_file_name($document_number);
        $salt = defined('AUTH_SALT') ? AUTH_SALT : wp_salt('auth');
        $token = substr(hash('sha256', $salt . '|' . $document_id . '|' . $document_number), 0, 16);
        $path = trailingslashit($dir) . $safe_number . '-' . $token . '.pdf';

        $written = file_put_contents($path, $binary, LOCK_EX);
        if (false === $written || $written <= 0) {
            return new WP_Error('seo_facturas_pdf_write', 'No se puede guardar el PDF generado.');
        }

        return array(
            'path' => $path,
            'hash' => hash_file('sha256', $path),
            'size' => filesize($path),
        );
    }

    private static function private_base_dir() {
        $uploads = wp_upload_dir(null, false);
        if (!empty($uploads['error'])) {
            return new WP_Error('seo_facturas_upload_dir', (string) $uploads['error']);
        }

        $base = trailingslashit($uploads['basedir']) . 'seo-facturas';
        if (!is_dir($base) && !wp_mkdir_p($base)) {
            return new WP_Error('seo_facturas_upload_dir_create', 'No se puede crear el directorio de facturas.');
        }
        return $base;
    }

    private static function protect_directory($dir) {
        if (!is_dir($dir)) {
            return;
        }

        $index = trailingslashit($dir) . 'index.php';
        if (!file_exists($index)) {
            @file_put_contents($index, "<?php\n// Silence is golden.\n", LOCK_EX);
        }

        $htaccess = trailingslashit($dir) . '.htaccess';
        if (!file_exists($htaccess)) {
            $rules = "Order deny,allow\nDeny from all\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n";
            @file_put_contents($htaccess, $rules, LOCK_EX);
        }

        $webconfig = trailingslashit($dir) . 'web.config';
        if (!file_exists($webconfig)) {
            $rules = '<?xml version="1.0" encoding="UTF-8"?><configuration><system.webServer><authorization><remove users="*" roles="" verbs=""/><add accessType="Deny" users="*"/></authorization></system.webServer></configuration>';
            @file_put_contents($webconfig, $rules, LOCK_EX);
        }
    }

    private static function year_from_date($issued_at) {
        $timestamp = strtotime((string) $issued_at);
        if (!$timestamp) {
            return wp_date('Y');
        }
        return wp_date('Y', $timestamp);
    }
}
