# DPNA CBT, Grup, dan Finalisasi Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menambah sumber CBT eksternal, grup kegiatan berbobot, rincian sumber, serta snapshot finalisasi DPNA tanpa menghapus struktur atau data lama.

**Architecture:** Jenis kegiatan menentukan sumber `manual|cbt`. Nilai CBT disimpan per kegiatan dan peserta; grup DPNA merata-ratakan satu atau beberapa kegiatan. Kalkulasi live tetap menjadi draft, sedangkan finalisasi menyimpan konfigurasi dan hasil sumber per peserta sebagai snapshot berversi.

**Tech Stack:** PHP 8.3, Laravel 13, Eloquent, Livewire 4 anonymous components, Spatie Permission, Bootstrap/Velzon.

**Spec:** `docs/superpowers/specs/2026-09-08-dpna-cbt-grup-finalisasi-design.md`

## Global Constraints

- Semua migration hanya additive; `down()` tidak menjatuhkan tabel/kolom atau menghapus baris.
- Pertahankan `aturan_kegiatan_blok.nilai_masuk_dpna` dan `bobot_nilai_dpna` sebagai fallback legacy.
- Jangan menambah integrasi HTTP aplikasi CBT pada tahap ini.
- Jangan commit, push, pull, membuat branch, atau worktree.
- Tulis pemeriksaan sebelum kode produksi, tetapi jangan menjalankan test/server/render check sesuai preferensi pengguna.
- Semua action Livewire memeriksa ulang otorisasi dan relasi dari database.

---

### Task 1: Skema Additive dan Model Domain

**Files:**
- Create: `database/migrations/2026_09_08_000002_add_dpna_cbt_groups_and_finalization.php`
- Create: `app/Models/NilaiCbtBlok.php`
- Create: `app/Models/GrupDpnaBlok.php`
- Create: `app/Models/AnggotaGrupDpnaBlok.php`
- Create: `app/Models/FinalisasiDpnaBlok.php`
- Create: `app/Models/SnapshotDpnaPeserta.php`
- Modify: `app/Models/JenisKegiatan.php`
- Modify: `app/Models/Blok.php`
- Modify: `app/Models/AturanKegiatanBlok.php`
- Modify: `app/Models/PesertaBlok.php`
- Test: `tests/Unit/NilaiCbtBlokTest.php`

**Interfaces:**
- Produces: `NilaiCbtBlok::hitungNilaiAkhir(float $ujianPertama, bool $mengikutiRemedial, ?float $remedial, float $bobotPertama, float $bobotRemedial): ?float`.
- Produces: relasi `Blok::grup_dpna_blok()`, `Blok::finalisasi_dpna_blok()`, `AturanKegiatanBlok::nilai_cbt_blok()`, dan `JenisKegiatan::sumber_nilai`.

- [ ] Tulis `tests/Unit/NilaiCbtBlokTest.php` untuk hasil 100% ujian pertama, hasil berbobot saat remedial, serta `null` saat remedial wajib tetapi nilainya kosong atau bobot tidak total 100.
- [ ] Buat migration yang menambah `jenis_kegiatan.sumber_nilai` default `manual`, lalu membuat `nilai_cbt_blok`, `grup_dpna_blok`, `anggota_grup_dpna_blok`, `finalisasi_dpna_blok`, dan `snapshot_dpna_peserta`.
- [ ] Gunakan flag `aktif` pada grup dan anggota agar perubahan konfigurasi menonaktifkan data, bukan menghapusnya.
- [ ] Tambahkan `konfigurasi_json` pada finalisasi dan `sumber_json` pada snapshot memakai cast array.
- [ ] Buat model, cast, dan relasi. Biarkan `down()` kosong dengan komentar bahwa rollback destruktif sengaja dilarang.

### Task 2: Kunci Input Manual untuk CBT

**Files:**
- Modify: `app/Support/AksesPertemuanBlok.php`
- Modify: `resources/views/components/blok-operasional/nilai-pertemuan.blade.php`
- Modify: `resources/views/pages/jenis-kegiatan/add_edit.blade.php`
- Modify: `app/Livewire/TableJenisKegiatan.php`
- Test: `tests/Feature/NilaiCbtManualAccessTest.php`

**Interfaces:**
- Consumes: `jenis_kegiatan.sumber_nilai`.
- Produces: `AksesPertemuanBlok::sumberNilaiCbt(int $pertemuanId): bool` dan `bolehIsiNilai()` yang selalu `false` untuk CBT.

- [ ] Tulis feature test bahwa kegiatan manual tetap dapat ditulis, sedangkan `simpan`, `importNilai`, dan `unduhTemplate` CBT ditolak server-side.
- [ ] Tambahkan pilihan `Manual` dan `CBT eksternal` pada form Jenis Kegiatan, validasi `Rule::in(['manual','cbt'])`, dan tampilkan sumber pada tabel master.
- [ ] Ubah `bolehIsiNilai()` agar memeriksa sumber kegiatan setelah memeriksa hak atas pertemuan.
- [ ] Muat sumber nilai pada komponen; untuk CBT tampilkan banner read-only dan status data sinkronisasi, tanpa form, tombol import, atau template.
- [ ] Pertahankan nilai manual lama; perubahan jenis ke CBT tidak menghapus `nilai_pertemuan_blok`.

### Task 3: Kalkulasi CBT dan Grup DPNA

**Files:**
- Modify: `app/Support/PerhitunganDpnaBlok.php`
- Modify: `app/Models/NilaiCbtBlok.php`
- Test: `tests/Unit/PerhitunganDpnaBlokTest.php`
- Test: `tests/Feature/PerhitunganGrupDpnaTest.php`

**Interfaces:**
- Produces: `PerhitunganDpnaBlok::rataRataGrup(Collection $nilai): ?float`.
- Produces: `rekap()` keys `grup`, `menggunakan_grup`, `nilai_grup`, dan `sumber_detail`, sambil mempertahankan `kegiatan` dan `nilai_kegiatan` untuk fallback.

- [ ] Tambahkan unit test grup satu anggota, rata-rata sama beberapa anggota, dan anggota kosong menghasilkan `null`.
- [ ] Tambahkan feature test bahwa kegiatan CBT membaca `nilai_cbt_blok.nilai_akhir`, kegiatan manual tetap merata-ratakan rekap pertemuan, dan grup campuran belum lengkap bila satu anggota kosong.
- [ ] Muat nilai CBT untuk seluruh peserta dan aturan dalam satu query.
- [ ] Bentuk detail sumber manual per pertemuan dan detail CBT per ujian.
- [ ] Jika grup aktif ada, hitung nilai setiap grup dari anggota aktif dan gunakan bobot grup pada `nilaiAkhir()`.
- [ ] Jika tidak ada grup aktif, gunakan `nilai_masuk_dpna` dan `bobot_nilai_dpna` lama tanpa perubahan hasil.

### Task 4: Konfigurasi Grup pada Halaman DPNA

**Files:**
- Modify: `resources/views/pages/dpna-blok/detail.blade.php`
- Modify: `app/Livewire/TableDpnaBlok.php`
- Test: `tests/Feature/DpnaGrupConfigurationTest.php`

**Interfaces:**
- Consumes: model grup dan anggota.
- Produces: Livewire methods `tambahGrup()`, `hapusGrup(int $index)`, `simpanGrup()`, dan `buatGrupDariKonfigurasiLama()`.

- [ ] Tulis feature test validasi nama, bobot, anggota kosong, anggota ganda, total bobot plus kehadiran tepat 100, serta blok mismatch.
- [ ] Muat grup aktif ke state sebagai `id`, `nama`, `bobot`, `urutan`, dan daftar `aturan_ids`.
- [ ] Simpan grup dan anggota memakai `updateOrCreate`; tandai baris lama `aktif=false` alih-alih menghapus.
- [ ] Tambahkan aksi migrasi aman dari konfigurasi lama: satu grup per kegiatan legacy aktif, bobot disalin, kolom lama tidak berubah.
- [ ] Ganti matriks dan detail agar memakai kolom grup saat grup aktif tersedia; fallback tetap memakai kolom kegiatan lama.
- [ ] Ganti ringkasan tabel daftar DPNA agar membaca total bobot grup bila tersedia, fallback ke total legacy.

### Task 5: Finalisasi, Buka Kembali, dan Snapshot

**Files:**
- Create: `app/Support/FinalisasiDpnaBlokService.php`
- Modify: `resources/views/pages/dpna-blok/detail.blade.php`
- Modify: `database/migrations/2026_09_08_000002_add_dpna_cbt_groups_and_finalization.php`
- Test: `tests/Feature/FinalisasiDpnaBlokTest.php`

**Interfaces:**
- Produces: `FinalisasiDpnaBlokService::finalisasi(Blok $blok, User $user): FinalisasiDpnaBlok`.
- Produces: `FinalisasiDpnaBlokService::bukaKembali(Blok $blok, User $user, string $alasan): FinalisasiDpnaBlok`.

- [ ] Tulis feature test finalisasi ditolak bila ada peserta/sumber belum lengkap atau user tanpa `dpna-blok:finalisasi`.
- [ ] Tulis test finalisasi membuat versi, konfigurasi JSON, dan satu snapshot per peserta; koreksi nilai operasional sesudahnya tidak mengubah snapshot.
- [ ] Tulis test buka kembali memerlukan alasan dan permission, lalu finalisasi berikutnya membuat versi baru tanpa menimpa versi lama.
- [ ] Daftarkan permission `dpna-blok:finalisasi` pada migration yang sama, terkait menu DPNA dengan `main_permission=false`, dan berikan ke role `admin` serta `pengelola`.
- [ ] Implementasikan service dengan transaksi dan `lockForUpdate()` pada riwayat versi blok.
- [ ] Tambahkan badge Draft/Final, tombol Finalisasi, form alasan buka kembali, dan riwayat versi pada halaman.
- [ ] Saat final, matriks utama membaca snapshot terbaru. Saat dibuka kembali, matriks membaca kalkulasi live draft.
- [ ] Nonaktifkan penyimpanan konfigurasi bobot/grup selama status final; cek ulang pada server.

### Task 6: Nilai Sumber dan Dokumentasi

**Files:**
- Modify: `resources/views/pages/dpna-blok/detail.blade.php`
- Modify: `task/task_5.md`
- Modify: `CLAUDE.md`
- Test: `tests/Feature/DpnaNilaiSumberTest.php`

**Interfaces:**
- Consumes: `baris[*].sumber_detail` untuk draft dan `snapshot_dpna_peserta.sumber_json` untuk final.

- [ ] Tulis feature test detail mahasiswa menampilkan daftar pertemuan manual, nilai CBT ujian pertama/remedial, bobot, status lengkap, dan referensi sinkronisasi.
- [ ] Buat detail bertingkat: Kehadiran, Grup, Anggota Kegiatan, lalu nilai pertemuan atau CBT.
- [ ] Pastikan semua output menggunakan escaping Blade dan tidak merender JSON mentah.
- [ ] Dokumentasikan sumber CBT, fallback legacy, grup rata-rata sama, snapshot final, dan buka kembali pada task serta CLAUDE.md.
- [ ] Lakukan review statis untuk query kolektif, N+1, route/action authorization, migration additive, dan tidak adanya operasi drop/truncate pada file baru.
- [ ] Jangan menjalankan test, server, migration, formatter, atau render check; laporkan status belum diverifikasi otomatis.
