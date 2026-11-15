<?php
/**
 * TaxoCommerce System Check: bounded self-only source code diagnostics.
 *
 * No dependency on WordPress Plugin Check, CLI tools or other plugins.
 * This scanner checks only files inside its own installed plugin directory.
 *
 * @package TaxoCommerceSystemCheck
 */
defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-taxocommerce-system-check-code-review-rules.php';

final class TaxoCommerce_System_Check_Code_Check {
    const ACTION = 'taxocommerce_system_check_code_check';
    const EXPORT_ACTION = 'taxocommerce_system_check_code_check_json';
    const TTL = HOUR_IN_SECONDS;

    public static function speeds() {
        return array(
            1 => array( 'batch' => 1, 'delay' => 5000, 'label' => __( 'Very low', 'taxocommerce-system-check' ) ),
            2 => array( 'batch' => 1, 'delay' => 3000, 'label' => __( 'Low', 'taxocommerce-system-check' ) ),
            3 => array( 'batch' => 2, 'delay' => 2000, 'label' => __( 'Normal', 'taxocommerce-system-check' ) ),
            4 => array( 'batch' => 3, 'delay' => 1250, 'label' => __( 'High', 'taxocommerce-system-check' ) ),
            5 => array( 'batch' => 4, 'delay' => 800, 'label' => __( 'Maximum', 'taxocommerce-system-check' ) ),
        );
    }

    public static function boot() {
        add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
        add_action( 'admin_post_' . self::EXPORT_ACTION, array( __CLASS__, 'export' ) );
    }

    private static function state_key() {
        return 'tcs_code_check_' . get_current_blog_id() . '_' . get_current_user_id();
    }

    private static function state() {
        $value = get_transient( self::state_key() );
        return is_array( $value ) && isset( $value['files'], $value['results'], $value['cursor'], $value['status'] )
            ? $value : false;
    }

    /**
     * Enumerate only System Check's own source, never its neighbour plugins.
     * Ignore symlinks and limit the number of entries to bound directory work.
     */
    public static function files() {
        $root = realpath( TAXOCOMMERCE_SYSTEM_CHECK_PATH );
        if ( false === $root || ! is_dir( $root ) ) {
            return array();
        }
        $relative = array();
        $directory = new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS );
        $iterator = new RecursiveIteratorIterator( $directory );
        foreach ( $iterator as $entry ) {
            if ( count( $relative ) >= 300 ) {
                break;
            }
            if ( $entry->isLink() || ! $entry->isFile() || 'php' !== strtolower( $entry->getExtension() ) ) {
                continue;
            }
            $path = realpath( $entry->getPathname() );
            if ( false === $path || 0 !== strpos( $path, $root . DIRECTORY_SEPARATOR ) ) {
                continue;
            }
            $relative[] = str_replace( DIRECTORY_SEPARATOR, '/', substr( $path, strlen( $root ) + 1 ) );
        }
        sort( $relative, SORT_STRING );
        return $relative;
    }

    private static function finding( $status, $message, $evidence = array() ) {
        return array(
            'status'   => $status,
            'message'  => $message,
            'evidence' => $evidence,
        );
    }

    /**
     * Native PHP parser checks syntax without executing or spawning PHP.
     * Valid only for the current server PHP version, not every older PHP.
     */
    public static function php_check( $source ) {
        if ( ! defined( 'TOKEN_PARSE' ) ) {
            return self::finding( 'skip', __( 'PHP parser mode is not available on this server.', 'taxocommerce-system-check' ) );
        }
        try {
            token_get_all( $source, TOKEN_PARSE );
        } catch ( ParseError $error ) {
            return self::finding(
                'fail',
                __( 'PHP syntax error detected by the local parser.', 'taxocommerce-system-check' ),
                array( 'line' => absint( $error->getLine() ), 'php_version' => PHP_VERSION )
            );
        }
        return self::finding(
            'pass',
            __( 'PHP syntax accepted by this server version. Older versions are not tested.', 'taxocommerce-system-check' ),
            array( 'php_version' => PHP_VERSION, 'compatibility_tested' => false )
        );
    }

    /**
     * Focused WordPress structure conventions, not official Plugin Check.
     * Never treat nonce/sanitization/escaping as verified from regex matching.
     */
    public static function wordpress_check( $source, $relative ) {
        $checks = array();
        $checks['ABSPATH guard'] = (bool) preg_match(
            '/(?:defined\s*\(\s*[\x27\x22]ABSPATH[\x27\x22]\s*\)|\bABSPATH\b)/',
            $source
        );
        if ( 'taxocommerce-system-check.php' === $relative ) {
            foreach ( array( 'Plugin Name', 'Version', 'Text Domain', 'Requires at least', 'Requires PHP', 'License' ) as $field ) {
                $checks[ $field ] = (bool) preg_match( '/^\s*\*\s*' . preg_quote( $field, '/' ) . ':\s*\S+/mi', $source );
            }
            $checks['Text Domain match'] = (bool) preg_match(
                '/^\s*\*\s*Text Domain:\s*taxocommerce-system-check\s*$/mi',
                $source
            );
            $checks['readme.txt'] = is_readable( TAXOCOMMERCE_SYSTEM_CHECK_PATH . 'readme.txt' );
        }
        $missing = array();
        foreach ( $checks as $label => $ok ) {
            if ( ! $ok ) {
                $missing[] = $label;
            }
        }
        return self::finding(
            $missing ? 'warning' : 'pass',
            $missing
                ? __( 'Some WordPress structure conventions need review.', 'taxocommerce-system-check' )
                : __( 'The inspected WordPress structure conventions are present.', 'taxocommerce-system-check' ),
            array(
                'checks' => $checks,
                'missing' => $missing,
                'not_verified' => array( 'nonce_flow', 'authorization_flow', 'sanitization', 'output_escaping', 'wordpress_org_full_rules' ),
            )
        );
    }

    /**
     * Detect direct use of dangerous PHP call tokens, excluding comments and
     * text literals. Findings require human review; no code is executed.
     */
    public static function security_check( $source ) {
        $tokens = token_get_all( $source );
        $bad = array();
        $names = array( 'exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen', 'unserialize' );
        $count = count( $tokens );
        for ( $i = 0; $i < $count; ++$i ) {
            $token = $tokens[ $i ];
            if ( ! is_array( $token ) ) {
                continue;
            }
            if ( defined( 'T_EVAL' ) && T_EVAL === $token[0] ) {
                $bad[] = array( 'function' => 'eval', 'line' => $token[2] );
                continue;
            }
            if ( T_STRING !== $token[0] || ! in_array( strtolower( $token[1] ), $names, true ) ) {
                continue;
            }
            $next = $i + 1;
            while ( $next < $count && is_array( $tokens[ $next ] )
                && in_array( $tokens[ $next ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
                ++$next;
            }
            if ( $next >= $count || '(' !== $tokens[ $next ] ) {
                continue;
            }
            // Ignore method calls; a token name alone does not prove a PHP function.
            $previous = $i - 1;
            while ( $previous >= 0 && is_array( $tokens[ $previous ] )
                && in_array( $tokens[ $previous ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
                --$previous;
            }
            if ( $previous >= 0 && is_array( $tokens[ $previous ] )
                && in_array( $tokens[ $previous ][0], array( T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION ), true ) ) {
                continue;
            }
            $bad[] = array( 'function' => strtolower( $token[1] ), 'line' => $token[2] );
        }
        return self::finding(
            $bad ? 'warning' : 'pass',
            $bad
                ? __( 'Potentially dangerous function calls need manual review.', 'taxocommerce-system-check' )
                : __( 'No blocked function calls found; this does not certify security.', 'taxocommerce-system-check' ),
            array(
                'calls' => $bad,
                'not_verified' => array( 'injection_analysis', 'nonce_flow', 'capability_checks', 'escaping', 'dependency_vulnerabilities' ),
            )
        );
    }

    public static function check_file( $relative ) {
        $path = realpath( TAXOCOMMERCE_SYSTEM_CHECK_PATH . $relative );
        $root = realpath( TAXOCOMMERCE_SYSTEM_CHECK_PATH );
        if ( false === $root || false === $path
            || 0 !== strpos( $path, $root . DIRECTORY_SEPARATOR )
            || ! is_file( $path ) || ! is_readable( $path ) || 'php' !== strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
            return array(
                'php' => self::finding( 'skip', __( 'File unavailable or outside System Check.', 'taxocommerce-system-check' ) ),
                'wordpress' => self::finding( 'skip', __( 'Not checked.', 'taxocommerce-system-check' ) ),
                'security' => self::finding( 'skip', __( 'Not checked.', 'taxocommerce-system-check' ) ),
            );
        }
        $length = filesize( $path );
        if ( false === $length || $length > 1048576 ) {
            $skip = self::finding( 'skip', __( 'File exceeds the 1 MB read limit.', 'taxocommerce-system-check' ) );
            return array( 'php' => $skip, 'wordpress' => $skip, 'security' => $skip );
        }
        $source = file_get_contents( $path );
        if ( ! is_string( $source ) ) {
            $skip = self::finding( 'skip', __( 'Unable to read the file.', 'taxocommerce-system-check' ) );
            return array( 'php' => $skip, 'wordpress' => $skip, 'security' => $skip );
        }
        $php = self::php_check( $source );
        if ( 'pass' === $php['status'] ) {
            $compatibility = TaxoCommerce_System_Check_Code_Rules::compatibility( $source );
            if ( $compatibility ) {
                $php['status'] = 'warning';
                $php['message'] = __( 'This code may use features newer than the declared minimum PHP version.', 'taxocommerce-system-check' );
                $php['evidence']['compatibility'] = $compatibility;
            }
        }
        $wordpress = self::wordpress_check( $source, $relative );
        $structure = TaxoCommerce_System_Check_Code_Rules::structure( $source, $relative );
        $wordpress['evidence']['findings'] = $structure['findings'];
        $wordpress['evidence']['declarations'] = $structure['declarations'];
        $wordpress['evidence']['unverified_includes'] = $structure['unverified'];
        $wordpress['evidence']['findings_truncated'] = $structure['findings_truncated'];
        $wordpress['evidence']['declarations_truncated'] = $structure['declarations_truncated'];
        if ( $structure['findings'] || $structure['unverified'] || $structure['findings_truncated'] || $structure['declarations_truncated'] ) {
            $wordpress['status'] = 'warning';
            $wordpress['message'] = __( 'Code references and declarations require review.', 'taxocommerce-system-check' );
            foreach ( $structure['findings'] as $finding ) {
                if ( 'fail' === $finding['severity'] ) {
                    $wordpress['status'] = 'fail';
                    break;
                }
            }
        }
        $security = self::security_check( $source );
        $security['evidence']['signals'] = TaxoCommerce_System_Check_Code_Rules::security_signals( $source );
        if ( $security['evidence']['signals'] ) {
            $security['status'] = 'warning';
            $security['message'] = __( 'Potential security issues need a manual code-flow review.', 'taxocommerce-system-check' );
        }
        // Core 0.4.82 rules are vendored locally: no dependency on Core.
        $native = TaxoCommerce_System_Check_Code_Review_Rules::scan( $source, $relative );
        foreach ( array( 'php', 'wordpress', 'security' ) as $group ) {
            if ( empty( $native[ $group ] ) ) {
                continue;
            }
            if ( 'php' === $group ) {
                $php['evidence']['native_review'] = $native[ $group ];
                if ( 'pass' === $php['status'] ) {
                    $php['status'] = 'warning';
                }
            } elseif ( 'wordpress' === $group ) {
                $wordpress['evidence']['native_review'] = $native[ $group ];
                if ( 'pass' === $wordpress['status'] ) {
                    $wordpress['status'] = 'warning';
                }
            } else {
                $security['evidence']['native_review'] = $native[ $group ];
                if ( 'pass' === $security['status'] ) {
                    $security['status'] = 'warning';
                }
            }
        }
        return array(
            'php' => $php,
            'wordpress' => $wordpress,
            'security' => $security,
        );
    }

    /**
     * Cross-file symbol index, updated one inspected file per step.
     * Duplicate declarations can collide at runtime if both are loaded.
     *
     * @param array  $state Current report.
     * @param string $file  Inspected relative file.
     * @return array Updated report.
     */
    public static function index_symbols( $state, $file ) {
        if ( ! isset( $state['symbols'] ) || ! is_array( $state['symbols'] ) ) {
            $state['symbols'] = array();
        }
        $declarations = $state['results'][ $file ]['wordpress']['evidence']['declarations'] ?? array();
        foreach ( $declarations as $symbol ) {
            $key = $symbol['type'] . ':' . $symbol['name'];
            if ( isset( $state['symbols'][ $key ] ) ) {
                $previous = $state['symbols'][ $key ];
                $finding = array(
                    'rule' => 'duplicate_symbol',
                    'severity' => 'review',
                    'symbol' => $symbol['display'],
                    'line' => (int) $symbol['line'],
                    'other_file' => $previous['file'],
                    'other_line' => (int) $previous['line'],
                );
                $state['results'][ $file ]['wordpress']['evidence']['findings'][] = $finding;
                $state['results'][ $file ]['wordpress']['status'] = 'warning';
                $state['results'][ $file ]['wordpress']['message'] = __( 'Possible duplicate PHP symbols need review.', 'taxocommerce-system-check' );
                if ( isset( $state['results'][ $previous['file'] ] ) ) {
                    $reverse = $finding;
                    $reverse['line'] = (int) $previous['line'];
                    $reverse['other_file'] = $file;
                    $reverse['other_line'] = (int) $symbol['line'];
                    $state['results'][ $previous['file'] ]['wordpress']['evidence']['findings'][] = $reverse;
                    if ( 'fail' !== $state['results'][ $previous['file'] ]['wordpress']['status'] ) {
                        $state['results'][ $previous['file'] ]['wordpress']['status'] = 'warning';
                    }
                }
            } else {
                $state['symbols'][ $key ] = array(
                    'file' => $file,
                    'line' => (int) $symbol['line'],
                );
            }
        }
        return $state;
    }

    public static function process( $state ) {
        if ( 'running' !== $state['status'] ) {
            return $state;
        }
        $speeds = self::speeds();
        $speed = isset( $speeds[ $state['speed'] ] ) ? $state['speed'] : 3;
        $batch = $speeds[ $speed ]['batch'];
        $total = count( $state['files'] );
        for ( $i = 0; $i < $batch && $state['cursor'] < $total; ++$i ) {
            $file = $state['files'][ $state['cursor'] ];
            try {
                $state['results'][ $file ] = self::check_file( $file );
                $state = self::index_symbols( $state, $file );
            } catch ( Throwable $error ) {
                $state['results'][ $file ] = array(
                    'php' => self::finding( 'warning', __( 'Unable to complete the PHP check.', 'taxocommerce-system-check' ) ),
                    'wordpress' => self::finding( 'skip', __( 'Not checked.', 'taxocommerce-system-check' ) ),
                    'security' => self::finding( 'skip', __( 'Not checked.', 'taxocommerce-system-check' ) ),
                );
            }
            ++$state['cursor'];
        }
        if ( $state['cursor'] >= $total ) {
            $state['status'] = 'completed';
        }
        $state['updated_at'] = gmdate( 'c' );
        return $state;
    }

    public static function handle() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Access denied.', 'taxocommerce-system-check' ) );
        }
        check_admin_referer( self::ACTION );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
        $operation = isset( $_POST['operation'] ) && is_string( $_POST['operation'] )
            ? sanitize_key( wp_unslash( $_POST['operation'] ) ) : '';
        $state = self::state();
        if ( 'start' === $operation ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
            $requested = isset( $_POST['speed'] ) && is_scalar( $_POST['speed'] )
                ? absint( wp_unslash( $_POST['speed'] ) ) : 3;
            $speeds = self::speeds();
            $speed = isset( $speeds[ $requested ] ) ? $requested : 3;
            $files = self::files();
            $state = array(
                'schema' => 1,
                'plugin' => 'taxocommerce-system-check',
                'version' => TAXOCOMMERCE_SYSTEM_CHECK_VERSION,
                'started_at' => gmdate( 'c' ),
                'updated_at' => gmdate( 'c' ),
                'status' => $files ? 'running' : 'completed',
                'speed' => $speed,
                'files' => $files,
                'cursor' => 0,
                'results' => array(),
                'checks' => array( 'php_parser', 'php_minimum_compatibility', 'wordpress_structure', 'literal_include_paths', 'duplicate_symbols', 'security_patterns', 'security_flow_heuristics' ),
                'symbols' => array(),
                'limitations' => array(
                    'official_plugin_check' => 'not_executed',
                    'runtime_activation' => 'not_executed',
                    'complete_dataflow_analysis' => 'not_verified',
                    'older_php_versions' => 'not_tested',
                    'file_inventory_limit' => count( $files ) >= 300 ? 'potentially_truncated' : 'not_reached',
                ),
                'official_plugin_check' => 'not_executed',
                'native_static_review' => 'enabled',
                'native_rules_revision' => 1,
                'inventory_truncated' => count( $files ) >= 300,
            );
        } elseif ( is_array( $state ) && 'stop' === $operation ) {
            $state['status'] = 'paused';
        } elseif ( is_array( $state ) && 'resume' === $operation && 'paused' === $state['status'] ) {
            $state['status'] = 'running';
        } elseif ( is_array( $state ) && 'step' === $operation ) {
            $state = self::process( $state );
        }
        if ( is_array( $state ) ) {
            set_transient( self::state_key(), $state, self::TTL );
        }
        wp_safe_redirect( taxocommerce_system_check_tab_url( 'auto-test', array( 'tcs_subtab' => 'code' ) ) );
        exit;
    }

    /** Count actual inspected results; skipped and review are never counted as passed. */
    public static function summary( $results ) {
        $counts = array();
        foreach ( array( 'php', 'wordpress', 'security' ) as $category ) {
            $counts[ $category ] = array( 'pass' => 0, 'fail' => 0, 'warning' => 0, 'skip' => 0 );
        }
        foreach ( $results as $result ) {
            foreach ( $counts as $category => $statuses ) {
                $status = isset( $result[ $category ]['status'] ) ? $result[ $category ]['status'] : 'skip';
                if ( ! isset( $counts[ $category ][ $status ] ) ) {
                    $status = 'skip';
                }
                ++$counts[ $category ][ $status ];
            }
        }
        return $counts;
    }

    /**
     * Project the System Check diagnostic evidence into Core's presentation
     * contract without removing existing detailed checks or their JSON fields.
     */
    private static function normalized_state( $state ) {
        $normalized = $state;
        $normalized['inventory_truncated'] = ! empty( $state['inventory_truncated'] )
            || 'potentially_truncated' === ( $state['limitations']['file_inventory_limit'] ?? '' );
        $normalized['results'] = array();
        foreach ( (array) ( $state['results'] ?? array() ) as $file => $checks ) {
            $row = array();
            foreach ( array( 'php', 'wordpress', 'security' ) as $group ) {
                $check = $checks[ $group ] ?? array();
                $status = $check['status'] ?? 'skip';
                $evidence = is_array( $check['evidence'] ?? null ) ? $check['evidence'] : array();
                $findings = array();
                foreach ( array( 'findings', 'compatibility', 'signals', 'native_review' ) as $name ) {
                    foreach ( (array) ( $evidence[ $name ] ?? array() ) as $item ) {
                        if ( is_array( $item ) && ! empty( $item['rule'] ) ) {
                            $findings[] = $item;
                        }
                    }
                }
                if ( 'php' === $group && 'fail' === $status && empty( $findings ) ) {
                    $findings[] = array( 'rule' => 'php_parse_error', 'line' => absint( $evidence['line'] ?? 0 ), 'severity' => 'error' );
                }
                if ( 'wordpress' === $group ) {
                    foreach ( (array) ( $evidence['missing'] ?? array() ) as $missing ) {
                        $findings[] = array( 'rule' => 'review_wp_structure', 'field' => (string) $missing );
                    }
                    foreach ( (array) ( $evidence['unverified_includes'] ?? array() ) as $item ) {
                        if ( is_array( $item ) && ! empty( $item['rule'] ) ) {
                            $findings[] = $item;
                        }
                    }
                }
                if ( 'security' === $group ) {
                    foreach ( (array) ( $evidence['calls'] ?? array() ) as $item ) {
                        if ( is_array( $item ) && isset( $item['function'] ) ) {
                            $findings[] = array( 'rule' => 'dangerous_call_' . strtolower( (string) $item['function'] ), 'line' => absint( $item['line'] ?? 0 ) );
                        }
                    }
                }
                if ( in_array( $status, array( 'fail', 'warning' ), true ) && empty( $findings ) ) {
                    $findings[] = array( 'rule' => 'review_incomplete_evidence' );
                }
                $row[ $group ] = array(
                    'status' => $status,
                    'evidence' => $findings,
                );
            }
            $normalized['results'][ $file ] = $row;
        }
        return $normalized;
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
    public static function summary_core( $state ) {
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
                        'description' => __( 'The check failed but returned no source location.', 'taxocommerce-system-check' ),
                        'recommendation' => __( 'Inspect this check and rerun the analysis.', 'taxocommerce-system-check' ),
                        'origin' => 'native_code_check',
                    );
                }
            }
            if ( ! $file_has_findings && ! $file_incomplete ) {
                $sections['clean'][] = array(
                    'file' => (string) $file,
                    'status' => 'pass',
                    'description' => __( 'No findings in the checks implemented for this file.', 'taxocommerce-system-check' ),
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
            'review_plugin_installation' => __( 'Plugin installation or upgrade operation needs review.', 'taxocommerce-system-check' ),
            'review_plugin_lifecycle_activate_plugin' => __( 'Plugin activation must require an explicit user action.', 'taxocommerce-system-check' ),
            'review_plugin_lifecycle_deactivate_plugins' => __( 'Plugin deactivation must require an explicit user action.', 'taxocommerce-system-check' ),
            'review_request_nonce' => __( 'Request input needs a verified nonce in its execution path.', 'taxocommerce-system-check' ),
            'review_request_capability' => __( 'Request handler needs an appropriate user permission check.', 'taxocommerce-system-check' ),
            'review_request_sanitization' => __( 'Request input needs contextual sanitization and validation.', 'taxocommerce-system-check' ),
            'review_file_upload' => __( 'File upload requires validation and safe handling.', 'taxocommerce-system-check' ),
            'review_upload_tmp_name' => __( 'Uploaded temporary file path needs secure validation.', 'taxocommerce-system-check' ),
            'review_dynamic_inline_style' => __( 'Dynamic CSS needs context-specific validation.', 'taxocommerce-system-check' ),
            'review_unescaped_output' => __( 'Output variable needs context-appropriate escaping.', 'taxocommerce-system-check' ),
            'review_nonprefixed_hook' => __( 'Custom WordPress hook is missing the plugin-specific prefix.', 'taxocommerce-system-check' ),
            'review_unclosed_output_buffer' => __( 'Output buffer may not be closed in the same execution flow.', 'taxocommerce-system-check' ),
            'review_output_buffer_flow' => __( 'An early exit may bypass output buffer closure.', 'taxocommerce-system-check' ),
            'review_dynamic_sql_query' => __( 'Dynamic SQL query requires validation of identifiers and parameters.', 'taxocommerce-system-check' ),
            'review_sql_preparation' => __( 'SQL construction and prepared statements need review.', 'taxocommerce-system-check' ),
            'review_ajax_nonce' => __( 'AJAX handler may be missing nonce verification.', 'taxocommerce-system-check' ),
            'missing_abspath_guard' => __( 'Direct-file access protection needs review.', 'taxocommerce-system-check' ),
            'php_parse_error' => __( 'PHP syntax error.', 'taxocommerce-system-check' ),
        );
        return $labels[ $rule ] ?? __( 'Review the flagged code for compliance with WordPress requirements.', 'taxocommerce-system-check' );
    }

    /** Guidance is a review action, not proof that the code is defective. */
    private static function finding_recommendation( $rule ) {
        if ( false !== strpos( $rule, 'plugin_lifecycle' ) || 'review_plugin_installation' === $rule ) {
            return __( 'Confirm an explicit administrator action and WordPress permissions; otherwise use native WordPress plugin management.', 'taxocommerce-system-check' );
        }
        if ( false !== strpos( $rule, 'nonce' ) || false !== strpos( $rule, 'capability' ) ) {
            return __( 'Verify the nonce and capability along the actual input-handling path.', 'taxocommerce-system-check' );
        }
        if ( false !== strpos( $rule, 'sanitization' ) || false !== strpos( $rule, 'upload' ) || false !== strpos( $rule, 'file_' ) ) {
            return __( 'Validate the input type, sanitize values and check uploads with WordPress APIs.', 'taxocommerce-system-check' );
        }
        if ( false !== strpos( $rule, 'sql' ) ) {
            return __( 'Check SQL parameters and use $wpdb->prepare() for untrusted values.', 'taxocommerce-system-check' );
        }
        if ( false !== strpos( $rule, 'output' ) || false !== strpos( $rule, 'escape' ) || false !== strpos( $rule, 'inline_style' ) ) {
            return __( 'Inspect the execution context and escape output at the point of rendering.', 'taxocommerce-system-check' );
        }
        if ( false !== strpos( $rule, 'hook' ) || false !== strpos( $rule, 'generic_' ) ) {
            return __( 'Use a unique plugin-specific prefix; preserve compatibility through reviewed migration.', 'taxocommerce-system-check' );
        }
        if ( false !== strpos( $rule, 'buffer' ) ) {
            return __( 'Ensure every ob_start() closes in the same logical flow, including error paths.', 'taxocommerce-system-check' );
        }
        if ( 'php_parse_error' === $rule ) {
            return __( 'Correct the PHP syntax and rerun the check.', 'taxocommerce-system-check' );
        }
        return __( 'Inspect the source line and validate whether this warning requires a code change.', 'taxocommerce-system-check' );
    }

    /** Render one accessible severity marker; colour alone is never the label. */
    private static function severity_dot( $status, $label ) {
        echo '<span class="tc-cc-indicator tc-cc-indicator--' . esc_attr( $status ) . '"><span class="tc-cc-dot" aria-hidden="true"></span>' . esc_html( $label ) . '</span>';
    }

    /** Render sections in the same order as the segmented JSON. */
    private static function render_classified( $state ) {
        $data = self::classified_findings( $state );
        $sections = array(
            'errors' => array( 'error', __( 'Errors to fix', 'taxocommerce-system-check' ), __( 'No confirmed errors in the implemented checks.', 'taxocommerce-system-check' ) ),
            'warnings' => array( 'warning', __( 'Warnings to review', 'taxocommerce-system-check' ), __( 'No warnings detected.', 'taxocommerce-system-check' ) ),
            'clean' => array( 'pass', __( 'Files without findings', 'taxocommerce-system-check' ), __( 'No fully checked clean files yet.', 'taxocommerce-system-check' ) ),
            'unchecked' => array( 'unchecked', __( 'Unchecked or incomplete', 'taxocommerce-system-check' ), __( 'All inventoried files have been checked.', 'taxocommerce-system-check' ) ),
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
                    echo '<div class="tc-cc-entry"><strong>' . esc_html( $item['file'] ?: __( 'Inventory', 'taxocommerce-system-check' ) ) . '</strong>';
                    if ( isset( $item['line'] ) && null !== $item['line'] ) {
                        echo ' <span>' . esc_html__( 'Line', 'taxocommerce-system-check' ) . ' ' . esc_html( (string) $item['line'] ) . '</span>';
                    }
                    if ( ! empty( $item['rule'] ) ) {
                        echo ' <code>' . esc_html( $item['rule'] ) . '</code>';
                    }
                    echo '<p>' . esc_html( $item['description'] ?? __( 'Not checked.', 'taxocommerce-system-check' ) ) . '</p>';
                    if ( ! empty( $item['recommendation'] ) ) {
                        echo '<p><strong>' . esc_html__( 'Recommended action:', 'taxocommerce-system-check' ) . '</strong> ' . esc_html( $item['recommendation'] ) . '</p>';
                    }
                    echo '</div>';
                }
                echo '</div>';
            }
            echo '</section>';
        }
        echo '</div>';
    }

    private static function status( $item ) {
        if ( ! is_array( $item ) ) {
            return __( 'Not run', 'taxocommerce-system-check' );
        }
        $names = array(
            'pass' => __( 'Passed', 'taxocommerce-system-check' ),
            'fail' => __( 'Failed', 'taxocommerce-system-check' ),
            'warning' => __( 'Review', 'taxocommerce-system-check' ),
            'skip' => __( 'Not checked', 'taxocommerce-system-check' ),
        );
        return $names[ $item['status'] ?? 'skip' ] ?? $names['skip'];
    }

    private static function button( $action, $label, $primary = false ) {
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';
        echo '<input type="hidden" name="operation" value="' . esc_attr( $action ) . '">';
        wp_nonce_field( self::ACTION );
        echo '<button type="submit" class="button ' . ( $primary ? 'button-primary' : 'button-secondary' ) . '">' . esc_html( $label ) . '</button></form>';
    }

    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Access denied.', 'taxocommerce-system-check' ) );
        }
        $state = self::state();
        $speeds = self::speeds();
        echo '<section class="wrap tcs-code-check">';
        echo '<h2>' . esc_html__( 'Code Check · System Check', 'taxocommerce-system-check' ) . '</h2>';
        echo '<p>' . esc_html__( 'Only this plugin is inspected. Three independent checks: PHP syntax, WordPress conventions and security patterns. No other checker plugin is required.', 'taxocommerce-system-check' ) . '</p>';
        echo '<p class="description">' . esc_html__( 'Native local checks: PHP, literal include paths, duplicate declarations, WordPress conventions and security patterns. Full data flow, runtime and official Plugin Check remain unverified.', 'taxocommerce-system-check' ) . '</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '"><input type="hidden" name="operation" value="start">';
        wp_nonce_field( self::ACTION );
        echo '<label for="tcs-code-speed">' . esc_html__( 'CPU speed', 'taxocommerce-system-check' ) . '</label> ';
        echo '<select id="tcs-code-speed" name="speed">';
        foreach ( $speeds as $index => $config ) {
            $selected = is_array( $state ) ? (int) $state['speed'] === $index : 3 === $index;
            echo '<option value="' . esc_attr( (string) $index ) . '"' . selected( $selected, true, false ) . '>' . esc_html( $config['label'] ) . '</option>';
        }
        echo '</select> ';
        submit_button( __( 'Run Code Check', 'taxocommerce-system-check' ), 'primary', 'submit', false );
        echo '</form>';
        if ( is_array( $state ) ) {
            $normalized = self::normalized_state( $state );
            $summary = self::summary_core( $normalized );
            $label = 'fail' === $summary['status']
                ? __( 'Errors detected', 'taxocommerce-system-check' )
                : ( 'review' === $summary['status']
                    ? __( 'Review required: warnings found', 'taxocommerce-system-check' )
                    : ( 'pass' === $summary['status']
                        ? __( 'No findings in implemented checks (not a WordPress.org certification)', 'taxocommerce-system-check' )
                        : __( 'Incomplete: not all files were checked', 'taxocommerce-system-check' ) ) );
            echo '<div class="notice notice-' . ( 'fail' === $summary['status'] ? 'error' : 'warning' ) . ' inline"><p><strong>'
                . esc_html( $label ) . '</strong></p>';
            echo '<p>' . esc_html__( 'Files analysed:', 'taxocommerce-system-check' ) . ' ' . esc_html( (string) $summary['files_scanned'] )
                . '/' . esc_html( (string) $summary['files_total'] ) . ' · '
                . esc_html__( 'Findings:', 'taxocommerce-system-check' ) . ' ' . esc_html( (string) $summary['findings'] ) . ' · '
                . esc_html__( 'Distinct rules:', 'taxocommerce-system-check' ) . ' ' . esc_html( (string) $summary['distinct_rules'] ) . '</p>';
            echo '<p>PHP: ' . esc_html( (string) $summary['by_group']['php'] )
                . ' · WordPress: ' . esc_html( (string) $summary['by_group']['wordpress'] )
                . ' · ' . esc_html__( 'Security:', 'taxocommerce-system-check' ) . ' ' . esc_html( (string) $summary['by_group']['security'] ) . '</p>';
            if ( $summary['rules'] ) {
                echo '<details><summary>' . esc_html__( 'All rules with findings', 'taxocommerce-system-check' ) . '</summary><ul>';
                foreach ( $summary['rules'] as $rule => $occurrences ) {
                    echo '<li><code>' . esc_html( $rule ) . '</code> (' . esc_html( (string) $occurrences ) . ')</li>';
                }
                echo '</ul></details>';
            }
            echo '<p>' . esc_html__( 'Native static indicators only. Official Plugin Check, live security and older PHP versions have not been executed here.', 'taxocommerce-system-check' ) . '</p></div>';
            echo '<p><strong>' . esc_html__( 'Progress', 'taxocommerce-system-check' ) . ':</strong> '
                . esc_html( (string) $state['cursor'] ) . '/' . esc_html( (string) count( $state['files'] ) )
                . ' · ' . esc_html( $state['status'] ) . '</p>';
            if ( 'running' === $state['status'] ) {
                self::button( 'stop', __( 'Stop', 'taxocommerce-system-check' ) );
                echo '<form id="tcs-code-next" data-delay="' . esc_attr( (string) $speeds[ $state['speed'] ]['delay'] ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
                echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '"><input type="hidden" name="operation" value="step">';
                wp_nonce_field( self::ACTION );
                echo '<noscript><button class="button" type="submit">' . esc_html__( 'Continue batch', 'taxocommerce-system-check' ) . '</button></noscript></form>';
            } elseif ( 'paused' === $state['status'] ) {
                self::button( 'resume', __( 'Resume', 'taxocommerce-system-check' ), true );
            }
            if ( $state['results'] ) {
                self::render_classified( $normalized );
                echo '<details class="tc-cc-legacy"><summary>' . esc_html__( 'Detailed results by file (technical view)', 'taxocommerce-system-check' ) . '</summary>';
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
                echo '<input type="hidden" name="action" value="' . esc_attr( self::EXPORT_ACTION ) . '">';
                wp_nonce_field( self::EXPORT_ACTION );
                submit_button( __( 'Download JSON report', 'taxocommerce-system-check' ), 'secondary', 'submit', false );
                echo '</form>';
            }
            if ( $state['results'] ) {
                $summary = self::summary( $state['results'] );
                echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'File', 'taxocommerce-system-check' ) . '</th><th>'
                    . esc_html__( 'Passed', 'taxocommerce-system-check' ) . '</th><th>'
                    . esc_html__( 'Failed', 'taxocommerce-system-check' ) . '</th><th>'
                    . esc_html__( 'Review', 'taxocommerce-system-check' ) . '</th><th>'
                    . esc_html__( 'Not checked', 'taxocommerce-system-check' ) . '</th></tr></thead><tbody>';
                foreach ( array( 'php' => 'PHP', 'wordpress' => 'WordPress', 'security' => 'Security' ) as $category => $label ) {
                    echo '<tr><th scope="row">' . esc_html( $label ) . '</th>';
                    foreach ( array( 'pass', 'fail', 'warning', 'skip' ) as $status ) {
                        echo '<td>' . esc_html( (string) $summary[ $category ][ $status ] ) . '</td>';
                    }
                    echo '</tr>';
                }
                echo '</tbody></table>';
            }
            echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'File', 'taxocommerce-system-check' ) . '</th><th>'
                . esc_html__( 'PHP', 'taxocommerce-system-check' ) . '</th><th>'
                . esc_html__( 'WordPress', 'taxocommerce-system-check' ) . '</th><th>'
                . esc_html__( 'Security', 'taxocommerce-system-check' ) . '</th></tr></thead><tbody>';
            foreach ( $state['results'] as $file => $result ) {
                echo '<tr><th scope="row">' . esc_html( $file ) . '</th>';
                foreach ( array( 'php', 'wordpress', 'security' ) as $category ) {
                    $item = $result[ $category ] ?? false;
                    echo '<td><strong>' . esc_html( self::status( $item ) ) . '</strong>';
                    if ( is_array( $item ) ) {
                        echo '<p>' . esc_html( $item['message'] ) . '</p>';
                        // Surface actionable evidence; retain complete detail in the JSON report.
                        if ( 'php' === $category && ! empty( $item['evidence']['line'] ) ) {
                            echo '<p>#' . esc_html( (string) absint( $item['evidence']['line'] ) ) . '</p>';
                        }
                        // Bounded actionable details; all evidence remains in JSON.
                        foreach ( array( 'findings', 'unverified_includes', 'compatibility', 'signals' ) as $group ) {
                            if ( empty( $item['evidence'][ $group ] ) || ! is_array( $item['evidence'][ $group ] ) ) {
                                continue;
                            }
                            echo '<ul>';
                            foreach ( array_slice( $item['evidence'][ $group ], 0, 8 ) as $finding ) {
                                $rule = isset( $finding['rule'] ) ? (string) $finding['rule'] : '';
                                $line = isset( $finding['line'] ) ? absint( $finding['line'] ) : 0;
                                $detail = isset( $finding['path'] ) ? ' ' . $finding['path'] : '';
                                if ( isset( $finding['other_file'] ) ) {
                                    $detail .= ' ' . $finding['other_file'];
                                }
                                echo '<li><code>' . esc_html( $rule ) . '</code> #' . esc_html( (string) $line ) . esc_html( $detail ) . '</li>';
                            }
                            echo '</ul>';
                        }
                        if ( 'wordpress' === $category && ! empty( $item['evidence']['missing'] ) ) {
                            echo '<ul>';
                            foreach ( array_slice( $item['evidence']['missing'], 0, 10 ) as $missing ) {
                                echo '<li>' . esc_html( $missing ) . '</li>';
                            }
                            echo '</ul>';
                        }
                        if ( 'security' === $category && ! empty( $item['evidence']['calls'] ) ) {
                            echo '<ul>';
                            foreach ( array_slice( $item['evidence']['calls'], 0, 10 ) as $call ) {
                                echo '<li>' . esc_html( (string) $call['function'] . ' #' . absint( $call['line'] ) ) . '</li>';
                            }
                            echo '</ul>';
                        }
                    }
                    echo '</td>';
                }
                echo '</tr>';
            }
            echo '</tbody></table>';
            if ( $state['results'] ) {
                echo '</details>';
            }
        }
        echo '</section>';
    }

    public static function export() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Access denied.', 'taxocommerce-system-check' ) );
        }
        check_admin_referer( self::EXPORT_ACTION );
        $report = self::state();
        if ( ! is_array( $report ) || ! $report['results'] ) {
            wp_die( esc_html__( 'Run Code Check before downloading results.', 'taxocommerce-system-check' ) );
        }
        $normalized = self::normalized_state( $report );
        $report['classified_findings'] = self::classified_findings( $normalized );
        $report['summary'] = self::summary_core( $normalized );
        $json = wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE );
        if ( ! is_string( $json ) ) {
            wp_die( esc_html__( 'Unable to generate JSON.', 'taxocommerce-system-check' ) );
        }
        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'X-Content-Type-Options: nosniff' );
        header( 'Content-Disposition: attachment; filename="taxocommerce-system-check-code-check-' . gmdate( 'Ymd-His' ) . '.json"' );
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download.
        echo $json;
        exit;
    }
}
