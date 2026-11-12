<?php
/**
 * Merchant-first market intelligence for Ojeador. Merchant Reports API is a
 * different source from individual Google Shopping offers: never mix them.
 *
 * No external request on render. Explicit, paginated reports:search only.
 */
defined('ABSPATH') || exit;

final class TaxoCommerce_Google_Bing_Merchant {
    const ACCOUNT_OPTION = 'seo_ojeador_merchant_account_v1';
    const MODE_OPTION = 'seo_ojeador_market_source_v1';
    const STATE_OPTION = 'seo_ojeador_merchant_snapshots_v1';
    const BASELINE_OPTION = 'seo_ojeador_merchant_pro_baseline_v1';

    public static function init() {
        add_action('admin_post_tgb_google_merchant_settings',array(__CLASS__,'save_settings'));
        add_action('admin_post_tgb_google_merchant_sync',array(__CLASS__,'handle_sync'));
        add_action('admin_post_tgb_google_merchant_baseline',array(__CLASS__,'handle_baseline'));
    }

    /** Zero paid Shopping requests by default; explicit admin opt-in for legacy. */
    public static function mode() {
        $mode=sanitize_key((string)get_option(self::MODE_OPTION,'merchant_only'));
        return $mode==='legacy' ? 'legacy' : 'merchant_only';
    }
    public static function is_merchant_only() { return self::mode()==='merchant_only'; }

    public static function account() {
        $id=(string)get_option(self::ACCOUNT_OPTION,'');
        return preg_match('/^[0-9]{5,20}$/',$id) ? $id : '';
    }

    public static function snapshots() {
        $state=get_option(self::STATE_OPTION,array());
        return is_array($state)?$state:array();
    }

    private static function redirect($extra=array()) {
        wp_safe_redirect(add_query_arg(array_merge(array(
            'page'=>'taxocommerce-google-bing','tgb_tab'=>'merchant'
        ),$extra),admin_url('admin.php')));
        exit;
    }

    public static function save_settings() {
        if (!current_user_can('manage_options'))wp_die('No autorizado.');
        check_admin_referer('seo_ojeador_merchant_settings');
        $account=sanitize_text_field((string)wp_unslash($_POST['merchant_account']??''));
        $mode=sanitize_key((string)wp_unslash($_POST['market_mode']??'merchant_only'));
        if ($account!==''&&!preg_match('/^[0-9]{5,20}$/',$account))self::redirect(array('merchant_error'=>'Cuenta Merchant inválida.'));
        if ($account!==self::account()) {
            // Never mix results from different Merchant accounts.
            delete_option(self::STATE_OPTION);
        }
        update_option(self::ACCOUNT_OPTION,$account,false);
        update_option(self::MODE_OPTION,$mode==='legacy'?'legacy':'merchant_only',false);
        if ($mode!=='legacy' && class_exists('SEO_Ojeador_Worker'))SEO_Ojeador_Worker::stop_run();
        self::redirect(array('merchant_notice'=>'saved'));
    }

    private static function b64url($s) {
        return rtrim(strtr(base64_encode($s),'+/','-_'),'=');
    }

    private static function access_token() {
        if (!function_exists('seo_google_search_service_account')) {
            return new WP_Error('merchant_auth_module','Falta el módulo Google / cuenta de servicio.');
        }
        $service=seo_google_search_service_account();
        if (is_wp_error($service))return new WP_Error('merchant_auth_missing',
            'Configura una cuenta de servicio en Google Search / Analytics y dale acceso a la cuenta Merchant. La conexión OAuth actual de Search Console no otorga permisos de Merchant.');
        if (!function_exists('openssl_sign'))return new WP_Error('merchant_openssl','OpenSSL no está disponible.');
        $email=(string)$service['client_email'];
        $cache='seo_merchant_access_'.md5($email);
        $token=get_transient($cache);
        if (is_string($token)&&$token!=='')return $token;
        $now=time();$uri='https://oauth2.googleapis.com/token';
        $head=self::b64url(wp_json_encode(array('alg'=>'RS256','typ'=>'JWT')));
        $payload=self::b64url(wp_json_encode(array(
            'iss'=>$email,
            'scope'=>'https://www.googleapis.com/auth/content',
            'aud'=>$uri,'iat'=>$now,'exp'=>$now+3500
        )));
        $part=$head.'.'.$payload;$sig='';
        if (!openssl_sign($part,$sig,(string)$service['private_key'],OPENSSL_ALGO_SHA256)) {
            return new WP_Error('merchant_auth_sign','No se pudo firmar la solicitud Merchant.');
        }
        $response=wp_safe_remote_post($uri,array('timeout'=>20,'body'=>array(
            'grant_type'=>'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'=>$part.'.'.self::b64url($sig)
        )));
        if (is_wp_error($response))return $response;
        $body=json_decode((string)wp_remote_retrieve_body($response),true);
        if (wp_remote_retrieve_response_code($response)!==200||empty($body['access_token'])) {
            return new WP_Error('merchant_token_error','Google no ha autorizado la cuenta de servicio para Merchant.');
        }
        $token=(string)$body['access_token'];
        set_transient($cache,$token,max(60,absint($body['expires_in']??3600)-120));
        return $token;
    }

    /**
     * Official Google Merchant Reports API. Restricted to two read-only queries
     * whose fields are documented; never arbitrary SQL or credentials in URLs.
     */
    private static function search_report($view,$page_token='') {
        $account=self::account();
        if (!$account)return new WP_Error('merchant_account_missing','Introduce el ID numérico de la cuenta Merchant Center.');
        $token=self::access_token();
        if (is_wp_error($token))return $token;
        if ($view==='prices') {
            $query="SELECT offer_id,id,title,brand,price,report_country_code,benchmark_price FROM price_competitiveness_product_view WHERE report_country_code = 'ES'";
        } elseif($view==='popular') {
            $query="SELECT report_date,report_granularity,report_country_code,report_category_id,rank,previous_rank,relative_demand,relative_demand_change,title,brand,category_l1,category_l2,variant_gtins,inventory_status FROM best_sellers_product_cluster_view WHERE report_granularity = 'WEEKLY' AND report_country_code = 'ES' ORDER BY rank";
        } else {
            return new WP_Error('merchant_report_invalid','Informe no permitido.');
        }
        $payload=array('query'=>$query,'pageSize'=>500);
        if ($page_token!=='')$payload['pageToken']=$page_token;
        $url='https://merchantapi.googleapis.com/reports/v1/accounts/'.$account.'/reports:search';
        $response=wp_safe_remote_post($url,array(
            'timeout'=>35,
            'redirection'=>0,
            'headers'=>array('Authorization'=>'Bearer '.$token,'Content-Type'=>'application/json','Accept'=>'application/json'),
            'body'=>wp_json_encode($payload)
        ));
        if (is_wp_error($response))return $response;
        $code=(int)wp_remote_retrieve_response_code($response);
        $json=json_decode((string)wp_remote_retrieve_body($response),true);
        if ($code<200||$code>=300) {
            $message=is_array($json)?sanitize_text_field((string)($json['error']['message']??'')):'';
            return new WP_Error('merchant_report_http','Merchant Reports HTTP '.$code.($message?' · '.$message:''),array('http_code'=>$code));
        }
        return is_array($json)?$json:new WP_Error('merchant_report_json','Respuesta JSON inválida.');
    }

    private static function amount($data) {
        if (!is_array($data)||($data['currencyCode']??'')!=='EUR')return null;
        if (!is_numeric($data['amountMicros']??null))return null;
        $num=((float)$data['amountMicros'])/1000000;
        return $num>0?round($num,4):null;
    }

    public static function normalize_price_row(array $row) {
        $id=sanitize_text_field((string)($row['offerId']??''));
        if ($id==='' && !empty($row['id'])) {
            $parts=explode('~',(string)$row['id']);
            $id=(string)end($parts);
        }
        if (!preg_match('/^wc-[0-9]+$/',$id)||($row['reportCountryCode']??'')!=='ES')return null;
        $benchmark=self::amount($row['benchmarkPrice']??null);
        if ($benchmark===null)return null;
        return array(
            'offer_id'=>$id,'product_id'=>absint(substr($id,3)),
            'title'=>sanitize_text_field((string)($row['title']??'')),
            'brand'=>sanitize_text_field((string)($row['brand']??'')),
            'price'=>self::amount($row['price']??null),
            'benchmark'=>$benchmark,'currency'=>'EUR'
        );
    }

    private static function normalize_popular_row(array $row) {
        if (($row['reportCountryCode']??'')!=='ES')return null;
        $title=sanitize_text_field((string)($row['title']??''));
        if ($title==='')return null;
        $brand=sanitize_text_field((string)($row['brand']??''));
        $key=hash('sha256',mb_strtolower($brand.'|'.$title.'|'.(string)($row['reportCategoryId']??''),'UTF-8'));
        return array(
            'key'=>$key,'title'=>$title,'brand'=>$brand,
            'category'=>sanitize_text_field((string)($row['categoryL1']??'')),
            'category_id'=>absint($row['reportCategoryId']??0),
            'subcategory'=>sanitize_text_field((string)($row['categoryL2']??'')),
            'report_date'=>is_array($row['reportDate']??null) ? sprintf('%04d-%02d-%02d',absint($row['reportDate']['year']??0),absint($row['reportDate']['month']??0),absint($row['reportDate']['day']??0)) : sanitize_text_field((string)($row['reportDate']??'')),
            'rank'=>absint($row['rank']??0),
            'previous_rank'=>absint($row['previousRank']??0),
            'relative_demand'=>sanitize_key((string)($row['relativeDemand']??'')),
            'demand_change'=>sanitize_key((string)($row['relativeDemandChange']??'')),
            'inventory_status'=>sanitize_key((string)($row['inventoryStatus']??'')),
            'variant_gtins'=>array_slice(array_values(array_filter(array_map('sanitize_text_field',
                (array)($row['variantGtins']??array())))),0,8)
        );
    }

    /** One page per button click. Retains data and cursor between requests. */
    public static function sync($view='prices',$restart=false) {
        if (!in_array($view,array('prices','popular'),true))return new WP_Error('merchant_view','Vista inválida.');
        $lock='seo_ojeador_merchant_sync_lock';
        if (get_transient($lock))return new WP_Error('merchant_sync_busy','Hay una sincronización Merchant en curso.');
        set_transient($lock,1,2*MINUTE_IN_SECONDS);
        try {
            $state=self::snapshots();
            $section=(!$restart&&isset($state[$view])&&is_array($state[$view]))
                ? $state[$view] : array('rows'=>array(),'page_token'=>'','total_received'=>0,'complete'=>false);
            if (!empty($section['complete'])&&!$restart)return array('count'=>count((array)$section['rows']),'complete'=>true,'received'=>0);
            $answer=self::search_report($view,(string)($section['page_token']??''));
            if (is_wp_error($answer))return $answer;
            $rows=(array)($section['rows']??array());$received=0;
            foreach((array)($answer['results']??array())as $result) {
                $part=(array)($result[$view==='prices'?'priceCompetitivenessProductView':'bestSellersProductClusterView']??array());
                $row=$view==='prices'?self::normalize_price_row($part):self::normalize_popular_row($part);
                if (!$row)continue;
                $key=$view==='prices'?$row['offer_id']:$row['key'];
                $rows[$key]=$row;$received++;
                if (count($rows)>20000)break;
            }
            $section['rows']=$rows;
            $section['page_token']=sanitize_text_field((string)($answer['nextPageToken']??''));
            $section['total_received']=absint($section['total_received']??0)+count((array)($answer['results']??array()));
            $section['complete']=$section['page_token']==='' || count($rows)>=20000;
            if ($section['complete'])$section['page_token']='';
            $section['updated_at']=gmdate('c');
            $section['country']='ES';
            $state[$view]=$section;
            update_option(self::STATE_OPTION,$state,false);
            return array('count'=>count($rows),'received'=>$received,'complete'=>$section['complete']);
        } finally {delete_transient($lock);}
    }

    public static function handle_sync() {
        if (!current_user_can('manage_options'))wp_die('No autorizado.');
        check_admin_referer('seo_ojeador_merchant_sync');
        $view=sanitize_key((string)wp_unslash($_POST['view']??'prices'));
        $result=self::sync($view,!empty($_POST['restart']));
        self::redirect(is_wp_error($result)
            ? array('merchant_error'=>$result->get_error_message())
            : array('merchant_notice'=>'synced'));
    }

    /** Accepts only sanitized, compact baseline exported from Ojeador PRO. */
    public static function handle_baseline() {
        if (!current_user_can('manage_options'))wp_die('No autorizado.');
        check_admin_referer('seo_ojeador_merchant_baseline');
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw upload metadata is validated for upload status, size and JSON schema before use.
        $file=isset($_FILES['baseline_file'])&&is_array($_FILES['baseline_file'])?(array)$_FILES['baseline_file']:array();
        $tmp=(string)($file['tmp_name']??'');
        if (($file['error']??-1)!==UPLOAD_ERR_OK||!is_uploaded_file($tmp)
            ||absint($file['size']??0)>2*1024*1024)self::redirect(array('merchant_error'=>'Archivo inválido o superior a 2 MB.'));
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- PHP uploaded temp file with strict size limit.
        $raw=file_get_contents($tmp);
        $json=json_decode((string)$raw,true);
        if (!is_array($json)||($json['schema']??'')!=='seo_ojeador_merchant_baseline'
            ||!is_array($json['stars']??null))self::redirect(array('merchant_error'=>'JSON de línea base incompatible.'));
        $stars=array();
        foreach(array_slice($json['stars'],0,1000)as $entry) {
            $id=absint($entry['product_id']??0);if(!$id)continue;
            $stars[$id]=array(
                'name'=>sanitize_text_field((string)($entry['name']??'')),
                'median'=>is_numeric($entry['legacy_median']??null)?(float)$entry['legacy_median']:null,
                'min'=>is_numeric($entry['legacy_min']??null)?(float)$entry['legacy_min']:null,
                'max'=>is_numeric($entry['legacy_max']??null)?(float)$entry['legacy_max']:null,
                'offers'=>absint($entry['legacy_count']??0),
            );
        }
        update_option(self::BASELINE_OPTION,array('stars'=>$stars,
            'generated_at'=>sanitize_text_field((string)($json['generated_at_utc']??'')),
            'source'=>'Ojeador PRO / Export'),false);
        self::redirect(array('merchant_notice'=>'baseline'));
    }

    public static function comparison() {
        $snapshot=self::snapshots();
        $prices=(array)($snapshot['prices']['rows']??array());
        $baseline=get_option(self::BASELINE_OPTION,array());
        $stars=(array)($baseline['stars']??array());
        if (!$stars && class_exists('SEO_Ojeador_Stars')) {
            // Local snapshot, not remote APIs. PRO baseline takes precedence.
            foreach((array)(SEO_Ojeador_Stars::export_data(false)['products']??array())as$entry) {
                $id=absint($entry['object_id']??0);if(!$id)continue;
                $stars[$id]=array(
                    'name'=>sanitize_text_field((string)($entry['product_name']??'')),
                    'median'=>is_numeric($entry['market_price_median']??null)?(float)$entry['market_price_median']:null,
                    'min'=>is_numeric($entry['market_price_min']??null)?(float)$entry['market_price_min']:null,
                    'max'=>is_numeric($entry['market_price_max']??null)?(float)$entry['market_price_max']:null,
                    'offers'=>absint($entry['competitor_comparable_count']??0)
                );
            }
        }
        $out=array();$covered=0;$comparable=0;$agree=0;
        foreach($stars as$id=>$legacy) {
            $p=(array)($prices['wc-'.absint($id)]??array());
            $b=isset($p['benchmark'])?(float)$p['benchmark']:null;
            $m=is_numeric($legacy['median']??null)?(float)$legacy['median']:null;
            if ($b!==null)$covered++;
            $delta=($b!==null&&$m!==null&&$m>0)?round((($b/$m)-1)*100,2):null;
            if ($delta!==null) {$comparable++;if (abs($delta)<=10)$agree++;}
            $out[]=array(
                'product_id'=>absint($id),'name'=>(string)($legacy['name']??$p['title']??''),
                'merchant_benchmark'=>$b,'legacy_median'=>$m,
                'legacy_min'=>$legacy['min']??null,'legacy_max'=>$legacy['max']??null,
                'offers'=>absint($legacy['offers']??0),'delta_pct'=>$delta
            );
        }
        usort($out,static function($a,$b) {
            return (int)($b['delta_pct']!==null)<=>(int)($a['delta_pct']!==null)
                ?: abs((float)($b['delta_pct']??0))<=>abs((float)($a['delta_pct']??0));
        });
        return array('baseline'=>count($stars),'covered'=>$covered,'comparable'=>$comparable,
            'agree_10_pct'=>$agree,'rows'=>$out,'snapshot_rows'=>count($prices));
    }

    /**
     * Official Merchant Competitive Visibility. Explicit report generation only.
     * Up to three categories from actual Best Sellers data; no scraper fallback.
     *
     * @param array $category_ids Google taxonomy category IDs already observed.
     * @param int   $days         Days requested.
     * @return array Status and normalized evidence.
     */
    public static function competitive_visibility( array $category_ids, $days = 28 ) {
        $account = self::account();
        if ( '' === $account ) {
            return array( 'status' => 'unavailable', 'rows' => array(), 'errors' => array( 'Merchant Center no configurado.' ) );
        }
        $category_ids = array_slice( array_values( array_unique( array_filter( array_map( 'absint', $category_ids ) ) ) ), 0, 3 );
        if ( ! $category_ids ) {
            return array( 'status' => 'unavailable', 'rows' => array(), 'errors' => array( 'Sin categorías Google verificadas.' ) );
        }
        $days = in_array( absint( $days ), array( 28, 90 ), true ) ? absint( $days ) : 28;
        $cache_key = 'tci_merchant_visibility_' . md5( $account . '|' . implode( ',', $category_ids ) . '|' . $days );
        $cached = get_transient( $cache_key );
        if ( is_array( $cached ) ) {
            return $cached;
        }
        $token = self::access_token();
        if ( is_wp_error( $token ) ) {
            return array( 'status' => 'unavailable', 'rows' => array(), 'errors' => array( $token->get_error_message() ) );
        }
        $start = gmdate( 'Y-m-d', time() - $days * DAY_IN_SECONDS );
        $end = gmdate( 'Y-m-d', time() - DAY_IN_SECONDS );
        $rows = array();
        $errors = array();
        foreach ( $category_ids as $category_id ) {
            $query = "SELECT report_country_code, report_category_id, traffic_source, domain, is_your_domain, rank, page_overlap_rate, higher_position_rate, relative_visibility FROM competitive_visibility_competitor_view WHERE date BETWEEN '" .
                $start . "' AND '" . $end . "' AND report_country_code = 'ES' AND report_category_id = " . $category_id . " AND traffic_source = 'ORGANIC' ORDER BY rank";
            $response = wp_safe_remote_post(
                'https://merchantapi.googleapis.com/reports/v1/accounts/' . $account . '/reports:search',
                array(
                    'timeout' => 35,
                    'redirection' => 0,
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $token,
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ),
                    'body' => wp_json_encode( array( 'query' => $query, 'pageSize' => 100 ) ),
                )
            );
            if ( is_wp_error( $response ) ) {
                $errors[] = $response->get_error_message();
                continue;
            }
            $code = (int) wp_remote_retrieve_response_code( $response );
            $json = json_decode( (string) wp_remote_retrieve_body( $response ), true );
            if ( $code < 200 || $code >= 300 || ! is_array( $json ) ) {
                $errors[] = 'Merchant categoría ' . $category_id . ': HTTP ' . $code;
                continue;
            }
            foreach ( (array) ( $json['results'] ?? array() ) as $item ) {
                $entry = (array) ( $item['competitiveVisibilityCompetitorView'] ?? array() );
                $domain = sanitize_text_field( (string) ( $entry['domain'] ?? '' ) );
                if ( '' === $domain ) {
                    continue;
                }
                $rows[] = array(
                    'domain' => $domain,
                    'category_id' => absint( $entry['reportCategoryId'] ?? $category_id ),
                    'traffic_source' => sanitize_key( (string) ( $entry['trafficSource'] ?? 'ORGANIC' ) ),
                    'is_own' => ! empty( $entry['isYourDomain'] ),
                    'rank' => absint( $entry['rank'] ?? 0 ),
                    'overlap' => isset( $entry['pageOverlapRate'] ) ? (float) $entry['pageOverlapRate'] : null,
                    'higher_position' => isset( $entry['higherPositionRate'] ) ? (float) $entry['higherPositionRate'] : null,
                    'relative_visibility' => isset( $entry['relativeVisibility'] ) ? (float) $entry['relativeVisibility'] : null,
                );
            }
        }
        $result = array(
            'status' => $rows ? 'available' : ( $errors ? 'unavailable' : 'no_data' ),
            'rows' => $rows,
            'errors' => array_slice( $errors, 0, 3 ),
            'country' => 'ES',
            'period_days' => $days,
        );
        set_transient( $cache_key, $result, 6 * HOUR_IN_SECONDS );
        return $result;
    }

    public static function render() {
        if (!current_user_can('manage_options'))return;
        $snapshot=self::snapshots();$c=self::comparison();
        $popular=(array)($snapshot['popular']['rows']??array());
        $base=get_option(self::BASELINE_OPTION,array());
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET parameter, no state modification.
        $error=sanitize_text_field((string)wp_unslash($_GET['merchant_error']??''));
        if ($error!=='')echo '<div class="notice notice-error inline"><p>'.esc_html($error).'</p></div>';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only GET parameter, no state modification.
        if (isset($_GET['merchant_notice']))echo '<div class="notice notice-success inline">' . '<p>' . esc_html__( 'Operación Merchant guardada. Revisa los recuentos y el estado de sincronización.', 'taxocommerce-google-bing' ) . '</p>' . '</div>';
        echo '<h2>' . esc_html__( 'Google Merchant Center · informes de mercado', 'taxocommerce-google-bing' ) . '</h2>';
        echo '<p>' . esc_html__( 'Los benchmarks son referencias agregadas de Google, NO precios de comercios individuales. Los resultados antiguos de Shopping siguen disponibles como fotografía histórica y nunca se sobrescriben con benchmarks.', 'taxocommerce-google-bing' ) . '</p>';
        echo '<form action="'.esc_url(admin_url('admin-post.php')).'" method="post">';
        wp_nonce_field('seo_ojeador_merchant_settings');
        echo '<input type="hidden" name="action" value="tgb_google_merchant_settings">';
        echo '<p><label><strong>ID de Merchant Center</strong> <input type="text" name="merchant_account" value="'.esc_attr(self::account()).'" placeholder="ID numérico" pattern="[0-9]{5,20}" size="22"></label></p>';
        echo '<p>' . '<label>' . '<strong>' . esc_html__( 'Fuente de mercado', 'taxocommerce-google-bing' ) . '</strong>' . ' ' . '<select name="market_mode">';
        echo '<option value="merchant_only" '.selected(self::mode(),'merchant_only',false).'>Merchant solamente · 0 consultas de pago Ojeador</option>';
        echo '<option value="legacy" '.selected(self::mode(),'legacy',false).'>Shopping tradicional · admite consultas de pago</option>';
        echo '</select></label></p>';
        echo '<p class="description">' . esc_html__( 'Por defecto se bloquean nuevos escaneos Shopping de Ojeador y su radar Trends/Search; no se borran resultados. La opción tradicional exige una elección explícita. Ingeniero y otros servicios utilizan sus propias políticas de API.', 'taxocommerce-google-bing' ) . '</p>';
        echo '<p class="description">' . esc_html__( 'Requiere la cuenta de servicio de Google configurada en el plugin y autorizada como usuario de Merchant Center. Merchant Reports API debe estar habilitada y Market Insights disponible para la cuenta. No se envían claves a SerpApi.', 'taxocommerce-google-bing' ) . '</p>';
        submit_button('Guardar fuente y cuenta','primary','submit',false);echo '</form>';
        foreach(array('prices'=>'Precios competitivos','popular'=>'Productos populares')as$view=>$label) {
            $info=(array)($snapshot[$view]??array());
            echo '<div class="postbox" style="padding:12px;margin-top:15px"><h3>'.esc_html($label).'</h3>';
            echo '<p>'.esc_html(number_format_i18n(count((array)($info['rows']??array())))).' filas útiles · '.esc_html((string)($info['updated_at']??'Sin sincronizar')).' · '.(!empty($info['complete'])?'Completo':'Pendiente de más páginas').'</p>';
            echo '<form action="'.esc_url(admin_url('admin-post.php')).'" method="post">';
            wp_nonce_field('seo_ojeador_merchant_sync');echo '<input type="hidden" name="action" value="tgb_google_merchant_sync"><input type="hidden" name="view" value="'.esc_attr($view).'">';
            submit_button('Consultar siguiente lote Merchant (máx. 500)','secondary','submit',false,array('disabled'=>!self::account()||!empty($info['complete'])));
            echo ' ' . '<label>' . '<input type="checkbox" name="restart" value="1">' . ' ' . esc_html__( 'Reiniciar lectura desde primera página', 'taxocommerce-google-bing' ) . '</label>';
            echo '</form></div>';
        }
        echo '<div class="postbox" style="padding:12px">' . '<h3>' . esc_html__( 'Comparar con Ojeador PRO', 'taxocommerce-google-bing' ) . '</h3>';
        echo '<p>' . esc_html__( 'Exportación reducida de los productos estrella de PRO: no contiene las 27.075 ofertas individuales. También se pueden usar datos locales si no se importa.', 'taxocommerce-google-bing' ) . '</p>';
        echo '<form action="'.esc_url(admin_url('admin-post.php')).'" method="post" enctype="multipart/form-data">';
        wp_nonce_field('seo_ojeador_merchant_baseline');echo '<input type="hidden" name="action" value="tgb_google_merchant_baseline">';
        echo '<input type="file" name="baseline_file" accept=".json,application/json" required> ';
        submit_button('Importar línea base PRO','secondary','submit',false);echo '</form>';
        if (!empty($base['generated_at']))echo '<p>Referencia PRO: '.esc_html((string)$base['generated_at']).'</p>';
        echo '<p><strong>Productos de referencia:</strong> '.esc_html($c['baseline']).' · <strong>Con benchmark Merchant:</strong> '.esc_html($c['covered']).' · <strong>Comparables:</strong> '.esc_html($c['comparable']).' · <strong>Desviación ≤10%:</strong> '.esc_html($c['agree_10_pct']).'</p>';
        echo '<p class="description">' . esc_html__( 'Sin benchmarks reales Merchant no es posible medir ahorro ni diferencias. Las métricas anteriores no son equivalentes estadísticamente.', 'taxocommerce-google-bing' ) . '</p>';
        echo '<div style="overflow:auto">' . '<table class="widefat striped">' . '<thead>' . '<tr>' . '<th>' . esc_html__( 'Producto', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Mediana ofertas observadas', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Benchmark Merchant', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Diferencia', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Ofertas históricas', 'taxocommerce-google-bing' ) . '</th>' . '</tr>' . '</thead>' . '<tbody>';
        foreach(array_slice($c['rows'],0,80)as $row) {
            echo '<tr><td>'.esc_html($row['name']).' (#'.absint($row['product_id']).')</td>';
            foreach(array('legacy_median','merchant_benchmark')as$k)echo '<td>'.($row[$k]!==null?esc_html(number_format_i18n((float)$row[$k],2)).' €':'—').'</td>';
            echo '<td>'.($row['delta_pct']!==null?esc_html(number_format_i18n((float)$row['delta_pct'],1)).'%':'—').'</td><td>'.absint($row['offers']).'</td></tr>';
        }
        echo '</tbody></table></div></div>';
        echo '<div class="postbox" style="padding:12px">' . '<h3>' . esc_html__( 'Productos populares ajenos al inventario', 'taxocommerce-google-bing' ) . '</h3>';
        echo '<p>' . esc_html__( 'Son candidatos de Merchant, no compras ni nuevas categorías autorizadas. No equivalen a un catálogo completo de precios de competidores.', 'taxocommerce-google-bing' ) . '</p>';
        $missing=array_filter($popular,static function($r){return ($r['inventory_status']??'')==='not_in_inventory';});
        uasort($missing,static function($a,$b){return absint($a['rank']??999999)<=>absint($b['rank']??999999);});
        echo '<p>'.esc_html(count($missing)).' candidatos no presentes en el feed de Merchant.</p>';
        echo '<div style="overflow:auto">' . '<table class="widefat striped">' . '<thead>' . '<tr>' . '<th>' . esc_html__( 'Producto / marca', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Categoría Google', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Popularidad', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Evolución', 'taxocommerce-google-bing' ) . '</th>' . '<th>' . esc_html__( 'Fecha del informe', 'taxocommerce-google-bing' ) . '</th>' . '</tr>' . '</thead>' . '<tbody>';
        foreach(array_slice($missing,0,40)as$r){
            echo '<tr><td>'.esc_html($r['title']).' · '.esc_html($r['brand']).'</td><td>'.esc_html($r['category']).'</td><td>'.absint($r['rank']).'</td><td>'.esc_html($r['demand_change']).'</td><td>'.esc_html($r['report_date']).'</td></tr>';
        }
        echo '</tbody></table></div></div>';
    }
}


if ( ! class_exists( 'SEO_Ojeador_Merchant' ) ) {
    class_alias( 'TaxoCommerce_Google_Bing_Merchant', 'SEO_Ojeador_Merchant' );
}
