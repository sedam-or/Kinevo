SHELL := /usr/bin/env bash

PACK ?= LIFESYNC_DOCUMENTATION_MASTER_PACK.md
SRS ?= LIFESYNC_OS_SRS_v2.0.0.md

.PHONY: setup setup-force dry-run validate status doctor

setup:
	./scripts/bootstrap-docs.sh --pack "$(PACK)" --srs "$(SRS)"

setup-force:
	./scripts/bootstrap-docs.sh --pack "$(PACK)" --srs "$(SRS)" --force

dry-run:
	./scripts/bootstrap-docs.sh --pack "$(PACK)" --srs "$(SRS)" --dry-run

validate:
	./scripts/validate-repo.sh .

status:
	./scripts/status.sh .

doctor: validate status
