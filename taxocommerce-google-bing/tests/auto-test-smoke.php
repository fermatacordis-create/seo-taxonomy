<?php
/** Standalone regression for grouped pre/post Insights Auto Test. */
define('ABSPATH', __DIR__);
define('TAXOCOMMERCE_GOOGLE_BING_VERSION','1.1.62');
function home_url($path='/'){return 'https://example.test'.$path;}
function current_time($format){return '2026-10-29 12:00:00';}
function get_current_blog_id(){return 1;}
class TaxoCommerce_Connections_Status {
    public static function definitions(){return array('gsc'=>array('name'=>'GSC','mode'=>'test','ready'=>false));}
}
function assert_auto($value,$label){if(!$value){echo 'FAIL '.$label.PHP_EOL;exit(1);}}
require dirname(__DIR__).'/includes/class-taxocommerce-google-bing-auto-test.php';
$groups=TaxoCommerce_Google_Bing_Auto_Test::groups();
$cases=TaxoCommerce_Google_Bing_Auto_Test::cases();
assert_auto(count($groups)===10,'group count');
assert_auto(count($cases)>=34,'case count');
assert_auto(isset($cases['analyst_tests'],$cases['provider_gsc'],$cases['live_requests']),'critical cases');
$test=array('test'=>array('startup','Injected deterministic test'));
$report=TaxoCommerce_Google_Bing_Auto_Test::run_suite('uat',$test);
assert_auto($report['totals']['not_run']===1 && $report['totals']['pass']===0,'not-run is grey');
$before=array('site_hash'=>'same','group'=>'all','phase'=>'before','rows'=>array(array('id'=>'first','status'=>'pass'),array('id'=>'second','status'=>'fail'),array('id'=>'third','status'=>'pass')));
$after=array('site_hash'=>'same','group'=>'all','phase'=>'after','rows'=>array(array('id'=>'first','status'=>'fail'),array('id'=>'second','status'=>'pass'),array('id'=>'third','status'=>'skip'),array('id'=>'new','status'=>'fail')));
$result=TaxoCommerce_Google_Bing_Auto_Test::compare_reports($before,$after);
assert_auto(count($result['new_failures'])===2,'regressions');
assert_auto(count($result['resolved'])===1,'resolved');
assert_auto(count($result['coverage_lost'])===1,'lost coverage');
assert_auto(count($result['new_checks'])===1,'new cases');
$after['site_hash']='another';
assert_auto(null===TaxoCommerce_Google_Bing_Auto_Test::compare_reports($before,$after),'cross-site comparison blocked');
$after['site_hash']='same';$after['phase']='check';
assert_auto(null===TaxoCommerce_Google_Bing_Auto_Test::compare_reports($before,$after),'non-after comparison blocked');
$view_source=file_get_contents(dirname(__DIR__).'/includes/class-taxocommerce-google-bing-auto-test.php');
assert_auto(false!==strpos($view_source,"self::run_form( 'all', 'check', __( 'Ejecutar Autocheck'"),'one click check');
assert_auto(false!==strpos($view_source,"self::export_form( 'last', __( 'Descargar resultados (JSON)'"),'last JSON available');
assert_auto(false===strpos($view_source,"self::run_form( 'all', 'before'"),'no before button');
assert_auto(false===strpos($view_source,"self::run_form( 'all', 'after'"),'no after button');
assert_auto(false===strpos($view_source,"self::run_form( $group_id, 'check'"),'no per-group buttons');
assert_auto(false!==strpos($view_source,'TaxoCommerce_Insights_Autotest_Code::render_subtabs'),'code check subtab kept');
echo 'Insights Auto Test smoke passed.'.PHP_EOL;

/* Shared family report contract: new and previously saved check records. */
$legacy_report = array(
    'version' => '0', 'executed_at' => '2026-11-14T12:00:00+00:00',
    'rows' => array( array(
        'id' => 'test_case', 'group' => 'startup', 'name' => 'Legacy case',
        'detail' => 'Legacy detail', 'status' => 'pass', 'ms' => 2,
    ) ),
);
$canonical_report = TaxoCommerce_Google_Bing_Auto_Test::standardize_report( $legacy_report );
assert_auto( isset( $canonical_report['rows'][0]['group_id'], $canonical_report['rows'][0]['group_name'], $canonical_report['rows'][0]['label'], $canonical_report['rows'][0]['message'], $canonical_report['rows'][0]['ms'] ), 'Unified row fields' );
assert_auto( 'Legacy case' === $canonical_report['rows'][0]['label'] && 'Legacy detail' === $canonical_report['rows'][0]['message'], 'Historical text retained' );
assert_auto( 'Legacy case' === $canonical_report['rows'][0]['name'] && 'Legacy detail' === $canonical_report['rows'][0]['detail'], 'Legacy keys retained' );
assert_auto( isset( $canonical_report['wordpress'], $canonical_report['php'], $canonical_report['date'], $canonical_report['executed_at'], $canonical_report['timestamp'] ), 'Unified report metadata' );
assert_auto( false !== json_encode( $canonical_report ), 'Report JSON encodes' );
