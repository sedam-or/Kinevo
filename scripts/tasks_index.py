#!/usr/bin/env python3
"""Task index generator and validator for Nivaras (stdlib only).

Usage
  python3 scripts/tasks_index.py           regenerate tasks/index.md
  python3 scripts/tasks_index.py --check   validate every task and fail (exit 1) if index.md is stale

Rules checked are listed in tasks/README.md.
"""
import os
import re
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import spec_tool  # noqa: E402  (same directory, stdlib only)

ROOT = spec_tool.ROOT
TASKS = os.path.join(ROOT, "tasks")
INDEX = os.path.join(TASKS, "index.md")

STATUSES = ("draft", "todo", "in_progress", "review", "changes_requested", "blocked", "done")
KINDS = ("feature", "integration")
MILESTONES = [f"M{n}" for n in range(0, 12)]
REQUIRED_FIELDS = ("id", "title", "milestone", "kind", "status", "depends_on", "requirements", "branch",
                   "budget_lines", "assignee")
SECTIONS = ("Goal", "Context", "Scope", "Files", "Interfaces", "Business rules", "Security",
            "Implementation notes", "Acceptance", "Tests", "Definition of done", "Report", "Questions")
DRAFT_SECTIONS = ("Goal", "Scope")
FILENAME = re.compile(r"^(T-\d{3})-[a-z0-9]+(?:-[a-z0-9]+)*\.md$")
BRANCH = re.compile(r"^m(\d+)-t(\d{3})-[a-z0-9]+(?:-[a-z0-9]+)*$")
AC_ROW = re.compile(r"^\|\s*AC-\d+\s*\|(.*)\|\s*`([^`]+)`\s*\|\s*$", re.M)


def parse_front_matter(text):
    if not text.startswith("---\n"):
        return None, text
    end = text.find("\n---\n", 4)
    if end < 0:
        return None, text
    fields = {}
    for line in text[4:end].splitlines():
        line = re.sub(r"\s+#.*$", "", line).rstrip()
        if not line.strip():
            continue
        key, sep, value = line.partition(":")
        if not sep:
            continue
        value = value.strip()
        if value.startswith("[") and value.endswith("]"):
            inner = value[1:-1].strip()
            fields[key.strip()] = [v.strip() for v in inner.split(",") if v.strip()] if inner else []
        else:
            fields[key.strip()] = value.strip('"')
    return fields, text[end + 5:]


def sections(body):
    return set(re.findall(r"^## (.+?)\s*$", body, re.M))


def defined_spec_ids():
    texts = {p: spec_tool.read(p) for p in spec_tool.spec_files()}
    ids = set()
    for text in texts.values():
        for head, num, tail in spec_tool.REQ_DEF.findall(text):
            width = len(num)
            nums = [int(num)] + [int(n) for n in re.findall(r"\d+", tail)]
            if re.search(r"…|\.\.\.|–", tail):
                nums = list(range(nums[0], nums[-1] + 1))
            ids.update(f"{head}{n:0{width}d}" for n in nums)
        ids.update(head + num for head, num in spec_tool.REQ_DEF_INLINE.findall(text))
    journeys = texts[os.path.join(spec_tool.SPEC, "standards", "user-journeys.md")]
    ids.update(re.findall(r"^## (J\d+[ab]?) ", journeys, re.M))
    ids.update(re.findall(r"/ (J\d+[ab]?) ", journeys))
    decisions = texts[os.path.join(spec_tool.SPEC, "decisions.md")]
    ids.update("D-" + n for n in spec_tool.DECISION_DEF.findall(decisions))
    ids.update("OWNER-" + n for n in spec_tool.OWNER_DEF.findall("\n".join(texts.values())))
    for path in texts:
        name = os.path.basename(path)
        if os.path.dirname(path).endswith("adr") and name[:4].isdigit():
            ids.add("ADR-" + name[:4])
    return ids


def load_tasks():
    tasks, problems = {}, []
    for name in sorted(os.listdir(TASKS)):
        if not name.startswith("T-"):
            continue
        path = os.path.join(TASKS, name)
        m = FILENAME.match(name)
        if not m:
            problems.append(f"{name}: file name must be T-nnn-<slug>.md")
            continue
        fields, body = parse_front_matter(spec_tool.read(path))
        if fields is None:
            problems.append(f"{name}: missing front matter")
            continue
        fields["_file"], fields["_body"] = name, body
        if fields.get("id") != m.group(1):
            problems.append(f"{name}: id {fields.get('id')!r} does not match the file name")
        if fields.get("id") in tasks:
            problems.append(f"{name}: duplicate id {fields['id']}")
        tasks[fields.get("id", name)] = fields
    return tasks, problems


def milestone_index(task):
    return MILESTONES.index(task["milestone"]) if task.get("milestone") in MILESTONES else -1


def check_fields(tasks, spec_ids):
    out = []
    for tid, t in tasks.items():
        name = t["_file"]
        for key in REQUIRED_FIELDS:
            if key not in t:
                out.append(f"{name}: missing field `{key}`")
        if t.get("status") not in STATUSES:
            out.append(f"{name}: status {t.get('status')!r} not in {STATUSES}")
        if t.get("kind") not in KINDS:
            out.append(f"{name}: kind {t.get('kind')!r} not in {KINDS}")
        if t.get("milestone") not in MILESTONES:
            out.append(f"{name}: milestone {t.get('milestone')!r} unknown")
        if not str(t.get("budget_lines", "")).isdigit():
            out.append(f"{name}: budget_lines must be an integer")
        b = BRANCH.match(t.get("branch", ""))
        if not b or f"M{int(b.group(1))}" != t.get("milestone") or b.group(2) != tid[2:]:
            out.append(f"{name}: branch {t.get('branch')!r} must be m<N>-t<nnn>-<slug> for this task (BRANCHING.md §2)")
        for req in t.get("requirements", []):
            if req not in spec_ids:
                out.append(f"{name}: requirement {req} is not defined in spec/")
        present = sections(t["_body"])
        needed = DRAFT_SECTIONS if t.get("status") == "draft" else SECTIONS
        for sec in needed:
            if sec not in present:
                out.append(f"{name}: missing section `## {sec}`")
        if t.get("status") != "draft":
            if not AC_ROW.findall(t["_body"]):
                out.append(f"{name}: Acceptance has no `| AC-n | … | `test_name` |` row")
            if not t.get("requirements"):
                out.append(f"{name}: non-draft task lists no requirements")
    return out


def check_graph(tasks):
    out = []
    for tid, t in tasks.items():
        for dep in t.get("depends_on", []):
            if dep not in tasks:
                out.append(f"{t['_file']}: depends on unknown task {dep}")
                continue
            if milestone_index(tasks[dep]) > milestone_index(t):
                out.append(f"{t['_file']}: depends on {dep} from a later milestone")
            if t.get("status") == "todo" and tasks[dep].get("status") == "draft":
                out.append(f"{t['_file']}: is todo but depends on draft task {dep}")
    # cycle detection (iterative DFS, colours)
    colour = {tid: 0 for tid in tasks}
    for start in sorted(tasks):
        if colour[start]:
            continue
        stack = [(start, iter(tasks[start].get("depends_on", [])))]
        colour[start] = 1
        while stack:
            node, deps = stack[-1]
            nxt = next(deps, None)
            if nxt is None:
                colour[node] = 2
                stack.pop()
            elif nxt in colour and colour[nxt] == 1:
                cycle = [n for n, _ in stack] + [nxt]
                out.append("dependency cycle: " + " -> ".join(cycle[cycle.index(nxt):]))
                colour[nxt] = 2
            elif nxt in colour and colour[nxt] == 0:
                colour[nxt] = 1
                stack.append((nxt, iter(tasks[nxt].get("depends_on", []))))
    return out


def ancestors(tasks, tid):
    seen, todo = set(), list(tasks[tid].get("depends_on", []))
    while todo:
        dep = todo.pop()
        if dep in seen or dep not in tasks:
            continue
        seen.add(dep)
        todo.extend(tasks[dep].get("depends_on", []))
    return seen


def check_integration(tasks):
    out = []
    by_ms = {}
    for tid, t in tasks.items():
        by_ms.setdefault(t.get("milestone"), []).append(tid)
    for ms, ids in sorted(by_ms.items(), key=lambda kv: MILESTONES.index(kv[0]) if kv[0] in MILESTONES else 99):
        integ = [i for i in ids if tasks[i].get("kind") == "integration"]
        if len(integ) != 1:
            out.append(f"{ms}: needs exactly one integration task, found {len(integ)}")
            continue
        covered = ancestors(tasks, integ[0])
        missing = sorted(set(ids) - covered - {integ[0]})
        if missing:
            out.append(f"{ms}: integration task {integ[0]} does not depend (transitively) on {', '.join(missing)}")
    return out


def render_index(tasks):
    lines = [
        "# Task index",
        "",
        "Generated by `python3 scripts/tasks_index.py`. Do not edit by hand.",
        "",
    ]
    counts = {}
    for t in tasks.values():
        counts[t.get("status")] = counts.get(t.get("status"), 0) + 1
    lines.append("Status: " + ", ".join(f"{s} {counts[s]}" for s in STATUSES if s in counts) + f" (total {len(tasks)})")
    lines.append("")
    by_ms = {}
    for tid in sorted(tasks):
        by_ms.setdefault(tasks[tid].get("milestone"), []).append(tid)
    for ms in MILESTONES:
        if ms not in by_ms:
            continue
        lines += [f"## {ms}", "", "| Task | Title | Kind | Status | Depends on | Requirements |", "|---|---|---|---|---|---|"]
        for tid in by_ms[ms]:
            t = tasks[tid]
            deps = ", ".join(t.get("depends_on", [])) or "—"
            reqs = ", ".join(t.get("requirements", [])) or "—"
            lines.append(f"| [{tid}]({t['_file']}) | {t.get('title', '')} | {t.get('kind', '')} | {t.get('status', '')} | {deps} | {reqs} |")
        lines.append("")
    # requirement → task map (spec index §7 points here)
    req_map = {}
    for tid in sorted(tasks):
        for req in tasks[tid].get("requirements", []):
            req_map.setdefault(req, []).append(tid)
    lines += ["## Requirement → task map", "", "| Requirement | Tasks |", "|---|---|"]
    for req in sorted(req_map):
        lines.append(f"| {req} | {', '.join(req_map[req])} |")
    lines.append("")
    return "\n".join(lines)


def main():
    check = "--check" in sys.argv[1:]
    tasks, problems = load_tasks()
    spec_ids = defined_spec_ids()
    problems += check_fields(tasks, spec_ids)
    problems += check_graph(tasks)
    problems += check_integration(tasks)
    rendered = render_index(tasks)
    if check:
        current = spec_tool.read(INDEX) if os.path.exists(INDEX) else ""
        if current != rendered:
            problems.append("tasks/index.md is stale: run `python3 scripts/tasks_index.py`")
    else:
        with open(INDEX, "w", encoding="utf-8") as fh:
            fh.write(rendered)
    for p in problems:
        print(p)
    print(f"{len(problems)} problem(s) in {len(tasks)} task(s)")
    return 1 if problems else 0


if __name__ == "__main__":
    sys.exit(main())
