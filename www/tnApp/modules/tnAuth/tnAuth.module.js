angular.module('tnApp.auth', ['tnApp.api', 'tnApp.theme', 'tnApp.status', 'tnApp.state', 'tnApp.user', 'tnApp.form', 'angular-hmac-sha512'])
.factory('Auth', ['$q', 'API', '$crypthmac', '$window', '$location', 'User', function($q, API, $crypthmac, $window, $location, User){
	var data = {
		user: null,
		schema: null
	};

	function hash_password(password, username){
		return $crypthmac.encrypt(password, username);
	}

	function setActiveUser(user){
		if(!user){
			data.user = null;
		}
		else if (user && user.id){
			// TOOD: what if user already is loaded... (best way)
			// TODO: we also need to track in Auth.data the auth_id, username, etc
			User.api.loadUser(user.id).then(function(user){
				data.user = user;
			});
		}
	}

	var api = {
		loadSchema: function(){
			var defer = $q.defer();
			if(data.schema){
				defer.resolve(data.schema);
			}
			else{
				API.get('/schema/auth').then(function(res){
					if(!res.error && angular.isDefined(res.schema)){
						data.schema = res.schema;
						defer.resolve(data.schema);
					}
					else{
						defer.reject(res.msg);
					}
				}, function(reason){ defer.reject(reason); });
			}
			return defer.promise;
		},
		ping: function(){
			var defer = $q.defer();
			API.post('/auth/ping').then(function(res){
				if(!res.error && res.user){
					setActiveUser(res.user);
					defer.resolve(true);
				}
				else{
					defer.resolve(false);
				}
			}, function(reason){ defer.reject(reason); });
			return defer.promise;
		},
		login: function(username, password){
			var defer = $q.defer();
			if(username && password){
				var req = {
					username: username,
					password: hash_password(password, username)
				};
				API.post('/auth/login', {data: req}).then(function(res){
					if(!res.error && res.user){
						setActiveUser(res.user);
						var search = $location.search();
						var from = search.from?search.from:'';
						$location.path(from);
						$location.search('from', null);
						defer.resolve(true);
					}
					else{
						defer.reject(res.msg);
					}
				}, function(reason){ defer.reject(reason); });
			}
			else{
				// TODO: set a Status?
				defer.reject('No username or password.');
			}
			return defer.promise;
		},
		logout: function(){
			var defer = $q.defer();
			API.post('/auth/logout').then(function(res){
				if(!res.error){
					$window.location.href = '';
					defer.resolve(true);
				}
				else{
					defer.reject(res.msg);
				}
			}, function(reason){ defer.reject(reason); });
			return defer.promise;
		},
		recoverPassword: function(username){
			console.log('recover password for '+username+'...');
			if(username){
				
				return true;
			}
			return false;
		},
		register: function(username, password, password_confirm){
			var defer = $q.defer();
			if(username && password && password_confirm == password){
				var req = {
					username: username,
					password: hash_password(password, username),
					start_session: true
				};
				API.post('/auth/register', {data: req}).then(function(res){
					if(!res.error && res.user){
						setActiveUser(res.user);
						defer.resolve(true);
					}
					else{
						defer.reject(res.msg);
					}
				}, function(reason){ defer.reject(reason); });
			}
			else{
				// TODO: set a Status?
				defer.reject('Bad input!');
			}
			return defer.promise;
		}
	};

	return {
		data: data,
		api: api
	};
}])
.controller('AuthController', ['$scope', 'Auth', 'API', function($scope, Auth, API){
	Auth.api.loadSchema().then(function(schema){
		$scope.schema = schema;
	});
	$scope.input = {};

	$scope.auth = Auth.data;
	$scope.path = $scope.$parent.path;

	// actions
	$scope.login = function(input){
		return Auth.api.login(input.username, input.password);
	};
	$scope.logout = function(){
		return Auth.api.logout();
	};
	$scope.register = function(input){
		return Auth.api.register(input.username, input.password, input.password_confirm);
	};
	$scope.recover = function(input){
		return Auth.api.recoverPassword(input.username);
	};
}])
.directive('tnAuth', ['Theme', function(Theme){
	return {
		restrict: 'E',
		scope: { state: '@' },
		controller: 'AuthController',
		templateUrl: Theme.getTemplate
	};
}])
.directive('tnNewUsername', ['$q', 'API', function($q, API){
	return {
		restrict: 'A',
		require: 'ngModel',
		link: function(scope, attr, element, ngModel) {
			var error = null;
			scope.customError = function(){
				return scope.schemaError() || error;
			};

			ngModel.$asyncValidators.unique = function(modelValue, viewValue){
				var defer = $q.defer();
				var value = modelValue || viewValue;

				API.get('/auth/available', {data: {username: value}, silent: true}).then(function(res){
					if(res.available){
						delete error;
						defer.resolve(true);
					}
					else{
						if(angular.isDefined(res.is_email) && res.is_email){
							error = { code: 'uniqueEmail', message: 'Email address is already in use.' };
						}
						else{
							error = { code: 'uniqueUsername', message: 'Username is already in use.' };
						}
						if(res.msg){
							error.message = res.msg;
						}
						defer.reject(res.msg);
					}
				});

				return defer.promise;
			};
		}
	};
}])
.directive('tnPasswordConfirm', function(){
	return {
		restrict: 'A',
		require: 'ngModel',
		link: function(scope, attr, element, ngModel) {
			scope.$watch(element.tnPasswordConfirm, function(value){
				scope.tnPasswordConfirm = value;
				ngModel.$validate();
			});

			var error;
			scope.customError = function(){
				return scope.schemaError() || error;
			};
			ngModel.$validators.match = function(modelValue, viewValue){
				var value = modelValue || viewValue;
				if(value != scope.tnPasswordConfirm){
					error = { code: 'match', message: 'Passwords do not match.' };
					return false;
				}
				delete error;
				return true;
			};
		}
	};
})
.config(['schemaFormDecoratorsProvider', function(schemaFormDecoratorsProvider){
	schemaFormDecoratorsProvider.addMapping(
		'bootstrapDecorator',
		'new-username',
		'tn-new-username.html'
	);

	schemaFormDecoratorsProvider.addMapping(
		'bootstrapDecorator',
		'password-confirm',
		'tn-password-confirm.html'
	);
}])
.run(['$templateCache', function($templateCache){
	// Get and modify default templates
	var tmpl = $templateCache.get('directives/decorators/bootstrap/default.html');

	$templateCache.put(
		'tn-new-username.html',
		tmpl.replace('type="{{form.type}}"', 'type="text" tn-new-username').replace(/schemaError/g, 'customError')
	);

	$templateCache.put(
		'tn-password-confirm.html',
		tmpl.replace('type="{{form.type}}"', 'type="password" tn-password-confirm="{{form.condition}}"').replace(/schemaError/g, 'customError')
	);
}]);
