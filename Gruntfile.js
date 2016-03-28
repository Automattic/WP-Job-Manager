/* jshint node:true */
module.exports = function( grunt ){
	'use strict';

	grunt.initConfig({
		// setting folder templates
		dirs: {
			css: 'assets/css',
			fonts: 'assets/font',
			images: 'assets/images',
			js: 'assets/js'
		},

		// Compile all .less files.
		less: {
			compile: {
				options: {
					// These paths are searched for @imports
					paths: ['<%= dirs.css %>/']
				},
				files: [{
					expand: true,
					cwd: '<%= dirs.css %>/',
					src: [
						'*.less',
						'!icons.less',
						'!mixins.less'
					],
					dest: '<%= dirs.css %>/',
					ext: '.css'
				}]
			}
		},

		// Minify all .css files.
		cssmin: {
			minify: {
				expand: true,
				cwd: '<%= dirs.css %>/',
				src: ['*.css'],
				dest: '<%= dirs.css %>/',
				ext: '.css'
			}
		},

		// Minify .js files.
		uglify: {
			options: {
				preserveComments: 'some'
			},
			frontend: {
				files: [{
					expand: true,
					cwd: '<%= dirs.js %>',
					src: [
						'*.js',
						'!*.min.js'
					],
					dest: '<%= dirs.js %>',
					ext: '.min.js'
				}]
			},
		},

		// Watch changes for assets
		watch: {
			less: {
				files: ['<%= dirs.css %>/*.less'],
				tasks: ['less', 'cssmin'],
			},
			js: {
				files: [
					'<%= dirs.js %>/*js',
					'!<%= dirs.js %>/*.min.js',
				],
				tasks: ['uglify']
			}
		},

		shell: {
			options: {
				stdout: true,
				stderr: true
			},
			generatepot: {
				command: [
					'makepot'
				].join( '&&' )
			}
		},

		jshint: {
			options: grunt.file.readJSON('.jshintrc'),
			src: [
				'assets/js/**/*.js',
				'!assets/js/**/*.min.js',
				// External Libraries:
				'!assets/js/jquery-chosen/*.js',
				'!assets/js/jquery-deserialize/*.js',
				'!assets/js/jquery-fileupload/*.js',
				'!assets/js/jquery-tiptip/*.js'
			]
		},
	});

	// Load NPM tasks to be used here
	grunt.loadNpmTasks( 'grunt-shell' );
	grunt.loadNpmTasks( 'grunt-contrib-less' );
	grunt.loadNpmTasks( 'grunt-contrib-cssmin' );
	grunt.loadNpmTasks( 'grunt-contrib-uglify' );
	grunt.loadNpmTasks( 'grunt-contrib-watch' );
	grunt.loadNpmTasks( 'grunt-contrib-jshint' );

	// Register tasks
	grunt.registerTask( 'default', [
		'less',
		'cssmin',
		'uglify'
	]);

	// Just an alias for pot file generation
	grunt.registerTask( 'pot', [
		'shell:generatepot'
	]);

	grunt.registerTask( 'dev', [
		'default'
	]);

};
