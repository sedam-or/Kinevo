SHELL := /usr/bin/env bash

PACK ?= LIFESYNC_DOCUMENTATION_MASTER_PACK.md
SRS ?= LIFESYNC_OS_SRS_v2.0.0.md

COMPOSE := docker compose -f infrastructure/docker-compose.yml
APP := $(COMPOSE) exec -T app

.PHONY: setup setup-force dry-run validate secrets status doctor \
	up down logs migrate shell \
	test lint format analyse ci \
	check-links check-openapi

# --- Documentation bootstrap -------------------------------------------------
setup:
	./scripts/bootstrap-docs.sh --pack "$(PACK)" --srs "$(SRS)"

setup-force:
	./scripts/bootstrap-docs.sh --pack "$(PACK)" --srs "$(SRS)" --force

dry-run:
	./scripts/bootstrap-docs.sh --pack "$(PACK)" --srs "$(SRS)" --dry-run

# --- Repository validation ---------------------------------------------------
validate:
	./scripts/validate-repo.sh .

secrets:
	./scripts/check-secrets.sh .

status:
	./scripts/status.sh .

check-links:
	./scripts/check-doc-links.sh .

check-openapi:
	./scripts/check-openapi.sh .

doctor: validate status

# --- Docker services ---------------------------------------------------------
up:
	$(COMPOSE) up -d --build

down:
	$(COMPOSE) down

logs:
	$(COMPOSE) logs -f

migrate:
	$(APP) php artisan migrate --force

shell:
	$(APP) sh

# --- Quality gates (run inside the app container) ---------------------------
test:
	$(APP) php artisan test

lint:
	$(APP) vendor/bin/pint --test

format:
	$(APP) vendor/bin/pint

analyse:
	$(APP) vendor/bin/phpstan analyse --memory-limit=512M

# Frontend gates require a Node toolchain and frontend sources (Vue/TypeScript),
# which are introduced with the frontend bootstrap task. They are not defined
# until `server/package.json` scripts and sources exist.

ci: validate secrets check-links check-openapi lint analyse test
