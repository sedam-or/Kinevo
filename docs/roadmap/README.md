# Roadmap — How Kinevo execution is organized

Root `TASK.md` is a **slim control plane** only (current phase, gate, next phase, conventions,
links). All detailed roadmap content lives here.

```
docs/roadmap/
├── README.md                              ← this file
├── roadmap.md                             ← baseline + P29–P39 overview
├── rebaseline-2026-08.md                  ← old→new phase mapping + document migration matrix
├── KINEVO_MASTER_EXECUTION_PROGRAM.md     ← CANONICAL master execution program (R0 → P39)
├── active/                                ← the currently authorized phase
│   └── P28-product-experience-closure.md
├── planned/                               ← future phases (P29–P39), detailed at activation
│   └── P29…P39-*.md
└── archive/                               ← historical evidence (NOT authority)
    ├── master-prompts/                    ← superseded execution prompts (verbatim)
    ├── planning-specs/                    ← owner planning inputs (third-party spec, finance revision)
    ├── convergence/                       ← completed convergence registers (PRE_CONVERGENCE_BASELINE,
    │                                        DOCUMENT_DRIFT_TRIAGE)
    └── task-legacy/                       ← verbatim legacy task detail (Phases 0–10, P11–P20
                                             execution records, Phases 21–27, commercial delta,
                                             old P29–P39 plan)
```

Rules: task IDs are immutable (mapping in rebaseline-2026-08.md); archives are never rewritten;
a phase file becomes ACTIVE only by owner authorization; STOP at every phase boundary.
