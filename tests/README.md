# WP Job Manager Unit Tests

## Running Tests with wp-env (Recommended)

wp-env provides a Docker-based WordPress environment with MySQL. Requires [Docker Desktop](https://www.docker.com/products/docker-desktop/).

1. Install dependencies:
    ```
    $ make install
    ```

2. Start the environment:
    ```
    $ make up
    ```

3. Run the tests:
    ```
    $ make test
    ```

4. Stop the environment when done:
    ```
    $ make down
    ```

Run `make help` to see all available commands.

## Running Tests Manually

If you prefer not to use Docker, you can set up the test environment manually.

1. Install [PHPUnit](http://phpunit.de/) via Composer by running:
    ```
    $ composer install
    ```

2. Install WordPress and the WP Unit Test lib using the `install-wp-tests.sh` script:
    ```
    $ tests/bin/install-wp-tests.sh <db-name> <db-user> <db-password> [db-host]
    ```

    Example:

        $ tests/bin/install-wp-tests.sh wordpress root root localhost

    **Important**: The `<db-name>` database will be created if it doesn't exist and all data will be removed during testing.

3. Run the tests:
    ```
    $ vendor/bin/phpunit
    ```

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

Tests are automatically run with GitHub Actions for each commit and pull request.
