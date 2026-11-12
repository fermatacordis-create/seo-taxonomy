<?php
$root = dirname( __DIR__ );
$main = file_get_contents( $root . '/taxocommerce-import-export.php' );
$core = file_get_contents( $root . '/includes/class-taxocommerce-import-export.php' );
$csv = file_get_contents( $root . '/includes/class-taxocommerce-import-export-csv.php' );
$batch = file_get_contents( $root . '/includes/class-taxocommerce-import-export-batch.php' );
$exporter = file_get_contents( $root . '/includes/class-taxocommerce-import-export-exporter.php' );
$exporter_js = file_get_contents( $root . '/assets/exporter.js' );

$checks = array(
    preg_match( '/^ \* Version:\\s*([0-9.]+)$/m', $main, $found_version ) === 1
        && preg_match( '/^Stable tag:\\s*([0-9.]+)$/m', file_get_contents( $root . '/readme.txt' ), $found_stable ) === 1
        && $found_version[1] === $found_stable[1],
    strpos( $main, "class-taxocommerce-import-export-batch.php" ) !== false,
    strpos( $main, "class-taxocommerce-ie-load.php" ) !== false,
    strpos( $batch, "TaxoCommerce_IE_Load::batch_size( 'import' )" ) !== false,
    strpos( $batch, "taxocommerce_import_export_batch_rows_per_tick', 2000" ) !== false,
    strpos( $batch, "taxocommerce_import_export_batch_runtime_seconds', 25" ) !== false,
    strpos( $batch, "row_save_seconds" ) !== false,
    strpos( $batch, "db_queries_per_row" ) !== false,
    strpos( $batch, "TaxoCommerce_IE_Load::delay( 'import' )" ) === false,
    strpos( $exporter, "TaxoCommerce_IE_Load::batch_size( 'export' )" ) !== false,
    strpos( $exporter, "tc-ie-export-priority" ) !== false,
    strpos( $batch, "tc-ie-import-priority" ) !== false,
    strpos( $batch, "queue_individual" ) !== false,
    strpos( $batch, "admin_post_taxocommerce_ie_batch_delete_pending" ) !== false,
    strpos( $batch, "pending_file" ) !== false,
    strpos( $batch, "tc_ie_delete_not_pending" ) !== false,
    strpos( $batch, "handle_delete_pending" ) !== false,
    strpos( $batch, "INDIVIDUAL_STATE" ) !== false,
    strpos( $batch, "individual_status" ) !== false,
    strpos( $core, "tc-ie-individual-monitor" ) !== false,
    strpos( $core, "taxocommerce_ie_individual_status" ) !== false,
    strpos( $csv, 'queue_individual( $file )' ) !== false,
    strpos( $main, "class-taxocommerce-import-export-exporter.php" ) !== false,
    strpos( $core, "Exportación" ) !== false,
    strpos( $core, "new TaxoCommerce_Import_Export_Exporter" ) !== false,
    strpos( $exporter, "Exportación por bloques" ) !== false,
    strpos( $exporter, "'products_reduced'" ) !== false,
    strpos( $exporter, "taxocommerce_ie_export_start" ) !== false,
    strpos( $exporter, "taxocommerce_ie_export_step" ) !== false,
    strpos( $exporter, "taxocommerce_ie_export_status" ) !== false,
    strpos( $exporter, "taxocommerce_ie_export_cancel" ) !== false,
    strpos( $exporter, "taxocommerce_import_export_export_rows_per_block" ) !== false,
    strpos( $exporter, 'ftruncate( $handle, $committed_size )' ) !== false,
    strpos( $exporter, "'file_size'" ) !== false,
    strpos( $exporter, "'examined'" ) !== false,
    strpos( $exporter, "'skipped'" ) !== false,
    strpos( $exporter, "'blocks'" ) !== false,
    strpos( $exporter, "'future', 'trash'" ) !== false,
    strpos( $exporter_js, "registros revisados" ) !== false,
    strpos( $exporter, "taxocommerce_import_export_export_runtime_seconds" ) !== false,
    strpos( $exporter, "_taxocommerce_core_parent_hub_secondary" ) !== false,
    strpos( $exporter, "category_scope_active" ) !== false,
    strpos( $csv, "export_header_for_job" ) !== false,
    strpos( $csv, "export_row_for_job" ) !== false,
    strpos( $csv, "product_inventory_reduced" ) !== false,
    strpos( $csv, "product_vocabulary_export_payload" ) !== false,
    strpos( $csv, "product_seo_attributes_export_payload" ) !== false,
    strpos( $exporter_js, "Generando en segundo plano" ) !== false,
    strpos( $exporter_js, "Reintentar bloque" ) === false || strpos( $exporter, "Reintentar bloque" ) !== false,
    strpos( $core, "Importación por lotes" ) !== false,
    strpos( $core, "new TaxoCommerce_Import_Export_Batch" ) !== false,
    strpos( $csv, "import_row_for_batch" ) !== false,
    strpos( $csv, "normalize_headers_for_batch" ) !== false,
    strpos( $csv, "infer_record_type_from_headers" ) !== false,
    strpos( $csv, "'product_id'            => 'id'") !== false,
    strpos( $csv, "'titulo'                => 'name'") !== false,
    strpos( $csv, "'marca_taxonomia'       => 'brand_taxonomy'") !== false,
    strpos( $csv, "'_partial_update'") !== false,
    strpos( $csv, "taxocommerce_ie_partial_identifier" ) !== false,
    strpos( $csv, "taxocommerce_ie_partial_missing_product" ) !== false,
    strpos( $csv, 'array_key_exists( \'name\', $data )' ) !== false,
    strpos( $csv, "apply_brand_provider_fields" ) !== false,
    strpos( $csv, "resolve_existing_brand_terms" ) !== false,
    strpos( $csv, "product_brand_export_payload" ) !== false,
    strpos( $csv, "detect_delimiter" ) !== false,
    strpos( $batch, "taxocommerce_import_export_batch_importers" ) !== false,
    strpos( $batch, "taxocommerce_import_export_batch_detect_type" ) !== false,
    strpos( $batch, "infer_record_type_from_headers" ) !== false,
    strpos( $batch, "taxocommerce-import-export/queue" ) !== false,
    strpos( $batch, "taxocommerce_ie_batch_worker" ) !== false,
    strpos( $batch, "seo_process_supervisor" ) === false,
    strpos( $batch, "seo_ie_process_import_batch_queue" ) === false,
    strpos( $batch, 'as_schedule_single_action(' ) === false,
    strpos( $batch, 'wp_schedule_single_event(' ) === false,
    strpos( $batch, 'add_action( self::WORKER_HOOK' ) === false,
    strpos( $batch, "Pausar después del archivo actual" ) !== false,
    strpos( $batch, "Detener importación actual" ) !== false,
    strpos( $batch, "detect_delimiter" ) !== false,
    strpos( $batch, "user_id" ) !== false,
    strpos( $batch, "wp_set_current_user" ) !== false,
    strpos( $batch, "remaining" ) !== false,
    strpos( $batch, "worker();" ) !== false,
    strpos( $batch, "wp_ajax_nopriv_taxocommerce_ie_batch_continue" ) !== false,
    strpos( $batch, 'hash_equals( $expected, $submitted )' ) !== false,
    strpos( $batch, "private function dispatch_next()" ) !== false,
    strpos( $batch, "'blocking'    => false" ) !== false,
    strpos( $batch, 'private function schedule( $delay = 60 )' ) === false,
    strpos( $batch, 'public function retire_legacy_schedule()' ) !== false,
    strpos( $batch, 'wp_unschedule_hook( self::WORKER_HOOK )' ) !== false,
    strpos( $batch, "'busy'    => " . '$busy' ) !== false,
    strpos( $batch, "assets/batch-runner.js" ) !== false,

);

foreach ( $checks as $index => $ok ) {
    if ( ! $ok ) {
        fwrite( STDERR, 'FAIL batch queue guard ' . ( $index + 1 ) . PHP_EOL );
        exit( 1 );
    }
}

echo "TaxoCommerce Import-Export batch queue guards passed.\n";

// Runtime regression for abandoned and legacy CSV exports.
require __DIR__ . '/legacy-export-cancel-smoke.php';
