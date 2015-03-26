<?php

function user_list_get($tn){
	$res = array();
	$res_code = 200;

	try{
		$users = $tn->data->listOf('user');
		$res['users'] = $users;
		$res['msg'] = count($users).' users found.';

	}catch(Exception $e){ $res['msg'] = $e->getMessage(); }

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
		/**$table_def = $tn->data->getTableDefs('user'); // table defs working
		if($table_def){
			print "user table def:".print_r($table_def,true)."\n";
		}
		else{
			print "bad news bears up in there!\n";
		}
		*/

/**
		if($fields = $tn->data->getFields('auth', 'input')){
			print "auth input fields:".print_r($fields,true)."\n";
		}
		else{
			print "no auth input fields :(\n";
		}

		if($fields = $tn->data->getFields('auth', 'save')){
			print "auth save fields:".print_r($fields,true)."\n";
		}
		else{
			print "no auth save fields :(\n";
		}

		if($fields = $tn->data->getFields('auth', 'list')){
			print "auth list fields:".print_r($fields,true)."\n";
		}
		else{
			print "no auth list fields :(\n";
		}
		if($fields = $tn->data->getFields('auth', 'load')){
			print "auth load fields:".print_r($fields,true)."\n";
		}
		else{
			print "no auth load fields :(\n";
		}
		*/

		//$tn->data->assert('user');

/**
		if($fields = $tn->data->getFields('user', 'input')){
			print "user input fields:".print_r($fields,true)."\n";
		}
		else{
			print "no user input fields :(\n\n";
		}

		if($fields = $tn->data->getFields('user', 'save')){
			print "user save fields:".print_r($fields,true)."\n";
		}
		else{
			print "no user save fields :(\n\n";
		}

		if($fields = $tn->data->getFields('user', 'list')){
			print "user list fields:".print_r($fields,true)."\n";
		}
		else{
			print "no user list fields :(\n\n";
		}
		if($fields = $tn->data->getFields('user', 'load')){
			print "user load fields:".print_r($fields,true)."\n";
		}
		else{
			print "no user load fields :(\n\n";
		}
*/

		// TODO: filter tableDefs by field list
		// - getTableDef('input')
		// - getTableDef('save')
		// - getTableDef('list')
		// - getTableDef('load')

		//$tn->data->assert('user');
		/**
		$row = $tn->data->user()->select("user.id", "user.name", "auth.username")->where('user.id', $id)->fetch();
		$data = $tn->data->rowToArray($row);
		$rows = $row->user_test_t2_tD()->select("user_test_t2_tD.tD_d1")->fetchPairs('id');
		$array_rows = array();
		foreach($rows as $c_id => $c_row){
			$c_a_rows = $c_row->user_test_t2_tD_d2()->fetchPairs('id');
			$array_rows[$c_id] = array();
			foreach($c_a_rows as $c_a_id => $c_a_row){
				$array_rows[$c_id][$c_a_id] = $tn->data->getArrayRowValue($c_a_row, 'user_test_t2_tD_d2');
			}
		}
		print "\nDATA:".print_r($array_rows,TRUE)."\n";
*/
		
		


		if($user = user_get_user($tn, array('user.id' => $id))){
			$res['user'] = $user;
		}
		else{
			//$res['error'] = TRUE;
			$res['msg'] = 'User not found.';
		}

	}catch(Exception $e){
		// EXAMPLE OF ERROR BUBBLING
		$res_code = 500;
		$res['msg'] = $e->getMessage();
		$res['error'] = TRUE;	
	}

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

function user_get_user($tn, $args){
	try{
		//$tn->data->assert('user');



		if($user = $tn->data->load('user', $args)){
			return $user;
		}

/**		if($user = $tn->data->user()->where($field, $id)->fetch()){
			$user = $tn->data->rowToArray($user);
			//$tn->data->assert('user.role');
			if($roles = $tn->data->{'user.role'}()->where('user.id', $user['id'])->fetchPairs('role', TRUE)){
				$user['roles'] = array_keys($roles);
			}
			return $user;
		}
		*/

	} catch (Exception $e) { throw $e; }
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