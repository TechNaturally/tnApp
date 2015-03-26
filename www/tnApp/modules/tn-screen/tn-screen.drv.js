angular.module('tnApp.screen')
.directive('tnScreen', ['Theme', '$compile', '$injector', 'md5', 'Tree', function(Theme, $compile, $injector, md5, Tree){
	return {
		restrict: 'E',
		scope: {'path': '@'},
		controller: 'ScreenController',
		templateUrl: 'tnApp/modules/tn-screen/tn-screen.tpl.html',
		link: function(scope, elem, attr){
			scope.$watch('content', function(content){
				elem.empty();
				if(!content){
					return;
				}
				angular.forEach(content, function(contents, area){
					var area_container = angular.element('<div class="area '+area+'"></div>');
					var area_errors;
					if(area == 'nav'){
						var navTree = Tree.arrayToTree(contents);
						var valueKey = area+'_'+md5.createHash(angular.toJson(navTree, false));
						valueKey = valueKey.replace(/-/g, '_');
						scope[valueKey] = navTree;
						if(scope.path.charAt(0) != '/'){
							scope.path = '/'+scope.path;
						}
						var cont_elem = angular.element('<tn-nav tree="'+valueKey+'" active="{{path}}"></tn-nav>');
						area_container.append(cont_elem);
					}
					else{
						angular.forEach(contents, function(data, index){
							if(data.type == 'widget'){
								var args = '';
								angular.forEach(data.args, function(value, key){
									if(value){
										if(angular.isObject(value)){
											var valueKey = data.content+'_'+area+'_'+key+'_'+md5.createHash(angular.toJson(value, false));
											valueKey = valueKey.replace(/-/g, '_');
											scope[valueKey] = value;
											args += ' '+key+'="'+valueKey+'"';
										}
										else{
											args += ' '+key+'="'+value+'"';
										}
									}
								});
								var cont_elem = angular.element('<'+data.content+args+'></'+data.content+'>');
								area_container.append(cont_elem);
							}
							else if(data.type == 'error'){
								if(!area_errors){
									area_errors = angular.element('<ul class="errors"></ul>');
									area_container.prepend(area_errors);
								}
								area_errors.append('<li>'+data.content+'</li>');
							}
						});
					}
					$compile(area_container)(scope);
					elem.append(area_container);
				});
			});
		}
	};
}]);