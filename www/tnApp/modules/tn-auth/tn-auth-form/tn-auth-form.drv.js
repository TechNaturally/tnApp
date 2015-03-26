angular.module('tnApp.auth')
.directive('tnNewUsername', ['$q', 'API', function($q, API){
	return {
		restrict: 'A',
		require: 'ngModel',
		link: function(scope, attr, element, ngModel) {
			var error = null;
			scope.customError = function(){
				return scope.schemaError() || error;
			};

			ngModel.$asyncValidators.unique = function(modelValue, viewValue){
				var defer = $q.defer();
				var value = modelValue || viewValue;

				API.get('/auth/available', {data: {username: value}, silent: true}).then(function(res){
					if(res.available){
						error = null;
						defer.resolve(true);
					}
					else{
						if(angular.isDefined(res.is_email) && res.is_email){
							error = { code: 'uniqueEmail', message: 'Email address is already in use.' };
						}
						else{
							error = { code: 'uniqueUsername', message: 'Username is already in use.' };
						}
						if(res.msg){
							error.message = res.msg;
						}
						defer.reject(res.msg);
					}
				});

				return defer.promise;
			};
		}
	};
}])
.directive('tnPasswordConfirm', function(){
	return {
		restrict: 'A',
		require: 'ngModel',
		link: function(scope, attr, element, ngModel) {
			// TODO: try with attr.$observe
			scope.$watch(element.tnPasswordConfirm, function(value){
				scope.tnPasswordConfirm = value;
				ngModel.$validate();
			});

			var error;
			scope.customError = function(){
				return scope.schemaError() || error;
			};
			ngModel.$validators.match = function(modelValue, viewValue){
				var value = modelValue || viewValue;
				if(value != scope.tnPasswordConfirm){
					error = { code: 'match', message: 'Passwords do not match.' };
					return false;
				}
				error = null;
				return true;
			};
		}
	};
});