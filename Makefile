.PHONY: help install dev up down logs build test test-php test-client lint phpcs phpstan fix check

## List the targets
help:
	@awk '/^## /{doc=substr($$0,4); next} /^[a-z-]+:/{if(doc){split($$0,t,":"); printf "  %-10s %s\n", t[1], doc; doc=""}}' $(MAKEFILE_LIST)

## Install PHP dependencies (including the dev tools)
install:
	composer install

## Local server on http://localhost:8080 (log in with SITE_PASSWORD_HASH from .env)
dev:
	php -S localhost:8080 -t public bin/dev-router.php

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
test: test-php test-client

test-php:
	vendor/bin/phpunit

## Tests of the sync client bin/rodokmen-sync (Python standard library only)
test-client:
	python3 -m unittest discover -s tests/client

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
