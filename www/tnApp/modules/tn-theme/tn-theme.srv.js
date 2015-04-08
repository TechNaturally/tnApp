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
				if(registry && registry.indexOf(template) !== -1){
					return themeBase+template;
				}
				if(angular.isUndefined(module)){
					var typeSplit = type.split('-');
					module = typeSplit.slice(0, 2).join('-');
				}
				return moduleBase+module+'/'+((type!=module)?type+'/':'')+template;
			}
			return '';
		}
	};
}]);