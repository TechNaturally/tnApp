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

		foreach($contents as $priority => $content){
			if(empty($content)){ continue; }
			if(!isset($this->screens[$path][$priority])){
				$this->screens[$path][$priority] = $content;
			}
			else{
				$this->screens[$path][$priority] = array_merge($this->screens[$path][$priority], $content);
			}
		}
	}

	public function get_screen($path){
		$screen = array();
		foreach($this->screens as $screen_path => $contents){
			$path_rx = $screen_path;

			$path_rx = preg_replace('/\*/', '.*', $path_rx);

			$args_rx = preg_replace('/:([^\/]+)/', ':(\w[\w\d]*\??)', $path_rx);
			$args_rx = preg_replace('/\//', '\\/', $args_rx);

			$path_rx = preg_replace('/\/:([^\/]+)\?/', '(/[\w\d]*)?', $path_rx);
			$path_rx = preg_replace('/:([^\/]+)/', '(\w[\w\d]*)', $path_rx);

			$path_rx = preg_replace('/\//', '\\/', $path_rx);
			
			if(preg_match("/^$path_rx$/", $path, $matches)){
				array_shift($matches);

				$args = array();
				$arg_matches = array();
				preg_match("/^$args_rx$/", $screen_path, $arg_matches);
				array_shift($arg_matches);
				if(!empty($arg_matches)){
					foreach($arg_matches as $arg_idx => $arg_id){
						if(substr($arg_id,-1)=='?'){
							$arg_id = substr($arg_id, 0, -1);
						}
						if($arg_idx < count($matches)){
							if(substr($matches[$arg_idx], 0, 1) == '/'){
								$matches[$arg_idx] = substr($matches[$arg_idx], 1);
							}
							$args[$arg_id] = $matches[$arg_idx];
						}
						else{
							$args[$arg_id] = '';
						}
					}
				}

				foreach($contents as $priority => $content){
					if(empty($content)){ continue; }
					foreach($content as $content_idx => $content_data){
						$data_args = array();
						if(!empty($content_data->args)){
							foreach($content_data->args as $path_arg => $data_arg){
								$data_args[$data_arg] = (isset($args[$path_arg]) && !empty($args[$path_arg]))?$args[$path_arg]:'';
							}
							$content[$content_idx]->args = $data_args;
						}
						if(!empty($content_data->access) && !$this->app->security->passes($content_data->access, $data_args)){
							$content[$content_idx] = NULL;
							continue;
						}
					}

					$content = array_filter($content);
					if(!empty($content)){
						if(!isset($screen[$priority])){
							$screen[$priority] = $content;
						}
						else{
							$screen[$priority] = array_merge($screen[$priority], $content);
						}
					}
				}
			}
		}

		return $screen;
	}
}

class Screen {
	
}

?>