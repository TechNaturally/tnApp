<?php

function user_list_get($tn){
	$res = array();
	$res_code = 200;

	try{
		$tn->data->assert('user');

		$users = $tn->data->user()->select('id', 'name', 'email')->fetchPairs('id');
		$res['users'] = $users;
		$res['msg'] = count($users).' users found.';

	}catch(Exception $e){ $res['msg'] = $e->getMessage(); }

	//$res['msg'] = "Hello from user module :)";

	$tn->app->render($res_code, $res);
}

function user_list_post($tn){
	// adding a new user
	// /api/user/post {user info}
}

function user_profile_get($tn, $id){
	$res = array();
	$res_code = 200;

	try{
		if($user = user_get_user($tn, $id)){
			$res['user'] = $user;
		}
		else{
			//$res['error'] = TRUE;
			$res['msg'] = 'User not found.';
		}

	}catch(Exception $e){}

	$tn->app->render($res_code, $res);
}

function user_profile_put($tn, $id){
	$res = array();
	$res_code = 200;
	// updating a user (or if !$id... add new?)
	// /api/user/0/put {user info}

	$req = json_decode($tn->app->request->getBody());
	if($req->user){
		try{
			//$res['msg'] = print_r($req->user,true);
			if($user = user_save_user($tn, (array)$req->user)){
				$res['user'] = $user;
				$res['msg'] = 'User saved!';
			}

		}catch(Exception $e){
			$res['msg'] = $e->getMessage();
		}
	}
	
	$tn->app->render($res_code, $res);
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

function user_save_user($tn, $user){
	try{
		$tn->data->assert('user');

		$roles = NULL;
		if(isset($user['roles'])){
			$roles = $user['roles'];
			unset($user['roles']);
		}

		if(isset($user['id'])){
			$user_save = $tn->data->user()->where('id', $user['id']);
			$user_save->update($user);
			$user = $user_save->fetch();
		}
		else{
 			$user = $tn->data->user()->insert($user);
		}

		if($user){
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
				// do that only if they aren't there
				$tn->data->user_role()->insert_multi($user_roles);
				$user['roles'] = $roles;
			}
			return $user;
		}
	} catch (Exception $e) { throw $e; }
	throw new Exception('Error saving user.');
	return NULL;
}

?>