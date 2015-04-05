angular.module('tnApp.form')
.directive('tnArrayId', function(){
	return {
		restrict: 'A',
		controller: function($scope){
			var formDefCache = {};
			$scope.hideArrayId = function(index){
				if (!formDefCache[index]) {
					var form = $scope.copyWithIndex(index);
					if(angular.isArray(form.items)){
						for(var i=0; i < form.items.length; i++){
							if(form.items[i].key.indexOf('id') != -1){
								form.items[i].type = 'hidden';
								form.items[i].title = '';
								form.items[i].feedback = false;
								form.items[i].htmlClass = 'hidden';
							}
						}
					}
					formDefCache[index] = form;
				}
				return formDefCache[index];
			};
		}
	};
})
.run(['$templateCache', function($templateCache){
	// hack the array template to hide id fields
	var tmpl = $templateCache.get('directives/decorators/bootstrap/array.html');
	tmpl = tmpl.replace('<div sf-array="form"', '<div sf-array="form" tn-array-id').replace('copyWithIndex($index)', 'hideArrayId($index)');
	$templateCache.put(
		'directives/decorators/bootstrap/array.html',
		tmpl
	);
}]);