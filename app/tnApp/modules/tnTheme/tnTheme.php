<?php
function theme_registry_get($tn){
	$res = array();
	$res_code = 200;
	$theme_base = '/theme/templates';
	$theme_path = '..'.$theme_base;

	if($registry = theme_get_templates($theme_path)){
		if(!empty($registry)){
			$res['base_path'] = $theme_base;
			$res['registry'] = $registry;
		}
	}
	
	$tn->app->render($res_code, $res);
}

function theme_get_templates($path, $suffix=".tpl.html"){
	$templates = array();
	try{
		if($files = scandir($path)){
			foreach($files as $file){
				if($file != '.' && $file != '..' && is_dir($path.'/'.$file)){
					$child_templates = theme_get_templates($path.'/'.$file);
					if(!empty($child_templates)){
						foreach($child_templates as $child_file){
							$templates[] = $file.'/'.$child_file;
						}
					}
				}
				else if( ($temp = strlen($file) - strlen($suffix)) >= 0 && strpos($file, $suffix, $temp) !== FALSE ){
					$templates[] = $file;
				}
			}
		}
	}
	catch(Exception $e){}
	return $templates;
}
?>