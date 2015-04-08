angular.module('tnApp.user')
.directive('tnUserAdmin', ['Theme', function(Theme){
	return {
		restrict: 'E',
		controller: 'UserListController',
		templateUrl: Theme.getTemplate
	};
}]);