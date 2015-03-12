<?php
namespace TN;

class ScreenManager {
	protected $app;
	protected $screens;

	public function __construct($app){
		$this->app = $app;
		$this->screens = array();
	}

	public function add_screen($path, $contents){
		if(!isset($this->screens[$path])){
			$this->screens[$path] = array();
		}

		foreach($contents as $area => $content){
			if(empty($content)){ continue; }
			if(!isset($this->screens[$path][$area])){
				$this->screens[$path][$area] = $content;
			}
			else{
				$this->screens[$path][$area] = array_merge($this->screens[$path][$area], $content);
			}
		}
	}

	public function get_screen($path){
		$screen = array();
		foreach($this->screens as $screen_path => $contents){
			$path_rx = $screen_path;

			$path_rx = preg_replace('/\*/', '.*', $path_rx);

			$args_rx = preg_replace('/:([^\/]+)/', '(:\w[\w\d]*\??)', $path_rx); // match arg names
			$args_rx = preg_replace('/\//', '\\/', $args_rx); // allow slashes

			$path_rx = preg_replace('/\/:([^\/]+)\?/', '(/[\w\d]*)?', $path_rx); // match optional args
			$path_rx = preg_replace('/:([^\/]+)/', '(\w[\w\d]*)', $path_rx); // match args

			$path_rx = preg_replace('/\//', '\\/', $path_rx); // allow slashes
			
			// check if the path matches
			if(preg_match("/^$path_rx$/", $path, $matches)){
				array_shift($matches); // matches will contain the arg values

				$args = array();

				// extract the arg names
				$arg_matches = array();
				preg_match("/^$args_rx$/", $screen_path, $arg_matches);
				array_shift($arg_matches);

				// if there are arg names found
				if(!empty($arg_matches)){
					foreach($arg_matches as $arg_idx => $arg_id){
						if(substr($arg_id,-1)=='?'){
							$arg_id = substr($arg_id, 0, -1);
						}
						// map the arg values to the arg names
						if($arg_idx < count($matches)){
							if($matches[$arg_idx][0] == '/'){
								$matches[$arg_idx] = substr($matches[$arg_idx], 1);
							}
							$args[$arg_id] = $matches[$arg_idx];
						}
						else{
							$args[$arg_id] = '';
						}
					}
				}

				// add the content sorted by area
				foreach($contents as $area => $content){
					if(empty($content)){ continue; }
					// for each piece of content
					foreach($content as $content_idx => $content_data){

						// skip if it is hidden on this path
						if(!empty($content_data->hide)){
							$hidden_paths = array_filter($content_data->hide, function($hide_path) use ($path){
								$hide_path = preg_replace('/\*/', '.*', $hide_path);
								$hide_path = preg_replace('/\//', '\\/', $hide_path);
								return preg_match("/^$hide_path$/", $path);
							});
							if(!empty($hidden_paths)){
								$content[$content_idx] = NULL;
								continue;
							}
						}


						// add the name-mapped args
						$data_args = array();
						if(!empty($content_data->args)){
							foreach($content_data->args as $data_arg => $arg_data){
								if($arg_data && is_string($arg_data) && $arg_data[0] == ':'){
									// handle arguments from the path
									$arg_data_split = explode('?', $arg_data, 2);
									if(count($arg_data_split) > 1){
										// if it is an optional argument
										$path_arg = $arg_data_split[0];
										$arg_value = $arg_data_split[1]; // static default
									}
									else{
										$path_arg = $arg_data;
										$arg_value = ''; // empty default
									}
									// if arg is in the path, copy the value
									if($path_arg && isset($args[$path_arg]) && !empty($args[$path_arg])){
										$arg_value = $args[$path_arg];
									}
								}
								else{
									// static argument value
									$arg_value = $arg_data;
								}

								// special arg values
								if($arg_value == '!auth_id' && function_exists('auth_session_check')){
									$auth_user = auth_session_check();
									$arg_value = ($auth_user && isset($auth_user['id']))?$auth_user['id']:'';
								}
								
								// store it for the content
								$data_args[$data_arg] = $arg_value;
							}
							$content[$content_idx]->args = $data_args;
						}
						// check security access for this content
						if(!empty($content_data->access) && !$this->app->security->passes($content_data->access, $data_args)){
							$content[$content_idx] = NULL;
							continue;
						}
					}

					// add the prioritized content to the screen
					$content = array_filter($content);
					if(!empty($content)){
						if(!isset($screen[$area])){
							$screen[$area] = $content;
						}
						else{
							$screen[$area] = array_merge($screen[$area], $content);
						}
					}
				}
			}
		}
		return $screen;
	}
}

?>