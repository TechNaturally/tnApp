<?php
// email regex source: http://regexlib.com/REDetails.aspx?regexp_id=3122
define('EMAIL_RX', '/^[0-9a-zA-Z]+([0-9a-zA-Z]*[-._+])*[0-9a-zA-Z]+@[0-9a-zA-Z]+([-.][0-9a-zA-Z]+)*([0-9a-zA-Z]*[.])[a-zA-Z]{2,6}$/');

function email_is_valid($address){
	return (!empty(preg_match(EMAIL_RX, $address))?TRUE:FALSE);
}

function email_valid_get($tn){
	$res = array();
	$res_code = 200;

	$req = $tn->app->request;
	$email = $req->get('email');

	$res['valid'] = email_is_valid($email);
	$res['msg'] = "Email address is".(!$res['valid']?" not":"")." valid.";

	$tn->app->render($res_code, $res);
}

?>