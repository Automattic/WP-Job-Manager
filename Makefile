.DEFAULT_GOAL := help

PLUGIN_NAME := wp-job-manager
WP_ENV := COMPOSE_PROJECT_NAME=$(PLUGIN_NAME) npx @wordpress/env

## Development environment
up: ## Start WordPress development environment
	$(WP_ENV) start

down: ## Stop WordPress development environment
	$(WP_ENV) stop

destroy: ## Remove WordPress environment containers and data
	$(WP_ENV) destroy

logs: ## Show WordPress environment logs
	$(WP_ENV) logs

## Testing
test: ## Run PHPUnit tests in (requires: make start)
	$(WP_ENV) run tests-cli --env-cwd=wp-content/plugins/wp-job-manager vendor/bin/phpunit

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

.PHONY: up down destroy logs test lint lint-fix build help
