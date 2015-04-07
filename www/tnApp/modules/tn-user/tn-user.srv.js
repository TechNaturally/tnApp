angular.module('tnApp.user')
.factory('User', ['$q', 'API', '$crypthmac', function($q, API, $crypthmac){
	var data = {
		list: {},
		schema: null
	};

	function hash_password(password, username){
		return $crypthmac.encrypt(password, username);
	}

	var api = {
		loadSchema: function(){
			var defer = $q.defer();
			if(data.schema){
				defer.resolve(data.schema);
			}
			else{
				API.get('/schema/user').then(function(res){
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
		listUsers: function(){
			var defer = $q.defer();
			if(data.list && Object.count(data.list) > 1){
				defer.resolve(data.list);
			}
			else{
				API.get('/user', {silent: true}).then(function(res){
					if(!res.error && angular.isDefined(res.users)){
						angular.forEach(res.users, function(user, id){
							if(!data.list[id]){
								data.list[id] = user;
								data.list[id].loaded = false;
							}
						});
						defer.resolve(data.list);
					}
					else{
						defer.reject(res.msg);
					}
				}, function(reason){ defer.reject(reason); });
			}

			return defer.promise;
		},
		loadUser: function(id){
			var defer = $q.defer();
			if(data.list && data.list[id] && data.list[id].loaded){
				defer.resolve(data.list[id]);
			}
			else{
				API.get('/user/'+id).then(function(res){
					if(!res.error && angular.isDefined(res.user)){
						data.list[id] = res.user;
						data.list[id].loaded = true;
						defer.resolve(data.list[id]);
					}
					else{
						defer.reject(res.msg);
					}
				}, function(reason){ defer.reject(reason); });
			}

			return defer.promise;
		},
		saveUser: function(user){
			var defer = $q.defer();

			if(user.id){
				user = angular.copy(user);
				if(angular.isDefined(user.loaded)){
					delete user.loaded;
				}
				if(angular.isDefined(user.auth)){
					if(user.auth.password){
						user.auth.password = hash_password(user.auth.password, user.auth.username);
					}
					if(user.auth.new_password){
						user.auth.new_password = hash_password(user.auth.new_password, user.auth.username);
					}
					if(user.auth.new_password_confirm){
						// validation has confirmed they are the same, so don't pass the new password confirmation
						delete user.auth.new_password_confirm;
					}
					console.log('Saving with auth:'+angular.toJson(user.auth,true));
				}
				API.put('/user/'+user.id, {data: {user: user}}).then(function(res){
					if(res.user){
						angular.forEach(res.user, function(value, key){
							data.list[user.id][key] = value;
						});
					}
					defer.resolve('User saved.');

				}, function(reason){ defer.reject(reason); });				
			}
			else{
				// TODO: PUSH to /user
				defer.reject('No user id.');
			}

			return defer.promise;
		}
	};

	return {
		data: data,
		api: api
	};
}]);