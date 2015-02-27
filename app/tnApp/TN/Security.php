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

	public function allow($route){
		if(!$this->isProtected($route)){
			return TRUE;
		}
		if(empty($this->routes[$route])){
			return FALSE;
		}

		global $_SESSION;
		foreach($this->routes[$route] as $credential){
			// TODO: this is the fun part of checking credentials against $_SESSION
			if($credential == 'user' && !empty($_SESSION['user'])){
				return TRUE;
			}
		}

		return FALSE;
	}

	public function call() {
		$this->app->hook('slim.before.dispatch', array($this, 'onBeforeDispatch'));
        $this->next->call();
	}

	public function onBeforeDispatch() {
		$route = $this->app->router()->getCurrentRoute();
		if($route){
			$route_pattern = $this->app->request->getMethod().$route->getPattern();
			if(!$this->allow($route_pattern)){
				$this->kickout();
			}
		}
	}
}
?>