<?php
/**
 * CSV cancellation of existing social calendar entries, without any publishing.
 *
 * @package TaxoCommerce_Social
 */

defined( 'ABSPATH' ) || exit;


/**
 * Identify cancellation CSVs in the existing scheduling importer.
 *
 * Checking a header does not create, change or cancel any schedule.
 *
 * @param string $path Temporary CSV path.
 * @return bool
 */
function tcsocial_csv_cancel_has_action_column( $path ) {
    if ( ! is_readable( $path ) ) {
        return false;
    }
    $handle = tcsocial_network_csv_stream_open( $path, 'r' );
    if ( false === $handle ) {
        return false;
    }
    $first_line = fgets( $handle );
    if ( false === $first_line ) {
        tcsocial_network_csv_stream_close( $handle );
        return false;
    }
    $delimiter = tcsocial_network_scheduler_detect_delimiter( $first_line );
    rewind( $handle );
    $headers = fgetcsv( $handle, 0, $delimiter, '"', '' );
    tcsocial_network_csv_stream_close( $handle );

    foreach ( (array) $headers as $header ) {
        if ( 'accion' === tcsocial_network_scheduler_normalize_header( $header ) ) {
            return true;
        }
    }
    return false;
}

/**
 * Share the safe cancellation preview between both CSV upload controls.
 * Both entry points validate the upload, capability and nonce first.
 *
 * @param string $path Validated uploaded file path.
 * @return void
 */
function tcsocial_csv_cancel_start_preview( $path ) {
    $parsed = tcsocial_csv_cancel_parse( $path );
    if ( is_wp_error( $parsed ) ) {
        wp_die( esc_html( $parsed->get_error_message() ) );
    }
    $token = wp_generate_password( 24, false, false );
    set_transient( 'tcsocial_cancel_' . get_current_user_id() . '_' . $token, $parsed, 20 * MINUTE_IN_SECONDS );
    wp_safe_redirect( add_query_arg(
        array(
            'page'             => 'taxocommerce-social',
            'tab'              => 'calendar',
            'cancel_csv_token' => $token,
        ),
        admin_url( 'admin.php' )
    ) );
    exit;
}

/**
 * Parse exact cancellation instructions. Dates must match current agenda.
 *
 * @param string $path Uploaded CSV path.
 * @return array|WP_Error
 */
function tcsocial_csv_cancel_parse( $path ) {
    if ( ! is_uploaded_file( $path ) && ! is_readable( $path ) ) {
        return new WP_Error( 'unreadable_csv', __( 'No se puede leer el CSV.', 'taxocommerce-social' ) );
    }
    $handle = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Native CSV stream.
    if ( ! $handle ) {
        return new WP_Error( 'unreadable_csv', __( 'No se puede abrir el CSV.', 'taxocommerce-social' ) );
    }
    $first = fgets( $handle );
    if ( false === $first ) {
        fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Native CSV stream.
        return new WP_Error( 'empty_csv', __( 'El CSV está vacío.', 'taxocommerce-social' ) );
    }
    $delimiter = tcsocial_network_scheduler_detect_delimiter( $first );
    rewind( $handle );
    $headers = fgetcsv( $handle, 0, $delimiter, '"', '' );
    $columns = array();
    foreach ( (array) $headers as $index => $header ) {
        $columns[ tcsocial_network_scheduler_normalize_header( $header ) ] = $index;
    }
    foreach ( array( 'contenido_id', 'redes', 'fecha_hora', 'accion' ) as $required ) {
        if ( ! array_key_exists( $required, $columns ) ) {
            fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Native CSV stream.
            /* translators: %s: required CSV column name. */
            return new WP_Error( 'missing_header', sprintf( __( 'Falta la columna %s.', 'taxocommerce-social' ), $required ) );
        }
    }
    $entries = array();
    $seen = array();
    $line = 1;
    while ( ( $row = fgetcsv( $handle, 0, $delimiter, '"', '' ) ) !== false ) {
        ++$line;
        if ( $line > 2001 ) {
            fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Native CSV stream.
            return new WP_Error( 'too_many_rows', __( 'El límite es 2.000 filas.', 'taxocommerce-social' ) );
        }
        if ( ! array_filter( $row, 'strlen' ) ) {
            continue;
        }
        $id       = absint( $row[ $columns['contenido_id'] ] ?? 0 );
        $provider = sanitize_key( $row[ $columns['redes'] ] ?? '' );
        $date     = sanitize_text_field( $row[ $columns['fecha_hora'] ] ?? '' );
        $action   = sanitize_key( $row[ $columns['accion'] ] ?? '' );
        $errors   = array();
        if ( 'cancelar' !== $action ) {
            $errors[] = __( 'Acción no admitida: debe ser cancelar.', 'taxocommerce-social' );
        }
        if ( ! $id || ! tcsocial_network_get_provider( $provider ) ) {
            $errors[] = __( 'Contenido o red no válidos.', 'taxocommerce-social' );
        }
        $key = $id . '|' . $provider;
        if ( isset( $seen[ $key ] ) ) {
            $errors[] = __( 'Contenido y red repetidos en el CSV.', 'taxocommerce-social' );
        }
        $seen[ $key ] = true;
        $expected = tcsocial_network_scheduler_parse_import_datetime( $date );
        if ( is_wp_error( $expected ) ) {
            $errors[] = $expected->get_error_message();
        }
        $current = ( $id && tcsocial_network_get_provider( $provider ) )
            ? tcsocial_network_get_scheduled_timestamp( $id, $provider ) : 0;
        if ( ! $current ) {
            $errors[] = __( 'No existe una programación que cancelar.', 'taxocommerce-social' );
        } elseif ( ! is_wp_error( $expected ) && (int) $expected !== (int) $current ) {
            $errors[] = __( 'La fecha actual es distinta; no se cancelará una programación modificada.', 'taxocommerce-social' );
        }
        $entries[] = array(
            'id'       => $id,
            'provider' => $provider,
            'date'     => $date,
            'expected' => is_wp_error( $expected ) ? 0 : (int) $expected,
            'title'    => $id ? get_the_title( $id ) : '',
            'errors'   => $errors,
        );
    }
    fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Native CSV stream.
    if ( ! $entries ) {
        return new WP_Error( 'no_rows', __( 'El archivo no contiene registros.', 'taxocommerce-social' ) );
    }
    return $entries;
}

/** Preview an uploaded CSV without altering the agenda. */
function tcsocial_csv_cancel_preview() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Sin permisos.', 'taxocommerce-social' ) );
    }
    check_admin_referer( 'tcsocial_csv_cancel_preview' );
    $file = isset( $_FILES['cancel_csv'] ) && is_array( $_FILES['cancel_csv'] ) ? $_FILES['cancel_csv'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File metadata validated below.
    $filename = sanitize_file_name( (string) ( $file['name'] ?? '' ) );
    if ( ! $file || ! empty( $file['error'] ) || empty( $file['tmp_name'] )
        || (int) ( $file['size'] ?? 0 ) > 2 * MB_IN_BYTES
        || 'csv' !== strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) )
        || ! is_uploaded_file( $file['tmp_name'] ) ) {
        wp_die( esc_html__( 'Selecciona un CSV válido de hasta 2 MB.', 'taxocommerce-social' ) );
    }
    tcsocial_csv_cancel_start_preview( (string) $file['tmp_name'] );
}
add_action( 'admin_post_tcsocial_csv_cancel_preview', 'tcsocial_csv_cancel_preview' );

/** Confirm cancellation only for unchanged exact content/network/date triples. */
function tcsocial_csv_cancel_confirm() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Sin permisos.', 'taxocommerce-social' ) );
    }
    check_admin_referer( 'tcsocial_csv_cancel_confirm' );
    $token = isset( $_POST['cancel_csv_token'] ) ? sanitize_key( wp_unslash( $_POST['cancel_csv_token'] ) ) : '';
    $key = 'tcsocial_cancel_' . get_current_user_id() . '_' . $token;
    $entries = $token ? get_transient( $key ) : false;
    if ( ! is_array( $entries ) ) {
        wp_die( esc_html__( 'Previsualización caducada.', 'taxocommerce-social' ) );
    }
    delete_transient( $key );
    $cancelled = 0;
    $skipped = 0;
    foreach ( $entries as $entry ) {
        if ( ! empty( $entry['errors'] ) ) {
            ++$skipped;
            continue;
        }
        $current = tcsocial_network_get_scheduled_timestamp( $entry['id'], $entry['provider'] );
        if ( ! $current || (int) $current !== (int) $entry['expected'] ) {
            ++$skipped;
            continue;
        }
        tcsocial_network_clear_scheduled_publication( $entry['id'], $entry['provider'] );
        // A legacy producer may still own the previous WP-Cron event.
        wp_clear_scheduled_hook( 'seo_social_network_publish_manual_scheduled', array( $entry['id'], $entry['provider'] ) );
        ++$cancelled;
    }
    wp_safe_redirect( add_query_arg( array(
        'page' => 'taxocommerce-social', 'tab' => 'calendar',
        'cancel_csv_done' => $cancelled, 'cancel_csv_skipped' => $skipped,
    ), admin_url( 'admin.php' ) ) );
    exit;
}
add_action( 'admin_post_tcsocial_csv_cancel_confirm', 'tcsocial_csv_cancel_confirm' );

/** Render an independent cancellation panel beside existing import/export controls. */
function tcsocial_csv_cancel_panel() {
    echo '<section class="seo-social-card"><h3>' . esc_html__( 'Cancelar programaciones mediante CSV', 'taxocommerce-social' ) . '</h3>';
    echo '<p>' . esc_html__( 'Importa un CSV con contenido_id, redes, fecha_hora y accion=cancelar. Se cancelan exclusivamente las coincidencias exactas; las demás quedan intactas. Primero se muestra una previsualización.', 'taxocommerce-social' ) . '</p>';
    echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    echo '<input type="hidden" name="action" value="tcsocial_csv_cancel_preview">';
    wp_nonce_field( 'tcsocial_csv_cancel_preview' );
    echo '<input type="file" accept=".csv" name="cancel_csv" required> <button type="submit" class="button">' . esc_html__( 'Previsualizar cancelaciones', 'taxocommerce-social' ) . '</button></form>';
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only summary from redirect.
    if ( isset( $_GET['cancel_csv_done'] ) ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only summary from redirect.
        $done = absint( $_GET['cancel_csv_done'] );
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only summary from redirect.
        $skipped = absint( $_GET['cancel_csv_skipped'] ?? 0 );
        /* translators: 1: cancelled schedule count, 2: skipped schedule count. */
        echo '<p>' . esc_html( sprintf( __( 'Canceladas: %1$d. Omitidas: %2$d.', 'taxocommerce-social' ), $done, $skipped ) ) . '</p>';
    }
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only transient preview.
    $token = isset( $_GET['cancel_csv_token'] ) ? sanitize_key( wp_unslash( $_GET['cancel_csv_token'] ) ) : '';
    $entries = $token ? get_transient( 'tcsocial_cancel_' . get_current_user_id() . '_' . $token ) : false;
    if ( is_array( $entries ) ) {
        $valid = 0;
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Contenido', 'taxocommerce-social' ) . '</th><th>' . esc_html__( 'Red', 'taxocommerce-social' ) . '</th><th>' . esc_html__( 'Fecha', 'taxocommerce-social' ) . '</th><th>' . esc_html__( 'Resultado', 'taxocommerce-social' ) . '</th></tr></thead><tbody>';
        foreach ( $entries as $entry ) {
            if ( empty( $entry['errors'] ) ) {
                ++$valid;
            }
            echo '<tr><td>' . esc_html( $entry['title'] . ' #' . $entry['id'] ) . '</td><td>' . esc_html( $entry['provider'] ) . '</td><td>' . esc_html( $entry['date'] ) . '</td><td>' . esc_html( empty( $entry['errors'] ) ? __( 'Cancelar', 'taxocommerce-social' ) : implode( '; ', $entry['errors'] ) ) . '</td></tr>';
        }
        echo '</tbody></table>';
        if ( $valid ) {
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
            echo '<input type="hidden" name="action" value="tcsocial_csv_cancel_confirm"><input type="hidden" name="cancel_csv_token" value="' . esc_attr( $token ) . '">';
            wp_nonce_field( 'tcsocial_csv_cancel_confirm' );
            /* translators: %d: number of selected schedules to cancel. */
            echo '<p><button type="submit" class="button button-primary">' . esc_html( sprintf( __( 'Confirmar cancelación de %d programaciones', 'taxocommerce-social' ), $valid ) ) . '</button></p></form>';
        }
    }
    echo '</section>';
}
