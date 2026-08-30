# Software Requirements Specification (SRS)
# Kinevo

**Status:** Architecture-Frozen / Implementable Baseline / Single Source of Truth

**SRS Version:** 2.0.0

**Baseline PRD:** Kinevo PRD v2.1 — *Final — Dynamic Capacity Edition*

**PRD Date:** 12 Agustus 2026

**SRS Supersedes:** Kinevo SRS v1.0.0

**Product Type:** Personal Productivity & Life Management System, Single-User, Open-Source

**Primary Deployment Profile:** Self-hosted Linux container deployment (Oracle Cloud Always Free is a supported personal deployment profile; the software SHALL remain infrastructure-portable).

**Normative Language:** **MUST/SHALL** = mandatory implementation requirement; **SHOULD** = high-priority recommendation which may be deferred only with explicit scope approval; **MAY/COULD** = optional; **WON'T** = explicitly excluded from this release.

> **Single Source of Truth:** This document is the normative requirement baseline for implementation. Code, schema, API, UX, test cases, scheduler policy, and deployment configuration MUST remain traceable to the IDs in this SRS. When an implementation artifact conflicts with this SRS, the SRS governs until an approved version change is recorded.

---

## Document Control

| Item | Value |
|---|---|
| Product | Kinevo |
| SRS ID | LSOS-SRS-001 |
| Version | 2.0.0 |
| Supersedes | 1.0.0 |
| Baseline PRD | Kinevo PRD v2.1 |
| Requirement Owner | Product/System Owner |
| Primary User | Single owner/user |
| Architecture Style | Modular monolith |
| Frontend | Vue 3 + TypeScript + Inertia.js + Vite + Pinia |
| Backend | Laravel modular monolith |
| Database | PostgreSQL |
| Offline | Service Worker + IndexedDB + Cache Storage |
| Queue | Laravel Queue; Redis optional |
| Scheduler Runtime | Laravel Scheduler + queue workers |
| Canvas Engine | Excalidraw behind a Kinevo Canvas Adapter |
| Note Editor | Tiptap behind a Kinevo Knowledge/Editor boundary |
| AI Runtime | Provider abstraction; Ollama supported; external providers optional |
| Storage | S3-compatible object storage |
| Reverse Proxy / TLS | Nginx + Cloudflare-compatible edge |
| Containerization | Docker / Docker Compose compatible |
| Source of truth | This SRS |

## 0.1 Scope of this Revision

SRS 2.0 retains the PRD functional baseline and resolves the principal architecture decisions left open in SRS 1.0. The revision adds first-class concepts for long-horizon outcomes, milestones, knowledge, canvas, adaptive context, AI-assisted proposals, and a strict separation between deterministic scheduling constraints and optional soft optimization signals.

## 0.2 Revision Principles

1. Existing PRD intent SHALL be preserved unless an explicit architectural refinement is documented below.
2. New capabilities SHALL be additive unless they require a change to an existing invariant.
3. Open-source engines SHALL remain bounded components; they SHALL NOT become owners of Kinevo business semantics.
4. AI SHALL remain advisory and schema-constrained; the deterministic domain model remains authoritative.
5. Self-hosting SHALL be supported without making Oracle Cloud a hard dependency.
6. Development-time models (including local coding models) are tooling, not product runtime requirements.

## 0.3 Requirement Precedence

When rules conflict, precedence is:

1. Legal/security constraints
2. Data integrity and temporal invariants
3. Hard Landscape constraints
4. Locked task constraints for automation
5. Explicit user commitments / Sacred Anchor
6. Goal/milestone deadline feasibility
7. Priority tier
8. Capacity and safety reserve
9. Soft optimization signals (energy, cognitive fit, flow-fit, context switching)
10. Ordinary backlog preference

Automation MUST never resolve a lower-priority rule by silently violating a higher-priority rule.

---

# 1. PENDAHULUAN

## 1.1 Tujuan Dokumen

This SRS defines the complete implementation baseline for Kinevo, including product behavior, domain semantics, architecture, persistence, APIs, offline synchronization, scheduling, adaptive productivity behavior, knowledge/canvas integration, AI boundaries, test strategy, and deployment constraints.

## 1.2 Product Vision

Kinevo is a personal operating system for planning, executing, learning from, and adapting meaningful work. The system connects long-horizon outcomes to concrete daily execution while protecting time, human capacity, cognitive bandwidth, and historical integrity.

The product is intentionally **not** an autonomous productivity authority. It SHALL help the user make better decisions, preserve explicit commitments, and provide explainable proposals rather than silently taking destructive scheduling actions.

## 1.3 In-Scope Capability Domains

- Goal and outcome management.
- Milestone decomposition and deadline planning.
- Program and workload management.
- Task/subtask lifecycle.
- Timeline, week, and calendar views.
- Hard Landscape and recurring constraints.
- Deterministic constraint-based scheduling.
- Dynamic rescheduling proposals with confirmation.
- Capacity measurement and adaptive capacity feedback.
- Focus sessions, recharge, pauses, and recovery.
- Activity/progress/event logging.
- Personal knowledge layer: notes, links, references, attachments.
- Visual thinking layer: canvas/diagram documents.
- Offline Today and queued mutations.
- PDF/iCal import/export where specified by priority.
- Optional local AI assistance through a provider boundary.
- Analytics and explainable productivity feedback.

## 1.4 Explicitly Out of Scope for MVP

- Multi-user collaboration.
- Shared calendars.
- Native mobile application.
- Payment/billing.
- OAuth Google as a hard dependency.
- AI-controlled autonomous scheduling.
- Mandatory external AI API.
- Mandatory local LLM runtime on production server.
- Field-level application encryption.
- Distributed microservice deployment.
- Kafka/event-bus infrastructure.
- Kubernetes.

## 1.5 Definitions

| Term | Normative Definition |
|---|---|
| Goal | A desired outcome with an explicit time horizon and/or deadline. |
| Milestone | A meaningful intermediate state used to prove progress toward a Goal. |
| Program | A sustained workstream that produces tasks and/or contributes to one or more Goals. |
| Task | A directly executable unit of work. |
| Subtask | A single-level child checklist item of a Task. |
| Hard Landscape | A fixed temporal block that automation SHALL NOT overlap. |
| Dynamic Empty Slot | A free interval of at least 15 minutes that is eligible for scheduled work. |
| Sacred Anchor | A daily 25-minute study commitment placed in the first qualifying slot at/after 06:00 and protected from automation. |
| Effective Capacity | Observed productive capacity derived from eligible recent periods. |
| Schedule Candidate | A generated proposal that has not yet become the authoritative schedule. |
| Schedule Version | Monotonic version identifying the canonical schedule state. |
| Knowledge Item | A note, reference, document, attachment, or related information object. |
| Canvas | A visual knowledge/diagram document backed by a canvas engine such as Excalidraw. |
| Adaptive Context | Optional user/task signals used only as soft optimization inputs. |
| AI Proposal | Structured output from an AI provider awaiting validation and/or user approval. |
| Offline Operation | A client mutation accepted locally while the authoritative server cannot currently be reached. |


# 3. KEBUTUHAN FUNGSIONAL (FR)

## 3.0 Konvensi Umum FR

Setiap FR di bawah memiliki: kode, nama, priority, requirement `shall`, actor, preconditions, postconditions, normal flow, alternative flow, exception flow, business rules, acceptance criteria, dan traceability.

### Global validation rules

- `title` task: 1–200 karakter setelah trim.
- `duration_minutes`: integer positif; batas maksimum konfigurasi produk yang rasional adalah 24 jam per task session; task lebih besar harus dipecah.
- Timestamp harus timezone-aware.
- Semua write endpoint harus idempotent pada retry menggunakan idempotency key atau operation UUID.
- Semua mutation wajib memeriksa ownership dan state transition.

---

## FR-01 — Timeline 24 Jam

**Prioritas:** Must-Have

**Deskripsi:** Sistem SHALL menampilkan timeline 24 jam untuk tanggal terpilih, termasuk Hard Landscape, scheduled tasks, Recharge, buffer, dan empty slots yang memenuhi aturan durasi.

**Aktor:** User.

**Preconditions:** User authenticated; tanggal valid.

**Postconditions:** Timeline ter-render sesuai schedule database; conflict/overload state teridentifikasi.

**Normal Flow:**
1. User membuka Today.
2. Client meminta schedule tanggal tersebut.
3. Server memuat hard landscape, task assignments, pauses, dan generated slots.
4. Sistem menyusun interval temporal tanpa overlap yang tidak sah.
5. UI menampilkan event chronologically.
6. Empty slot ≥15 menit ditampilkan sebagai fillable slot.

**Alternative Flows:** Tanggal dapat berpindah ke hari lain dari navigasi; bila offline, gunakan cached Today data.

**Exception Flows:** Jika data tidak dapat dimuat dan cache tidak tersedia, tampilkan error non-destructive dan retry action.

**Business Rules:** Slot <15 menit menjadi buffer; Hard Landscape tidak dapat diisi task; task yang overlap ditandai conflict.

**Acceptance Criteria:**
- **Given** satu hari berisi Hard Landscape dan interval kosong 20 menit, **When** Today dibuka, **Then** interval 20 menit ditampilkan sebagai empty slot.
- **Given** interval kosong 10 menit, **When** timeline dirender, **Then** interval tidak ditampilkan sebagai empty slot.

**Traceability:** PRD FR-01, FR-02; US-03, US-04, US-09.

---

## FR-02 — Slot Kosong Dinamis

**Prioritas:** Must-Have

**Deskripsi:** Sistem SHALL menghitung seluruh interval bebas di antara Hard Landscape dan event non-overlap, hanya mengekspos interval ≥15 menit sebagai Dynamic Empty Slot.

**Aktor:** Scheduling service / User-triggered recalculation.

**Preconditions:** Event calendar untuk tanggal tersedia.

**Postconditions:** Set slot dapat diisi konsisten dengan timeline.

**Normal Flow:** Sistem mengurutkan interval, menghitung gap, mengecualikan gap <15 menit, lalu menghasilkan slot dengan start/end/duration.

**Alternative Flows:** Jika task dipindahkan, slot dihitung ulang.

**Exception Flows:** Overlapping source events menyebabkan conflict state; engine tidak boleh menganggap waktu overlap sebagai available.

**Business Rules:** Durasi slot = end-start; boundary inclusive/exclusive mengikuti interval `[start,end)`.

**Acceptance Criteria:**
- **Given** gap 25 menit, **When** slot engine berjalan, **Then** satu slot 25 menit tersedia.
- **Given** gap 14 menit, **When** slot engine berjalan, **Then** tidak ada slot fillable.

**Traceability:** PRD FR-02; AC-05; US-02, US-03.

---

## FR-03 — Quick Capture

**Prioritas:** Must-Have

**Deskripsi:** Sistem SHALL menerima capture task dengan judul, priority, optional program/goal link, dan duration lalu menempatkannya atau menawarkan tiga strategi penanganan slot penuh.

**Aktor:** User.

**Preconditions:** User berada di aplikasi; title dan priority valid.

**Postconditions:** Task tersimpan tanpa hilang; task ditempatkan, ditukar, atau dijadwalkan kemudian.

**Normal Flow:**
1. User menekan `+`.
2. User mengisi title, priority, optional program/goal, optional duration.
3. Default duration diterapkan: Cepat=15, Sedang=45, Berat=90 menit.
4. Engine mencari slot ≥ duration.
5. Jika ada, task ditempatkan pada slot yang sesuai.
6. Jika tidak ada, UI menampilkan Manual Swap, Auto Swap, Schedule Later.

**Alternative Flows:** Offline create masuk mutation queue dan client-local temporary ID.

**Exception Flows:** Duplicate operation retry tidak boleh membuat task ganda; invalid duration ditolak; linked program inactive/dropped ditolak atau diminta unlink.

**Business Rules:** Auto Swap hanya boleh memindahkan task unlocked; candidate dengan priority terendah didahulukan, deadline terjauh sebagai tie-breaker.

**Acceptance Criteria:**
- **Given** seluruh slot penuh, **When** Quick Capture dibuat, **Then** tiga opsi tersebut tampil.
- **Given** Auto Swap dipilih, **When** ada kandidat unlocked, **Then** candidate dipindah ke hari berikutnya dan task baru ditempatkan.
- **Given** tidak ada candidate aman, **Then** task tidak dihapus dan user diminta memilih Schedule Later/manual.

**Traceability:** PRD FR-03; AC-01; US-02.

---

## FR-04 — Sacred Anchor Multi-Track

**Prioritas:** Must-Have

**Deskripsi:** Sistem SHALL membuat atau mempertahankan Sacred Anchor 25 menit pada empty slot pertama ≥25 menit setelah 06:00, menguncinya, menyediakan mode TryHackMe/Codedex/English, dan mencatat XP saat selesai.

**Aktor:** Scheduler; User.

**Preconditions:** Hari aktif; tersedia slot ≥25 menit setelah 06:00.

**Postconditions:** Anchor terjadwal dan locked atau tercatat tidak dapat ditempatkan bila tidak ada slot valid.

**Normal Flow:** Cari slot pertama valid → create/update Sacred Anchor → set `locked=true` → assign study mode → Pomodoro 25 menit → completion log/XP.

**Alternative Flows:** Bila tidak ada slot valid, UI memberi status anchor unresolved tanpa memaksa overlap.

**Exception Flows:** Engine tidak boleh overwrite Hard Landscape atau memindahkan task locked.

**Business Rules:** Sacred Anchor locked terhadap auto engine tetapi manual move tetap diperbolehkan; single anchor per day per active track.

**Acceptance Criteria:**
- **Given** tersedia slot 30 menit pada 07:00, **When** scheduler berjalan, **Then** anchor dibuat 07:00–07:25 dan locked.

**Traceability:** PRD FR-04; US-03.

---

## FR-05 — Recharge Timer

**Prioritas:** Must-Have

**Deskripsi:** Sistem SHALL menampilkan Recharge Timer 15 menit setelah dua sesi Pomodoro selesai dan mencatat durasi sebagai Recharge.

**Aktor:** User; timer service.

**Preconditions:** Dua sesi Pomodoro selesai dalam konteks hari/session yang relevan.

**Postconditions:** Recharge interval tercatat; Work-Life Ratio memasukkan durasi tersebut.

**Normal Flow:** Complete session → increment count → if count=2 show CTA → start timer → persist completion.

**Alternative Flows:** User dapat menunda/mengakhiri timer secara manual; durasi aktual yang tercatat adalah durasi tracked, bukan durasi nominal jika sistem memiliki start/stop semantics.

**Exception Flows:** Refresh/browser close tidak boleh menghilangkan started timer; timer state dihitung dari persisted timestamps.

**Business Rules:** Recharge Timer termasuk Recharge, bukan Productive Time.

**Acceptance Criteria:**
- **Given** dua Pomodoro selesai, **When** second completion recorded, **Then** CTA Recharge 15 menit muncul.

**Traceability:** PRD FR-05; US-03.

---

## FR-06 — Social Anchor

**Prioritas:** Should-Have

**Deskripsi:** Sistem SHALL memungkinkan user membuat task sosial manual dan menampilkan reminder mingguan pada Minggu sore.

**Aktor:** User; notification job.

**Preconditions:** User memiliki akses Today/Week.

**Postconditions:** Social task tersimpan jika dibuat; reminder dapat tampil.

**Normal Flow:** User create task → assign optional social category → schedule → Sunday reminder generated.

**Alternative Flows:** User dismiss/snooze reminder.

**Exception Flows:** Notification job failure dicatat tetapi tidak mengubah task.

**Business Rules:** Sistem tidak boleh membuat task sosial otomatis.

**Acceptance Criteria:** **Given** reminder day/time tercapai, **When** user membuka app, **Then** in-app reminder ditampilkan dan dapat ditunda.

**Traceability:** PRD FR-06; US-04.

---

## FR-07 — Emergency Pause & Mini Pause

**Prioritas:** Must-Have

**Deskripsi:** Sistem SHALL menyediakan Mini Pause untuk memindahkan task hari berjalan dan Emergency Pause untuk mempertahankan task yang dipilih serta menggeser task lain satu minggu.

**Aktor:** User.

**Preconditions:** Task aktif tersedia.

**Postconditions:** Task dipindahkan sesuai mode; notification state dan analytics state diperbarui.

**Normal Flow Mini:** User click Mini Pause → engine mencari slot berikutnya hari selanjutnya → move all eligible tasks → recalc schedule.

**Normal Flow Emergency:** User click Emergency → tampilkan task minggu → user select keep → system shifts unchecked tasks +1 week → disable notifications → mark week recovery state → analytics grey.

**Alternative Flows:** User cancel sebelum commit.

**Exception Flows:** Locked tasks yang tidak dipilih untuk dipertahankan tidak boleh dipindahkan otomatis bila policy lock berlaku; pada konflik, task diberi conflict flag.

**Business Rules:** Kedua mode dihitung Recharge. Emergency Pause tidak menghapus historical activity.

**Acceptance Criteria:**
- **Given** user selects one task to keep, **When** Emergency Pause confirmed, **Then** all other eligible tasks move +1 week and notifications are suppressed.

**Traceability:** PRD FR-07; AC-03; US-05.

---

## FR-08 — Manual Lock

**Prioritas:** Must-Have

**Deskripsi:** Sistem SHALL menyediakan state lock per task assignment yang menghalangi Auto-Scheduler/Dynamic Rescheduler tetapi tetap memungkinkan user move manual.

**Aktor:** User; scheduling service.

**Preconditions:** Task exists.

**Postconditions:** `locked=true/false` persisted; subsequent auto scheduling obeys state.

**Normal Flow:** User toggles lock → server checks ownership → persist lock.

**Alternative Flows:** Lock dapat diubah sebelum atau sesudah task dijadwalkan.

**Exception Flows:** Scheduler race condition ditangani transactionally.

**Business Rules:** Lock bukan immutable; hanya auto engine yang dilarang memindahkan.

**Acceptance Criteria:** **Given** task locked, **When** Auto-Schedule runs, **Then** assignment tetap dan tidak dipindahkan.

**Traceability:** PRD FR-08; US-09.

---

## FR-09 — Subtask, Partial Completion & Promote Subtask

**Prioritas:** Must-Have

**Deskripsi:** Sistem SHALL mendukung checklist subtask, note per subtask, calculated progress, partial completion, continuation task, dan Promote Subtask.

**Aktor:** User.

**Preconditions:** Task exists dan berada pada current context.

**Postconditions:** Progress konsisten; partial completion menghasilkan continuation; promoted subtask menjadi task mandiri.

**Normal Flow:** Create subtask → complete/uncomplete → calculate progress → on partial completion clone remaining subtask + notes → schedule continuation → mark original `continued`.

**Alternative Flows:** No remaining subtask: completion menjadi complete, bukan continuation. Promote: remove child from original and create new task.

**Exception Flows:** Concurrency pada subtask update menggunakan optimistic concurrency/version number.

**Business Rules:** Progress = completed subtasks / total subtasks ×100. Tidak ada hierarchy lebih dalam dari Subtask.

**Acceptance Criteria:** Mengikuti AC-02 dan AC-07 dari PRD, termasuk retention notes dan default duration 90 menit untuk promoted heavy task.

**Traceability:** PRD FR-09, FR-45; AC-02, AC-07; US-07, US-08, US-13.

---

## FR-10 — Drag-and-Drop Manual

**Prioritas:** Should-Have

**Deskripsi:** Sistem SHALL memungkinkan user memindahkan task antar slot/hari dengan gesture drag-and-drop atau keyboard fallback.

**Aktor:** User.

**Preconditions:** Target date/slot valid.

**Postconditions:** Assignment berubah, conflict dihitung ulang.

**Normal Flow:** Drag → validate target → show drop preview → commit → recalc overload.

**Alternative Flows:** Keyboard move via accessible controls.

**Exception Flows:** Drop ke Hard Landscape ditolak.

**Business Rules:** Manual move boleh memindahkan locked task; server tetap memvalidasi overlap dan ownership.

**Acceptance Criteria:** **Given** target slot cukup, **When** task dipindahkan, **Then** task berada pada target dan daily capacity diperbarui.

**Traceability:** PRD FR-10; US-09.

---

## FR-11 — Kalender 7 Hari

**Prioritas:** Must-Have

**Deskripsi:** Sistem SHALL menampilkan tujuh hari dengan task/event/slot summary yang konsisten dengan Today.

**Aktor:** User.

**Preconditions:** Week date diketahui.

**Postconditions:** Weekly board menampilkan tujuh hari dan status overload/deadline.

**Normal Flow:** load week → fetch summarized schedule → render each day → enable navigation.

**Alternative Flows:** Partial cached week tidak menggantikan Today canonical cache.

**Exception Flows:** Missing day data ditampilkan sebagai unavailable, bukan kosong diam-diam.

**Business Rules:** Minggu mengikuti user locale/configuration; internal storage menggunakan UTC.

**Acceptance Criteria:** **Given** week selected, **When** Week view opened, **Then** 7 calendar dates render lengkap.

**Traceability:** PRD FR-11.

---

## FR-12 — Grafik 4 Pilar Kehidupan

**Prioritas:** Must-Have

**Deskripsi:** Sistem SHALL menghitung dan menampilkan realisasi vs target untuk Karier, Kesehatan, Bahasa, Branding serta Uncategorized.

**Aktor:** User; analytics service.

**Preconditions:** Task dan pillar mapping tersedia.

**Postconditions:** Current period metrics available.

**Normal Flow:** Aggregate completed task duration/XP according to period and target → compute percentages → render bars.

**Alternative Flows:** Uncategorized tetap ditampilkan jika ada data.

**Exception Flows:** Division by zero target menghasilkan N/A atau 0% sesuai configured metric, bukan NaN.

**Business Rules:** Pilar ditentukan melalui program/goal mapping; Uncategorized hanya untuk task tanpa mapping.

**Acceptance Criteria:** **Given** completed duration 5h versus target 10h, **Then** pillar shows 50% realization.

**Traceability:** PRD FR-12; US-04.

---

## FR-13 — Deadline Color Coding

**Prioritas:** Must-Have

**Deskripsi:** Sistem SHALL menandai deadline: merah besok, kuning dalam 3 hari, hijau aman.

**Aktor:** User.

**Preconditions:** Task/goal deadline exists.

**Postconditions:** Badge category calculated at render time.

**Normal Flow:** Compute date delta in user timezone → classify → display color + text for accessibility.

**Alternative Flows:** Overdue deadline gets `overdue` state distinct from the three normal colors.

**Exception Flows:** Invalid deadline cannot be rendered as normal.

**Business Rules:** Color tidak boleh menjadi satu-satunya signal; text/aria label wajib.

**Acceptance Criteria:** Deadline +1 day = red; +2/+3 days = yellow; >3 days = green; past = overdue.

**Traceability:** PRD FR-13.

---

## FR-14 — Overload Detection

**Prioritas:** Must-Have

**Deskripsi:** Sistem SHALL membandingkan total durasi scheduled tasks dengan total Dynamic Empty Slot capacity pada level hari dan minggu.

**Aktor:** System; User.

**Preconditions:** schedule calculable.

**Postconditions:** overload minutes and severity available.

**Normal Flow:** Sum task durations → sum available slot minutes → compare → persist/compute warning.

**Alternative Flows:** Hard Landscape changes trigger recomputation.

**Exception Flows:** Duplicate schedule records tidak boleh double-count; aggregation dilakukan dari canonical assignment IDs.

**Business Rules:** Overload weekly = scheduled task duration > total empty-slot capacity; daily analogously. Unauthorized tasks tidak boleh dipakai.

**Acceptance Criteria:** mengikuti AC-05: 33h vs 30h → +3h overload.

**Traceability:** PRD FR-14; AC-05.

---

## FR-15 — Kalender Bulanan Penuh

**Prioritas:** Should-Have

**Deskripsi:** Sistem SHALL menyediakan monthly grid dengan event/task indicators.

**Aktor:** User.

**Preconditions:** none beyond access.

**Postconditions:** Monthly calendar rendered.

**Normal Flow:** select month → query summary → render days.

**Alternative Flows:** Jump to Today.

**Exception Flows:** Large month query must be paginated/aggregated server-side where needed.

**Business Rules:** Hanya summary level; detail dibuka via day navigation.

**Acceptance Criteria:** **Given** month selected, **Then** semua tanggal tampil dan penanda event penting tersedia.

**Traceability:** PRD FR-15.

---

## FR-16 — Penanda Kalender

**Prioritas:** Should-Have

**Deskripsi:** Sistem SHALL menandai Hard Landscape, scheduled slots berdasarkan pillar, goal deadline, Emergency/Mini Pause.

**Aktor:** System.

**Preconditions:** Calendar data available.

**Postconditions:** Marker set consistent with source state.

**Normal Flow:** aggregate markers by date → deduplicate → render.

**Alternative Flows:** Marker overlap uses stacked/priority representation.

**Exception Flows:** Unknown pillar marker falls back to Uncategorized.

**Business Rules:** Marker semantics must not change based only on UI theme.

**Acceptance Criteria:** Event state changes reflect on next refresh without duplicate markers.

**Traceability:** PRD FR-16.

---

## FR-17 — Navigasi Hari

**Prioritas:** Should-Have

**Deskripsi:** Click date on monthly calendar SHALL navigate to corresponding Today date.

**Aktor:** User.

**Preconditions:** valid date.

**Postconditions:** Today date context updated.

**Normal Flow:** click → route changes → load date schedule.

**Alternative Flows:** Browser back returns previous calendar context.

**Exception Flows:** invalid route date returns 404/invalid date page.

**Business Rules:** No data mutation from navigation.

**Acceptance Criteria:** **When** user clicks day 14, **Then** Today opens for day 14.

**Traceability:** PRD FR-17.

---

## FR-18 — Drag-and-Drop Antar Hari

**Prioritas:** Could-Have

**Deskripsi:** Sistem MAY memindahkan task antar hari dari monthly calendar.

**Aktor:** User.

**Preconditions:** feature enabled.

**Postconditions:** Task assignment date changed.

**Normal Flow:** drag → date preview → validate → commit.

**Alternative Flows:** keyboard move.

**Exception Flows:** invalid date/overlap rules reject with explanation.

**Business Rules:** Manual move permitted, but server remains authoritative.

**Acceptance Criteria:** Task dropped on day with a valid slot is rescheduled there.

**Traceability:** PRD FR-18.

---

## FR-19 — CRUD Yearly Goals

**Prioritas:** Must-Have

**Deskripsi:** Sistem SHALL menyediakan create/read/update/archive lifecycle untuk maksimal 5 Yearly Goals dan menghitung progress dari contribution sources.

**Aktor:** User.

**Preconditions:** authenticated owner.

**Postconditions:** Goal persisted; progress recalculable.

**Normal Flow:** create goal → validate max=5 active → save target/deadline → link programs.

**Alternative Flows:** edit active goal; archive/completion preserving history.

**Exception Flows:** sixth active goal rejected.

**Business Rules:** Progress is derived, not manually overwritten without audit field.

**Acceptance Criteria:** **Given** 5 active yearly goals, **When** user adds sixth, **Then** request rejected with clear limit message.

**Traceability:** PRD FR-19; US-10, US-15.

---

## FR-20 — CRUD Monthly Goals & Auto-Breakdown

**Prioritas:** Must-Have

**Deskripsi:** Sistem SHALL support up to 7 Monthly Goals per month and break estimated workload across 4 weeks based on available buffer capacity.

**Aktor:** User; breakdown service.

**Preconditions:** active month and capacity data.

**Postconditions:** monthly goals stored; weekly breakdown generated and editable.

**Normal Flow:** create goal → define estimated workload → calculate week capacities → allocate proportionally → present draft → user saves/overrides.

**Alternative Flows:** manual override of each week.

**Exception Flows:** no capacity yields unallocated remainder and warning, never hidden overbooking.

**Business Rules:** max 7 active monthly goals per month; override persists as user-managed allocation.

**Acceptance Criteria:** **Given** monthly estimate exists, **When** auto-breakdown runs, **Then** total allocations equal target estimate unless user explicitly overrides/reduces.

**Traceability:** PRD FR-20.

---

## FR-21 — Contribution Matrix

**Prioritas:** Must-Have

**Deskripsi:** Sistem SHALL relate Programs to Yearly Goals with contribution percentage and mechanism Realtime/Event-Based.

**Aktor:** User; analytics service.

**Preconditions:** Program and Yearly Goal exist.

**Postconditions:** Contribution relation active and usable in progress calculation.

**Normal Flow:** select program → select goal → set weight → select mechanism → validate weight → save.

**Alternative Flows:** update weight; deactivate relation.

**Exception Flows:** negative weight rejected; normalized contribution rules apply.

**Business Rules:** If multiple programs contribute to a goal, aggregate logic must be deterministic; weight overflow must produce warning/error according to configured policy.

**Acceptance Criteria:** Task completion under linked program updates goal progress according to weight.

**Traceability:** PRD FR-21; US-06, US-10.

---

## FR-22 — Program Lifecycle Management

**Prioritas:** Must-Have

**Deskripsi:** Sistem SHALL support Aktif, Paused, Completed, Dropped with distinct scheduling and historical contribution behavior.

**Aktor:** User; scheduler.

**Preconditions:** Program exists.

**Postconditions:** Lifecycle transition recorded; schedule recalculated.

**Normal Flow:** User selects status → validate transition → persist → update recurring schedule → recalc capacity → audit event.

**Alternative Flows:** Completed offers 30-second Undo; Dropped preserves historical contribution.

**Exception Flows:** Invalid transition rejected with reason.

**Business Rules:** Completed removes recurring future schedule and adds completed contribution; Dropped removes recurring future schedule, retains historical contributions, reduces forward target load.

**Acceptance Criteria:** **When** program Completed, **Then** recurring future schedule is removed, history remains, and capacity recalculates.

**Traceability:** PRD FR-22; US-06.

---

## FR-23 — Conflict Rescheduler Priority

**Prioritas:** Should-Have

**Deskripsi:** System SHALL use Program/Goal priority tier 1–3 to rank conflict resolution and only auto-move unlocked tasks.

**Aktor:** Scheduler.

**Preconditions:** conflict detected.

**Postconditions:** candidate move proposal generated or task marked conflict.

**Normal Flow:** detect conflict → identify candidate unlocked tasks → rank by tier/deadline → find target slot → propose/execute per engine mode.

**Alternative Flows:** no target slot → keep in place + red conflict.

**Exception Flows:** never delete task; never move locked via auto engine.

**Business Rules:** Tier 1 > 2 > 3; equal tier uses nearest Yearly Goal deadline.

**Acceptance Criteria:** Locked task remains; unlocked lower-priority task is candidate before higher-priority task.

**Traceability:** PRD FR-23, FR-28.

---

## FR-24 — PDF Import

**Prioritas:** Should-Have

**Deskripsi:** Sistem SHALL accept KRS PDF, parse schedule, show preview, and only create Hard Landscape after user confirmation; manual input is mandatory fallback.

**Aktor:** User; import processor.

**Preconditions:** PDF type supported, size under configured limit.

**Postconditions:** Parsed events in staging or imported after confirmation.

**Normal Flow:** upload → validate → parse → show confidence/result → preview → user confirm → persist Hard Landscape.

**Alternative Flows:** parsing partial success; user edits rows in preview.

**Exception Flows:** parse failure → manual entry mode; malformed file rejected.

**Business Rules:** Import must not silently overwrite existing schedule.

**Acceptance Criteria:** **Given** valid KRS PDF, **When** import completes, **Then** preview is visible before persistence.

**Traceability:** PRD FR-24; US-01.

---

## FR-25 — Override Reschedule

**Prioritas:** Must-Have

**Deskripsi:** Sistem SHALL support permanent shift and one-time exception for external schedule changes.

**Aktor:** User.

**Preconditions:** Source hard landscape recurring series exists.

**Postconditions:** Effective schedule reflects override without losing source history.

**Normal Flow:** choose series → choose Shift Permanen / Exception → enter date/time → validate → save → trigger Dynamic Rescheduler if enabled.

**Alternative Flows:** multiple exceptions.

**Exception Flows:** end date before start rejected; overlap with another hard landscape flagged.

**Business Rules:** Permanent shift deactivates original schedule for selected period; one-time exception removes only selected occurrence.

**Acceptance Criteria:** **When** permanent shift is saved, **Then** original recurring occurrence becomes inactive for applicable range.

**Traceability:** PRD FR-25; US-06.

---

## FR-26 — Program Intake

**Prioritas:** Must-Have

**Deskripsi:** Sistem SHALL support Program workload types Structured, Range, Flexible/No Target.

**Aktor:** User.

**Preconditions:** valid program name and category.

**Postconditions:** Program created and capacity effect derived according to workload type.

**Normal Flow:** create program → choose workload type → enter required values → validate → save → update capacity.

**Alternative Flows:** Range with min/max values; Flexible with no weekly capacity.

**Exception Flows:** invalid range min>max rejected.

**Business Rules:** Structured and Range affect capacity; Flexible does not affect weekly capacity until its tasks are scheduled, then contributes to overload.

**Acceptance Criteria:** Flexible program with 0 weekly target does not inflate weekly capacity; scheduled task can still trigger daily overload.

**Traceability:** PRD FR-26; US-06, US-15.

---

## FR-27 — Auto-Scheduling Engine

**Prioritas:** Should-Have

**Deskripsi:** Scheduler SHALL generate a weekly draft using constraints: Sacred Anchor first, priority tiers, duration fit, no hard-landscape collision, no locked-task moves, daily load balance, and 30% recharge/buffer reserve.

**Aktor:** User / weekly scheduler job.

**Preconditions:** week exists, active programs/goals/tasks available.

**Postconditions:** Draft schedule created with explainable assignment decisions.

**Normal Flow:**
1. Acquire schedule-generation lock.
2. Snapshot current constraints.
3. Place Sacred Anchors.
4. Rank active programs.
5. Allocate tasks into compatible slots.
6. Enforce daily and 30% reserve constraints.
7. Run Effective Capacity feedback.
8. Persist draft with version.
9. Release lock and notify user.

**Alternative Flows:** user executes Sync Now; job can partially complete and resume if idempotent.

**Exception Flows:** timeout → retain last stable schedule and mark job failed; no destructive cleanup.

**Business Rules:** Generated schedule is a draft until user approval/edit. Effective Capacity <80% reduces load proportionally; >90% with no burnout signal may offer Boost Mode/backlog fill.

**Acceptance Criteria:** AC-09 applies: 60% recent realization results in materially reduced next-week load and recommendation.

**Traceability:** PRD FR-27, FR-49; US-15.

---

## FR-28 — Dynamic Rescheduler

**Prioritas:** Should-Have

**Deskripsi:** System SHALL produce a preview diff when Hard Landscape or relevant constraint changes, require user confirmation, and never move locked tasks automatically.

**Aktor:** User; rescheduler service.

**Preconditions:** Existing schedule + new constraint.

**Postconditions:** On confirmation, assignments changed atomically; on cancel, no schedule mutation.

**Normal Flow:** change detected → generate candidate plan → calculate diff → show task movements → user Apply/Cancel → commit if Apply.

**Alternative Flows:** conflict remains unresolved and tasks flagged red.

**Exception Flows:** concurrent schedule edit causes version conflict; require reload/retry.

**Business Rules:** No automatic destructive scheduling; conflict is visible state.

**Acceptance Criteria:** **Given** lecturer reschedules, **When** Dynamic Rescheduler runs, **Then** preview lists impacted tasks and Apply/Cancel is required.

**Traceability:** PRD FR-28; US-06.

---

## FR-29 — Sync Now

**Prioritas:** Should-Have

**Deskripsi:** User SHALL be able to manually trigger schedule synchronization at any time.

**Aktor:** User.

**Preconditions:** User online; scheduler not already locked for same scope.

**Postconditions:** New draft or result status available.

**Normal Flow:** click Sync Now → enqueue/start job → show progress → show draft/result.

**Alternative Flows:** If same job running, UI attaches to existing run instead of duplicate execution.

**Exception Flows:** job failure provides retry.

**Business Rules:** Sync Now is non-destructive until approval where applicable.

**Acceptance Criteria:** **When** Sync Now clicked, **Then** scheduler executes or reports in-progress state without duplicate jobs.

**Traceability:** PRD FR-29.

---

## FR-30 — iCal Integration

**Prioritas:** Could-Have

**Deskripsi:** System MAY export iCal URL and import `.ics` or public holiday calendars.

**Aktor:** User; external calendar client.

**Preconditions:** feature enabled; valid authorization token for private feed.

**Postconditions:** Calendar events can be exchanged using iCalendar format.

**Normal Flow:** generate feed token → external client subscribes → server outputs `.ics`.

**Alternative Flows:** one-time `.ics` import staging.

**Exception Flows:** malformed `.ics` rejected with per-event error report.

**Business Rules:** Export feed must not expose unrelated private metadata.

**Acceptance Criteria:** Generated feed can be parsed by a standards-compatible client.

**Traceability:** PRD FR-30.

---

## FR-31 — Annual Heatmap

**Prioritas:** Should-Have

**Deskripsi:** System SHALL display annual activity heatmap with pillar filtering.

**Aktor:** User.

**Preconditions:** activity logs exist.

**Postconditions:** daily intensity calculated from configured activity metric.

**Normal Flow:** aggregate daily completion/recharge → map to intensity → filter pillar.

**Alternative Flows:** all pillars view.

**Exception Flows:** missing date shown as zero/no data with accessible label.

**Business Rules:** Metric definition must be stable within a report version.

**Acceptance Criteria:** Filtering by pillar changes heatmap dataset without mutating logs.

**Traceability:** PRD FR-31.

---

## FR-32 — Monthly Realization vs Target

**Prioritas:** Should-Have

**Deskripsi:** System SHALL render monthly bar/line comparison of realization vs target.

**Aktor:** User; analytics service.

**Preconditions:** targets and actuals available.

**Postconditions:** report data calculated reproducibly.

**Normal Flow:** query month → aggregate target/actual → calculate percentage → render.

**Alternative Flows:** select specific pillar/program.

**Exception Flows:** missing target displayed as N/A.

**Business Rules:** Actuals use completed tracked task/session duration according to product metric definition.

**Acceptance Criteria:** Actual 8h/10h target = 80%.

**Traceability:** PRD FR-32.

---

## FR-33 — Achievement Badges

**Prioritas:** Could-Have

**Deskripsi:** System MAY award badges for milestone patterns such as 100% Monthly Goal and balanced Work-Life Ratio.

**Aktor:** Analytics/badge service.

**Preconditions:** feature enabled.

**Postconditions:** immutable badge award record exists.

**Normal Flow:** calculate achievement → evaluate rule → award if first occurrence.

**Alternative Flows:** retroactive award after import.

**Exception Flows:** duplicate evaluation must not duplicate badge.

**Business Rules:** Badge criteria versioned.

**Acceptance Criteria:** same achievement evaluated twice yields one badge record.

**Traceability:** PRD FR-33.

---

## FR-34 — Daily Activity Log

**Prioritas:** Must-Have

**Deskripsi:** System SHALL record completion events for tasks/subtasks and support detailed inspection plus JSON/CSV export.

**Aktor:** User; event logger.

**Preconditions:** tracked action occurred.

**Postconditions:** immutable activity record persisted.

**Normal Flow:** state change → append log → update aggregates asynchronously or transactionally → expose in Logs.

**Alternative Flows:** offline event queued and later synchronized.

**Exception Flows:** duplicate event IDs ignored.

**Business Rules:** Log is append-only; correction occurs by compensating event rather than destructive edit.

**Acceptance Criteria:** completing a task creates one activity event; export includes task/subtask/note references allowed by privacy policy.

**Traceability:** PRD FR-34; US-04.

---

## FR-35 — End-of-Day Prompt

**Prioritas:** Should-Have

**Deskripsi:** System SHALL prompt user when untouched tasks exist at end of day.

**Aktor:** Scheduler / notification job.

**Preconditions:** task exists and is neither completed nor partial.

**Postconditions:** notification state recorded.

**Normal Flow:** end-of-day scan → identify untouched tasks → create prompt.

**Alternative Flows:** no untouched task => no prompt.

**Exception Flows:** in-app unavailable => event remains pending until app opens.

**Business Rules:** FR-47 is authoritative for 21:00 reconciliation; FR-35 is the UX prompt layer where applicable.

**Acceptance Criteria:** untouched task triggers prompt, already complete tasks do not.

**Traceability:** PRD FR-35, FR-47; US-11.

---

## FR-36 — Holiday Detection

**Prioritas:** Should-Have

**Deskripsi:** System SHALL attempt semi-automatic holiday detection from KRS/calendar patterns and require user confirmation; manual date range is always supported.

**Aktor:** Detection service; User.

**Preconditions:** calendar data exists.

**Postconditions:** proposed or confirmed Break Mode period exists.

**Normal Flow:** analyze academic schedule → propose period → user confirm/edit.

**Alternative Flows:** manual input.

**Exception Flows:** low confidence => manual confirmation mandatory.

**Business Rules:** Detection never activates Break Mode without confirmation.

**Acceptance Criteria:** failed detection results in manual flow, not silent failure.

**Traceability:** PRD FR-36; US-10.

---

## FR-37 — Boost Mode Setup

**Prioritas:** Should-Have

**Deskripsi:** System SHALL present holiday boost target controls with recommendations and safe cap 70% capacity.

**Aktor:** User; analytics service.

**Preconditions:** Break Mode confirmed.

**Postconditions:** boost targets saved with validity period.

**Normal Flow:** show current targets → compute recommendations → user adjusts sliders → validate against cap → save.

**Alternative Flows:** user keeps normal targets.

**Exception Flows:** proposed target >70% rejected or capped with explicit warning.

**Business Rules:** 70% is a safety cap for boost scheduling during break mode.

**Acceptance Criteria:** AC-04 applies.

**Traceability:** PRD FR-37; AC-04; US-10.

---

## FR-38 — Holiday Auto-Scheduling

**Prioritas:** Should-Have

**Deskripsi:** System SHALL use boost targets during confirmed Break Mode when generating schedules.

**Aktor:** Scheduler.

**Preconditions:** Break Mode active; boost target configured.

**Postconditions:** schedule uses temporary target without mutating baseline target.

**Normal Flow:** detect mode → load effective target → generate schedule → constrain to 70% capacity → save draft.

**Alternative Flows:** return to baseline when period ends.

**Exception Flows:** missing boost configuration => use normal target.

**Business Rules:** Boost is scoped by start/end datetime.

**Acceptance Criteria:** holiday schedule reflects configured boost until end date.

**Traceability:** PRD FR-38; US-10.

---

## FR-39 — Holiday End Notification

**Prioritas:** Should-Have

**Deskripsi:** System SHALL notify H-3 before Break Mode end and present summary report.

**Aktor:** Notification job.

**Preconditions:** Break Mode end date exists and is ≥3 days away.

**Postconditions:** notification/summary available.

**Normal Flow:** scheduled scan → create notification → user opens → show summary.

**Alternative Flows:** if app was closed, notification persists in-app on next open.

**Exception Flows:** duplicate job execution must not duplicate notification.

**Business Rules:** H-3 uses user timezone.

**Acceptance Criteria:** one notification is generated exactly once per break period.

**Traceability:** PRD FR-39.

---

## FR-40 — Undo 30 Detik

**Prioritas:** Should-Have

**Deskripsi:** System SHALL provide a reversible action window of 30 seconds for supported mutations.

**Aktor:** User.

**Preconditions:** mutation completed and undoable.

**Postconditions:** original state restored or undo token expires.

**Normal Flow:** mutation → create undo record → show toast timer → user clicks Undo → execute compensating mutation.

**Alternative Flows:** token expires.

**Exception Flows:** newer conflicting mutation prevents safe undo; UI reports cannot undo without destructive overwrite.

**Business Rules:** Undo is not a substitute for audit log; historical log records both mutation and compensating action.

**Acceptance Criteria:** completing a program and clicking Undo within 30 seconds restores previous lifecycle/schedule state.

**Traceability:** PRD FR-40; FR-22.

---

## FR-41 — In-App Notifications

**Prioritas:** Should-Have

**Deskripsi:** System SHALL show in-app toast/reminder for slot start, deadlines, overload, Emergency Pause end, and holiday end.

**Aktor:** Notification service.

**Preconditions:** trigger event occurs and user has notification permission/preferences enabled in app.

**Postconditions:** notification recorded with read/dismiss state.

**Normal Flow:** scan triggers → enqueue notification → show when app active → record state.

**Alternative Flows:** snooze/dismiss if supported.

**Exception Flows:** duplicate trigger suppressed by unique event key.

**Business Rules:** Web Push is out of MVP; notifications only in-app.

**Acceptance Criteria:** one 5-minute-before-start notification per eligible task assignment.

**Traceability:** PRD FR-41.

---

## FR-42 — Task Notes

**Prioritas:** Must-Have

**Deskripsi:** Every task SHALL have optional lightweight Markdown notes.

**Aktor:** User.

**Preconditions:** task exists.

**Postconditions:** sanitized note persisted and synchronized.

**Normal Flow:** edit note → validate length → sanitize/render safe subset → save.

**Alternative Flows:** offline edit queued.

**Exception Flows:** invalid Markdown payload cannot execute scripts.

**Business Rules:** Markdown is presentation syntax, not executable HTML.

**Acceptance Criteria:** note survives reload and offline sync.

**Traceability:** PRD FR-42; US-07.

---

## FR-43 — Evidence Attachments

**Prioritas:** Should-Have

**Deskripsi:** User MAY attach up to 3 files to a completed task, each JPG/PNG/PDF and ≤5 MB.

**Aktor:** User.

**Preconditions:** task exists; upload allowed.

**Postconditions:** attachments stored and associated with task.

**Normal Flow:** choose files → validate count/type/size → upload to scoped storage → persist metadata → associate.

**Alternative Flows:** retry a failed individual upload.

**Exception Flows:** invalid MIME, oversized file, storage failure → reject without dangling DB record.

**Business Rules:** Max 3 attachments per task; no arbitrary executable file type.

**Acceptance Criteria:** fourth file rejected; 5.1MB file rejected.

**Traceability:** PRD FR-43.

---

## FR-44 — Offline Support

**Prioritas:** Must-Have

**Deskripsi:** Service Worker SHALL cache all Today data needed for execution and Quick Capture SHALL work offline using an outbound mutation queue with last-write-wins synchronization.

**Aktor:** User; Service Worker; Sync service.

**Preconditions:** Today has been loaded online at least once for full baseline cache.

**Postconditions:** offline mutation persisted locally; eventual sync applies last-write-wins policy.

**Normal Flow:** online load → cache Today → connection lost → user Quick Capture → create queue item → connection restored → sync queue in order → server acknowledgement → remove queue item.

**Alternative Flows:** multiple offline edits to same entity collapse to latest mutation where safe.

**Exception Flows:** stale version conflict resolved by last-write-wins; attachment upload requires online state unless specifically queued.

**Business Rules:** IndexedDB/local cache is not authoritative. Local encryption depends on browser OS storage security; app must not claim encrypted-at-rest field semantics.

**Acceptance Criteria:** UAT offline scenario passes and exactly one task appears after sync.

**Traceability:** PRD FR-44; AC/UAT offline; US-02.

---

## FR-45 — Hierarchical Decomposition

**Prioritas:** Must-Have

**Deskripsi:** System SHALL enforce hierarchy Yearly Goal → Monthly Goal → Program → Task → Subtask, with no deeper level.

**Aktor:** User; backend validation.

**Preconditions:** parent entity exists for any non-root child.

**Postconditions:** valid tree stored.

**Normal Flow:** create child → validate parent type → persist relation.

**Alternative Flows:** Promote Subtask converts node to Task.

**Exception Flows:** Program as child of Program rejected; Subtask as child of Subtask rejected.

**Business Rules:** No sub-program; user should create separate programs for complexity.

**Acceptance Criteria:** API rejects unsupported parent-child relation with stable error code.

**Traceability:** PRD FR-45; US-07, US-13.

---

## FR-46 — Task Templates

**Prioritas:** Should-Have

**Deskripsi:** Program SHALL support recurring Task Templates and cron-generated task instances.

**Aktor:** User; scheduler job.

**Preconditions:** active Program exists.

**Postconditions:** template saved; future task instances generated idempotently.

**Normal Flow:** create template → define recurrence → cron scans → calculate target occurrences → create tasks → Auto-Scheduler allocates.

**Alternative Flows:** pause template without deleting history.

**Exception Flows:** duplicate cron run does not duplicate instances.

**Business Rules:** Template is pattern; Task is concrete instance. Program status Dropped/Completed prevents new future instances.

**Acceptance Criteria:** AC-08 applies: weekday standup template generates five task instances for next week.

**Traceability:** PRD FR-46; AC-08; US-14.

---

## FR-47 — End-of-Day Reconciliation

**Prioritas:** Must-Have

**Deskripsi:** Every day at 21:00 local time, system SHALL scan tasks not Selesai/Sebagian and prompt status; at 23:59 local, non-responsive tasks become Terlewat and move to backlog.

**Aktor:** Scheduled job; User.

**Preconditions:** local day and task data available.

**Postconditions:** task gets explicit status, or Terlewat after deadline.

**Normal Flow:** 21:00 scan → create one reconciliation notification → user chooses Selesai/Sebagian/Jadwalkan Ulang/Lewati → update task. At 23:59 unresponded eligible tasks become Terlewat.

**Alternative Flows:** If user opens after 23:59, Morning Recovery presents Terlewat tasks.

**Exception Flows:** job retry must be idempotent and must not create duplicate state transitions.

**Business Rules:** Time calculations use user timezone; emergency pause suppression may suppress notifications while preserving audit data.

**Acceptance Criteria:** AC-06 applies exactly.

**Traceability:** PRD FR-47; AC-06; US-11.

---

## FR-48 — Morning Recovery

**Prioritas:** Must-Have

**Deskripsi:** System SHALL show previous-day Terlewat tasks before normal daily planning and support reschedule, delete, or mark complete.

**Aktor:** User; morning job/UI.

**Preconditions:** Terlewat task exists.

**Postconditions:** each presented task receives explicit action or remains backlog.

**Normal Flow:** open Today in morning → query Terlewat from previous day → show recovery list → user chooses action → update task and log.

**Alternative Flows:** user dismisses list and returns later.

**Exception Flows:** task no longer valid because program Dropped/Completed → show reason and require manual disposition.

**Business Rules:** Recovery should prioritize nearest deadline first.

**Acceptance Criteria:** Terlewat from yesterday appears next morning and can be rescheduled to today.

**Traceability:** PRD FR-48; US-12.

---

## FR-49 — Dynamic Capacity Feedback Loop

**Prioritas:** Should-Have

**Deskripsi:** System SHALL estimate Effective Capacity from 2–4 recent weeks and adjust recommendation for future schedule load.

**Aktor:** Analytics + scheduling engine.

**Preconditions:** enough historical data; if fewer weeks, use available minimum and mark confidence low.

**Postconditions:** next-week recommendation includes capacity adjustment and reason.

**Normal Flow:** calculate actual completed productive hours → compare to target → derive ratio → apply feedback rule → produce recommendation.

**Alternative Flows:** no reliable history → use baseline capacity from configured program targets and avoid aggressive adjustment.

**Exception Flows:** anomalous week marked emergency/break mode should be excluded or weighted separately to avoid corrupting capacity estimate.

**Business Rules:** <80% target → lower next-week load proportionally and suggest reducing/pausing programs. >90% and no burnout signal → offer Boost/backlog fill. Emergency/break weeks are tagged so engine can exclude or normalize them.

**Acceptance Criteria:** AC-09 applies: 60% realization results in approximately 60–70% of productive capacity target and recommendation.

**Traceability:** PRD FR-49; AC-09; US-15.

---



## FR-50 — Goal Horizon and Deadline

**Prioritas:** Must-Have

**Deskripsi:** Sistem SHALL mendukung Goal dengan planning horizon `yearly`, `quarterly`, `monthly`, atau `custom`, serta target date yang eksplisit bila outcome terikat waktu.

**Aktor:** User; Planning Service.

**Preconditions:** User authenticated; title valid.

**Postconditions:** Goal persisted dengan horizon, start date, target date, status, dan progress policy yang valid.

**Business Rules:** Horizon SHALL NOT menjadi parent-child hierarchy. Custom-horizon Goal boleh berdiri sendiri.

**Acceptance Criteria:** Given research Goal selesai empat bulan lagi, When target date disimpan, Then system calculates remaining calendar time and exposes the Goal as deadline-bound.

## FR-51 — First-Class Milestones

**Prioritas:** Must-Have

**Deskripsi:** Sistem SHALL mendukung Milestone sebagai intermediate outcome yang dapat diurutkan, memiliki target date, estimasi workload, status, progress, dan optional dependency.

**Aktor:** User; Planning Service.

**Business Rules:** Milestone belongs to exactly one Goal. Milestone SHALL NOT create recursive nesting.

**Acceptance Criteria:** User can create, reorder, update, block, complete, or drop a Milestone without corrupting Goal history.

## FR-52 — Goal Breakdown Proposal

**Prioritas:** Must-Have

**Deskripsi:** Sistem SHALL dapat menghasilkan draft breakdown Goal menjadi Milestone dan workload allocation berdasarkan deadline, estimated workload, capacity, Hard Landscape, active commitments, and dependency information.

**Aktor:** User; Planning/Scheduling Service; optional AI Provider.

**Postconditions:** A proposal exists; no large hierarchy is silently committed before user approval.

**Acceptance Criteria:** User receives proposed Milestones, target dates, estimated workload, and allocation gaps; user can accept/edit/reject.

## FR-53 — Knowledge Item Lifecycle

**Prioritas:** Must-Have

**Deskripsi:** Sistem SHALL provide first-class Knowledge Items with title, rich content, versioning, search text, attachments, and ownership.

**Aktor:** User; Knowledge Service.

**Acceptance Criteria:** Note persists, survives reload, supports version-aware update, and can be linked to at least one supported domain object.

## FR-54 — Knowledge Links

**Prioritas:** Must-Have

**Deskripsi:** Sistem SHALL support explicit links between Notes/Knowledge Items and Goals, Milestones, Programs, Tasks, and Canvases.

**Business Rules:** Links are domain relationships, not arbitrary HTML. Deleting a target entity SHALL use explicit orphan/preserve policy.

**Acceptance Criteria:** A user can open a Goal and navigate to all linked notes/canvases without duplicate unmanaged copies of the relationship.

## FR-55 — Canvas Lifecycle

**Prioritas:** Should-Have

**Deskripsi:** Sistem SHALL provide create/read/update/archive behavior for Canvas documents and allow attachment to Goal, Milestone, Program, Task, or Note context.

**Aktor:** User; Canvas Service; Canvas Engine Adapter.

**Acceptance Criteria:** Canvas opens with existing scene, user can edit, and saved scene is restored after reload.

## FR-56 — Canvas Version Conflict Protection

**Prioritas:** Must-Have for enabled Canvas release

**Deskripsi:** Canvas document persistence SHALL use optimistic versioning. Stale clients SHALL receive a `409 CANVAS_VERSION_CONFLICT`.

**Acceptance Criteria:** Given server version 8 and client base version 7, When client saves, Then server rejects the mutation without overwriting version 8.

## FR-57 — Offline Knowledge/Canvas Mutations

**Prioritas:** Should-Have

**Deskripsi:** When enabled, Notes and Canvas mutations SHALL be queueable offline using IndexedDB and synchronized when online.

**Acceptance Criteria:** Offline edit survives tab close, remains queued, and either synchronizes or enters an explicit conflict state after reconnect.

## FR-58 — Adaptive Context Capture

**Prioritas:** Could-Have

**Deskripsi:** Sistem MAY collect user-entered energy, stress, task difficulty, skill familiarity, interruption, and focus-duration signals.

**Business Rules:** Signals are subjective/contextual and SHALL NOT be represented as clinical or neurological measurements.

## FR-59 — Adaptive Context as Soft Scheduling Signal

**Prioritas:** Should-Have

**Deskripsi:** Scheduler MAY incorporate Adaptive Context as a soft ranking feature only after hard constraints are satisfied.

**Business Rules:** Sparse or anomalous data SHALL trigger fallback to deterministic baseline policy.

**Acceptance Criteria:** A high-difficulty task with low current energy can receive a lower ranking or be decomposed, but the scheduler never violates deadline, Hard Landscape, or locked-task constraints to do so.

## FR-60 — AI Provider Abstraction

**Prioritas:** Should-Have

**Deskripsi:** System SHALL expose an AI provider interface so Ollama, external providers, or a disabled provider can be selected without changing domain semantics.

**Acceptance Criteria:** The application remains operational when the configured AI provider is unavailable.

## FR-61 — AI Structured Output Validation

**Prioritas:** Must-Have for any AI mutation feature

**Deskripsi:** AI responses intended for domain use SHALL conform to versioned schemas and SHALL be rejected if invalid.

**Acceptance Criteria:** Malformed AI JSON never reaches persistence as a domain mutation.

## FR-62 — AI Proposal Approval

**Prioritas:** Must-Have for AI-triggered planning mutations

**Deskripsi:** AI-generated Goal breakdowns, Milestones, Tasks, reschedules, or Canvas proposals SHALL be presented as proposals before application unless the operation is explicitly configured as non-mutating.

**Acceptance Criteria:** Rejecting an AI proposal creates no domain mutation.

## FR-63 — Explainable Scheduler Decisions

**Prioritas:** Must-Have

**Deskripsi:** Scheduler output SHALL include reason codes and a human-readable summary for non-trivial automated placements/movements.

**Example Reasons:** `HARD_CONSTRAINT_FILTERED`, `LOCK_PROTECTED`, `DEADLINE_PRIORITY`, `CAPACITY_FIT`, `ENERGY_FIT`, `CONTEXT_SWITCH_PENALTY`, `PROGRESS_VALUE`.

## FR-64 — Hard/Soft Scheduler Separation

**Prioritas:** Must-Have

**Deskripsi:** Scheduling SHALL be implemented as feasibility validation followed by candidate ranking. Soft optimization MUST NOT override hard constraints.

**Acceptance Criteria:** Any candidate violating a hard rule is rejected before scoring; changes to soft scoring cannot make an invalid candidate executable.

## FR-65 — Upload & Asset Pipeline (Uppy + Pic Smaller)

**Prioritas:** Should-Have (post-P27 adoption baseline; ADR-014)

**Deskripsi:** Note/Canvas uploads SHALL flow through a Kinevo-owned pipeline: Uppy (upload UX/transport) → client-side validation → image compression via the Pic Smaller engine (when applicable) → Kinevo upload adapter (authorization + ownership) → object storage → Kinevo `Asset` record → Note/Canvas reference. Binary payloads SHALL NOT be embedded inside structured documents where an asset reference is appropriate.

**Business Rules:** Kinevo owns authorization, ownership, canonical asset metadata (user/workspace-scoped; storage_key, content_type, size, sha256, dimensions, compression profile) and storage policy. Upload states (selecting/compressing/uploading/paused/queued/completed/failed/retrying/cancelled) SHALL be user-visible. Compression failure SHALL NOT block upload (original bytes proceed with a visible notice). Storage unavailability SHALL fail visibly with retry/recovery.

**Acceptance Criteria:** A note image upload survives a compression-engine failure and a transient network failure; every stored asset has an owned Asset record; no embedded-editor type/CSS leaks into Kinevo chrome.

## FR-66 — Notification Provider Abstraction (Gotify transport)

**Prioritas:** Should-Have (post-P27 adoption baseline; ADR-014)

**Deskripsi:** Notification semantics (Notification, NotificationPreference, NotificationEvent) SHALL remain Kinevo domain objects; delivery transports (including Gotify) SHALL be inserted behind a Kinevo `NotificationProvider` port without rewriting the notification domain.

**Business Rules:** Channels conceptually separate security/billing/productivity/marketing; marketing requires consent/policy handling. A Gotify outage SHALL degrade delivery only — in-app notifications continue and delivery retries where configured. External terminology SHALL NOT surface to normal users.

**Acceptance Criteria:** Swapping or removing the transport provider requires no notification-domain change; no notification content leaks into transport logs beyond the documented payload.

## FR-67 — AI Usage Firewall

**Prioritas:** Must-Have for hosted AI; guardrails apply to BYOK

**Deskripsi:** Every AI request SHALL pass the preflight firewall in order: Authenticate → Entitlement → AI allowance → Rate limit → Request budget → Context/token guard — before any provider invocation. A failed preflight SHALL NOT call the provider. Actual usage SHALL be settled after the call (reserve maximum permitted budget → call → measure actual → settle → release unused reservation).

**Business Rules:** Both hosted-AI and BYOK requests are bound by token/context/request/rate limits, timeouts, and abuse protection (BYOK never consumes Kinevo hosted credits but never bypasses safeguards). The request firewall MUST run before provider invocation.

**Acceptance Criteria:** A user at zero remaining allowance gets a clear, non-provider error with no provider call logged; a completed request settles exactly the actual usage against the AI usage ledger.

## FR-68 — Provider Price Catalog

**Prioritas:** Must-Have before any usage-based billing goes live

**Deskripsi:** Provider model prices SHALL live in a versioned catalog (provider, model, currency, input/cached-input/output rates, effective_from/until, pricing_version, source) — never hardcoded in feature code. Historical usage SHALL remain reproducible after price changes.

**Acceptance Criteria:** A price change creates a new catalog version; past usage rows still resolve to the price version effective at request time.

## FR-69 — Product Event Taxonomy & Analytics Boundary

**Prioritas:** Should-Have (post-P27 adoption baseline; ADR-014)

**Deskripsi:** Product analytics events SHALL be semantic and content-minimal, owned by Kinevo (signup, verification_completed, onboarding_completed, workspace_created, goal_created, goal_progressed, ai_breakdown_requested, ai_proposal_accepted, task_completed, review_opened, wrapped_opened, wrapped_shared, upgrade_started, subscription_activated). Raw note/canvas/private task content SHALL NOT be sent as event payload. OpenPanel (product behavior) and Langfuse (AI engineering telemetry) SHALL stay separate systems; the Kinevo AI ledger remains the only customer-facing billing/usage truth.

**Acceptance Criteria:** An event stream replay cannot reconstruct private note/canvas content; entitlement/credit resolution never reads from OpenPanel or Langfuse.

---

# 4. KEBUTUHAN NON-FUNGSIONAL (NFR)


> Angka NFR yang tidak eksplisit pada PRD di bawah diberi label derived requirement agar dapat divalidasi bersama owner sebelum go-live.

## NFR-01 Performance

- FCP SHALL target <2 detik pada baseline network/device yang didefinisikan QA.
- Slot interaction SHALL target <100 ms perceived UI response for local interactions.
- Standard read API P95 SHOULD be ≤500 ms under expected single-user load.
- Standard mutation API P95 SHOULD be ≤800 ms excluding file uploads/import processing.
- Today view SHALL avoid blocking on non-critical analytics.

## NFR-02 Security

- Backend SHALL enforce Supabase RLS/ownership filtering.
- HTTPS/TLS SHALL be used for all network traffic.
- Authentication token SHALL be managed through trusted auth mechanism; client must not persist raw credentials in application tables.
- Markdown, attachment metadata, imported PDF text, and API parameters SHALL be validated/sanitized.
- File upload SHALL enforce allowlist extension + detected content type + size.
- Rate limiting SHOULD apply to authentication, import, upload, and scheduler-trigger endpoints.
- Secrets SHALL reside in platform secret storage, never in source code.
- OWASP-relevant controls: access control, injection prevention, XSS, CSRF posture where applicable, SSRF protection for URL imports, insecure direct object reference prevention, secure error handling, dependency management.
- Regulatory baseline SHALL follow applicable UU PDP requirements after legal validation.

## NFR-03 Privacy & Data Minimization

- Collect only fields required for functionality.
- Activity logs and notes are private user data.
- Export SHALL require authenticated user context.
- iCal feed SHALL expose only fields explicitly designated as exportable.
- Attachments SHALL not be world-readable.

## NFR-04 Availability

- Target uptime SHOULD be ≥99.5% monthly for the managed application path, excluding planned maintenance and third-party outages. **[Derived; confirm target]**
- Scheduler failure SHALL not destroy last stable schedule.
- User SHALL have manual Sync Now fallback.

## NFR-05 Backup & Disaster Recovery

- Daily database/storage backup is required by PRD.
- Full manual JSON/CSV export MUST be available.
- Recovery objective: restore critical user data after infrastructure failure. **[Derived; exact RTO/RPO to be confirmed]**
- Suggested baseline: RPO ≤24h, RTO ≤4h for personal deployment. **[Derived]**

## NFR-06 Scalability

MVP is single-user; architecture SHALL avoid assumptions that make future multi-user impossible, but multi-tenancy is not required. Query patterns must use indexed user/date keys.

## NFR-07 Usability & Accessibility

- Keyboard navigation required.
- Basic screen reader support required.
- Drag-and-drop SHALL have keyboard alternative.
- Color-coded states SHALL include text/icon/accessibility labels.
- Primary Today interactions SHALL be usable on mobile-width browsers even though native mobile app is out of scope.
- Critical actions such as Emergency Pause, delete, and reschedule SHALL have explicit confirmation.

## NFR-08 Reliability

- Scheduler jobs SHALL be idempotent.
- Cron retries SHALL not duplicate task instances or notifications.
- All mutation requests SHOULD carry operation UUID/idempotency key.
- Partial failure SHALL use compensating action or transactional boundary.
- Offline queue SHALL persist through tab close.

## NFR-09 Maintainability

- Domain logic SHALL be separated from UI rendering.
- Scheduling policy SHALL be testable as pure/domain functions where feasible.
- API contracts SHALL be versioned.
- Errors SHALL use stable codes.
- Structured logs SHALL include correlation/operation ID, actor scope, job name, and outcome without secrets.
- README/runbook and schema migrations SHALL be maintained with code.

## NFR-10 Observability

Minimum telemetry:
- scheduler run success/failure/duration;
- task creation/update failure;
- sync queue backlog size;
- import parsing success/failure;
- notification delivery state;
- storage upload failure;
- database health;
- client offline sync failure.

Sensitive note content MUST NOT be emitted to logs.

---


## 4.11 NFR-11 Architecture Portability

- The application MUST run on a standard Linux x86_64 or ARM64 container environment compatible with the selected runtime dependencies.
- Oracle Cloud MUST NOT be required by application code.
- PostgreSQL MUST remain the relational source of truth.
- Object storage MUST be abstracted behind a storage interface where practical.
- AI providers MUST be abstracted behind a provider interface.
- Canvas and editor engines MUST be isolated behind adapter boundaries.

## 4.12 NFR-12 Explainability of Automation

- Every automatic schedule movement MUST have an inspectable reason code.
- Every schedule proposal MUST identify impacted tasks, old assignment, proposed assignment, and the principal constraints/signals that produced the proposal.
- AI-generated changes MUST be distinguishable from deterministic system calculations.

## 4.13 NFR-13 AI Safety

- AI output MUST be treated as untrusted input.
- AI output MUST be schema-validated before application.
- AI MUST NOT directly execute arbitrary SQL, shell commands, filesystem writes, migrations, or destructive domain mutations.
- AI-triggered domain mutations MUST pass the same authorization, validation, invariant, and transaction checks as human-triggered mutations.
- AI prompts/context MUST exclude secrets and credentials.

## 4.14 NFR-14 Knowledge/Canvas Integrity

- Note and canvas saves MUST be versioned.
- Concurrent stale writes MUST return a conflict rather than silently overwrite a newer version.
- Binary assets MUST NOT be embedded into relational rows when they exceed configured payload thresholds; object storage SHALL be used.

## 4.15 NFR-15 Offline Integrity

- Offline mutations MUST have unique operation IDs.
- Replaying the same offline operation MUST be idempotent.
- Local cache data MUST be distinguishable from authoritative server data.
- Synchronization failures MUST preserve the user's local queued mutation until a deterministic resolution is available.



---

# 5. SYSTEM ARCHITECTURE

## 5.1 Architectural Style

Kinevo SHALL use a **modular monolith**. All principal business domains run in one deployable backend application while retaining strict module boundaries. Microservices are explicitly rejected for MVP because the system is single-user and the dominant complexity is business/domain correctness rather than distributed scale.

## 5.2 High-Level Architecture

```text
                           Internet
                              |
                         Cloudflare/TLS
                              |
                            Nginx
                              |
                    +---------v----------+
                    | Laravel Application|
                    |  Modular Monolith  |
                    +----+----+----+-----+
                         |    |    |
          +--------------+    |    +----------------+
          |                   |                     |
          v                   v                     v
     PostgreSQL          Queue/Workers       Object Storage
          ^                   ^
          |                   |
          +---------+---------+
                    |
             Scheduler Runtime

Browser
  |
  +-- Vue 3 / TypeScript / Inertia / Pinia
  +-- Service Worker
  +-- IndexedDB
  +-- Canvas React Island -> Canvas Adapter -> Excalidraw
  +-- Knowledge Editor -> Tiptap Adapter

Optional AI path:
Laravel AI Orchestrator -> AI Provider -> Ollama / External Provider
```

## 5.3 Layering Rules

```text
Presentation
  -> Application
      -> Domain
          -> Infrastructure

Domain MUST NOT import Vue, Inertia, HTTP controllers, Excalidraw, Tiptap, Ollama, or infrastructure-specific classes.
```

### 5.3.1 Presentation Layer

Responsible for route handling, HTTP concerns, Inertia page composition, validation feedback, UI state, and serialization.

### 5.3.2 Application Layer

Responsible for use cases/commands/queries, transaction orchestration, authorization context, idempotency handling, and coordinating domain services.

### 5.3.3 Domain Layer

Responsible for business invariants, state transitions, scheduling rules, goal/milestone semantics, capacity calculations, progress semantics, and deterministic policy decisions.

### 5.3.4 Infrastructure Layer

Responsible for PostgreSQL persistence, queues, storage, external providers, PDF/iCal parsing, AI transports, and adapter implementations.

## 5.4 Domain Modules

```text
Identity
Goals
Milestones
Programs
Tasks
Scheduling
Capacity
Execution
Recovery
Knowledge
Canvas
Analytics
Notifications
OfflineSync
AI
ImportExport
Audit
Settings
```

## 5.5 Architectural Boundaries

### 5.5.1 Knowledge Boundary

Tiptap is an editor engine. Kinevo owns note identity, links, ownership, metadata, persistence, versioning, authorization, and AI integration.

### 5.5.2 Canvas Boundary

Excalidraw is a canvas engine. Kinevo owns canvas identity, context links, persistence, versioning, ownership, attachments, and domain semantics.

### 5.5.3 AI Boundary

Ollama or another provider is an inference engine. Kinevo owns prompts/templates, structured schemas, policy checks, context selection, approval flow, audit, and mutation execution.

## 5.6 Scheduler Architecture

Scheduling SHALL be split into two logical stages:

1. **Hard Constraint Engine**: rejects invalid candidates.
2. **Soft Optimization Engine**: ranks valid candidates without ever overriding hard constraints.

### 5.6.1 Hard Constraints

- Hard Landscape overlap forbidden.
- Locked task cannot be moved by automation.
- No illegal temporal overlap.
- Sacred Anchor placement policy.
- Deadline feasibility.
- Capacity safety reserve.
- Valid program/goal lifecycle.
- Existing approved schedule version integrity.

### 5.6.2 Soft Signals

- Goal urgency.
- Milestone urgency.
- Priority tier.
- Deadline proximity.
- Task duration fit.
- Recent effective capacity.
- Energy signal.
- Stress signal.
- Difficulty/familiarity fit.
- Context-switching penalty.
- Progress value.

Soft signals MUST remain configurable and explainable. Their use SHALL NOT be described as medical, diagnostic, or deterministic proof of cognitive state.

## 5.7 Schedule Lifecycle

```text
Current Schedule
     |
Generate Proposal
     |
Candidate Schedule
     |
Preview / Explain
     |
+----+----+
|         |
Cancel   Apply
           |
      Validate Version
           |
      Atomic Commit
           |
     New Schedule Version
```

Generated schedules are drafts until explicitly applied where the operation is not inherently non-destructive.

---

# 6. DOMAIN MODEL

## 6.1 Core Hierarchy

The system SHALL distinguish **planning horizon** from **domain hierarchy**. A Goal MAY have a Yearly, Quarterly, Monthly, or Custom time horizon. Horizon is metadata; it is not a forced parent-child tree.

```text
Goal
  |
  +---- Milestone
  |
  +---- Program
  |       |
  |       +---- Task Template
  |       +---- Task
  |               +---- Subtask
  |
  +---- Knowledge Links

Goal/Milestone/Program/Task MAY link to Knowledge Items and Canvases through explicit relationships.
```

## 6.2 Goal Semantics

A Goal SHALL contain:

- title
- description/outcome statement
- horizon
- start date
- target date/deadline where applicable
- status
- progress calculation policy
- optional target metric
- priority tier where used by planning

A Goal is completed when its configured outcome criteria are met; task count alone SHALL NOT be considered sufficient for every Goal.

## 6.3 Milestone Semantics

A Milestone is a first-class intermediate outcome. It SHALL support:

- sequence/order
- target date
- estimated effort
- status
- progress mode
- completion evidence/reference
- dependency on other milestones where configured

## 6.4 Program Semantics

Programs remain sustained workstreams and retain the existing lifecycle: Active, Paused, Completed, Dropped.

## 6.5 Task Semantics

Tasks remain single executable work items. A Task MAY reference Program, Goal/Milestone context, due date, duration, notes, attachments, and schedule assignments.

The system SHALL enforce no recursive subtask hierarchy deeper than one level.

## 6.6 Knowledge Semantics

Knowledge Items SHALL support:

- rich text content
- references/links
- attachments
- relation to Goal/Milestone/Program/Task
- optional Canvas relation
- version metadata

## 6.7 Canvas Semantics

A Canvas is a versioned visual document. The canonical relational metadata is owned by Kinevo; scene serialization is stored as structured document data; binary assets are stored in object storage when required.

## 6.8 Progress Events

The system SHALL support append-only meaningful progress events, including but not limited to:

- task completed
- milestone advanced
- milestone completed
- evidence attached
- experiment/result recorded
- goal progress materially changed

Progress events are informational inputs to analytics and adaptive recommendations; they MUST NOT overwrite historical activity logs.

---

# 7. DATA MODEL

## 7.1 Core Tables

The v1 core entities remain authoritative and SHALL be extended rather than duplicated.

| Entity | Purpose |
|---|---|
| profiles | Owner profile/settings |
| goals | Long-horizon outcome |
| goal_targets | Optional measurable target definition |
| milestones | Intermediate outcomes |
| milestone_dependencies | Dependency graph for milestones |
| programs | Sustained workstreams |
| contributions | Program-to-goal contribution weights |
| tasks | Executable work |
| subtasks | Single-level task decomposition |
| task_assignments | Concrete schedule intervals |
| hard_landscape_events | Immutable scheduling constraints |
| schedule_overrides | Recurrence exceptions |
| task_templates | Recurring task patterns |
| activity_logs | Append-only historical events |
| pause_events | Mini/Emergency Pause state |
| notifications | In-app notification state |
| attachments | File metadata |
| capacity_snapshots | Observed capacity |
| scheduler_runs | Scheduler execution history |
| offline_operation_ledger | Offline mutation reconciliation |
| notes | Knowledge documents |
| note_versions | Version history where enabled |
| knowledge_links | Cross-domain links |
| canvases | Canvas metadata |
| canvas_documents | Versioned canvas scene data |
| canvas_files | Canvas binary asset metadata |
| focus_sessions | Actual focus execution intervals |
| adaptive_context | Optional energy/stress/difficulty signals |
| progress_events | Meaningful progress events |
| ai_runs | AI proposal/audit records |
| ai_proposals | Validated structured proposals |

## 7.2 New Goal Table

`goals` SHOULD contain at minimum:

- `id` UUID primary key
- `user_id` UUID
- `title` text 1–200
- `description` text/markdown
- `horizon` enum (`yearly|quarterly|monthly|custom`)
- `start_date` date
- `target_date` date nullable
- `status` enum (`draft|active|paused|completed|archived|dropped`)
- `priority_tier` integer 1–3
- `progress_mode` enum/config
- `progress_value` derived or audited snapshot
- timestamps

## 7.3 Milestone Table

`milestones` SHOULD contain:

- `id`
- `goal_id`
- `user_id`
- `title`
- `description`
- `sequence`
- `target_date`
- `estimated_minutes`
- `status` (`planned|active|blocked|completed|dropped`)
- `progress_mode`
- `progress_value`
- `completed_at`
- `version`
- timestamps

## 7.4 Knowledge Tables

### `notes`

- `id`
- `user_id`
- `title`
- `document_json` JSONB (canonical editor document)
- `markdown_cache` text nullable
- `plain_text_cache` text nullable
- `version` integer
- timestamps

### `knowledge_links`

- `id`
- `user_id`
- `source_type`
- `source_id`
- `target_type`
- `target_id`
- `link_type`
- timestamps

## 7.5 Canvas Tables

### `canvases`

- `id`
- `user_id`
- `title`
- `goal_id?`
- `milestone_id?`
- `program_id?`
- `task_id?`
- `version`
- `archived_at`
- timestamps

### `canvas_documents`

- `id`
- `canvas_id`
- `schema_version`
- `scene_json` JSONB
- `version`
- `created_at`
- `updated_at`

### `canvas_files`

- `id`
- `canvas_id`
- `storage_path`
- `content_type`
- `size_bytes`
- `sha256?`
- timestamps

Binary data SHALL be stored in object storage; scene JSON SHOULD reference binary assets by stable application-owned IDs/paths rather than embedding large binary payloads.

## 7.6 Adaptive Context

`adaptive_context` MAY record user-entered contextual signals:

- `energy_level` 1–10
- `stress_level` 1–10
- `task_difficulty` 1–10
- `skill_familiarity` 1–10
- `context_switch_cost` optional
- timestamp

These values are advisory signals only and MUST NOT be represented as diagnosis or objective neurological measurement.

## 7.7 AI Audit Tables

`ai_runs` SHALL record:

- provider
- model identifier
- prompt/template version
- context hash
- input/output token metadata where supported
- status
- latency
- error code
- created_at

`ai_proposals` SHALL record:

- proposal type
- schema version
- structured JSON output
- validation result
- user decision (`pending|accepted|rejected|edited`)
- resulting operation ID if applied

Sensitive note content SHOULD NOT be stored redundantly in AI logs.

## 7.8 Indexing

At minimum:

- `goals(user_id, status, target_date)`
- `milestones(goal_id, status, target_date)`
- `tasks(user_id, status, due_at)`
- `tasks(user_id, program_id, status)`
- `task_assignments(user_id, date, start_at)`
- `hard_landscape_events(user_id, start_at, end_at)`
- `activity_logs(user_id, event_at DESC)`
- `notifications(user_id, scheduled_for, read_at)`
- `notes(user_id, updated_at DESC)`
- `knowledge_links(user_id, source_type, source_id)`
- `canvases(user_id, updated_at DESC)`
- `canvas_documents(canvas_id, version DESC)`
- `scheduler_runs(user_id, started_at DESC)`
- `ai_runs(user_id, created_at DESC)`

Partitioning is NOT required for MVP.

---

# 8. API CONTRACT

## 8.1 API Principles

- REST over HTTPS for business mutations and reads.
- Versioned under `/api/v1`.
- Idempotency key or operation UUID for mutation endpoints.
- Stable error codes.
- 409 for version/state conflicts.
- 202 for queued/long-running operations.
- APIs MUST reuse application/domain services rather than duplicate business logic.

## 8.2 Core Existing Endpoints

The v1 endpoints remain valid, including:

```text
GET   /api/v1/today?date=
GET   /api/v1/week?start=
POST  /api/v1/tasks
PATCH /api/v1/tasks/{id}
POST  /api/v1/tasks/{id}/partial-complete
POST  /api/v1/subtasks/{id}/promote
POST  /api/v1/tasks/{id}/lock
POST  /api/v1/tasks/{id}/reschedule
POST  /api/v1/schedule/auto
POST  /api/v1/schedule/reschedule
POST  /api/v1/schedule/apply
POST  /api/v1/schedule/sync
POST  /api/v1/imports/pdf
GET   /api/v1/analytics/pillars
GET   /api/v1/logs
POST  /api/v1/export
POST  /api/v1/undo/{id}
```

## 8.3 Goal/Milestone Endpoints

```text
GET   /api/v1/goals
POST  /api/v1/goals
GET   /api/v1/goals/{id}
PATCH /api/v1/goals/{id}
POST  /api/v1/goals/{id}/archive
POST  /api/v1/goals/{id}/breakdown
GET   /api/v1/goals/{id}/progress

GET   /api/v1/milestones?goal_id=
POST  /api/v1/milestones
PATCH /api/v1/milestones/{id}
POST  /api/v1/milestones/{id}/complete
POST  /api/v1/milestones/{id}/reorder
```

`/goals/{id}/breakdown` SHALL return a draft proposal and SHALL NOT silently create an entire hierarchy without user approval.

## 8.4 Knowledge Endpoints

```text
GET   /api/v1/notes
POST  /api/v1/notes
GET   /api/v1/notes/{id}
PATCH /api/v1/notes/{id}
POST  /api/v1/notes/{id}/links
GET   /api/v1/knowledge/search
```

## 8.5 Canvas Endpoints

```text
GET   /api/v1/canvases
POST  /api/v1/canvases
GET   /api/v1/canvases/{id}
PATCH /api/v1/canvases/{id}
PUT   /api/v1/canvases/{id}/document
POST  /api/v1/canvases/{id}/archive
POST  /api/v1/canvases/{id}/files
GET   /api/v1/canvases/{id}/links
POST  /api/v1/canvases/{id}/links
DELETE /api/v1/canvases/{id}/links/{linkId}
```

Canvas document PUT MUST accept a client version. A stale version MUST return `409 CANVAS_VERSION_CONFLICT`.

## 8.6 AI Endpoints

```text
POST /api/v1/ai/decompose-goal
POST /api/v1/ai/summarize-note
POST /api/v1/ai/extract-tasks
POST /api/v1/ai/suggest-milestones
POST /api/v1/ai/suggest-canvas
GET  /api/v1/ai/runs/{id}
POST /api/v1/ai/proposals/{id}/accept
POST /api/v1/ai/proposals/{id}/reject
```

AI endpoints SHALL produce structured proposals; they SHALL NOT bypass domain validation.

## 8.7 Canonical Error Codes

Existing stable codes remain, with new additions:

| Code | HTTP | Meaning |
|---|---:|---|
| CANVAS_VERSION_CONFLICT | 409 | Canvas was changed since client snapshot |
| NOTE_VERSION_CONFLICT | 409 | Note was changed since client snapshot |
| GOAL_BREAKDOWN_INVALID | 422 | Breakdown proposal violates goal/milestone constraints |
| AI_PROVIDER_UNAVAILABLE | 503 | AI provider unavailable |
| AI_OUTPUT_INVALID | 422 | AI returned invalid structured output |
| AI_PROPOSAL_PENDING | 202 | Proposal generated but awaiting user decision |
| OFFLINE_OPERATION_CONFLICT | 409 | Offline mutation conflicts with authoritative state |



---

# 9. OFFLINE & SYNCHRONIZATION SPECIFICATION

## 9.1 Offline Scope

Offline MVP SHALL guarantee:

- Today view cache.
- Current-day task/subtask/note access.
- Quick Capture.
- Selected current canvas/document editing where cached.
- Queued mutations.

Offline SHALL NOT guarantee arbitrary historical dataset availability.

## 9.2 Local Storage

IndexedDB SHALL store:

- cached entities
- cached schedule snapshot
- local editor/canvas snapshot
- outbound mutation queue
- sync metadata

## 9.3 Mutation Queue

Each mutation SHALL have:

```text
operation_id
entity_type
entity_id
operation_type
payload
client_timestamp
base_version?
status
attempt_count
last_error?
```

## 9.4 Conflict Strategy

The existing product rule of last-write-wins remains valid for the narrow MVP offline queue where explicitly configured. For versioned rich content and canvas documents, the more conservative rule SHALL be applied: stale writes return a conflict and are not silently discarded.

This distinction prevents applying blind last-write-wins to high-value rich documents while preserving simple queue behavior for low-risk operations.

## 9.5 Sync Flow

```text
Local Mutation
    ↓
Persist IndexedDB
    ↓
Queue Operation
    ↓
Online Detected
    ↓
POST/PUT Operation
    ↓
Server Validation
    ↓
+-----------+-----------+
|                       |
Applied                Conflict
|                       |
ACK                    Resolution UI
|                       |
Remove Queue           Keep Local Data
```

---

# 10. KNOWLEDGE LAYER SPECIFICATION

## 10.1 Editor Architecture

The default rich-text editor SHALL use Tiptap or another replaceable editor adapter. Kinevo SHALL own the document entity and persistence.

## 10.2 Document Model

Canonical representation SHALL be structured JSON. Markdown/plain text are derived formats for interoperability/search where needed.

## 10.3 Custom Semantic Nodes

The editor SHOULD support domain-aware references such as:

- Goal reference.
- Milestone reference.
- Program reference.
- Task reference.
- Canvas embed.
- File/attachment reference.

These MUST resolve through Kinevo APIs rather than storing uncontrolled arbitrary HTML/URLs as business semantics.

## 10.4 Search

Search SHOULD index title and normalized plain text. Rich structured content MUST be sanitized before display.

## 10.5 Knowledge Linking

Any Knowledge Item MAY link to one or more Goals, Milestones, Programs, Tasks, or Canvases. A Canvas MAY also act as a link source attached to Goals, Milestones, Programs, Tasks, or Notes. Link types SHOULD be explicit (`supports`, `references`, `derived_from`, `evidence_for`, `related_to`, etc.).

---

# 11. CANVAS / EXCALIDRAW INTEGRATION SPECIFICATION

## 11.1 Architectural Position

Excalidraw SHALL be treated as an editor/renderer engine and MUST NOT own Kinevo domain state. Kinevo SHALL expose a Canvas Adapter boundary.

## 11.2 Frontend Integration Boundary

The main UI is Vue. Canvas rendering MAY use a bounded React island/custom-element boundary because the Excalidraw integration is React-based. Vue components MUST NOT depend directly on Excalidraw internal implementation details.

The adapter SHALL expose an application-level contract equivalent to:

```text
load(document)
save(document, baseVersion)
setReadOnly(enabled)
setTheme(theme)
subscribe(changeCallback)
flush()
destroy()
```

## 11.3 Save Policy

- Debounced autosave SHOULD be used.
- The default target debounce is approximately 800–1500 ms, configurable.
- Manual save MAY trigger an immediate flush.
- Navigation/unload SHOULD perform best-effort flush.
- Every persisted canvas document SHALL increment its version.

## 11.4 Canvas Storage

Scene JSON SHALL be stored in PostgreSQL JSONB. Large binary assets SHALL be stored in object storage with stable IDs.

## 11.5 Canvas Versioning

A client MUST submit its known base version. The server MUST reject stale updates with a 409 response.

## 11.6 Canvas Offline

Canvas snapshots MAY be cached and edited offline where the feature is enabled. Offline save SHALL use the same operation ledger design as other offline mutations, with conflict detection for versioned documents.

## 11.7 Canvas-to-Domain Integration

Canvases MAY be attached to Goal, Milestone, Program, Task, or Note contexts. Deleting a business entity MUST NOT silently delete a referenced canvas; the system SHALL preserve or explicitly orphan the canvas according to retention policy.

---

# 12. ADAPTIVE PRODUCTIVITY & HUMAN-CENTRIC REQUIREMENTS

## 12.1 Design Position

The system MAY use evidence-informed ideas related to challenge/skill fit, meaningful progress, cognitive load, and work/rest patterns. It SHALL NOT claim to diagnose cognitive state, mental health, neurological fatigue, or clinical conditions.

## 12.2 Adaptive Signals

Optional signals include:

- energy self-report 1–10
- stress self-report 1–10
- task difficulty 1–10
- skill familiarity 1–10
- interruption count
- actual vs estimated duration
- focus-session completion
- meaningful progress events

## 12.3 Soft Optimization Use

Signals SHALL influence ranking only when enough data/confidence exists. The system MUST fall back to baseline scheduling rules when data is sparse or anomalous.

## 12.4 Adaptive Focus Blocks

The system SHOULD support variable focus blocks rather than forcing a universal fixed cycle. Possible durations are configuration, not biological claims.

## 12.5 Progress Principle Application

The system SHOULD surface meaningful progress rather than only completion counts. A progress event MUST reference the domain change that created the event.

## 12.6 Cognitive Load Safeguard

UI SHALL prefer progressive disclosure. The primary execution screen SHALL emphasize current/next work and avoid presenting all analytics simultaneously.

---

# 13. AI ARCHITECTURE

## 13.1 Provider Abstraction

```text
AI Orchestrator
      │
      ├── Ollama Provider
      ├── External Provider (optional)
      └── Disabled/Mock Provider
```

## 13.2 AI Roles

AI MAY assist with:

- goal decomposition
- milestone suggestions
- note summarization
- task extraction
- knowledge classification
- canvas proposal generation
- semantic search assistance

AI SHALL NOT autonomously:

- delete tasks
- violate locked scheduling constraints
- directly mutate production database tables
- bypass user confirmation for destructive rescheduling

## 13.3 Structured Output

All AI mutations SHALL use schema-validated JSON objects. Example proposal categories:

```text
GoalBreakdownProposal
MilestoneProposal
TaskExtractionProposal
CanvasProposal
SummaryProposal
```

## 13.4 AI Context Selection

The AI Orchestrator SHALL select minimal necessary context. It MUST NOT send all user data by default. Context MUST be scoped to the current user and purpose.

## 13.5 AI Development Models

Local coding/agent models such as Qwythos are development tooling and SHALL NOT be part of the production product dependency graph.

## 13.6 Production Local AI

Ollama MAY run in a separate optional service. A small model appropriate to available VPS resources SHOULD be used. The application MUST remain functional when the AI provider is unavailable.

---

# 14. UI/UX REQUIREMENTS

## 14.1 Primary Information Architecture

```text
Today
Week
Calendar
Roadmap
Knowledge
Canvas
Analytics
Input & Sync
Break Mode
Settings
```

Today remains the primary execution surface.

## 14.2 Goal Workspace

A Goal workspace SHALL show:

- outcome statement
- deadline
- progress
- milestones
- linked programs
- linked knowledge
- linked canvases
- active risks/conflicts
- schedule load summary

## 14.3 Cognitive Load Requirements

Primary execution views SHALL:

- emphasize now/next/later
- progressively reveal secondary data
- avoid color-only communication
- avoid dashboard overload
- make destructive actions explicit

## 14.4 Canvas UX

Canvas SHALL provide:

- open from Goal/Milestone/Program/Task/Note
- autosave state indicator
- offline indicator
- save/conflict state
- read-only mode
- return-to-context navigation

## 14.5 AI UX

AI proposals SHALL show:

- what the AI proposes
- which context was used at a high level
- confidence/uncertainty where meaningful
- Accept/Edit/Reject

AI suggestions SHALL never be visually indistinguishable from confirmed system state.

## 14.6 Retention UX

Retention SHALL be driven by context → continuity → progress → reflection → reduced cognitive overhead — never by streak spam, badges, artificial urgency, manipulative notifications, or dark patterns.

- **Empty states** SHALL answer: What is this? Why does it matter? What can I do? What should I do next? Generic "No data" without a next step is a defect on critical surfaces (canonical copy: docs/design.md).
- **Personalization** SHALL be evidence-based (display name, active workspace/goal, deadlines, today tasks, recent progress/activity, local date/time). Heuristic productivity signals SHALL NOT be turned into medical/psychological claims.
- **CTA hierarchy** per critical surface: one primary, one secondary, optional tertiary; destructive actions never compete visually with the primary workflow.
- **Micro-interactions** SHALL communicate cause and effect (completion → progress → next action; save → saved; online → offline → queued → syncing → saved). Motion SHALL honor `prefers-reduced-motion`.
- **Continuity**: a Goal page SHALL surface relevant Notes/Canvas/Tasks; a Task page SHALL surface linked Goal/Knowledge/Canvas; a Note/Canvas page SHALL surface its linked entities.
- **Retention loops**: daily (Today → NOW → Start → Complete → progress → next action), weekly (Review → what moved/stalled → next-week plan), long-term (progress history → insight → Wrapped → next goal).
- **Wrapped** sharing SHALL default OFF, with preview → privacy summary → confirm → share; no raw Note/Canvas/private task content may leak through the share artifact.
- **First session** SHALL progressively disclose: Goal → AI Breakdown → first Task → Today → first completion, before exposing Knowledge/Canvas/Analytics at full weight.

North Star metric: **Weekly Goal Progressing Users (WGPU)** — unique users in a 7-day window performing at least one meaningful progress action on an active Goal.

---

# 15. SECURITY & PRIVACY

## 15.1 Ownership

All business rows MUST be scoped to the authenticated user. Server-side authorization MUST be mandatory even for single-user deployment.

## 15.2 Input Handling

- Markdown sanitization.
- Attachment allowlist.
- File size validation.
- Schema validation.
- SSRF protection for any future URL import.
- Secure error handling.

## 15.3 Secrets

Secrets MUST be supplied through environment/secret management and MUST NOT be committed to Git.

## 15.4 AI Privacy

AI context MUST be purpose-scoped. Sensitive note content SHOULD only be sent when needed and only to a configured provider. Local Ollama use MAY keep processing on the self-hosted infrastructure.

## 15.5 Open-Source Dependency Compliance

All third-party source/code integrations MUST record license and attribution obligations in `docs/third-party/`.

---

# 16. OPERATIONS & DEPLOYMENT

## 16.1 Deployment Profile

Reference personal deployment:

```text
Oracle Cloud Always Free profile
Linux ARM64
Docker Compose
Nginx
Laravel
PostgreSQL
Queue Worker
Optional Ollama
Object Storage / backup destination
```

The application MUST remain deployable on another Linux container host.

## 16.2 Production Containers

Recommended logical services:

```text
nginx
app (Laravel/PHP-FPM)
worker (queue)
scheduler (Laravel scheduler trigger)
db (PostgreSQL)
ollama (optional)
```

Redis is optional and SHALL NOT be required by core functionality on day one.

## 16.3 Network Exposure

Only HTTP/HTTPS SHOULD be externally exposed. PostgreSQL, queue backends, and Ollama SHALL be internal-network services unless explicit remote access is required.

## 16.4 Backup

- Daily DB backup.
- Remote backup copy.
- Manual JSON/CSV export.
- Restore test periodically.

## 16.5 Observability

Minimum telemetry:

- scheduler run status/duration
- queue failures
- API error rate
- offline queue backlog
- import failures
- storage failures
- AI provider status
- database health

Sensitive content MUST NOT be logged.

---

# 17. TEST STRATEGY

## 17.1 Unit Test Priorities

- Time interval and slot calculations.
- Goal/milestone progress calculations.
- Priority and constraint ranking.
- Schedule hard-constraint validator.
- Soft scoring determinism.
- Capacity calculations.
- Partial completion.
- Program transitions.
- Goal breakdown validation.
- Knowledge link invariants.
- Canvas version conflict semantics.
- AI proposal schema validation.

## 17.2 Integration Tests

- Task + assignment transaction.
- Goal + milestone creation.
- Scheduler idempotency.
- Template generation.
- Offline queue sync.
- Canvas read/write/version conflict.
- Note read/write/version conflict.
- Attachment metadata/storage atomicity.
- AI proposal generation with mocked provider.

## 17.3 Architecture Spike Gate

Before Canvas enters production scope, the following spike criteria MUST pass:

```text
Vue/Inertia
   ↓
Canvas Adapter
   ↓
React Island
   ↓
Excalidraw
   ↓
Laravel API
   ↓
PostgreSQL
   ↓
IndexedDB offline queue
```

Required proof:

- scene load
- drawing/editing
- persistence
- autosave
- reload restoration
- offline edit
- reconnect sync
- stale-version 409
- binary asset persistence
- read-only mode

## 17.4 E2E Golden Flows

1. Create long-horizon Goal → create Milestones → create Program → schedule Tasks.
2. Hard Landscape changes → generate diff → approve → validate schedule.
3. Offline Quick Capture → reconnect → exactly one server task.
4. Open Canvas → draw → offline edit → reconnect → persisted scene.
5. Note → extract task proposal → review → create Task.
6. Goal → AI breakdown → review → persist approved milestones only.

## 17.5 Release Gates

Release SHALL fail when:

- Must-have acceptance criteria fail.
- Migration validation fails.
- Scheduler simulation fails.
- Offline UAT fails.
- Canvas spike/regression gate fails for enabled Canvas release.
- Security checks fail.
- Open-source license compliance is unresolved for a shipped dependency.

---

# 18. TRACEABILITY

## 18.1 Existing PRD Coverage

All PRD FR-01 through FR-49 remain normative unless explicitly superseded by an approved v2.0 refinement. The original functional requirements are retained in Section 3 and SHALL be mapped to the updated architecture/domain contracts.

## 18.2 New v2 Functional Requirements

The following requirements are introduced by SRS 2.0 as derived/product-refinement requirements:

| ID | Requirement | Type |
|---|---|---|
| FR-50 | Goal with explicit horizon/deadline | Derived/Product refinement |
| FR-51 | First-class Milestone management | Derived/Product refinement |
| FR-52 | Goal-to-milestone workload breakdown proposal | Derived/Product refinement |
| FR-53 | Knowledge Item lifecycle | Derived/Product refinement |
| FR-54 | Knowledge link graph between domain entities | Derived/Product refinement |
| FR-55 | Canvas lifecycle and persistence | Derived/Product refinement |
| FR-56 | Canvas version conflict protection | Derived/Technical |
| FR-57 | Offline Canvas/Knowledge queued mutation where enabled | Derived/Technical |
| FR-58 | Adaptive Context capture | Derived/Product refinement |
| FR-59 | Adaptive context as soft scheduling signal | Derived/Technical |
| FR-60 | AI proposal architecture with provider abstraction | Derived/Technical |
| FR-61 | AI structured output validation | Derived/Security |
| FR-62 | AI proposal approval workflow | Derived/Product refinement |
| FR-63 | Explainable scheduler decision reasons | Derived/Technical |
| FR-64 | Deterministic hard constraints + soft optimization separation | Derived/Architecture |

## 18.3 FR-50 — Goal Horizon and Deadline

The system SHALL allow a Goal to have a configurable planning horizon (`yearly`, `quarterly`, `monthly`, `custom`) and an explicit target date when the outcome is deadline-bound.

**Acceptance Criteria**

- Given a Goal with deadline 4 months away, the system stores the target date and exposes remaining time.
- A custom-horizon Goal SHALL NOT require a Yearly Goal parent merely to exist.

## 18.4 FR-51 — Milestones

The system SHALL create, reorder, update, complete, pause, and drop Milestones associated with a Goal.

**Acceptance Criteria**

- Completing a Milestone updates Goal progress according to the configured progress policy.
- A Milestone can be scheduled independently through linked Programs/Tasks.

## 18.5 FR-52 — Goal Breakdown

The system SHALL generate a draft milestone/workload breakdown based on available capacity, deadline, existing commitments, and explicit user input.

**Acceptance Criteria**

- The system never silently creates a large hierarchy from a draft proposal.
- Approved items are persisted atomically or through explicit child operations.

## 18.6 FR-53/54 — Knowledge

Knowledge Items SHALL support CRUD, links, search, versioning, and domain references.

## 18.7 FR-55/56/57 — Canvas

The system SHALL persist a Canvas, protect it with optimistic versioning, and support offline editing where the feature is enabled.

## 18.8 FR-58/59 — Adaptive Context

The system MAY collect contextual signals and SHALL treat them as soft ranking inputs with confidence/fallback rules.

## 18.9 FR-60/61/62 — AI

The system SHALL isolate AI transport, validate structured output, and require approval before domain mutations where configured.

## 18.10 FR-63/64 — Explainable Scheduler

The scheduling engine SHALL separate feasibility from ranking and SHALL expose machine-readable reason codes for generated proposals.

---

# 19. ARCHITECTURE DECISION RECORDS — RESOLVED BASELINE

| ADR | Decision | Status |
|---|---|---|
| ADR-001 | Modular monolith | Accepted |
| ADR-002 | Laravel backend | Accepted |
| ADR-003 | Vue 3 + TypeScript + Inertia | Accepted |
| ADR-004 | PostgreSQL | Accepted |
| ADR-005 | Service Worker + IndexedDB offline | Accepted |
| ADR-006 | Laravel Queue/Scheduler; Redis optional | Accepted |
| ADR-007 | Deterministic scheduler with hard/soft split | Accepted |
| ADR-008 | Goal + Milestone first-class domain model | Accepted |
| ADR-009 | Tiptap-based Knowledge Editor boundary | Accepted |
| ADR-010 | Excalidraw Canvas Adapter boundary | Accepted pending spike gate |
| ADR-011 | AI Provider abstraction; Ollama optional | Accepted |
| ADR-012 | Oracle Cloud as deployment profile, not platform dependency | Accepted |
| ADR-013 | Open-source license/attribution ledger | Accepted |

## 19.1 Retired v1 Decisions

The following SRS 1.0 defaults are retired:

- Vercel + Supabase as mandatory deployment baseline.
- React/Next.js as frontend baseline.
- Supabase Auth/RLS as mandatory implementation mechanism.
- Vercel Cron + pg_cron as scheduler dependency.

Equivalent security/ownership guarantees remain mandatory; implementation is now provided by the Laravel/PostgreSQL deployment architecture.

---

# 20. IMPLEMENTATION ROADMAP

## Phase 0 — Foundation

- Repository structure.
- Laravel + Vue/Inertia baseline.
- PostgreSQL migrations.
- Auth.
- CI.
- baseline tests.

## Phase 1 — Core OS

- Goals.
- Milestones.
- Programs.
- Tasks/subtasks.
- Today/Week/Calendar.
- Hard Landscape.
- Activity logs.

## Phase 2 — Scheduling

- Slot calculation.
- deterministic scheduler.
- Dynamic Rescheduler.
- capacity feedback.
- reconciliation/recovery.

## Phase 3 — Knowledge

- Notes.
- Tiptap adapter.
- knowledge links.
- search.

## Phase 4 — Canvas

- Architecture spike.
- React island / adapter.
- Excalidraw persistence.
- version conflict.
- offline support.

## Phase 5 — Adaptive Context

- focus sessions.
- energy/stress/difficulty signals.
- soft scheduling signals.
- explainability UI.

## Phase 6 — AI

- provider abstraction.
- Ollama integration.
- goal/milestone proposals.
- note summarization/task extraction.
- canvas proposals.

---

# 21. CHANGE MANAGEMENT

A change SHALL update all affected artifacts:

1. SRS requirement IDs.
2. Architecture decision records.
3. Domain model.
4. Database schema/migrations.
5. OpenAPI.
6. UI/UX design.
7. Test strategy/UAT.
8. Implementation status.
9. License/attribution records if third-party code changes.

Version rules:

- Major = incompatible domain/API/schema behavior.
- Minor = backward-compatible feature requirement.
- Patch = clarification without behavior change.

---

# 22. SOURCE OF TRUTH DECLARATION

This file is the **Kinevo SRS v2.0.0 Single Source of Truth**. It supersedes SRS v1.0.0.

The document retains the original PRD functional baseline while introducing an architecture-frozen implementation model centered on a modular monolith, Goal/Milestone semantics, deterministic scheduling, Knowledge/Canvas integration, adaptive context, and optional provider-abstracted AI.

Every implementation artifact SHOULD reference the relevant SRS IDs. No generated code, schema, API contract, or UI specification may silently introduce behavior that conflicts with this document.

**End of SRS v2.0.0.**
