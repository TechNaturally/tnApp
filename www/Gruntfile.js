module.exports = function(grunt) {
	grunt.initConfig({
		pkg: grunt.file.readJSON('package.json'),

		clean: {
			all: ["index.html", "dist", ".tmp"],
			dev: ["index.html"],
			dist: ["dist"],
			tmp: [".tmp"]
		},

		copy: {
			api: {
				expand: true,
				dest: 'dist/api',
				cwd: 'api/',
				src: '**',
				dot: true
			},
			deploy: {
				src: '.htaccess',
				dot: true,
				dest: 'dist/.htaccess'
			}
		},

		includeSource: {
			dev: {
				files: {
					'index.html': 'index-src.html'
				}
			},
			deploy: {
				options: {
					baseUrl: '../'
				},
				files: {
					'dist/index.html': 'index-src.html'
				}
			}
		},

		useminPrepare: {
			deploy: {
				src: 'dist/index.html'
			}
		},

		usemin: {
			deploy: {
				src: 'dist/index.html'
			}
		},

		wiredep: {
			dev: {
				src: "index.html"
			},
			deploy: {
				src: "dist/index.html"
			}
		}
	});

	grunt.loadNpmTasks('grunt-contrib-clean');
	grunt.loadNpmTasks('grunt-contrib-concat');
	grunt.loadNpmTasks('grunt-contrib-copy');
	grunt.loadNpmTasks('grunt-contrib-uglify');
	grunt.loadNpmTasks('grunt-include-source');
	grunt.loadNpmTasks('grunt-usemin');
	grunt.loadNpmTasks('grunt-wiredep');

	grunt.registerTask('dev', ['clean:dev', 'includeSource:dev', 'wiredep:dev']);
	grunt.registerTask('deploy', ['clean:dist', 'includeSource:deploy', 'wiredep:deploy', 'useminPrepare:deploy', 'concat', 'uglify', 'usemin:deploy', 'clean:tmp', 'copy:deploy']);

	grunt.registerTask('default', ['dev'])
};