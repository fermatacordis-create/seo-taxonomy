<?php
/**
 * Core homepage Extensions layout regression, no WordPress database needed.
 * php .github/tests/taxocommerce-core/extensions-dashboard-smoke.php
 */
$core = file_get_contents( ( dirname( __DIR__, 3 ) . '/taxocommerce-core' ) . '/includes/class-taxocommerce-core.php' );
$home = file_get_contents( ( dirname( __DIR__, 3 ) . '/taxocommerce-core' ) . '/includes/class-taxocommerce-core-dashboard.php' );
$css  = file_get_contents( ( dirname( __DIR__, 3 ) . '/taxocommerce-core' ) . '/assets/admin.css' );
function tcc_extension_assert( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, 'FAIL Core Extensions dashboard: ' . $message . PHP_EOL );
        exit( 1 );
    }
}
tcc_extension_assert( false !== strpos( $core, 'public function render_extension_catalog()' ), 'the extension catalogue renderer exists' );
tcc_extension_assert( false !== strpos( $home, 'call_user_func( $extensions_renderer )' ), 'Core homepage renders the extension catalogue' );
tcc_extension_assert( false !== strpos( $home, 'is_callable( $extensions_renderer )' ), 'renderer callback is guarded' );
tcc_extension_assert( false === strpos( $home, 'tcc-migration' ), 'redundant migration table removed' );
tcc_extension_assert( false === strpos( $home, "'slug'        => 'taxocommerce-core-modules'" ), 'Extensions navigation duplicate removed' );
tcc_extension_assert( false === strpos( $core, "'taxocommerce-core-modules',\n\t\t\tarray( \$this, 'render_modules' )" ), 'Extensions submenu registration removed' );
tcc_extension_assert( false !== strpos( $core, 'redirect_old_extensions_page' ), 'old bookmarks redirect to Core' );
tcc_extension_assert( false !== strpos( $core, "admin.php?page=taxocommerce-core#tcc-extensions" ), 'old bookmark has anchored target' );
tcc_extension_assert( false !== strpos( $core, 'get_plugins()' ), 'live installed plugins are detected' );
tcc_extension_assert( false !== strpos( $core, "extension_is_active( \$plugin_file )" ), 'live activation state is checked' );
tcc_extension_assert( false !== strpos( $core, 'local_package_path( $extension )' ), 'local ZIP is detected' );
tcc_extension_assert( false !== strpos( $core, 'tcc-extensions-grid' ), 'cards exist' );
foreach ( array( 'taxocommerce_core_install_extension', 'taxocommerce_core_upload_extension', 'taxocommerce_core_activate_extension', 'taxocommerce_core_deactivate_extension' ) as $action ) {
    tcc_extension_assert( false === strpos( $core, 'admin_post_' . $action ), $action . ' must not be registered by Core' );
    tcc_extension_assert( false === strpos( $core, 'value="' . $action . '"' ), $action . ' must not be submitted from Core cards' );
}
tcc_extension_assert( false === strpos( $core, 'new Plugin_Upgrader' ), 'Core does not install other plugins' );
tcc_extension_assert( false === strpos( $core, 'activate_plugin(' ), 'Core does not activate other plugins' );
tcc_extension_assert( false === strpos( $core, 'deactivate_plugins(' ), 'Core does not deactivate other plugins' );
tcc_extension_assert( false === strpos( $core, 'wp_add_inline_style(' ), 'Core does not generate dynamic inline CSS' );
tcc_extension_assert( false !== strpos( $core, "'activate_url' =>" ), 'native WordPress activation link exists' );
tcc_extension_assert( false !== strpos( $core, "'deactivate_url' =>" ), 'native WordPress deactivation link exists' );
tcc_extension_assert( false !== strpos( $core, 'plugin-install.php?tab=upload' ), 'manual ZIP upload uses native WordPress' );
tcc_extension_assert( false !== strpos( $core, 'public function download_extension()' ), 'local package download remains available' );
tcc_extension_assert( false !== strpos( $core, "check_admin_referer( 'taxocommerce_core_download_extension' )" ), 'local download has CSRF protection' );
tcc_extension_assert( false !== strpos( $core, "current_user_can( 'install_plugins' )" ), 'install controls respect capability' );
tcc_extension_assert( false !== strpos( $core, "current_user_can( 'activate_plugins' )" ), 'activation controls respect capability' );
tcc_extension_assert( false !== strpos( $css, '@media(max-width:782px){.tcc-extensions-grid{grid-template-columns:1fr}' ), 'single-column mobile cards' );
echo "Core Extensions dashboard regression passed.\n";
