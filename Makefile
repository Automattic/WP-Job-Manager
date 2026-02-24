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
test: ## Run PHPUnit tests in wp-env (requires: make start)
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
