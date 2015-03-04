angular.module('tnApp.form', ['tnApp.api', 'tnApp.theme', 'schemaForm'])
.directive('tnForm', ['API', 'Theme', function(API, Theme){
	return {
		restrict: 'E',
		scope: {
			action: '@',
			method: '@?',
			name: '@?',
			schema: '=?',
			input: '=?',
			defaults: '=?'
		},
		templateUrl: Theme.getTemplate,
		controller: function($scope){
			// provide state-changing function 'go' (ex. using tn-state directive)
			// some buttons in the form may call this directly
			if(angular.isFunction($scope.$parent.go)){
				$scope.go = $scope.$parent.go;
			}

			// this is used as the ng-submit callback, where action is provided by the API form
			$scope.do = function(action){
				if(angular.isDefined(action) && angular.isFunction($scope.$parent[action])){
					$scope.$parent[action]($scope.input).then(function(result){
						console.log('Success with '+action+'!');
						$scope.cancel();
					},
					function(error){
						console.log('Something happened with '+action+': "'+error+'"');
					});
				}
			};

			// resets input
			$scope.reset = function(input){
				if(angular.isUndefined(input)){
					input = $scope.input;
				}

				angular.forEach(input, function(value, key){
					if(key !== '#forms'){
						input[key] = '';
					}
				});
				if($scope.defaults){
					angular.forEach($scope.defaults, function(value, key){
						input[key] = value;
					});
				}
				
				if(input['#forms']){
					angular.forEach(input['#forms'], function(form, form_name){
						if(form){
							form.$setPristine();
						}
					});
				}
			};

			// resets state (if go function exists) and input (local and on parent if it exists)
			$scope.cancel = function(){
				if(angular.isFunction($scope.go)){
					$scope.go();
				}

				$scope.reset();
				if(angular.isDefined($scope.$parent.input) && $scope.input !== $scope.$parent.input){
					$scope.reset($scope.$parent.input);
				}
			};
		},
		link: function(scope, element, attrs){
			if(!scope.action){
				return;
			}

			var action = attrs.action;
			if(action.charAt(0) == '/'){
				action = action.substr(1);
			}

			var module = action.split('/');
			module = (module.length > 0)?module[0]:null;

			// if no method provided, use a default
			if(!attrs.method){
				scope.method = 'POST';
			}

			// if no name given, generate one based on the action
			if(!attrs.name){
				scope.name = action.replace(/\//g, '-');
			}

			// form options
			scope.options = { formDefaults: { 
									ngModelOptions: {allowInvalid: true},
									validationMessage: {
										"default": "Invalid input!",
										302: "{title} is required",
										200: "Minimum length of {title} is {minLength}",
										201: "Maximum length of {title} is {maxLength}",
										202: "Invalid format for {title}",
									}
								} 
							};

			// if no schema given, load it based on the module
			if(!attrs.schema && module){
				scope.schema = {};
				API.get('/schema/'+module).then(function(res){
					if(!res.error && angular.isDefined(res.schema)){
						scope.schema = res.schema;
					}
				});
			}

			// if no input given, make a new one
			if(!scope.input){
				scope.input = {};
			}
			if(!attrs.defaults){
				scope.defaults = angular.copy(scope.input);
			}
			else{
				angular.forEach(scope.defaults, function(value, key){
					scope.input[key] = value;
				});
			}
			if(angular.isUndefined(scope.input['#forms'])){
				scope.input['#forms'] = {};
			}
			if(scope[scope.name]){
				scope.input['#forms'][scope.name] = scope[scope.name];
			}

			// load the form based on the action
			scope.form = [];
			API.request(scope.method, scope.action+'/form').then(function(res){
				if(!res.error && angular.isDefined(res.form)){
					scope.form = res.form;
				}
				if(!res.error && angular.isDefined(res.callback)){
					scope.submit = res.callback;
				}
			});
		}
	};
}]);
