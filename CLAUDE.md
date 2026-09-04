# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project context

This is a Laravel SIAKAD application for a medical-school block learning system (`Sistem Blok Kedokteran`). Development is task-driven: before changing feature code, read `task/readme_first.md` and the relevant `task/task_*.md` in order. `AGENT.md` has the longer project rules; this file is the concise operating guide.

Main stack:

- PHP 8.3, Laravel 13, Laravel Breeze auth scaffolding.
- Livewire 4 view-based/anonymous components and class components.
- Spatie Laravel Permission for roles/permissions.
- Livewire PowerGrid for CRUD tables.
- Maatwebsite Excel and OpenSpout for imports/exports.
- Vite, Tailwind, Bootstrap/Velzon assets, Remix Icon.

## Common commands

Install/setup:

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
```

Project setup script, if a full reset/setup is intended:

```bash
composer run setup
```

Development servers/workers/logs/assets together:

```bash
composer run dev
```

Individual development commands:

```bash
php artisan serve
php artisan queue:listen --tries=1 --timeout=0
php artisan pail --timeout=0
npm run dev
```

Build assets:

```bash
npm run build
```

Tests:

```bash
composer run test
php artisan test
php artisan test tests/Feature/Auth/AuthenticationTest.php
php artisan test --filter=authenticated_users_can_login
```

Formatting/linting:

```bash
vendor/bin/pint
vendor/bin/pint --dirty
```

Useful Laravel checks after changing routes/config/views:

```bash
php artisan route:list
php artisan config:clear
php artisan view:cache
```

## Architecture

### Routing and page components

- Main routes are in `routes/web.php`; auth routes are in `routes/auth.php`.
- Authenticated pages are grouped under `Route::middleware(['auth'])` and registered with `Route::livewire(...)`.
- Livewire page aliases use namespaces configured in `config/livewire.php`: `pages::...` maps to `resources/views/pages`, and `layouts::...` maps to `resources/views/layouts`.
- Most CRUD pages follow `resources/views/pages/{domain}/index.blade.php` plus `resources/views/pages/{domain}/add_edit.blade.php`.
- Add/edit pages often use anonymous Livewire components directly in Blade with `new #[Layout('layouts.app')] class extends Component { ... }`.

### Domain model

Core academic domains live in `app/Models` and use Indonesian names/fields:

- Master data: `Prodi`, `Dosen`, `Mahasiswa`, `Semester`, `MataKuliah`, `JenisKegiatan`, `KomponenPenilaian`.
- Block setup: `Blok`, `AturanKegiatanBlok`, `MateriBlok`, `MateriRinciBlok`.
- Block operations (all hang off `blok`): `PesertaBlok`, `KelompokBlok`, `AnggotaKelompokBlok`, `PertemuanBlok`, `DosenPertemuanBlok`.
- Teaching links: `LampiranMateriBlok` stores modul/video URLs (no file uploads — the project has no permanent file-storage pattern). `materi_rinci_blok_id` is always set; `pertemuan_blok_id` NULL means the default that applies to every kelompok on that materi, while a set value means an addition owned by one kelompok's meeting. Same default/override idiom as `materi_rinci_blok.tanggal_rencana`. Deliberately has no unique business key, so plain `create()`/`delete()` are safe here.
- Execution records: `PresensiPertemuanBlok` (one row per `peserta_blok` per `pertemuan_blok`, status `hadir|sakit|izin|alpa`), `MonitoringPertemuanBlok` (the jurnal, one row per `pertemuan_blok`), and `NilaiPertemuanBlok` (one row per `peserta_blok` per rubric komponen per `pertemuan_blok`). All three intentionally have **no soft deletes** because each has a unique business key and is written through `updateOrCreate` — a soft-deleted row would occupy the unique index. Presensi and nilai are keyed to `peserta_blok_id`, not `mahasiswa_id`, so a non-participant can never be recorded.
- Assessment rubric: `KomponenPenilaian` (master) → `KomponenPenilaianKegiatan` (default per `jenis_kegiatan`) → `KomponenPenilaianBlok` (per `aturan_kegiatan_blok`, the layer `NilaiPertemuanBlok` points at).
- `Kelas` is an optional *rombel* (parallel section) inside one `Blok`. It owns no participants, groups, or meetings — it is only a label on `peserta_blok.kelas_id` and `kelompok_blok.kelas_id`. See `task/task_4.md`; do not turn it back into an operational container.
- Several tables use non-standard primary keys such as `id_prodi`, `id_dosen`, `id_mahasiswa`, and `id_kelas`; models define `$primaryKey`, and relationships specify foreign/owner keys explicitly.
- Master academic models commonly use soft deletes. Multi-table writes in block/class workflows are wrapped in `DB::transaction(...)`.
- `peserta_blok`, `kelompok_blok`, `pertemuan_blok`, and `kelas` all combine soft deletes with a unique business key, and a soft-deleted row still occupies that unique index. Save through `Model::withTrashed()->firstOrNew([...business key...])` then `restore()` if trashed — plain `updateOrCreate()` will hit a unique-constraint violation.

### Livewire and table pattern

- CRUD index tables use Livewire PowerGrid classes in `app/Livewire/Table{Domain}.php` rather than manual table markup.
- PowerGrid components usually define `setUp()`, `datasource()`, `fields()`, `columns()`, optional `filters()`, and `actions()`.
- Table action links pass encrypted IDs with `Crypt::encrypt(...)`; edit routes use `wire:navigate`.
- Delete handlers decrypt IDs in `try/catch`, abort on invalid encrypted IDs, validate domain constraints, then dispatch `notify` or flash a success message.

### Permissions and navigation

- Roles/permissions use Spatie Permission. Seeded base roles are `admin`, `pengelola`, `dosen`, and `mahasiswa`.
- Menus are database-driven through `menus` and `permissions`; the menu's main permission is marked by `permissions.main_permission = 1`.
- `resources/views/layouts/navbar.blade.php` loads parent menus and children from the database and displays them with `@can(...)` checks.
- The navbar query inner-joins `permissions` on `main_permission = 1`, so a parent menu without its own permission row never renders and a child without one throws on `@can($i->main_permission->name)`. Always create menu and permission together. Register menus through a migration (see `2026_08_18_000007_register_portal_dosen_menu.php`) and grant with `Role::findOrCreate(...)`, not `Role::whereIn(...)->get()` — on a fresh `migrate --seed` the roles do not exist yet when migrations run, so the grant would silently no-op.
- New feature pages need route/menu/permission alignment. Do not rely only on hidden navbar items for access control; lock routes or page actions with the relevant Spatie permission where needed.

### Block-learning workflow

- `Blok` owns activity rules (`AturanKegiatanBlok`) and teaching material hierarchy (`MateriBlok` → `MateriRinciBlok`).
- `resources/views/pages/blok/add_edit.blade.php` is the complex block setup form: information, activity rules, material details, and review tabs. It can copy structure from existing blocks — but deliberately never copies `materi_rinci_blok.tanggal_rencana`, because planned dates are semester-specific.
- `materi_rinci_blok` carries the planned schedule (`tanggal_rencana`, `jam_mulai_rencana`, `jam_selesai_rencana`). It is a *default*: each `kelompok_blok` may override date/time per meeting, since practicals rotate between groups.
- Mata kuliah mapping stays on `mata_kuliah.blok_id`, chosen from the Blok form.
- `resources/views/pages/blok-operasional/detail.blade.php` is a thin shell (summary, tab nav, modal script). Each tab is its own Livewire component in `resources/views/components/blok-operasional/`: `peserta`, `rombel`, `kelompok`, `pertemuan`.
- The `pertemuan` tab maps dosen pengampu and schedule per group per `materi_rinci_blok`, prefilled from the template plan. The `kelompok` tab has a `Bagi Merata` action that creates N groups and distributes active participants evenly.
- Participant/candidate/member search and sorting run in SQL with pagination, not by filtering PHP collections.
- Current implementation forces block activities to be group-based (`perlu_kelompok = true`) in block setup, so `pertemuan_blok.kelompok_blok_id` is NOT NULL.
- Modul/video links are managed through `resources/views/components/blok-operasional/lampiran-materi.blade.php`, a shared component with two modes: no `pertemuan_blok_id` edits the materi-level default (pengelola only), a set `pertemuan_blok_id` edits one meeting's own list while showing the inherited defaults read-only. All write paths re-check authorization through `app/Support/AksesPertemuanBlok.php` because Livewire action arguments come from the client.
- Presensi and jurnal live in two shared components, `presensi-pertemuan.blade.php` and `jurnal-pertemuan.blade.php`, each taking only a `pertemuan_blok_id`. Both are embedded together in one `Monitoring` form from the dosen page (`pertemuan-saya`) and the pengelola `monitoring` tab; one save action dispatches to both components. Session participants come from `anggota_kelompok_blok` of the meeting's kelompok; the save loop iterates the server-side member list, never the client-supplied `status` array keys.
- Validation locks execution data: `monitoring_pertemuan_blok.divalidasi_pada` being non-null blocks every role from editing presensi and jurnal for that meeting. Pengelola/admin must call `bukaValidasi()` first. Saving a jurnal also advances `pertemuan_blok.status` via `MonitoringPertemuanBlok::STATUS_PERTEMUAN` — that is what finally uses the `selesai` and `batal` enum values. Both writes happen in one `DB::transaction`.
- `aturan_kegiatan_blok.perlu_presensi` already existed and is honoured: the combined Monitoring action stays available for jurnal, while the presensi component shows a warning banner when attendance is not required.
- Assessment uses three layers, not one table (see `task/task_5.md`): `komponen_penilaian` (global master) → `komponen_penilaian_kegiatan` (default rubric per `jenis_kegiatan`, template only) → `komponen_penilaian_blok` (the rubric owned by one `aturan_kegiatan_blok`). `nilai_pertemuan_blok` hangs off the **per-blok** layer so each komponen's `nilai_min`/`nilai_maks` is frozen for that blok; editing the standard later never re-interprets historical scores. Scoring uses per-komponen min/max bounds, **not percentage weights** — raw scores are summed, so don't add weighting math.
- **Nilai is deliberately NOT locked by `monitoring_pertemuan_blok.divalidasi_pada`.** Presensi and jurnal lock on validation; penilaian does not, because dosen often score after validation and correcting a score is normal work. That rule lives alone in `AksesPertemuanBlok::bolehIsiNilai()`, which does not call `terkunci()` — do not merge the two.
- Three deliberately different soft-delete choices: `komponen_penilaian_kegiatan` has none (business key + `updateOrCreate`); `komponen_penilaian_blok` has soft deletes so dropping a komponen doesn't cascade away its scores, which means it must be saved through `withTrashed()->firstOrNew()` + `restore()`; `nilai_pertemuan_blok` has none because it is written by `updateOrCreate` over a business key. A cleared score is hard-deleted, so "row exists" means "sudah dinilai" and completeness badges only need `withCount`.
- `nilai_pertemuan_blok.komponen_penilaian_blok_id` cascades so blok permanent-delete and `migrate:fresh` keep working; the guard against silent score loss is `lolosPengecekanNilaiTersimpan()` in the Blok form, not the foreign key.
- `aturan_kegiatan_blok.perlu_penilaian` gates the Nilai button and tab. The flag and the rubric are separate, so both contradictory states are handled: flag on with empty rubric is refused on save (badge `rubrik kosong`), flag off with scores present still renders them behind a warning banner.
- The per-blok rubric is edited in a `Penilaian` tab inside `resources/views/pages/blok/add_edit.blade.php`, giving that form a fourth state layer (`aturan[i].komponen[j]` alongside `aturan[i].materi[j].rinci[k]`). Unlike `tanggal_rencana`, the rubric *is* copied by `copyFromBlok()`.
- Scoring lives in `resources/views/components/blok-operasional/nilai-pertemuan.blade.php`, a matrix of mahasiswa × komponen embedded as a third tab in the same `#pelaksanaanModal` used by `pertemuan-saya` and the `monitoring` tab. Valid modal modes are centralised in each host's `MODE_PELAKSANAAN` constant. Validation rules are built per matrix cell because bounds differ per komponen, and bounds are always read from the database, never from the HTML `min`/`max` attributes.
- Two portal pages are scoped to the logged-in user rather than being master-data CRUD: `resources/views/pages/pertemuan-saya/index.blade.php` (dosen, resolves `auth()->user()->dosen`) and `resources/views/pages/materi-saya/index.blade.php` (mahasiswa, read-only, walks `peserta_blok` → `anggota_kelompok_blok` → `kelompok_blok` → `pertemuan_blok`). `dosen.user_id` and `mahasiswa.user_id` are nullable, so always abort when the relation is missing.

### DPNA (nilai akhir blok)

- One score per meeting is aggregated twice. `rekap_nilai_pertemuan_blok` (no soft deletes, unique on `pertemuan_blok_id` + `peserta_blok_id`, written by `updateOrCreate` inside the nilai component's save) holds `total` plus `nilai_akhir` normalised to 0–100 via `RekapNilaiPertemuanBlok::hitungNilaiAkhir()`. `app/Support/PerhitunganDpnaBlok.php` then averages those per kegiatan and applies the DPNA weights. Nothing is stored at the DPNA level — the matrix is recomputed on every page load, so there is no finalisation trail.
- Weights live in two places: `blok.kehadiran_masuk_dpna` / `blok.bobot_kehadiran_dpna` for attendance, and `aturan_kegiatan_blok.nilai_masuk_dpna` / `bobot_nilai_dpna` per kegiatan. `dpna-blok/detail.blade.php` enforces that every active source has a weight above 0, that inactive sources are 0, and that active weights total exactly 100.
- `nilaiAkhir()` returns null as soon as **any** active source is incomplete, so a single missing presensi row or unscored komponen blanks the whole column. Completeness is deliberately strict: a partial DPNA is worse than a visibly empty one.
- **How many meetings a participant should have is derived, never stored.** It equals `withCount('materi_rinci_blok')` on `AturanKegiatanBlok` — one `pertemuan_blok` per materi rinci per kelompok, and a participant sits in exactly one kelompok per kegiatan. The old `aturan_kegiatan_blok.jumlah_pertemuan` column was dropped by `2026_08_24_000003` precisely to remove the second source of truth; do not bring it back. `pertemuan_blok_count` is not a substitute — that counts every kelompok's meetings.
- The `materi_rinci_blok` relation on `AturanKegiatanBlok` is a `HasManyThrough` that filters `materi_blok.deleted_at` by hand, because `hasManyThrough` applies only the far model's global scopes, not the intermediate's.

### Imports and exports

- Imports live in `app/Imports` and use Maatwebsite Excel collection imports with heading rows, row-level Indonesian validation messages, and `DB::transaction(...)`.
- PowerGrid XLS export customization lives in `app/Exports/PowerGridExportToXLS.php` and writes temporary XLSX files under `storage/framework/powergrid-temp` via OpenSpout.
- Nilai per pertemuan has a template download + import inside `components/blok-operasional/nilai-pertemuan.blade.php`, so dosen pengampu and pengelola get the identical feature from `pertemuan-saya` and the `monitoring` tab — the only gate is `AksesPertemuanBlok::bolehIsiNilai()`. `app/Imports/NilaiPertemuanImport.php` only reads and validates; writing stays in the component's `tulisNilai()`, which `simpan()` also calls, so the total and `rekap_nilai_pertemuan_blok` math has one implementation.
- `NilaiPertemuanImport::kunciKomponen()` is the single source for komponen column names and is called by **both** the export and the import, so the header written can never drift from the header read. Keys are `Str::slug($kode, '_')` (idempotent, so the key doubles as the header text), disambiguated with the `komponen_penilaian_blok` id when a kode is blank or two kodes slug alike. Nilai bounds are deliberately kept **out** of the column name so a file downloaded before a rubric edit still imports.
- The nilai template has two sheets and the import therefore **must** implement `WithMultipleSheets` mapping index `0`. Without it `Reader::buildSheetImports()` does `array_fill(0, getSheetCount(), $import)` and feeds the "Petunjuk" sheet to the handler too. Index, not sheet name, so a file re-saved as CSV still imports.
- Import semantics for nilai, mirrored in the Petunjuk sheet and the UI helper text: a blank cell **deletes** that komponen's score (same as clearing the on-screen input), while a `nim` row absent from the file is **left untouched** — so a trimmed file never wipes other students. One bad row rejects the whole file, because reading completes before `tulisNilai()` runs.

### UI conventions

- Main layout is `resources/views/layouts/app.blade.php`; navbar/sidebar is `resources/views/layouts/navbar.blade.php`.
- UI text is primarily Indonesian.
- Use Bootstrap/Velzon layout classes already present: `page-title-box`, `row`, `col-*`, `card`, `card-header`, `card-body`, badges, tabs, and forms.
- Use Remix Icon for actions (`ri-add-box-fill`, `ri-file-edit-line`, `ri-delete-bin-line`, `ri-save-line`). Avoid emoji in buttons.
- Forms commonly use a fixed/floating save button with `.fab-save` and label `SIMPAN`.

### Error reporting

- `bootstrap/app.php` configures exception reporting to `App\ErrorReporter::send(...)` with a cache-based 2-minute duplicate throttle. Be careful when changing exception handling because reporter failures are intentionally swallowed to avoid loops.
