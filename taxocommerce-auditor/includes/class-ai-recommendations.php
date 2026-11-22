<?php
/**
 * Evidence-based product recommendations for AI-assisted product discovery.
 *
 * This is a read-only presentation of existing Auditor findings, not a claim
 * that a particular assistant will recommend or rank a product.
 *
 * @package TaxoCommerce_Auditor
 */
namespace TaxoCommerce\Auditor;

defined( 'ABSPATH' ) || exit;

final class AI_Recommendations {
    /**
     * Map verified product findings to concrete editorial review tasks.
     *
     * @param array $report Latest product audit report.
     * @return array Recommendations from the retained report sample.
     */
    public static function from_report( $report ) {
        $guidance = array(
            'product_missing_title' => array( 'identity', __( 'Identificar el producto con un título preciso y verificable.', 'taxocommerce-auditor' ) ),
            'product_missing_excerpt' => array( 'use_case', __( 'Redactar un resumen que indique qué es el producto y para qué sirve, usando solo datos confirmados.', 'taxocommerce-auditor' ) ),
            'product_missing_description' => array( 'use_case', __( 'Documentar aplicaciones, límites, contenido y especificaciones respaldadas por la ficha del fabricante.', 'taxocommerce-auditor' ) ),
            'product_without_category' => array( 'architecture', __( 'Asignar una categoría WooCommerce existente que corresponda a la función real del producto.', 'taxocommerce-auditor' ) ),
            'product_attribute_title_conflict' => array( 'specifications', __( 'Contrastar el atributo y el título con la referencia del fabricante antes de corregir la especificación.', 'taxocommerce-auditor' ) ),
            'product_tag_numeric_conflict' => array( 'specifications', __( 'Verificar la unidad y el valor técnico frente a la fuente original; no cambiar etiquetas automáticamente.', 'taxocommerce-auditor' ) ),
            'product_title_excerpt_drift' => array( 'consistency', __( 'Comprobar que título y resumen describen el mismo artículo; la diferencia de palabras no prueba un error.', 'taxocommerce-auditor' ) ),
            'product_no_stable_sku' => array( 'identity', __( 'Comprobar si existe una referencia o SKU estable del fabricante y documentarla si está verificada.', 'taxocommerce-auditor' ) ),
            'product_unreadable' => array( 'technical', __( 'Reparar la lectura del producto antes de evaluar su información para asistentes.', 'taxocommerce-auditor' ) ),
        );
        $rows = array();
        foreach ( (array) ( $report['findings_sample'] ?? array() ) as $finding ) {
            if ( ! is_array( $finding ) || 'product' !== (string) ( $finding['entity_type'] ?? '' ) ) {
                continue;
            }
            $code = (string) ( $finding['code'] ?? '' );
            if ( ! isset( $guidance[ $code ] ) ) {
                continue;
            }
            $rows[] = array(
                'entity_id' => absint( $finding['entity_id'] ?? 0 ),
                'title' => (string) ( $finding['title'] ?? '' ),
                'code' => $code,
                'area' => $guidance[ $code ][0],
                'evidence' => (string) ( $finding['evidence'] ?? '' ),
                'recommendation' => $guidance[ $code ][1],
                'priority' => (string) ( $finding['priority'] ?? 'P5' ),
                'status' => __( 'Revisión humana necesaria', 'taxocommerce-auditor' ),
            );
        }
        return $rows;
    }

    /**
     * Render recommendations without fetching remote data or editing products.
     *
     * @param array $report Last persisted audit.
     */
    public static function render( $report ) {
        $rows = self::from_report( $report );
        echo '<h3>' . esc_html__( 'Recomendaciones para búsquedas con IA', 'taxocommerce-auditor' ) . '</h3>';
        echo '<p>' . esc_html__( 'Propuestas basadas en hallazgos verificables del último análisis de productos. No son puntuaciones de ChatGPT, Copilot ni Gemini, ni garantizan recomendaciones. Ningún contenido se modifica automáticamente.', 'taxocommerce-auditor' ) . '</p>';
        echo '<p>' . esc_html__( 'Cobertura: solo hallazgos incluidos en la muestra guardada del informe. Para revisar todo el catálogo, ejecuta la auditoría completa y consulta el JSON completo; esta vista no representa necesariamente todas las incidencias.', 'taxocommerce-auditor' ) . '</p>';
        if ( empty( $rows ) ) {
            echo '<p>' . esc_html__( 'No hay recomendaciones sustentadas en los hallazgos conservados. Esto no certifica que los productos estén optimizados para IA.', 'taxocommerce-auditor' ) . '</p>';
            return;
        }
        echo '<div class="taxocommerce-auditor-table"><table class="widefat striped"><thead><tr>';
        foreach ( array( __( 'Prioridad', 'taxocommerce-auditor' ), __( 'Producto', 'taxocommerce-auditor' ), __( 'Área', 'taxocommerce-auditor' ), __( 'Evidencia', 'taxocommerce-auditor' ), __( 'Propuesta', 'taxocommerce-auditor' ), __( 'Estado', 'taxocommerce-auditor' ) ) as $heading ) {
            echo '<th scope="col">' . esc_html( $heading ) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ( array_slice( $rows, 0, 200 ) as $row ) {
            $url = get_edit_post_link( $row['entity_id'], '' );
            echo '<tr><td>' . esc_html( $row['priority'] ) . '</td><td>';
            if ( $url ) {
                echo '<a href="' . esc_url( $url ) . '">' . esc_html( $row['title'] ) . '</a>';
            } else {
                echo esc_html( $row['title'] );
            }
            echo '<br><code>#' . esc_html( (string) $row['entity_id'] ) . '</code></td>';
            echo '<td>' . esc_html( $row['area'] ) . '</td><td>' . esc_html( $row['evidence'] ) . '</td>';
            echo '<td>' . esc_html( $row['recommendation'] ) . '</td><td>' . esc_html( $row['status'] ) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
}
