angular.module('tnApp.screen', ['tnApp.api', 'tnApp.theme'])
.factory('Screen', function(){
	return {};
})
.controller('ScreenController', ['$scope', function($scope){
	// TODO: hit API.get('/screen', {path: $scope.path})
	// - load & compile content (as directives)
	console.log('loading screen: '+$scope.path+' ...');

}])
.directive('tnScreen', ['Theme', function(Theme){
	return {
		restrict: 'E',
		scope: {'path': '@'},
		controller: 'ScreenController',
		templateUrl: Theme.getTemplate
	};
}]);