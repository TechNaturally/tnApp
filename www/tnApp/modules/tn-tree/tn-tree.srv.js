angular.module('tnApp.tree')
.factory('Tree', function(){
	return {
		arrayToTree: function(input){
			var tree = {};
			var branch = tree;
			angular.forEach(input, function(value){
				if(angular.isDefined(value.path)){
					var trail = value.path.split('/');
					var crumb = '';
					angular.forEach(trail, function(step, index){
						if(step){
							var last = (index==trail.length-1);
							var title = last?value.title:step;
							crumb += '/'+step;
							if(branch.child === undefined){
							    branch.child = {};
							}
							if(branch.child[crumb] === undefined){
							    branch.child[crumb] = {
							        'title': title
							    };
							}
							else if(last){
							    var tmp = { 'title': title };
							    tmp.child = branch.child[crumb].child;
							    branch.child[crumb] = tmp;
							}
							branch = branch.child[crumb];
						}
					});
					branch = tree;
				}

			});

			return tree;
		}

	};
});