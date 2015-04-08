angular.module('tnApp.auth')
.directive('tnAuth', ['Theme', function(Theme){
	return {
		restrict: 'E',
		scope: { state: '@' },
		controller: 'AuthController',
		templateUrl: Theme.getTemplate
	};
}]);