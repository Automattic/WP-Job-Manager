# wp-env Local Development Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add wp-env as the standard local development environment for running WordPress and PHPUnit tests.

**Architecture:** wp-env provides Docker-based WordPress dev and test instances. The plugin is mounted into both. PHPUnit runs inside the test container where `WP_TESTS_DIR` is already set. A Makefile wraps all commands. A Playground blueprint enables hosted demos.

**Tech Stack:** `@wordpress/env`, Docker Desktop, GNU Make

---

### Task 1: Add `.wp-env.json`

**Files:**
- Create: `.wp-env.json`

**Step 1: Create the wp-env config file**

```json
{
	"core": null,
	"plugins": [ "." ],
	"config": {
		"WP_DEBUG": true,
		"WP_DEBUG_LOG": true
	},
	"phpVersion": "8.1"
}
```

**Step 2: Verify wp-env picks it up**

Run: `npx wp-env start`
Expected: WordPress available at http://localhost:8888, test instance at http://localhost:8889

**Step 3: Stop the environment**

Run: `npx wp-env stop`

**Step 4: Commit**

```bash
git add .wp-env.json
git commit -m "Add .wp-env.json for local development"
```

---

### Task 2: Install `@wordpress/env` as dev dependency

**Files:**
- Modify: `package.json`

**Step 1: Install the package**

Run: `npm install --save-dev @wordpress/env`

**Step 2: Verify it's in devDependencies**

Run: `node -e "console.log(require('./package.json').devDependencies['@wordpress/env'])"`
Expected: prints a version string

**Step 3: Commit**

```bash
git add package.json package-lock.json
git commit -m "Add @wordpress/env as dev dependency"
```

---

### Task 3: Add `blueprint.json` for Playground

**Files:**
- Create: `blueprint.json`

**Step 1: Create the blueprint**

```json
{
	"landingPage": "/wp-admin/edit.php?post_type=job_listing",
	"login": true,
	"steps": [
		{
			"step": "installPlugin",
			"pluginData": {
				"resource": "wordpress.org/plugins",
				"slug": "wp-job-manager"
			}
		}
	]
}
```

Note: Uses the wordpress.org release (not local source) since this is for hosted Playground demos.

**Step 2: Verify the blueprint loads**

Open in browser: `https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/Automattic/WP-Job-Manager/trunk/blueprint.json`

This won't work until merged to trunk — just verify the JSON is valid for now:
Run: `node -e "JSON.parse(require('fs').readFileSync('blueprint.json','utf8')); console.log('Valid JSON')"`
Expected: `Valid JSON`

**Step 3: Commit**

```bash
git add blueprint.json
git commit -m "Add Playground blueprint for hosted demos"
```

---

### Task 4: Create `Makefile`

**Files:**
- Create: `Makefile`

**Step 1: Create the Makefile**

```makefile
.DEFAULT_GOAL := help

## Development environment
start: ## Start WordPress development environment
	npx wp-env start

stop: ## Stop WordPress development environment
	npx wp-env stop

destroy: ## Remove WordPress environment containers and data
	npx wp-env destroy

logs: ## Show WordPress environment logs
	npx wp-env logs

## Testing
test: ## Run PHPUnit tests in wp-env
	npx wp-env run tests-cli --env-cwd=wp-content/plugins/wp-job-manager vendor/bin/phpunit

lint: ## Run PHP CodeSniffer
	./vendor/bin/phpcs

lint-fix: ## Auto-fix PHP CodeSniffer issues
	./vendor/bin/phpcbf

## Build
build: ## Build plugin zip
	npm run build

## Help
help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-15s\033[0m %s\n", $$1, $$2}'

.PHONY: start stop destroy logs test lint lint-fix build help
```

**Step 2: Verify `make help` works**

Run: `make help`
Expected: lists all targets with descriptions

**Step 3: Verify `make start` works**

Run: `make start`
Expected: wp-env starts, WordPress available at localhost:8888

**Step 4: Verify `make test` works**

Run: `make test`
Expected: PHPUnit runs inside the test container and executes the test suite

Note: The `--env-cwd` flag sets the working directory inside the container to the plugin directory so `vendor/bin/phpunit` resolves correctly. If this path doesn't work, the alternative is:
```makefile
test:
	npx wp-env run tests-cli phpunit --path=/var/www/html --config=wp-content/plugins/wp-job-manager/phpunit.xml.dist
```

**Step 5: Stop environment**

Run: `make stop`

**Step 6: Commit**

```bash
git add Makefile
git commit -m "Add Makefile with wp-env development targets"
```

---

### Task 5: Update `tests/README.md`

**Files:**
- Modify: `tests/README.md`

**Step 1: Add wp-env section as the recommended method**

Replace the entire file with:

```markdown
# WP Job Manager Unit Tests

## Running Tests with wp-env (Recommended)

wp-env provides a Docker-based WordPress environment with MySQL. Requires [Docker Desktop](https://www.docker.com/products/docker-desktop/).

1. Install dependencies:
    ```
    $ composer install
    $ npm install
    ```

2. Start the test environment:
    ```
    $ make start
    ```

3. Run the tests:
    ```
    $ make test
    ```

4. Stop the environment when done:
    ```
    $ make stop
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
```

**Step 2: Commit**

```bash
git add tests/README.md
git commit -m "Update test docs with wp-env instructions"
```

---

### Task 6: Update `docs/CONTRIBUTING.md`

**Files:**
- Modify: `docs/CONTRIBUTING.md`

**Step 1: Replace the "Getting started" section**

Replace lines 20-25 (the Getting started section) with:

```markdown
## Getting started

- **Prerequisites:** [Docker Desktop](https://www.docker.com/products/docker-desktop/), Node.js, Composer
- **Quick start:** `composer install && npm install && make start` — then visit http://localhost:8888
- **Run tests:** `make test`
- **All commands:** `make help`
- [Git Flow and PR Review](https://github.com/Automattic/WP-Job-Manager/wiki/Our-Git-Flow-and-PR-Review)
- [String localisation guidelines](https://codex.wordpress.org/I18n_for_WordPress_Developers)
- [Running unit tests](https://github.com/Automattic/WP-Job-Manager/blob/trunk/tests/README.md)
```

**Step 2: Commit**

```bash
git add docs/CONTRIBUTING.md
git commit -m "Update contributing docs with wp-env quick start"
```

---

### Task 7: Add Playground link to `README.md`

**Files:**
- Modify: `README.md`

**Step 1: Add a "Try it" section after the Description heading**

After line 5 (the description paragraph), add:

```markdown

### Try it ###

[Open WP Job Manager in WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/Automattic/WP-Job-Manager/trunk/blueprint.json) — no install required.
```

**Step 2: Commit**

```bash
git add README.md
git commit -m "Add Playground demo link to README"
```

---

### Task 8: Add `.wp-env.json` to build exclusion

**Files:**
- Check: `scripts/build-plugin.sh`
- Check: `scripts/exclude.lst` (if it exists)

**Step 1: Check if `.wp-env.json` would be included in builds**

The `rsync` in `build-plugin.sh` copies everything not explicitly excluded. `.wp-env.json`, `blueprint.json`, and `Makefile` would be included in the release zip.

Check if `scripts/exclude.lst` already handles this:
Run: `cat scripts/exclude.lst`

**Step 2: Add exclusions if needed**

Add `.wp-env.json`, `blueprint.json`, and `Makefile` to `scripts/exclude.lst` (one per line, as glob patterns matching the zip path).

Alternatively, add `--exclude .wp-env.json --exclude blueprint.json --exclude Makefile` to the rsync command in `build-plugin.sh`.

**Step 3: Verify by building**

Run: `npm run build && unzip -l build/wp-job-manager.zip | grep -E '\.wp-env|blueprint|Makefile'`
Expected: no matches (files excluded from build)

**Step 4: Commit**

```bash
git add scripts/exclude.lst  # or scripts/build-plugin.sh
git commit -m "Exclude dev config files from release build"
```

---

### Task 9: Smoke test the full workflow

**Step 1: Clean slate**

Run: `make destroy` (if environment exists)

**Step 2: Start fresh**

Run: `make start`
Expected: WordPress running at localhost:8888

**Step 3: Verify plugin is active**

Open http://localhost:8888/wp-admin/ (user: admin, pass: password)
Navigate to Plugins — WP Job Manager should be active.

**Step 4: Run PHPUnit**

Run: `make test`
Expected: all tests pass

**Step 5: Clean up**

Run: `make stop`
