angular.module('tnApp.user')
.directive('tnUserAdmin', ['Theme', function(Theme){
	return {
		restrict: 'E',
		controller: 'UserListController',
		templateUrl: 'tnApp/modules/tn-user/tn-user-admin/tn-user-admin.tpl.html'
	};
}]);