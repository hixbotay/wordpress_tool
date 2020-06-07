<?php
if(!function_exists('debug')){
	function debug($var){
		echo '<pre>';print_r($var);echo '</pre>';
	}
}
function jb_array_get($array, $key)

{

    return isset($array[$key]) ? $array[$key] : null;

}

