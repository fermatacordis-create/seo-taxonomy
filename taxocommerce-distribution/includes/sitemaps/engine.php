<?php
/**
 * TaxoCommerce Distribution - sitemap engine migrated from the proven Marketing implementation.
 */
defined( 'ABSPATH' ) || exit;

function tcdist_sitemap_sitemap_storage()
{
    $upload = wp_upload_dir();

    if (!empty($upload['error'])) {
        return new WP_Error(
            'tcdist_sitemap_upload_error',
            'No se puede utilizar el directorio de uploads: ' . $upload['error']
        );
    }

    return array(
        'dir'     => trailingslashit($upload['basedir']) . 'seo-sitemaps/',
        'baseurl' => trailingslashit($upload['baseurl']) . 'seo-sitemaps/',
    );
}

/**
 * Allowed names to prevent path traversal and serve only generated XML files.
 *
 * @param string $filename
 * @return bool
 */
function tcdist_sitemap_sitemap_filename_is_allowed($filename)
{
    return (bool) preg_match(
        '/\Asitemap(?:[_-][a-z0-9]+)*(?:-\d+)?\.xml\z/',
        strtolower((string) $filename)
    );
}

/**
 * Who publishes the public XML sitemap.
 *
 * Distribution owns /sitemap.xml by default. Another plugin can claim it with
 * the `taxocommerce_sitemap_owner` filter (OPNV returns 'opnv'). Then this
 * module stops serving the files, editing robots.txt and disabling the
 * WordPress sitemap, and offers its category URLs to that owner instead
 * (see tcdist_sitemap_offer_groups_to_opnv()).
 *
 * @return string '' when Distribution owns the public sitemap, otherwise the owner id.
 */
function tcdist_sitemap_external_owner()
{
    if (defined('TCDIST_LEGACY_SITEMAP_ACTIVE') && TCDIST_LEGACY_SITEMAP_ACTIVE) {
        return '';
    }

    $owner = sanitize_key((string) apply_filters('taxocommerce_sitemap_owner', 'taxocommerce'));

    return ($owner === '' || $owner === 'taxocommerce') ? '' : $owner;
}

/**
 * Canonical public URL for a sitemap.
 *
 * @param string $filename
 * @return string
 */
function tcdist_sitemap_sitemap_public_url($filename)
{
    $filename = basename((string) $filename);

    if (!tcdist_sitemap_sitemap_filename_is_allowed($filename)) {
        return '';
    }

    return home_url('/' . $filename);
}

/**
 * Registers public sitemap URLs.
 */
function tcdist_sitemap_register_sitemap_rewrite_rules()
{
    if (tcdist_sitemap_external_owner() !== '') {
        return;
    }

    // Main index, historical aliases, and generated child sitemaps.
    add_rewrite_rule(
        '^(wp-sitemap\.xml|sitemap(?:[_-][a-z0-9-]+)?\.xml)$',
        'index.php?tcdist_sitemap_sitemap=$matches[1]',
        'top'
    );
}
if ( ! defined( 'TCDIST_LEGACY_SITEMAP_ACTIVE' ) || ! TCDIST_LEGACY_SITEMAP_ACTIVE ) { add_action('init', 'tcdist_sitemap_register_sitemap_rewrite_rules', 1); }

/**
 * Disables the native WordPress sitemap. This module is the only public
 * sitemap source and keeps /wp-sitemap.xml as a compatible alias.
 *
 * @param bool $enabled
 * @return bool
 */
function tcdist_sitemap_disable_wordpress_core_sitemaps($enabled = true)
{
    if (tcdist_sitemap_external_owner() !== '') {
        return $enabled; // The sitemap owner decides.
    }

    return false;
}
if ( ! defined( 'TCDIST_LEGACY_SITEMAP_ACTIVE' ) || ! TCDIST_LEGACY_SITEMAP_ACTIVE ) { add_filter('wp_sitemaps_enabled', 'tcdist_sitemap_disable_wordpress_core_sitemaps', 100); }

/**
 * @param string[] $query_vars
 * @return string[]
 */
function tcdist_sitemap_register_sitemap_query_var($query_vars)
{
    $query_vars[] = 'tcdist_sitemap_sitemap';
    return array_values(array_unique($query_vars));
}
if ( ! defined( 'TCDIST_LEGACY_SITEMAP_ACTIVE' ) || ! TCDIST_LEGACY_SITEMAP_ACTIVE ) { add_filter('query_vars', 'tcdist_sitemap_register_sitemap_query_var'); }

/**
 * Updates rewrite rules once when the endpoint version changes.
 */
function tcdist_sitemap_maybe_flush_sitemap_rewrite_rules()
{
    $stored_version = (string) get_option('tcdist_sitemap_sitemap_rewrite_version', '');

    if ($stored_version === (string) TCDIST_SITEMAP_REWRITE_VERSION) {
        return;
    }

    tcdist_sitemap_register_sitemap_rewrite_rules();
    flush_rewrite_rules(false);
    update_option(
        'tcdist_sitemap_sitemap_rewrite_version',
        (string) TCDIST_SITEMAP_REWRITE_VERSION,
        false
    );
}
if ( ! defined( 'TCDIST_LEGACY_SITEMAP_ACTIVE' ) || ! TCDIST_LEGACY_SITEMAP_ACTIVE ) { add_action('admin_init', 'tcdist_sitemap_maybe_flush_sitemap_rewrite_rules', 20); }

/**
 * Gets the requested name even when rewrite rules have not yet
 * refrescado. Esto evita que /sitemap.xml dependa de una visita previa al admin.
 *
 * @return string
 */
function tcdist_sitemap_get_requested_sitemap_filename()
{
    $filename = (string) get_query_var('tcdist_sitemap_sitemap');

    if ($filename === '' && isset($_SERVER['REQUEST_URI'])) {
        $request_uri  = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']));
        $request_path = (string) wp_parse_url($request_uri, PHP_URL_PATH);
        $home_path    = (string) wp_parse_url(home_url('/'), PHP_URL_PATH);
        $request_path = '/' . ltrim($request_path, '/');
        $home_path    = '/' . trim($home_path, '/');

        if ($home_path !== '/' && strpos($request_path, $home_path . '/') === 0) {
            $request_path = substr($request_path, strlen($home_path));
        }

        $candidate = strtolower(basename(trim($request_path, '/')));

        if (
            'wp-sitemap.xml' === $candidate
            || in_array($candidate, array('sitemap-index.xml', 'sitemap_index.xml'), true)
        ) {
            $filename = 'sitemap.xml';
        } elseif (tcdist_sitemap_sitemap_filename_is_allowed($candidate)) {
            $filename = $candidate;
        }
    }

    $filename = strtolower(basename($filename));

    if (
        'wp-sitemap.xml' === $filename
        || in_array($filename, array('sitemap-index.xml', 'sitemap_index.xml'), true)
    ) {
        return 'sitemap.xml';
    }

    return tcdist_sitemap_sitemap_filename_is_allowed($filename) ? $filename : '';
}

/**
 * Sirve el XML con cabeceras correctas y sin salida del tema o de otros hooks.
 */
function tcdist_sitemap_maybe_serve_sitemap()
{
    if (tcdist_sitemap_external_owner() !== '') {
        return;
    }

    $filename = tcdist_sitemap_get_requested_sitemap_filename();

    if ($filename === '') {
        return;
    }

    $storage = tcdist_sitemap_sitemap_storage();
    if (is_wp_error($storage)) {
        status_header(503);
        exit;
    }

    $file_path = $storage['dir'] . $filename;

    if (!is_file($file_path) || !is_readable($file_path)) {
        status_header(404);
        nocache_headers();
        exit;
    }

    $mtime = (int) filemtime($file_path);
    $size  = (int) filesize($file_path);
    $etag  = '"' . md5($filename . '|' . $mtime . '|' . $size) . '"';

    $if_none_match = isset($_SERVER['HTTP_IF_NONE_MATCH'])
        ? sanitize_text_field(wp_unslash($_SERVER['HTTP_IF_NONE_MATCH']))
        : '';
    $if_modified_since = isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])
        ? strtotime(sanitize_text_field(wp_unslash($_SERVER['HTTP_IF_MODIFIED_SINCE'])))
        : false;

    if ($if_none_match === $etag || ($if_modified_since && $if_modified_since >= $mtime)) {
        status_header(304);
        header('ETag: ' . $etag);
        exit;
    }

    while (ob_get_level() > 0) {
        @ob_end_clean();
    }

    status_header(200);
    header('Content-Type: application/xml; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Robots-Tag: noindex, follow');
    header('Cache-Control: public, max-age=300, stale-while-revalidate=86400');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
    header('ETag: ' . $etag);

    readfile($file_path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
    exit;
}
if ( ! defined( 'TCDIST_LEGACY_SITEMAP_ACTIVE' ) || ! TCDIST_LEGACY_SITEMAP_ACTIVE ) { add_action('template_redirect', 'tcdist_sitemap_maybe_serve_sitemap', 0); }

/**
 * Declares the main index in robots.txt without duplication.
 *
 * @param string $output
 * @param bool   $public
 * @return string
 */
function tcdist_sitemap_add_sitemap_to_robots($output, $public)
{
    if (!$public || tcdist_sitemap_external_owner() !== '') {
        return $output;
    }

    $canonical_url = tcdist_sitemap_sitemap_public_url('sitemap.xml');
    $lines         = preg_split('/\r\n|\r|\n/', (string) $output);
    $clean_lines   = array();

    foreach ((array) $lines as $existing_line) {
        $trimmed = trim((string) $existing_line);

        if (preg_match('/^Sitemap:\s*(.+)$/i', $trimmed, $matches)) {
            $sitemap_path = (string) wp_parse_url(trim($matches[1]), PHP_URL_PATH);
            $basename     = strtolower(basename($sitemap_path));

            // Remove only our aliases and the native sitemap so robots.txt does not
            // publish two different indexes.
            if (in_array(
                $basename,
                array('wp-sitemap.xml', 'sitemap.xml', 'sitemap-index.xml', 'sitemap_index.xml'),
                true
            )) {
                continue;
            }
        }

        $clean_lines[] = $existing_line;
    }

    $output = rtrim(implode("\n", $clean_lines));
    $output .= ($output !== '' ? "\n\n" : '') . 'Sitemap: ' . $canonical_url . "\n";

    return $output;
}
if ( ! defined( 'TCDIST_LEGACY_SITEMAP_ACTIVE' ) || ! TCDIST_LEGACY_SITEMAP_ACTIVE ) { add_filter('robots_txt', 'tcdist_sitemap_add_sitemap_to_robots', 20, 2); }

/**
 * Elimina caracteres no permitidos por XML 1.0 y escapa entidades.
 *
 * @param mixed $value
 * @return string
 */
function tcdist_sitemap_xml_escape($value)
{
    $value = (string) $value;

    if (function_exists('wp_check_invalid_utf8')) {
        $value = wp_check_invalid_utf8($value, true);
    }

    $cleaned = preg_replace(
        '/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u',
        '',
        $value
    );

    if (is_string($cleaned)) {
        $value = $cleaned;
    }

    return htmlspecialchars(
        $value,
        ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/**
 * Normalizes a site URL and rejects external hosts or invalid schemes.
 *
 * @param string $url
 * @return string
 */
function tcdist_sitemap_sitemap_normalize_url($url)
{
    $url = esc_url_raw((string) $url, array('http', 'https'));

    if ($url === '') {
        return '';
    }

    $parts      = wp_parse_url($url);
    $home_parts = wp_parse_url(home_url('/'));

    if (
        !is_array($parts)
        || empty($parts['scheme'])
        || empty($parts['host'])
        || !is_array($home_parts)
        || empty($home_parts['host'])
        || strtolower($parts['host']) !== strtolower($home_parts['host'])
    ) {
        return '';
    }

    return preg_replace('/#.*$/', '', $url);
}

/**
 * Normaliza una fecha al formato W3C empleado por sitemaps.
 *
 * @param mixed $value
 * @return string
 */
function tcdist_sitemap_sitemap_normalize_lastmod($value)
{
    if ($value === '' || $value === null) {
        return '';
    }

    $timestamp = is_numeric($value) ? (int) $value : strtotime((string) $value);

    if ($timestamp <= 0) {
        return '';
    }

    return gmdate('c', $timestamp);
}

/**
 * Limpia, deduplica y ordena entradas antes de construir el XML.
 *
 * @param array $urls
 * @return array
 */
function tcdist_sitemap_prepare_sitemap_urls($urls)
{
    $prepared = array();
    $seen     = array();

    foreach ((array) $urls as $url) {
        if (!is_array($url) || empty($url['loc'])) {
            continue;
        }

        $loc = tcdist_sitemap_sitemap_normalize_url($url['loc']);
        if ($loc === '' || isset($seen[$loc])) {
            continue;
        }

        $entry = array(
            'loc'     => $loc,
            'lastmod' => tcdist_sitemap_sitemap_normalize_lastmod(
                isset($url['lastmod']) ? $url['lastmod'] : ''
            ),
            'images'  => array(),
        );

        $image_seen = array();
        foreach ((array) (isset($url['images']) ? $url['images'] : array()) as $image_url) {
            $image_url = esc_url_raw((string) $image_url, array('http', 'https'));
            if ($image_url === '' || isset($image_seen[$image_url])) {
                continue;
            }

            $image_parts = wp_parse_url($image_url);
            if (!is_array($image_parts) || empty($image_parts['host'])) {
                continue;
            }

            $image_seen[$image_url] = true;
            $entry['images'][]      = $image_url;

            if (count($entry['images']) >= 5) {
                break;
            }
        }

        $seen[$loc]   = true;
        $prepared[]   = $entry;
    }

    usort(
        $prepared,
        static function ($left, $right) {
            return strcmp($left['loc'], $right['loc']);
        }
    );

    return $prepared;
}

/**
 * @param array $urls
 * @return string
 */
function tcdist_sitemap_build_urlset_xml($urls)
{
    $urls       = tcdist_sitemap_prepare_sitemap_urls($urls);
    $has_images = false;

    foreach ($urls as $url) {
        if (!empty($url['images'])) {
            $has_images = true;
            break;
        }
    }

    $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
    if ($has_images) {
        $xml .= ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"';
    }
    $xml .= ">\n";

    foreach ($urls as $url) {
        $xml .= "  <url>\n";
        $xml .= '    <loc>' . tcdist_sitemap_xml_escape($url['loc']) . "</loc>\n";

        if ($url['lastmod'] !== '') {
            $xml .= '    <lastmod>' . tcdist_sitemap_xml_escape($url['lastmod']) . "</lastmod>\n";
        }

        foreach ($url['images'] as $image_url) {
            $xml .= "    <image:image>\n";
            $xml .= '      <image:loc>' . tcdist_sitemap_xml_escape($image_url) . "</image:loc>\n";
            $xml .= "    </image:image>\n";
        }

        $xml .= "  </url>\n";
    }

    $xml .= "</urlset>\n";
    return $xml;
}

/**
 * @param array $files
 * @return string
 */
function tcdist_sitemap_build_sitemap_index_xml($files)
{
    $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    foreach ((array) $files as $file) {
        if (empty($file['filename'])) {
            continue;
        }

        $loc = tcdist_sitemap_sitemap_public_url($file['filename']);
        if ($loc === '') {
            continue;
        }

        $xml .= "  <sitemap>\n";
        $xml .= '    <loc>' . tcdist_sitemap_xml_escape($loc) . "</loc>\n";

        $lastmod = tcdist_sitemap_sitemap_normalize_lastmod(
            isset($file['lastmod']) ? $file['lastmod'] : ''
        );
        if ($lastmod !== '') {
            $xml .= '    <lastmod>' . tcdist_sitemap_xml_escape($lastmod) . "</lastmod>\n";
        }

        $xml .= "  </sitemap>\n";
    }

    $xml .= "</sitemapindex>\n";
    return $xml;
}

/**
 * Validates XML, root element, and namespace. Also counts published entries.
 *
 * @param string $xml
 * @param string $expected_root
 * @return array{valid:bool,root:string,count:int,error:string}
 */
function tcdist_sitemap_validate_xml_string($xml, $expected_root = '')
{
    $result = array(
        'valid' => false,
        'root'  => '',
        'count' => 0,
        'error' => '',
    );

    if (!is_string($xml) || trim($xml) === '') {
        $result['error'] = 'The file is empty.';
        return $result;
    }

    if (class_exists('DOMDocument')) {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $dom   = new DOMDocument('1.0', 'UTF-8');
        $flags = 0;
        if (defined('LIBXML_NONET')) {
            $flags |= LIBXML_NONET;
        }
        if (defined('LIBXML_NOBLANKS')) {
            $flags |= LIBXML_NOBLANKS;
        }

        $loaded = $dom->loadXML($xml, $flags);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded || !$dom->documentElement) {
            $first_error = !empty($errors) ? trim($errors[0]->message) : 'XML no analizable.';
            $result['error'] = $first_error;
            return $result;
        }

        $root      = $dom->documentElement->localName;
        $namespace = $dom->documentElement->namespaceURI;

        if ($expected_root !== '' && $root !== $expected_root) {
            $result['error'] = 'Unexpected root element: ' . $root . '.';
            return $result;
        }

        if ($namespace !== 'http://www.sitemaps.org/schemas/sitemap/0.9') {
            $result['error'] = 'Namespace de sitemap ausente o incorrecto.';
            return $result;
        }

        $result['valid'] = true;
        $result['root']  = $root;
        $result['count'] = $root === 'sitemapindex'
            ? $dom->getElementsByTagNameNS($namespace, 'sitemap')->length
            : $dom->getElementsByTagNameNS($namespace, 'url')->length;

        return $result;
    }

    $root = preg_match('/<sitemapindex\b/i', $xml) ? 'sitemapindex' : (
        preg_match('/<urlset\b/i', $xml) ? 'urlset' : ''
    );

    if ($root === '' || ($expected_root !== '' && $root !== $expected_root)) {
        $result['error'] = 'The XML root element could not be confirmed.';
        return $result;
    }

    $closing = $root === 'sitemapindex' ? '</sitemapindex>' : '</urlset>';
    if (stripos($xml, $closing) === false) {
        $result['error'] = 'Falta la etiqueta de cierre principal.';
        return $result;
    }

    $result['valid'] = true;
    $result['root']  = $root;
    $result['count'] = $root === 'sitemapindex'
        ? preg_match_all('/<sitemap>/i', $xml)
        : preg_match_all('/<url>/i', $xml);

    return $result;
}

/**
 * @param string $file_path
 * @param string $expected_root
 * @return array{valid:bool,root:string,count:int,error:string}
 */
function tcdist_sitemap_validate_xml_file($file_path, $expected_root = '')
{
    if (!is_file($file_path) || !is_readable($file_path)) {
        return array(
            'valid' => false,
            'root'  => '',
            'count' => 0,
            'error' => 'El archivo no existe o no es legible.',
        );
    }

    $xml = file_get_contents($file_path);
    return tcdist_sitemap_validate_xml_string($xml, $expected_root);
}

/**
 * Writes and validates an XML file inside the temporary build directory.
 *
 * @param string $file_path
 * @param string $xml
 * @param string $expected_root
 * @return array|WP_Error
 */
/**
 * Preserves explicit permissions used by local sitemap artifacts.
 *
 * This remains a direct local operation because WP_Filesystem may use a
 * remote transport and change the semantics of the atomic publishing flow.
 *
 * @param string $file_path Ruta local.
 * @param int    $mode      Permisos POSIX.
 * @return bool
 */
function tcdist_sitemap_set_file_permissions($file_path, $mode)
{
    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Local sitemap build artifacts require exact chmod semantics.
    return @chmod($file_path, $mode);
}

/**
 * Atomically renames a local sitemap artifact.
 *
 * WP_Filesystem::move() may degrade to copy+delete depending on transport;
 * local rename() is required here so partial XML files are never published.
 *
 * @param string $source Origen local.
 * @param string $target Destino local.
 * @return bool
 */
function tcdist_sitemap_atomic_rename($source, $target)
{
    // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Atomic local publication requires native rename semantics.
    return @rename($source, $target);
}

/**
 * Removes an already-empty local directory after cleaning its contents.
 *
 * @param string $dir Directorio local.
 * @return bool
 */
function tcdist_sitemap_remove_empty_directory($dir)
{
    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Directory is local, temporary and already recursively emptied.
    return @rmdir($dir);
}

/**
 * Abre el fichero de lock como recurso para flock().
 *
 * WP_Filesystem no expone un recurso compatible con flock().
 *
 * @param string $path Ruta del lock.
 * @param string $mode Modo de apertura.
 * @return resource|false
 */
function tcdist_sitemap_lock_stream_open($path, $mode)
{
    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- flock() requires a native PHP stream resource.
    return @fopen($path, $mode);
}

/**
 * Cierra el recurso de lock abierto para flock().
 *
 * @param resource $stream Recurso abierto.
 * @return bool
 */
function tcdist_sitemap_lock_stream_close($stream)
{
    if (!is_resource($stream)) {
        return false;
    }

    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Paired with tcdist_sitemap_lock_stream_open() for flock().
    return @fclose($stream);
}

function tcdist_sitemap_write_validated_xml($file_path, $xml, $expected_root)
{
    $validation = tcdist_sitemap_validate_xml_string($xml, $expected_root);
    if (!$validation['valid']) {
        return new WP_Error('tcdist_sitemap_invalid_xml', $validation['error']);
    }

    $bytes = file_put_contents($file_path, $xml, LOCK_EX);
    if ($bytes === false) {
        return new WP_Error('tcdist_sitemap_write_error', 'No se pudo escribir ' . basename($file_path) . '.');
    }

    tcdist_sitemap_set_file_permissions($file_path, 0644);

    $file_validation = tcdist_sitemap_validate_xml_file($file_path, $expected_root);
    if (!$file_validation['valid']) {
        wp_delete_file($file_path);
        return new WP_Error('tcdist_sitemap_invalid_written_xml', $file_validation['error']);
    }

    $file_validation['bytes'] = (int) $bytes;
    return $file_validation;
}

/**
 * Publishes a file atomically so partial XML files are never visible.
 *
 * @param string $source
 * @param string $target
 * @return bool
 */
function tcdist_sitemap_publish_sitemap_file($source, $target)
{
    $temporary = $target . '.tmp-' . wp_generate_uuid4();

    if (!copy($source, $temporary)) {
        return false;
    }

    tcdist_sitemap_set_file_permissions($temporary, 0644);

    if (tcdist_sitemap_atomic_rename($temporary, $target)) {
        return true;
    }

    if (is_file($target)) {
        wp_delete_file($target);
    }

    $renamed = tcdist_sitemap_atomic_rename($temporary, $target);
    if (!$renamed && is_file($temporary)) {
        wp_delete_file($temporary);
    }

    return $renamed;
}

/**
 * @param string $dir
 */
function tcdist_sitemap_remove_directory($dir)
{
    if (!is_dir($dir)) {
        return;
    }

    $items = scandir($dir);
    if (!is_array($items)) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            tcdist_sitemap_remove_directory($path);
        } else {
            wp_delete_file($path);
        }
    }

    tcdist_sitemap_remove_empty_directory($dir);
}

/**
 * @param string[] $roles
 * @return int[]
 */
function tcdist_sitemap_get_node_page_ids_by_roles($roles)
{
    global $wpdb;

    $roles = array_values(array_unique(array_filter(array_map('sanitize_key', (array) $roles))));
    if (empty($roles)) {
        return array();
    }

    /*
     * TaxoCommerce Core is the canonical source in the modular family.
     * Roles are stored directly on published WordPress pages, so Distribution
     * does not depend on a legacy SEO table in a clean installation.
     */
    $ids = array();
    foreach ($roles as $role) {
        $role_ids = get_posts(
            array(
                'post_type'              => 'page',
                'post_status'            => 'publish',
                'posts_per_page'         => -1,
                'fields'                 => 'ids',
                'orderby'                => 'ID',
                'order'                  => 'ASC',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'meta_key'               => '_taxocommerce_core_seo_role', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Core canonical SEO role metadata.
                'meta_value'             => $role, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Exact role lookup for published pages.
            )
        );
        $ids = array_merge($ids, array_map('absint', (array) $role_ids));
    }

    if (!empty($ids)) {
        return array_values(array_unique(array_filter($ids)));
    }

    /*
     * Temporary coexistence fallback for installations where the old
     * TaxoCommerce SEO plugin still owns seo_nodes. No legacy table is
     * required once Core metadata is present.
     */
    $nodes_table = $wpdb->prefix . 'seo_nodes';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only check for optional legacy table.
    $table_exists = $wpdb->get_var(
        $wpdb->prepare('SHOW TABLES LIKE %s', $nodes_table)
    );

    if ($table_exists !== $nodes_table) {
        return array();
    }

    foreach ($roles as $role) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Legacy coexistence requires fresh role data.
        $legacy_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT object_id
                 FROM %i
                 WHERE object_type = 'page'
                   AND seo_role = %s
                   AND status = 1
                   AND object_id > 0
                 ORDER BY object_id ASC",
                $nodes_table,
                $role
            )
        );
        $ids = array_merge($ids, array_map('absint', (array) $legacy_ids));
    }

    return array_values(array_unique(array_filter($ids)));
}

/**
 * @param string $post_type
 * @return int[]
 */
function tcdist_sitemap_get_published_post_ids($post_type)
{
    global $wpdb;

    $post_type = sanitize_key($post_type);
    if ($post_type === '') {
        return array();
    }

    return array_map(
        'absint',
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Current published sitemap inventory.
        (array) $wpdb->get_col(
            $wpdb->prepare(
                "SELECT ID
                 FROM {$wpdb->posts}
                 WHERE post_type = %s
                   AND post_status = 'publish'
                   AND post_password = ''
                 ORDER BY ID ASC",
                $post_type
            )
        )
    );
}

/**
 * System pages that must not be submitted to the search index.
 *
 * @return int[]
 */
function tcdist_sitemap_get_excluded_page_ids_for_sitemap()
{
    $ids = array();

    if (function_exists('wc_get_page_id')) {
        foreach (array('cart', 'checkout', 'myaccount') as $page_key) {
            $page_id = (int) wc_get_page_id($page_key);
            if ($page_id > 0) {
                $ids[] = $page_id;
            }
        }
    }

    $ids = array_merge(
        $ids,
        tcdist_sitemap_get_node_page_ids_by_roles(
            array('cluster', 'hub_primary', 'hub_secondary')
        )
    );

    return array_values(array_unique(array_filter(array_map('absint', $ids))));
}

/**
 * Converts posts into sitemap entries. Products may include their featured
 * image through the official image extension.
 *
 * @param array $ids
 * @param bool  $include_images
 * @return array
 */
function tcdist_sitemap_posts_to_sitemap_urls($ids, $include_images = false)
{
    $ids  = array_values(array_unique(array_filter(array_map('absint', (array) $ids))));
    $urls = array();

    foreach (array_chunk($ids, 500) as $id_chunk) {
        $posts = get_posts(
            array(
                'post_type'              => 'any',
                'post_status'            => 'publish',
                'post__in'               => $id_chunk,
                'posts_per_page'         => count($id_chunk),
                'orderby'                => 'post__in',
                'no_found_rows'          => true,
                'ignore_sticky_posts'    => true,
                'update_post_term_cache' => false,
                'update_post_meta_cache' => (bool) $include_images,
                'suppress_filters'       => false,
            )
        );

        foreach ((array) $posts as $post) {
            if (
                !$post
                || $post->post_status !== 'publish'
                || $post->post_password !== ''
            ) {
                continue;
            }

            $include = apply_filters('tcdist_sitemap_sitemap_include_post', true, $post);
            if (!$include) {
                continue;
            }

            $url = get_permalink($post->ID);
            $url = apply_filters('tcdist_sitemap_sitemap_post_url', $url, $post);
            $url = tcdist_sitemap_sitemap_normalize_url($url);

            if ($url === '') {
                continue;
            }

            $entry = array(
                'loc'     => $url,
                'lastmod' => get_post_modified_time('c', true, $post),
                'images'  => array(),
            );

            if ($include_images) {
                $thumbnail_id = get_post_thumbnail_id($post->ID);
                if ($thumbnail_id) {
                    $image_url = wp_get_attachment_image_url($thumbnail_id, 'full');
                    if ($image_url) {
                        $entry['images'][] = $image_url;
                    }
                }
            }

            $entry['images'] = apply_filters(
                'tcdist_sitemap_sitemap_post_images',
                $entry['images'],
                $post
            );

            $urls[] = $entry;
        }
    }

    return tcdist_sitemap_prepare_sitemap_urls($urls);
}

/**
 * Calculates the most recent modification time of products directly associated
 * with each category in a single query.
 *
 * @return array<int,string>
 */
function tcdist_sitemap_get_product_category_lastmods()
{
    global $wpdb;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh category modification timestamps for XML sitemap.
    $rows = $wpdb->get_results(
        "SELECT tt.term_id, MAX(p.post_modified_gmt) AS lastmod
         FROM {$wpdb->term_taxonomy} tt
         INNER JOIN {$wpdb->term_relationships} tr
            ON tr.term_taxonomy_id = tt.term_taxonomy_id
         INNER JOIN {$wpdb->posts} p
            ON p.ID = tr.object_id
         WHERE tt.taxonomy = 'product_cat'
           AND p.post_type = 'product'
           AND p.post_status = 'publish'
           AND p.post_password = ''
         GROUP BY tt.term_id"
    );

    $lastmods = array();
    foreach ((array) $rows as $row) {
        $term_id  = absint($row->term_id);
        $timestamp = !empty($row->lastmod)
            ? strtotime($row->lastmod . ' UTC')
            : false;

        if ($term_id > 0 && $timestamp) {
            $lastmods[$term_id] = gmdate('c', $timestamp);
        }
    }

    return $lastmods;
}

/**
 * @return array
 */
function tcdist_sitemap_get_product_category_sitemap_urls()
{
    $terms = get_terms(
        array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => true,
            'orderby'    => 'term_id',
            'order'      => 'ASC',
        )
    );

    if (is_wp_error($terms)) {
        return array();
    }

    $lastmods = tcdist_sitemap_get_product_category_lastmods();
    $urls     = array();

    foreach ((array) $terms as $term) {
        if (!$term || empty($term->term_id)) {
            continue;
        }

        $include = apply_filters('tcdist_sitemap_sitemap_include_term', true, $term);
        if (!$include) {
            continue;
        }

        $link = get_term_link($term);
        if (is_wp_error($link)) {
            continue;
        }

        $urls[] = array(
            'loc'     => $link,
            'lastmod' => isset($lastmods[$term->term_id]) ? $lastmods[$term->term_id] : '',
            'images'  => array(),
        );
    }

    return tcdist_sitemap_prepare_sitemap_urls($urls);
}

/**
 * When OPNV owns the public sitemap, TaxoCommerce still decides which product
 * categories are worth indexing: it hands OPNV the same list it would publish
 * (same `tcdist_sitemap_sitemap_include_term` rules and lastmod per category).
 * OPNV prints them as /opnv-sitemap-product-categories.xml.
 *
 * @param mixed $groups Map of group slug => callable returning list<array{loc:string, lastmod?:string}>.
 * @return array
 */
function tcdist_sitemap_offer_groups_to_opnv($groups)
{
    $groups = is_array($groups) ? $groups : array();
    if (tcdist_sitemap_external_owner() !== 'opnv') {
        return $groups;
    }
    $groups['product-categories'] = 'tcdist_sitemap_get_product_category_sitemap_urls';

    return $groups;
}
if ( ! defined( 'TCDIST_LEGACY_SITEMAP_ACTIVE' ) || ! TCDIST_LEGACY_SITEMAP_ACTIVE ) { add_filter('opnv_sitemap_groups', 'tcdist_sitemap_offer_groups_to_opnv'); }

/**
 * @param array $ids
 * @return int[]
 */
function tcdist_sitemap_filter_generic_page_ids_for_sitemap($ids)
{
    $excluded_ids   = array_fill_keys(tcdist_sitemap_get_excluded_page_ids_for_sitemap(), true);
    $excluded_slugs = array(
        'carrito', 'finalizar-compra', 'mi-cuenta', 'checkout', 'cart',
        'my-account',
    );
    $filtered = array();

    foreach ((array) $ids as $id) {
        $id = absint($id);
        if ($id <= 0 || isset($excluded_ids[$id])) {
            continue;
        }

        $post = get_post($id);
        if (!$post || in_array($post->post_name, $excluded_slugs, true)) {
            continue;
        }

        $filtered[] = $id;
    }

    return $filtered;
}

/**
 * Creates one or more files for a group while respecting the batch size.
 *
 * @param string $build_dir
 * @param string $group_slug
 * @param array  $urls
 * @param array  $files
 * @param array  $errors
 */
function tcdist_sitemap_create_sitemap_group($build_dir, $group_slug, $urls, &$files, &$errors)
{
    $urls = tcdist_sitemap_prepare_sitemap_urls($urls);
    if (empty($urls)) {
        return;
    }

    $batch_size = (int) apply_filters(
        'tcdist_sitemap_sitemap_batch_size',
        TCDIST_SITEMAP_BATCH_SIZE,
        $group_slug
    );
    $batch_size = max(100, min(50000, $batch_size));
    $chunks     = array_chunk($urls, $batch_size);
    $generated  = gmdate('c');

    foreach ($chunks as $index => $chunk) {
        $filename = 'sitemap-' . sanitize_title($group_slug) . '-' . ($index + 1) . '.xml';
        $xml      = tcdist_sitemap_build_urlset_xml($chunk);
        $written  = tcdist_sitemap_write_validated_xml(
            $build_dir . $filename,
            $xml,
            'urlset'
        );

        if (is_wp_error($written)) {
            $errors[] = $filename . ': ' . $written->get_error_message();
            continue;
        }

        $files[] = array(
            'filename'  => $filename,
            'url_count' => (int) $written['count'],
            'bytes'     => (int) $written['bytes'],
            'lastmod'   => $generated,
            'group'     => $group_slug,
        );
    }
}

/**
 * Writes an administrative manifest without exposing sensitive data.
 *
 * @param string $dir
 * @param array  $manifest
 * @return bool
 */
function tcdist_sitemap_write_sitemap_manifest($dir, $manifest)
{
    $json = wp_json_encode(
        $manifest,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
    );

    if (!is_string($json)) {
        return false;
    }

    $target    = $dir . 'manifest.json';
    $temporary = $target . '.tmp-' . wp_generate_uuid4();

    if (file_put_contents($temporary, $json, LOCK_EX) === false) {
        return false;
    }

    tcdist_sitemap_set_file_permissions($temporary, 0644);

    if (tcdist_sitemap_atomic_rename($temporary, $target)) {
        return true;
    }

    wp_delete_file($target);
    return tcdist_sitemap_atomic_rename($temporary, $target);
}

/**
 * Reads the latest generation manifest.
 *
 * @return array
 */
function tcdist_sitemap_get_sitemap_manifest()
{
    $storage = tcdist_sitemap_sitemap_storage();
    if (is_wp_error($storage)) {
        return array();
    }

    $file = $storage['dir'] . 'manifest.json';
    if (!is_file($file) || !is_readable($file)) {
        return array();
    }

    $decoded = json_decode((string) file_get_contents($file), true);
    return is_array($decoded) ? $decoded : array();
}

/**
 * Genera todos los sitemaps en staging, los valida y solo entonces sustituye
 * the public version. Existing valid XML files are never deleted first.
 *
 * @return array
 */
function tcdist_sitemap_create_sitemaps()
{
    // The compatibility entry point is now a non-blocking queue operation.
    // Each worker request processes at most 250 catalog candidates, then
    // publishes validated XML children before swapping sitemap.xml last.
    return tcdist_sitemap_start_background_build();
}

/**
 * Local report of validity, size, and entry count.
 *
 * @return array
 */
function tcdist_sitemap_get_sitemap_report()
{
    $storage = tcdist_sitemap_sitemap_storage();
    if (is_wp_error($storage) || !is_dir($storage['dir'])) {
        return array(
            'main_valid' => false,
            'total_urls' => 0,
            'files'      => array(),
            'manifest'   => array(),
        );
    }

    $files = array();
    // While publishing a new run, hide new child files from this report until
    // the replacement index and manifest are committed. They are not yet public
    // entries of the current sitemap.xml.
    $manifest = tcdist_sitemap_get_sitemap_manifest();
    $published_names = array();
    $job = function_exists( 'tcdist_sitemap_job_state' ) ? tcdist_sitemap_job_state() : array();
    if ( 'running' === ( $job['status'] ?? '' ) && ! empty( $manifest['files'] ) ) {
        foreach ( (array) $manifest['files'] as $entry ) {
            if ( ! empty( $entry['filename'] ) ) {
                $published_names[ basename( (string) $entry['filename'] ) ] = true;
            }
        }
    }
    foreach ((array) glob($storage['dir'] . '*.xml') as $file_path) {
        $filename = basename($file_path);
        if ( $published_names && 'sitemap.xml' !== $filename && ! isset( $published_names[ $filename ] ) ) {
            continue;
        }

        if (in_array($filename, array('sitemap-index.xml', 'sitemap_index.xml'), true)) {
            continue;
        }

        $expected_root = $filename === 'sitemap.xml' ? 'sitemapindex' : 'urlset';
        $validation    = tcdist_sitemap_validate_xml_file($file_path, $expected_root);

        $files[] = array(
            'filename' => $filename,
            'url'      => tcdist_sitemap_sitemap_public_url($filename),
            'valid'    => (bool) $validation['valid'],
            'count'    => (int) $validation['count'],
            'root'     => $validation['root'],
            'error'    => $validation['error'],
            'bytes'    => (int) filesize($file_path),
            'modified' => (int) filemtime($file_path),
        );
    }

    usort(
        $files,
        static function ($left, $right) {
            if ($left['filename'] === 'sitemap.xml') {
                return -1;
            }
            if ($right['filename'] === 'sitemap.xml') {
                return 1;
            }
            return strcmp($left['filename'], $right['filename']);
        }
    );

    $total_urls = 0;
    $main_valid = false;
    foreach ($files as $file) {
        if ($file['filename'] === 'sitemap.xml') {
            $main_valid = $file['valid'];
        } else {
            $total_urls += $file['count'];
        }
    }

    return array(
        'main_valid' => $main_valid,
        'total_urls' => $total_urls,
        'files'      => $files,
        'manifest'   => $manifest,
    );
}

/**
 * Compatibilidad con la interfaz previa.
 *
 * @return string[]
 */
function tcdist_sitemap_get_existing_sitemaps()
{
    $report = tcdist_sitemap_get_sitemap_report();
    $urls   = array();

    foreach ($report['files'] as $file) {
        if (!empty($file['url'])) {
            $urls[] = $file['url'];
        }
    }

    return $urls;
}

/**
 * Schedules a single delayed regeneration after content changes.
 */
function tcdist_sitemap_schedule_sitemap_regeneration($post_id = 0)
{
    $post_id = absint($post_id);

    if (
        (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        || ($post_id > 0 && wp_is_post_revision($post_id))
    ) {
        return;
    }

    if (!wp_next_scheduled('tcdist_sitemap_regenerate_sitemaps_event')) {
        wp_schedule_single_event(
            time() + TCDIST_SITEMAP_REGEN_DELAY,
            'tcdist_sitemap_regenerate_sitemaps_event'
        );
    }
}
if ( ! defined( 'TCDIST_LEGACY_SITEMAP_ACTIVE' ) || ! TCDIST_LEGACY_SITEMAP_ACTIVE ) { add_action('save_post_product', 'tcdist_sitemap_schedule_sitemap_regeneration', 20, 1); }
if ( ! defined( 'TCDIST_LEGACY_SITEMAP_ACTIVE' ) || ! TCDIST_LEGACY_SITEMAP_ACTIVE ) { add_action('save_post_page', 'tcdist_sitemap_schedule_sitemap_regeneration', 20, 1); }
if ( ! defined( 'TCDIST_LEGACY_SITEMAP_ACTIVE' ) || ! TCDIST_LEGACY_SITEMAP_ACTIVE ) { add_action('save_post_post', 'tcdist_sitemap_schedule_sitemap_regeneration', 20, 1); }

/**
 * Term hooks provide a term_id, not a post_id.
 */
function tcdist_sitemap_schedule_sitemap_regeneration_for_term()
{
    tcdist_sitemap_schedule_sitemap_regeneration(0);
}
if ( ! defined( 'TCDIST_LEGACY_SITEMAP_ACTIVE' ) || ! TCDIST_LEGACY_SITEMAP_ACTIVE ) { add_action('created_product_cat', 'tcdist_sitemap_schedule_sitemap_regeneration_for_term', 20, 0); }
if ( ! defined( 'TCDIST_LEGACY_SITEMAP_ACTIVE' ) || ! TCDIST_LEGACY_SITEMAP_ACTIVE ) { add_action('edited_product_cat', 'tcdist_sitemap_schedule_sitemap_regeneration_for_term', 20, 0); }
if ( ! defined( 'TCDIST_LEGACY_SITEMAP_ACTIVE' ) || ! TCDIST_LEGACY_SITEMAP_ACTIVE ) { add_action('delete_product_cat', 'tcdist_sitemap_schedule_sitemap_regeneration_for_term', 20, 0); }

/**
 * Silent regeneration for WP-Cron.
 */
function tcdist_sitemap_regenerate_sitemaps_silent()
{
    $result = tcdist_sitemap_create_sitemaps();

    if (!is_array($result) || ($result['type'] ?? '') !== 'success') {
        $message = is_array($result) && isset($result['message'])
            ? wp_strip_all_tags($result['message'])
            : 'Resultado desconocido.';
        if (function_exists('seo_system_private_log')) {
            seo_system_private_log('error', '[SEO Marketing] Automatic sitemap regeneration: ' . $message);
        }
    }
}
if ( ! defined( 'TCDIST_LEGACY_SITEMAP_ACTIVE' ) || ! TCDIST_LEGACY_SITEMAP_ACTIVE ) { add_action('tcdist_sitemap_regenerate_sitemaps_event', 'tcdist_sitemap_regenerate_sitemaps_silent'); }


/**
 * Ensures an initial generation without blocking public requests.
 */
function tcdist_sitemap_ensure_initial_sitemap_generation()
{
    $storage = tcdist_sitemap_sitemap_storage();
    if (is_wp_error($storage) || is_file($storage['dir'] . 'sitemap.xml')) {
        return;
    }

    if (!wp_next_scheduled('tcdist_sitemap_regenerate_sitemaps_event')) {
        wp_schedule_single_event(
            time() + 60,
            'tcdist_sitemap_regenerate_sitemaps_event'
        );
    }
}
if ( ! defined( 'TCDIST_LEGACY_SITEMAP_ACTIVE' ) || ! TCDIST_LEGACY_SITEMAP_ACTIVE ) { add_action('init', 'tcdist_sitemap_ensure_initial_sitemap_generation', 30); }

