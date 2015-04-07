angular.module('tnApp.auth')
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
}]);