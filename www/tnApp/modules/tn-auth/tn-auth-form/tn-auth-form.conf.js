angular.module('tnApp.auth')
.config(['schemaFormDecoratorsProvider', function(schemaFormDecoratorsProvider){
	schemaFormDecoratorsProvider.addMapping(
		'bootstrapDecorator',
		'new-username',
		'tn-new-username.html'
	);

	schemaFormDecoratorsProvider.addMapping(
		'bootstrapDecorator',
		'password-confirm',
		'tn-password-confirm.html'
	);
}])
.run(['$templateCache', function($templateCache){
	// Get and modify default templates
	var tmpl = $templateCache.get('directives/decorators/bootstrap/default.html');

	$templateCache.put(
		'tn-new-username.html',
		tmpl.replace('type="{{form.type}}"', 'type="text" tn-new-username').replace(/schemaError/g, 'customError')
	);

	$templateCache.put(
		'tn-password-confirm.html',
		tmpl.replace('type="{{form.type}}"', 'type="password" tn-password-confirm="{{form.condition}}"').replace(/schemaError/g, 'customError')
	);
}]);