<?php
/**
 * Run the selected active plugin's read-only checks without family dependencies.
 *
 * Deep checks are delegated to that plugin's already published Autocheck API.
 * A missing native adapter is explicitly reported; basic checks are not
 * represented as a complete functional validation.
 *
 * @package TaxoCommerceSystemCheck
 */
defined( 'ABSPATH' ) || exit;

final class TaxoCommerce_System_Check_Module_Audit {
    const ACTION = 'tcs_module_audit';
    const EXPORT = 'tcs_module_audit_export';
    const TTL = DAY_IN_SECONDS;

    public static function boot() {
        add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'ajax' ) );
        add_action( 'admin_post_' . self::EXPORT, array( __CLASS__, 'export' ) );
        // Browser-driven checks are application jobs, never Action Scheduler jobs.
        add_filter( 'taxocommerce_system_check_process_workers', array( __CLASS__, 'process_rows' ) );
        add_filter( 'taxocommerce_system_check_stop_worker', array( __CLASS__, 'process_stop' ), 10, 2 );
    }

    private static function key( $id ) {
        return 'tcs_module_audit_' . get_current_blog_id() . '_' . get_current_user_id() . '_' . $id;
    }

    private static function state( $id ) {
        $value = get_transient( self::key( $id ) );
        return is_array( $value ) && isset( $value['module'], $value['status'] ) && $id === $value['module'] ? $value : false;
    }

    private static function recent_key() {
        return 'tcs_module_audit_recent_' . get_current_blog_id() . '_' . get_current_user_id();
    }

    /** A bounded execution trail; no credentials, request arguments or PHP paths. */
    private static function event( &$state, $action, $message = '' ) {
        $state['events'] = isset( $state['events'] ) && is_array( $state['events'] ) ? $state['events'] : array();
        $state['events'][] = array(
            'at' => gmdate( 'c' ),
            'action' => sanitize_key( (string) $action ),
            'phase' => sanitize_key( (string) ( $state['phase'] ?? '' ) ),
            'message' => sanitize_text_field( (string) $message ),
            'functional_done' => count( $state['functional_rows'] ?? array() ),
            'code_done' => absint( $state['code_cursor'] ?? 0 ),
        );
        $state['events'] = array_slice( $state['events'], -60 );
    }

    /** Read-only snapshot of the current administrator's check jobs. */
    public static function process_rows( $rows ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return $rows;
        }
        foreach ( taxocommerce_system_check_registered_modules() as $id => $module ) {
            $state = self::state( $id );
            if ( ! $state ) {
                continue;
            }
            $status = (string) $state['status'];
            if ( 'running' === $status ) {
                $status = time() - absint( $state['heartbeat'] ?? 0 ) > 30 ? 'waiting' : 'running';
            }
            $rows[] = array(
                'id' => 'audit:' . $id,
                // translators: %s: name of the active WordPress plugin being checked.
                'label' => sprintf( __( 'Plugin check: %s', 'taxocommerce-system-check' ), (string) ( $module['name'] ?? $id ) ),
                'status' => $status,
                'detail' => sprintf(
                    // translators: %1$d: functional tests, %2$d: files checked, %3$d: total plugin PHP files.
                    __( 'Browser-driven check, not PHP background execution; function tests: %1$d, code files: %2$d/%3$d.', 'taxocommerce-system-check' ),
                    count( $state['functional_rows'] ?? array() ),
                    absint( $state['code_cursor'] ?? 0 ),
                    count( $state['code_files'] ?? array() )
                ),
                'can_stop' => 'running' === ( $state['status'] ?? '' ),
            );
        }
        return $rows;
    }

    /** Cooperatively pause only the signed-in administrator's own report. */
    public static function process_stop( $stopped, $id ) {
        if ( $stopped || ! current_user_can( 'manage_options' ) || ! is_string( $id )
            || ! preg_match( '/^audit:([a-z0-9_-]+)$/', $id, $match ) ) {
            return $stopped;
        }
        $module = $match[1];
        if ( ! self::module( $module ) ) {
            return false;
        }
        $state = self::state( $module );
        if ( ! $state || 'running' !== $state['status'] ) {
            return false;
        }
        $state['status'] = 'paused';
        self::event( $state, 'stop', __( 'Paused from Processes.', 'taxocommerce-system-check' ) );
        set_transient( self::key( $module ), $state, self::TTL );
        return true;
    }

    /** Core is the source of active modules, not a duplicated plugin list. */
    private static function module( $id ) {
        $modules = taxocommerce_system_check_registered_modules();
        return isset( $modules[ $id ] ) ? $modules[ $id ] : false;
    }

    /** Resolve only a registered TaxoCommerce plugin directory. */
    private static function root( $id ) {
        $parent = realpath( dirname( TAXOCOMMERCE_SYSTEM_CHECK_PATH ) );
        $slug = 'system_check' === $id ? 'taxocommerce-system-check' : 'taxocommerce-' . str_replace( '_', '-', $id );
        $root = realpath( dirname( TAXOCOMMERCE_SYSTEM_CHECK_PATH ) . '/' . $slug );
        return $parent && $root && dirname( $root ) === $parent && is_dir( $root ) ? $root : false;
    }

    /**
     * Locate a native static check implementation by its source directory.
     * Never instantiate or call a class outside the selected active module.
     */
    private static function native( $root, $kind ) {
        if ( ! $root ) {
            return '';
        }
        if ( 'code' === $kind ) {
            $candidate = $root . '/includes/class-taxocommerce-autotest-code.php';
            if ( is_readable( $candidate ) ) {
                require_once $candidate;
            }
        }
        foreach ( get_declared_classes() as $class ) {
            $reflection = new ReflectionClass( $class );
            $filename = $reflection->getFileName();
            if ( ! $filename || 0 !== strpos( wp_normalize_path( $filename ), wp_normalize_path( $root ) . '/' ) ) {
                continue;
            }
            if ( 'code' === $kind ) {
                if ( ! preg_match( '/Autotest_Code$/', $class )
                    || ! $reflection->hasMethod( 'files' )
                    || ! $reflection->hasMethod( 'step' )
                    || ! $reflection->hasMethod( 'summary' ) ) {
                    continue;
                }
            } elseif ( ! preg_match( '/(?:_Auto_Test|_Autotest)$/', $class )
                || ( ! $reflection->hasMethod( 'run_suite' ) && ! $reflection->hasMethod( 'run' ) ) ) {
                continue;
            }
            $method = 'code' === $kind ? 'step' : ( $reflection->hasMethod( 'run_suite' ) ? 'run_suite' : 'run' );
            if ( $reflection->getMethod( $method )->isPublic() && $reflection->getMethod( $method )->isStatic() ) {
                return $class;
            }
        }
        return '';
    }

    public static function speeds() {
        return array( 1 => array( 'size' => 1, 'delay' => 6000 ), 2 => array( 'size' => 1, 'delay' => 4000 ), 3 => array( 'size' => 2, 'delay' => 2500 ), 4 => array( 'size' => 3, 'delay' => 1500 ), 5 => array( 'size' => 4, 'delay' => 900 ) );
    }

    private static function file_inventory( $root ) {
        if ( ! $root ) {
            return array();
        }
        $files = array();
        $iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
        foreach ( $iterator as $entry ) {
            if ( count( $files ) >= 250 ) {
                break;
            }
            if ( ! $entry->isFile() || $entry->isLink() || 'php' !== strtolower( $entry->getExtension() ) ) {
                continue;
            }
            $resolved = realpath( $entry->getPathname() );
            if ( $resolved && 0 === strpos( $resolved, $root . DIRECTORY_SEPARATOR ) ) {
                $files[] = str_replace( DIRECTORY_SEPARATOR, '/', substr( $resolved, strlen( $root ) + 1 ) );
            }
        }
        sort( $files, SORT_STRING );
        return $files;
    }

    private static function begin( $id, $speed ) {
        $module = self::module( $id );
        if ( ! $module ) {
            return new WP_Error( 'invalid_plugin', __( 'Select an active plugin from the table.', 'taxocommerce-system-check' ) );
        }
        $root = self::root( $id );
        if ( ! $root ) {
            return new WP_Error( 'invalid_root', __( 'The active plugin directory could not be verified.', 'taxocommerce-system-check' ) );
        }
        $functional_class = self::native( $root, 'functional' );
        $code_class = self::native( $root, 'code' );
        $cases = $functional_class && is_callable( array( $functional_class, 'cases' ) ) ? $functional_class::cases() : array();
        $cases = is_array( $cases ) ? $cases : array();
        // Never run write/UAT/network-operation cases through the family screen.
        $excluded = array();
        foreach ( array_keys( $cases ) as $case ) {
            if ( preg_match( '/(?:^write(?:_|$)|_uat$|^uat$|^destructive$|^remote_ops$)/i', (string) $case ) ) {
                $excluded[] = array( 'id' => $case, 'label' => (string) ( $cases[ $case ][1] ?? $case ), 'group' => 'Safety', 'status' => 'skip', 'message' => __( 'Real operations are not executed by this check.', 'taxocommerce-system-check' ) );
                unset( $cases[ $case ] );
            }
        }
        $mode = 'basic';
        if ( $functional_class ) {
            $method = is_callable( array( $functional_class, 'run_suite' ) ) ? 'run_suite' : 'run';
            $parameters = ( new ReflectionMethod( $functional_class, $method ) )->getParameters();
            if ( $parameters && in_array( $parameters[0]->getName(), array( 'cases', 'custom_cases' ), true ) ) {
                $mode = 'first_override';
            } elseif ( count( $parameters ) > 1 && in_array( $parameters[1]->getName(), array( 'cases', 'override', 'custom_cases' ), true ) ) {
                $mode = 'second_override';
            } else {
                $mode = 'full_suite';
            }
        }
        // A no-argument native suite already reports its own intentionally skipped UAT cases.
        // Do not duplicate those rows in the family report.
        if ( 'full_suite' === $mode ) {
            $excluded = array();
        }
        $files = $code_class ? $code_class::files() : self::file_inventory( $root );
        $files = is_array( $files ) ? array_values( $files ) : array();
        return array(
            'schema' => 2, 'module' => $id, 'name' => (string) ( $module['name'] ?? $id ),
            'version' => (string) ( $module['version'] ?? '' ), 'updated_at' => gmdate( 'c' ),
            'phase' => 'functional', 'status' => 'running', 'speed' => $speed,
            'functional_class' => $functional_class, 'functional_mode' => $mode,
            'cases' => $cases, 'functional_cursor' => 0, 'functional_rows' => $excluded,
            'code_class' => $code_class, 'code_cursor' => 0, 'code_files' => $files,
            'code_results' => array(), 'inventory_truncated' => count( $files ) >= 250,
            'run_id' => wp_generate_uuid4(), 'heartbeat' => time(), 'events' => array(),
            'note' => $functional_class ? '' : __( 'Only basic availability checks are supported by this plugin.', 'taxocommerce-system-check' ),
        );
    }

    private static function append_rows( &$state, $report ) {
        // Some native Autocheck implementations (including Billing and Suppliers)
        // return a list of rows directly rather than a report envelope.
        if ( is_array( $report ) && isset( $report[0] ) && is_array( $report[0] ) ) {
            $report = array( 'rows' => $report );
        }
        if ( ! is_array( $report ) || ! isset( $report['rows'] ) || ! is_array( $report['rows'] ) ) {
            $state['functional_rows'][] = array( 'id' => 'invalid_report', 'group' => '', 'label' => 'Autocheck', 'status' => 'fail', 'message' => __( 'The native test returned an invalid report.', 'taxocommerce-system-check' ) );
            return;
        }
        foreach ( $report['rows'] as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $status = (string) ( $item['status'] ?? 'fail' );
            $status = 'ok' === $status ? 'pass' : ( 'error' === $status ? 'fail' : ( 'not_run' === $status ? 'skip' : $status ) );
            $state['functional_rows'][] = array(
                'id' => (string) ( $item['id'] ?? $item['name'] ?? '' ),
                'group' => (string) ( $item['group'] ?? $item['group_id'] ?? '' ),
                'label' => (string) ( $item['label'] ?? $item['name'] ?? '' ),
                'status' => in_array( $status, array( 'pass', 'fail', 'skip', 'warning' ), true ) ? $status : 'fail',
                'message' => (string) ( $item['message'] ?? $item['detail'] ?? '' ),
            );
        }
    }

    private static function generic_code_check( $root, $relative ) {
        $file = realpath( $root . '/' . $relative );
        if ( ! $file || 0 !== strpos( $file, $root . DIRECTORY_SEPARATOR ) || ! is_readable( $file ) || ! is_file( $file ) || filesize( $file ) > 786432 ) {
            return array( 'status' => 'skip', 'message' => __( 'File unavailable or over the size limit.', 'taxocommerce-system-check' ), 'evidence' => array() );
        }
        $source = file_get_contents( $file );
        if ( ! is_string( $source ) ) {
            return array( 'status' => 'skip', 'message' => __( 'Unable to read the file.', 'taxocommerce-system-check' ), 'evidence' => array() );
        }
        $php = TaxoCommerce_System_Check_Code_Check::php_check( $source );
        $wordpress = TaxoCommerce_System_Check_Code_Check::wordpress_check( $source, $relative );
        $security = TaxoCommerce_System_Check_Code_Check::security_check( $source );
        return array( 'php' => $php, 'wordpress' => $wordpress, 'security' => $security );
    }

    private static function advance( $state ) {
        $started = microtime( true );
        $speed = (int) $state['speed'];
        $size = self::speeds()[ $speed ]['size'];
        $class = $state['functional_class'];
        if ( 'functional' === $state['phase'] ) {
            if ( ! $class ) {
                foreach ( taxocommerce_system_check_module_checks( $state['module'], self::module( $state['module'] ) ) as $id => $check ) {
                    $state['functional_rows'][] = array( 'id' => $id, 'group' => 'Basic', 'label' => $check['label'], 'status' => $check['ok'] ? 'pass' : 'fail', 'message' => __( 'Basic availability check; not a functional integration test.', 'taxocommerce-system-check' ) );
                }
                $state['phase'] = 'code';
            } else {
                $method = is_callable( array( $class, 'run_suite' ) ) ? 'run_suite' : 'run';
                $mode = $state['functional_mode'];
                if ( 'full_suite' === $mode ) {
                    $report = $class::$method();
                    self::append_rows( $state, $report );
                    $state['functional_cursor'] = count( $state['cases'] );
                    $state['phase'] = 'code';
                } else {
                    $slice = array_slice( $state['cases'], $state['functional_cursor'], $size, true );
                    if ( $slice ) {
                        $report = 'second_override' === $mode ? $class::$method( 'all', $slice ) : $class::$method( $slice );
                        self::append_rows( $state, $report );
                        $state['functional_cursor'] += count( $slice );
                    }
                    if ( $state['functional_cursor'] >= count( $state['cases'] ) ) {
                        $state['phase'] = 'code';
                    }
                }
            }
        } elseif ( 'code' === $state['phase'] ) {
            $native = $state['code_class'];
            $root = self::root( $state['module'] );
            if ( $native ) {
                // Existing scanner's step() uses a bounded speed and its own rules.
                $code = $state['native_code'] ?? array(
                    'schema' => 1, 'plugin' => $state['module'], 'timestamp' => gmdate( 'c' ),
                    'status' => 'running', 'speed' => $speed, 'cursor' => 0,
                    'files' => $state['code_files'], 'results' => array(), 'symbols' => array(),
                    'native_static_review' => 'enabled', 'native_rules_revision' => 1,
                    'official_pcp' => 'not_executed', 'runtime_security' => 'not_verified',
                    'older_php_versions' => 'not_tested', 'inventory_truncated' => $state['inventory_truncated'],
                );
                $state['native_code'] = $native::step( $code );
                $state['code_cursor'] = (int) ( $state['native_code']['cursor'] ?? 0 );
                if ( 'completed' === ( $state['native_code']['status'] ?? '' ) ) {
                    $state['phase'] = 'done';
                    $state['status'] = 'completed';
                }
            } else {
                for ( $i = 0; $i < $size && $state['code_cursor'] < count( $state['code_files'] ); ++$i ) {
                    $relative = $state['code_files'][ $state['code_cursor'] ];
                    $state['code_results'][ $relative ] = self::generic_code_check( $root, $relative );
                    ++$state['code_cursor'];
                    if ( microtime( true ) - $started > 1.2 ) {
                        break;
                    }
                }
                if ( $state['code_cursor'] >= count( $state['code_files'] ) ) {
                    $state['phase'] = 'done';
                    $state['status'] = 'completed';
                }
            }
        }
        $state['updated_at'] = gmdate( 'c' );
        $state['heartbeat'] = time();
        self::event( $state, 'step', (string) $state['phase'] );
        return $state;
    }

    private static function code_entries( $state ) {
        if ( $state['code_class'] && ! empty( $state['native_code'] ) ) {
            $class = $state['code_class'];
            if ( is_callable( array( $class, 'classified_findings' ) ) ) {
                return $class::classified_findings( $state['native_code'] );
            }
            $results = $state['native_code']['results'] ?? array();
        } else {
            $results = $state['code_results'] ?? array();
        }
        $data = array( 'errors' => array(), 'warnings' => array(), 'unchecked' => array() );
        foreach ( $results as $file => $groups ) {
            foreach ( array( 'php', 'wordpress', 'security' ) as $group ) {
                $item = $groups[ $group ] ?? $groups;
                $status = $item['status'] ?? 'skip';
                if ( 'pass' === $status ) {
                    continue;
                }
                $row = array( 'file' => (string) $file, 'group' => $group, 'status' => $status, 'description' => (string) ( $item['message'] ?? '' ), 'evidence' => $item['evidence'] ?? array() );
                $category = 'fail' === $status ? 'errors' : ( 'skip' === $status ? 'unchecked' : 'warnings' );
                $data[ $category ][] = $row;
            }
        }
        return $data;
    }

    public static function summary( $state ) {
        if ( ! $state ) {
            return array( 'status' => 'not_run', 'phase' => 'pending' );
        }
        $totals = array( 'pass' => 0, 'fail' => 0, 'skip' => 0, 'warning' => 0 );
        foreach ( $state['functional_rows'] as $item ) {
            $status = $item['status'] ?? 'skip';
            ++$totals[ isset( $totals[ $status ] ) ? $status : 'skip' ];
        }
        $code = self::code_entries( $state );
        return array(
            'plugin' => $state['module'], 'name' => $state['name'], 'status' => $state['status'],
            'phase' => $state['phase'], 'updated_at' => $state['updated_at'], 'note' => $state['note'],
            'functional' => array(
                'status' => 'functional' === $state['phase'] ? 'running' : ( $state['functional_class'] ? ( $totals['fail'] ? 'fail' : ( $totals['warning'] ? 'review' : 'checked' ) ) : 'basic_only' ),
                'done' => count( $state['functional_rows'] ),
                'total' => 'full_suite' === $state['functional_mode'] && 'functional' !== $state['phase']
                    ? count( $state['functional_rows'] )
                    : count( $state['cases'] ) + ( $state['functional_class'] ? count( array_filter( $state['functional_rows'], static function ( $item ) { return 'Safety' === ( $item['group'] ?? '' ); } ) ) : 2 ),
                'totals' => $totals,
            ),
            'code' => array(
                'status' => 'completed' === $state['status'] ? 'checked' : 'running',
                'done' => $state['code_cursor'], 'total' => count( $state['code_files'] ),
                'errors' => count( $code['errors'] ?? array() ), 'warnings' => count( $code['warnings'] ?? array() ),
                'unchecked' => count( $code['unchecked'] ?? array() ),
                'native' => (bool) $state['code_class'],
            ),
            'delay_ms' => self::speeds()[ $state['speed'] ]['delay'],
        );
    }

    public static function report( $state ) {
        if ( ! $state ) {
            return false;
        }
        return array(
            'schema' => 2, 'plugin' => $state['module'], 'name' => $state['name'],
            'version' => $state['version'], 'executed_at' => $state['updated_at'],
            'execution_log' => $state['events'] ?? array(),
            'summary' => self::summary( $state ), 'functional_tests' => $state['functional_rows'],
            'code_findings' => self::code_entries( $state ),
            'limitations' => array(
                'functional' => $state['functional_class'] ? 'native_read_only_autotest' : 'basic_availability_only',
                'code' => $state['code_class'] ? 'native_heuristic_scanner' : 'basic_heuristic_scanner',
                'official_wordpress_plugin_check' => 'not_executed',
                'inventory_truncated' => $state['inventory_truncated'],
            ),
        );
    }

    public static function ajax() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'taxocommerce-system-check' ) ), 403 );
        }
        check_ajax_referer( self::ACTION, 'nonce' );
        $id = isset( $_POST['module'] ) && is_scalar( $_POST['module'] ) ? sanitize_key( wp_unslash( $_POST['module'] ) ) : '';
        if ( ! self::module( $id ) ) {
            wp_send_json_error( array( 'message' => __( 'The selected plugin is not registered as active.', 'taxocommerce-system-check' ) ), 400 );
        }
        $op = isset( $_POST['operation'] ) && is_scalar( $_POST['operation'] ) ? sanitize_key( wp_unslash( $_POST['operation'] ) ) : 'status';
        $state = self::state( $id );
        if ( 'start' === $op ) {
            $value = isset( $_POST['speed'] ) && is_scalar( $_POST['speed'] ) ? absint( wp_unslash( $_POST['speed'] ) ) : 3;
            try {
                $state = self::begin( $id, isset( self::speeds()[ $value ] ) ? $value : 3 );
            } catch ( Throwable $error ) {
                wp_send_json_error( array( 'message' => __( 'The active plugin directory could not be verified.', 'taxocommerce-system-check' ) ), 503 );
            }
            if ( is_wp_error( $state ) ) {
                wp_send_json_error( array( 'message' => $state->get_error_message() ), 400 );
            }
            self::event( $state, 'start', __( 'Plugin validation started.', 'taxocommerce-system-check' ) );
            set_transient( self::recent_key(), $id, self::TTL );
        } elseif ( $state && 'stop' === $op && 'running' === $state['status'] ) {
            $state['status'] = 'paused';
            self::event( $state, 'stop', __( 'Stopped by administrator; active PHP requests cannot be interrupted.', 'taxocommerce-system-check' ) );
        } elseif ( $state && 'resume' === $op && in_array( $state['status'], array( 'paused', 'running' ), true ) ) {
            $state['status'] = 'running';
            unset( $state['error'] );
            $state['heartbeat'] = time();
            self::event( $state, 'resume', __( 'Validation resumed in browser.', 'taxocommerce-system-check' ) );
        } elseif ( $state && 'step' === $op && 'running' === $state['status'] ) {
            try {
                $state = self::advance( $state );
            } catch ( Throwable $error ) {
                $state['status'] = 'paused';
                $state['error'] = __( 'The check was paused after an execution error.', 'taxocommerce-system-check' );
                self::event( $state, 'error', get_class( $error ) );
            }
            // A stop or a fresh start may have arrived during the previous step.
            // Never resurrect a paused job or overwrite a new generation.
            $latest = self::state( $id );
            if ( $latest && ( $latest['run_id'] ?? '' ) !== ( $state['run_id'] ?? '' ) ) {
                $state = $latest;
            } elseif ( $latest && 'paused' === ( $latest['status'] ?? '' ) ) {
                $state['status'] = 'paused';
                $state['events'] = array_slice( array_merge( $state['events'] ?? array(), array_slice( $latest['events'] ?? array(), -1 ) ), -60 );
            }
        }
        if ( $state ) {
            set_transient( self::key( $id ), $state, self::TTL );
        }
        if ( 'report' === $op ) {
            wp_send_json_success( self::report( $state ) );
        }
        $result = self::summary( $state );
        if ( ! empty( $state['error'] ) ) {
            $result['error'] = $state['error'];
        }
        wp_send_json_success( $result );
    }

    public static function export() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'taxocommerce-system-check' ) );
        }
        check_admin_referer( self::EXPORT );
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified above.
        $id = isset( $_GET['module'] ) && is_scalar( $_GET['module'] ) ? sanitize_key( wp_unslash( $_GET['module'] ) ) : '';
        if ( ! self::module( $id ) ) {
            wp_die( esc_html__( 'Unknown plugin.', 'taxocommerce-system-check' ) );
        }
        $report = self::report( self::state( $id ) );
        if ( ! $report || 'completed' !== ( $report['summary']['status'] ?? '' ) ) {
            wp_die( esc_html__( 'Complete the selected plugin check before downloading the report.', 'taxocommerce-system-check' ) );
        }
        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'X-Content-Type-Options: nosniff' );
        header( 'Content-Disposition: attachment; filename="taxocommerce-check-' . sanitize_file_name( $id ) . '-' . gmdate( 'Ymd-His' ) . '.json"' );
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON attachment, never HTML.
        echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE );
        exit;
    }

    private static function recent() {
        $id = get_transient( self::recent_key() );
        return is_string( $id ) && self::module( $id ) ? self::summary( self::state( $id ) ) : false;
    }

    public static function row( $id ) {
        $state = self::state( $id );
        $summary = self::summary( $state );
        if ( ! $state ) {
            $function_status = __( 'Not checked', 'taxocommerce-system-check' );
            $code_status = __( 'Not checked', 'taxocommerce-system-check' );
        } else {
            $totals = $summary['functional']['totals'];
            $function_status = 'basic_only' === $summary['functional']['status']
                ? __( 'Basic only: ', 'taxocommerce-system-check' ) . ' · ' . $totals['fail'] . ' ' . __( 'failures', 'taxocommerce-system-check' )
                : $summary['functional']['done'] . '/' . $summary['functional']['total'] . ' · ' . $totals['fail'] . ' ' . __( 'failures', 'taxocommerce-system-check' )
                . ' · ' . $totals['warning'] . ' ' . __( 'warnings', 'taxocommerce-system-check' );
            $code_status = $summary['code']['done'] . '/' . $summary['code']['total'] . ' · '
                . $summary['code']['errors'] . ' ' . __( 'errors', 'taxocommerce-system-check' ) . ' · '
                . $summary['code']['warnings'] . ' ' . __( 'warnings to review', 'taxocommerce-system-check' );
        }
        echo '<td data-tcs-module-functional="' . esc_attr( $id ) . '">' . esc_html( $function_status ) . '</td>';
        echo '<td data-tcs-module-code="' . esc_attr( $id ) . '">' . esc_html( $code_status ) . '</td>';
        echo '<td><button type="button" class="button button-small" data-tcs-module-start="' . esc_attr( $id ) . '">' . esc_html__( 'Check plugin', 'taxocommerce-system-check' ) . '</button> ';
        echo '<button type="button" class="button button-small" data-tcs-module-report="' . esc_attr( $id ) . '">' . esc_html__( 'View results', 'taxocommerce-system-check' ) . '</button> ';
        $can_toggle = $state && in_array( $state['status'], array( 'running', 'paused' ), true );
        $button_label = $state && 'running' === $state['status'] ? __( 'Stop', 'taxocommerce-system-check' ) : __( 'Resume', 'taxocommerce-system-check' );
        echo '<button type="button" class="button button-small" data-tcs-module-toggle="' . esc_attr( $id ) . '"' . ( $can_toggle ? '' : ' disabled="disabled"' ) . '>' . esc_html( $button_label ) . '</button></td>';
    }

    public static function render() {
        echo '<section id="tcs-module-audit" class="postbox" style="padding:14px 18px;max-width:1100px">';
        echo '<h3>' . esc_html__( 'Individual plugin validation', 'taxocommerce-system-check' ) . '</h3>';
        echo '<p>' . esc_html__( 'Check plugins individually or run all active TaxoCommerce modules sequentially. Each plugin keeps its own results, log and JSON report.', 'taxocommerce-system-check' ) . '</p>';
        echo '<label for="tcs-module-speed">' . esc_html__( 'CPU usage', 'taxocommerce-system-check' ) . '</label> ';
        echo '<select id="tcs-module-speed">';
        foreach ( array( 1 => __( 'Very low', 'taxocommerce-system-check' ), 2 => __( 'Low', 'taxocommerce-system-check' ), 3 => __( 'Normal', 'taxocommerce-system-check' ), 4 => __( 'High', 'taxocommerce-system-check' ), 5 => __( 'Maximum', 'taxocommerce-system-check' ) ) as $speed => $name ) {
            echo '<option value="' . esc_attr( (string) $speed ) . '"' . selected( 3, $speed, false ) . '>' . esc_html( $name ) . '</option>';
        }
        echo '</select> ';
        echo '<button type="button" class="button button-primary" data-tcs-module-all>' . esc_html__( 'Check all plugins', 'taxocommerce-system-check' ) . '</button> ';
        echo '<button type="button" class="button" data-tcs-module-op="stop">' . esc_html__( 'Stop', 'taxocommerce-system-check' ) . '</button> ';
        echo '<button type="button" class="button" data-tcs-module-op="resume">' . esc_html__( 'Resume', 'taxocommerce-system-check' ) . '</button>';
        echo '<p id="tcs-module-bulk-progress" role="status" aria-live="polite"></p>';
        echo '<p id="tcs-module-progress" role="status" aria-live="polite"></p>';
        echo '<section id="tcs-module-report" hidden aria-label="' . esc_attr__( 'Plugin validation results', 'taxocommerce-system-check' ) . '"></section>';
        echo '</section>';
        wp_add_inline_script( 'taxocommerce-system-check-module-audit', 'window.tcsModuleAudit=' . wp_json_encode( array(
            'url' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( self::ACTION ),
            'site' => get_current_blog_id(), 'user' => get_current_user_id(),
            'initial' => self::recent(),
            'export' => wp_nonce_url( admin_url( 'admin-post.php?action=' . self::EXPORT ), self::EXPORT ),
            'i18n' => array(
                'request_failed' => __( 'Unable to execute the check.', 'taxocommerce-system-check' ),
                'all_progress' => __( 'Plugins checked', 'taxocommerce-system-check' ),
                'all_failures' => __( 'Checks with findings', 'taxocommerce-system-check' ),
                'all_errors' => __( 'Execution errors', 'taxocommerce-system-check' ),
                'all_paused' => __( 'Paused', 'taxocommerce-system-check' ),
                'all_complete' => __( 'All plugin checks finished', 'taxocommerce-system-check' ),
                'failures' => __( 'failures', 'taxocommerce-system-check' ),
                'skipped' => __( 'skipped', 'taxocommerce-system-check' ),
                'errors' => __( 'errors', 'taxocommerce-system-check' ),
                'warnings' => __( 'warnings', 'taxocommerce-system-check' ),
                'basic_only' => __( 'Basic only: ', 'taxocommerce-system-check' ),
                'no_details' => __( 'No details available.', 'taxocommerce-system-check' ),
                'review_required' => __( 'Review required.', 'taxocommerce-system-check' ),
                'recommendation' => __( 'Recommendation: ', 'taxocommerce-system-check' ),
                'none_recorded' => __( 'No results in this category.', 'taxocommerce-system-check' ),
                'not_run' => __( 'No report exists for this plugin yet.', 'taxocommerce-system-check' ),
                'results_of' => __( 'Results for ', 'taxocommerce-system-check' ),
                'status' => __( 'Status: ', 'taxocommerce-system-check' ),
                'date' => __( ' · Date: ', 'taxocommerce-system-check' ),
                'download_json' => __( 'Download complete JSON for this plugin', 'taxocommerce-system-check' ),
                'functional_failures' => __( 'Functional failures', 'taxocommerce-system-check' ),
                'skipped_checks' => __( 'Skipped checks', 'taxocommerce-system-check' ),
                'passed_checks' => __( 'Passed checks', 'taxocommerce-system-check' ),
                'code_errors' => __( 'Code errors', 'taxocommerce-system-check' ),
                'code_warnings' => __( 'Code warnings (manual review)', 'taxocommerce-system-check' ),
                'unchecked' => __( 'Unchecked files', 'taxocommerce-system-check' ),
                'disclaimer' => __( 'Local code review is not the official WordPress Plugin Check.', 'taxocommerce-system-check' ),
                'can_resume' => __( ' · You can resume this check.', 'taxocommerce-system-check' ),
                'preparing' => __( 'Preparing check for ', 'taxocommerce-system-check' ),
                'functions' => __( 'Functions', 'taxocommerce-system-check' ),
                'code' => __( 'Code', 'taxocommerce-system-check' ),
                'execution_log' => __( 'Execution log', 'taxocommerce-system-check' ),
                'invalid_json' => __( 'The server returned an invalid JSON response. Check PHP notices or the server error log.', 'taxocommerce-system-check' ),
                'resume_hint' => __( 'The browser is not processing batches; press Resume to continue.', 'taxocommerce-system-check' ),
                'stop' => __( 'Stop', 'taxocommerce-system-check' ),
                'resume' => __( 'Resume', 'taxocommerce-system-check' ),
                'running' => __( 'Running', 'taxocommerce-system-check' ),
                'completed' => __( 'Completed', 'taxocommerce-system-check' ),
                'pending' => __( 'Pending', 'taxocommerce-system-check' ),
                'waiting' => __( 'Waiting', 'taxocommerce-system-check' ),
                'pass' => __( 'Passed', 'taxocommerce-system-check' ),
                'fail' => __( 'Failed', 'taxocommerce-system-check' ),
                'skip' => __( 'Not run', 'taxocommerce-system-check' ),
                'warning' => __( 'Review', 'taxocommerce-system-check' ),
                'not_run_status' => __( 'Not run', 'taxocommerce-system-check' ),
                'paused_status' => __( 'Paused', 'taxocommerce-system-check' ),
                'error_status' => __( 'Error', 'taxocommerce-system-check' ),
                'findings' => __( 'Findings', 'taxocommerce-system-check' ),
            ),
        ) ) . ';', 'before' );
    }
}
