<?php
namespace TN;
use Exception;

require_once "Data.php";
require_once "Screen.php";
require_once "Security.php";

class App {
	public $app;
	public $data;
	public $screens;
	public $security;

	protected $module_path;

	public function __construct($app, $config_file="tna_config.json"){
		$this->app = $app;

		$config = array(
			"name" => 'TNApp',
			"modules" => [],
			"module_path" => '../modules'
			);
		$config_path = dirname(__FILE__).'/../../'.$config_file;
		if($config_path && file_exists($config_path)){
			$config_load = json_decode(file_get_contents($config_path));
			if(!empty($config_load)){
				foreach($config_load as $set => $setting){
					$config[$set] = $setting;
				}
			}
		}

		if(!empty($config['name'])){
			$this->app->setName($config['name']);
		}

		$this->init_security();

		$this->init_screens();

		if(!empty($config['data'])){
			$this->init_data($config['data']);
		}

		if(!empty($config['modules'])){
			$this->load_modules($config['modules'], dirname(__FILE__).'/'.$config['module_path']);
		}
	}

	protected function init_data($config){
		// initialize data manager
		$this->data = new \TN\Data($config);

		// add a route for requesting the schema
		$this->app->get('/schema/:id', function($id){
			// we generally (can't think of exceptions) only want to be telling the clients about the data they can input
			$schema = $this->data->getSchema($id, 'input');
			if(!$schema){
				// make an empty schema
				$schema = new stdClass;
				$schema->id = "/$id";
				$schema->type = "object";
				$schema->properties = new stdClass;
			}
			if($schema){
				$this->deliver_schema($schema);
			}
			else{
				// could not find the schema
				$this->app->pass();
			}
		});
	}

	protected function init_screens(){
		$this->screens = new \TN\ScreenManager($this);
	}

	protected function init_security(){
		// create and add our custom Security middleware to Slim, we also use the encrypted SessionCookie middleware
		$this->security = new \TN\Security();
		$this->app->add($this->security);
		$this->app->add(new \Slim\Middleware\SessionCookie(array('name' => $this->app->getName().'_session')));
	}

	protected function load_modules($modules, $module_path){
		if(empty($module_path) && empty($this->module_path)){ return; }
		if(!empty($module_path)){
			if($module_path[0] != '/'){
				$module_path = dirname(__FILE__).'/'.$module_path;
			}
			if(substr($module_path, -1) != '/'){
				$module_path .= '/';
			}
			$this->module_path = $module_path;
		}
		foreach($modules as $module_id){
			$this->load_module($module_id, $this->module_path.$module_id);
		}	
	}

	private function load_module($module_id, $module_path){
		$module_json = "$module_path/$module_id.json";
		$module_php = "$module_path/$module_id.php";
		$module = NULL;

		// try to load the module config from json
		if(file_exists($module_json)){
			$module = json_decode(file_get_contents($module_json));
		}

		// load the php callbacks
		if(file_exists($module_php)){
			require_once($module_php);
		}

		// if module config loaded
		if($module){
			if($module->id){
				$module_id = $module->id;
			}

/**
			// set the database fields
			if(!empty($module->database)){
				$this->data->addTableFields($module_id, $module->database);
			}

			// load the schema
			if(!empty($module->schema)){
				$this->data->addSchema('/'.$module_id, $module->schema);
			}

			*/
			if(!empty($module->data)){
				$this->data->addType($module_id, $module->data);
			}



			// load the routes
			if(!empty($module->routes)){
				$this->load_module_routes($module_id, $module->routes);
			}

			// load the screens
			if(!empty($module->screens)){
				$this->load_module_screens($module_id, $module->screens);
			}
		}
	}

	private function load_module_screens($module_id, $screens){
		foreach($screens as $path => $content){
			$this->screens->add_screen($path, $content);
		}
	}

	private function load_module_routes($module_id, $routes){
		foreach($routes as $local_path => $route){
			$path = '/'.$module_id.(($local_path[0] != '/')?'/':'').$local_path;
			if(!empty($route->callback) && !empty($route->methods)){
				// read default access and form settings for this route
				// specific request methods can override them if needed

				$base_callback = $route->callback;

				$route_callback = $module_id.'_'.$route->callback;

				// default route access, if none given defaults to TRUE
				$route_access = isset($route->access)?$route->access:TRUE;

				// default input form for this route - will be served through <path>/form
				$route_form = isset($route->form)?$route->form:NULL;

				// method-specific settings
				foreach($route->methods as $method => $settings){
					if(!$settings){
						continue; // method disabled
					}
					$method_callback = $route_callback.'_'.strtolower($method);

					// use route default access + form unless overridden by method's settings
					$method_access = $route_access;
					$method_form = $route_form;
					if(is_object($settings)){
						if(isset($settings->access)){
							$method_access = $settings->access;
						}
						if(isset($settings->form)){
							$method_form = $settings->form;
						}
					}

					// register the route method (if the callback is found)
					if(function_exists($method_callback)){
						// app route handlers
						if($method == 'GET'){
							$this->app->get($path, function() use($method_callback){ $args = func_get_args(); call_user_func_array($method_callback, array_merge(array($this), $args)); } );
							if($method_form){
								$this->app->get($path.'/form', function() use($method_form, $base_callback){ $this->deliver_form($method_form, $base_callback); });
							}
						}
						else if($method == 'POST'){
							$this->app->post($path, function() use($method_callback){ $args = func_get_args(); call_user_func_array($method_callback, array_merge(array($this), $args)); } );
							if($method_form){
								$this->app->post($path.'/form', function() use($method_form, $base_callback){ $this->deliver_form($method_form, $base_callback); });
							}
						}
						else if($method == 'PUT'){
							$this->app->put($path, function() use($method_callback){ $args = func_get_args(); call_user_func_array($method_callback, array_merge(array($this), $args)); } );
							if($method_form){
								$this->app->put($path.'/form', function() use($method_form, $base_callback){ $this->deliver_form($method_form, $base_callback); });
							}
						}
						else if($method == 'DELETE'){
							$this->app->delete($path, function() use($method_callback){ $args = func_get_args(); call_user_func_array($method_callback, array_merge(array($this), $args)); } );
						}
						else if($method == 'OPTIONS'){
							$this->app->options($path, function() use($method_callback){ $args = func_get_args(); call_user_func_array($method_callback, array_merge(array($this), $args)); } );
						}
						else if($method == 'HEAD'){
							$this->app->head($path, function() use($method_callback){ $args = func_get_args(); call_user_func_array($method_callback, array_merge(array($this), $args)); } );
						}
						else{
							continue; // unsupported method
						}

						// protect the route method if there is a security handler
						if(!empty($this->security)){
							$this->security->protect($method.$path, $method_access);

							// also protect the route's form
							if($method_form){
								$this->security->protect($method.$path.'/form', $method_access);
							}
						}
					}
				}
			}
		}
	}

	protected function deliver_form($form, $callback){
		$this->app->render(200, array("form"=>$form, "callback"=>$callback));
	}

	protected function deliver_schema($schema){
		$this->app->render(200, array("schema"=>$schema));
	}
}

?>