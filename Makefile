SHELL := /usr/bin/env bash

PACK ?= LIFESYNC_DOCUMENTATION_MASTER_PACK.md
SRS ?= LIFESYNC_OS_SRS_v2.0.0.md

.PHONY: setup setup-force dry-run validate secrets status doctor up down logs migrate shell

setup:
	./scripts/bootstrap-docs.sh --pack "$(PACK)" --srs "$(SRS)"

setup-force:
	./scripts/bootstrap-docs.sh --pack "$(PACK)" --srs "$(SRS)" --force

dry-run:
	./scripts/bootstrap-docs.sh --pack "$(PACK)" --srs "$(SRS)" --dry-run

validate:
	./scripts/validate-repo.sh .

secrets:
	./scripts/check-secrets.sh .

status:
	./scripts/status.sh .

doctor: validate status

up:
	docker compose -f infrastructure/docker-compose.yml up -d --build

down:
	docker compose -f infrastructure/docker-compose.yml down

logs:
	docker compose -f infrastructure/docker-compose.yml logs -f

migrate:
	docker compose -f infrastructure/docker-compose.yml exec app php artisan migrate --force

shell:
	docker compose -f infrastructure/docker-compose.yml exec app sh
