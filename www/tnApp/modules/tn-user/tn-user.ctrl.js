angular.module('tnApp.user')
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
}]);