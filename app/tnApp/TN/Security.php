<?php
namespace TN;

class Security extends \Slim\Middleware {
	protected $routes = array();

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

	private function userMatches($rule){
		if($rule === TRUE || $rule === FALSE){
			return $rule;
		}
		else if($rule == 'user'){
			// TODO: special user based security...
		}
		else if($rule == '^user'){
			// TODO: special user based security...
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
				if($this->userMatches($rule)){
					return TRUE;
				}
			}
		}
		else{
			return $this->userMatches($rules);
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
}
?>