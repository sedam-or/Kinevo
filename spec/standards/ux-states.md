# Standard — UX states and feedback catalogue (UXS)

Every async or stateful surface implements the states below. Keys are message keys in `web/src/i18n/{en,id}.json`; both languages are required (CI `check-i18n`). Error-code messages are separate: each registry code `X` has `error.X.title` and `error.X.detail` (`standards/error-handling.md`).

## 1. Conventions

| Element | Use | Semantics | Dismissal |
|---|---|---|---|
| **Toast** | transient confirmation or non-blocking failure | `role="status"` (info/success) or `role="alert"` (error); one at a time per kind; queue max 3 | success/info auto-dismiss after 6 s, paused on hover/focus; errors persist until dismissed |
| **Banner** | persistent page-level condition | `role="status"`/`role="alert"`, placed under the page header | non-critical dismissible (remembered for the session); critical not dismissible |
| **Inline** | field/section feedback | linked with `aria-describedby`, icon + text | cleared on valid input |
| **Modal** | decisions needing attention | `role="dialog"` (`alertdialog` for destructive), focus trap, labelled + described | `Esc`/Cancel unless saving |
| **Empty** | no data | headline + one sentence + one primary action | n/a |
| **Loading** | waiting | skeleton ≥ 400 ms delay (avoid flash), `aria-busy`; spinner only inside buttons | n/a |
| **Error card** | a section failed to load | message from error code, `request_id`, Retry | Retry |
| **Conflict card** | optimistic concurrency / sync conflict | shows "Your change" vs "Current version", actions per surface | resolved by action |

Rules: every error shows *what happened*, *why if known*, *what to do*; never only colour; never raw codes except the `request_id` in a copyable detail; destructive actions use a modal with the object's name and the consequence; undo is offered via toast "Undo" (10 s) wherever the API allows a reversal (task complete/skip → reopen; archive → unarchive; draft dismissal is not undoable).

## 2. Global and system states

| Key | Element | EN | ID | Actions |
|---|---|---|---|---|
| `state.loading` | skeleton | — | — | — |
| `state.offline.banner` | banner (status) | You're offline. Changes are saved on this device and will sync when you're back. | Kamu sedang offline. Perubahan disimpan di perangkat ini dan akan disinkronkan saat kembali online. | — |
| `state.offline.banner_no_sync` | banner (status) | You're offline. Editing is paused until you reconnect. | Kamu sedang offline. Pengeditan dijeda sampai tersambung kembali. | — |
| `state.reconnecting` | banner | Reconnecting… | Menyambung kembali… | — |
| `state.synced` | toast (status) | All changes synced. | Semua perubahan tersinkron. | — |
| `state.sync.pending` | inline in header | {count, plural, one {1 change waiting to sync} other {# changes waiting to sync}} | {count} perubahan menunggu sinkronisasi | Open queue |
| `state.update.available` | banner | A new version is available. | Versi baru tersedia. | Reload |
| `state.session.expired` | modal (alertdialog) | Your session ended. Sign in again to continue. Your unsaved text is kept. | Sesi berakhir. Masuk lagi untuk melanjutkan. Teks yang belum disimpan tetap aman. | Sign in |
| `state.session.revoked` | page | You were signed out because your sign-in was used elsewhere. Please sign in again. | Kamu dikeluarkan karena sesi masuk terdeteksi dipakai di tempat lain. Silakan masuk lagi. | Sign in |
| `state.forbidden` | error card | You don't have access to this. | Kamu tidak punya akses ke halaman ini. | Go to Today |
| `state.not_found` | page | We can't find that. It may have been deleted. | Tidak ditemukan. Mungkin sudah dihapus. | Go to Today |
| `state.server_error` | error card | Something went wrong on our side. Try again. Reference: {request_id} | Terjadi masalah di sisi server. Coba lagi. Referensi: {request_id} | Retry, Copy reference |
| `state.rate_limited` | toast (alert) | Too many requests. Try again in {seconds, plural, one {# second} other {# seconds}}. | Terlalu banyak permintaan. Coba lagi dalam {seconds} detik. | — |
| `state.module.disabled` | page | {module} is turned off on this server. | {module} dinonaktifkan di server ini. | Back; (admin) Open Modules |
| `state.maintenance` | page | Nivaras is updating. This usually takes a minute. | Nivaras sedang diperbarui. Biasanya hanya satu menit. | Retry |
| `state.setup.required` | page | This server isn't set up yet. | Server ini belum disiapkan. | Open setup |

## 3. Auth and setup

| Key | Element | EN | ID | Actions |
|---|---|---|---|---|
| `auth.login.invalid` | inline (form) | Email or password is incorrect. | Email atau kata sandi salah. | — |
| `auth.login.locked` | inline | Too many attempts. Try again in {minutes, plural, one {# minute} other {# minutes}}. | Terlalu banyak percobaan. Coba lagi dalam {minutes} menit. | — |
| `auth.login.mfa_prompt` | inline | Enter the 6-digit code from your authenticator app. | Masukkan kode 6 digit dari aplikasi autentikator. | Use a recovery code |
| `auth.login.mfa_invalid` | inline | That code didn't work. | Kode itu tidak berlaku. | — |
| `auth.register.closed` | page | Registration is closed on this server. Ask the administrator for an invite. | Pendaftaran ditutup di server ini. Minta undangan dari administrator. | — |
| `auth.register.email_taken` | inline | An account with this email already exists. | Akun dengan email ini sudah ada. | Sign in |
| `auth.password.too_short` | inline | Use at least {min} characters. | Gunakan minimal {min} karakter. | — |
| `auth.password.too_common` | inline | That password is too common. Choose another. | Kata sandi itu terlalu umum. Pilih yang lain. | — |
| `auth.reset.requested` | page | If an account exists for that email and email is set up here, a link is on its way. Otherwise ask your administrator. | Jika ada akun dengan email itu dan email diatur di sini, tautan sedang dikirim. Jika tidak, hubungi administrator. | — |
| `auth.reset.invalid` | page | This reset link is no longer valid. | Tautan reset ini sudah tidak berlaku. | Request a new one |
| `auth.totp.enrolled` | toast (status) | Two-step verification is on. Save your recovery codes. | Verifikasi dua langkah aktif. Simpan kode pemulihan. | Download codes |
| `auth.totp.recovery_codes` | modal | Save these recovery codes now. Each works once and they won't be shown again. | Simpan kode pemulihan ini sekarang. Masing-masing hanya berlaku sekali dan tidak akan ditampilkan lagi. | Copy, Download, I saved them |
| `setup.token.invalid` | inline | This setup link isn't valid. Find the link in the server log. | Tautan penyiapan ini tidak valid. Cari tautan di log server. | Show how |
| `setup.step.template` / `.modules` / `.rules` | page headings | Choose a template / Choose features / Review defaults | Pilih templat / Pilih fitur / Tinjau pengaturan awal | Next, Back |
| `setup.finish.error` | banner (alert) | Setup couldn't be completed. Nothing was changed. Try again. | Penyiapan belum selesai. Tidak ada yang diubah. Coba lagi. | Retry |
| `setup.done` | page | You're all set. Let's plan your first goal. | Semua siap. Mari rencanakan tujuan pertamamu. | Create a goal |

## 4. Today and execution

| Key | Element | EN | ID | Actions |
|---|---|---|---|---|
| `today.loading` | skeleton | — | — | — |
| `today.empty.no_tasks` | empty | Nothing to do yet. Add a goal or a task to get started. | Belum ada yang dikerjakan. Tambahkan tujuan atau tugas untuk memulai. | New task, New goal |
| `today.empty.nothing_scheduled` | empty | You have tasks, but none are scheduled for today. | Kamu punya tugas, tetapi belum ada yang dijadwalkan hari ini. | Plan my week |
| `progress.empty` | empty | Nothing has moved yet. Finish a task and it shows up here. | Belum ada kemajuan. Selesaikan satu tugas dan hasilnya muncul di sini. | Go to Today |
| `today.now.label` / `today.next.label` | label | Now / Next | Sekarang / Berikutnya | Start, Skip |
| `today.review.needed` | banner (status) | Your schedule may be out of date: {reason}. Review proposed changes. | Jadwalmu mungkin sudah usang: {reason}. Tinjau perubahan yang diusulkan. | Review |
| `today.counts` | text | {completed} of {total} done | {completed} dari {total} selesai | — |
| `session.running` | card | Focusing on "{title}" — {elapsed} | Fokus pada "{title}" — {elapsed} | Complete, Abandon |
| `session.complete.prompt` | modal | How did it go? | Bagaimana hasilnya? | Done, Partly done |
| `session.completed` | toast (status) | Nice. "{title}" is {outcome, select, done {done} other {partly done}}. | Bagus. "{title}" {outcome, select, done {selesai} other {selesai sebagian}}. | Undo |
| `session.stale` | banner | A timer from {when} is still running. | Pengatur waktu dari {when} masih berjalan. | Complete at {end}, Abandon |
| `session.already_running` | toast (alert) | Another task is in progress: "{title}". Finish it first. | Tugas lain sedang berjalan: "{title}". Selesaikan dulu. | Open it |
| `task.status.undo` | toast (status) | "{title}" marked {status}. | "{title}" ditandai {status}. | Undo |

## 5. Goals, tasks, workspaces

| Key | Element | EN | ID | Actions |
|---|---|---|---|---|
| `goals.empty` | empty | What do you want to achieve? Start with one goal. | Apa yang ingin kamu capai? Mulai dari satu tujuan. | New goal |
| `goal.delete.confirm` | modal (alertdialog) | Delete "{title}"? {count, plural, =0 {} one {Its # task will stay without a goal.} other {Its # tasks will stay without a goal.}} | Hapus "{title}"? {count, plural, =0 {} other {{count} tugasnya tetap ada tanpa tujuan.}} | Delete, Cancel |
| `goal.has_tasks` | modal | This goal has {count} tasks. Delete the goal and keep the tasks? | Tujuan ini punya {count} tugas. Hapus tujuan dan simpan tugasnya? | Delete goal, Cancel |
| `tasks.empty` | empty | No tasks match. | Tidak ada tugas yang cocok. | Clear filters |
| `task.anchor_exists` | inline | You already have an anchor task: "{title}". | Kamu sudah punya tugas jangkar: "{title}". | Open it |
| `workspace.limit` | modal | Your plan allows {max} workspaces. | Paketmu mengizinkan {max} ruang kerja. | Manage plan |
| `workspace.archived.readonly` | banner | This workspace is archived and read-only. | Ruang kerja ini diarsipkan dan hanya-baca. | Unarchive |
| `conflict.task` | conflict card | This task changed elsewhere. | Tugas ini berubah di tempat lain. | Reload, Reapply my change |
| `stale.data` | inline | Last updated {time}. | Terakhir diperbarui {time}. | Refresh |

## 6. Schedule and landscape

| Key | Element | EN | ID | Actions |
|---|---|---|---|---|
| `schedule.draft.pending` | banner | A proposed schedule is ready for {range}. | Jadwal usulan siap untuk {range}. | Review |
| `schedule.draft.review` | panel | {placed} placed, {unplaced} couldn't be placed, {moves} moves | {placed} ditempatkan, {unplaced} tidak bisa ditempatkan, {moves} dipindahkan | Apply, Dismiss |
| `schedule.draft.applied` | toast (status) | Schedule updated. | Jadwal diperbarui. | View week |
| `schedule.draft.stale` | banner (alert) | The schedule changed since this proposal. Generate a new one. | Jadwal berubah sejak usulan ini. Buat usulan baru. | Generate |
| `schedule.draft.infeasible` | banner (alert) | This proposal no longer fits your commitments. Generate a new one. | Usulan ini tidak lagi sesuai dengan komitmenmu. Buat usulan baru. | Generate |
| `schedule.sync.no_changes` | toast (status) | Your schedule is up to date. | Jadwalmu sudah terbaru. | — |
| `schedule.sync.busy` | toast (alert) | A schedule update is already running. | Pembaruan jadwal sedang berjalan. | — |
| `schedule.sync.proposal` | toast (status) | {count, plural, one {# change proposed} other {# changes proposed}}. | {count} perubahan diusulkan. | Review |
| `placement.explain` | popover | Placed here because: {reasons} | Ditempatkan di sini karena: {reasons} | — |
| `unplaced.reason.NO_AVAILABLE_SLOT` | inline | No free time fits this task in the range. | Tidak ada waktu kosong yang cukup untuk tugas ini. | — |
| `unplaced.reason.CAPACITY_CAP` | inline | Your daily capacity limit was reached. | Batas kapasitas harianmu tercapai. | — |
| `unplaced.reason.NO_AVAILABLE_ANCHOR_SLOT` | inline | No slot long enough for your anchor. | Tidak ada slot yang cukup untuk tugas jangkarmu. | — |
| `landscape.empty` | empty | Add the things you can't move: classes, meetings, shifts. | Tambahkan hal yang tidak bisa dipindah: kelas, rapat, shift. | Add commitment, Import calendar |
| `landscape.import.preview` | panel | {new} new, {exists} already imported, {warnings} need attention | {new} baru, {exists} sudah diimpor, {warnings} perlu perhatian | Import selected |
| `landscape.import.done` | toast (status) | Imported {count} commitments. | {count} komitmen diimpor. | Undo |
| `landscape.import.warning.rrule_unsupported` | inline | Repeat pattern not supported; imported as a single event. | Pola pengulangan tidak didukung; diimpor sebagai satu acara. | — |
| `landscape.import.warning.all_day_skipped_by_default` | inline | All-day event — not selected by default. | Acara sehari penuh — tidak dipilih secara bawaan. | — |
| `landscape.import.error.ics_invalid` | inline | That file isn't a valid calendar (.ics). | Berkas itu bukan kalender (.ics) yang valid. | Choose another |
| `landscape.override.created` | toast | Change saved for {scope}. | Perubahan disimpan untuk {scope}. | Undo |

## 7. Sync

| Key | Element | EN | ID | Actions |
|---|---|---|---|---|
| `sync.conflict` | conflict card | Your change couldn't be applied because the item changed. | Perubahanmu tidak dapat diterapkan karena item sudah berubah. | Keep current, Reapply mine |
| `sync.rejected` | conflict card | The server didn't accept this change: {reason}. | Server tidak menerima perubahan ini: {reason}. | Discard, Edit |
| `sync.dependency_failed` | conflict card | This change depended on another that failed. | Perubahan ini bergantung pada perubahan lain yang gagal. | Discard |
| `sync.queue.empty` | empty | Nothing waiting to sync. | Tidak ada yang menunggu sinkronisasi. | — |

## 8. Notes and canvas

| Key | Element | EN | ID | Actions |
|---|---|---|---|---|
| `notes.empty` | empty | Capture what you're thinking. Link it to a goal or task. | Catat pikiranmu. Tautkan ke tujuan atau tugas. | New note |
| `note.saving` / `.saved` / `.save_failed` | inline status | Saving… / Saved / Couldn't save. Your text is kept here. | Menyimpan… / Tersimpan / Gagal menyimpan. Teksmu tetap di sini. | Retry |
| `note.conflict` | conflict card | This note changed elsewhere. | Catatan ini berubah di tempat lain. | Keep mine as a new note, Use current |
| `search.too_short` | inline | Type at least 2 characters. | Ketik minimal 2 karakter. | — |
| `search.empty` | empty | No notes match "{q}". | Tidak ada catatan yang cocok dengan "{q}". | — |
| `canvas.empty` | empty | Sketch an idea. Boards can link to goals and tasks. | Gambarkan idemu. Papan dapat ditautkan ke tujuan dan tugas. | New board |
| `canvas.save_failed` | banner (alert) | Couldn't save this board. Your changes are kept here. | Papan ini gagal disimpan. Perubahanmu tetap di sini. | Retry |
| `canvas.offline_readonly` | banner | Offline — this board is read-only. | Offline — papan ini hanya-baca. | — |
| `canvas.conflict` | conflict card | This board changed elsewhere. | Papan ini berubah di tempat lain. | Keep mine as a copy, Use current |
| `canvas.file.rejected` | inline | Only PNG, JPEG, WebP or GIF images up to {max} are allowed. | Hanya gambar PNG, JPEG, WebP, atau GIF hingga {max} yang diizinkan. | — |

## 9. AI

| Key | Element | EN | ID | Actions |
|---|---|---|---|---|
| `ai.not_configured.user` | empty/inline | AI isn't set up on this server. | AI belum diatur di server ini. | Add your own key (if allowed) |
| `ai.not_configured.admin` | empty/inline | AI isn't set up yet. Choose a provider in Settings. | AI belum diatur. Pilih penyedia di Pengaturan. | Open settings |
| `ai.sent.preview` | panel | This is what will be sent to {provider}: | Ini yang akan dikirim ke {provider}: | Send, Cancel |
| `ai.generating` | loading | Drafting milestones… | Menyusun tahapan… | Cancel |
| `ai.proposal.ready` | panel | Review the suggestions. Nothing is added until you accept. | Tinjau saran ini. Tidak ada yang ditambahkan sampai kamu menyetujui. | Edit, Accept, Reject |
| `ai.proposal.accepted` | toast | Added {milestones} milestones and {tasks} tasks. | Menambahkan {milestones} tahapan dan {tasks} tugas. | View goal |
| `ai.output_invalid` | banner (alert) | The AI's answer couldn't be used. Try again or change your description. | Jawaban AI tidak dapat digunakan. Coba lagi atau ubah deskripsimu. | Try again |
| `ai.limit` | toast (alert) | You've reached your AI limit for now. | Kamu mencapai batas AI untuk saat ini. | — |
| `ai.provider_unavailable` | inline (alert) | The AI service isn't reachable right now. Try again in a moment. | Layanan AI sedang tidak dapat dihubungi. Coba lagi sebentar lagi. | Try again |
| `ai.private_ip` | inline | That address isn't allowed. Use a public HTTPS address. | Alamat itu tidak diizinkan. Gunakan alamat HTTPS publik. | — |
| `ai.provider.unavailable` | banner (alert) | The AI service didn't respond. Try again later. | Layanan AI tidak merespons. Coba lagi nanti. | Retry |

## 10. Settings, data, admin, billing

| Key | Element | EN | ID | Actions |
|---|---|---|---|---|
| `data.export.queued` | toast | Preparing your export. We'll show it here when it's ready. | Menyiapkan ekspor. Akan muncul di sini saat siap. | — |
| `data.export.ready` | banner | Your export is ready until {date}. | Ekspormu siap hingga {date}. | Download |
| `data.delete.confirm` | modal (alertdialog) | Delete your account and all data? This can't be undone. Type "{phrase}" and enter your password. | Hapus akun dan semua data? Tindakan ini tidak dapat dibatalkan. Ketik "{phrase}" dan masukkan kata sandimu. | Delete account, Cancel |
| `data.delete.last_admin` | inline | You're the only administrator. Make someone else an administrator first. | Kamu satu-satunya administrator. Jadikan orang lain administrator terlebih dulu. | — |
| `admin.module.switched` | toast | {module} is now {state, select, on {on} other {off}}. | {module} sekarang {state, select, on {aktif} other {nonaktif}}. | Undo |
| `admin.module.dependency` | inline | Turn on {missing} first. | Aktifkan {missing} terlebih dulu. | — |
| `admin.module.required_by` | inline | {dependents} needs this. Turn it off first. | {dependents} membutuhkan ini. Nonaktifkan dulu. | — |
| `admin.policy.saved` | toast | Setting saved. It applies {when}. | Pengaturan disimpan. Berlaku {when}. | — |
| `admin.policy.invalid` | inline | Allowed range: {min}–{max}. | Rentang yang diizinkan: {min}–{max}. | — |
| `admin.secret.set` | inline | Saved. Ends in {last4}. | Tersimpan. Berakhiran {last4}. | Replace, Remove |
| `billing.processing` | banner | We're confirming your payment. This can take a few minutes. | Kami sedang mengonfirmasi pembayaranmu. Bisa memakan waktu beberapa menit. | Refresh |
| `billing.past_due` | banner (alert) | Your last payment didn't go through. Update it by {date} to keep your plan. | Pembayaran terakhir gagal. Perbarui sebelum {date} agar paketmu tetap aktif. | Update payment |
| `billing.over_limit` | banner | You're over your plan's limit. You can keep using what you have. | Kamu melebihi batas paketmu. Kamu masih bisa memakai yang sudah ada. | Manage plan |
| `billing.free` | inline | You're on the free plan. | Kamu memakai paket gratis. | See plans |
| `billing.active` | inline | Your plan is active until {date}. | Paketmu aktif sampai {date}. | Cancel plan |
| `billing.canceled` | inline | Your plan has ended. Your data is kept. | Paketmu telah berakhir. Datamu tetap tersimpan. | See plans |
| `billing.not_configured` | inline (admin) | Billing can't be turned on until the currency and plans are set. | Penagihan belum bisa diaktifkan sebelum mata uang dan paket diatur. | Open policies |

## 11. Requirements

| ID | Requirement | Acceptance |
|---|---|---|
| UXS-01 | Every key above exists in `en.json` and `id.json`; ICU message syntax validates; plurals follow each language's `Intl.PluralRules` categories (Indonesian has only `other`). | CI `check-i18n` |
| UXS-02 | Every surface in `08-frontend.md` §5 renders each of its listed states, covered by a component test and an axe check. | CI |
| UXS-03 | Toasts and banners use the live-region roles in §1; errors are never toast-only when they block the user's task (an inline or card error is also present). | Test |
| UXS-04 | Destructive actions use `alertdialog` with the object's name; Cancel is the initial focus. | Test |
| UXS-05 | Copy review: no blame, sentence case, no exclamation marks, Indonesian reviewed by a native speaker before 1.0 (maintainer action M-L10N). | Review |
