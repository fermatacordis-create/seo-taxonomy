<?php
$root = dirname( __DIR__ );
$repo = dirname( $root );

$main = file_get_contents( $root . '/taxocommerce-google-bing.php' );
$admin = file_get_contents( $root . '/includes/class-taxocommerce-connections-admin.php' );
$amazon = file_get_contents( $root . '/includes/class-taxocommerce-connections-amazon.php' );
$pinterest = file_get_contents( $root . '/includes/class-taxocommerce-connections-pinterest.php' );
$google_bing = file_get_contents( $root . '/includes/class-taxocommerce-connections-google-bing.php' );
$oauth = file_get_contents( $root . '/includes/class-taxocommerce-google-bing-oauth.php' );
$core = file_get_contents( $repo . '/taxocommerce-core/includes/class-taxocommerce-core.php' );
$readme = file_get_contents( $root . '/readme.txt' );

preg_match( '/\* Version: ([0-9]+\.[0-9]+\.[0-9]+)/', $main, $header_version );
preg_match( "/define\\( 'TAXOCOMMERCE_GOOGLE_BING_VERSION', '([^']+)' \\);/", $main, $constant_version );
preg_match( '/Stable tag: ([0-9]+\.[0-9]+\.[0-9]+)/', $readme, $readme_version );

$checks = array(
    ! empty( $header_version[1] ) && $header_version[1] === ( $constant_version[1] ?? '' ) && $header_version[1] === ( $readme_version[1] ?? '' ),
    strpos( $main, "'connections'      => 'Connections'" ) !== false,
    strpos( $main, "TaxoCommerce_Connections_Admin::render()" ) !== false,
    strpos( $main, "class-taxocommerce-connections-amazon.php" ) !== false,
    strpos( $main, "class-taxocommerce-connections-pinterest.php" ) !== false,
    strpos( $main, "TaxoCommerce_Connections_Amazon::boot()" ) !== false,
    strpos( $main, "TaxoCommerce_Connections_Pinterest::boot()" ) !== false,
    strpos( $admin, "'page'    => 'taxocommerce-google-bing'" ) !== false,
    strpos( $admin, "'tgb_tab' => 'connections'" ) !== false,
    strpos( $main, "'google_search'    => 'Google Search'" ) !== false,
    strpos( $main, "'google_analytics' => 'Google Analytics'" ) !== false,
    strpos( $main, "'bing'             => 'Bing'" ) !== false,
    strpos( $main, "'google_trends'    => 'Google Trends'" ) !== false,
    // OAuth external redirect uses the WordPress safe redirect with a narrow Google host allowlist.
    strpos( $oauth, 'wp_safe_redirect( esc_url_raw( $url ), 302' ) !== false,
    strpos( $oauth, "'accounts.google.com'" ) !== false,
    strpos( $oauth, 'remove_filter( \'allowed_redirect_hosts\'' ) !== false,
    strpos( $oauth, 'wp_redirect( esc_url_raw' ) === false,
    strpos( $main, "'analyst'" ) === false,
    strpos( $main, "'mercado'" ) === false,
    strpos( $main, "'informe'" ) === false,
    strpos( $amazon, "const OPTION        = 'taxocommerce_connections_amazon'" ) !== false,
    strpos( $amazon, "const LEGACY_OPTION = 'seo_amazon_creators_recipe_settings'" ) !== false,
    strpos( $pinterest, "const OPTION        = 'taxocommerce_connections_pinterest'" ) !== false,
    strpos( $pinterest, "const LEGACY_OPTION = 'seo_social_network_settings_v1'" ) !== false,
    strpos( $google_bing, "const GOOGLE_OPTION           = 'taxocommerce_google_bing_google_settings'" ) !== false,
    strpos( $google_bing, "const BING_OPTION             = 'taxocommerce_google_bing_bing_settings'" ) !== false,
    strpos( $google_bing, "const MERCHANT_ACCOUNT_OPTION = 'taxocommerce_google_bing_merchant_account'" ) !== false,
    strpos( $oauth, "'tgb_tab'        => 'connections'" ) !== false,
    ! is_dir( $repo . '/taxocommerce-connections' ), // Obsolete bridge must stay retired.
    strpos( $core, "'connections' => array(" ) === false,
    strpos( $core, "'taxocommerce-connections'   => 'Connections'" ) === false,
);

foreach ( $checks as $index => $ok ) {
    if ( ! $ok ) {
        print( 'FAIL merged Connections guard ' . ( $index + 1 ) . PHP_EOL );
        exit( 1 );
    }
}

echo "Insights merged-connections guards passed.\n";
