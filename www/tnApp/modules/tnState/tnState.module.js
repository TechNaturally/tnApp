angular.module('tnApp.state', [])
.directive('tnState', function(){
	return {
		restrict: 'A',
		scope: true,
		controller: ['$scope', '$element', '$attrs', function($scope, $element, $attrs){
			if(angular.isDefined($attrs['tnState']) && $attrs['tnState']){
				$scope.state = $attrs['tnState'];
			}
			$scope.default_state = $scope.state;
			$scope.go = function(state){
				if(angular.isUndefined(state)){
					state = $scope.default_state;
				}
				$scope.state = state;
			};
		}]
	};
});