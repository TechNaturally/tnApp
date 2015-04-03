angular.module('tnApp.screen')
.provider('Screen', function(){
	var data = {
		screens: {}
	};

	return {
		addScreenContent: function(screens){
			angular.forEach(screens, function(screen, path){
				if(angular.isUndefined(data.screens[path])){
					data.screens[path] = {};
				}
				angular.forEach(screen, function(content, area){
					if(angular.isUndefined(data.screens[path][area])){
						data.screens[path][area] = content;
					}
					else{
						data.screens[path][area] = data.screens[path][area].concat(content);
					}
				});
			});
		},
		$get: ['$q', 'API', 'Auth', function($q, API, Auth){

			var api = {
				load: function(path){
					var defer = $q.defer();
					/** original concept which loads screen contents from server, we do what api/screen does right down here
					API.get('/screen', {data: {path: path}}).then(function(res){
						defer.resolve(res.content);
					});
					*/

					if(!path){
						path = '/';
					}
					else if(path.charAt(0) != '/'){
						path = '/'+path;
					}
					console.log('loading screen:'+path);

					var screen = {};

					angular.forEach(data.screens, function(contents, screen_path){
						var path_rxp = screen_path;
						path_rxp = path_rxp.replace(/\*/g, '.*');

						var args_rxp = path_rxp.replace(/:([^\/]+)/g, '((:\\w[\\w\\d]*)(<(.+)>)?(\\?)?)'); // match arg names
						args_rxp = args_rxp.replace(/\//g, '\\/'); // allow slashes
						path_rxp = path_rxp.replace(/\//g, '\\/'); // allow slashes

						// replace :args<rx_pattern>? patterns with regular expression, extracting the arg name into arg_map
						var arg_map = [];
						var args_rx = new RegExp('^'+args_rxp+'$');
						var arg_match = args_rx.exec(screen_path);
						if(arg_match && arg_match.length){
							for(var i=1; i < arg_match.length; i+=5){
								var arg_name = (i+1 < arg_match.length)?arg_match[i+1]:'';
								var arg_condition = (i+2 < arg_match.length && arg_match[i+2])?arg_match[i+2]:'';
								var arg_pattern = (i+3 < arg_match.length && arg_match[i+3])?arg_match[i+3]:'[\\w\\d]+';
								var arg_optional = (i+4 < arg_match.length && arg_match[i+4]);

								arg_condition = arg_condition.replace(/\*/g, '.*', arg_condition);

								arg_map.push(arg_name);
								path_rxp = path_rxp.replace((arg_optional?'\\\/':'')+arg_name+arg_condition, '('+(arg_optional?'\\\/':'')+'('+arg_pattern+'))');
							}
						}

						// now see if the path matches
						var path_rx = new RegExp('^'+path_rxp+'$');
						var path_match = path_rx.exec(path);
						if(path_match && path_match.length){
							path_match.shift(); // remove the overall pattern match

							// map any matched arg values with their name
							var args = {};
							angular.forEach(arg_map, function(arg_name, arg_index){
								args[arg_name] = (arg_index*2+1 < path_match.length)?path_match[arg_index*2+1]:'';
							});

							//console.log(' ');
							//console.log('matched:'+screen_path);
							//console.log('with:'+JSON.stringify(path_match));
							//console.log('args:'+JSON.stringify(args));

							// TODO: do we want to block the screen?

							// add the content sorted by area
							angular.forEach(contents, function(content, area){
								if(content){
									// for each piece of content
									var screen_content = [];
									angular.forEach(content, function(content_data, content_idx){
										// skip if it is hidden on this path
										if(angular.isDefined(content_data.hide) && content_data.hide){
											var hide = false;
											angular.forEach(content_data.hide, function(hide_path){
												if(!hide){
													hide_path = hide_path.replace(/\*/g, '.*');
													hide_path = hide_path.replace(/\//g, '\\/');
													var hide_path_rx = new RegExp('^'+hide_path+'$');
													if(hide_path_rx.test(path)){
														hide = true;
													}
												}
											});
											if(hide){
												content_data = null;
											}
										}

										// add the name-mapped args
										var data_args = {};
										if(content_data && angular.isDefined(content_data.args) && content_data.args){
											angular.forEach(content_data.args, function(arg_data, data_arg){
												var arg_value = null;

												if(arg_data && angular.isString(arg_data) && arg_data.charAt(0) == ':'){
													// handle arguments from the path
													var arg_data_split = arg_data.split('?', 2);
													if(arg_data_split.length > 1){
														// if it is an optional argument
														path_arg = arg_data_split[0];
														arg_value = arg_data_split[1]; // static_default
													}
													else{
														path_arg = arg_data;
														arg_value = ''; // empty default
													}

													// if arg is in the path, copy the value
													if(path_arg && angular.isDefined(args[path_arg]) && args[path_arg]){
														arg_value = args[path_arg];
													}
												}
												else{
													// static argument value
													arg_value = arg_data;
												}

												if(arg_value == '!auth_id'){
													arg_value = (!Auth.data.user || angular.isUndefined(Auth.data.user.id))?0:Auth.data.user.id;
												}
												
												// store it for the content
												data_args[data_arg] = arg_value;
											});
											content[content_idx].args = data_args;
										}

										if(content_data){
											if(!angular.isDefined(content_data.access) || Auth.api.passes(content_data.access)){
												screen_content.push(content_data);
											}
										}
									});

									// add the content to the screen sorted into areas
									if(!angular.isDefined(screen[area])){
										screen[area] = screen_content;
									}
									else{
										screen[area] = screen[area].concat(screen_content);
									}
								}
							});
						}
					});

					defer.resolve(screen);
					return defer.promise;
				}
			};


			return {
				api: api
			};
		}]
	};
});