angular.module('tnApp.state', [])
.directive('tnState', function(){
	return {
		restrict: 'A',
		scope: true,
		controller: ['$scope', '$element', '$attrs', function($scope, $element, $attrs){
			$scope.state = $scope.default_state = $attrs['tnState'];
			$scope.go = function(state){
				if(angular.isUndefined(state)){
					state = this.default_state;
				}
				$scope.state = state;
			};
		}]
	};
});