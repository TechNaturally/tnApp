<?php
namespace TN;

class Security {
	protected $data = NULL;
	protected $data_access = array();
	protected $routes = array();

	public function setData($data){
		$this->data = $data;
	}

	public function protect($type, $access){
		$this->data_access[$type] = $access;
	}

	public function allowRead($type, $field, $args=NULL){
		print "\nsecurity check to READ [$type] [$field]".($args?" (".print_r($args, true).")":"")."...\n";
		return TRUE;
	}

	public function allowWrite($type, $field, $args=NULL){
		print "\nsecurity check to WRITE [$type] [$field]".($args?" (#".print_r($args, true).")":"")."...\n";
		return TRUE;
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

	private function userMatches($rule, $params){
		if($rule === TRUE || $rule === FALSE){
			return $rule;
		}
		else if($rule == 'user'){
			return $this->userHasAccess($params);
		}
		else if($rule == '^user'){
			return !$this->userHasAccess($params);
		}
		else if($rule[0] == '^'){
			return !$this->userHasRole(substr($rule, 1));
		}
		else{
			return $this->userHasRole($rule);
		}
		return FALSE;

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