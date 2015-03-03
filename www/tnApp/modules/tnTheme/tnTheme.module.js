angular.module('tnApp.theme', [])
.factory('Theme', ['$q', '$http', function($q, $http){
	// config with theme paths
	var themeBase = '/theme';
	var moduleBase = '/tnApp/modules';
	var registry = null;

	return {
		register: function(){
			// TODO: implement named themes "Theme.setTheme(name) + re-register"
			var defer =  $q.defer();
			if(registry){
				defer.resolve(true);
			}
			else{
				$http.get(themeBase+(name?'/'+name:'')+'/registry.json').then(function(res){
					registry = res.data;
					console.log('registry:'+JSON.stringify(registry));
				})
				.finally(function(){
					defer.resolve(registry?true:false);
				});
			}
			return defer.promise;
		},

		getTemplate: function(elem, attr){
			var type = null;
			if(elem.length){
				type = elem[0].tagName.toLowerCase();
			}

			if(type){
				//var moduleBase = ;

				var typeSplit = type.split('-');
				var module = typeSplit.slice(0, 2).join('-');
				var template = ((typeSplit.length > 2)?typeSplit.slice(2).join('-'):module)+'.html';

				if(registry && registry.indexOf(template) !== -1){
					return '/theme/views/'+template;
				}

				module = attr.$normalize(module);

				return moduleBase+'/'+module+'/views/'+template;
			}

			return '';
		}
	};
}]);