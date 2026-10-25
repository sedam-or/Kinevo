# Module spec template (every `modules/<key>.md` follows this order)

1. Header: key, kind, depends on, tables owned (`06-domain-model.md` §), prefix of requirement ids
2. Permissions defined (`<module>.<resource>.<action>`) — registry copy is `standards/rbac.md`
3. Policies consumed/owned (keys from `04-policies.md`; never new keys here)
4. Endpoints: method, path, permission, request fields, success response, error codes (registry)
5. Functional requirements: `<AREA>-FR-nn` with Given/When/Then acceptance
6. State machines: every state, transition, guard, success, failure, recovery
7. Data flow for multi-step behaviour: who writes what, in which transaction, which event follows
8. Validation: field kind (from `standards/validation.md`) + bounds per field, server and client
9. Events emitted / consumed; jobs
10. UI states used (keys in `standards/ux-states.md`)
11. Abuse cases (`SEC-AC-nn`) and tests expected
