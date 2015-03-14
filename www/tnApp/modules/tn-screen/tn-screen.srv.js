angular.module('tnApp.screen')
.factory('Screen', ['$q', 'API', function($q, API){

	// TOOD: implement screenProvider - inject into module configs and add screens from module's screens.json
	// TODO: imlement Screen service which does the loading part here...
	// TODO: - port PHP Screen into Screen service
	
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
}]);