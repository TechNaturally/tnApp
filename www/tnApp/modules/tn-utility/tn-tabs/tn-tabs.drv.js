angular.module('tnApp.utility')
.directive('tnTabs', ['Theme', function(Theme){
	return {
		restrict: 'E',
		scope: {'tabs': '=actions'},
		templateUrl: function(elem, attr){ return Theme.getTemplate(elem, attr, 'tn-utility'); },
		controller: function(){}
	};
}]);