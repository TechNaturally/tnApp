angular.module('tnApp.user')
.directive('tnUserList', ['Theme', function(Theme){
	return {
		restrict: 'E',
		controller: 'UserListController',
		templateUrl: 'tnApp/modules/tn-user/tn-user-list/tn-user-list.tpl.html'
	};
}]);