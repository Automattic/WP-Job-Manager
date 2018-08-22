/* eslint-disable */

module.exports = function( grunt ) {
	'use strict';

	grunt.initConfig({
		// setting folder templates
		dirs: {
			css: 'assets/css',
			fonts: 'assets/font',
			images: 'assets/images',
			js: 'assets/js',
			blocks: 'assets/blocks',
			build: 'tmp/build',
			svn: 'tmp/release-svn'
		},

		shell: {
			buildMixtape: {
				command: 'node_modules/.bin/mixtape build'
			},
			webpack: {
				command: 'npm run build'
			},
			webpackDev: {
				command: 'npm run dev'
			},
			testJS: {
				command: 'npm run test'
			},
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

		copy: {
			main: {
				src: [
					'**',
					'!assets/js/**/*.js', 'assets/js/**/*.min.js', 'assets/js/jquery-fileupload/*.js', 'assets/js/jquery-deserialize/*.js',
					'!assets/css/*.less',
					'!*.log', // Log Files
					'!assets/blocks/**', // Block source files
					'!node_modules/**', '!Gruntfile.js', '!package.json', '!jest.config.json',
					'!package-lock.json', '!webpack.config.js', // JS build/package files
					'!.git/**', '!.github/**', // Git / Github
					'!tests/**', '!bin/**', '!phpunit.xml', '!phpunit.xml.dist', // Unit Tests
					'!vendor/**', '!composer.lock', '!composer.phar', '!composer.json', // Composer
					'!.*', '!**/*~', '!tmp/**', //hidden/tmp files
					'!*.code-workspace', // IDE files
					'!docs/**',
					'!CONTRIBUTING.md',
					'!readme.md',
					'!phpcs.xml.dist',
					'!tools/**',
					'!mixtape.json'
				],
				dest: '<%= dirs.build %>/'
			}
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

		// Generate POT files.
		makepot: {
			options: {
				type: 'wp-plugin',
				domainPath: '/languages',
				potHeaders: {
					'report-msgid-bugs-to': 'https://github.com/Automattic/WP-Job-Manager/issues',
					'language-team': 'LANGUAGE <EMAIL@ADDRESS>'
				}
			},
			dist: {
				options: {
					potFilename: 'wp-job-manager.pot',
					exclude: [
						'apigen/.*',
						'tests/.*',
						'tmp/.*',
						'vendor/.*',
						'node_modules/.*'
					]
				}
			}
		},

		// Check textdomain errors.
		checktextdomain: {
			options:{
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
					'_nx_noop:1,2,3c,4d'
				]
			},
			files: {
				src:  [
					'**/*.php',         // Include all files
					'!apigen/**',       // Exclude apigen/
					'!node_modules/**', // Exclude node_modules/
					'!tests/**',        // Exclude tests/
					'!vendor/**',       // Exclude vendor/
					'!tmp/**'           // Exclude tmp/
				],
				expand: true
			}
		},

		addtextdomain: {
			wpjobmanager: {
				options: {
					textdomain: 'wp-job-manager'
				},
				files: {
					src: [
						'*.php',
						'**/*.php',
						'!node_modules/**'
					]
				}
			}
		},

		wp_deploy: {
			deploy: {
				options: {
					plugin_slug: 'wp-job-manager',
					build_dir: '<%= dirs.build %>',
					tmp_dir: '<%= dirs.svn %>/',
					max_buffer: 1024 * 1024
				}
			}
		},

		zip: {
			'main': {
				cwd: '<%= dirs.build %>/',
				src: [ '<%= dirs.build %>/**' ],
				dest: 'tmp/wp-job-manager.zip',
				compression: 'DEFLATE'
			}
		},

		phpunit: {
			main: {
				dir: ''
			},
			options: {
				bin: 'vendor/bin/phpunit',
				colors: true
			}
		},

		clean: {
			main: [ 'tmp/*.zip', 'lib/wpjm_rest', '<%= dirs.build %>' ], //Clean up build folder
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

		checkrepo: {
			deploy: {
				tagged: true,
				clean: true
			}
		},

		wp_readme_to_markdown: {
			readme: {
				files: {
					'readme.md': 'readme.txt'
				}
			}
		}
	});

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
	grunt.loadNpmTasks( 'grunt-checkrepo' );
	grunt.loadNpmTasks( 'grunt-wp-i18n' );
	grunt.loadNpmTasks( 'grunt-wp-readme-to-markdown');
	grunt.loadNpmTasks( 'grunt-zip' );

	grunt.registerTask( 'check-mixtape', 'Checking for WPJM\'s REST library (Mixtape) and building if necessary', function() {
		if ( ! grunt.file.exists( 'lib/wpjm_rest/class-wp-job-manager-rest-bootstrap.php' ) ) {
			grunt.task.run( [ 'build-mixtape' ] );
		}
	});

	grunt.registerTask( 'check-mixtape-fatal', 'Checking for WPJM\'s REST library (Mixtape)', function() {
		if ( ! grunt.file.exists( 'lib/wpjm_rest/class-wp-job-manager-rest-bootstrap.php' ) ) {
			grunt.fail.fatal( 'Unable to build WPJM\'s REST library (Mixtape).' );
		}
	});

	grunt.registerTask( 'build-mixtape', [ 'shell:buildMixtape' ] );

	grunt.registerTask( 'build-blocks', [ 'shell:webpack' ] );
	grunt.registerTask( 'build-blocks:dev', [ 'shell:webpackDev' ] );

	grunt.registerTask( 'build', [ 'gitinfo', 'clean', 'check-mixtape', 'check-mixtape-fatal', 'test', 'build-blocks', 'copy' ] );
	grunt.registerTask( 'build-unsafe', [ 'clean', 'check-mixtape', 'check-mixtape-fatal', 'build-blocks', 'copy' ] );

	grunt.registerTask( 'deploy', [ 'checkbranch:master', 'checkrepo', 'build', 'wp_deploy' ] );
	grunt.registerTask( 'deploy-unsafe', [ 'build', 'wp_deploy' ] );

	grunt.registerTask( 'package', [ 'build', 'zip' ] );
	grunt.registerTask( 'package-unsafe', [ 'build-unsafe', 'zip' ] );

	// Register tasks
	grunt.registerTask( 'default', [
		'less',
		'cssmin',
		'uglify',
		'wp_readme_to_markdown'
	] );

	// Just an alias for pot file generation
	grunt.registerTask( 'pot', [
		'makepot'
	] );

	grunt.registerTask( 'test', [
		'shell:testJS',
		'phpunit'
	] );

	grunt.registerTask( 'dev', [
		'test',
		'default'
	] );
};
