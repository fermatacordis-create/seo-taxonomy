<?php
/**
 * Plugin Name: TaxoCommerce Templates
 * Plugin URI: https://asociacionfocazul.com/taxocommerce-templates/
 * Description: Standalone WordPress/WooCommerce request-template routing, SEO role assignments and migration audit.
 * Version: 0.4.32
 * Release Date: 2026-11-10
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: David Perez
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: taxocommerce-templates
 * Domain Path: /languages
 */
defined('ABSPATH') || exit;

require_once __DIR__ . '/includes/admin-branding.php';
require_once __DIR__ . '/includes/class-taxocommerce-autotest-code.php';
\TaxoCommerce_Templates_Autotest_Code::boot();
define('TCT_VERSION','0.4.32');
define('TCT_FILE',__FILE__);
define('TCT_DIR',plugin_dir_path(__FILE__));
define('TCT_URL',plugin_dir_url(__FILE__));
require_once TCT_DIR . 'includes/class-tct-db.php';
require_once TCT_DIR . 'includes/class-tct-router.php';
require_once TCT_DIR . 'includes/class-tct-request-registry.php';
require_once TCT_DIR . 'includes/class-tct-request-admin.php';
require_once TCT_DIR . 'includes/class-tct-template-editor.php';
require_once TCT_DIR . 'includes/class-tct-front-composer.php';
require_once TCT_DIR . 'includes/class-tct-unified-manager.php';
require_once TCT_DIR . 'includes/class-tct-routing-admin.php';
require_once TCT_DIR . 'includes/class-tct-designs.php';
require_once TCT_DIR . 'includes/class-tct-association-audit.php';
require_once TCT_DIR . 'includes/class-tct-autocheck.php';
require_once TCT_DIR . 'includes/class-tct-seo-title.php';
require_once TCT_DIR . 'includes/class-tct-category-content-sync.php';
require_once TCT_DIR . 'includes/class-tct-manual.php';
require_once TCT_DIR . 'includes/class-tct-legacy-email-compat.php';
require_once TCT_DIR . 'includes/class-tct-pilot-router.php';

final class TaxoCommerce_Templates {
    const ROLE_OPTION = 'taxocommerce_templates_custom_roles';
    const ENABLE_OPTION = 'taxocommerce_templates_render_enabled';
    // New consent is OFF by default, including when an older installation
    // left ENABLE_OPTION=1. Deployment can never turn public routes on.
    const REVIEW_CONSENT_OPTION = 'taxocommerce_templates_review_consent_v1';

    /** Explicit operator opt-in required after updating the plugin. */
    public static function policies_enabled() {
        return 1 === (int) get_option(self::ENABLE_OPTION, 0)
            && 1 === (int) get_option(self::REVIEW_CONSENT_OPTION, 0);
    }

    public static function nodes() { global $wpdb; return $wpdb->prefix.'seo_nodes'; }
    public static function templates() { global $wpdb; return $wpdb->prefix.'seo_templates'; }
    public static function base_roles() {
        return TCT_Router::page_roles();
    }
    public static function roles() {
        $custom=get_option(self::ROLE_OPTION,array());
        return array_merge(self::base_roles(),is_array($custom)?$custom:array());
    }
    public static function installed() {
        global $wpdb;
        foreach(array(self::nodes(),self::templates()) as $table) {
            if(TCT_DB::get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table)))!==$table) return false;
        }
        return true;
    }
    public static function activate() {
        global $wpdb;
        require_once ABSPATH.'wp-admin/includes/upgrade.php';
        $charset=$wpdb->get_charset_collate();
        $nodes=self::nodes(); $templates=self::templates();
        // The schema remains compatible with existing Core / SEO Taxonomy data.
        dbDelta("CREATE TABLE {$nodes} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            object_type VARCHAR(50) NOT NULL,
            object_id BIGINT UNSIGNED NOT NULL,
            seo_role VARCHAR(80) NOT NULL,
            keywords LONGTEXT NULL,
            title VARCHAR(255) NULL,
            status TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY unique_object_role (object_type,object_id,seo_role),
            KEY object_lookup (object_type,object_id),
            KEY seo_role (seo_role),
            KEY status (status)
        ) {$charset};");
        dbDelta("CREATE TABLE {$templates} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            template_key VARCHAR(191) NOT NULL,
            template_name VARCHAR(191) NOT NULL,
            template_file VARCHAR(191) NOT NULL,
            template_content LONGTEXT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            template_type VARCHAR(30) NOT NULL DEFAULT 'page',
            is_public TINYINT(1) NOT NULL DEFAULT 1,
            is_assignable TINYINT(1) NOT NULL DEFAULT 0,
            assignment_mode VARCHAR(20) NOT NULL DEFAULT 'automatic',
            display_order INT(11) NOT NULL DEFAULT 0,
            description TEXT NULL,
            device_variants_enabled TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY template_key (template_key),
            KEY is_active (is_active),
            KEY template_type (template_type)
        ) {$charset};");
        add_option(self::ENABLE_OPTION,0,'',false);
        TCT_Router::seed_catalog();
    }
    public static function boot() {
        TCT_Autocheck::boot();
        // Mantener el filtrado de escaparate ES del anterior cargador. No
        // declarar funciones duplicadas si ambos plugins estan activos.
        if (self::policies_enabled() &&
            !function_exists('seo_front_es_international_category_ids')) {
            require_once TCT_DIR . 'includes/seo-front-es-catalog.php';
        }
        add_action('admin_enqueue_scripts',array(__CLASS__,'enqueue_policy_control'));
        add_action('admin_menu',array(__CLASS__,'menu'),65);
        add_action('admin_post_tct_save_role',array(__CLASS__,'save_role'));
        add_action('admin_post_' . TCT_Category_Content_Sync::ACTION,array('TCT_Category_Content_Sync','handle'));
        add_action('admin_post_tct_save_template',array(__CLASS__,'save_template'));
        add_action('admin_post_tct_settings',array(__CLASS__,'settings'));
        add_action('admin_post_tct_save_page_role',array(__CLASS__,'save_page_role'));
        add_action('admin_post_tct_upload_template',array('TCT_Template_Editor','upload'));
        add_action('admin_post_tct_save_request',array('TCT_Request_Admin','save_request'));
        add_action('admin_post_tct_save_route_mapping',array('TCT_Routing_Admin','save'));
        add_action('admin_enqueue_scripts',array('TCT_Routing_Admin','enqueue'));
        add_action('admin_post_tct_save_code',array('TCT_Template_Editor','save'));
        add_action('admin_post_tct_front_composer_save',array('TCT_Front_Composer','save'));
        add_action('admin_enqueue_scripts',array('TCT_Front_Composer','enqueue'));
        add_action('admin_enqueue_scripts',array('TCT_Designs','enqueue'));
        add_action('admin_post_tct_design_create',array('TCT_Designs','create'));
        add_action('admin_post_tct_design_save',array('TCT_Designs','save'));
        // No frontend hooks at all while policies are suspended. Admin/editor,
        // saved routes and the master switch remain fully operational.
        if (self::policies_enabled()) {
            add_filter( 'document_title_parts', array( 'TCT_SEO_Title', 'concise_parts' ), 99 );
            add_filter('wc_get_template',array('TCT_Request_Registry','resolve_woocommerce_partial'),40,5);
            add_filter('wc_get_template_part',array('TCT_Request_Registry','resolve_woocommerce_part'),40,3);
            add_filter('comments_template',array('TCT_Request_Registry','resolve_comments'),40,1);
            add_filter('woocommerce_email_classes',array('TCT_Legacy_Email_Compat','apply'),999);
            add_filter('template_include',array('TCT_Request_Registry','resolve'),PHP_INT_MAX);
        }
        add_action('admin_post_tct_save_device_mode',array(__CLASS__,'save_device_mode'));
        add_action('admin_post_tct_assign_page',array(__CLASS__,'assign_page'));
        add_action('admin_post_tct_unassign_page',array(__CLASS__,'unassign_page'));
        add_action('admin_post_tct_download_template',array(__CLASS__,'download_template'));
        add_action('admin_post_tct_export_associations',array('TCT_Association_Audit','export_csv'));
        add_action('admin_post_tct_restore_missing_roles',array('TCT_Association_Audit','restore_missing_roles'));
        add_action('admin_init',array('TCT_Router','seed_catalog'));
        add_action('add_meta_boxes_page',array(__CLASS__,'page_metabox'));
        add_action('save_post_page',array(__CLASS__,'save_metabox'),25,2);
        add_action('taxocommerce_core_register_modules',array(__CLASS__,'register_core'));
        if(function_exists('taxocommerce_core')&&did_action('taxocommerce_core_loaded')) self::register_core(taxocommerce_core());
    }
    public static function register_core($core) {
        if(is_object($core)&&method_exists($core,'register_module')) {
            $core->register_module('templates',array('name'=>'TaxoCommerce Templates','version'=>TCT_VERSION,'status'=>'active','description'=>'Roles y plantillas independientes.','admin_url'=>admin_url('admin.php?page=taxocommerce-templates')));
        }
    }
    public static function menu() {
        $parent=function_exists('taxocommerce_core')?'taxocommerce-core':null;
        if($parent) add_submenu_page($parent,__( 'TaxoCommerce Templates', 'taxocommerce-templates' ),__( 'Templates', 'taxocommerce-templates' ),'manage_options','taxocommerce-templates',array(__CLASS__,'screen'));
        else add_menu_page(__( 'TaxoCommerce Templates', 'taxocommerce-templates' ),__( 'Templates', 'taxocommerce-templates' ),'manage_options','taxocommerce-templates',array(__CLASS__,'screen'),'dashicons-layout',62);
    }
    private static function guard($nonce) {
        if(!current_user_can('manage_options')) wp_die('Permisos insuficientes.');
        check_admin_referer($nonce);
        if(!self::installed()) wp_die('Tablas no disponibles. Desactiva y reactiva el plugin para instalarlas.');
    }
    private static function redirect($notice) {
        wp_safe_redirect(add_query_arg(array('page'=>'taxocommerce-templates','tct_notice'=>$notice),admin_url('admin.php')));exit;
    }
    public static function page_role($id) {
        return TCT_Request_Registry::role_for_object( 'page', absint( $id ) );
    }
    public static function set_role($id,$role) {
        global $wpdb;$id=absint($id);$role=sanitize_key($role);
        if('page'!==get_post_type($id)||($role&&!isset(self::roles()[$role]))) return new WP_Error('tct_invalid_role','Página o rol no válido.');
        $table=self::nodes();$current=self::page_role($id);
        if($current===$role) return true;
        // Roles used in live hierarchy must not be silently reassigned.
        $relations=$wpdb->prefix.'seo_relations';
        if($current&&TCT_DB::get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($relations)))===$relations) {
            $count=(int)TCT_DB::get_var($wpdb->prepare("SELECT COUNT(*) FROM %i WHERE (source_id=%d AND source_type IN ('cluster','hub_primary','hub_secondary','landing_page')) OR (target_id=%d AND target_type IN ('cluster','hub_primary','hub_secondary','landing_page'))",$relations,$id,$id));
            if($count>0) return new WP_Error('tct_hierarchy_linked','La página tiene relaciones jerárquicas: desvincúlala en Core antes de cambiar su rol.');
        }
        $now=current_time('mysql');TCT_DB::query('START TRANSACTION');
        $disabled=TCT_DB::update($table,array('status'=>0,'updated_at'=>$now),array('object_type'=>'page','object_id'=>$id));
        $saved=true;
        if(false!==$disabled&&$role) {
            $existing=TCT_DB::get_var($wpdb->prepare("SELECT id FROM %i WHERE object_type='page' AND object_id=%d AND seo_role=%s LIMIT 1",$table,$id,$role));
            $saved=$existing?
                false!==TCT_DB::update($table,array('status'=>1,'updated_at'=>$now),array('id'=>(int)$existing)):
                false!==TCT_DB::insert($table,array('object_type'=>'page','object_id'=>$id,'seo_role'=>$role,'status'=>1,'created_at'=>$now,'updated_at'=>$now));
        }
        if(false===$disabled||!$saved){TCT_DB::query('ROLLBACK');return new WP_Error('tct_save_failed','No se pudo guardar el rol.');}
        TCT_DB::query('COMMIT');
        // A new role must be visible immediately to the active template resolver.
        wp_cache_delete( 'role_page_' . $id, 'taxocommerce_templates' );
        if($role) update_post_meta($id,'_taxocommerce_seo_role',$role);
        else delete_post_meta($id,'_taxocommerce_seo_role');
        return true;
    }
    public static function page_metabox() {
        add_meta_box('tct-role','TaxoCommerce: rol',array(__CLASS__,'metabox_content'),'page','side');
    }
    public static function metabox_content($post) {
        $role=self::installed()?self::page_role($post->ID):'';
        echo '<p>' . '<label for="tct_role">' . esc_html__( 'Tipo de página', 'taxocommerce-templates' ) . '</label>' . '</p>' . '<select id="tct_role" name="tct_role">' . '<option value="">' . esc_html__( 'Sin rol', 'taxocommerce-templates' ) . '</option>';
        foreach(self::roles() as $key=>$name)echo '<option value="'.esc_attr($key).'" '.selected($role,$key,false).'>'.esc_html($name).'</option>';
        echo '</select>' . '<p class="description">' . esc_html__( 'Se aplica a todas las páginas del mismo rol.', 'taxocommerce-templates' ) . '</p>';
        wp_nonce_field('tct_page_role','tct_page_role_nonce');
        // Save only during the native post save and after all validation.
    }
    public static function save_metabox($id,$post) {
        if(!isset($_POST['tct_page_role_nonce'])||!is_string($_POST['tct_page_role_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['tct_page_role_nonce'])),'tct_page_role'))return;
        if((defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE)||wp_is_post_revision($id)||!current_user_can('edit_page',$id)||!self::installed())return;
        $role=isset($_POST['tct_role']) && is_string($_POST['tct_role']) ? sanitize_key(wp_unslash($_POST['tct_role'])) : '';
        self::set_role($id,$role);
    }
    public static function save_page_role() {
        self::guard('tct_page_role_admin');
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- This handler already verifies its form nonce before using these fields.
        $id=isset($_POST['page_id']) && is_scalar($_POST['page_id']) ? absint($_POST['page_id']) : 0;
        $role=isset($_POST['role']) && is_string($_POST['role']) ? sanitize_key(wp_unslash($_POST['role'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by the handler's existing check_admin_referer guard.
        $result=self::set_role($id,$role);
        self::redirect(is_wp_error($result)?$result->get_error_message():'Rol actualizado.');
    }
    public static function save_role() {
        self::guard('tct_save_role');
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- This handler already verifies its form nonce before using these fields.
        $role=isset($_POST['role']) && is_string($_POST['role']) ? sanitize_key(wp_unslash($_POST['role'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by the handler's existing check_admin_referer guard.
        $name=isset($_POST['name']) && is_string($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by the handler's existing check_admin_referer guard.
        if(!$role||!$name||strlen($role)>80)self::redirect('Rol o nombre inválido.');
        $custom=get_option(self::ROLE_OPTION,array());if(!is_array($custom))$custom=array();
        if(isset(self::base_roles()[$role]))self::redirect('Este rol pertenece a la estructura principal.');
        $custom[$role]=$name;update_option(self::ROLE_OPTION,$custom,false);
        global $wpdb;
        $table=self::templates();
        $exists=TCT_DB::get_var($wpdb->prepare("SELECT id FROM %i WHERE template_key=%s", $table, $role));
        if(!$exists) {
            TCT_DB::insert($table,array(
                'template_key'=>$role,'template_name'=>$name,'template_file'=>'',
                'template_type'=>'page','assignment_mode'=>'multiple','is_assignable'=>1,
                'is_active'=>0,'is_public'=>1,'created_at'=>current_time('mysql'),
                'updated_at'=>current_time('mysql')
            ));
        }
        self::redirect('Rol creado.');
    }
    public static function save_template() {
        self::guard('tct_save_template');
        global $wpdb;
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- This handler already verifies its form nonce before using these fields.
        $role=isset($_POST['role']) && is_string($_POST['role']) ? sanitize_key(wp_unslash($_POST['role'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by the handler's existing check_admin_referer guard.
        $file=isset($_POST['file']) && is_string($_POST['file']) ? sanitize_file_name(wp_unslash($_POST['file'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by the handler's existing check_admin_referer guard.
        $table=self::templates();
        $exists=TCT_DB::get_var($wpdb->prepare("SELECT id FROM %i WHERE template_key=%s", $table, $role));
        if(!$role||!$exists||!TCT_Router::valid_filename($file))self::redirect('Ruta o plantilla no validas.');
        $path=TCT_Router::path($file);
        if(!$path)self::redirect('El archivo no existe en la biblioteca instalada.');
        $fields=array('template_file'=>$file,'is_active'=>1,'updated_at'=>current_time('mysql'));
        $saved=TCT_DB::update($table,$fields,array('id'=>(int)$exists));
        self::redirect(false===$saved?'No se pudo guardar la asignación.':'Plantilla asociada.');
    }
    public static function settings() {
        // The emergency switch must work even if the template tables are
        // damaged. Only capability and nonce are required to change it.
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Acceso denegado.', 'taxocommerce-templates'));
        }
        check_admin_referer('tct_settings');
        $enabled = isset($_POST['enable']) && is_string($_POST['enable']) &&
            '1' === sanitize_text_field(wp_unslash($_POST['enable']));
        // Disable the effective gate FIRST. Re-enable it only after the
        // existing legacy switch has been updated. Safe even after an upgrade.
        update_option(self::REVIEW_CONSENT_OPTION, 0, false);
        update_option(self::ENABLE_OPTION, $enabled ? 1 : 0, false);
        if ($enabled) {
            update_option(self::REVIEW_CONSENT_OPTION, 1, false);
        }
        self::redirect($enabled
            ? 'Políticas de TaxoCommerce Templates activadas.'
            : 'Políticas suspendidas. WordPress, el tema y otros plugins conservan el control.');
    }

    public static function enqueue_policy_control() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation/filter parameter; writes use separate nonce-protected handlers.
        if ( ! current_user_can( 'manage_options' ) || ! isset( $_GET['page'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation/filter, sanitized and without state-changing effects.
            || ! is_string( $_GET['page'] ) || 'taxocommerce-templates' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation/filter, sanitized and without state-changing effects.
            return;
        }
        wp_enqueue_style( 'tct-policy-control', TCT_URL . 'assets/policy-switch.css', array(), TCT_VERSION );
    }

    public static function render_policy_control() {
        $enabled = self::policies_enabled();
        echo '<section class="tct-policy-control" aria-label="' . esc_attr__( 'Aplicación de políticas de plantillas', 'taxocommerce-templates' ) . '">';
        echo '<h2>' . esc_html__( 'Aplicación de políticas', 'taxocommerce-templates' ) . '</h2>';
        echo '<p><strong>' . esc_html__( 'Estado:', 'taxocommerce-templates' ) . ' ' . esc_html( $enabled ? __( 'Aplicando las políticas de Templates', 'taxocommerce-templates' ) : __( 'Políticas suspendidas', 'taxocommerce-templates' ) ) . '</strong></p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('tct_settings');
        echo '<input type="hidden" name="action" value="tct_settings">';
        echo '<label><input type="checkbox" name="enable" value="1" ' . checked($enabled, true, false) . '> ';
        echo '<strong>' . esc_html__( 'Permitir que TaxoCommerce Templates aplique sus plantillas y reglas en la web', 'taxocommerce-templates' ) . '</strong>' . '</label>';
        echo '<p class="description">' . esc_html__( 'Desmarcada: este plugin sigue activo y puedes editar sus plantillas, pero no sustituye páginas, productos, categorías, comentarios, fragmentos WooCommerce ni correos. WordPress y los demás plugins recuperan el control.', 'taxocommerce-templates' ) . '</p>';
        echo '<p class="description">' . '<strong>' . esc_html__( 'Protección al actualizar:', 'taxocommerce-templates' ) . '</strong>' . ' ' . esc_html__( 'tras instalar esta versión las políticas permanecen suspendidas, incluso si una versión anterior tenía el enrutador activo. Solo se habilitan cuando marcas esta casilla y guardas expresamente.', 'taxocommerce-templates' ) . '</p>';
        echo '<p class="description">' . esc_html__( 'Marcada: las rutas configuradas pueden aplicarse según sus prioridades, mercados y reglas. No activa automáticamente plantillas que sigan como borrador.', 'taxocommerce-templates' ) . '</p>';
        submit_button( __( 'Guardar estado de las políticas', 'taxocommerce-templates' ), 'secondary', 'submit', false );
        echo '</form></section>';
    }
    public static function available_files() {
        return TCT_Router::available_files();
    }

    public static function save_device_mode() {
        self::guard('tct_save_device_mode');
        global $wpdb;
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- This handler already verifies its form nonce before using these fields.
        $key = isset($_POST['template_key']) && is_string($_POST['template_key']) ? sanitize_key(wp_unslash($_POST['template_key'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by the handler's existing check_admin_referer guard.
        $table = self::templates();
        $row = TCT_DB::get_row($wpdb->prepare("SELECT template_file FROM %i WHERE template_key=%s", $table, $key));
        if (!$row) {
            self::redirect('Ruta desconocida.');
        }
        $plan = TCT_Router::plan((object)array('template_file'=>$row->template_file, 'device_variants_enabled'=>0), 'mobile');
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- This handler already verifies its form nonce before using these fields.
        $enabled = !empty($_POST['enabled']) ? 1 : 0;
        if (!$enabled && $plan['dispatcher']) {
            self::redirect('La principal es un dispatcher y necesita sus variantes.');
        }
        $saved = TCT_DB::update($table, array('device_variants_enabled'=>$enabled, 'updated_at'=>current_time('mysql')), array('template_key'=>$key));
        self::redirect(false === $saved ? 'Error guardando el modo por dispositivo.' : 'Modo por dispositivo actualizado.');
    }

    public static function assign_page() {
        self::guard('tct_assign_page');
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- This handler already verifies its form nonce before using these fields.
        $id = isset($_POST['page_id']) ? absint($_POST['page_id']) : 0;
        $key = isset($_POST['template_key']) && is_string($_POST['template_key']) ? sanitize_key(wp_unslash($_POST['template_key'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by the handler's existing check_admin_referer guard.
        if (!$id || !$key || !isset(self::roles()[$key])) {
            self::redirect('Pagina o rol incorrecto.');
        }
        global $wpdb;
        $table = self::templates();
        $mode = TCT_DB::get_var($wpdb->prepare(
            "SELECT assignment_mode FROM %i WHERE template_key=%s AND is_assignable=1", $table, $key
        ));
        if (!in_array($mode, array('multiple','single'), true)) {
            self::redirect('Esta ruta se asigna automaticamente.');
        }
        if ('single' === $mode) {
            $nodes = self::nodes();
            $occupied = (int) TCT_DB::get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM %i WHERE seo_role=%s AND object_type='page' AND object_id<>%d AND status=1",
                $nodes, $key, $id
            ));
            if ($occupied) {
                self::redirect('Esta ruta ya tiene una pagina. Desasignala antes.');
            }
        }
        $result = self::set_role($id,$key);
        self::redirect(is_wp_error($result) ? $result->get_error_message() : 'Pagina asignada correctamente.');
    }

    public static function unassign_page() {
        self::guard('tct_unassign_page');
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- This handler already verifies its form nonce before using these fields.
        $id = isset($_POST['page_id']) ? absint($_POST['page_id']) : 0;
        if (!$id || !current_user_can('edit_page', $id)) {
            self::redirect('Pagina no valida o sin permisos.');
        }
        $existing = self::page_role($id);
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- This handler already verifies its form nonce before using these fields.
        $role = isset($_POST['template_key']) && is_string($_POST['template_key']) ? sanitize_key(wp_unslash($_POST['template_key'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by the handler's existing check_admin_referer guard.
        if (!$existing || $existing !== $role) {
            self::redirect('La pagina ya no tiene esa asignacion.');
        }
        $result = self::set_role($id, '');
        self::redirect(is_wp_error($result) ? $result->get_error_message() : 'Pagina desasignada.');
    }

    public static function download_template() {
        if (!current_user_can('manage_options')) {
            wp_die('Acceso denegado.');
        }
        $filename = isset($_GET['file']) && is_string($_GET['file']) ? sanitize_file_name(wp_unslash($_GET['file'])) : '';
        check_admin_referer('tct_download_' . $filename);
        $path = TCT_Router::path($filename);
        if (!$path) {
            wp_die('Plantilla no encontrada.');
        }
        nocache_headers();
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');
        // WP_Filesystem allows the download without using direct readfile().
        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        global $wp_filesystem;
        if (!$wp_filesystem && !WP_Filesystem()) {
            wp_die(esc_html__('No se pudo acceder al archivo solicitado.', 'taxocommerce-templates'));
        }
        $contents = $wp_filesystem->get_contents($path);
        if (false === $contents) {
            wp_die(esc_html__('No se pudo leer la plantilla.', 'taxocommerce-templates'));
        }
        header('Content-Length: ' . strlen($contents));
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Authorized PHP file download, never an HTML response.
        echo $contents;
        exit;
    }

    public static function upload_template() {
        TCT_Template_Editor::upload();
    }

    public static function screen() {
        if (!current_user_can('manage_options')) {
            wp_die('Acceso denegado.');
        }
        // The legacy URLs still resolve, preserving form redirects and old bookmarks.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation/filter parameter; writes use separate nonce-protected handlers.
        $tab = isset($_GET['tab']) && is_string($_GET['tab']) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation/filter, sanitized and without state-changing effects.
            ? sanitize_key(wp_unslash($_GET['tab'])) : 'routing'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation/filter, sanitized and without state-changing effects.
        if (!in_array($tab, array('routing','designer','manager','requests','roles','editor','audit','composer','category-sync','autocheck','manual'), true)) {
            $tab = 'routing';
        }
        $main_tab = in_array($tab, array('autocheck','manual','category-sync'), true) ? $tab : ( in_array($tab, array('routing','requests','roles','audit'), true) ? 'routing' : 'designer' );
        echo '<div class="wrap">' . '<h1>' . esc_html__( 'TaxoCommerce Templates', 'taxocommerce-templates' ) . '</h1>' . '<nav class="nav-tab-wrapper">';
        foreach (array(
            'routing'  => __( 'Enrutamiento', 'taxocommerce-templates' ),
            'designer' => __( 'Diseñador de plantillas', 'taxocommerce-templates' ),
            'category-sync' => __( 'SEO content', 'taxocommerce-templates' ),
            'autocheck' => __( 'Autocheck', 'taxocommerce-templates' ),
            'manual' => __( 'Manual', 'taxocommerce-templates' ),
        ) as $key => $label) {
            echo '<a class="nav-tab ' . ($key === $main_tab ? 'nav-tab-active' : '') . '" href="' .
                esc_url(TCT_Request_Admin::admin_url($key)) . '">' . esc_html($label) . '</a>';
        }
        echo '</nav>';
        self::render_policy_control();
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation/filter parameter; writes use separate nonce-protected handlers.
        if (isset($_GET['tct_notice']) && is_string($_GET['tct_notice'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation/filter, sanitized and without state-changing effects.
            echo '<div class="notice notice-info"><p>' . esc_html(sanitize_text_field(wp_unslash($_GET['tct_notice']))) . '</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation/filter, sanitized and without state-changing effects.
        }
        echo '</div>';

        if ('autocheck' === $main_tab) {
            TCT_Autocheck::render();
        } elseif ('manual' === $main_tab) {
            TCT_Manual::render();
        } elseif ('category-sync' === $main_tab) {
            TCT_Category_Content_Sync::render();
        } elseif ('routing' === $main_tab) {
            if ('audit' === $tab) {
                TCT_Association_Audit::render();
            } elseif ('roles' === $tab) {
                // Advanced legacy page-to-role mappings remain accessible.
                self::render_roles_screen();
            } elseif ('requests' === $tab) {
                TCT_Request_Admin::render();
            } else {
                TCT_Routing_Admin::render();
            }
        } else {
            TCT_Unified_Manager::render($tab);
        }
    }

    public static function render_roles_screen($embedded = false) {
        if (!current_user_can('manage_options')) {
            wp_die('Acceso denegado.');
        }
        if (!self::installed()) {
            echo '<p>' . esc_html__( 'Faltan tablas; reactiva el módulo.', 'taxocommerce-templates' ) . '</p>';
            return;
        }
        global $wpdb;
        $rows = TCT_Router::rows();
        $files = TCT_Router::available_files();
        $node_table = self::nodes();
        $associations = TCT_DB::get_results($wpdb->prepare("SELECT object_id, seo_role FROM %i WHERE object_type='page' AND status=1", $node_table));
        $by_role = array();
        $occupied = array();
        foreach ((array)$associations as $entry) {
            $by_role[$entry->seo_role][] = (int)$entry->object_id;
            $occupied[(int)$entry->object_id] = $entry->seo_role;
        }
        $pages = get_posts(array('post_type'=>'page','post_status'=>'publish','posts_per_page'=>-1,'orderby'=>'title','order'=>'ASC','fields'=>'ids'));
        if (!$embedded) {
            echo '<div class="wrap">' . '<h1>' . esc_html__( 'Gestor de Plantillas', 'taxocommerce-templates' ) . '</h1>';
        }
        echo '<p>' . esc_html__( 'Rutas migradas del gestor original. No se incluyen ni ejecutan archivos PHP del plugin antiguo.', 'taxocommerce-templates' ) . '</p>';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation/filter parameter; writes use separate nonce-protected handlers.
        if (!$embedded && isset($_GET['tct_notice']) && is_string($_GET['tct_notice'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation/filter, sanitized and without state-changing effects.
            echo '<div class="notice notice-info"><p>' . esc_html(sanitize_text_field(wp_unslash($_GET['tct_notice']))) . '</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation/filter, sanitized and without state-changing effects.
        }
        echo '<p class="description">' . esc_html__( 'El interruptor para aplicar o suspender las políticas está disponible al principio de la página, sin necesidad de desactivar el plugin.', 'taxocommerce-templates' ) . '</p>';
        echo '<h2>' . esc_html__( 'Archivos de plantilla', 'taxocommerce-templates' ) . '</h2>';
        echo '<p>La subida, duplicado, descarga, edicion y variantes se gestionan ahora desde <a href="' . esc_url(TCT_Request_Admin::admin_url('editor')) . '"><strong>Editor y biblioteca</strong></a>.</p>';
        echo '<h2>' . esc_html__( 'Asignacion a paginas y rutas automaticas', 'taxocommerce-templates' ) . '</h2>';
        echo '<p>' . esc_html__( 'Las rutas automaticas se resuelven por tipo de peticion. Las rutas asignables admiten paginas existentes; las variantes no son roles independientes.', 'taxocommerce-templates' ) . '</p>';
        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:14px">';
        foreach ($rows as $row) {
            $key = $row->template_key;
            $principal = TCT_Router::path($row->template_file);
            $is_assignable = (int)$row->is_assignable && in_array($row->assignment_mode,array('single','multiple'),true);
            echo '<section style="background:#fff;border:1px solid #c3c4c7;padding:15px;min-width:0">';
            echo '<h3 style="margin:0 0 5px">' . esc_html($row->template_name) . '</h3>';
            echo '<p><code>' . esc_html($key) . '</code> · ' . esc_html($row->template_type) . ' · ';
            echo $is_assignable ? esc_html('Asignable: ' . $row->assignment_mode) : 'Automatica';
            echo '</p><p><strong>Principal:</strong> <code>' . esc_html($row->template_file) . '</code> — ';
            echo $principal ? 'Archivo disponible' : '<strong style="color:#a00">Archivo pendiente</strong>';
            echo '</p>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('tct_save_template');
            echo '<input type="hidden" name="action" value="tct_save_template"><input type="hidden" name="role" value="' . esc_attr($key) . '">';
            echo '<select name="file" style="max-width:100%">' . '<option value="">' . esc_html__( 'Elegir archivo principal', 'taxocommerce-templates' ) . '</option>';
            foreach ($files as $file) {
                echo '<option value="' . esc_attr($file) . '" ' . selected($file,$row->template_file,false) . '>' . esc_html($file) . '</option>';
            }
            echo '</select> ';
            submit_button( __( 'Asociar archivo', 'taxocommerce-templates' ),'secondary','submit',false);
            echo '</form>';
            if ('email' !== $row->template_type) {
                $mobile = TCT_Router::plan($row,'mobile');
                $desktop = TCT_Router::plan($row,'desktop');
                echo '<details style="margin-top:10px">' . '<summary>' . esc_html__( 'Variantes telefono / ordenador', 'taxocommerce-templates' ) . '</summary>';
                echo '<p>Telefono: <code>' . esc_html($mobile['variant']) . '</code> — ' . esc_html($mobile['source']) . '</p>';
                echo '<p>Ordenador: <code>' . esc_html($desktop['variant']) . '</code> — ' . esc_html($desktop['source']) . '</p>';
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                wp_nonce_field('tct_save_device_mode');
                echo '<input type="hidden" name="action" value="tct_save_device_mode"><input type="hidden" name="template_key" value="' . esc_attr($key) . '">';
                echo '<label><input type="checkbox" name="enabled" value="1" ' . checked($mobile['enabled'],true,false) . ($mobile['dispatcher']?' disabled':'') . '> Usar variantes</label> ';
                submit_button('Guardar','secondary','submit',false);
                echo '</form></details>';
            }
            if ($principal) {
                $download_url = wp_nonce_url(admin_url('admin-post.php?action=tct_download_template&file=' . rawurlencode($row->template_file)), 'tct_download_' . $row->template_file);
                echo '<p><a href="' . esc_url($download_url) . '">Descargar principal</a> · ';
                echo '<a href="' . esc_url(TCT_Request_Admin::admin_url('editor',array('tct_source'=>$row->template_file))) . '">Ver / editar codigo</a></p>';
            }
            if ($is_assignable) {
                echo '<h4>' . esc_html__( 'Paginas asignadas', 'taxocommerce-templates' ) . '</h4>';
                $assigned = isset($by_role[$key]) ? $by_role[$key] : array();
                if (!$assigned) {
                    echo '<p>' . esc_html__( 'Ninguna pagina asignada.', 'taxocommerce-templates' ) . '</p>';
                }
                foreach ($assigned as $id) {
                    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:5px 0">';
                    wp_nonce_field('tct_unassign_page');
                    echo '<input type="hidden" name="action" value="tct_unassign_page">';
                    echo '<input type="hidden" name="template_key" value="' . esc_attr($key) . '">';
                    echo '<input type="hidden" name="page_id" value="' . (int)$id . '">';
                    echo esc_html(get_the_title($id)) . ' <code>#' . (int)$id . '</code> ';
                    submit_button( __( 'Desasignar', 'taxocommerce-templates' ),'secondary','submit',false);
                    echo '</form>';
                }
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                wp_nonce_field('tct_assign_page');
                echo '<input type="hidden" name="action" value="tct_assign_page"><input type="hidden" name="template_key" value="' . esc_attr($key) . '">';
                echo '<select name="page_id" required style="max-width:100%">' . '<option value="">' . esc_html__( 'Seleccionar pagina libre', 'taxocommerce-templates' ) . '</option>';
                foreach ($pages as $id) {
                    if (isset($occupied[$id])) {
                        continue;
                    }
                    echo '<option value="' . esc_attr($id) . '">' . esc_html(get_the_title($id)) . ' (#' . esc_html($id) . ')</option>';
                }
                echo '</select> ';
                submit_button( __( 'Asignar pagina', 'taxocommerce-templates' ),'secondary','submit',false);
                echo '</form>';
            }
            echo '</section>';
        }
        echo '</div>';
        echo '<h2>Crear un rol adicional</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('tct_save_role');
        echo '<input type="hidden" name="action" value="tct_save_role"><input name="role" placeholder="clave_rol" required> <input name="name" placeholder="Nombre del rol" required> ';
        submit_button( __( 'Crear rol', 'taxocommerce-templates' ),'secondary','submit',false);
        echo '</form>';
        if (!$embedded) {
            echo '</div>';
        }
    }

}
register_activation_hook(__FILE__,array('TaxoCommerce_Templates','activate'));
add_action('plugins_loaded',array('TaxoCommerce_Templates','boot'),30);

/* Native local gettext catalog fallback (#2062). */
add_action( 'init', static function () {
    $domain = 'taxocommerce-templates';
    if ( is_textdomain_loaded( $domain ) ) {
        return;
    }
    $locale = sanitize_file_name( determine_locale() );
    // Prefer official WordPress language packs when installed.
    $official_pack = WP_LANG_DIR . '/plugins/' . $domain . '-' . $locale;
    if ( is_readable( $official_pack . '.mo' ) || is_readable( $official_pack . '.l10n.php' ) ) {
        return;
    }
    $catalog = __DIR__ . '/languages/' . $domain . '-' . $locale . '.mo';
    if ( ! is_readable( $catalog ) ) {
        // Use a bundled base-language catalog for WordPress locale variants.
        $language = strtolower( substr( $locale, 0, 2 ) );
        $fallback_locales = array(
            'es' => 'es_ES',
            'en' => 'en_US',
            'fr' => 'fr_FR',
            'de' => 'de_DE',
            'nl' => 'nl_NL',
        );
        if ( isset( $fallback_locales[ $language ] ) ) {
            $catalog = __DIR__ . '/languages/' . $domain . '-' . $fallback_locales[ $language ] . '.mo';
        }
    }
    if ( is_readable( $catalog ) ) {
        load_textdomain( $domain, $catalog );
    }
}, 1 );
