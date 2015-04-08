angular.module('tnApp.user')
.directive('tnUser', ['Theme', function(Theme){
	return {
		restrict: 'E',
		scope: {'user_id': '@id', 'state': '@'},
		controller: 'UserController',
		templateUrl: Theme.getTemplate
	};
}]);