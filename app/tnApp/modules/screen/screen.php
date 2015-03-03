<?php

function screen_load_get($tn){
	$res = array();
	$res_code = 200;

	$req = $tn->app->request;
	$path = $req->get('path');

	if($path){
		$res['content'] = array();

		/** JSON:
		{
			'user/:user_id': {
							5: [
								{ 'type': 'widget',
									'content': 'tn-user',
									'args': {':user_id' => 'id'},
									'access': ['user']
								}
							],
							10: [
								{ 'type': 'widget',
									'content': 'tn-user-list',
									'access': ['content']
								}
							]
						}
		}

		*/

		$paths = array('user/:id' => array('type' => 'widget', 'content' => array(5 => array('tn-user', 'args' => array(':user_id' => 'id')))));
		/** TODO:
		- parse path arg names based on (:/w+)
		- match path as regexp
			- map path args to content args
		*/

		if(substr($path, 0, 4) == 'user'){
			$res['content'][5] = array();
			$res['content'][5][] = array(
				'type' => 'widget',
				'content' => 'tn-user'
				);
		}
	}

	$tn->app->render($res_code, $res);
}

?>