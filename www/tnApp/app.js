angular.module('tnApp', ['ngRoute', 'tnApp.screen', 'tnApp.auth'])
.controller('AppController', ['$scope', '$routeParams', 'Auth', function ($scope, $routeParams, Auth){
  $scope.auth = Auth.data;

  $scope.path = $routeParams.path;

}])
.config(['$routeProvider', '$locationProvider',
  function ($routeProvider, $locationProvider) {
  	$routeProvider
    .when('/:path*?', {
      title: 'App Screen Handler',
      templateUrl: '/tnApp/app.html',
      controller: 'AppController',
      resolve: {
        authenticated: function(Auth){
          return Auth.api.ping();
        },
        hasTheme: function(Theme){
          return Theme.register();
        }
      }
    })
  	.otherwise({
  		redirectTo: '/'
  	});

    $locationProvider.html5Mode(true);
}]);
