angular.module('tnApp.auth')
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
			data.user = user;
			console.log('User active! '+angular.toJson(data.user, true));
		}
	}

	var api = {
		passes: function(rules){
			console.log('checking security for '+angular.toJson(rules));
			if(rules === true){
				return true;
			}
			else if(angular.isString(rules)){

			}
			else if(angular.isArray(rules)){

			}
			console.log ('failed :(');

			return false;
		},
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
				if(!res.error && res.session){
					setActiveUser(res.session);
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
					if(!res.error && res.session){
						setActiveUser(res.session);
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
					if(!res.error && res.session){
						setActiveUser(res.session);
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
}]);