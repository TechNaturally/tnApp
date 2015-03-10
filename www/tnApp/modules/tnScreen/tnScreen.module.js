angular.module('tnApp.screen', ['tnApp.api', 'tnApp.theme', 'angular-md5'])
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
.directive('tnScreen', ['Theme', '$compile', '$injector', 'md5', function(Theme, $compile, $injector, md5){
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
									if(angular.isObject(value)){
										var valueKey = data.content.replace(/-/g, '_')+'_'+priority+'_'+key+'_'+md5.createHash(angular.toJson(value, false));
										scope[valueKey] = value;
										args += ' '+key+'="'+valueKey+'"';
									}
									else{
										args += ' '+key+'="'+value+'"';
									}
								}
							});
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