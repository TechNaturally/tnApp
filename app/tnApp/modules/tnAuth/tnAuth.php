<?php
function auth_session_start($user){
	global $_SESSION;
	if(!empty($_SESSION['user'])){
		return $_SESSION['user'];
	}
	if(!empty($user['id']) && isset($user['roles'])){
		$_SESSION['user'] = array( "id" => $user['id'], "roles" => $user['roles'] );
		return auth_session_check();
	}
	return NULL;
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

function auth_assert_user($tn, $auth){
	try{
		if(function_exists('user_get_user')){
			try {
				return user_get_user($tn, array('auth' => $auth['id']));
			}
			catch(\TN\DataMissingException $e){
				// TODO: default roles? - should be in app's config.json - do we check in user_save_user for new users?
				if(function_exists('user_save_user')){
					return user_save_user($tn, array(
						'auth' => $auth['id'],
						'email' => $auth['email'],
						'name' => $auth['username'],
						'roles' => array('admin', 'test')
						));
				}
			}
		}
	}
	catch(Exception $e) { throw $e; }

	return NULL;
}

function auth_load_get($tn, $id){
	$res = array();
	$res_code = 200;

	try{
		print "\n";

		$auth = $tn->data->load('auth', array('auth.id' => $id));
		if($auth){
			print "*** GOT AUTH #$id:".print_r($auth, TRUE)."\n";

		}
		else{
			$res['msg'] = 'Auth not found.';
		}

	}catch(Exception $e){
		// EXAMPLE OF ERROR BUBBLING
		$res_code = 500;
		$res['msg'] = $e->getMessage();
		$res['error'] = TRUE;	
	}

	$tn->app->render($res_code, $res);
}

function auth_ping_post($tn){
	$res = array();
	$res_code = 200;

	if($session = auth_session_check()){
		$res['session'] = $session;
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
		if(!empty($req->username) && !empty($req->password)){
			$auth = $tn->data->loadFields('auth', array('username' => $req->username), array('username', 'email', 'hash'));
			if($auth){
				if(auth_password_check($req->password, $auth['hash'])){
					unset($auth['hash']);
					if($user = auth_assert_user($tn, $auth)){
						if($session = auth_session_start($user)){
							$res['msg'] = 'Login successful!';
							$res['session'] = $session;
						}
						else{
							$res['error'] = TRUE; // no session
						}
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
				$res['error'] = TRUE; // no auth for username
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

		$req = json_decode($tn->app->request->getBody());

		$username = !empty($req->username)?$req->username:'';
		$email = auth_valid_email($username)?$username:'';

		$auth = array(
			"username" => $username,
			"hash" => auth_password_hash($req->password),
			"email" => $email
			);
		if($auth = $tn->data->save('auth', $auth)){
			$res['msg'] = "Created auth:<pre>".print_r($auth, TRUE)."</pre>\n";
			if($user = auth_assert_user($tn, $auth)){
				if($session = auth_session_start($user)){
					$res['session'] = $session;
				}
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
		$auth = $tn->data->auth()->where('username', $username)->fetch();
		return !empty($auth);
	}
	return FALSE;
}

function auth_email_exists($tn, $email){
	if($email){
		$auth = $tn->data->auth()->where('email', $email)->fetch();
		return !empty($auth);
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