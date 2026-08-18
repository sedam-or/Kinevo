#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'USAGE'
Usage:
  ./scripts/bootstrap-docs.sh [OPTIONS]

Options:
  --pack FILE        Master documentation pack (default: ./KINEVO_DOCUMENTATION_MASTER_PACK.md)
  --srs FILE         Authoritative SRS file to install as docs/SRS.md (optional)
  --root DIR         Target repository root (default: current directory)
  --force            Overwrite files extracted from the pack
  --dry-run          Show actions without writing files
  --skip-gitignore   Do not create a helper .gitignore entry for generated artifacts
  -h, --help         Show help

The master pack uses `## <path>` as a document delimiter. Sections ending with `/`
are treated as directories; other sections are materialized as files.
USAGE
}

PACK="./KINEVO_DOCUMENTATION_MASTER_PACK.md"
SRS=""
ROOT="$(pwd)"
FORCE=0
DRY_RUN=0
SKIP_GITIGNORE=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --pack) PACK="$2"; shift 2 ;;
    --srs) SRS="$2"; shift 2 ;;
    --root) ROOT="$2"; shift 2 ;;
    --force) FORCE=1; shift ;;
    --dry-run) DRY_RUN=1; shift ;;
    --skip-gitignore) SKIP_GITIGNORE=1; shift ;;
    -h|--help) usage; exit 0 ;;
    *) echo "ERROR: unknown argument: $1" >&2; usage >&2; exit 2 ;;
  esac
done

ROOT="$(cd "$ROOT" && pwd)"
PACK="$(cd "$(dirname "$PACK")" && pwd)/$(basename "$PACK")"
[[ -f "$PACK" ]] || { echo "ERROR: master pack not found: $PACK" >&2; exit 1; }
if [[ -n "$SRS" ]]; then
  SRS="$(cd "$(dirname "$SRS")" && pwd)/$(basename "$SRS")"
  [[ -f "$SRS" ]] || { echo "ERROR: SRS not found: $SRS" >&2; exit 1; }
fi

export ROOT PACK SRS FORCE DRY_RUN SKIP_GITIGNORE
python3 - <<'PY'
from __future__ import annotations
import os
import re
from pathlib import Path

root = Path(os.environ['ROOT']).resolve()
pack = Path(os.environ['PACK']).resolve()
srs = Path(os.environ['SRS']).resolve() if os.environ['SRS'] else None
force = os.environ['FORCE'] == '1'
dry_run = os.environ['DRY_RUN'] == '1'
skip_gitignore = os.environ['SKIP_GITIGNORE'] == '1'

HEADING = re.compile(r'^##\s+(.+?)\s*$')
SAFE = re.compile(r'^[A-Za-z0-9._/ -]+$')

text = pack.read_text(encoding='utf-8')
lines = text.splitlines(keepends=True)
sections: list[tuple[str, str]] = []
current = None
buf: list[str] = []

for line in lines:
    m = HEADING.match(line.rstrip('\n'))
    if m:
        if current is not None:
            sections.append((current, ''.join(buf)))
        current = m.group(1).strip()
        buf = []
    elif current is not None:
        buf.append(line)

if current is not None:
    sections.append((current, ''.join(buf)))

if not sections:
    raise SystemExit('ERROR: no `## <path>` sections found in master pack')

created_files = 0
created_dirs = 0
skipped_files = 0

for rel, body in sections:
    if rel.startswith('!') or rel.startswith('#'):
        continue
    rel = rel.replace('\\', '/')
    if rel.startswith('/') or rel == '..' or rel.startswith('../') or '/..' in rel.split('/'):
        raise SystemExit(f'ERROR: unsafe path in pack: {rel}')
    if not SAFE.match(rel):
        raise SystemExit(f'ERROR: unsupported characters in path: {rel}')

    target = (root / rel).resolve()
    try:
        target.relative_to(root)
    except ValueError:
        raise SystemExit(f'ERROR: path escapes repo root: {rel}')

    if rel.endswith('/'):
        if dry_run:
            print(f'[DIR ] {rel}')
        else:
            target.mkdir(parents=True, exist_ok=True)
        created_dirs += 1
        continue

    if target.exists() and not force:
        print(f'[SKIP] {rel} (exists; use --force to overwrite)')
        skipped_files += 1
        continue

    if dry_run:
        print(f'[FILE] {rel}')
        continue

    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(body.lstrip('\n'), encoding='utf-8')
    created_files += 1
    print(f'[FILE] {rel}')

# Optional authoritative SRS replacement.
if srs:
    target = root / 'docs' / 'SRS.md'
    if target.exists() and not force:
        print('[SKIP] docs/SRS.md (exists; authoritative --srs not installed; use --force)')
    elif dry_run:
        print('[FILE] docs/SRS.md (authoritative SRS)')
    else:
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(srs.read_text(encoding='utf-8'), encoding='utf-8')
        print('[FILE] docs/SRS.md (authoritative SRS)')

# Create helper source-control directories if absent.
for d in ('scripts',):
    p = root / d
    if dry_run:
        print(f'[DIR ] {d}/')
    else:
        p.mkdir(parents=True, exist_ok=True)

if not skip_gitignore:
    gi = root / '.gitignore'
    marker = '# Kinevo bootstrap helper'
    content = gi.read_text(encoding='utf-8') if gi.exists() else ''
    lines = [x.rstrip('\n') for x in content.splitlines()]
    if marker not in lines:
        addition = '\n'.join([
            marker,
            '.bootstrap-state/',
            '',
        ])
        new_content = content.rstrip() + ('\n\n' if content.strip() else '') + addition
        if dry_run:
            print('[FILE] .gitignore (helper entries)')
        else:
            gi.write_text(new_content, encoding='utf-8')
            print('[FILE] .gitignore (helper entries)')

print(f'\nBootstrap complete: files={created_files}, dirs={created_dirs}, skipped={skipped_files}')
PY
