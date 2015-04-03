angular.module('tnApp.auth')
.config(['ScreenProvider', function(ScreenProvider){
	ScreenProvider.addScreenContent({
		"*": {
			"nav": [
				{ "type": "link",
					"path": "/admin/user",
					"title": "User Admin",
					"access": ["admin"]
				}
			]

		},
		"/user*": {
			"nav": [
				{ "type": "link",
					"path": "/user",
					"title": "View Profile",
					"access": ["user"]
				},
				{ "type": "link",
					"path": "/user/edit",
					"title": "Edit Profile",
					"access": ["user"]
				}
			],
			"page": [
				{	"type": "error",
					"content": "Access denied.",
					"access": ["^user"],
					"hide": ["/user/(login|register|recover)"]
				}
			]
		},
		"/user/:user_id<\\d+>?/:action<view|edit>?": {
			"page": [
				{ "type": "widget",
					"content": "tn-user",
					"args": {"id": ":user_id?!auth_id", "state": ":action?view"},
					"access": ["user"]
				}
			]
		},
		"/admin": {
			"page": [
				{ "type": "widget",
					"content": "tn-user-admin",
					"access": ["admin"]
				}
			]
		},
		"/admin/user*": {
			"tabs": [
				{ "type": "widget",
					"content": "tn-tabs",
					"args": {"actions": {"/admin/user": "Manage's Users", "/admin/user/add": "Add User"}},
					"access": ["admin"]
				}
			],
			"page": [
				{ "type": "widget",
					"content": "tn-user-list",
					"access": ["admin"]
				}
			]
		},
		"/admin/user/:user_id<\\d+>": {
			"page": [
				{ "type": "widget",
					"content": "tn-user",
					"args": {"id": ":user_id"},
					"access": ["admin"]
				}
			]
		}
	});
}]);