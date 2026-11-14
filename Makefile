.PHONY: help install dev demo e2e-server up down logs build test test-php test-client test-js test-e2e lint phpcs phpstan fix check

## List the targets
help:
	@awk '/^## /{doc=substr($$0,4); next} /^[a-z0-9-]+:/{if(doc){split($$0,t,":"); printf "  %-12s %s\n", t[1], doc; doc=""}}' $(MAKEFILE_LIST)

## Install PHP dependencies (including the dev tools) and build the front-end libraries (pnpm)
install:
	composer install
	pnpm install --frozen-lockfile
	pnpm build

## Local server on http://localhost:8080 (log in with SITE_PASSWORD_HASH from .env)
dev:
	php -S localhost:8080 -t public bin/dev-router.php

# Settings of the local servers with the demo family (make demo, make e2e-server); the password is "demo".
DEMO_ENV = SITE_FORCE_HTTPS=0 SITE_SYNC_TOKEN= SITE_DEFAULT_PERSON= SITE_SEARCH_HINT= SITE_TRUSTED_PROXIES= \
	SITE_PASSWORD_HASH='$$2y$$12$$JloCvcq/xvCOGUKjMt3MKek0wcT5WN30tmUz0t4GcxTnvyJv0xq/q' \
	SITE_SECRET=demo-secret-only-for-the-local-demo-server-0000

## Local server with the demo family on http://localhost:8081 (password: demo)
demo:
	php bin/demo-data.php $(CURDIR)/var/demo
	SITE_DATA_DIR=$(CURDIR)/var/demo SITE_CACHE_DIR=$(CURDIR)/var/demo-cache SITE_LOG_DIR=$(CURDIR)/var/demo-log $(DEMO_ENV) \
		php -S localhost:8081 -t public bin/dev-router.php

## Server for the end-to-end tests on http://localhost:8090: fresh demo data, cache and logs in var/e2e
# Several PHP workers, because the tests run in parallel.
e2e-server:
	rm -rf $(CURDIR)/var/e2e
	php bin/demo-data.php $(CURDIR)/var/e2e/data
	SITE_DATA_DIR=$(CURDIR)/var/e2e/data SITE_CACHE_DIR=$(CURDIR)/var/e2e/cache SITE_LOG_DIR=$(CURDIR)/var/e2e/log $(DEMO_ENV) \
		PHP_CLI_SERVER_WORKERS=4 php -S localhost:8090 -t public bin/dev-router.php

## Build and start the Docker container (production-like)
up:
	docker compose up -d --build

## Stop the Docker container
down:
	docker compose down

## Tail the container logs
logs:
	docker compose logs -f

## Rebuild the image without cache
build:
	docker compose build --no-cache

## Unit and integration tests (fixture data, no export needed)
test: test-php test-client test-js

test-php:
	vendor/bin/phpunit

## Tests of the sync client bin/rodokmen-sync (Python standard library only)
test-client:
	python3 -m unittest discover -s tests/client

## Unit tests of the browser scripts in public/assets (Vitest)
test-js:
	pnpm test

## End-to-end tests in Chromium against make e2e-server (Playwright; once: pnpm exec playwright install chromium)
test-e2e:
	pnpm test:e2e

## Coding standard and static analysis
lint: phpcs phpstan

phpcs:
	vendor/bin/phpcs

phpstan:
	vendor/bin/phpstan analyse --memory-limit=1G

## Fix coding standard violations that can be fixed automatically
fix:
	vendor/bin/phpcbf || true

## Everything that must pass before a commit
check: lint test
