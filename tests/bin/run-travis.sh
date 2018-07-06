#!/bin/bash

set -e

source ~/.nvm/nvm.sh

run_phpunit_for() {
	test_branch="$1";
	echo "Testing on $test_branch..."
	export WP_TESTS_DIR="/tmp/$test_branch/tests/phpunit"
	cd "/tmp/$test_branch/src/wp-content/plugins/$PLUGIN_SLUG"
	nvm use 8
	npm install >/dev/null
	./node_modules/.bin/mixtape build >/dev/null

	phpunit

	if [ $? -ne 0 ]; then
		exit 1
	fi
}

if [ "$WP_TRAVISCI" == "phpunit" ]; then
	WP_SLUGS=('master' 'latest' 'previous')

	if [ ! -z "$WP_VERSION" ]; then
		WP_SLUGS=("$WP_VERSION")
	fi

	for WP_SLUG in "${WP_SLUGS[@]}"; do
		run_phpunit_for "wordpress-$WP_SLUG"
	done
elif [ "$WP_TRAVISCI" == "phpcs" ]; then
	composer install

	echo "Testing PHP code formatting..."

	bash ./tests/bin/phpcs.sh

	if [ $? -ne 0 ]; then
		exit 1
	fi
else

	npm install npm -g
	npm install

		if $WP_TRAVISCI; then
	# Everything is fine
	:
		else
				exit 1
		fi
fi

exit 0
