.DEFAULT_GOAL := help

PLUGIN_NAME := wp-job-manager
WP_ENV := COMPOSE_PROJECT_NAME=$(PLUGIN_NAME) npx @wordpress/env
WP_ENV_TESTS := COMPOSE_PROJECT_NAME=$(PLUGIN_NAME)-tests npx @wordpress/env --config .wp-env.tests.json
NODE_MIN_VERSION := 20

define check_node
	@NODE_VERSION=$$(node --version 2>/dev/null | sed 's/v//'); \
	if [ -z "$$NODE_VERSION" ]; then \
		echo "Error: Node.js is not installed. Please install Node.js $(NODE_MIN_VERSION)+ from https://nodejs.org"; \
		exit 1; \
	fi; \
	NODE_MAJOR=$$(echo "$$NODE_VERSION" | cut -d. -f1); \
	if [ "$$NODE_MAJOR" -lt "$(NODE_MIN_VERSION)" ]; then \
		echo "Error: Node.js v$$NODE_VERSION found, but $(NODE_MIN_VERSION)+ is required."; \
		echo "If using nvm: nvm install $(NODE_MIN_VERSION) && nvm use $(NODE_MIN_VERSION)"; \
		exit 1; \
	fi
endef

## Development environment
install: ## Install dependencies (requires Node 20+, PHP/Composer)
	$(check_node)
	composer install
	npm install

up: ## Start WordPress development and test environments
	$(WP_ENV) start
	$(WP_ENV_TESTS) start

down: ## Stop WordPress development and test environments
	$(WP_ENV) stop
	$(WP_ENV_TESTS) stop

destroy: ## Remove WordPress environment containers and data
	$(WP_ENV) destroy

logs: ## Show WordPress environment logs
	$(WP_ENV) logs

## Testing
test-up: ## Start WordPress test environment
	$(WP_ENV_TESTS) start

test-down: ## Stop WordPress test environment
	$(WP_ENV_TESTS) stop

test: ## Run PHPUnit tests (requires: make test-up)
	$(WP_ENV_TESTS) run cli --env-cwd=wp-content/plugins/wp-job-manager vendor/bin/phpunit

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

.PHONY: install up down destroy logs test-up test-down test lint lint-fix build help
