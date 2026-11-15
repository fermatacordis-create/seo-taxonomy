<?php
/**
 * Static, WordPress-independent audit of Autocheck contracts for every plugin.
 * No plugin is activated and no catalogue/queue/provider operations are run.
 */
$root = dirname( __DIR__, 2 );
$ui = array(
    'accounting' => 'taxocommerce-accounting.php',
    'auditor' => 'includes/class-admin.php',
    'campaigns' => 'includes/admin.php',
    'core' => 'includes/class-taxocommerce-core-auto-test.php',
    'customers' => 'includes/class-tccust-admin.php',
    'database' => 'taxocommerce-database.php',
    'dependiente' => 'includes/class-tcd-admin.php',
    'distribution' => 'includes/admin.php',
    'facturacion' => 'includes/class-seo-facturas-admin.php',
    'faqs' => 'includes/class-taxocommerce-faqs.php',
    'google-bing' => 'taxocommerce-google-bing.php',
    'images' => 'includes/class-taxocommerce-images.php',
    'import-export' => 'includes/class-taxocommerce-import-export.php',
    'logistica' => 'includes/seo-transporte.php',
    'redirects' => 'includes/class-admin.php',
    'scrappers' => 'taxocommerce-scrappers.php',
    'social' => 'includes/admin.php',
    'suppliers' => 'includes/class-tcsup-admin.php',
    'system-check' => 'taxocommerce-system-check.php',
    'tasks' => 'taxocommerce-tasks.php',
    'templates' => 'taxocommerce-templates.php',
    'vocabulary' => 'includes/class-tcv-admin.php',
);
$reporter = array(
    'accounting' => 'taxocommerce-accounting.php',
    'auditor' => 'includes/class-auto-test.php',
    'campaigns' => 'includes/autotest.php',
    'core' => 'includes/class-taxocommerce-core-auto-test.php',
    'customers' => 'includes/class-tccust-autotest.php',
    'database' => 'includes/class-tcdb-auto-test.php',
    'dependiente' => 'includes/class-tcd-auto-test.php',
    'distribution' => 'includes/autocheck.php',
    'facturacion' => 'includes/class-taxocommerce-billing-autotest.php',
    'faqs' => 'includes/class-taxocommerce-faqs-auto-test.php',
    'google-bing' => 'includes/class-taxocommerce-google-bing-auto-test.php',
    'images' => 'includes/class-taxocommerce-images-auto-test.php',
    'import-export' => 'includes/class-taxocommerce-import-export-auto-test.php',
    'logistica' => 'includes/class-auto-test.php',
    'redirects' => 'includes/class-auto-test.php',
    'scrappers' => 'includes/class-taxocommerce-scrappers-auto-test.php',
    'social' => 'includes/class-auto-test.php',
    'suppliers' => 'includes/class-tcsup-autotest.php',
    'system-check' => 'includes/class-taxocommerce-system-check-auto-test.php',
    'tasks' => 'taxocommerce-tasks.php',
    'templates' => 'includes/class-tct-autocheck.php',
    'vocabulary' => 'includes/class-tcv-auto-test.php',
);
$actual = glob( $root . '/taxocommerce-*', GLOB_ONLYDIR );
if ( count( $ui ) !== count( $actual ) || array_keys( $ui ) !== array_keys( $reporter ) ) {
    fwrite( STDERR, "Autocheck family inventory has changed; update this audit.\n" );
    exit( 1 );
}
$errors = array();
foreach ( $ui as $slug => $path ) {
    $base = $root . '/taxocommerce-' . $slug;
    $view = $base . '/' . $path;
    $engine = $base . '/' . $reporter[ $slug ];
    if ( ! is_file( $view ) || ! is_file( $engine ) ) {
        $errors[] = "$slug: missing UI or runner";
        continue;
    }
    $ui_source = file_get_contents( $view );
    $code = file_get_contents( $engine );
    // UI must expose the exact, consistent name but retain existing route keys.
    if ( false === strpos( $ui_source, 'Autocheck' ) ) {
        $errors[] = "$slug: no visible Autocheck label";
    }
    foreach ( array( 'wp_json_encode', 'application/json', 'Content-Disposition', 'check_admin_referer' ) as $contract ) {
        if ( false === strpos( $code, $contract ) ) {
            $errors[] = "$slug: missing JSON/export security contract $contract";
        }
    }
    if ( false === strpos( $code, 'rows' ) || false === strpos( $code, 'totals' ) ) {
        $errors[] = "$slug: exported result structure missing rows or totals";
    }
    if ( false === strpos( $code, "'executed_at'" ) || false === strpos( $code, 'Fecha de ejecución:' ) ) {
        $errors[] = "$slug: fecha de ejecución ausente del JSON o de la interfaz";
    }
    // Each new timestamp is generated in the manually executed report; never on download.
    // Support a single UTC timestamp shared by both generated_at and executed_at.
    // Do not force plugins to call gmdate twice just to satisfy a textual test.
    $inline_utc = false !== strpos( $code, "'executed_at' => gmdate( 'c' )" );
    $shared_utc = false !== strpos( $code, "$" . "generated_at = gmdate( 'c' )" )
        && false !== strpos( $code, "'executed_at' => $" . "generated_at" );
    if ( ! $inline_utc && ! $shared_utc ) {
        $errors[] = "$slug: no se registra la ejecución en UTC";
    }
    if ( false === strpos( $code, 'nonce' ) && false === strpos( $code, 'NONCE' ) ) {
        $errors[] = "$slug: missing nonce for JSON download";
    }
}
if ( $errors ) {
    foreach ( $errors as $error ) {
        fwrite( STDERR, "FAIL: $error\n" );
    }
    exit( 1 );
}
echo "PASS: " . count( $ui ) . "/" . count( $actual ) . " TaxoCommerce plugins expose Autocheck and protected JSON result export.\n";
