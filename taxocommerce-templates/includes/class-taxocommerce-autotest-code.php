<?php
/**
 * Independent, bounded PHP/WordPress/security self-audit.
 *
 * Vendored in this plugin from .github/templates/taxocommerce-autotest-code.php.template.
 * Never loads or depends on TaxoCommerce Core or WordPress Plugin Check.
 *
 * @package TaxoCommerceTemplates
 */
defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-taxocommerce-code-review-rules.php';

final class TaxoCommerce_Templates_Autotest_Code {
    const ACTION = 'taxocommerce_templates_code_check';
    const EXPORT_ACTION = 'taxocommerce_templates_code_check_json';
    const TTL = HOUR_IN_SECONDS;
    const DOMAIN = 'taxocommerce-templates';

    public static function boot() {
        add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
        add_action( 'admin_post_' . self::EXPORT_ACTION, array( __CLASS__, 'export' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
    }

    public static function assets() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only navigation; no data is changed.
        $section = isset( $_GET['tc_autotest_section'] ) && is_scalar( $_GET['tc_autotest_section'] )
            ? sanitize_key( wp_unslash( $_GET['tc_autotest_section'] ) ) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        if ( 'code' === $section && current_user_can( 'manage_options' ) ) {
            wp_enqueue_script(
                'taxocommerce-templates-autotest-code',
                plugins_url( 'assets/autotest-code.js', dirname( __DIR__ ) . '/taxocommerce-templates.php' ),
                array(),
                '1.0.0',
                true
            );
        wp_enqueue_style(
            'taxocommerce-templates-autotest-code',
            plugins_url( 'assets/autotest-code.css', dirname( __DIR__ ) . '/taxocommerce-templates.php' ),
            array(),
            '0.4.33'
        );
        }
    }

    public static function speeds() {
        return array(
            1 => array( 'size' => 1, 'delay' => 5000 ),
            2 => array( 'size' => 1, 'delay' => 3000 ),
            3 => array( 'size' => 2, 'delay' => 2000 ),
            4 => array( 'size' => 3, 'delay' => 1200 ),
            5 => array( 'size' => 4, 'delay' => 800 ),
        );
    }

    private static function key() {
        return 'tc_templates_code_' . get_current_blog_id() . '_' . get_current_user_id();
    }

    private static function state() {
        $value = get_transient( self::key() );
        return is_array( $value ) && isset( $value['files'], $value['cursor'], $value['results'], $value['status'] ) ? $value : false;
    }

    /** One plugin directory, no symlink traversal or other plugin code. */
    public static function files() {
        $root = realpath( dirname( __DIR__ ) );
        if ( ! $root ) {
            return array();
        }
        $files = array();
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
        );
        foreach ( $iterator as $entry ) {
            if ( count( $files ) >= 250 ) {
                break;
            }
            if ( $entry->isLink() || ! $entry->isFile() || 'php' !== strtolower( $entry->getExtension() ) ) {
                continue;
            }
            $path = realpath( $entry->getPathname() );
            if ( false !== $path && 0 === strpos( $path, $root . DIRECTORY_SEPARATOR ) ) {
                $files[] = str_replace( DIRECTORY_SEPARATOR, '/', substr( $path, strlen( $root ) + 1 ) );
            }
        }
        sort( $files, SORT_STRING );
        return $files;
    }

    private static function finding( $status, $evidence = array() ) {
        return array( 'status' => $status, 'evidence' => array_slice( $evidence, 0, 150 ) );
    }

    public static function analyze( $relative ) {
        $root = realpath( dirname( __DIR__ ) );
        $path = realpath( dirname( __DIR__ ) . '/' . $relative );
        if ( ! $root || ! $path || 0 !== strpos( $path, $root . DIRECTORY_SEPARATOR )
            || ! is_file( $path ) || ! is_readable( $path ) || 'php' !== strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
            $unknown = self::finding( 'skip', array( array( 'rule' => 'file_unavailable' ) ) );
            return array( 'php' => $unknown, 'wordpress' => $unknown, 'security' => $unknown, 'declarations' => array() );
        }
        $size = filesize( $path );
        if ( false === $size || $size > 786432 ) {
            $unknown = self::finding( 'skip', array( array( 'rule' => 'file_too_large' ) ) );
            return array( 'php' => $unknown, 'wordpress' => $unknown, 'security' => $unknown, 'declarations' => array() );
        }
        $source = file_get_contents( $path );
        if ( ! is_string( $source ) ) {
            $unknown = self::finding( 'skip', array( array( 'rule' => 'file_unreadable' ) ) );
            return array( 'php' => $unknown, 'wordpress' => $unknown, 'security' => $unknown, 'declarations' => array() );
        }
        return self::analyze_source( $source, $relative, $root );
    }

    /**
     * Source-only analyzer. PHP parser does not execute inspected code.
     * Security results are heuristic signals, not a WordPress.org verdict.
     */
    public static function analyze_source( $source, $relative, $root ) {
        $php = array();
        try {
            token_get_all( $source, TOKEN_PARSE );
        } catch ( ParseError $error ) {
            $php[] = array( 'rule' => 'php_parse_error', 'line' => absint( $error->getLine() ) );
        }
        $tokens = token_get_all( $source );
        $wp = array();
        $security = array();
        $declarations = array();
        if ( false === strpos( $source, 'ABSPATH' ) ) {
            $wp[] = array( 'rule' => 'missing_abspath_guard' );
        }
        if ( 'taxocommerce-templates.php' === $relative ) {
            foreach ( array( 'Plugin Name', 'Version', 'Text Domain', 'Requires PHP', 'License' ) as $field ) {
                if ( ! preg_match( '/^\s*\*\s*' . preg_quote( $field, '/' ) . ':\s*\S+/mi', $source ) ) {
                    $wp[] = array( 'rule' => 'missing_plugin_header', 'field' => $field );
                }
            }
            if ( ! preg_match( '/^\s*\*\s*Text Domain:\s*taxocommerce-templates\s*$/mi', $source ) ) {
                $wp[] = array( 'rule' => 'incorrect_text_domain' );
            }
            if ( ! is_readable( $root . '/readme.txt' ) ) {
                $wp[] = array( 'rule' => 'missing_readme' );
            }
        }
        $class_depth = array();
        $new_class = false;
        $depth = 0;
        $input = 0;
        $echo = 0;
        $wpdb = 0;
        $nonce = false;
        $capability = false;
        $sanitize = false;
        $escape = false;
        $prepare = false;
        $rest = 0;
        $permission = false;
        $ajax = 0;
        $namespace = '';
        $n = count( $tokens );
        foreach ( $tokens as $i => $token ) {
            if ( '{' === $token || ( is_array( $token ) && in_array( $token[0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) ) ) {
                ++$depth;
                if ( $new_class ) {
                    $class_depth[] = $depth;
                    $new_class = false;
                }
                continue;
            }
            if ( '}' === $token ) {
                if ( $class_depth && end( $class_depth ) === $depth ) {
                    array_pop( $class_depth );
                }
                $depth = max( 0, $depth - 1 );
                continue;
            }
            if ( ! is_array( $token ) ) {
                continue;
            }
            $id = $token[0];
            $text = $token[1];
            $line = $token[2];
            if ( T_NAMESPACE === $id ) {
                $namespace = '';
                for ( $j = $i + 1; $j < $n; ++$j ) {
                    $piece = $tokens[ $j ];
                    if ( ';' === $piece || '{' === $piece ) {
                        break;
                    }
                    if ( is_array( $piece ) && ( T_STRING === $piece[0]
                        || T_NS_SEPARATOR === $piece[0]
                        || ( defined( 'T_NAME_QUALIFIED' ) && T_NAME_QUALIFIED === $piece[0] ) ) ) {
                        $namespace .= $piece[1];
                    }
                }
            }
            if ( in_array( $id, array( T_CLASS, T_INTERFACE, T_TRAIT ), true )
                || ( defined( 'T_ENUM' ) && T_ENUM === $id ) ) {
                $new_class = true;
                $j = self::next_token( $tokens, $i );
                if ( null !== $j && is_array( $tokens[ $j ] ) && T_STRING === $tokens[ $j ][0] ) {
                    $declarations[] = array(
                        'name' => strtolower( trim( $namespace . '\\' . $tokens[ $j ][1], '\\' ) ),
                        'kind' => 'class',
                        'line' => $line,
                    );
                }
            }
            if ( T_FUNCTION === $id && ! $class_depth && 0 === $depth ) {
                $j = self::next_token( $tokens, $i );
                if ( null !== $j && '&' === $tokens[ $j ] ) {
                    $j = self::next_token( $tokens, $j );
                }
                if ( null !== $j && is_array( $tokens[ $j ] ) && T_STRING === $tokens[ $j ][0] ) {
                    $declarations[] = array(
                        'name' => strtolower( trim( $namespace . '\\' . $tokens[ $j ][1], '\\' ) ),
                        'kind' => 'function',
                        'line' => $line,
                    );
                }
            }
            if ( T_VARIABLE === $id && in_array( $text, array( '$_GET', '$_POST', '$_REQUEST', '$_COOKIE', '$_FILES' ), true ) ) {
                $input = $input ?: $line;
            }
            if ( T_ECHO === $id || T_PRINT === $id ) {
                $echo = $echo ?: $line;
            }
            if ( T_VARIABLE === $id && '$wpdb' === $text ) {
                $wpdb = $wpdb ?: $line;
            }
            if ( T_CONSTANT_ENCAPSED_STRING === $id ) {
                if ( false !== strpos( $text, 'wp_ajax_' ) ) {
                    $ajax = $ajax ?: $line;
                }
                if ( false !== strpos( $text, 'permission_callback' ) ) {
                    $permission = true;
                }
            }
            if ( T_EVAL === $id ) {
                $security[] = array( 'rule' => 'dangerous_eval', 'line' => $line );
            }
            if ( T_STRING === $id ) {
                $name = strtolower( $text );
                if ( in_array( $name, array( 'exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen', 'unserialize' ), true )
                    && self::is_function_call( $tokens, $i ) ) {
                    $security[] = array( 'rule' => 'dangerous_call_' . $name, 'line' => $line );
                }
                if ( 0 === strpos( $name, 'sanitize_' ) || in_array( $name, array( 'wp_unslash', 'absint', 'filter_input', 'wp_kses' ), true ) ) {
                    $sanitize = true;
                }
                if ( 0 === strpos( $name, 'esc_' ) || 'wp_kses' === $name ) {
                    $escape = true;
                }
                if ( 'prepare' === $name ) {
                    $prepare = true;
                }
                if ( in_array( $name, array( 'check_admin_referer', 'check_ajax_referer', 'wp_verify_nonce' ), true ) ) {
                    $nonce = true;
                }
                if ( 'current_user_can' === $name ) {
                    $capability = true;
                }
                if ( 'register_rest_route' === $name ) {
                    $rest = $rest ?: $line;
                }
                $php8 = array( 'str_contains', 'str_starts_with', 'str_ends_with', 'array_is_list' );
                if ( in_array( $name, $php8, true ) && self::is_function_call( $tokens, $i ) ) {
                    $php[] = array( 'rule' => 'php_8_function_' . $name, 'line' => $line );
                }
            }
            if ( defined( 'T_MATCH' ) && T_MATCH === $id ) {
                $php[] = array( 'rule' => 'php_8_match', 'line' => $line );
            }
            if ( defined( 'T_NULLSAFE_OBJECT_OPERATOR' ) && T_NULLSAFE_OBJECT_OPERATOR === $id ) {
                $php[] = array( 'rule' => 'php_8_nullsafe', 'line' => $line );
            }
        }
        if ( $input && ! $sanitize ) {
            $security[] = array( 'rule' => 'review_input_sanitization', 'line' => $input );
        }
        if ( $echo && ! $escape ) {
            $security[] = array( 'rule' => 'review_output_escaping', 'line' => $echo );
        }
        if ( $wpdb && ! $prepare ) {
            $security[] = array( 'rule' => 'review_sql_preparation', 'line' => $wpdb );
        }
        if ( $ajax && ! $nonce ) {
            $security[] = array( 'rule' => 'review_ajax_nonce', 'line' => $ajax );
        }
        if ( $ajax && ! $capability ) {
            $security[] = array( 'rule' => 'review_ajax_capability', 'line' => $ajax );
        }
        if ( $rest && ! $permission ) {
            $security[] = array( 'rule' => 'review_rest_permissions', 'line' => $rest );
        }

        // Verify only simple, unambiguously local __DIR__ literal includes.
        if ( preg_match_all(
            '/\b(?:include|require)(?:_once)?\s*\(?\s*__DIR__\s*\.\s*(["\x27])([^"\x27]+)\1\s*\)?\s*;/i',
            $source,
            $matches,
            PREG_OFFSET_CAPTURE
        ) ) {
            foreach ( $matches[2] as $match ) {
                $candidate = dirname( $root . '/' . $relative ) . '/' . ltrim( $match[0], '/\\' );
                $inside = realpath( $candidate );
                if ( false === $inside ) {
                    $wp[] = array( 'rule' => 'missing_literal_include', 'path' => basename( $candidate ) );
                } elseif ( 0 !== strpos( $inside, $root . DIRECTORY_SEPARATOR ) ) {
                    $wp[] = array( 'rule' => 'include_outside_plugin' );
                }
            }
        }
        // Native WordPress.org review signals complement (never replace) the
        // existing PHP, WordPress, and security indicators. No other plugin,
        // executable, network service or WordPress Plugin Check is invoked.
        $local = TaxoCommerce_Templates_Code_Review_Rules::scan( $source, $relative );
        $php = array_merge( $php, $local['php'] );
        $wp = array_merge( $wp, $local['wordpress'] );
        $security = array_merge( $security, $local['security'] );
        return array(
            'php' => self::finding( $php ? ( 'php_parse_error' === $php[0]['rule'] ? 'fail' : 'warning' ) : 'pass', $php ),
            'wordpress' => self::finding( $wp ? 'warning' : 'pass', $wp ),
            'security' => self::finding( $security ? 'warning' : 'pass', $security ),
            'declarations' => array_slice( $declarations, 0, 120 ),
        );
    }

    private static function next_token( $tokens, $offset ) {
        for ( $i = $offset + 1, $n = count( $tokens ); $i < $n; ++$i ) {
            if ( ! is_array( $tokens[ $i ] ) || ! in_array(
                $tokens[ $i ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true
            ) ) {
                return $i;
            }
        }
        return null;
    }

    private static function is_function_call( $tokens, $offset ) {
        $next = self::next_token( $tokens, $offset );
        if ( null === $next || '(' !== $tokens[ $next ] ) {
            return false;
        }
        $prev = $offset - 1;
        while ( $prev >= 0 && is_array( $tokens[ $prev ] ) && T_WHITESPACE === $tokens[ $prev ][0] ) {
            --$prev;
        }
        return $prev < 0 || ! is_array( $tokens[ $prev ] )
            || ! in_array( $tokens[ $prev ][0], array( T_FUNCTION, T_OBJECT_OPERATOR, T_DOUBLE_COLON ), true );
    }

    public static function step( $state ) {
        if ( 'running' !== $state['status'] ) {
            return $state;
        }
        $speeds = self::speeds();
        $batch = $speeds[ $state['speed'] ]['size'] ?? 2;
        $total = count( $state['files'] );
        for ( $i = 0; $i < $batch && $state['cursor'] < $total; ++$i ) {
            $file = $state['files'][ $state['cursor'] ];
            try {
                $report = self::analyze( $file );
                foreach ( $report['declarations'] as $symbol ) {
                    $key = $symbol['kind'] . ':' . $symbol['name'];
                    if ( isset( $state['symbols'][ $key ] ) ) {
                        $other = $state['symbols'][ $key ];
                        $report['wordpress']['evidence'][] = array(
                            'rule' => 'review_duplicate_symbol',
                            'symbol' => $symbol['name'],
                            'line' => $symbol['line'],
                            'other_file' => $other['file'],
                        );
                        $report['wordpress']['status'] = 'warning';
                    } else {
                        $state['symbols'][ $key ] = array( 'file' => $file, 'line' => $symbol['line'] );
                    }
                }
                unset( $report['declarations'] );
                $state['results'][ $file ] = $report;
            } catch ( Throwable $error ) {
                $unknown = self::finding( 'skip', array( array( 'rule' => 'analysis_incomplete' ) ) );
                $state['results'][ $file ] = array( 'php' => $unknown, 'wordpress' => $unknown, 'security' => $unknown );
            }
            ++$state['cursor'];
        }
        if ( $state['cursor'] >= $total ) {
            $state['status'] = 'completed';
        }
        $state['summary'] = self::summary( $state );
        $state['updated_at'] = gmdate( 'c' );
        return $state;
    }

    /**
     * Summarise actual findings, not the number of scanned PHP files.
     *
     * A PHP syntax pass must never conceal WordPress/security review warnings.
     * An incomplete inventory cannot be presented as an overall pass.
     *
     * @param array $state Stored Code Check state.
     * @return array Summary of the checked files and visible findings.
     */
    public static function summary( $state ) {
        $summary = array(
            'files_scanned' => 0,
            'files_total' => count( $state['files'] ?? array() ),
            'findings' => 0,
            'rules' => array(),
            'by_group' => array( 'php' => 0, 'wordpress' => 0, 'security' => 0 ),
            'failed' => 0,
            'skipped' => 0,
            'review_files' => 0,
            'status' => 'incomplete',
        );
        foreach ( $state['results'] ?? array() as $checks ) {
            ++$summary['files_scanned'];
            $flagged = false;
            foreach ( array( 'php', 'wordpress', 'security' ) as $group ) {
                $item = $checks[ $group ] ?? array();
                if ( 'fail' === ( $item['status'] ?? '' ) ) {
                    ++$summary['failed'];
                    $flagged = true;
                }
                if ( 'skip' === ( $item['status'] ?? '' ) ) {
                    ++$summary['skipped'];
                }
                foreach ( $item['evidence'] ?? array() as $evidence ) {
                    if ( ! isset( $evidence['rule'] ) ) {
                        continue;
                    }
                    ++$summary['findings'];
                    ++$summary['by_group'][ $group ];
                    $rule = (string) $evidence['rule'];
                    $summary['rules'][ $rule ] = ( $summary['rules'][ $rule ] ?? 0 ) + 1;
                    $flagged = true;
                }
            }
            if ( $flagged ) {
                ++$summary['review_files'];
            }
        }
        ksort( $summary['rules'] );
        $summary['distinct_rules'] = count( $summary['rules'] );
        if ( $summary['failed'] ) {
            $summary['status'] = 'fail';
        } elseif ( 'completed' === ( $state['status'] ?? '' )
            && ! $summary['skipped']
            && ! empty( $summary['files_total'] )
            && $summary['files_scanned'] === $summary['files_total']
            && empty( $state['inventory_truncated'] ) ) {
            $summary['status'] = $summary['findings'] ? 'review' : 'pass';
        }
        return $summary;
    }

    /**
     * Group findings into actionable levels without changing legacy results.
     * A warning is not promoted to an error without confirmed evidence.
     *
     * @param array $state The existing local Code Check state.
     * @return array Segmented entries for UI and JSON export.
     */
    public static function classified_findings( $state ) {
        $sections = array(
            'errors' => array(),
            'warnings' => array(),
            'clean' => array(),
            'unchecked' => array(),
        );
        foreach ( $state['results'] ?? array() as $file => $checks ) {
            $file_has_findings = false;
            $file_incomplete = false;
            foreach ( array( 'php', 'wordpress', 'security' ) as $group ) {
                $item = $checks[ $group ] ?? array( 'status' => 'skip', 'evidence' => array() );
                $status = $item['status'] ?? 'skip';
                $evidence = isset( $item['evidence'] ) && is_array( $item['evidence'] ) ? $item['evidence'] : array();
                if ( 'skip' === $status ) {
                    $file_incomplete = true;
                    $sections['unchecked'][] = array( 'file' => $file, 'group' => $group, 'status' => 'unchecked', 'reason' => 'not_checked' );
                }
                foreach ( $evidence as $finding ) {
                    if ( empty( $finding['rule'] ) ) {
                        continue;
                    }
                    $file_has_findings = true;
                    $severity = 'fail' === $status || in_array( $finding['severity'] ?? '', array( 'error', 'fail' ), true )
                        || 'php_parse_error' === $finding['rule'] ? 'error' : 'warning';
                    $entry = array(
                        'file' => (string) $file,
                        'line' => isset( $finding['line'] ) ? absint( $finding['line'] ) : null,
                        'group' => $group,
                        'rule' => (string) $finding['rule'],
                        'severity' => $severity,
                        'description' => self::finding_description( (string) $finding['rule'] ),
                        'recommendation' => self::finding_recommendation( (string) $finding['rule'] ),
                        'origin' => isset( $finding['origin'] ) ? (string) $finding['origin'] : 'native_code_check',
                    );
                    $sections[ 'error' === $severity ? 'errors' : 'warnings' ][] = $entry;
                }
                if ( 'fail' === $status && ! $evidence ) {
                    $file_has_findings = true;
                    $sections['errors'][] = array(
                        'file' => (string) $file,
                        'line' => null,
                        'group' => $group,
                        'rule' => 'check_failed_without_evidence',
                        'severity' => 'error',
                        'description' => __( 'The check failed but returned no source location.', 'taxocommerce-templates' ),
                        'recommendation' => __( 'Inspect this check and rerun the analysis.', 'taxocommerce-templates' ),
                        'origin' => 'native_code_check',
                    );
                }
            }
            if ( ! $file_has_findings && ! $file_incomplete ) {
                $sections['clean'][] = array(
                    'file' => (string) $file,
                    'status' => 'pass',
                    'description' => __( 'No findings in the checks implemented for this file.', 'taxocommerce-templates' ),
                );
            }
        }
        foreach ( (array) ( $state['files'] ?? array() ) as $file ) {
            if ( ! isset( $state['results'][ $file ] ) ) {
                $sections['unchecked'][] = array( 'file' => (string) $file, 'group' => 'all', 'status' => 'unchecked', 'reason' => 'not_scanned' );
            }
        }
        if ( ! empty( $state['inventory_truncated'] ) ) {
            $sections['unchecked'][] = array( 'file' => '', 'group' => 'all', 'status' => 'unchecked', 'reason' => 'inventory_truncated' );
        }
        $sections['counts'] = array(
            'errors' => count( $sections['errors'] ),
            'warnings' => count( $sections['warnings'] ),
            'clean' => count( $sections['clean'] ),
            'unchecked' => count( $sections['unchecked'] ),
        );
        return $sections;
    }

    /** Translate the known finding category; retain original rule for diagnostics. */
    private static function finding_description( $rule ) {
        $labels = array(
            'review_plugin_installation' => __( 'Plugin installation or upgrade operation needs review.', 'taxocommerce-templates' ),
            'review_plugin_lifecycle_activate_plugin' => __( 'Plugin activation must require an explicit user action.', 'taxocommerce-templates' ),
            'review_plugin_lifecycle_deactivate_plugins' => __( 'Plugin deactivation must require an explicit user action.', 'taxocommerce-templates' ),
            'review_request_nonce' => __( 'Request input needs a verified nonce in its execution path.', 'taxocommerce-templates' ),
            'review_request_capability' => __( 'Request handler needs an appropriate user permission check.', 'taxocommerce-templates' ),
            'review_request_sanitization' => __( 'Request input needs contextual sanitization and validation.', 'taxocommerce-templates' ),
            'review_file_upload' => __( 'File upload requires validation and safe handling.', 'taxocommerce-templates' ),
            'review_upload_tmp_name' => __( 'Uploaded temporary file path needs secure validation.', 'taxocommerce-templates' ),
            'review_dynamic_inline_style' => __( 'Dynamic CSS needs context-specific validation.', 'taxocommerce-templates' ),
            'review_unescaped_output' => __( 'Output variable needs context-appropriate escaping.', 'taxocommerce-templates' ),
            'review_nonprefixed_hook' => __( 'Custom WordPress hook is missing the plugin-specific prefix.', 'taxocommerce-templates' ),
            'review_unclosed_output_buffer' => __( 'Output buffer may not be closed in the same execution flow.', 'taxocommerce-templates' ),
            'review_output_buffer_flow' => __( 'An early exit may bypass output buffer closure.', 'taxocommerce-templates' ),
            'review_dynamic_sql_query' => __( 'Dynamic SQL query requires validation of identifiers and parameters.', 'taxocommerce-templates' ),
            'review_sql_preparation' => __( 'SQL construction and prepared statements need review.', 'taxocommerce-templates' ),
            'review_ajax_nonce' => __( 'AJAX handler may be missing nonce verification.', 'taxocommerce-templates' ),
            'missing_abspath_guard' => __( 'Direct-file access protection needs review.', 'taxocommerce-templates' ),
            'php_parse_error' => __( 'PHP syntax error.', 'taxocommerce-templates' ),
        );
        return $labels[ $rule ] ?? __( 'Review the flagged code for compliance with WordPress requirements.', 'taxocommerce-templates' );
    }

    /** Guidance is a review action, not proof that the code is defective. */
    private static function finding_recommendation( $rule ) {
        if ( false !== strpos( $rule, 'plugin_lifecycle' ) || 'review_plugin_installation' === $rule ) {
            return __( 'Confirm an explicit administrator action and WordPress permissions; otherwise use native WordPress plugin management.', 'taxocommerce-templates' );
        }
        if ( false !== strpos( $rule, 'nonce' ) || false !== strpos( $rule, 'capability' ) ) {
            return __( 'Verify the nonce and capability along the actual input-handling path.', 'taxocommerce-templates' );
        }
        if ( false !== strpos( $rule, 'sanitization' ) || false !== strpos( $rule, 'upload' ) || false !== strpos( $rule, 'file_' ) ) {
            return __( 'Validate the input type, sanitize values and check uploads with WordPress APIs.', 'taxocommerce-templates' );
        }
        if ( false !== strpos( $rule, 'sql' ) ) {
            return __( 'Check SQL parameters and use $wpdb->prepare() for untrusted values.', 'taxocommerce-templates' );
        }
        if ( false !== strpos( $rule, 'output' ) || false !== strpos( $rule, 'escape' ) || false !== strpos( $rule, 'inline_style' ) ) {
            return __( 'Inspect the execution context and escape output at the point of rendering.', 'taxocommerce-templates' );
        }
        if ( false !== strpos( $rule, 'hook' ) || false !== strpos( $rule, 'generic_' ) ) {
            return __( 'Use a unique plugin-specific prefix; preserve compatibility through reviewed migration.', 'taxocommerce-templates' );
        }
        if ( false !== strpos( $rule, 'buffer' ) ) {
            return __( 'Ensure every ob_start() closes in the same logical flow, including error paths.', 'taxocommerce-templates' );
        }
        if ( 'php_parse_error' === $rule ) {
            return __( 'Correct the PHP syntax and rerun the check.', 'taxocommerce-templates' );
        }
        return __( 'Inspect the source line and validate whether this warning requires a code change.', 'taxocommerce-templates' );
    }

    /** Render one accessible severity marker; colour alone is never the label. */
    private static function severity_dot( $status, $label ) {
        echo '<span class="tc-cc-indicator tc-cc-indicator--' . esc_attr( $status ) . '"><span class="tc-cc-dot" aria-hidden="true"></span>' . esc_html( $label ) . '</span>';
    }

    /** Render sections in the same order as the segmented JSON. */
    private static function render_classified( $state ) {
        $data = self::classified_findings( $state );
        $sections = array(
            'errors' => array( 'error', __( 'Errors to fix', 'taxocommerce-templates' ), __( 'No confirmed errors in the implemented checks.', 'taxocommerce-templates' ) ),
            'warnings' => array( 'warning', __( 'Warnings to review', 'taxocommerce-templates' ), __( 'No warnings detected.', 'taxocommerce-templates' ) ),
            'clean' => array( 'pass', __( 'Files without findings', 'taxocommerce-templates' ), __( 'No fully checked clean files yet.', 'taxocommerce-templates' ) ),
            'unchecked' => array( 'unchecked', __( 'Unchecked or incomplete', 'taxocommerce-templates' ), __( 'All inventoried files have been checked.', 'taxocommerce-templates' ) ),
        );
        echo '<div class="tc-cc-report">';
        foreach ( $sections as $name => $meta ) {
            $items = $data[ $name ];
            echo '<section class="tc-cc-section tc-cc-section--' . esc_attr( $meta[0] ) . '">';
            echo '<h3>';
            self::severity_dot( $meta[0], $meta[1] );
            echo ' <span class="tc-cc-count">(' . esc_html( (string) count( $items ) ) . ')</span></h3>';
            if ( ! $items ) {
                echo '<p>' . esc_html( $meta[2] ) . '</p>';
            } else {
                echo '<div class="tc-cc-entries">';
                foreach ( $items as $item ) {
                    echo '<div class="tc-cc-entry"><strong>' . esc_html( $item['file'] ?: __( 'Inventory', 'taxocommerce-templates' ) ) . '</strong>';
                    if ( isset( $item['line'] ) && null !== $item['line'] ) {
                        echo ' <span>' . esc_html__( 'Line', 'taxocommerce-templates' ) . ' ' . esc_html( (string) $item['line'] ) . '</span>';
                    }
                    if ( ! empty( $item['rule'] ) ) {
                        echo ' <code>' . esc_html( $item['rule'] ) . '</code>';
                    }
                    echo '<p>' . esc_html( $item['description'] ?? __( 'Not checked.', 'taxocommerce-templates' ) ) . '</p>';
                    if ( ! empty( $item['recommendation'] ) ) {
                        echo '<p><strong>' . esc_html__( 'Recommended action:', 'taxocommerce-templates' ) . '</strong> ' . esc_html( $item['recommendation'] ) . '</p>';
                    }
                    echo '</div>';
                }
                echo '</div>';
            }
            echo '</section>';
        }
        echo '</div>';
    }

    public static function handle() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Access denied.', 'taxocommerce-templates' ) );
        }
        check_admin_referer( self::ACTION );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked above.
        $op = isset( $_POST['operation'] ) && is_scalar( $_POST['operation'] )
            ? sanitize_key( wp_unslash( $_POST['operation'] ) ) : '';
        $state = self::state();
        if ( 'start' === $op ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked above.
            $requested = isset( $_POST['speed'] ) && is_scalar( $_POST['speed'] )
                ? absint( wp_unslash( $_POST['speed'] ) ) : 3;
            $speeds = self::speeds();
            $files = self::files();
            $state = array(
                'schema' => 1,
                'plugin' => self::DOMAIN,
                'timestamp' => gmdate( 'c' ),
                'status' => $files ? 'running' : 'completed',
                'speed' => isset( $speeds[ $requested ] ) ? $requested : 3,
                'cursor' => 0,
                'files' => $files,
                'symbols' => array(),
                'results' => array(),
                'native_static_review' => 'enabled',
                'native_rules_revision' => 1,
                'official_pcp' => 'not_executed',
                'runtime_security' => 'not_verified',
                'older_php_versions' => 'not_tested',
                'inventory_truncated' => count( $files ) >= 250,
            );
        } elseif ( is_array( $state ) && 'stop' === $op ) {
            $state['status'] = 'paused';
        } elseif ( is_array( $state ) && 'resume' === $op && 'paused' === $state['status'] ) {
            $state['status'] = 'running';
        } elseif ( is_array( $state ) && 'step' === $op ) {
            $state = self::step( $state );
        }
        if ( is_array( $state ) ) {
            set_transient( self::key(), $state, self::TTL );
        }
        $redirect = wp_get_referer();
        wp_safe_redirect(
            add_query_arg(
                'tc_autotest_section',
                'code',
                $redirect ? $redirect : admin_url( 'plugins.php' )
            )
        );
        exit;
    }

    public static function export() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Access denied.', 'taxocommerce-templates' ) );
        }
        check_admin_referer( self::EXPORT_ACTION );
        $state = self::state();
        if ( ! is_array( $state ) || ! $state['results'] ) {
            wp_die( esc_html__( 'Run Code Check first.', 'taxocommerce-templates' ) );
        }
        $state['classified_findings'] = self::classified_findings( $state );
        $state['summary'] = self::summary( $state );
        $json = wp_json_encode( $state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        if ( ! is_string( $json ) ) {
            wp_die( esc_html__( 'Unable to create JSON.', 'taxocommerce-templates' ) );
        }
        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'X-Content-Type-Options: nosniff' );
        header( 'Content-Disposition: attachment; filename="taxocommerce-templates-code-check-' . gmdate( 'Ymd-His' ) . '.json"' );
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download, no HTML.
        echo $json;
        exit;
    }

    /**
     * Render two sub-tabs and return true only when Code Check takes over.
     * Existing functional Autotest is never run unless explicitly selected.
     */
    public static function render_subtabs() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Access denied.', 'taxocommerce-templates' ) );
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab selection.
        $code = isset( $_GET['tc_autotest_section'] ) && 'code' === sanitize_key( wp_unslash( $_GET['tc_autotest_section'] ) );
        $base = remove_query_arg( 'tc_autotest_section' );
        echo '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'Autotest sections', 'taxocommerce-templates' ) . '">';
        echo '<a class="nav-tab' . ( $code ? '' : ' nav-tab-active' ) . '" href="' . esc_url( $base ) . '">'
            . esc_html__( 'Functionality Check', 'taxocommerce-templates' ) . '</a>';
        echo '<a class="nav-tab' . ( $code ? ' nav-tab-active' : '' ) . '" href="'
            . esc_url( add_query_arg( 'tc_autotest_section', 'code', $base ) ) . '">'
            . esc_html__( 'Code Check', 'taxocommerce-templates' ) . '</a></nav>';
        if ( $code ) {
            self::render();
        }
        return $code;
    }

    private static function button( $operation, $label, $primary = false ) {
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';
        echo '<input type="hidden" name="operation" value="' . esc_attr( $operation ) . '">';
        wp_nonce_field( self::ACTION );
        echo '<button type="submit" class="button ' . ( $primary ? 'button-primary' : 'button-secondary' ) . '">'
            . esc_html( $label ) . '</button></form>';
    }

    public static function render() {
        $state = self::state();
        $speeds = self::speeds();
        echo '<section class="wrap tc-autotest-code tcc-family-panel">';
        echo '<h2>' . esc_html__( 'Code Check', 'taxocommerce-templates' ) . '</h2>';
        echo '<p>' . esc_html__( 'PHP syntax, WordPress structure and security indicators for this plugin only. No other checker is required. Findings do not replace official Plugin Check or a security audit.', 'taxocommerce-templates' ) . '</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '"><input type="hidden" name="operation" value="start">';
        wp_nonce_field( self::ACTION );
        echo '<label for="tc-autotest-code-speed">' . esc_html__( 'CPU usage', 'taxocommerce-templates' ) . '</label> ';
        echo '<select id="tc-autotest-code-speed" name="speed">';
        foreach ( $speeds as $value => $config ) {
            echo '<option value="' . esc_attr( (string) $value ) . '"'
                . selected( $value, is_array( $state ) ? (int) $state['speed'] : 3, false ) . '>'
                . esc_html( (string) $value ) . '</option>';
        }
        echo '</select> ';
        submit_button( __( 'Run Code Check', 'taxocommerce-templates' ), 'primary', 'submit', false );
        echo '</form>';
        if ( is_array( $state ) ) {
            $summary = self::summary( $state );
            $label = 'fail' === $summary['status']
                ? __( 'Errors detected', 'taxocommerce-templates' )
                : ( 'review' === $summary['status']
                    ? __( 'Review required: warnings found', 'taxocommerce-templates' )
                    : ( 'pass' === $summary['status']
                        ? __( 'No findings in implemented checks (not a WordPress.org certification)', 'taxocommerce-templates' )
                        : __( 'Incomplete: not all files were checked', 'taxocommerce-templates' ) ) );
            echo '<div class="notice notice-' . ( 'fail' === $summary['status'] ? 'error' : 'warning' ) . ' inline"><p><strong>'
                . esc_html( $label ) . '</strong></p>';
            echo '<p>' . esc_html__( 'Files analysed:', 'taxocommerce-templates' ) . ' ' . esc_html( (string) $summary['files_scanned'] )
                . '/' . esc_html( (string) $summary['files_total'] ) . ' · '
                . esc_html__( 'Findings:', 'taxocommerce-templates' ) . ' ' . esc_html( (string) $summary['findings'] ) . ' · '
                . esc_html__( 'Distinct rules:', 'taxocommerce-templates' ) . ' ' . esc_html( (string) $summary['distinct_rules'] ) . '</p>';
            echo '<p>PHP: ' . esc_html( (string) $summary['by_group']['php'] )
                . ' · WordPress: ' . esc_html( (string) $summary['by_group']['wordpress'] )
                . ' · ' . esc_html__( 'Security:', 'taxocommerce-templates' ) . ' ' . esc_html( (string) $summary['by_group']['security'] ) . '</p>';
            if ( $summary['rules'] ) {
                echo '<details><summary>' . esc_html__( 'All rules with findings', 'taxocommerce-templates' ) . '</summary><ul>';
                foreach ( $summary['rules'] as $rule => $occurrences ) {
                    echo '<li><code>' . esc_html( $rule ) . '</code> (' . esc_html( (string) $occurrences ) . ')</li>';
                }
                echo '</ul></details>';
            }
            echo '<p>' . esc_html__( 'Native static indicators only. Official Plugin Check, live security and older PHP versions have not been executed here.', 'taxocommerce-templates' ) . '</p></div>';
            echo '<p>' . esc_html__( 'Progress', 'taxocommerce-templates' ) . ': '
                . esc_html( (string) $state['cursor'] ) . '/' . esc_html( (string) count( $state['files'] ) )
                . ' · ' . esc_html( $state['status'] ) . '</p>';
            if ( 'running' === $state['status'] ) {
                self::button( 'stop', __( 'Stop', 'taxocommerce-templates' ) );
                echo '<form id="tc-autotest-code-step" data-delay="'
                    . esc_attr( (string) $speeds[ $state['speed'] ]['delay'] )
                    . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
                echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';
                echo '<input type="hidden" name="operation" value="step">';
                wp_nonce_field( self::ACTION );
                echo '<noscript><button type="submit" class="button">' . esc_html__( 'Continue batch', 'taxocommerce-templates' ) . '</button></noscript></form>';
            } elseif ( 'paused' === $state['status'] ) {
                self::button( 'resume', __( 'Resume', 'taxocommerce-templates' ), true );
            }
            if ( $state['results'] ) {
                self::render_classified( $state );
                echo '<details class="tc-cc-legacy"><summary>' . esc_html__( 'Detailed results by file (technical view)', 'taxocommerce-templates' ) . '</summary>';
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
                echo '<input type="hidden" name="action" value="' . esc_attr( self::EXPORT_ACTION ) . '">';
                wp_nonce_field( self::EXPORT_ACTION );
                submit_button( __( 'Download JSON', 'taxocommerce-templates' ), 'secondary', 'submit', false );
                echo '</form>';
                echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'File', 'taxocommerce-templates' )
                    . '</th><th>PHP</th><th>WordPress</th><th>' . esc_html__( 'Security', 'taxocommerce-templates' ) . '</th></tr></thead><tbody>';
                foreach ( $state['results'] as $file => $checks ) {
                    echo '<tr><th scope="row">' . esc_html( $file ) . '</th>';
                    foreach ( array( 'php', 'wordpress', 'security' ) as $section ) {
                        $item = $checks[ $section ] ?? array( 'status' => 'skip', 'evidence' => array() );
                        echo '<td><strong>' . esc_html( $item['status'] ) . '</strong>';
                        foreach ( $item['evidence'] as $finding ) {
                            $rule = isset( $finding['rule'] ) ? $finding['rule'] : 'unknown';
                            echo '<div><code>' . esc_html( $rule ) . '</code>';
                            if ( isset( $finding['origin'] ) ) {
                                echo ' <small>(' . esc_html( $finding['origin'] ) . ')</small>';
                            }
                            if ( isset( $finding['line'] ) ) {
                                echo ' #' . esc_html( (string) absint( $finding['line'] ) );
                            }
                            echo '</div>';
                        }
                        echo '</td>';
                    }
                    echo '</tr>';
                }
                echo '</tbody></table></details>';
            }
        }
        echo '</section>';
    }
}
