<?php

function screen_load_get($tn){
	$res = array();
	$res_code = 200;

	$req = $tn->app->request;
	$path = $req->get('path');

	if($path){
		if(substr($path,0,1) != '/'){
			$path = '/'.$path;
		}
		$res['content'] = $tn->screens->get_screen($path);
	}

	$tn->app->render($res_code, $res);
}

?>