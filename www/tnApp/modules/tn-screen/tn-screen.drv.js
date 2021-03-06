angular.module('tnApp.screen')
.directive('tnScreen', ['Theme', '$compile', '$injector', 'md5', 'Tree', function(Theme, $compile, $injector, md5, Tree){
	return {
		restrict: 'E',
		scope: {'path': '@'},
		controller: 'ScreenController',
		templateUrl: Theme.getTemplate,
		link: function(scope, elem, attr){
			//var children = angular.isDefined(elem[0].children[0].children)?elem[0].children[0].children:null;
			var children = elem.children();
			if(children){
				children = children.children();
			}
			var areas = {};
			if(children && children.length){
				for(var i = 0; i < children.length; i++){
					if(children[i].className){
						var classSplit = children[i].className.split(' ', 2);
						areas[classSplit[0]] = angular.element(children[i]);
					}
				}
			}

			scope.$watch('content', function(content){
				angular.forEach(areas, function(area, area_name){
					area.empty();
				});
				if(!content){
					return;
				}
				angular.forEach(content, function(contents, area){
					if(angular.isUndefined(areas[area])){
						areas[area] = angular.element('<div class="'+area+'"></div>');
						elem.append(areas[area]);
					}
					var area_container = areas[area];
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
					
				});
			});
		}
	};
}]);