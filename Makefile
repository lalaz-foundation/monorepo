.PHONY: help test lint analyze check docs install clean

help: ## Show commands
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[32m%-15s\033[0m %s\n", $$1, $$2}'

install: ## Install all dependencies
	@composer install
	@for dir in packages/*/; do cd "$$dir" && composer install && cd ../..; done
	@cd docs && npm install

test: ## Run all tests
	@vendor/bin/phpunit

test-%: ## Test package (e.g., make test-auth)
	@cd packages/$* && vendor/bin/phpunit

coverage: ## Run tests with coverage
	@vendor/bin/phpunit --coverage-html coverage

lint: ## Check code style
	@php -d memory_limit=512M vendor/bin/php-cs-fixer fix --dry-run --diff

fix: ## Fix code style
	@php -d memory_limit=512M vendor/bin/php-cs-fixer fix

analyze: ## Static analysis
	@vendor/bin/phpstan analyse

check: lint analyze test ## Run all checks

audit: ## Security audit
	@composer audit

docs: ## Start docs server
	@cd docs && npm run docs:dev

docs-build: ## Build docs
	@cd docs && npm run docs:build

clean: ## Clean generated files
	@rm -rf coverage/ .phpunit.cache/ .php-cs-fixer.cache docs/.vitepress/dist docs/.vitepress/cache

packages: ## List packages
	@ls -1 packages/

validate: ## Validate composer.json files
	@for dir in packages/*/; do echo "$$dir" && cd "$$dir" && composer validate && cd ../..; done
