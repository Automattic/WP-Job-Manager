/* eslint-disable */

module.exports = function( grunt ) {
	'use strict';

	grunt.initConfig( {
		// setting folder templates
		dirs: {
			css: 'assets/css',
			fonts: 'assets/font',
			images: 'assets/images',
			js: 'assets/js',
			select2: 'assets/js/select2',
			blocks: 'assets/blocks',
			build: 'build/tmp-package',
			svn: 'build/release-svn',
		},

		shell: {
			webpack: {
				command: 'npm run build',
			},
			webpackDev: {
				command: 'npm run dev',
			},
			testJS: {
				command: 'npm run test',
			},
		},

		// Compile all .less files.
		less: {
			compile: {
				options: {
					// These paths are searched for @imports
					paths: [ '<%= dirs.css %>/' ],
				},
				files: [
					{
						expand: true,
						cwd: '<%= dirs.css %>/',
						src: [ '*.less', '!icons.less', '!mixins.less' ],
						dest: '<%= dirs.css %>/',
						ext: '.css',
					},
				],
			},
		},

		// Minify all .css files.
		cssmin: {
			minify: {
				expand: true,
				cwd: '<%= dirs.css %>/',
				src: [ '*.css' ],
				dest: '<%= dirs.css %>/',
				ext: '.css',
			},
		},

		// Minify .js files.
		uglify: {
			options: {
				preserveComments: 'some',
			},
			frontend: {
				files: [
					{
						expand: true,
						cwd: '<%= dirs.js %>',
						src: [ '*.js', '!*.min.js' ],
						dest: '<%= dirs.js %>',
						ext: '.min.js',
					},
				],
			},
		},

		copy: {
			main: {
				src: [
					'**',
					'!assets/js/**/*.js',
					'assets/js/**/*.min.js',
					'assets/js/jquery-fileupload/*.js',
					'assets/js/jquery-deserialize/*.js',
					'!assets/css/*.less',
					'!*.log', // Log Files
					'!assets/blocks/**', // Block source files
					'!node_modules/**',
					'!Gruntfile.js',
					'!package.json',
					'!jest.config.json',
					'!package-lock.json',
					'!webpack.config.js', // JS build/package files
					'!.git/**',
					'!.github/**', // Git / Github
					'!tests/**',
					'!bin/**',
					'!phpunit.xml',
					'!phpunit.xml.dist', // Unit Tests
					'!vendor/**',
					'!composer.lock',
					'!composer.phar',
					'!composer.json', // Composer
					'!.*',
					'!**/*~',
					'!tmp/**', //hidden/tmp files
					'!build/**', //hidden/tmp files
					'!*.code-workspace', // IDE files
					'!docs/**',
					'!CONTRIBUTING.md',
					'!readme.md',
					'!phpcs.xml.dist',
					'!tools/**',
					'!jest.config.js',
				],
				dest: '<%= dirs.build %>/',
			},
			select2: {
				expand: true,
				flatten: true,
				src: [
					'node_modules/select2/dist/js/select2.full.min.js',
					'node_modules/select2/dist/css/select2.min.css',
				],
				dest: '<%= dirs.select2 %>/',
			},
		},

		// Watch changes for assets
		watch: {
			less: {
				files: [ '<%= dirs.css %>/*.less' ],
				tasks: [ 'less', 'cssmin' ],
			},
			js: {
				files: [ '<%= dirs.js %>/*js', '!<%= dirs.js %>/*.min.js' ],
				tasks: [ 'uglify' ],
			},
		},

		// Generate POT files.
		makepot: {
			options: {
				type: 'wp-plugin',
				domainPath: '/languages',
				potHeaders: {
					'report-msgid-bugs-to': 'https://github.com/Automattic/WP-Job-Manager/issues',
					'language-team': 'LANGUAGE <EMAIL@ADDRESS>',
				},
			},
			dist: {
				options: {
					potFilename: 'wp-job-manager.pot',
					exclude: [ 'apigen/.*', 'tests/.*', 'tmp/.*', 'build/.*', 'vendor/.*', 'node_modules/.*' ],
				},
			},
		},

		// Check textdomain errors.
		checktextdomain: {
			options: {
				text_domain: 'wp-job-manager',
				keywords: [
					'__:1,2d',
					'_e:1,2d',
					'_x:1,2c,3d',
					'esc_html__:1,2d',
					'esc_html_e:1,2d',
					'esc_html_x:1,2c,3d',
					'esc_attr__:1,2d',
					'esc_attr_e:1,2d',
					'esc_attr_x:1,2c,3d',
					'_ex:1,2c,3d',
					'_n:1,2,4d',
					'_nx:1,2,4c,5d',
					'_n_noop:1,2,3d',
					'_nx_noop:1,2,3c,4d',
				],
			},
			files: {
				src: [
					'**/*.php', // Include all files
					'!apigen/**', // Exclude apigen/
					'!node_modules/**', // Exclude node_modules/
					'!tests/**', // Exclude tests/
					'!vendor/**', // Exclude vendor/
					'!tmp/**', // Exclude tmp/
					'!build/**', // Exclude build/
				],
				expand: true,
			},
		},

		addtextdomain: {
			wpjobmanager: {
				options: {
					textdomain: 'wp-job-manager',
				},
				files: {
					src: [ '*.php', '**/*.php', '!node_modules/**' ],
				},
			},
		},

		wp_deploy: {
			deploy: {
				options: {
					plugin_slug: 'wp-job-manager',
					build_dir: '<%= dirs.build %>',
					tmp_dir: '<%= dirs.svn %>/',
					max_buffer: 1024 * 1024,
				},
			},
		},

		zip: {
			main: {
				cwd: '<%= dirs.build %>/',
				src: [ '<%= dirs.build %>/**' ],
				dest: 'build/wp-job-manager.zip',
				compression: 'DEFLATE',
			},
		},

		phpunit: {
			main: {
				dir: '',
			},
			options: {
				bin: 'vendor/bin/phpunit',
				colors: true,
			},
		},

		clean: {
			main: [ 'build/*.zip', '<%= dirs.build %>' ], //Clean up build folder
		},

		jshint: {
			options: grunt.file.readJSON( '.jshintrc' ),
			src: [
				'assets/js/**/*.js',
				'!assets/js/**/*.min.js',
				// External Libraries:
				'!assets/js/jquery-chosen/*.js',
				'!assets/js/jquery-deserialize/*.js',
				'!assets/js/jquery-fileupload/*.js',
				'!assets/js/jquery-tiptip/*.js',
			],
		},

		wp_readme_to_markdown: {
			readme: {
				files: {
					'readme.md': 'readme.txt',
				},
			},
		},
	} );

	// Load NPM tasks to be used here
	grunt.loadNpmTasks( 'grunt-contrib-less' );
	grunt.loadNpmTasks( 'grunt-contrib-cssmin' );
	grunt.loadNpmTasks( 'grunt-contrib-uglify' );
	grunt.loadNpmTasks( 'grunt-contrib-watch' );
	grunt.loadNpmTasks( 'grunt-contrib-jshint' );
	grunt.loadNpmTasks( 'grunt-checktextdomain' );
	grunt.loadNpmTasks( 'grunt-contrib-copy' );
	grunt.loadNpmTasks( 'grunt-contrib-clean' );
	grunt.loadNpmTasks( 'grunt-gitinfo' );
	grunt.loadNpmTasks( 'grunt-phpunit' );
	grunt.loadNpmTasks( 'grunt-checkbranch' );
	grunt.loadNpmTasks( 'grunt-wp-deploy' );
	grunt.loadNpmTasks( 'grunt-shell' );
	grunt.loadNpmTasks( 'grunt-wp-i18n' );
	grunt.loadNpmTasks( 'grunt-wp-readme-to-markdown' );
	grunt.loadNpmTasks( 'grunt-zip' );

	grunt.registerTask( 'update-assets', [ 'copy:select2' ] );

	grunt.registerTask( 'build-blocks', [ 'shell:webpack' ] );
	grunt.registerTask( 'build-blocks:dev', [ 'shell:webpackDev' ] );

	grunt.registerTask( 'pre-package', [ 'update-assets', 'wp_readme_to_markdown' ] );

	grunt.registerTask( 'build', [ 'less', 'cssmin', 'uglify' ] );

	// grunt.registerTask( 'build', [ 'gitinfo', 'clean', 'test', 'build-blocks', 'copy:main' ] );
	grunt.registerTask( 'build-package', [ 'gitinfo', 'clean', 'test', 'copy:main' ] );
	// grunt.registerTask( 'build-unsafe', [ 'clean', 'build-blocks', 'copy:main' ] );
	grunt.registerTask( 'build-package-unsafe', [ 'clean', 'copy:main' ] );

	grunt.registerTask( 'deploy', [ 'checkbranch:master', 'build-package', 'wp_deploy' ] );
	grunt.registerTask( 'deploy-unsafe', [ 'build-package', 'wp_deploy' ] );

	grunt.registerTask( 'package', [ 'build-package', 'zip' ] );
	grunt.registerTask( 'package-unsafe', [ 'build-package-unsafe', 'zip' ] );

	// Register tasks
	grunt.registerTask( 'default', [ 'build', 'wp_readme_to_markdown' ] );

	// Just an alias for pot file generation
	grunt.registerTask( 'pot', [ 'makepot' ] );

	grunt.registerTask( 'test', [ 'shell:testJS', 'phpunit' ] );

	grunt.registerTask( 'dev', [ 'test', 'default' ] );
};
