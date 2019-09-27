# WP Job Manager Unit Tests

## Initial Setup

From the WP Job Manager root directory (if you are using VVV you might need to `vagrant ssh` first), run the following:

1. Install [PHPUnit](http://phpunit.de/) via Composer by running:
    ```
    $ composer install
    ```

2. Install WordPress and the WP Unit Test lib using the `install-wp-tests.sh` script:
    ```
    $ tests/bin/install-wp-tests.sh <db-name> <db-user> <db-password> [db-host]
    ```

You may need to quote strings with backslashes to prevent them from being processed by the shell or other programs.

Example:

    $ tests/bin/install-wp-tests.sh wordpress root root localhost

    #  wordpress is the database name and root is both the MySQL user and its password.

**Important**: The `<db-name>` database will be created if it doesn't exist and all data will be removed during testing.

## Running Tests

Change to the plugin root directory and type:

    $ vendor/bin/phpunit

The tests will execute and you'll be presented with a summary.

You can run specific tests by providing the path and filename to the test class:

    $ vendor/bin/phpunit tests/php/tests/test_class.wp-job-manager-functions.php

A text code coverage summary can be displayed using the `--coverage-text` option:

    $ vendor/bin/phpunit --coverage-text

## Writing Tests

* Each test file should roughly correspond to an associated source file, e.g. the `tests/php/tests/includes/test_class.wp-job-manager-ajax.php` test file covers code in the `includes/class-wp-job-manager-ajax.php` file.
* Each test method should cover a single method or function with one or more assertions.
* A single method or function can have multiple associated test methods, especially if it's a large or complex method.
* Use the test coverage HTML report (under `tmp/coverage/index.html`) to examine which lines your tests are covering and aim for 100% coverage.
* For code that cannot be tested (e.g. they require a certain PHP version), you can exclude them from coverage using a comment: `// @codeCoverageIgnoreStart` and `// @codeCoverageIgnoreEnd`.
* In addition to covering each line of a method/function, make sure to test common input and edge cases.
* Prefer `assertSame()` where possible as it tests both type and value
* Remember that only methods prefixed with `test` will be run so use helper methods liberally to keep test methods small and reduce code duplication. If there is a common helper method used in multiple test files, consider adding it to the `WPJM_BaseTest` class so it can be shared by all test cases.
* Filters persist between test cases so be sure to remove them in your test method or in the `tearDown()` method.

## Automated Tests

Tests are automatically run with [Travis-CI](https://travis-ci.org/automattic/wp-job-manager) for each commit and pull request.
