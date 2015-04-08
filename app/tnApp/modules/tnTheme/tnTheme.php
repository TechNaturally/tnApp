<?php
function theme_registry_get($tn){
	$res = array();
	$res_code = 200;
	$theme_base = '/theme/templates';
	$theme_path = '..'.$theme_base;

	$registry = array();
	try{
		if($files = scandir($theme_path)){
			foreach($files as $file){
				$suffix = ".tpl.html";
				if( ($temp = strlen($file) - strlen($suffix)) >= 0 && strpos($file, $suffix, $temp) !== FALSE  ){
					$registry[] = $file;
				}
			}

			if(!empty($registry)){
				$res['base_path'] = $theme_base;
				$res['registry'] = $registry;
			}
		}
	}
	catch(Exception $e){}

	$tn->app->render($res_code, $res);
}
?>