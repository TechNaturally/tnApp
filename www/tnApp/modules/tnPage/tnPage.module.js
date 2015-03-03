angular.module('tnApp.page', ['ngRoute', 'tnApp.api', 'tnApp.theme'])
.controller('PageController', ['$scope', function($scope){
	console.log('Page controller...');
	//$scope.params = $routeParams['path'];

// we have the path, how do we handle

	// 1. parse path and search for directive $injector.has(<directive>+'Directive');

// if no "page directives" exist to fulfill the path...

	// 2. hit the API to see if it can suggest any content for the page path
		// expect an html formatted string
		// the string may contain <tnw:<widget>,<{args}>>
			// if $injector.has(widget+'Directive')
				// replace with directives
			// else
				// replace with empty space (or missing directive)
		// run through $compile

// and ya the server might run it through its own pager module, or any of its modules to add content to the path
// ('hook_content_for', path)

// ex. hook_content_for ('/admin')
	// user appends <tnw:user-admin>
	// grocery appends <tnw:grocery-admin>

// ex. hook_content_for ('/admin/user')
	// user appends <tnw:user-list,{admin:true}>

// ex. hook_content_for ('/admin/user/:id')
	// user appends <tnw:user-profile,{admin:true,user_id:$id}>

// ex. hook_content_for ('/user/:id')
	// user appends <tnw:user-profile,{user_id:$id}>


	/** server /api/page/:path => function page_content_get($tn, $path)
			foreach($tn->modules, $module_id){

			}
	*/

	// run all hook_content_for(path)

	/** path contents
			path		- the path which will append content
			contents	- the content to append
			priority	- the order the content should be appended
	*/

	// run path contents through content renderer:

	/** content renderer
			builds HTML response by compiling prioritized contents
	*/


}])
.directive('tnPage', ['Theme', function(Theme){
	return {
		restrict: 'E',
		scope: { path: '=' },
		controller: 'PageController',
		templateUrl: Theme.getTemplate,
		link: function(scope, elem, attr){
			var path = 'tn-'+scope.path.replace(/\//g, '-');
			
			console.log('linking page with:'+attr.$normalize(path)+':');
		}
	};
}]);