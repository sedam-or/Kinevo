#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "${1:-.}" && pwd)"
TASK="$ROOT/TASK.md"

[[ -f "$TASK" ]] || { echo "ERROR: TASK.md not found at $TASK" >&2; exit 1; }

python3 - "$TASK" <<'PY'
from __future__ import annotations
import re, sys
from pathlib import Path

p = Path(sys.argv[1])
text = p.read_text(encoding='utf-8')

status_re = re.compile(r'^- Status:\s*(TODO|READY|IN_PROGRESS|BLOCKED|IN_REVIEW|DONE|DEFERRED|CANCELLED)\s*$', re.M)
priority_re = re.compile(r'^- Priority:\s*(P[0-3])\s*$', re.M)
id_re = re.compile(r'^#{3,4}\s+([A-Z]+-\d+)\s+[—-]\s+(.+?)\s*$', re.M)

status_counts = {s: 0 for s in ['TODO','READY','IN_PROGRESS','BLOCKED','IN_REVIEW','DONE','DEFERRED','CANCELLED']}
priority_counts = {f'P{i}': 0 for i in range(4)}

tasks = []
parts = re.split(r'(?=^#{3,4}\s+[A-Z]+-\d+\s+[—-]\s+)', text, flags=re.M)
for part in parts:
    m = id_re.search(part)
    if not m:
        continue
    tid, title = m.groups()
    sm = status_re.search(part)
    pm = priority_re.search(part)
    status = sm.group(1) if sm else 'UNKNOWN'
    priority = pm.group(1) if pm else 'UNKNOWN'
    tasks.append((tid, title, status, priority))
    if status in status_counts:
        status_counts[status] += 1
    if priority in priority_counts:
        priority_counts[priority] += 1

print('LIFESYNC TASK STATUS')
print('====================')
print(f'Total tasks: {len(tasks)}')
print()
print('Status:')
for s, n in status_counts.items():
    print(f'  {s:12} {n:4}')
print()
print('Priority:')
for p, n in priority_counts.items():
    print(f'  {p:4}         {n:4}')
print()
print('Next actionable tasks:')
shown = 0
for tid, title, status, priority in tasks:
    if status in {'READY', 'TODO'} and priority in {'P0', 'P1'}:
        print(f'  [{priority}] {tid} — {title} ({status})')
        shown += 1
        if shown >= 12:
            break
if shown == 0:
    print('  none')
PY
