SHELL := /usr/bin/env bash

PACK ?= KINEVO_DOCUMENTATION_MASTER_PACK.md
SRS ?= KINEVO_SRS_v2.0.0.md

COMPOSE := docker compose -f infrastructure/docker-compose.yml
APP := $(COMPOSE) exec -T app
COMPOSE_PROD := docker compose -f infrastructure/docker-compose.prod.yml

.PHONY: setup setup-force dry-run validate secrets status doctor \
	up down logs migrate shell \
	test lint format analyse ci \
	frontend-install frontend-typecheck frontend-test frontend-build \
	check-links check-openapi \
	ollama-up ollama-down ai-status ai-smoke \
	prod-build prod-up prod-down prod-migrate prod-logs prod-certbot \
	prod-backup prod-backup-list prod-restore

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

# --- Optional AI development adapter (Ollama) --------------------------------
# The ollama service is opt-in (compose profile "ai") and internal-network
# only. The app remains fully operational without it (SRS FR-60).
ollama-up:
	$(COMPOSE) --profile ai up -d

ollama-down:
	$(COMPOSE) --profile ai down

ai-status:
	$(APP) php artisan ai:status

ai-smoke:
	$(APP) php artisan ai:smoke

# --- Production profile (TASK-080; deployment.md) ---------------------------
# Secrets (APP_KEY, DB_PASSWORD, APP_URL) come from the deployment environment.
prod-build:
	$(COMPOSE_PROD) build

prod-up:
	$(COMPOSE_PROD) up -d

prod-down:
	$(COMPOSE_PROD) down

prod-migrate:
	$(COMPOSE_PROD) run --rm app migrate

prod-logs:
	$(COMPOSE_PROD) logs -f

# Issue/renew a Let's Encrypt cert for SERVER_NAME via the webroot method
# (TASK-081). Requires the reverse-proxy to be up and SERVER_NAME + an email.
# usage: make prod-certbot EMAIL=admin@example.com
prod-certbot:
	$(COMPOSE_PROD) --profile certbot run --rm certbot certonly \
		--webroot -w /var/www/certbot \
		--email "$(EMAIL)" --agree-tos --no-eff-email \
		-d "$${SERVER_NAME}"

# --- Backup & restore (TASK-082, SRS §16.4/NFR-05) ---------------------------
# Run an on-demand backup against the running prod postgres. Remote copy is
# enabled with REMOTE_BUCKET=s3://bucket/prefix.
prod-backup:
	$(COMPOSE_PROD) run --rm backup /backup/backup.sh

# List local backups in the backup volume.
prod-backup-list:
	$(COMPOSE_PROD) run --rm --entrypoint /bin/sh backup -c "ls -lht /backups"

# Restore the newest local backup (or BACKUP_FILE). DESTRUCTIVE — requires
# CONFIRM_RESTORE=yes.
prod-restore:
	$(COMPOSE_PROD) run --rm -e CONFIRM_RESTORE="$(CONFIRM_RESTORE)" \
		-e BACKUP_DIR=/backups \
		-e "BACKUP_FILE=$(BACKUP_FILE)" \
		--entrypoint /bin/sh backup -c \
		"apk add --no-cache bash >/dev/null 2>&1 && /backup/restore.sh \"$$BACKUP_FILE\""

# --- Quality gates (run inside the app container) ---------------------------
test:
	$(APP) php artisan test

lint:
	$(APP) vendor/bin/pint --test

format:
	$(APP) vendor/bin/pint

analyse:
	$(APP) vendor/bin/phpstan analyse --memory-limit=512M

# --- Frontend gates (run inside the app container; Node is available in the
# dev image) ------------------------------------------------------------------
frontend-install:
	$(APP) npm install --ignore-scripts

frontend-typecheck:
	$(APP) npm run typecheck

frontend-test:
	$(APP) npm run test

frontend-build:
	$(APP) npm run build

ci: validate secrets check-links check-openapi lint analyse test \
	frontend-typecheck frontend-test frontend-build
