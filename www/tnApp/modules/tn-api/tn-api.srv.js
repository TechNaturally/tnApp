angular.module('tnApp.api')
.factory('API', ['$http', '$q', 'md5', 'Status', function($http, $q, md5, Status){
	var requests = {};

	var api = {
		get: function(path, config){
			if(angular.isUndefined(config)){ config = null; }
			return api.request('GET', path, config);
		},
		post: function(path, config){
			if(angular.isUndefined(config)){ config = null; }
			return api.request('POST', path, config);
		},
		put: function(path, config){
			if(angular.isUndefined(config)){ config = null; }
			return api.request('PUT', path, config);
		},
		request: function(method, path, config){
			if(!config){ config = {}; }

			var data = angular.isDefined(config.data)?config.data:null;
			var silent = angular.isDefined(config.silent)?config.silent:null;
			var single = angular.isDefined(config.single)?config.single:true;

			var data_md5 = md5.createHash(angular.toJson(data, false));
			var req_key = path+'/'+data_md5;

			if(single && angular.isDefined(requests[req_key])){
				return requests[req_key];
			}

			var defer = $q.defer();

			if(single){ requests[req_key] = defer.promise; }

			var request = {
				url: '/api'+(path.charAt(0)!='/'?'/':'')+path,
				method: method
			};
			if(method == 'GET'){
				request.params = data;
			}
			else{
				request.data = data;
			}
			
			$http(request).
				success(function(res, status, headers, config) {
					if(!silent && res.msg){
						Status.push(res.msg, (res.error?'error':'success'));
					}
					defer.resolve(res);
				}).
				error(function(res, status, headers, config) {
					Status.push('API Error #'+status+' on ['+method+'] \''+path+'\''+((res && res.msg)?': '+res.msg:''), 'error');
					defer.reject(res.msg?res.msg:'');
				}).
				finally(function(){
					if(single && requests[req_key]){
						delete requests[req_key];
					}
				});
			return defer.promise;
		}
	};

	return api;
}]);