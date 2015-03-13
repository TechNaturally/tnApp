angular.module('tnApp.status')
.directive('tnStatus', ['Theme', function(Theme){
	return {
		restrict: 'E',
		controller: 'StatusController',
		templateUrl: Theme.getTemplate
	};
}]);