#!/bin/bash

set -e

source ~/.nvm/nvm.sh

run_phpunit_for() {
  test_branch="$1";
  echo "Testing on $test_branch..."
  export WP_TESTS_DIR="/tmp/$test_branch/tests/phpunit"
  cd "/tmp/$test_branch/src/wp-content/plugins/$PLUGIN_SLUG"
  nvm use 6
  npm install >/dev/null
  ./node_modules/.bin/mixtape build >/dev/null

  if [[ ${TRAVIS_PHP_VERSION:0:3} == "5.2" ]]; then
    phpunit --exclude-group rest
  else
    phpunit
  fi

  if [ $? -ne 0 ]; then
    exit 1
  fi
}

if [ "$WP_TRAVISCI" == "phpunit" ]; then
    run_phpunit_for "wordpress-master"
    run_phpunit_for "wordpress-latest"
    run_phpunit_for "wordpress-previous"
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
