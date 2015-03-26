angular.module('tnApp.screen')
.controller('ScreenController', ['$scope', 'Screen', function($scope, Screen){
	Screen.api.load($scope.path).then(function(content){
		$scope.content = content;
	});
}]);