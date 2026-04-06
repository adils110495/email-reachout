# ──────────────────────────────────────────────────────────────
# AI Client Finder — Docker helpers
# ──────────────────────────────────────────────────────────────

.PHONY: up down build restart logs shell migrate seed fresh artisan queue

## Start all services in the background
up:
	docker compose up -d

## Stop all services
down:
	docker compose down

## Rebuild images (use after changing Dockerfile or php.ini)
build:
	docker compose build --no-cache

## Restart all services
restart:
	docker compose restart

## Tail logs from all containers
logs:
	docker compose logs -f

## Open a shell inside the PHP-FPM container
shell:
	docker compose exec app bash

## Run migrations
migrate:
	docker compose exec app php artisan migrate

## Run migrations + seeders
seed:
	docker compose exec app php artisan db:seed

## Drop all tables and re-run migrations
fresh:
	docker compose exec app php artisan migrate:fresh

## Run any artisan command: make artisan CMD="leads:find 'agency'"
artisan:
	docker compose exec app php artisan $(CMD)

## Start the queue worker manually (already running as the 'queue' service)
queue:
	docker compose exec app php artisan queue:work database --queue=emails,default --tries=3
