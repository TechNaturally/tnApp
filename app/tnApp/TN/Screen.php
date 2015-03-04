<?php
namespace TN;

class ScreenManager {
	protected $screens;

	public function __construct(){
		$this->screens = array();
	}

	public function add_screen($path, $contents){
		if(!isset($this->screens[$path])){
			$this->screens[$path] = array();
		}

		foreach($contents as $priority => $content){
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
		print "\n";
		foreach($this->screens as $screen_path => $content){
			$path_rx = $screen_path;
			
			$path_rx = preg_replace('/\*/', '.*', $path_rx);

			$args_rx = preg_replace('/:([^\/]+)/', ':(\w[\w\d]*\??)', $path_rx);

			$args_rx = preg_replace('/\//', '\\/', $args_rx);

			$path_rx = preg_replace('/\/:([^\/]+)\?/', '(/[\w\d]*)?', $path_rx);

			$path_rx = preg_replace('/\//', '\\/', $path_rx);
			$path_rx = preg_replace('/:([^\/]+)/', '(\w[\w\d]*)', $path_rx);


			if(preg_match("/^$path_rx$/", $path, $matches)){
				array_shift($matches);

				$screen[$screen_path] = array_merge($screen, $content);

				$args = array();
				$arg_matches = array();
				preg_match("/^$args_rx$/", $screen_path, $arg_matches);
				array_shift($arg_matches);
				if(!empty($arg_matches)){
					//print "arg_matches:".print_r($arg_matches,true);
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
			}
		}

		return $screen;
	}
}

class Screen {
	
}

?>