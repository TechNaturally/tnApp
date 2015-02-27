angular.module('tnApp.status', [])
.factory('Status', ['$http', function($http){
	var data = {
		messages: [],
		classes: {
			'status': 'info',
			'info': 'info',
			'success': 'success',
			'error': 'danger',
			'debug': 'warning'
		}
	};
	return {
		data: data,
		push: function(message, type){
			data.messages.push({
				message: message,
				type: type?type:'status'
			});
		},
		pop: function(index){
			if(index >= 0 && index < data.messages.length){
				data.messages.splice(index, 1);
			}
		}
	};
}])
.controller('StatusController', ['$scope', 'Status', function($scope, Status){
	$scope.status = Status.data;
	$scope.dismiss = Status.pop;
}])
.directive('tnStatus', function(){
	return {
		restrict: 'E',
		controller: 'StatusController',
		templateUrl: '/tnApp/modules/tnStatus/views/tn-status.html'
	};
});