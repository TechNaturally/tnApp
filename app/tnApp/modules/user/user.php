<?php

function user_list_get($tn){
	$res = array();
	$res_code = 200;

	$res['msg'] = "Hello from user module :)";

	$tn->app->render($res_code, $res);
}

function user_list_post($tn){
	// adding a new user
	// /api/user/post {user info}
}

function user_profile_get($tn, $id){
	$res = array();
	$res_code = 200;

	$res['msg'] = "Hello from user #$id :)";

	$tn->app->render($res_code, $res);
}

function user_profile_put($tn, $id){
	// updating a user (or if !$id... add new?)
	// /api/user/0/put {user info}
}

function user_get_user($tn, $id, $field='id'){
	try{
		$tn->data->assert('user');
		if($user = $tn->data->user()->where($field, $id)->fetch()){
			$user = $tn->data->rowToArray($user);
			$tn->data->assert('user_role');
			if($roles = $tn->data->user_role()->where('user_id', $user['id'])->fetchPairs('role', TRUE)){
				$user['roles'] = array_keys($roles);
			}
			return $user;
		}
	} catch (Exception $e) {}
	return NULL;
}

function user_create_user($tn, $user){
	try{
		$tn->data->assert('user');

		$roles = NULL;
		if(isset($user['roles'])){
			$roles = $user['roles'];
			unset($user['roles']);
		}

		if($user = $tn->data->user()->insert($user)){
			$user = $tn->data->rowToArray($user);
			if(!empty($roles)){
				$tn->data->assert('user_role');
				$user_roles = array();
				foreach($roles as $role_id){
					$user_roles[] = array(
						'user_id' => $user['id'],
						'role' => $role_id
						);
				}
				$tn->data->user_role()->insert_multi($user_roles);
				$user['roles'] = $roles;
			}
			return $user;
		}
	} catch (Exception $e) {}

	return NULL;
}

?>