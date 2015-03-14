angular.module('tnApp.status')
.controller('StatusController', ['$scope', 'Status', function($scope, Status){
	$scope.status = Status.data;
	$scope.dismiss = Status.pop;
}]);