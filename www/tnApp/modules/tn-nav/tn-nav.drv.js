angular.module('tnApp.nav')
.directive('tnNav', ['Theme', function(Theme){
	return {
		restrict: 'E',
		scope: {'tree': '=', 'active': '@'},
		templateUrl: Theme.getTemplate
	};
}]);