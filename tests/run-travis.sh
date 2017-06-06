#!/bin/bash

if [ "$WP_TRAVISCI" == "phpunit" ]; then

    echo "Testing on WordPress master..."
    export WP_TESTS_DIR=/tmp/wordpress-master/tests/phpunit
    cd /tmp/wordpress-master/src/wp-content/plugins/$PLUGIN_SLUG
    if $WP_TRAVISCI; then
	# Everything is fine
	:
    else
        exit 1
    fi

    echo "Testing on WordPress stable..."
    export WP_TESTS_DIR=/tmp/wordpress-latest/tests/phpunit
    cd /tmp/wordpress-latest/src/wp-content/plugins/$PLUGIN_SLUG
    if $WP_TRAVISCI; then
	# Everything is fine
	:
    else
        exit 1
    fi

    echo "Testing on WordPress stable minus one..."
    export WP_TESTS_DIR=/tmp/wordpress-previous/tests/phpunit
    cd /tmp/wordpress-previous/src/wp-content/plugins/$PLUGIN_SLUG
    if $WP_TRAVISCI; then
	# Everything is fine
	:
    else
        exit 1
    fi
else

    gem install less
    rm -rf ~/.yarn
    curl -o- -L https://yarnpkg.com/install.sh | bash -s -- --version 0.20.3
    yarn

    if $WP_TRAVISCI; then
	# Everything is fine
	:
    else
        exit 1
    fi
fi

exit 0
