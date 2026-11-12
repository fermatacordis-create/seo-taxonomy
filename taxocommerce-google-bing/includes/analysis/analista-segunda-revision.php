<?php
/**
 * Lightweight second review for editorial actions in the Analysis action plan.
 * Uses already loaded WordPress modification dates and the GSC data cutoff.
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'seo_analista_editorial_date_review' ) ) {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Preserve the existing Analista function namespace.
    function seo_analista_editorial_date_review( array $row, $source_date ) {
        $entity = (array) ( $row['entity'] ?? array() );
        $type   = sanitize_key( (string) ( $entity['type'] ?? '' ) );
        $id     = absint( $entity['id'] ?? 0 );
        if ( ! $id || ! in_array( $type, array( 'post', 'page', 'landing', 'product', 'category', 'cluster', 'hub_primary', 'hub_secondary' ), true ) ) {
            return $row;
        }
        if ( 'SIN_ACCION' === (string) ( $row['work_bucket'] ?? '' ) || in_array( (string) ( $row['status'] ?? '' ), array( 'executed', 'dismissed', 'queued', 'in_progress' ), true ) ) {
            return $row;
        }

        // Only editorial issues backed by the GSC period qualify. A newer edit
        // cannot resolve a shipping, commercial, indexing or technical error.
        $sources = (array) ( $row['sources'] ?? array() );
        if ( ! in_array( 'Search Console', $sources, true ) || empty( $row['issues'] ) ) {
            return $row;
        }
        $allowed = array( 'título', 'titulo', 'cobertura textual', 'descripción corta', 'descripcion corta', 'excerpt', 'seo title', 'meta description', 'vocabulary', 'enlazado interno', 'etiquetas seo', 'etiquetas editoriales', 'ctr bajo' );
        foreach ( (array) $row['issues'] as $issue ) {
            $editorial = false;
            foreach ( $allowed as $phrase ) {
                if ( false !== stripos( (string) $issue, $phrase ) ) {
                    $editorial = true;
                    break;
                }
            }
            if ( ! $editorial ) {
                return $row;
            }
        }

        $source_date = trim( (string) $source_date );
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $source_date ) ) {
            return $row;
        }
        $modified = substr( trim( (string) ( $row['content_modified_at'] ?? '' ) ), 0, 10 );
        if ( '' === $modified && 'category' !== $type && function_exists( 'get_post_modified_time' ) ) {
            $modified = (string) get_post_modified_time( 'Y-m-d', true, $id );
        }
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $modified ) || $modified <= $source_date ) {
            return $row;
        }

        $message = sprintf(
            'Contenido modificado el %1$s, después del último dato disponible de Search Console (%2$s). No repetir esta corrección editorial; vigilar si Google incorpora los cambios. La modificación no acredita por sí sola que el problema esté resuelto.',
            $modified,
            $source_date
        );
        $row['date_review'] = array(
            'state'               => 'modified_after_gsc_data',
            'content_modified_at' => $modified,
            'gsc_data_to'         => $source_date,
            'verified_fix'        => false,
        );
        $row['reason']              = $message;
        $row['work_bucket']         = 'VIGILAR';
        $row['action']              = 'VIGILAR';
        $row['action_type']         = 'VIGILAR';
        $row['action_label']        = 'Vigilar actualización en Google';
        $row['recommended_changes'] = array( $message );
        $primary = array(
            'type'        => 'VIGILAR',
            'instruction' => $message,
            'detail'      => $message,
            'owner'       => 'SEO',
            'executable'  => false,
        );
        $row['primary_action']  = $primary;
        $row['action_details']  = array( $primary );
        $row['signal_state']    = array_merge( (array) ( $row['signal_state'] ?? array() ), array( 'label' => 'Contenido modificado tras los datos de Google' ) );
        $row['execution_gate'] = array_merge( (array) ( $row['execution_gate'] ?? array() ), array(
            'bucket'        => 'VIGILAR',
            'final_bucket'  => 'VIGILAR',
            'state'         => 'MODIFIED_AFTER_GSC_DATA',
        ) );
        $row['task_decision'] = array_merge( (array) ( $row['task_decision'] ?? array() ), array(
            'work_bucket'        => 'VIGILAR',
            'intervention_type'  => 'VIGILAR',
            'intervention_label' => 'Vigilar actualización en Google',
            'primary_action'     => $primary,
            'actions'            => array( $primary ),
            'signal_state'       => $row['signal_state'],
            'execution_ready'    => false,
            'decision_reason'    => $message,
        ) );
        return $row;
    }
}
