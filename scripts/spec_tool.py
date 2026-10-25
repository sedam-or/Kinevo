#!/usr/bin/env python3
"""Spec tooling for Kinevo (stdlib only).

Subcommands
  codes   print the error codes referenced in the specs together with the HTTP statuses used next to them
  perms   print the permission keys defined in modules/*.md section 1 (backticked a.b.c keys)
  check   run the consistency checks and exit 1 on any finding:
          file references, decision/owner/ADR ids, requirement ids referenced but never defined,
          error codes vs the registry (code and HTTP status), policy keys vs 04-policies.md §3
          (heuristic: same key family), module permission keys vs standards/rbac.md

Rule M evidence: `python3 scripts/spec_tool.py check` is the mapping proof required by AGENTS.md.
"""
import collections
import glob
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SPEC = os.path.join(ROOT, "spec")


def read(path):
    with open(path, encoding="utf-8") as fh:
        return fh.read()


def spec_files():
    out = []
    for dirpath, _dirs, files in os.walk(SPEC):
        for name in files:
            if name.endswith(".md"):
                out.append(os.path.join(dirpath, name))
    return sorted(out)


CODE_WITH_STATUS = re.compile(r"`([a-z][a-z0-9]*(?:_[a-z0-9]+)*)`\s*(\d{3})\b")


def collect_codes():
    found = collections.defaultdict(lambda: {"statuses": set(), "files": set()})
    for path in spec_files():
        text = read(path)
        for m in CODE_WITH_STATUS.finditer(text):
            code, status = m.group(1), m.group(2)
            if int(status) < 400 or int(status) > 599:
                continue
            found[code]["statuses"].add(status)
            found[code]["files"].add(os.path.relpath(path, SPEC))
    return found


def cmd_codes():
    found = collect_codes()
    print(f"{len(found)} codes")
    for code in sorted(found):
        info = found[code]
        print(f"{code}\t{','.join(sorted(info['statuses']))}\t{';'.join(sorted(info['files']))}")


PERM_KEY = re.compile(r"`([a-z]+\.[a-z_]+(?:\.[a-z_]+)+(?:\.[a-z_]+)?(?:\|[a-z_]+)*)`")


def collect_perms():
    perms = {}
    for path in sorted(glob.glob(os.path.join(SPEC, "modules", "*.md"))):
        text = read(path)
        m = re.search(r"## 1\. Permissions(.*?)\n## ", text, re.S)
        if not m:
            continue
        block = m.group(1)
        for key in PERM_KEY.findall(block):
            prefix, _, rest = key.rpartition(".")
            if "|" in rest:
                head = prefix
                for action in rest.split("|"):
                    perms[f"{head}.{action}"] = os.path.basename(path)[:-3]
            else:
                perms[key] = os.path.basename(path)[:-3]
        # forms like `workspaces.workspace.read`, `.create`, `.update`
        for first in re.findall(r"`([a-z]+\.[a-z_]+)\.([a-z_]+)`((?:,\s*`\.[a-z_]+`)+)", block):
            head, action, tail = first
            perms[f"{head}.{action}"] = os.path.basename(path)[:-3]
            for extra in re.findall(r"`\.([a-z_]+)`", tail):
                perms[f"{head}.{extra}"] = os.path.basename(path)[:-3]
    return perms


def cmd_perms():
    perms = collect_perms()
    print(f"{len(perms)} permission keys")
    for key in sorted(perms):
        print(f"{key}\t{perms[key]}")


PREFIXES = (
    "PRD ARCH MOD POL TPL SET DOM API FE DS PRV THR OPS TST RM DST CLD "
    "ID ADM WS GOAL TASK LAND SCH EXE PRG SYN AI KNW CNV BIL AND "
    "CQ CA VAL SEC AUTH CON UXS RBAC ERR SM FLW"
).split()
REQ_REF = re.compile(r"\b((?:" + "|".join(PREFIXES) + r")(?:-[A-Z][A-Z0-9]*)?-\d+)\b")
_ID = r"((?:" + "|".join(PREFIXES) + r")(?:-[A-Z][A-Z0-9]*)?-)(\d+)"
# an id is defined where it starts a table row, a heading or a list item; a row may define a
# range (`SEC-AC-13…15`, `SEC-AC-16, 17`)
REQ_DEF = re.compile(r"^\s*(?:\|\s*|#+\s*|[-*]\s+)\**" + _ID + r"((?:\s*(?:…|\.\.\.|–|,)\s*\d+)*)", re.M)
# ... or in parentheses inside a heading or a bold lead: `## 11. Startup (ARCH-30)`, `**Outbox (ARCH-10).**`
REQ_DEF_INLINE = re.compile(r"^(?:#+ |\*\*)[^\n]*?\(" + _ID + r"\)", re.M)
DECISION_REF = re.compile(r"\bD-(\d{2})\b")
DECISION_DEF = re.compile(r"^\|\s*D-(\d{2})\s*\|", re.M)
OWNER_REF = re.compile(r"\bOWNER-(\d{2})\b")
OWNER_DEF = re.compile(r"^\|\s*OWNER-(\d{2})\s*\|", re.M)
ADR_REF = re.compile(r"(?<!legacy )\bADR-(\d{4})\b")
MD_REF = re.compile(r"(?<![\w/:.-])(?:spec/)?((?:modules/|standards/|adr/)?[A-Za-z0-9_][A-Za-z0-9_.-]*\.md)\b")
REG_ROW = re.compile(r"^\|\s*`([a-z][a-z0-9_]*)`\s*\|\s*(\d{3})\s*\|", re.M)
HTTP_ERROR_STATUSES = {
    "400", "401", "403", "404", "405", "409", "410", "412", "413", "415", "422", "423", "428", "429",
    "500", "502", "503", "504",
}
# backticked field names that appear next to a status number but are not error codes
NOT_CODES = {"http_status"}
STATUS_THEN_CODE = re.compile(r"\b(\d{3})\s+`([a-z][a-z0-9_]*)`")
POLICY_ROW = re.compile(r"^\|\s*`([a-z][a-z0-9_]*(?:\.[a-z0-9_]+)+)`\s*\|", re.M)
DOTTED = re.compile(r"`([a-z][a-z0-9_]*(?:\.[a-z0-9_]+)+)`")
# repository files that Phases 3 and 5 create; references to them are not broken links
PLANNED_ROOT = {
    "AGENTS.md", "CLAUDE.md", "README.md", "WORKFLOW.md", "COMMIT_CONVENTION.md", "BRANCHING.md",
    "CONTRIBUTING.md", "CODE_OF_CONDUCT.md", "SECURITY.md", "GOVERNANCE.md", "MAINTAINERS.md",
    "TRADEMARK.md", "RELEASING.md", "ROADMAP.md", "CHANGELOG.md",
}
# files whose dotted tokens are UI message keys, not policy keys
MESSAGE_KEY_FILES = (os.path.join("standards", "ux-states.md"), os.path.join("standards", "user-journeys.md"))
# recurring job names that share a family with policy keys
JOB_KEYS = {"sync.ledger.prune"}
FILE_EXT = (".md", ".rs", ".ts", ".tsx", ".json", ".toml", ".yaml", ".yml", ".sql", ".php", ".js")


def rel(path):
    return os.path.relpath(path, SPEC)


def check_file_refs(texts):
    out = []
    for path, text in texts.items():
        for ref in sorted(set(MD_REF.findall(text))):
            if ref in PLANNED_ROOT:
                continue
            candidates = [
                os.path.join(SPEC, ref),
                os.path.join(os.path.dirname(path), ref),
                os.path.join(SPEC, "modules", ref),
                os.path.join(SPEC, "standards", ref),
                os.path.join(ROOT, ref),
            ]
            if not any(os.path.isfile(c) for c in candidates):
                out.append(f"file-ref\t{rel(path)}\t{ref} does not exist")
    return out


def check_ids(texts, ref_re, def_re, defs_text, kind):
    defined = set(def_re.findall(defs_text))
    out = []
    for path, text in texts.items():
        for ident in sorted(set(ref_re.findall(text)) - defined):
            out.append(f"{kind}\t{rel(path)}\t{ident} is not defined")
    return out


def check_adrs(texts):
    present = {os.path.basename(p)[:4] for p in glob.glob(os.path.join(SPEC, "adr", "[0-9][0-9][0-9][0-9]-*.md"))}
    out = []
    for path, text in texts.items():
        for num in sorted(set(ADR_REF.findall(text)) - present):
            out.append(f"adr\t{rel(path)}\tADR-{num} has no file in adr/")
    return out


def check_requirements(texts):
    defined = set()
    for text in texts.values():
        for head, num, tail in REQ_DEF.findall(text):
            width = len(num)
            nums = [int(num)] + [int(n) for n in re.findall(r"\d+", tail)]
            if re.search(r"…|\.\.\.|–", tail):  # range: first…last
                nums = list(range(nums[0], nums[-1] + 1))
            defined.update(f"{head}{n:0{width}d}" for n in nums)
        defined.update(head + num for head, num in REQ_DEF_INLINE.findall(text))
    out = []
    for path, text in texts.items():
        for ident in sorted(set(REQ_REF.findall(text)) - defined):
            out.append(f"req-id\t{rel(path)}\t{ident} is referenced but never defined")
    return out


def check_error_codes(texts):
    registry_path = os.path.join(SPEC, "standards", "error-handling.md")
    registry_text = texts[registry_path]
    registry = dict(REG_ROW.findall(registry_text))
    # §2.2 field-level codes travel inside a 422 `validation_failed`
    field_level = re.search(r"### 2\.2 (.*?)\n### ", registry_text, re.S)
    if field_level:
        for code in re.findall(r"`([a-z][a-z0-9_]*)`", field_level.group(1)):
            registry.setdefault(code, "422")
    out = []
    for path, text in texts.items():
        uses = [(c, s) for c, s in CODE_WITH_STATUS.findall(text)]
        uses += [(c, s) for s, c in STATUS_THEN_CODE.findall(text)]
        for code, status in sorted(set(uses)):
            if status not in HTTP_ERROR_STATUSES or code in NOT_CODES:
                continue
            if code not in registry:
                out.append(f"error-code\t{rel(path)}\t`{code}` ({status}) is not in the registry")
            elif registry[code] != status:
                out.append(f"error-code\t{rel(path)}\t`{code}` used with {status}, registry says {registry[code]}")
    return out


def check_policies(texts, perms):
    text = texts[os.path.join(SPEC, "04-policies.md")]
    m = re.search(r"## 3\. Registry(.*?)\n## 4\.", text, re.S)
    keys = set(POLICY_ROW.findall(m.group(1))) if m else set()
    # only multi-segment families: a bare module prefix (`ai.`, `tasks.`) is shared with
    # message keys, job names and columns
    parents = {k.rpartition(".")[0] for k in keys if k.count(".") >= 2}
    out = []
    for path, body in texts.items():
        if path.endswith(MESSAGE_KEY_FILES):
            continue
        for token in sorted(set(DOTTED.findall(body))):
            if token in keys or token in perms or token in JOB_KEYS or token.endswith(FILE_EXT):
                continue
            # heuristic: a token in an existing key family (same parent) must be a declared key
            if token.rpartition(".")[0] in parents:
                out.append(f"policy\t{rel(path)}\t`{token}` looks like a policy key but is not in 04-policies.md §3")
    return out


def check_perms_registered(texts, perms):
    rbac = texts[os.path.join(SPEC, "standards", "rbac.md")]
    return [f"perm\tmodules/{mod}.md\t`{key}` is missing from standards/rbac.md"
            for key, mod in sorted(perms.items()) if f"`{key}`" not in rbac]


def cmd_check():
    texts = {p: read(p) for p in spec_files()}
    perms = collect_perms()
    decisions = texts[os.path.join(SPEC, "decisions.md")]
    owners = "\n".join(texts.values())
    findings = []
    findings += check_file_refs(texts)
    findings += check_ids(texts, DECISION_REF, DECISION_DEF, decisions, "decision")
    findings += check_ids(texts, OWNER_REF, OWNER_DEF, owners, "owner")
    findings += check_adrs(texts)
    findings += check_requirements(texts)
    findings += check_error_codes(texts)
    findings += check_policies(texts, perms)
    findings += check_perms_registered(texts, perms)
    for line in findings:
        print(line)
    print(f"{len(findings)} finding(s) in {len(texts)} spec files")
    return 1 if findings else 0


def main():
    if len(sys.argv) < 2:
        print(__doc__)
        return 2
    cmd = sys.argv[1]
    if cmd == "codes":
        cmd_codes()
        return 0
    if cmd == "perms":
        cmd_perms()
        return 0
    if cmd == "check":
        return cmd_check()
    print(__doc__)
    return 2


if __name__ == "__main__":
    sys.exit(main())
