angular.module('tnApp.theme')
.factory('Theme', ['$q', 'API', function($q, API){
	// config with theme paths
	var moduleBase = '/tnApp/modules/';
	var themeBase = '/theme/templates/';
	var registry = null;

	return {
		register: function(){
			var defer =  $q.defer();
			if(registry){
				defer.resolve(true);
			}
			else{
				API.get('/theme/registry').then(function(res){
					if(res.registry){
						registry = res.registry;
						console.log('theme registry:'+angular.toJson(registry, true));
					}
					if(res.base_path){
						themeBase = res.base_path;
						if(themeBase.substr(-1) != '/'){
							themeBase += '/';
						}
					}
					defer.resolve(registry?true:false);
				}, function(reason){ defer.reject(reason); });
			}
			return defer.promise;
		},

		getTemplate: function(elem, attr, module){
			var type = null;
			if(elem.length){
				type = elem[0].tagName.toLowerCase();
			}
			if(type){
				var template = type+'.tpl.html';
				if(angular.isUndefined(module)){
					var typeSplit = type.split('-');
					module = typeSplit.slice(0, 2).join('-');
				}
				if(registry){
					if(registry.indexOf(template) !== -1){
						return themeBase+template;
					}
					else if(registry.indexOf(module+'/'+template) !== -1){
						return themeBase+module+'/'+template;
					}
					else if(type!=module && registry.indexOf(module+'/'+type+'/'+template) !== -1){
						return themeBase+module+'/'+type+'/'+template;
					}
				}
				return moduleBase+module+'/'+((type!=module)?type+'/':'')+template;
			}
			return '';
		}
	};
}]);