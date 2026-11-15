<?php
/**
 * Self-contained TaxoCommerce family branding, without a Core dependency.
 * Presentation only: no changes to plugin routing, forms or permissions.
 */
defined( 'ABSPATH' ) || exit;

( static function () {

$tc_brand_slug = basename( dirname( __DIR__ ) );
$tc_brand_main = dirname( __DIR__ ) . '/' . $tc_brand_slug . '.php';
$tc_brand_info = require __DIR__ . '/official-excerpt.php';
$tc_brand_on_screen = static function () use ( $tc_brand_slug ) {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only WordPress admin route.
    $page = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    return $page === $tc_brand_slug || 0 === strpos( $page, $tc_brand_slug . '-' );
};

add_filter( 'admin_body_class', static function ( $classes ) use ( $tc_brand_on_screen ) {
    return $tc_brand_on_screen() ? $classes . ' tc-family-screen' : $classes;
} );

add_action( 'admin_enqueue_scripts', static function () use ( $tc_brand_on_screen, $tc_brand_slug ) {
    if ( ! $tc_brand_on_screen() ) {
        return;
    }
    wp_register_style( $tc_brand_slug . '-family-header', false, array(), '1.0.0' );
    wp_enqueue_style( $tc_brand_slug . '-family-header' );
    wp_add_inline_style( $tc_brand_slug . '-family-header', '.tc-family-hero{position:relative;display:grid;grid-template-columns:74px minmax(220px,1.1fr) minmax(300px,1.65fr) max-content;gap:18px;align-items:center;min-height:155px;margin:18px 20px 17px 0;padding:26px 30px;overflow:hidden;box-sizing:border-box;color:#17243b;border:1px solid #dfe6ef;border-left:4px solid #1769de;border-radius:13px;background:linear-gradient(110deg,#fff 0%,#f7faff 70%,#e9f1ff 100%);box-shadow:0 3px 14px rgba(20,45,85,.055)}
.tc-family-hero *{box-sizing:border-box}
.tc-family-hero__emblem{z-index:1;display:grid;place-items:center;width:74px;height:74px;border-radius:50%;background:#e7f0ff;color:#1769de}
.tc-family-hero__emblem .dashicons{width:43px;height:43px;font-size:43px}
.tc-family-hero__copy{position:relative;z-index:1;min-width:0}
.tc-family-hero__eyebrow{margin:0 0 5px!important;font-size:11px;font-weight:800;letter-spacing:.09em;text-transform:uppercase;color:#7686a0}
.tc-family-hero h1{margin:0 0 8px;font-size:29px;line-height:1.2;letter-spacing:-.035em;font-weight:750;color:#122038}
.tc-family-hero__desc{margin:0!important;font-size:14px;line-height:1.6;color:#47576f}
.tc-family-hero__link{display:inline-block;margin-top:9px;color:#145fc5;font-size:13px;line-height:1.5;text-decoration:underline;text-underline-offset:2px}
.tc-family-hero__link:focus-visible{outline:2px solid #1769de;outline-offset:3px}
.tc-family-hero__history-zone{position:relative;z-index:1;width:100%;min-width:0}
.tc-family-history{width:100%;min-width:0;margin:0;border:1px solid #dfe6ef;border-radius:7px;background:#fff;color:#26354c}
.tc-family-history summary{display:flex;align-items:center;min-height:44px;padding:11px 14px;cursor:pointer;font-size:13px;font-weight:600}
.tc-family-history summary:focus-visible{outline:2px solid #1769de;outline-offset:2px}
.tc-family-history__content{max-height:320px;overflow:auto;padding:14px;border-top:1px solid #e4e9f0;white-space:pre-wrap;overflow-wrap:anywhere;font:12px/1.6 monospace}
.tc-family-hero__meta{position:relative;z-index:2;display:flex;flex-direction:column;align-items:flex-end;gap:7px;justify-self:end;white-space:nowrap}
.tc-family-hero__version,.tc-family-hero__updated{padding:5px 11px;border:1px solid #bdd4f5;border-radius:20px;background:#f1f6ff;color:#145fc5;font-size:12px;font-weight:700}
.tc-family-hero__updated{border-color:#dfe6ef;background:#fff;color:#435369;font-weight:600}
.tc-family-hero__art{position:absolute;right:45px;top:25px;width:150px;height:115px;transform:rotate(-30deg) skew(15deg);opacity:.16;pointer-events:none}
.tc-family-hero__art i{position:absolute;display:block;width:68px;height:68px;border:2px solid #bbd5fc;background:#dae8ff;box-shadow:9px 11px 0 #e2ecff}
.tc-family-hero__art i:nth-child(1){left:75px;top:0;background:#347ef0}
.tc-family-hero__art i:nth-child(2){left:0;top:24px}
.tc-family-hero__art i:nth-child(3){left:112px;top:77px}
body.tc-family-screen #wpcontent{background:#f4f6f9}
body.tc-family-screen .wrap .nav-tab-wrapper{display:flex;flex-wrap:wrap;gap:7px;border-bottom:1px solid #dfe6ef;padding:0 0 9px}
body.tc-family-screen .wrap .nav-tab{margin:0;background:#fff;border:1px solid #dfe6ef;border-radius:7px;padding:9px 14px;font-weight:650;color:#455572}
body.tc-family-screen .wrap .nav-tab-active{background:#e9f2ff;border-color:#b5d0f5;color:#115bc7}
@media(max-width:1180px){.tc-family-hero{grid-template-columns:74px minmax(0,1fr) max-content}.tc-family-hero__copy{grid-column:2;grid-row:1}.tc-family-hero__meta{grid-column:3;grid-row:1}.tc-family-hero__history-zone{grid-column:2/4;grid-row:2}}
@media(max-width:700px){.tc-family-hero{grid-template-columns:48px minmax(0,1fr);padding:20px 15px;gap:12px;margin-right:10px}.tc-family-hero__emblem{width:48px;height:48px;grid-column:1;grid-row:1}.tc-family-hero__emblem .dashicons{width:28px;height:28px;font-size:28px}.tc-family-hero h1{font-size:23px}.tc-family-hero__copy{grid-column:2;grid-row:1}.tc-family-hero__meta{grid-column:2;grid-row:2;justify-self:start;align-items:flex-start;white-space:normal}.tc-family-hero__history-zone{grid-column:1/-1;grid-row:3}.tc-family-hero__art{display:none}.tc-family-hero__updated{white-space:normal}}' );
} );

add_action( 'admin_notices', static function () use ( $tc_brand_on_screen, $tc_brand_main, $tc_brand_info, $tc_brand_slug ) {
    if ( ! $tc_brand_on_screen() || ! is_readable( $tc_brand_main ) ) {
        return;
    }
    $plugin = get_file_data( $tc_brand_main, array( 'name' => 'Plugin Name', 'version' => 'Version', 'released' => 'Release Date', 'description' => 'Description' ) );
    $title = preg_replace( '/\\s+-\\s+.*$/', '', (string) ( $plugin['name'] ?? '' ) );
    $version = (string) ( $plugin['version'] ?? '' );
    $released = (string) ( $plugin['released'] ?? '' );
    // Prefer native plugin gettext catalogs, with predictable fallbacks for
    // the five supported base languages until new catalogs are compiled.
    $tc_version_label = __( 'Version', 'taxocommerce-customers' );
    $tc_updated_label = __( 'Last updated', 'taxocommerce-customers' );
    $tc_languages = array(
        'es' => array( 'Versión', 'Última actualización' ),
        'en' => array( 'Version', 'Last updated' ),
        'fr' => array( 'Version', 'Dernière mise à jour' ),
        'de' => array( 'Version', 'Zuletzt aktualisiert' ),
        'nl' => array( 'Versie', 'Laatst bijgewerkt' ),
    );
    $tc_language = strtolower( substr( determine_locale(), 0, 2 ) );
    if ( isset( $tc_languages[ $tc_language ] ) ) {
        if ( 'Version' === $tc_version_label ) {
            $tc_version_label = $tc_languages[ $tc_language ][0];
        }
        if ( 'Last updated' === $tc_updated_label ) {
            $tc_updated_label = $tc_languages[ $tc_language ][1];
        }
    }

    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $released ) ) {
        $released = '';
    }
    if ( '' === $title ) {
        return;
    }
    $tc_history_file = dirname( __DIR__ ) . '/CHANGELOG.md';
    $tc_history = is_readable( $tc_history_file ) ? file_get_contents( $tc_history_file ) : '';
    $tc_history_labels = array(
        'es' => 'Historial de versiones y mejoras',
        'en' => 'Version history and improvements',
        'fr' => 'Historique des versions et améliorations',
        'de' => 'Versionsverlauf und Verbesserungen',
        'nl' => 'Versiegeschiedenis en verbeteringen',
    );
    $tc_history_label = __( 'Version history and improvements', 'taxocommerce-customers' );
    if ( 'Version history and improvements' === $tc_history_label && isset( $tc_history_labels[ $tc_language ] ) ) {
        $tc_history_label = $tc_history_labels[ $tc_language ];
    }
    ?>
    <header class="tc-family-hero">
        <span class="tc-family-hero__emblem" aria-hidden="true"><span class="dashicons dashicons-groups"></span></span>
        <div class="tc-family-hero__copy">
            <p class="tc-family-hero__eyebrow">TaxoCommerce</p>
            <h1><?php echo esc_html( $title ); ?></h1>
            <p class="tc-family-hero__desc"><?php echo esc_html( trim( wp_strip_all_tags( (string) ( $plugin['description'] ?? '' ) ) ) ); ?></p>
            <?php $tc_brand_info->render( $tc_brand_slug, $tc_language ); ?>
        </div>
        <div class="tc-family-hero__history-zone">
            <?php if ( is_string( $tc_history ) && '' !== trim( $tc_history ) ) : ?>
                <details class="tc-family-history">
                    <summary><?php echo esc_html( $tc_history_label ); ?></summary>
                    <div class="tc-family-history__content"><?php echo esc_html( $tc_history ); ?></div>
                </details>
            <?php endif; ?>
        </div>
        <div class="tc-family-hero__meta">
            <span class="tc-family-hero__version"><?php echo esc_html( $tc_version_label . ' ' . $version ); ?></span>
            <span class="tc-family-hero__updated"><?php echo esc_html( $tc_updated_label . ': ' . $released ); ?></span>
        </div>
        <span class="tc-family-hero__art" aria-hidden="true"><i></i><i></i><i></i></span>
    </header>
    <?php
}, 5 );
} )();
