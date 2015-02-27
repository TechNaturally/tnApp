angular.module('tnApp', ['ngRoute', 'tnApp.status', 'tnApp.form', 'tnApp.user', 'tnApp.auth'])
.controller('AppController', ['$scope', '$log', 'Status', 'Auth', 'User', function ($scope, $log, Status, Auth, User){
  $scope.auth = Auth.data;
  $scope.user = User.data;

  // TEST
  $scope.what = 'what';
}])
.config(['$routeProvider',
  function ($routeProvider) {
  	$routeProvider.when('/', {
  		title: 'Test',
  		templateUrl: '/tnApp/views/test.html',
  		controller: 'AppController'
  	})
  	.otherwise({
  		redirectTo: '/'
  	});
  }])
.run(['$rootScope', 'Auth', function($rootScope, Auth) {
  Auth.api.ping();
}]);
