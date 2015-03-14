angular.module('tnApp.screen')
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
}]);