<?php
/**
 * Informe competitivo profundo de Analista.
 *
 * Separa:
 * - datos privados del dominio propio (GSC/GA4/Bing);
 * - evidencia publica observable de competidores;
 * - muestreo SERP via proveedor Google compartido SerpApi -> ScraperAPI;
 * - senales tematicas de Google Trends.
 *
 * No interpreta ausencia de datos como cero y no atribuye a competidores
 * metricas privadas que solo pertenecen al sitio conectado.
 */

defined('ABSPATH') || exit;

if (!defined('SEO_ANALISTA_COMPETITIVE_REPORT_OPTION')) {
    define('SEO_ANALISTA_COMPETITIVE_REPORT_OPTION', 'seo_analista_competitive_report_v1');
}
if (!defined('SEO_ANALISTA_COMPETITIVE_REPORT_HISTORY_OPTION')) {
    define('SEO_ANALISTA_COMPETITIVE_REPORT_HISTORY_OPTION', 'seo_analista_competitive_report_history_v1');
}

if (!function_exists('seo_analista_competitive_host')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_competitive_host($value) {
        $host = wp_parse_url((string) $value, PHP_URL_HOST);
        if (!$host) {
            $host = (string) $value;
        }
        $host = strtolower(trim((string) $host));
        $host = preg_replace('/^www\./', '', $host);
        $host = preg_replace('/:\d+$/', '', $host);
        return preg_replace('/[^a-z0-9.-]/', '', $host);
    }
}

if (!function_exists('seo_analista_competitive_domain_matches')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_competitive_domain_matches($candidate, $domain) {
        $candidate = seo_analista_competitive_host($candidate);
        $domain = seo_analista_competitive_host($domain);
        if ($candidate === '' || $domain === '') return false;
        return $candidate === $domain || substr($candidate, -strlen('.' . $domain)) === '.' . $domain;
    }
}

if (!function_exists('seo_analista_competitive_organic_rows')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_competitive_organic_rows($payload) {
        $payload = is_array($payload) ? $payload : array();
        foreach (array('organic_results','organic','results','organicResults') as $key) {
            if (!empty($payload[$key]) && is_array($payload[$key])) {
                return array_values(array_filter($payload[$key], 'is_array'));
            }
        }
        return array();
    }
}

if (!function_exists('seo_analista_competitive_remote_allowed')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_competitive_remote_allowed($set = null) {
        static $allowed = false;
        if (null !== $set) {
            $allowed = (bool) $set;
        }
        return $allowed;
    }
}

if (!function_exists('seo_analista_competitive_serp_rows')) {
    /**
     * Adaptador automatico de rankings para Analista.
     * Si otro adaptador ya entrego filas, no lo sustituye.
     */
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_competitive_serp_rows($rows, $keywords, $competitors, $context = array()) {
        if (!empty($rows)) return $rows;
        if (!class_exists('SEO_Ojeador_Shopping') || !is_callable(array('SEO_Ojeador_Shopping', 'search_google_web'))) {
            return array();
        }

        $keywords = function_exists('seo_analista_sanitize_keywords')
            ? seo_analista_sanitize_keywords((array) $keywords)
            : array_values(array_filter(array_map('sanitize_text_field', (array) $keywords)));
        $keywords = array_slice($keywords, 0, 20);

        $competitors = function_exists('seo_analista_sanitize_domains')
            ? seo_analista_sanitize_domains((array) $competitors)
            : array_values(array_filter(array_map('seo_analista_competitive_host', (array) $competitors)));

        $own = seo_analista_competitive_host(home_url('/'));
        $domains = array_values(array_unique(array_filter(array_merge(array($own), $competitors))));
        if (!$keywords || !$domains) return array();

        $out = array();
        foreach ($keywords as $keyword) {
            $cache_key = 'seo_an_cmp_serp_' . md5('v2|' . $keyword . '|' . implode('|', $domains));
            $cached = get_transient($cache_key);
            $payload = array();
            $trace = array();

            if (is_array($cached) && isset($cached['organic'])) {
                $organic = (array) $cached['organic'];
                $provider = sanitize_key((string) ($cached['provider'] ?? 'cache'));
            } elseif (!seo_analista_competitive_remote_allowed()) {
                // Las vistas normales pueden reutilizar cache, pero nunca abren
                // consultas nuevas. El consumo remoto se habilita solo durante
                // Generar informe competitivo.
                continue;
            } else {
                $response = SEO_Ojeador_Shopping::search_google_web($keyword, array('num'=>20), $trace);
                if (is_wp_error($response)) {
                    set_transient($cache_key, array('organic'=>array(),'provider'=>'error'), 15 * MINUTE_IN_SECONDS);
                    continue;
                }
                $payload = (array) $response;
                $organic = seo_analista_competitive_organic_rows($payload);
                $provider = sanitize_key((string) ($trace['provider'] ?? 'google_provider'));
                set_transient(
                    $cache_key,
                    array(
                        'organic'=>$organic,
                        'provider'=>$provider,
                        'cached_at'=>current_time('mysql'),
                    ),
                    12 * HOUR_IN_SECONDS
                );
            }

            foreach ($organic as $index => $item) {
                $url = esc_url_raw((string) ($item['link'] ?? $item['url'] ?? $item['destination'] ?? ''));
                $domain = seo_analista_competitive_host($url);
                if ($domain === '') continue;

                $matched = '';
                foreach ($domains as $wanted) {
                    if (seo_analista_competitive_domain_matches($domain, $wanted)) {
                        $matched = $wanted;
                        break;
                    }
                }
                if ($matched === '') continue;

                $position = absint($item['position'] ?? 0);
                if (!$position) $position = $index + 1;

                $out[] = array(
                    'keyword' => $keyword,
                    'domain' => $matched,
                    'position' => $position,
                    'url' => $url,
                    'title' => sanitize_text_field((string) ($item['title'] ?? '')),
                    'snippet' => sanitize_textarea_field((string) ($item['snippet'] ?? $item['description'] ?? '')),
                    'volume' => null,
                    'difficulty' => null,
                    'source' => $provider ?: 'google_provider',
                );
            }
        }

        return $out;
    }
}
add_filter('seo_analista_competitor_rankings', 'seo_analista_competitive_serp_rows', 10, 4);

if (!function_exists('seo_analista_competitive_keywords')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_competitive_keywords($days, $limit = 12) {
        $settings = function_exists('seo_analista_get_settings') ? seo_analista_get_settings() : array();
        $tracked = function_exists('seo_analista_sanitize_keywords')
            ? seo_analista_sanitize_keywords((array) ($settings['tracked_keywords'] ?? array()))
            : array();

        if ($tracked) return array_slice($tracked, 0, max(3, min(20, absint($limit))));

        $data = function_exists('seo_analista_get_data') ? (array) seo_analista_get_data($days) : array();
        $keywords = array();
        foreach ((array) ($data['queries'] ?? array()) as $row) {
            $query = function_exists('seo_analista_clean_query')
                ? seo_analista_clean_query($row['query_text'] ?? '')
                : sanitize_text_field((string) ($row['query_text'] ?? ''));
            if ($query === '') continue;
            if (function_exists('seo_analista_query_is_actionable') && !seo_analista_query_is_actionable($query)) continue;
            $keywords[] = $query;
            if (count($keywords) >= max(3, min(20, absint($limit)))) break;
        }
        return array_values(array_unique($keywords));
    }
}

if (!function_exists('seo_analista_competitive_fetch')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_competitive_fetch($url, $limit = 350000) {
        $url = esc_url_raw((string) $url);
        if ($url === '' || !wp_http_validate_url($url)) {
            return array('available'=>false,'url'=>$url,'code'=>0,'body'=>'','error'=>'URL no valida.');
        }
        $response = wp_safe_remote_get($url, array(
            'timeout'=>8,
            'redirection'=>3,
            'limit_response_size'=>max(4096, min(600000, absint($limit))),
            'headers'=>array(
                'Accept'=>'text/html,application/xhtml+xml,text/plain;q=0.8,*/*;q=0.5',
                'User-Agent'=>'SEO-Taxonomy-Competitive-Audit/' . (defined('SEO_ANALISTA_VERSION') ? SEO_ANALISTA_VERSION : '3.8.0'),
            ),
        ));
        if (is_wp_error($response)) {
            return array(
                'available'=>false,
                'url'=>$url,
                'code'=>0,
                'body'=>'',
                'error'=>sanitize_text_field($response->get_error_message()),
            );
        }
        $code = absint(wp_remote_retrieve_response_code($response));
        $body = (string) wp_remote_retrieve_body($response);
        return array(
            'available'=>$code >= 200 && $code < 400 && $body !== '',
            'url'=>$url,
            'code'=>$code,
            'body'=>$body,
            'error'=>$code >= 400 ? 'HTTP ' . $code : '',
        );
    }
}

if (!function_exists('seo_analista_competitive_html_attr')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_competitive_html_attr($html, $tag, $attr, $attr_value, $wanted_attr) {
        $pattern = '#<' . preg_quote($tag, '#') . '\b[^>]*\b' . preg_quote($attr, '#') . '=["\']'
            . preg_quote($attr_value, '#') . '["\'][^>]*>#i';
        if (!preg_match($pattern, (string) $html, $m)) return '';
        if (!preg_match('/\b' . preg_quote($wanted_attr, '/') . '=["\']([^"\']+)["\']/i', $m[0], $v)) return '';
        return html_entity_decode((string) $v[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

if (!function_exists('seo_analista_competitive_html_metrics')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_competitive_html_metrics($html, $domain) {
        $html = (string) $html;
        $text = strtolower(remove_accents(wp_strip_all_tags($html)));
        $title = '';
        if (preg_match('#<title[^>]*>(.*?)</title>#is', $html, $m)) {
            $title = sanitize_text_field(wp_strip_all_tags((string) $m[1]));
        }

        $description = '';
        if (preg_match('#<meta\b[^>]*name=["\']description["\'][^>]*content=["\']([^"\']*)["\'][^>]*>#i', $html, $m)
            || preg_match('#<meta\b[^>]*content=["\']([^"\']*)["\'][^>]*name=["\']description["\'][^>]*>#i', $html, $m)) {
            $description = sanitize_text_field(html_entity_decode((string) $m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        $canonical = '';
        if (preg_match('#<link\b[^>]*rel=["\'][^"\']*canonical[^"\']*["\'][^>]*href=["\']([^"\']+)["\'][^>]*>#i', $html, $m)
            || preg_match('#<link\b[^>]*href=["\']([^"\']+)["\'][^>]*rel=["\'][^"\']*canonical[^"\']*["\'][^>]*>#i', $html, $m)) {
            $canonical = esc_url_raw((string) $m[1]);
        }

        $links = array();
        if (preg_match_all('#<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>#i', $html, $matches)) {
            foreach ((array) ($matches[1] ?? array()) as $href) {
                $href = html_entity_decode((string) $href, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if ($href === '' || strpos($href, '#') === 0 || stripos($href, 'javascript:') === 0) continue;
                $host = seo_analista_competitive_host($href);
                if ($host === '' && strpos($href, '/') === 0) {
                    $host = $domain;
                }
                if ($host !== '' && seo_analista_competitive_domain_matches($host, $domain)) {
                    $links[] = $href;
                }
            }
        }
        $links = array_values(array_unique($links));

        $schema_types = array();
        if (preg_match_all('/"@type"\s*:\s*"([^"]+)"/i', $html, $types)) {
            foreach ((array) ($types[1] ?? array()) as $type) {
                $type = sanitize_text_field((string) $type);
                if ($type !== '') $schema_types[] = $type;
            }
        }
        $schema_types = array_values(array_unique($schema_types));

        $signals = array(
            'viewport' => (bool) preg_match('#<meta\b[^>]*name=["\']viewport["\']#i', $html),
            'search' => (bool) preg_match('#type=["\']search["\']|role=["\']search["\']|buscador|buscar#i', $html),
            'cart' => (bool) preg_match('/carrito|cart|basket|checkout/', $text),
            'filters' => (bool) preg_match('/filtro|filtrar|filter|faceta|refinar/', $text),
            'compare' => (bool) preg_match('/comparador|comparar|compare/', $text),
            'breadcrumbs' => (bool) preg_match('/breadcrumb|migas de pan/', $text),
            'related' => (bool) preg_match('/productos relacionados|tambien te puede|también te puede|accesorios compatibles|related products|otros clientes/', $text),
            'shipping' => (bool) preg_match('/envio|envío|entrega|shipping|delivery/', $text),
            'returns' => (bool) preg_match('/devolucion|devolución|devoluciones|returns/', $text),
            'warranty' => (bool) preg_match('/garantia|garantía|warranty/', $text),
            'reviews' => (bool) preg_match('/opiniones|resenas|reseñas|reviews|valoraciones/', $text),
            'phone' => (bool) preg_match('/tel[eé]fono|\+34|\b[689][0-9 ]{8,}\b/', $text),
            'email' => (bool) preg_match('/mailto:|\b[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}\b/i', $html),
            'whatsapp' => stripos($html, 'whatsapp') !== false,
            'company' => (bool) preg_match('/quienes somos|quiénes somos|sobre nosotros|empresa|aviso legal|cif|nif/', $text),
            'physical_store' => (bool) preg_match('/tienda fisica|tienda física|visitanos|visítanos|direccion|dirección/', $text),
            'technical_service' => (bool) preg_match('/servicio tecnico|servicio técnico|taller propio|sat\b/', $text),
            'price' => (bool) preg_match('/(?:\d+[\.,]\d{2}|\d+)\s*€|€\s*(?:\d+[\.,]\d{2}|\d+)/u', $text),
            'stock' => (bool) preg_match('/en stock|agotado|disponible|disponibilidad|stock/', $text),
            'sku_ean' => (bool) preg_match('/\bsku\b|\bean\b|gtin|referencia/', $text),
            'compatibility' => (bool) preg_match('/compatib|compatible|compatibilidad/', $text),
            'package' => (bool) preg_match('/contenido del paquete|incluye|contenido de la caja|suministro/', $text),
            'specs' => (bool) preg_match('/especificaciones|caracteristicas tecnicas|características técnicas|datos tecnicos|datos técnicos/', $text),
        );

        return array(
            'title'=>$title,
            'description'=>$description,
            'canonical'=>$canonical,
            'h1_count'=>preg_match_all('#<h1\b#i', $html),
            'hreflang_count'=>preg_match_all('#hreflang=["\'][^"\']+["\']#i', $html),
            'internal_links'=>count($links),
            'product_links'=>count(array_filter($links, static function($url) {
                return (bool) preg_match('#/(producto|product|productos|products)/#i', (string) $url);
            })),
            'category_links'=>count(array_filter($links, static function($url) {
                return (bool) preg_match('#/(categoria|category|categorias|categories|coleccion|collection|collections)/#i', (string) $url);
            })),
            'schema_types'=>$schema_types,
            'schema_product'=>function_exists('taxocommerce_google_bing_geo_schema_product') ? taxocommerce_google_bing_geo_schema_product($html) : array('status'=>'unknown'),
            'signals'=>$signals,
        );
    }
}

if (!function_exists('seo_analista_competitive_dimension')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_competitive_dimension($checks, $confidence_cap = 85) {
        $known = 0;
        $positive = 0;
        foreach ((array) $checks as $value) {
            if (null === $value) continue;
            $known++;
            if ($value) $positive++;
        }
        if ($known < 1) {
            return array('score'=>null,'confidence'=>0,'known'=>0,'positive'=>0);
        }
        $ratio = $positive / $known;
        return array(
            'score'=>round(1 + (4 * $ratio), 2),
            'confidence'=>min(absint($confidence_cap), 35 + ($known * 8)),
            'known'=>$known,
            'positive'=>$positive,
        );
    }
}

if (!function_exists('seo_analista_competitive_public_audit')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_competitive_public_audit($domain, $representative_url = '') {
        $domain = seo_analista_competitive_host($domain);
        $home_url = 'https://' . $domain . '/';
        $home = seo_analista_competitive_fetch($home_url, 420000);
        $robots = seo_analista_competitive_fetch('https://' . $domain . '/robots.txt', 120000);

        $representative_url = esc_url_raw((string) $representative_url);
        $representative = array('available'=>false,'url'=>$representative_url,'code'=>0,'body'=>'','error'=>'');
        if ($representative_url !== '' && seo_analista_competitive_domain_matches($representative_url, $domain)) {
            $representative = seo_analista_competitive_fetch($representative_url, 420000);
        }

        $home_metrics = !empty($home['available'])
            ? seo_analista_competitive_html_metrics($home['body'], $domain)
            : array();
        $page_metrics = !empty($representative['available'])
            ? seo_analista_competitive_html_metrics($representative['body'], $domain)
            : array();

        $robots_text = strtolower((string) ($robots['body'] ?? ''));
        $sitemap_declared = !empty($robots['available']) && stripos($robots_text, 'sitemap:') !== false;
        $schema_home = array_map('strtolower', (array) ($home_metrics['schema_types'] ?? array()));
        $schema_page = array_map('strtolower', (array) ($page_metrics['schema_types'] ?? array()));
        $home_signals = (array) ($home_metrics['signals'] ?? array());
        $page_signals = (array) ($page_metrics['signals'] ?? array());

        $technical = seo_analista_competitive_dimension(array(
            !empty($home['available']) ? ((int) ($home['code'] ?? 0) < 400) : null,
            !empty($home['available']) ? true : null,
            array_key_exists('canonical', $home_metrics) ? ((string) $home_metrics['canonical'] !== '') : null,
            array_key_exists('title', $home_metrics) ? ((string) $home_metrics['title'] !== '') : null,
            array_key_exists('description', $home_metrics) ? ((string) $home_metrics['description'] !== '') : null,
            array_key_exists('h1_count', $home_metrics) ? ((int) $home_metrics['h1_count'] >= 1) : null,
            !empty($home_metrics) ? !empty($schema_home) : null,
            !empty($robots['available']) ? true : null,
            !empty($robots['available']) ? $sitemap_declared : null,
        ), 90);

        $architecture = seo_analista_competitive_dimension(array(
            isset($home_metrics['internal_links']) ? ((int) $home_metrics['internal_links'] >= 20) : null,
            isset($home_metrics['internal_links']) ? ((int) $home_metrics['internal_links'] >= 50) : null,
            isset($home_metrics['category_links']) ? ((int) $home_metrics['category_links'] >= 4) : null,
            array_key_exists('search', $home_signals) ? (bool) $home_signals['search'] : null,
            array_key_exists('breadcrumbs', $page_signals) ? (bool) $page_signals['breadcrumbs'] : null,
            !empty($robots['code']) ? $sitemap_declared : null,
        ), 78);

        $linking = seo_analista_competitive_dimension(array(
            isset($home_metrics['internal_links']) ? ((int) $home_metrics['internal_links'] >= 30) : null,
            isset($page_metrics['internal_links']) ? ((int) $page_metrics['internal_links'] >= 12) : null,
            array_key_exists('related', $page_signals) ? (bool) $page_signals['related'] : null,
            array_key_exists('breadcrumbs', $page_signals) ? (bool) $page_signals['breadcrumbs'] : null,
            array_key_exists('compare', $home_signals) ? (bool) $home_signals['compare'] : null,
        ), 78);

        $trust = seo_analista_competitive_dimension(array(
            array_key_exists('phone', $home_signals) ? (bool) $home_signals['phone'] : null,
            array_key_exists('email', $home_signals) ? (bool) $home_signals['email'] : null,
            array_key_exists('company', $home_signals) ? (bool) $home_signals['company'] : null,
            array_key_exists('shipping', $home_signals) ? (bool) $home_signals['shipping'] : null,
            array_key_exists('returns', $home_signals) ? (bool) $home_signals['returns'] : null,
            array_key_exists('warranty', $home_signals) ? (bool) $home_signals['warranty'] : null,
            array_key_exists('reviews', $home_signals) ? (bool) $home_signals['reviews'] : null,
            array_key_exists('physical_store', $home_signals) ? (bool) $home_signals['physical_store'] : null,
            array_key_exists('technical_service', $home_signals) ? (bool) $home_signals['technical_service'] : null,
        ), 76);

        $ux = seo_analista_competitive_dimension(array(
            array_key_exists('viewport', $home_signals) ? (bool) $home_signals['viewport'] : null,
            array_key_exists('search', $home_signals) ? (bool) $home_signals['search'] : null,
            array_key_exists('cart', $home_signals) ? (bool) $home_signals['cart'] : null,
            array_key_exists('filters', $page_signals) ? (bool) $page_signals['filters'] : null,
            array_key_exists('compare', $home_signals) ? (bool) $home_signals['compare'] : null,
            array_key_exists('shipping', $page_signals) ? (bool) $page_signals['shipping'] : null,
            array_key_exists('returns', $page_signals) ? (bool) $page_signals['returns'] : null,
        ), 72);

        $product_checks = array();
        $product_page_available = !empty($representative['available']) && (
            in_array('product', $schema_page, true)
            || !empty($page_signals['price'])
            || !empty($page_signals['sku_ean'])
            || !empty($page_signals['stock'])
        );
        if ($product_page_available) {
            $product_checks = array(
                !empty($page_signals['price']),
                !empty($page_signals['stock']),
                !empty($page_signals['sku_ean']),
                in_array('product', $schema_page, true),
                !empty($page_signals['reviews']),
                !empty($page_signals['shipping']),
                !empty($page_signals['returns']),
                !empty($page_signals['warranty']),
                !empty($page_signals['specs']),
                !empty($page_signals['compatibility']),
                !empty($page_signals['package']),
            );
        }
        $product = seo_analista_competitive_dimension($product_checks, 82);

        return array(
            'domain'=>$domain,
            'home'=>array(
                'available'=>!empty($home['available']),
                'url'=>$home_url,
                'http_code'=>absint($home['code'] ?? 0),
                'metrics'=>$home_metrics,
                'error'=>(string) ($home['error'] ?? ''),
            ),
            'representative'=>array(
                'available'=>!empty($representative['available']),
                'url'=>$representative_url ?: null,
                'http_code'=>absint($representative['code'] ?? 0),
                'metrics'=>$page_metrics,
                'error'=>(string) ($representative['error'] ?? ''),
            ),
            'robots'=>array(
                'available'=>!empty($robots['available']),
                'http_code'=>absint($robots['code'] ?? 0),
                'sitemap_declared'=>$sitemap_declared,
                'error'=>(string) ($robots['error'] ?? ''),
            ),
            'dimensions'=>array(
                'architecture'=>$architecture,
                'product'=>$product,
                'linking'=>$linking,
                'trust'=>$trust,
                'ux'=>$ux,
                'technical'=>$technical,
            ),
        );
    }
}

if (!function_exists('seo_analista_competitive_rank_summary')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_competitive_rank_summary($keywords, $competitors) {
        $own = seo_analista_competitive_host(home_url('/'));
        $domains = array_values(array_unique(array_filter(array_merge(array($own), (array) $competitors))));
        $rows = function_exists('seo_analista_competitor_rankings')
            ? (array) seo_analista_competitor_rankings((array) $keywords, (array) $competitors)
            : array();

        $summary = array();
        foreach ($domains as $domain) {
            $summary[$domain] = array(
                'domain'=>$domain,
                'keywords'=>0,
                'top3'=>0,
                'top10'=>0,
                'top20'=>0,
                'position_sum'=>0.0,
                'visibility_points'=>0.0,
                'representative_url'=>'',
                'rows'=>array(),
            );
        }

        foreach ($rows as $row) {
            $domain = seo_analista_competitive_host($row['domain'] ?? '');
            if (!isset($summary[$domain])) continue;
            $position = (float) ($row['position'] ?? 0);
            if ($position <= 0) continue;
            $summary[$domain]['keywords']++;
            $summary[$domain]['position_sum'] += $position;
            $summary[$domain]['visibility_points'] += max(0, 101 - min(100, $position));
            if ($position <= 3) $summary[$domain]['top3']++;
            if ($position <= 10) $summary[$domain]['top10']++;
            if ($position <= 20) $summary[$domain]['top20']++;
            $summary[$domain]['rows'][] = $row;

            $url = esc_url_raw((string) ($row['url'] ?? ''));
            if ($url !== '') {
                $looks_product = preg_match('#/(producto|product|productos|products)/#i', $url);
                if ($summary[$domain]['representative_url'] === '' || $looks_product) {
                    $summary[$domain]['representative_url'] = $url;
                    if ($looks_product) {
                        // Mantener la primera URL con aspecto de ficha.
                        continue;
                    }
                }
            }
        }

        foreach ($summary as &$row) {
            $count = max(0, (int) $row['keywords']);
            $row['average_position'] = $count > 0 ? round($row['position_sum'] / $count, 1) : null;
            $row['visibility_index'] = $count > 0
                ? round(($row['visibility_points'] / ($count * 100)) * 100, 1)
                : null;
            $row['serp'] = $count > 0
                ? array(
                    'score'=>round(1 + (4 * ((float) $row['visibility_index'] / 100)), 2),
                    'confidence'=>min(96, 45 + ($count * 4)),
                    'known'=>$count,
                    'positive'=>null,
                )
                : array('score'=>null,'confidence'=>0,'known'=>0,'positive'=>0);
            unset($row['position_sum'], $row['visibility_points']);
        }
        unset($row);

        return array(
            'available'=>!empty($rows),
            'keywords'=>array_values((array) $keywords),
            'rows'=>$rows,
            'domains'=>$summary,
        );
    }
}

if (!function_exists('seo_analista_competitive_index')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_competitive_index($dimensions) {
        $weights = array(
            'serp'=>10,
            'architecture'=>20,
            'product'=>15,
            'linking'=>15,
            'trust'=>15,
            'ux'=>15,
            'technical'=>10,
        );
        $weighted = 0.0;
        $available_weight = 0;
        $confidence_weighted = 0.0;

        foreach ($weights as $key=>$weight) {
            $dimension = (array) ($dimensions[$key] ?? array());
            $score = $dimension['score'] ?? null;
            if (null === $score || !is_numeric($score)) continue;
            $weighted += (float) $score * $weight;
            $available_weight += $weight;
            $confidence_weighted += (float) ($dimension['confidence'] ?? 0) * $weight;
        }

        return array(
            'score'=>$available_weight > 0 ? round($weighted / $available_weight, 2) : null,
            'confidence'=>$available_weight > 0 ? round($confidence_weighted / $available_weight, 0) : 0,
            'coverage_weight'=>$available_weight,
        );
    }
}

if (!function_exists('seo_analista_competitive_median')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_competitive_median($values) {
        $values = array_values(array_filter((array) $values, static function($v){ return null !== $v && is_numeric($v); }));
        if (!$values) return null;
        sort($values, SORT_NUMERIC);
        $count = count($values);
        $middle = (int) floor($count / 2);
        if ($count % 2) return (float) $values[$middle];
        return ((float) $values[$middle - 1] + (float) $values[$middle]) / 2;
    }
}

if (!function_exists('seo_analista_build_competitive_report')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_build_competitive_report($days = 28) {
        $days = function_exists('seo_analista_days') ? seo_analista_days($days) : max(7, min(365, absint($days)));
        $settings = function_exists('seo_analista_get_settings') ? seo_analista_get_settings() : array();
        $competitors = function_exists('seo_analista_sanitize_domains')
            ? seo_analista_sanitize_domains((array) ($settings['competitors'] ?? array()))
            : array();
        $competitors = array_slice($competitors, 0, 8);
        $keywords = seo_analista_competitive_keywords($days, 12);
        $own = seo_analista_competitive_host(home_url('/'));

        if (!$competitors) {
            return new WP_Error('analista_competitive_no_domains', 'Añade al menos un dominio competidor antes de generar el informe.');
        }

        seo_analista_competitive_remote_allowed(true);
        try {
            $rank = seo_analista_competitive_rank_summary($keywords, $competitors);
        } finally {
            seo_analista_competitive_remote_allowed(false);
        }
        $domains = array_values(array_unique(array_merge(array($own), $competitors)));
        $matrix = array();

        foreach ($domains as $domain) {
            $rank_row = (array) ($rank['domains'][$domain] ?? array());
            $public = seo_analista_competitive_public_audit($domain, (string) ($rank_row['representative_url'] ?? ''));
            $dimensions = (array) ($public['dimensions'] ?? array());
            $dimensions = array_merge(array('serp'=>(array) ($rank_row['serp'] ?? array())), $dimensions);
            $index = seo_analista_competitive_index($dimensions);

            $matrix[$domain] = array(
                'domain'=>$domain,
                'is_own'=>$domain === $own,
                'dimensions'=>$dimensions,
                'index'=>$index,
                'serp'=>array(
                    'keywords'=>absint($rank_row['keywords'] ?? 0),
                    'top3'=>absint($rank_row['top3'] ?? 0),
                    'top10'=>absint($rank_row['top10'] ?? 0),
                    'top20'=>absint($rank_row['top20'] ?? 0),
                    'average_position'=>$rank_row['average_position'] ?? null,
                    'visibility_index'=>$rank_row['visibility_index'] ?? null,
                    'representative_url'=>(string) ($rank_row['representative_url'] ?? ''),
                ),
                'public'=>$public,
            );
        }

        $data = function_exists('seo_analista_get_data') ? (array) seo_analista_get_data($days) : array();
        $ga4 = function_exists('seo_analista_ga4_snapshot') ? (array) seo_analista_ga4_snapshot($days) : array();
        $bing = function_exists('seo_analista_bing_snapshot') ? (array) seo_analista_bing_snapshot($days, 30) : array();
        $market = function_exists('seo_analista_market_signals') ? (array) seo_analista_market_signals(30) : array();

        $own_private = array(
            'period'=>(array) ($data['period'] ?? array()),
            'search_console'=>array(
                'available'=>!empty($data['ready']),
                'impressions'=>isset($data['current']['impressions']) ? (float) $data['current']['impressions'] : null,
                'clicks'=>isset($data['current']['clicks']) ? (float) $data['current']['clicks'] : null,
                'ctr'=>isset($data['current']['ctr']) ? (float) $data['current']['ctr'] : null,
                'position'=>isset($data['current']['position']) ? (float) $data['current']['position'] : null,
            ),
            'ga4'=>array(
                'available'=>!empty($ga4['available']),
                'sessions'=>!empty($ga4['available']) ? (int) ($ga4['sessions'] ?? 0) : null,
                'users'=>!empty($ga4['available']) ? (int) ($ga4['users'] ?? 0) : null,
                'pageviews'=>!empty($ga4['available']) ? (int) ($ga4['pageviews'] ?? 0) : null,
                'purchases'=>!empty($ga4['available']) ? (int) ($ga4['purchases'] ?? 0) : null,
                'revenue'=>!empty($ga4['available']) ? (float) ($ga4['revenue'] ?? 0) : null,
            ),
            'bing'=>array(
                'available'=>!empty($bing['connected']),
                'impressions'=>!empty($bing['connected']) ? (float) ($bing['traffic']['impressions'] ?? 0) : null,
                'clicks'=>!empty($bing['connected']) ? (float) ($bing['traffic']['clicks'] ?? 0) : null,
            ),
        );

        $dimension_labels = array(
            'serp'=>'SERP',
            'architecture'=>'Arquitectura',
            'product'=>'Producto',
            'linking'=>'Enlazado',
            'trust'=>'Confianza',
            'ux'=>'UX',
            'technical'=>'Técnico observable',
        );
        $strengths = array();
        $gaps = array();
        $own_row = (array) ($matrix[$own] ?? array());

        foreach ($dimension_labels as $key=>$label) {
            $own_score = $own_row['dimensions'][$key]['score'] ?? null;
            if (null === $own_score) continue;
            $peer_scores = array();
            foreach ($matrix as $domain=>$row) {
                if ($domain === $own) continue;
                $score = $row['dimensions'][$key]['score'] ?? null;
                if (null !== $score) $peer_scores[] = $score;
            }
            $median = seo_analista_competitive_median($peer_scores);
            if (null === $median) continue;
            $delta = round((float) $own_score - (float) $median, 2);
            $item = array('dimension'=>$key,'label'=>$label,'own'=>(float) $own_score,'peer_median'=>round($median,2),'delta'=>$delta);
            if ($delta >= 0.35) $strengths[] = $item;
            if ($delta <= -0.35) $gaps[] = $item;
        }

        usort($strengths, static function($a,$b){ return (float) $b['delta'] <=> (float) $a['delta']; });
        usort($gaps, static function($a,$b){ return (float) $a['delta'] <=> (float) $b['delta']; });

        $ranking = array_values($matrix);
        usort($ranking, static function($a,$b){
            $as = $a['index']['score'] ?? null;
            $bs = $b['index']['score'] ?? null;
            if (null === $as && null === $bs) return 0;
            if (null === $as) return 1;
            if (null === $bs) return -1;
            return (float) $bs <=> (float) $as;
        });
        $own_rank = null;
        foreach ($ranking as $idx=>$row) {
            if (!empty($row['is_own'])) {
                $own_rank = $idx + 1;
                break;
            }
        }

        $plan_map = array(
            'serp'=>array('Auditar consultas y URLs con brecha frente a competidores y concentrar autoridad interna en las familias con demanda demostrada.','Muy alto','Medio'),
            'architecture'=>array('Revisar jerarquía, hubs y enlazado de las familias prioritarias; reforzar rutas necesidad → categoría → producto.','Alto','Medio'),
            'product'=>array('Estandarizar fichas prioritarias con precio, stock, SKU/EAN, entrega, garantía, especificaciones, compatibilidad y FAQ verificable.','Alto','Medio'),
            'linking'=>array('Reforzar bucles de enlaces guía → hub → categoría → producto → accesorios/compatibles.','Alto','Bajo-medio'),
            'trust'=>array('Aumentar señales públicas verificables de empresa, soporte, devoluciones, garantías, opiniones y relación con fabricantes/proveedores.','Alto','Medio'),
            'ux'=>array('Revisar búsqueda, filtros, comparación y señales de compra visibles en móvil y escritorio.','Medio-alto','Medio'),
            'technical'=>array('Verificar robots, sitemaps, canonical, metadatos, schema e indexabilidad de plantillas prioritarias.','Muy alto','Medio'),
        );
        $plan = array();
        foreach ($gaps as $gap) {
            $key = (string) $gap['dimension'];
            if (!isset($plan_map[$key])) continue;
            $plan[] = array(
                'priority'=>count($plan) + 1,
                'dimension'=>$key,
                'action'=>$plan_map[$key][0],
                'impact'=>$plan_map[$key][1],
                'effort'=>$plan_map[$key][2],
                'evidence'=>array('own'=>$gap['own'],'peer_median'=>$gap['peer_median'],'delta'=>$gap['delta']),
            );
        }
        if (!$plan) {
            $plan[] = array(
                'priority'=>1,
                'dimension'=>'serp',
                'action'=>'Mantener seguimiento de consultas y competidores; no hay una brecha pública suficientemente clara para recomendar una intervención estructural.',
                'impact'=>'Medio',
                'effort'=>'Bajo',
                'evidence'=>array(),
            );
        }

        $own_index = $own_row['index']['score'] ?? null;
        $leader = $ranking ? (array) $ranking[0] : array();
        $summary = array(
            'own_index'=>$own_index,
            'own_confidence'=>(int) ($own_row['index']['confidence'] ?? 0),
            'rank'=>$own_rank,
            'domains'=>count($ranking),
            'leader'=>(string) ($leader['domain'] ?? ''),
            'leader_index'=>$leader['index']['score'] ?? null,
            'statement'=>null !== $own_index
                ? sprintf(
                    'Índice competitivo observable %.2f/5, posición %s de %d en la muestra. La puntuación resume evidencia pública comparable; GSC, GA4 y Bing se muestran aparte y sólo corresponden al dominio propio.',
                    (float) $own_index,
                    null === $own_rank ? 'N/D' : (string) $own_rank,
                    count($ranking)
                )
                : 'No existe evidencia pública suficiente para calcular un índice competitivo comparable.',
        );

        return array(
            'schema'=>array('name'=>'seo_analista_competitive_report','version'=>1),
            'generated_at'=>current_time('mysql'),
            'period_days'=>$days,
            'own_domain'=>$own,
            'competitors'=>$competitors,
            'keywords'=>$keywords,
            'ranking_source'=>!empty($rank['available']) ? 'SerpApi/ScraperAPI · Google Search compartido' : 'Sin posiciones externas disponibles',
            'summary'=>$summary,
            'strengths'=>array_slice($strengths,0,5),
            'gaps'=>array_slice($gaps,0,5),
            'matrix'=>$ranking,
            'keyword_rows'=>array_slice((array) ($rank['rows'] ?? array()),0,300),
            'own_private'=>$own_private,
            'market_context'=>array_slice($market,0,20),
            'plan'=>$plan,
            'limitations'=>array(
                'Search Console, GA4 y Bing sólo describen el dominio conectado; nunca se atribuyen a competidores.',
                'La visibilidad de competidores es un muestreo SERP sobre las palabras seleccionadas, no una estimación de tráfico total.',
                'Google Trends se usa como señal temática y no como tráfico de dominio.',
                'Arquitectura, confianza, UX y SEO técnico son observaciones públicas de una muestra acotada; no sustituyen un crawl completo.',
                'Si una dimensión no tiene evidencia suficiente aparece como N/D y no reduce artificialmente el índice.',
                'Core Web Vitals de competidores y autoridad/backlinks requieren fuentes adicionales homogéneas; no se inventan.',
            ),
        );
    }
}

if (!function_exists('seo_analista_competitive_save_report')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_competitive_save_report($report) {
        if (!is_array($report)) return false;
        update_option(SEO_ANALISTA_COMPETITIVE_REPORT_OPTION, $report, false);
        $history = get_option(SEO_ANALISTA_COMPETITIVE_REPORT_HISTORY_OPTION, array());
        $history = is_array($history) ? $history : array();
        $history[] = array(
            'generated_at'=>(string) ($report['generated_at'] ?? ''),
            'summary'=>(array) ($report['summary'] ?? array()),
            'competitors'=>(array) ($report['competitors'] ?? array()),
            'keywords'=>(array) ($report['keywords'] ?? array()),
        );
        $history = array_slice($history, -8);
        update_option(SEO_ANALISTA_COMPETITIVE_REPORT_HISTORY_OPTION, $history, false);
        return true;
    }
}

if (!function_exists('seo_analista_generate_competitive_report_handler')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_generate_competitive_report_handler() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para generar el informe competitivo.', 'taxocommerce-google-bing'));
        }
        check_admin_referer('seo_analista_generate_competitive_report', 'seo_analista_competitive_nonce');
        $days = isset($_POST['analista_days']) ? absint(wp_unslash($_POST['analista_days'])) : 28;
        $days = in_array($days, array(28,90), true) ? $days : 28;

        $report = seo_analista_build_competitive_report($days);
        if (is_wp_error($report)) {
            wp_safe_redirect(seo_analista_admin_url(array(
                'analista_view'=>'comparacion',
                'analista_days'=>$days,
                'analista_notice'=>'competitive_error',
                'analista_message'=>rawurlencode($report->get_error_message()),
            )));
            exit;
        }

        seo_analista_competitive_save_report($report);
        wp_safe_redirect(seo_analista_admin_url(array(
            'analista_view'=>'comparacion',
            'analista_days'=>$days,
            'analista_notice'=>'competitive_generated',
        )));
        exit;
    }
}
add_action('admin_post_seo_analista_generate_competitive_report', 'seo_analista_generate_competitive_report_handler');

if (!function_exists('seo_analista_export_competitive_report_handler')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_export_competitive_report_handler() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos para exportar el informe competitivo.', 'taxocommerce-google-bing'));
        }
        check_admin_referer('seo_analista_export_competitive_report');

        $report = get_option(SEO_ANALISTA_COMPETITIVE_REPORT_OPTION, array());
        if (!is_array($report) || !$report) {
            wp_die(esc_html__('Todavía no existe un informe competitivo guardado.', 'taxocommerce-google-bing'));
        }

        $filename = 'analista-informe-competitivo-' . current_time('Ymd-His') . '.json';
        nocache_headers();
        header('Content-Type: application/json; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
        header('X-Content-Type-Options: nosniff');
        echo wp_json_encode($report, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
        exit;
    }
}
add_action('admin_post_seo_analista_export_competitive_report', 'seo_analista_export_competitive_report_handler');

if (!function_exists('seo_analista_competitive_score_text')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_competitive_score_text($dimension) {
        $dimension = is_array($dimension) ? $dimension : array();
        if (!array_key_exists('score', $dimension) || null === $dimension['score']) return 'N/D';
        $score = number_format_i18n((float) $dimension['score'], 2);
        $confidence = absint($dimension['confidence'] ?? 0);
        return $score . '/5 · ' . $confidence . '%';
    }
}

if (!function_exists('seo_analista_render_competitive_deep_panel')) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Legacy callable name is used by existing modules; changing it requires a coordinated migration.
    function seo_analista_render_competitive_deep_panel($days = 28) {
        $days = in_array(absint($days), array(28,90), true) ? absint($days) : 28;
        $report = get_option(SEO_ANALISTA_COMPETITIVE_REPORT_OPTION, array());
        $report = is_array($report) ? $report : array();
        $settings = function_exists('seo_analista_get_settings') ? seo_analista_get_settings() : array();
        $competitors = (array) ($settings['competitors'] ?? array());
        $tracked = (array) ($settings['tracked_keywords'] ?? array());

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only GET notice rendered with escaped output.
        if (isset($_GET['analista_notice'])) {
            $notice = sanitize_key(wp_unslash($_GET['analista_notice']));
            if ($notice === 'competitive_generated') {
                echo '<div class="notice notice-success inline">' . '<p>' . '<strong>' . esc_html__( 'Informe competitivo generado y guardado.', 'taxocommerce-google-bing' ) . '</strong>' . '</p>' . '</div>';
            } elseif ($notice === 'competitive_error') {
                $message = isset($_GET['analista_message'])
                    ? sanitize_text_field( wp_unslash( $_GET['analista_message'] ) )
                    : 'No se pudo generar el informe.';
                echo '<div class="notice notice-error inline"><p><strong>' . esc_html($message) . '</strong></p></div>';
            }
        }

        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        echo '<section class="seo-analista-section seo-analista-forms">';
        echo '<div class="seo-analista-section-head">' . '<div>' . '<h2>' . esc_html__( 'Informe competitivo profundo', 'taxocommerce-google-bing' ) . '</h2>';
        echo '<p>' . esc_html__( 'Compara dominios con una base homogénea de evidencia pública y añade, sólo para nuestra web, Search Console, GA4 y Bing. La generación es explícita: abrir esta pantalla no consume SerpApi/ScraperAPI ni rastrea competidores.', 'taxocommerce-google-bing' ) . '</p>' . '</div>' . '</div>';

        echo '<div class="seo-analista-grid compact">';
        if (function_exists('seo_analista_render_metric_card')) {
            seo_analista_render_metric_card('Competidores configurados', number_format_i18n(count($competitors)), '', 'Máximo 8 dominios por informe profundo.');
            seo_analista_render_metric_card('Keywords vigiladas', number_format_i18n(count($tracked)), '', $tracked ? 'Se usan primero estas palabras.' : 'Si está vacío, Analista toma una muestra de Search Console.');
            seo_analista_render_metric_card('Último informe', !empty($report['generated_at']) ? (string) $report['generated_at'] : '—', '', 'El último informe se conserva hasta regenerarlo.');
        }
        echo '</div>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block;margin-right:8px;">';
        echo '<input type="hidden" name="action" value="seo_analista_generate_competitive_report">';
        echo '<input type="hidden" name="analista_days" value="' . esc_attr($days) . '">';
        wp_nonce_field('seo_analista_generate_competitive_report', 'seo_analista_competitive_nonce');
        submit_button('Generar informe competitivo', 'primary', 'submit', false);
        echo '</form>';

        if ($report) {
            $export_url = wp_nonce_url(
                admin_url('admin-post.php?action=seo_analista_export_competitive_report'),
                'seo_analista_export_competitive_report'
            );
            echo '<a class="button button-secondary" href="' . esc_url($export_url) . '">Exportar JSON</a>';
        }
        echo '<p class="description">' . esc_html__( 'Coste: hasta 12 búsquedas SERP y un muestreo público acotado por dominio. SerpApi se usa primero y ScraperAPI como fallback, según la conexión compartida ya configurada.', 'taxocommerce-google-bing' ) . '</p>';

        if (!$report) {
            echo '<p class="description" style="margin-top:12px;">' . esc_html__( 'Configura primero los dominios en', 'taxocommerce-google-bing' ) . ' ' . '<strong>' . esc_html__( 'Competidores', 'taxocommerce-google-bing' ) . '</strong>' . esc_html__( '. Las palabras vigiladas son opcionales.', 'taxocommerce-google-bing' ) . '</p>';
            echo '</section>';
            return;
        }

        $summary = (array) ($report['summary'] ?? array());
        echo '<h3>' . esc_html__( 'Conclusión ejecutiva', 'taxocommerce-google-bing' ) . '</h3>';
        echo '<p>' . esc_html((string) ($summary['statement'] ?? '')) . '</p>';
        echo '<p class="description">Fuente SERP: ' . esc_html((string) ($report['ranking_source'] ?? '')) . ' · Palabras analizadas: ' . esc_html(number_format_i18n(count((array) ($report['keywords'] ?? array())))) . '.</p>';

        $labels = array(
            'serp'=>'SERP',
            'architecture'=>'Arquitectura',
            'product'=>'Producto',
            'linking'=>'Enlazado',
            'trust'=>'Confianza',
            'ux'=>'UX',
            'technical'=>'Técnico',
        );
        echo '<div class="seo-analista-table-wrap">' . '<table class="widefat striped">' . '<thead>' . '<tr>' . '<th>' . esc_html__( 'Dominio', 'taxocommerce-google-bing' ) . '</th>';
        foreach ($labels as $label) echo '<th>' . esc_html($label) . '</th>';
        echo '<th>' . esc_html__( 'Índice', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Evidencia', 'taxocommerce-google-bing' ) . '</th>' . '</tr>' . '</thead>' . '<tbody>';
        foreach ((array) ($report['matrix'] ?? array()) as $row) {
            $is_own = !empty($row['is_own']);
            echo '<tr' . ($is_own ? ' class="seo-analista-own"' : '') . '><td><strong>' . esc_html((string) ($row['domain'] ?? '')) . ($is_own ? ' · nosotros' : '') . '</strong></td>';
            foreach ($labels as $key=>$label) {
                echo '<td>' . esc_html(seo_analista_competitive_score_text((array) ($row['dimensions'][$key] ?? array()))) . '</td>';
            }
            $index = (array) ($row['index'] ?? array());
            echo '<td><strong>' . esc_html(null === ($index['score'] ?? null) ? 'N/D' : number_format_i18n((float) $index['score'],2) . '/5') . '</strong></td>';
            echo '<td>' . esc_html(absint($index['confidence'] ?? 0) . '%') . '</td></tr>';
        }
        echo '</tbody></table></div>';

        if (!empty($report['strengths'])) {
            echo '<h3>' . esc_html__( 'Dónde estamos por encima', 'taxocommerce-google-bing' ) . '</h3>' . '<ul>';
            foreach ((array) $report['strengths'] as $row) {
                echo '<li><strong>' . esc_html((string) ($row['label'] ?? '')) . '</strong>: ' .
                    esc_html(number_format_i18n((float) ($row['own'] ?? 0),2) . '/5 frente a mediana ' . number_format_i18n((float) ($row['peer_median'] ?? 0),2) . '/5') . '.</li>';
            }
            echo '</ul>';
        }

        if (!empty($report['gaps'])) {
            echo '<h3>' . esc_html__( 'Dónde estamos por debajo', 'taxocommerce-google-bing' ) . '</h3>' . '<ul>';
            foreach ((array) $report['gaps'] as $row) {
                echo '<li><strong>' . esc_html((string) ($row['label'] ?? '')) . '</strong>: ' .
                    esc_html(number_format_i18n((float) ($row['own'] ?? 0),2) . '/5 frente a mediana ' . number_format_i18n((float) ($row['peer_median'] ?? 0),2) . '/5') . '.</li>';
            }
            echo '</ul>';
        }

        echo '<h3>' . esc_html__( 'Plan priorizado', 'taxocommerce-google-bing' ) . '</h3>';
        echo '<div class="seo-analista-table-wrap">' . '<table class="widefat striped">' . '<thead>' . '<tr>' . '<th>' . esc_html__( 'Prioridad', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Área', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Acción', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Impacto', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Esfuerzo', 'taxocommerce-google-bing' ) . '</th>' . '</tr>' . '</thead>' . '<tbody>';
        foreach ((array) ($report['plan'] ?? array()) as $row) {
            echo '<tr><td>' . esc_html(absint($row['priority'] ?? 0)) . '</td><td>' . esc_html((string) ($row['dimension'] ?? '')) . '</td><td>' . esc_html((string) ($row['action'] ?? '')) . '</td><td>' . esc_html((string) ($row['impact'] ?? '')) . '</td><td>' . esc_html((string) ($row['effort'] ?? '')) . '</td></tr>';
        }
        echo '</tbody></table></div>';

        echo '<details style="margin-top:14px;">' . '<summary>' . '<strong>' . esc_html__( 'Calidad y límites de la evidencia', 'taxocommerce-google-bing' ) . '</strong>' . '</summary>' . '<ul>';
        foreach ((array) ($report['limitations'] ?? array()) as $limit) {
            echo '<li>' . esc_html((string) $limit) . '</li>';
        }
        echo '</ul></details>';
        echo '</section>';
    }
}
