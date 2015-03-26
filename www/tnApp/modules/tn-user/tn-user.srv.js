angular.module('tnApp.user')
.factory('User', ['$q', 'API', function($q, API){
	var data = {
		list: {},
		schema: null
	};

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
				API.put('/user/'+user.id, {data: {user: user}}).then(function(res){
					angular.forEach(user, function(value, key){
						data.list[user.id][key] = value;
					});
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