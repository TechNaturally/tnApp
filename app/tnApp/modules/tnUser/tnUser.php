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
	// TODO: adding a new user
	// /api/user/post {user info}
}

function user_profile_get($tn, $id){
	$res = array();
	$res_code = 200;

	try{
		print "\n";

		if($user = user_get_user($tn, array('user.id' => $id))){
//			print "LOADED USER:".print_r($user, TRUE)."\n";
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
			if($user = user_save_user($tn, (array)$req->user)){
				$res['user'] = $user;
				$res['msg'] = 'User saved!';
			}
		}catch(Exception $e){
			$res['msg'] = $e->getMessage();
			$res['error'] = TRUE;
		}
	}
	$tn->app->render($res_code, $res);
}

function user_get_user($tn, $args){
	try{
		if($user = $tn->data->load('user', $args)){
			return $user;
		}
	} catch (Exception $e) { throw $e; }
	throw new \TN\DataMissingException('Could not load user.');
	return NULL;
}

function user_save_user($tn, $user){
	try{
		if($user = $tn->data->save('user', $user)){
			return $user;
		}
	} catch (Exception $e) { throw $e; }
	throw new \TN\DataInvalidException('Could not save user.');
	return NULL;
}

function user_email_exists($tn, $email){
	if($email){
		try{
			$tn->data->assert('user');
			$user = $tn->data->user()->where('email', $email)->fetch();
			return !empty($user);
		}
		catch(Exception $e){}
	}
	return FALSE;
}

?>