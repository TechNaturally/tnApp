angular.module('tnApp.user')
.controller('UserListController', ['$scope', 'User', function($scope, User){
	$scope.user = User.data;
	User.api.listUsers();
}]);