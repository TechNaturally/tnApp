angular.module('tnApp.utility')
.directive('tnTabs', ['Theme', function(Theme){
	return {
		restrict: 'E',
		scope: {'tabs': '=actions'},
		templateUrl: 'tnApp/modules/tn-utility/tn-tabs/tn-tabs.tpl.html',
		controller: function(){}
	};
}]);