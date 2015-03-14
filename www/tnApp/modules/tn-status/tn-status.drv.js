angular.module('tnApp.status')
.directive('tnStatus', ['Theme', function(Theme){
	return {
		restrict: 'E',
		controller: 'StatusController',
		templateUrl: 'tnApp/modules/tn-status/tn-status.tpl.html'
	};
}]);