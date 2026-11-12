<?php
/**
 * Lightweight non-WordPress smoke tests for the native Auditor.
 * CI runs this on PHP 7.4 and PHP 8.4. Integration still needs STAGING.
 */
define( 'ABSPATH', __DIR__ );

// WordPress gettext returns its source string for unloaded locale in offline tests.
function __( $text, $domain = '' ) { return $text; }
function esc_html_e( $text, $domain = '' ) { echo htmlspecialchars( __( $text, $domain ), ENT_QUOTES, 'UTF-8' ); }
function esc_attr__( $text, $domain = '' ) { return __( $text, $domain ); }
function remove_accents( $v ) { return $v; }
function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }
function strip_shortcodes( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function sanitize_key( $v ) { return strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string) $v ) ); }
function sanitize_title( $v ) { return strtolower( str_replace( ' ', '-', (string) $v ) ); }
function is_wp_error( $v ) { return false; }
function apply_filters( $name, $value ) { return $value; }
function taxonomy_exists( $name ) { return in_array( $name, array( 'tc_semantic_tag', 'tc_tipo', 'product_cat' ), true ); }
function get_post_meta( $id, $key, $single = true ) {
    if ( '_taxocommerce_core_seo_role' === $key ) {
        $roles = array( 10 => 'hub_secondary', 12 => 'landing', 13 => 'landing' );
        return $roles[ $id ] ?? '';
    }
    return $single ? '' : array();
}
function get_term_meta( $id, $key, $single = true ) { return $single ? array() : array(); }
function wc_get_page_id( $slug ) { return -1; }
function get_post_type( $id ) { $p = get_post( $id ); return $p ? $p->post_type : false; }
function get_post_status( $id ) { return get_post( $id ) ? 'publish' : false; }
function get_the_title( $id ) { $p = get_post( $id ); return $p ? $p->post_title : ''; }
function term_exists( $id, $tax ) { return $id === 11 ? $id : false; }
function wc_attribute_label( $value ) { return $value; }
function wc_get_product_terms( $id, $tax, $args = array() ) { return array(); }

class WP_Post {
    public $ID;
    public $post_type;
    public $post_title;
    public $post_name;
    public $post_status = 'publish';
    public $post_excerpt;
    public $post_content;
    public function __construct( $id, $title, $slug, $type = 'post', $excerpt = '', $content = '' ) {
        $this->ID = $id;
        $this->post_type = $type;
        $this->post_title = $title;
        $this->post_name = $slug;
        $this->post_excerpt = $excerpt;
        $this->post_content = $content;
    }
}
class WP_Term {
    public $term_id;
    public $name;
    public $slug;
    public $description;
    public $count;
    public $parent;
    public $object_id;
    public function __construct( $id = 11, $name = 'Taladros a bateria', $count = 8, $parent = 0 ) {
        $this->term_id = $id;
        $this->name = $name;
        $this->slug = sanitize_title( $name );
        $this->description = '';
        $this->count = $count;
        $this->parent = $parent;
    }
}

$GLOBALS['tc_fake_posts'] = array(
    1  => new WP_Post( 1, 'Inicio', 'inicio', 'page' ),
    4  => new WP_Post( 4, 'Taladro profesional 18 V', 'taladro-profesional-18-v', 'product' ),
    9  => new WP_Post( 9, 'Contacto', 'contacto', 'page' ),
    10 => new WP_Post( 10, 'Taladros profesionales', 'taladros-profesionales', 'page' ),
    11 => new WP_Post( 11, 'Taladros profesionales', 'taladros-profesionales', 'post' ),
    12 => new WP_Post( 12, 'Taller de herramientas', 'taller-de-herramientas', 'page' ),
    13 => new WP_Post( 13, 'Taller de herramientas', 'taller-de-herramientas', 'page' ),
);
$GLOBALS['tc_fake_terms'] = array(
    11 => new WP_Term( 11, 'Taladros a bateria', 8 ),
    15 => new WP_Term( 15, 'Taladros electricos a bateria', 30 ),
    16 => new WP_Term( 16, 'Taladros a bateria', 2 ),
);

function get_post( $id ) { return $GLOBALS['tc_fake_posts'][ $id ] ?? null; }
function get_posts( $args ) {
    $types = (array) ( $args['post_type'] ?? array( 'post' ) );
    return array_keys( array_filter( $GLOBALS['tc_fake_posts'], static function ( $post ) use ( $types ) {
        return in_array( $post->post_type, $types, true );
    } ) );
}
function get_terms( $args ) {
    if ( isset( $args['parent'] ) ) {
        return array_values( array_filter( $GLOBALS['tc_fake_terms'], static function ( $term ) use ( $args ) {
            return $term->parent === $args['parent'];
        } ) );
    }
    return array_values( $GLOBALS['tc_fake_terms'] );
}
function get_term( $id, $tax = '' ) { return $GLOBALS['tc_fake_terms'][ $id ] ?? null; }
function wp_get_object_terms( $ids, $tax, $args = array() ) {
    if ( 'tc_tipo' === $tax && is_array( $ids ) ) {
        $out = array();
        foreach ( $ids as $i ) {
            $term = new WP_Term( $i < 208 ? 601 : 602, $i < 208 ? 'Tipo A' : 'Tipo B', 0 );
            $term->object_id = $i;
            $out[] = $term;
        }
        return $out;
    }
    return array();
}
class WP_Query {
    public $posts;
    public function __construct( $args ) {
        if ( 'product' === ( $args['post_type'] ?? '' ) && isset( $args['tax_query'] ) ) {
            $this->posts = range( 200, 215 );
        } else {
            $this->posts = array();
        }
    }
}
class WC_Product_Attribute {
    private $name;
    private $values;
    public function __construct( $name, $values ) { $this->name = $name; $this->values = $values; }
    public function get_name() { return $this->name; }
    public function is_taxonomy() { return false; }
    public function get_options() { return $this->values; }
}
class Fake_WC_Product {
    public $attrs = array();
    public function get_short_description() { return ''; }
    public function get_description() { return 'Taladro profesional con bateria y velocidad regulable para trabajo en madera y acero.'; }
    public function get_category_ids() { return array( 11 ); }
    public function get_sku() { return 'SKU-TEST'; }
    public function get_image_id() { return 0; }
    public function get_attributes() { return $this->attrs; }
}
$GLOBALS['tc_wc'] = new Fake_WC_Product();
function wc_get_product( $id ) { return $GLOBALS['tc_wc']; }

require dirname( __DIR__ ) . '/includes/class-faq-source.php';
require dirname( __DIR__ ) . '/includes/class-rules.php';
require dirname( __DIR__ ) . '/includes/class-editorial-specialist.php';
require dirname( __DIR__ ) . '/includes/class-category-review.php';
require dirname( __DIR__ ) . '/includes/class-priority.php';
require dirname( __DIR__ ) . '/includes/class-runner.php';

use TaxoCommerce\Auditor\Rules;
use TaxoCommerce\Auditor\Priority;
use TaxoCommerce\Auditor\Runner;
use TaxoCommerce\Auditor\Editorial_Specialist;
use TaxoCommerce\Auditor\Category_Review;

function verify( $ok, $message ) {
    if ( ! $ok ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

verify( array_sum( Rules::DIMENSIONS ) === 100, 'Quality dimensions still total 100' );
verify( Rules::band( 85 ) === 'fuerte' && Rules::band( 70 ) === 'correcta'
    && Rules::band( 55 ) === 'mejorable' && Rules::band( 54 ) === 'debil', 'Legacy quality bands' );
verify( Rules::band( null ) === 'no_aplica', 'Unknown quality is not zero' );
verify( Rules::identity_key( '  Taladro  Con  Bateria ' ) === 'taladro con bateria', 'Title normalization' );
verify( Rules::title_alignment( 'Taladro profesional bateria', 'Taladro profesional con bateria de litio' ) > 0.5, 'Alignment check' );
verify( Rules::title_alignment( 'X', 'X' ) === null, 'Too-short identities are unknown' );
verify( strlen( Rules::template_signature(
    'Una gran descripción original de materiales y aplicaciones de taladro en trabajos profesionales con muchas piezas de madera.',
    'Taladro profesional'
) ) > 10, 'Template fingerprint' );

$product = Rules::inspect( 'products', get_post( 4 ) );
$codes = array_column( $product['findings'], 'code' );
verify( in_array( 'product_missing_excerpt', $codes, true ), 'Missing product excerpt' );
verify( ! in_array( 'product_without_category', $codes, true ), 'No false missing category' );
verify( isset( $product['quality']['score'] ), 'Internal quality exists' );
verify( 'sku test' === $product['sku_key'], 'SKU used for duplicate candidates' );
$GLOBALS['tc_wc']->attrs = array( new WC_Product_Attribute( 'Tension', array( '12 V' ) ) );
$conflict = Rules::inspect( 'products', get_post( 4 ) );
verify( in_array( 'product_attribute_title_conflict', array_column( $conflict['findings'], 'code' ), true ), 'Legacy voltage conflict rule' );

// Canonical relationship fixtures: only seo_relations can determine parent hubs.
// WordPress term_meta remains empty in this smoke suite.
class TC_Auditor_Fake_DB {
    public $prefix = 'wp_';
    public $last_error = '';
    public $queries = 0;
    public function prepare( $sql, ...$args ) {
        return str_replace( array( '%i', '%s' ), array( '%s', '%s' ), $sql );
    }
    public function get_col( $query ) {
        ++$this->queries;
        return array( 11, 15 );
    }
}
$GLOBALS['wpdb'] = new TC_Auditor_Fake_DB();

$category = Rules::inspect( 'categories', get_term( 11 ) );
verify( in_array( 'category_missing_description', array_column( $category['findings'], 'code' ), true ), 'Missing native category description' );
verify( ! in_array( 'category_missing_excerpt', array_column( $category['findings'], 'code' ), true ), 'No excerpt false positive when field has not been migrated' );
verify( ! in_array( 'category_no_native_secondary_hub', array_column( $category['findings'], 'code' ), true ), 'Canonical hub relation prevents false orphan despite empty term_meta' );
$unlinked_category = Rules::inspect( 'categories', get_term( 16 ) );
verify( in_array( 'category_no_native_secondary_hub', array_column( $unlinked_category['findings'], 'code' ), true ), 'Missing canonical hub relation is reported' );
$empty_unlinked_category = Rules::inspect( 'categories', new WP_Term( 31, 'Categoria vacia sin hub', 0 ) );
verify( in_array( 'category_no_native_secondary_hub', array_column( $empty_unlinked_category['findings'], 'code' ), true ), 'Empty categories also require a secondary hub' );
verify( 1 === $GLOBALS['wpdb']->queries, 'SEO relations loaded once per PHP request, not once per category' );
$small = Category_Review::inspect( get_term( 16 ) );
verify( ! empty( $small['inventory']['review_merge'] ), 'Small category review' );
verify( ! empty( $small['inventory']['merge_proposal'] ), 'Semantically backed sibling suggestion' );
$big = new WP_Term( 30, 'Herramientas a bateria', 16 );
$split = Category_Review::inspect( $big );
verify( ! empty( $split['inventory']['split_proposal'] ), 'Native TIPO cohort split review' );
verify( count( $split['inventory']['proposal']['cohorts'] ) >= 2, 'Split groups have real source evidence' );

$corporate = Rules::inspect( 'pages', get_post( 9 ) );
verify( ! empty( $corporate['excluded'] ), 'Corporate pages excluded' );
verify( 'corporate' === Editorial_Specialist::page_kind( get_post( 1 ) ), 'Missing Woo page -1 never misclassifies page 1' );
$map = Editorial_Specialist::build_map();
$overlap = Editorial_Specialist::inspect( get_post( 11 ), 'posts', $map );
verify( in_array( 'editorial_post_canonical_cannibalization', array_column( $overlap, 'code' ), true ), 'Post-canonical overlap from legacy specialist' );
$landing = Editorial_Specialist::inspect( get_post( 12 ), 'pages', $map );
verify( in_array( 'editorial_landing_duplicate', array_column( $landing, 'code' ), true ), 'Duplicate landings' );

$faq = Rules::inspect( 'faqs', new WP_Post( 21, 'Como se utiliza', 'como-se-utiliza', 'faq' ) );
verify( in_array( 'faq_thin_answer', array_column( $faq['findings'], 'code' ), true ), 'Missing FAQ answer' );
verify( ! in_array( 'faq_orphan_owner', array_column( $faq['findings'], 'code' ), true ), 'Unknown FAQ ownership not invented' );

$canonical_faq = new \TaxoCommerce\Auditor\FAQ_Record( array(
    'id' => 22,
    'object_type' => 3,
    'object_id' => 4,
    'product_cat_id' => 11,
    'question' => 'Que bateria utiliza',
    'answer' => 'Respuesta suficientemente detallada para explicar la compatibilidad y el uso de la bateria del producto.',
    'active' => 1,
) );
$canonical_result = Rules::inspect( 'faqs', $canonical_faq );
verify( empty( array_intersect( array( 'faq_invalid_owner_type', 'faq_orphan_owner', 'faq_missing_product_category' ), array_column( $canonical_result['findings'], 'code' ) ) ), 'Canonical TaxoCommerce FAQ owner/category accepted' );

$must_check = Priority::decorate( array( 'code' => 'product_attribute_title_conflict', 'severity' => 'high', 'entity_type' => 'product', 'entity_id' => 4 ) );
verify( $must_check['priority'] === 'P1' && $must_check['requires_source_check'] && ! $must_check['ready_now'], 'P1 requires evidence and review' );
$enrich = Priority::decorate( array( 'code' => 'product_missing_excerpt', 'severity' => 'medium', 'entity_type' => 'product', 'entity_id' => 4 ) );
verify( $enrich['priority'] === 'P2', 'P2 enrichment' );

verify( Runner::valid_scope( 'products' ) && Runner::valid_scope( 'categories' )
    && Runner::valid_scope( 'posts' ) && Runner::valid_scope( 'pages' )
    && Runner::valid_scope( 'faqs' ), 'All five scopes supported' );
$product_kpis = Runner::scope_kpis( 'products', array(
    'run_mode' => 'full',
    'processed' => 100,
    'excluded' => 0,
    'entities_to_review' => 20,
    'finding_count' => 30,
    'severity_counts' => array( 'critical'=>1, 'high'=>2 ),
    'priority_counts' => array( 'P1'=>3 ),
    'status_counts' => array( 'NEEDS_SOURCE_VERIFICATION'=>2 ),
    'rule_counts' => array(
        'product_missing_description'=>7,
        'product_without_category'=>2,
        'product_without_native_vocabulary'=>5,
    ),
    'quality' => array(
        'average'=>78.5,
        'bands'=>array( 'debil'=>4 ),
    ),
) );
verify( 'complete_scope' === $product_kpis['coverage'], 'Full KPI coverage marker' );
verify( 80 === $product_kpis['general'][1]['value'], 'Clean entity KPI' );
verify( 80.0 === $product_kpis['general'][2]['value'], 'Clean rate KPI' );
verify( 7 === $product_kpis['specific'][0]['value'], 'Product-specific KPI' );

verify( ! Runner::valid_scope( 'invalid' ), 'Invalid scope rejected' );
verify( ! defined( 'SEO_SYSTEM_PATH' ) && ! class_exists( 'SEO_Auditor' ), 'No collisions with old monolith' );

// Guard against reintroducing interpolated identifiers in canonical read-only sources.
$faq_sql_source = file_get_contents( dirname( __DIR__ ) . '/includes/class-faq-source.php' );
$context_sql_source = file_get_contents( dirname( __DIR__ ) . '/includes/class-decision-context.php' );
verify( false === strpos( $faq_sql_source, 'FROM {$table}' ), 'FAQ queries use prepared identifiers' );
verify( false !== strpos( $faq_sql_source, "'SELECT COUNT(*) FROM %i WHERE active=1'" ), 'FAQ count uses WordPress identifier placeholder' );
verify( false !== strpos( $context_sql_source, 'FROM %i' ), 'context provider identifiers prepared' );
verify( 6 === substr_count( $faq_sql_source, 'phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching' ), 'Six canonical FAQ reads have reviewed, targeted DB annotations' );
verify( 5 === substr_count( $context_sql_source, 'phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching' ), 'Five canonical context reads have reviewed, targeted DB annotations' );

$admin_source = file_get_contents( dirname( __DIR__ ) . '/includes/class-admin.php' );
verify( false !== strpos( $admin_source, "is_string( \$_POST['search'] )" ), 'POST search validates scalar before sanitizing' );
verify( false !== strpos( $admin_source, "is_scalar( \$_POST['category_id'] )" ), 'POST category ID ignores malformed arrays' );
verify( false !== strpos( $admin_source, "is_scalar( \$_POST['rows'] )" ), 'POST row count ignores malformed arrays' );

// Regression guards for the eight issues in the old Playground Plugin Check export.
$runner_source = file_get_contents( dirname( __DIR__ ) . '/includes/class-runner.php' );
$editorial_source = file_get_contents( dirname( __DIR__ ) . '/includes/class-editorial-specialist.php' );
$category_source = file_get_contents( dirname( __DIR__ ) . '/includes/class-category-review.php' );
$readme_source = file_get_contents( dirname( __DIR__ ) . '/readme.txt' );
$entry_source = file_get_contents( dirname( __DIR__ ) . '/taxocommerce-auditor.php' );

verify( false !== strpos( $runner_source, 'esc_html( $terms->get_error_message() )' ), 'Taxonomy exceptions are escaped' );
verify( 0 === preg_match( '/[\'"]suppress_filters[\'"]\\s*=>\\s*true/', $editorial_source ), 'Editorial query never suppresses WordPress filters' );
verify( false !== strpos( $editorial_source, "'suppress_filters' => false" ), 'Editorial query keeps WordPress filters enabled' );
verify( false !== strpos( $admin_source, 'sanitize_text_field( wp_unslash( $_POST[\'search\'] ) )' ), 'POST search sanitized at ingress' );
verify( false !== strpos( $admin_source, 'absint( wp_unslash( (string) $_POST[\'category_id\'] ) )' ), 'POST category ID sanitized at ingress' );
verify( false !== strpos( $admin_source, 'absint( wp_unslash( (string) $_POST[\'rows\'] ) )' ), 'POST row limit sanitized at ingress' );
verify( 1 === substr_count( $runner_source, 'phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query' ), 'Filtered cursor batch has one justified query annotation' );
verify( 1 === substr_count( $category_source, 'phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query' ), 'Bounded category cohort has one justified query annotation' );
verify( false !== strpos( $runner_source, 'const BATCH_SIZE = 500;' ), 'Category-filtered cursor batches retain a bounded ceiling' );
verify( false !== strpos( $runner_source, 'Workload::effective_batch_size( $scope, $state )' ), 'Actual batch limit is adaptive, not always 500' );
verify( false !== strpos( $category_source, 'const SAMPLE_MAX = 200;' ), 'Category cohort sample stays bounded' );
verify( 1 === preg_match( '/^Tags:\\s*([^\\r\\n]+)/m', $readme_source, $readme_tags ), 'Plugin readme declares tags' );
verify( count( array_filter( array_map( 'trim', explode( ',', $readme_tags[1] ) ) ) ) <= 5, 'WordPress.org tag count at most five' );
verify( 1 === preg_match( '/Version:\\s*(\\d+\\.\\d+\\.\\d+)/', $entry_source, $observed_version ) && false !== strpos( $readme_source, 'Stable tag: ' . $observed_version[1] ), 'Standalone release version matches readme' );

echo "TaxoCommerce Auditor native parity smoke tests passed.\n";
