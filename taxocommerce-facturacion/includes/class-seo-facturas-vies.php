<?php
/**
 * Cliente VIES (UE) para validar NIF-IVA.
 *
 * Consulta el servicio oficial de la Comisión Europea. No sustituye
 * el criterio del asesor fiscal; solo aporta una comprobación técnica.
 */

defined('ABSPATH') || exit;

final class SEO_Facturas_Vies {
    const TRANSIENT_PREFIX = 'seo_facturas_vies_';
    const CACHE_TTL = DAY_IN_SECONDS;

    /**
     * @return array{status:string,valid:?bool,name:string,address:string,checked_at:string,message:string}
     */
    public static function validate($country, $vat_number) {
        $country = strtoupper(preg_replace('/[^A-Z]/', '', (string) $country));
        $vat_number = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) $vat_number));
        if (strlen($country) === 2 && 0 === strpos($vat_number, $country)) {
            $vat_number = substr($vat_number, 2);
        }
        if (2 !== strlen($country) || '' === $vat_number) {
            return self::result('invalid', false, '', '', 'NIF-IVA incompleto o país no válido.');
        }

        $cache_key = self::TRANSIENT_PREFIX . md5($country . $vat_number);
        $cached = get_transient($cache_key);
        if (is_array($cached) && isset($cached['status'])) {
            return $cached;
        }

        if (!class_exists('SoapClient')) {
            $out = self::result('unavailable', null, '', '', 'PHP SOAP no está disponible en el servidor.');
            set_transient($cache_key, $out, 5 * MINUTE_IN_SECONDS);
            return $out;
        }

        try {
            $client = new SoapClient(
                'https://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl',
                array(
                    'connection_timeout' => 8,
                    'exceptions' => true,
                    'trace' => false,
                    'cache_wsdl' => WSDL_CACHE_BOTH,
                )
            );
            $response = $client->checkVat(array(
                'countryCode' => $country,
                'vatNumber' => $vat_number,
            ));
            $valid = !empty($response->valid);
            $out = self::result(
                $valid ? 'valid' : 'invalid',
                $valid,
                isset($response->name) ? (string) $response->name : '',
                isset($response->address) ? (string) $response->address : '',
                $valid ? 'NIF-IVA validado en VIES.' : 'NIF-IVA no válido en VIES.'
            );
            set_transient($cache_key, $out, self::CACHE_TTL);
            return $out;
        } catch (Throwable $e) {
            $out = self::result('unavailable', null, '', '', 'VIES no disponible temporalmente.');
            set_transient($cache_key, $out, 5 * MINUTE_IN_SECONDS);
            return $out;
        }
    }

    private static function result($status, $valid, $name, $address, $message) {
        return array(
            'status' => (string) $status,
            'valid' => $valid,
            'name' => (string) $name,
            'address' => (string) $address,
            'checked_at' => gmdate('c'),
            'message' => (string) $message,
        );
    }
}
