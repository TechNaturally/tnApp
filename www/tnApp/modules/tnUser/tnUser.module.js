angular.module('tnApp.user', ['tnApp.api', 'tnApp.theme'])
.factory('User', ['$q', 'API', function($q, API){
	var data = {
		profile: null,
		list: null,
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
						console.log('user schema loaded');
						data.schema = res.schema;
						defer.resolve(data.schema);
					}
					else{
						defer.reject(res.msg);
					}
				}, function(reason){ defer.reject(reason); });
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

	User.api.loadSchema().then(function(schema){
		$scope.schema = schema;
	});

	$scope.user_id = 123;

}])
.directive('tnUser', ['Theme', function(Theme){
	return {
		restrict: 'E',
		scope: true,
		controller: 'UserController',
		templateUrl: Theme.getTemplate
	};
}])
.directive('tnUserList', ['Theme', function(Theme){
	return {
		restrict: 'E',
		controller: 'UserController',
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
