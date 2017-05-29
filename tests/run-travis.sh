#!/bin/bash

test_on() {
    local wordpress_to_test=$1;
    local plugin_path="/tmp/$wordpress_to_test/src/wp-content/plugins/$PLUGIN_SLUG";
    echo "Testing on $wordpress_to_test";
    export WP_TESTS_DIR="/tmp/$wordpress_to_test/tests/phpunit";
    cd $plugin_path;
    $WP_TRAVISCI;
    if [ $? -ne 0 ]; then
        echo "PHPUnit ($WP_TRAVISCI): Failed for $wordpress_to_test";
        exit 1;
    fi
}

if [ "$WP_TRAVISCI" == "phpunit" ]; then
    test_on "wordpress-master";
    test_on "wordpress-latest";
    test_on "wordpress-previous";
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
