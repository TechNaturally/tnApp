module.exports = function(grunt) {
	grunt.initConfig({
    	pkg: grunt.file.readJSON('package.json'),

    	includeSource: {
    		options: {
    		},
    		tnApp: {
    			files: {
					'index.html': 'index-src.html'
				}
    		}
    	},

    	wiredep: {
    		bower: {
    			src: [
    			"index.html"
    			]
    		}
    	}
	});

	grunt.loadNpmTasks('grunt-include-source');
	grunt.loadNpmTasks('grunt-wiredep');

	grunt.registerTask('default', ['includeSource', 'wiredep']);
};