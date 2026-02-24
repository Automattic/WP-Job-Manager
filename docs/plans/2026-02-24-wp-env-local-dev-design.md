# Design: Local Development with wp-env

## Problem

No standardized local dev environment. Developers must manually run `tests/bin/install-wp-tests.sh`, configure MySQL, and manage WordPress installs to run PHPUnit tests locally.

## Solution

Use `@wordpress/env` (wp-env) to provide a Docker-based local WordPress environment for development and testing. Add a Playground blueprint for hosted demos.

## Components

### `.wp-env.json`

Maps the plugin into WordPress with debug settings enabled. wp-env provides two environments:
- **Dev** (port 8888) — for manual testing and development
- **Test** (port 8889) — for PHPUnit, with the WP test library pre-configured

```json
{
  "core": null,
  "plugins": ["."],
  "config": {
    "WP_DEBUG": true,
    "WP_DEBUG_LOG": true
  },
  "phpVersion": "8.1"
}
```

The existing `tests/php/bootstrap.php` reads `WP_TESTS_DIR` from the environment. wp-env sets this automatically in the test container — no bootstrap changes needed.

### `blueprint.json`

Static file for a "Try in Playground" link. Installs and activates the plugin in the hosted Playground environment. No local CLI dependency.

### `Makefile`

Wraps wp-env and existing tooling:

| Target    | Action                                    |
|-----------|-------------------------------------------|
| `start`   | `wp-env start`                           |
| `stop`    | `wp-env stop`                            |
| `test`    | Run PHPUnit in wp-env test container     |
| `lint`    | `phpcs`                                  |
| `destroy` | `wp-env destroy`                         |
| `logs`    | `wp-env logs`                            |
| `help`    | List available targets                   |

### Dev dependency

`@wordpress/env` added to `devDependencies` in `package.json`.

### Documentation updates

- **docs/CONTRIBUTING.md** — Replace wiki link with `make start` / `make test` instructions
- **tests/README.md** — Add wp-env as recommended local test method; keep manual setup as alternative
- **README.md** — Add "Try in Playground" link

## Unchanged

- `tests/bin/install-wp-tests.sh` and other bin scripts (still used by CI)
- `.github/workflows/php.yml` (CI pipeline)
- `tests/php/bootstrap.php` (already compatible)
- `phpunit.xml.dist`
