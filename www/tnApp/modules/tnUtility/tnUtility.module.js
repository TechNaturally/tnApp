angular.module('tnApp.utility', ['tnApp.theme'])
.filter('count', function(){
	return function(input){
		if(angular.isDefined(input.length)){
			return input.length;
		}
		if(angular.isObject(input)){
			return Object.count(input);
		}
		return null;
	};
})
.directive('tnTabs', ['Theme', function(Theme){
	return {
		restrict: 'E',
		scope: {'tabs': '=actions'},
		templateUrl: function(elem, attr){ return Theme.getTemplate(elem, attr, 'tnUtility'); },
		controller: function(){}
	};
}]);

Object.count = function(obj){
	var size = 0, key;
	for(key in obj){
		if(obj.hasOwnProperty(key)){ size++ };
	}
	return size;
}
