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
			User.api.loadUser(user.id).then(function(user){
				data.user = User.data.list[user.id];
				console.log('Loaded user:'+angular.toJson(data.user, true));
			});
			console.log('User active! '+angular.toJson(data.user, true));
		}
	}

	function userHasRole(role){
		if(data.user && data.user.roles){
			if(!data.user.loaded){
				return (data.user.roles.indexOf(role) != -1);
			}
			else{
				for(var i=0; i < data.user.roles.length; i++){
					if(data.user.roles[i].roles == role){
						return true;
					}
				}
			}
		}
		return false;
	}

	function userMatches(rule){
		if(rule === true || rule === false){
			return rule;
		}
		else if(rule == 'user' && data.user){
			// TODO: special user based security...
			return true;
		}
		else if(rule == '^user' && data.user){
			// TODO: special user based security...
			return false;
		}
		else if(rule.charAt(0) == '^'){
			return !userHasRole(rule.substr(1));
		}
		else{
			return userHasRole(rule);
		}
		return false;
	}

	var api = {
		passes: function(rules){
			if(angular.isArray(rules)){
				for(var i=0; i < rules.length; i++){
					if(userMatches(rules[i])){
						return true;
					}
				}
			}
			else{
				return userMatches(rules);
			}
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
			if(data.user){
				defer.resolve(true);
			}
			else{
				API.post('/auth/ping').then(function(res){
					if(!res.error && res.session){
						setActiveUser(res.session);
						defer.resolve(true);
					}
					else{
						defer.resolve(false);
					}
				}, function(reason){ defer.reject(reason); });
			}
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