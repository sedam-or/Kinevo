# Third-Party License Ledger

### Purpose
Track every dependency or copied source component that creates redistribution obligations.

### Required columns
| Component | Version/Commit | License | Source | Modified? | Vendored? | Notice Required? | Notes |
|---|---|---|---|---|---|---|---|
| Excalidraw | pin at integration | MIT | GitHub/npm | TBD | TBD | Yes | Canvas engine |
| Tiptap | pin at integration | MIT/open-source package licensing | GitHub/npm | TBD | No | Check exact package | Editor engine |
| ProseMirror dependencies | lockfile | inspect each | npm | No | No | Check | Tiptap dependency chain |
| Ollama | deployment version | inspect distribution terms | GitHub | No | No | Check | Runtime tool |

### Rules
- Never list a dependency as license-approved without checking the exact package/version.
- Do not copy source code from AGPL/GPL projects without explicit review.
- Preserve notices required by component licenses.
- Re-run license review when upgrading dependencies or vendoring source.

---

