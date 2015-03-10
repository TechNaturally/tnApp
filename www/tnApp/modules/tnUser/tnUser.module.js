angular.module('tnApp.user', ['tnApp.api', 'tnApp.theme', 'tnApp.utility'])
.factory('User', ['$q', 'API', function($q, API){
	var data = {
		profile: null,
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
				API.get('/user').then(function(res){
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
}])
.controller('UserController', ['$scope', 'User', function($scope, User){
	$scope.user = User.data;
	//$scope.active = $scope.user.list[$scope.user_id];

	User.api.loadSchema().then(function(schema){
		$scope.schema = schema;
	});

	if($scope.user_id){
		User.api.loadUser($scope.user_id);
	}


	// profile saving
	$scope.profile = function(input){
		return User.api.saveUser(input);
	};
}])
.controller('UserListController', ['$scope', 'User', function($scope, User){
	$scope.user = User.data;
	User.api.listUsers();

}])
.directive('tnUser', ['Theme', function(Theme){
	return {
		restrict: 'E',
		scope: {'user_id': '@id'},
		controller: 'UserController',
		templateUrl: Theme.getTemplate
	};
}])
.directive('tnUserList', ['Theme', function(Theme){
	return {
		restrict: 'E',
		controller: 'UserListController',
		templateUrl: Theme.getTemplate
	};
}])
.directive('tnUserAdmin', ['Theme', function(Theme){
	return {
		restrict: 'E',
		controller: 'UserListController',
		templateUrl: Theme.getTemplate
	};
}]);

/**
Directives:
- user profile
	- view
	- edit (form)

- users list

*/

/**
API:
User.api.list ()
	- get: '/users'
	* reply:
		- users list
			| id => {username: <username>}
		- update data.users (only set username, full profile gets loaded on demand)

User.api.add (profile)
	- push: '/users'
		| username
		| password
		| email

User.api.load (id)
	- get: '/users/:id'
	* reply:
		- user
			| username
			| password
			| email
		- update data.users[<id>]

User.api.update (id, profile)
	- put: '/users/:id'
		| username
		| password
		| email

User.api.delete (id)
	- delete: '/users/:id'

User.api.set (id, profile)
	- data.list[id] = profile

*/

/**
Data:
User.data.list
	- list of users
		| id => <profile>

*/
