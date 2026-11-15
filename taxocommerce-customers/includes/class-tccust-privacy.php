<?php
/**
 * Integrate with WordPress personal data export / erasure requests.
 * WooCommerce owns order data and may retain records for legal obligations.
 *
 * @package TaxoCommerceCustomers
 */
defined( 'ABSPATH' ) || exit;

final class TCCUST_Privacy {
	public static function boot() {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'erasers' ) );
	}

	public static function exporters( $exporters ) {
		$exporters['taxocommerce-customers'] = array(
			'exporter_friendly_name' => __( 'TaxoCommerce Customer CRM', 'taxocommerce-customers' ),
			'callback' => array( __CLASS__, 'exporter' ),
		);
		return $exporters;
	}

	public static function erasers( $erasers ) {
		$erasers['taxocommerce-customers'] = array(
			'eraser_friendly_name' => __( 'TaxoCommerce Customer CRM', 'taxocommerce-customers' ),
			'callback' => array( __CLASS__, 'eraser' ),
		);
		return $erasers;
	}

	public static function exporter( $email_address, $page = 1 ) {
		$data = array();
		if ( 1 !== absint( $page ) ) {
			return array( 'data' => $data, 'done' => true );
		}
		$key = TCCUST_DB::email_key( $email_address );
		$customer = TCCUST_DB::find_email( $key );
		if ( is_array( $customer ) ) {
			$fields = array(
				'nombre' => __( 'First name', 'taxocommerce-customers' ),
				'apellidos' => __( 'Last name', 'taxocommerce-customers' ),
				'email' => __( 'Email', 'taxocommerce-customers' ),
				'empresa' => __( 'Company', 'taxocommerce-customers' ),
				'telefono' => __( 'Phone', 'taxocommerce-customers' ),
				'whatsapp' => __( 'WhatsApp', 'taxocommerce-customers' ),
				'direccion' => __( 'Street address', 'taxocommerce-customers' ),
				'codigo_postal' => __( 'Postal code', 'taxocommerce-customers' ),
				'localidad' => __( 'City', 'taxocommerce-customers' ),
				'provincia' => __( 'Province or region', 'taxocommerce-customers' ),
				'pais' => __( 'Country', 'taxocommerce-customers' ),
				'canal_preferido' => __( 'Preferred contact channel', 'taxocommerce-customers' ),
				'etiquetas' => __( 'Internal labels', 'taxocommerce-customers' ),
				'observaciones' => __( 'Private notes and incidents', 'taxocommerce-customers' ),
			);
			$values = array();
			foreach ( $fields as $key => $name ) {
				$values[] = array( 'name' => $name, 'value' => (string) ( $customer[ $key ] ?? '' ) );
			}
			$data[] = array(
				'group_id' => 'taxocommerce-customers',
				'group_label' => __( 'TaxoCommerce Customer CRM', 'taxocommerce-customers' ),
				'item_id' => 'tccust-' . absint( $customer['id'] ),
				'data' => $values,
			);
		}
		return array( 'data' => $data, 'done' => true );
	}

	public static function eraser( $email_address, $page = 1 ) {
		if ( 1 !== absint( $page ) ) {
			return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
		}
		$key = TCCUST_DB::email_key( $email_address );
		$customer = TCCUST_DB::find_email( $key );
		if ( ! is_array( $customer ) ) {
			return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
		}
		$id = absint( $customer['id'] );
		if ( 'manual' === $customer['source'] && ! TCCUST_DB::has_orders( $id ) ) {
			$deleted = TCCUST_DB::delete( $id );
			return array( 'items_removed' => false !== $deleted, 'items_retained' => false, 'messages' => array(), 'done' => true );
		}
		// Orders and tax records remain under WooCommerce's own retention policy.
		// The CRM has no independent basis to keep duplicate billing details.
		if ( ! empty( $customer['erased_at'] ) ) {
			return array(
				'items_removed' => false,
				'items_retained' => true,
				'messages' => array( __( 'Only a minimal WooCommerce relationship is retained in the CRM.', 'taxocommerce-customers' ) ),
				'done' => true,
			);
		}
		$changed = TCCUST_DB::redact( $customer );
		return array(
			'items_removed' => (bool) $changed,
			'items_retained' => true,
			'messages' => array( __( 'CRM personal information was anonymized; WooCommerce orders retain their own legal retention rules.', 'taxocommerce-customers' ) ),
			'done' => true,
		);
	}
}
