<?php
namespace TN;

class Security {
	protected $data = NULL;
	protected $rules = array();
	protected $routes = array();

	public function setData($data){
		$this->data = $data;
	}

	public function protect($type, $access){
		$this->rules[$type] = $access;
	}

	public function passes($rules, $args){
		if(is_array($rules)){
			foreach($rules as $rule){
				if(!$this->userMatches($rule, $args)){
					return FALSE;
				}
			}
		}
		else{
			return $this->userMatches($rules, $args);
		}
		return TRUE;
	}

	public function allowRead($type, $field, $args=NULL){
		print "\nsecurity check to READ [$type] [$field]\n"; //.($args?" (".print_r($args, true).")":"")."...\n";
		if(!empty($this->rules[$type])){
			$field = str_replace("$type.", '', $field);
			$args['type'] = $type;
			foreach($this->rules[$type] as $rule => $access){
				if($rule == $type && (isset($access->read) && !$this->passes($access->read, $args)) ){
					print " *BANNED ($type)*\n";
					return FALSE;
				}
				else if(strpos($field, $rule) === 0 && (isset($access->read) && !$this->passes($access->read, $args))){
					//print "   check $field vs ".$rule."\n";
					print " *BANNED ($rule)*\n";
					return FALSE;
				}
			}
		}
		return TRUE;
	}

	public function allowWrite($type, $field, $args=NULL){
		print "\nsecurity check to WRITE [$type] [$field]".($args?" (#".print_r($args, true).")":"")."...\n";
		return TRUE;
	}

	private function userMatches($rule, $args){
		if($rule === TRUE || $rule === FALSE){
			return $rule;
		}
		else if(strpos($rule, 'auth') === 0){
			return $this->userHasAccess($rule, $args);
		}
		else if(strpos($rule, '!auth') === 0){
			return !$this->userHasAccess(substr($rule, 1), $args);
		}
		else if($rule[0] == '!'){
			return !$this->userHasRole(substr($rule, 1));
		}
		else{
			return $this->userHasRole($rule);
		}
		return FALSE;
	}

	private function userHasRole($role){
		global $_SESSION;
		if(empty($_SESSION['user']) || empty($_SESSION['user']['roles']) || !is_array($_SESSION['user']['roles'])){
			return FALSE;
		}
		else{
			return in_array($role, $_SESSION['user']['roles']);
		}
		return FALSE;
	}

	private function userHasAccess($rule, $args){
		global $_SESSION;
		if(empty($_SESSION['user']) || empty($_SESSION['user']['id']) || empty($this->data)){
			return FALSE; // no user, no access
		}

		$rule_split = explode('=', $rule);
		if(count($rule_split) > 1){
			$field = $rule_split[1];
			$type = $args['type'];
			unset($args['type']);
			$data = $this->data->loadFields($type, $args, array($field), FALSE);
			$data = json_decode(json_encode($data));
			$check_field = $this->data->getNodeChild($data, $field, FALSE);
			if(is_array($check_field)){
				return in_array($_SESSION['user']['id'], $check_field);
			}
			return (!empty($check_field) && $check_field == $_SESSION['user']['id']);
		}
		else{
			return TRUE; // no field, and user is logged in
		}
		return FALSE;
	}

/**
	public function kickout(){
		$this->app->render(401, array('error' => TRUE, 'msg' => 'Access denied.'));
	}

	public function protect($route, $credentials) {
		// if credentials are simply TRUE, then there is no point protecting the route
		if($credentials !== TRUE && !$this->isProtected($route)){
			if(is_string($credentials)){
				$credentials = array($credentials);
			}
			if(is_array($credentials) || $credentials === FALSE){
				$this->routes[$route] = $credentials;
			}
		}
	}

	public function isProtected($route) {
		return array_key_exists($route, $this->routes);
	}

	private function userHasRole($role){
		global $_SESSION;
		if(empty($_SESSION['user']) || empty($_SESSION['user']['roles']) || !is_array($_SESSION['user']['roles'])){
			return FALSE;
		}
		else{
			return in_array($role, $_SESSION['user']['roles']);
		}
		return FALSE;
	}

	private function userHasAccess($params){
		global $_SESSION;
		// TODO: special user based security...
		//print "check user access for ".print_r($params, TRUE)."\n";
		return (!empty($_SESSION['user']));
	}

	

	public function passes($rules, $params){
		if(is_array($rules)){
			foreach($rules as $rule){
				if($this->userMatches($rule, $params)){
					return TRUE;
				}
			}
		}
		else{
			return $this->userMatches($rules, $params);
		}
		return FALSE;
	}

	public function allowRoute($route, $params){
		if(!$this->isProtected($route)){
			return TRUE;
		}
		if(empty($this->routes[$route])){
			return FALSE;
		}
		return $this->passes($this->routes[$route], $params);
	}

	public function call() {
		$this->app->hook('slim.before.dispatch', array($this, 'onBeforeDispatch'));
        $this->next->call();
	}

	public function onBeforeDispatch() {
		$route = $this->app->router()->getCurrentRoute();
		if($route){
			$route_pattern = $this->app->request->getMethod().$route->getPattern();
			$route_params = $route->getParams();
			if(!$this->allowRoute($route_pattern, $route->getParams())){
				$this->kickout();
			}
		}
	}
	*/
}
?>