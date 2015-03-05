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
	Screen.api.load($scope.path).then(function(content){
		$scope.content = content;
	});
}])
.directive('tnScreen', ['Theme', '$compile', '$injector', function(Theme, $compile, $injector){
	return {
		restrict: 'E',
		scope: {'path': '@'},
		controller: 'ScreenController',
		templateUrl: Theme.getTemplate,
		link: function(scope, elem, attr){
			scope.$watch('content', function(content){
				elem.empty();
				if(!content){
					return;
				}
				angular.forEach(content, function(contents, priority){
					angular.forEach(contents, function(data, index){
						if(data.type == 'widget'){ //} && data.content && $injector.has(data.content)){
							var args = '';
							angular.forEach(data.args, function(value, key){
								if(value){
									args += ' '+key+'="'+value+'"';
								}
							});
							console.log('ok...'+'<'+data.content+args+'>');
							var cont_elem = angular.element('<'+data.content+args+'></'+data.content+'>');
							$compile(cont_elem)(scope);
							elem.append(cont_elem);
						}
					});
				});
				
			});
			

		}
	};
}]);