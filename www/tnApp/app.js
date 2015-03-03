angular.module('tnApp', ['ngRoute', 'tnApp.screen', 'tnApp.auth'])
.controller('AppController', ['$scope', '$routeParams', 'Auth', function ($scope, $routeParams, Auth){
  $scope.auth = Auth.data;

  $scope.path = $routeParams.path;

  $scope.what = 'ok';

}])
.config(['$routeProvider', 
  function ($routeProvider) {
  	$routeProvider
    .when('/:path*?', {
      title: 'App Screen Handler',
      templateUrl: '/tnApp/app.html',
      controller: 'AppController',
      resolve: {
        hasTheme: function(Theme){
          return Theme.register();
        }
      }
    })
  	.otherwise({
  		redirectTo: '/'
  	});
  }])
.run(['$rootScope', 'Auth', function($rootScope, Auth) {
  Auth.api.ping();
}]);
