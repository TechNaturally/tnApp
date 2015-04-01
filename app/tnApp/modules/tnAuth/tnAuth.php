<?php
function auth_session_start($user){
	global $_SESSION;
	if(!empty($_SESSION['user'])){
		return $_SESSION['user'];
	}
	$_SESSION['user'] = $user;

	return auth_session_check();
}

function auth_session_check(){
	global $_SESSION;
	return (!empty($_SESSION['user'])?$_SESSION['user']:NULL);
}

function auth_session_finish(){
	global $_SESSION;
	if(!empty($_SESSION['user'])){
		unset($_SESSION['user']);
		return TRUE;
	}
	return FALSE;
}

function auth_user_assert_profile($tn, $user){
	try {
		if(function_exists('user_get_user')){
			$profile = user_get_user($tn, array('auth_id' => $user['id']));

			if(!$profile && function_exists('user_save_user')){
				$profile = user_save_user($tn, array(
					'auth' => array('id' => $user['id']),
					'email' => $user['email'],
					'name' => $user['username']
					));
			}
			// TODO: what about default roles? - should be in app's config.json - do we check in user_save_user for new users?

			return $profile;
		}
	} catch(Exception $e) {}

	return NULL;
}

function auth_ping_post($tn){
	$res = array();
	$res_code = 200;

	if($user = auth_session_check()){
		$res['user'] = $user;
		$res['msg'] = 'Session active!';
	}

	$tn->app->render($res_code, $res);
}

function auth_login_post($tn){
	$res = array();
	$res_code = 200;

	try {
		$tn->data->assert('auth');

		$req = json_decode($tn->app->request->getBody());
		if($req->username && $req->password){
			$user = $tn->data->auth("username", $req->username)->fetch();
			if($user && !empty($user['hash'])){
				if(auth_password_check($req->password, $user['hash'])){
					$roles = array('user');
					if($profile = auth_user_assert_profile($tn, $user)){
						if(!empty($profile['roles'])){
							$roles = array_merge($roles, $profile['roles']);
						}
						$res['user'] = auth_session_start(array(
							'id' => $profile['id'],
							'roles' => $roles
							));
						$res['msg'] = 'Login successful!';
					}
					else{
						$res['error'] = TRUE; // no profile
					}
				}
				else {
					$res['error'] = TRUE; // bad password
				}
			}
			else {
				$res['error'] = TRUE; // bad username
			}
		}
		else {
			$res['error'] = TRUE; // missing username or password
		}
		if(!empty($res['error'])){
			$res['msg'] = 'Invalid username or password!';
		}
	} catch (Exception $e) {
		$res['error'] = TRUE; // something went wrong
		$res['msg'] = $e->getMessage();
		$res_code = 500;
	}

	$tn->app->render($res_code, $res);
}

function auth_logout_post($tn){
	$res = array();
	$res_code = 200;
	if(auth_session_check()){
		auth_session_finish();
		$res['msg'] = 'Logout successful!';
	}
	else{
		$res['error'] = TRUE;
		$res['msg'] = 'Not logged in!';
	}

	$tn->app->render($res_code, $res);
}

function auth_register_post($tn){
	$res = array();
	$res_code = 200;

	try {
		$tn->data->assert('auth');

		$req = json_decode($tn->app->request->getBody());

		$username = $req->username;
		$email = '';

		if(auth_valid_email($username)){
			$email = $username;
		}
		
		$user = array(
			"username" => $username,
			"hash" => auth_password_hash($req->password),
			"email" => $email
			);
		$user = $tn->data->auth()->insert($user);
		$res['msg'] = 'Registration successful!';

		if(!empty($req->start_session)){
			$roles = array('user');
			if($profile = auth_user_assert_profile($tn, $user)){
				if(!empty($profile['roles'])){
					$roles = array_merge($roles, $profile['roles']);
				}

				$res['user'] = auth_session_start(array(
					'id' => $profile['id'],
					'roles' => $roles
					));
			}
			else{
				$res['error'] = TRUE;
				$res['msg'] = 'Could not load user profile.';
			}
		}
	} catch (Exception $e) {
		$res_code = 500;
		$res['msg'] = "Error: ".$e->getMessage();
	}
	$tn->app->render($res_code, $res);
}

function auth_recover_post($tn){
	$request = json_decode($tn->app->request->getBody());
	$tn->app->render(200, array('msg' => 'Auth Recover [POST] route! <pre>'.print_r($request,true).'</pre>'));
}

function auth_available_get($tn){
	$res = array();
	$res_code = 200;

	try{
		$tn->data->assert('auth');

		$req = $tn->app->request;
		$username = $req->get('username');
		$email = $req->get('email');

		if(auth_valid_email($username)){
			$res['is_email'] = TRUE;
			$email = $username;
			$username = '';
		}

		if($username){
			$res['available'] = !auth_username_exists($tn, $username);
			$res['msg'] = "Username is".(!$res['available']?" not":"")." available.";
		}
		else if($email){
			$res['valid'] = auth_valid_email($email);
			$res['available'] = ($res['valid'] && !auth_email_exists($tn, $email));
			$res['msg'] = "Email is";
			if(!$res['valid']){
				$res['msg'] .= " not valid.";
			}
			else{
				$res['msg'] .= (!$res['available']?" not":"")." available.";
			}
		}

	} catch (Exception $e) {
		$res_code = 500;
		$res['msg'] = "Error: ".$e->getMessage();
	}

	$tn->app->render($res_code, $res);
}

function auth_username_exists($tn, $username){
	if($username){
		$user = $tn->data->auth()->where('username', $username)->fetch();
		return !empty($user);
	}
	return FALSE;
}

function auth_email_exists($tn, $email){
	if($email){
		$user = $tn->data->auth()->where('email', $email)->fetch();
		return !empty($user);
	}
	return FALSE;
}


function auth_valid_email($username){
	return (function_exists('email_is_valid') && email_is_valid($username));
}

function auth_password_check($password, $hash){
	require_once "PasswordHash.php";
	return validate_password($password, $hash);
	return TRUE;
}

function auth_password_hash($password){
	require_once "PasswordHash.php";
	return create_hash($password);
}

?>