<?php
if(!defined('FVN_THEME_URL')) define('FVN_THEME_URL', site_url('/wp-content/themes/freelancerviet.net/'));
if(!defined('FVN_THEME_PATH')) define('FVN_THEME_PATH', __DIR__);

if(isset($_GET['error_detail'])){
	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
}else{
	ini_set('display_errors', 0);
	ini_set('display_startup_errors', 0);
}
//short code
foreach (glob(FVN_THEME_PATH.'/shortcodes/*.php') as $filename)
{
	require_once $filename;
}
require_once 'inc/post-utility.php';
require_once 'inc/brand.php';
require_once 'inc/product/product.php';
// Add custom Theme Functions here
add_filter('use_block_editor_for_post','__return_false');
add_filter( 'auto_update_plugin', '__return_false' );
add_filter( 'auto_update_theme', '__return_false' );

add_action( 'wp_enqueue_scripts', function () {
	wp_dequeue_style('flatsome-style');
	//wp_enqueue_style( 'fontawesome', FVN_THEME_URL.'css/fontawesome.min.css');
	//wp_enqueue_style( 'fontawesome-light', FVN_THEME_URL.'css/font-awesome-light.css');
	wp_enqueue_style ('fvn-style', FVN_THEME_URL.'style.css?v='.time());
	wp_enqueue_style( 'fvn-utils', FVN_THEME_URL.'css/utils.css?t='.time());	

},9999);


if(!function_exists('debug')){
	function debug($val){
		echo '<pre>';
		print_r($val);
		echo '</pre>';
	}
}
function fvn_get_client_ip() {
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}
