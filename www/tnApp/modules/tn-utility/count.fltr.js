angular.module('tnApp.utility')
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
});

Object.count = function(obj){
	var size = 0, key;
	for(key in obj){
		if(obj.hasOwnProperty(key)){ size++; }
	}
	return size;
};
