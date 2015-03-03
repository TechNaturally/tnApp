angular.module('tnApp.screen', ['tnApp.api', 'tnApp.theme'])
.factory('Screen', ['$q', 'API', function($q, API){
	
	var api = {
		load: function(path){
			var defer = $q.defer();
			API.get('/screen', {data: {path: path}}).then(function(res){
				defer.resolve(res.content);
			});
			return defer.promise;
		}
	}

	return {
		api: api
	};
}])
.controller('ScreenController', ['$scope', 'Screen', function($scope, Screen){
	// TODO: hit API.get('/screen', {path: $scope.path})
	// - load & compile content (as directives)
	console.log('loading screen: '+$scope.path+' ...');

	Screen.api.load($scope.path).then(function(content){
		$scope.content = content;
	});

}])
.directive('tnScreen', ['Theme', function(Theme){
	return {
		restrict: 'E',
		scope: {'path': '@'},
		controller: 'ScreenController',
		templateUrl: Theme.getTemplate,
		link: function(scope, elem, attr){
			scope.$watch('content', function(content){
				if(!content){
					return;
				}
				console.log(scope.path+' content: '+JSON.stringify(content));
			});
			

		}
	};
}]);