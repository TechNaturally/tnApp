angular.module('tnApp.user')
.directive('tnUserList', ['Theme', function(Theme){
	return {
		restrict: 'E',
		controller: 'UserListController',
		templateUrl: Theme.getTemplate
	};
}]);