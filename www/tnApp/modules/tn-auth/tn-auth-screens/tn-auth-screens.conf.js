angular.module('tnApp.auth')
.config(['ScreenProvider', function(ScreenProvider){
	ScreenProvider.addScreenContent({
		"*": {
			"nav": [
				{ "type": "link",
					"path": "/admin",
					"title": "Admin",
					"access": ["admin"]
				}
			],
			"auth": [
				{ "type": "widget",
					"content": "tn-auth",
					"hide": ["/user/(login|register|recover)"]
				}
			]
		},
		"/admin*": {
			"page": [
				{	"type": "error",
					"content": "Access denied.",
					"access": ["^admin"]
				}
			]
		},
		"/user/:action<login|register|recover>": {
			"page": [
				{ "type": "widget",
					"content": "tn-auth",
					"args": {"state": ":action"}
				}
			]
		}
	});
}]);