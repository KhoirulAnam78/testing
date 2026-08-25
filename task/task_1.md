# Task 1 - Rancangan Database Fondasi SIAKAD Sistem Blok

## Tujuan

Membangun fondasi database awal untuk Sistem Informasi Akademik (SIAKAD) dengan metode pembelajaran sistem blok. Task ini berfokus pada master data akademik, profil pengguna akademik, semester, jenis kegiatan blok, dan struktur dasar blok.

Belum termasuk: presensi, logbook, penilaian, jadwal detail, plotting dosen, upload modul, pembagian kelompok, dan rekap laporan.

## Status Implementasi

- [x] Migration `prodi`
- [x] Migration `dosen`
- [x] Migration `mahasiswa`
- [x] Migration `semester`
- [x] Migration `mata_kuliah`
- [x] Migration `jenis_kegiatan`
- [x] Migration `blok`
- [x] Migration `aturan_kegiatan_blok`
- [x] Model dan relasi Eloquent fondasi akademik
- [x] Seeder role awal, prodi awal, dan jenis kegiatan awal
- [x] Validasi `php -l`, `php artisan migrate`, dan `php artisan db:seed`

## Status Implementasi Fitur UI

- [x] CRUD Program Studi
- [x] CRUD Semester
- [x] CRUD Jenis Kegiatan
- [x] CRUD Dosen
- [x] CRUD Mahasiswa
- [x] CRUD Mata Kuliah
- [x] CRUD Blok dan Aturan Kegiatan Blok

Catatan validasi:

- `php artisan test` sudah dicoba, tetapi suite existing masih gagal pada scaffolding lama di luar batas Task 1: test mengacu ke `Livewire\Volt\Volt`, view `profile`, dan query navbar/permission lama. Migration Task 1 sendiri sudah berhasil `Ran` dan seed Task 1 berhasil dieksekusi.
- Lanjutan fitur UI Task 1 menambahkan route, halaman index/form, dan tabel PowerGrid untuk Semester, Jenis Kegiatan, Dosen, Mahasiswa, Mata Kuliah, serta Blok dan Aturan Kegiatan Blok.
- Validasi lanjutan UI Task 1: `php -l` untuk komponen tabel dan form baru berhasil, `php artisan view:cache` berhasil, `php artisan view:clear` berhasil, dan route akademik baru sudah terdaftar melalui `php artisan route:list`.

## Prinsip Desain

- Nama tabel dan field domain akademik menggunakan bahasa Indonesia.
- Field bawaan Laravel tetap memakai `created_at`, `updated_at`, dan `deleted_at`.
- Autentikasi tetap memakai tabel `users`.
- Hak akses memakai Spatie Permission yang sudah tersedia.
- Profil akademik dipisah dari `users` melalui tabel `dosen` dan `mahasiswa`.
- Data master utama memakai soft delete.
- Relasi memakai foreign key eksplisit.
- Field pencarian utama diberi index atau unique constraint.

## Tabel Existing yang Digunakan

### users

Tabel autentikasi Laravel.

Relasi:

- `users.id` ke `dosen.user_id`
- `users.id` ke `mahasiswa.user_id`
- Role dan permission dikelola oleh Spatie Permission

### roles dan permissions

Tabel dari Spatie Permission.

Role awal yang disarankan:

- `admin`
- `pengelola`
- `dosen`
- `mahasiswa`

## Tabel Baru

### 1. prodi

Menyimpan master program studi.

| Field | Tipe | Keterangan |
| --- | --- | --- |
| id_prodi | bigint unsigned | Primary key |
| kode | varchar unique | Kode prodi, contoh: `PSPD` |
| nama | varchar | Nama program studi |
| jenjang | varchar nullable | Dipilih dari referensi `config/akademik.php` key `jenjang_pendidikan`: S1, Profesi, Spesialis, Sub-Spesialis, S2, S3 |
| status | enum | `aktif`, `nonaktif` |
| created_at | timestamp nullable | Timestamp Laravel |
| updated_at | timestamp nullable | Timestamp Laravel |
| deleted_at | timestamp nullable | Soft delete |

Constraint:

- `kode` unique
- index `status`

Catatan:

- Tabel ini dipakai untuk memisahkan data mahasiswa, mata kuliah, dan blok berdasarkan program studi.
- Relasi dosen ke prodi dibuat nullable karena dosen dapat mengajar lintas prodi.
- `jenjang` bukan tabel master, melainkan daftar referensi statis pada `config/akademik.php`
  (key `jenjang_pendidikan`) dengan bentuk `kode => label`. Kolom tetap `varchar` dan
  menyimpan kode (contoh: `S1`, `Profesi`).
- Form prodi menampilkan `jenjang` sebagai dropdown dari referensi tersebut dan
  memvalidasinya dengan `Rule::in(array_keys(config('akademik.jenjang_pendidikan')))`.
  Tabel prodi memakai `Filter::select` dari referensi yang sama.
- Menambah atau mengubah pilihan jenjang dilakukan dengan mengedit `config/akademik.php`,
  bukan melalui UI.

### 2. dosen

Menyimpan data profil dosen atau tutor.

| Field | Tipe | Keterangan |
| --- | --- | --- |
| id_dosen | bigint unsigned | Primary key |
| user_id | bigint unsigned nullable | Relasi opsional ke `users.id` |
| prodi_id | bigint unsigned nullable | Relasi opsional ke `prodi.id_prodi` |
| nidn | varchar nullable unique | Nomor Induk Dosen Nasional |
| nip | varchar nullable unique | Nomor Induk Pegawai |
| nama | varchar | Nama dosen |
| email | varchar nullable | Email dosen |
| no_hp | varchar nullable | Nomor handphone |
| gelar_depan | varchar nullable | Contoh: dr. |
| gelar_belakang | varchar nullable | contoh : M.Kes., Sp.PD |
| bidang_keahlian | varchar nullable | Bidang keahlian dosen |
| status | enum | `aktif`, `nonaktif` |
| created_at | timestamp nullable | Timestamp Laravel |
| updated_at | timestamp nullable | Timestamp Laravel |
| deleted_at | timestamp nullable | Soft delete |

Constraint:

- `user_id` unique nullable, foreign key ke `users.id`, null on delete
- `prodi_id` foreign key ke `prodi.id_prodi`, null on delete
- `nidn` unique nullable
- `nip` unique nullable

### 3. mahasiswa

Menyimpan data profil mahasiswa.

| Field | Tipe | Keterangan |
| --- | --- | --- |
| id_mahasiswa | bigint unsigned | Primary key |
| user_id | bigint unsigned nullable | Relasi opsional ke `users.id` |
| prodi_id | bigint unsigned | Relasi ke `prodi.id_prodi` |
| nim | varchar unique | Nomor Induk Mahasiswa |
| nama | varchar | Nama mahasiswa |
| email | varchar nullable | Email mahasiswa |
| no_hp | varchar nullable | Nomor handphone |
| angkatan | unsigned small integer | Tahun angkatan |
| status | enum | `aktif`, `nonaktif`, `lulus`, `cuti` |
| created_at | timestamp nullable | Timestamp Laravel |
| updated_at | timestamp nullable | Timestamp Laravel |
| deleted_at | timestamp nullable | Soft delete |

Constraint:

- `user_id` unique nullable, foreign key ke `users.id`, null on delete
- `prodi_id` foreign key ke `prodi.id_prodi`, restrict on delete
- `nim` unique

Catatan:

- Setiap mahasiswa wajib terhubung ke satu prodi.
- Prodi tidak dapat dihapus jika masih memiliki mahasiswa.
- Relasi ini menjadi dasar filter mahasiswa berdasarkan prodi, angkatan, peserta blok, dan laporan akademik.
### 4. semester

Menyimpan periode semester akademik. Tabel ini sekaligus membawa informasi tahun akademik melalui field `tahun` dan `kode`, sehingga tidak diperlukan tabel `tahun_akademik` terpisah.

Contoh data:

- `nama`: `ganjil`, `tahun`: `2025`, `kode`: `20251`
- `nama`: `genap`, `tahun`: `2025`, `kode`: `20252`

| Field | Tipe | Keterangan |
| --- | --- | --- |
| id_semester | bigint unsigned | Primary key |
| nama | enum | `ganjil`, `genap`, `pendek` |
| tahun | unsigned small integer | Tahun akademik/periode, contoh: `2025` |
| kode | varchar unique | Kode semester, contoh: `20251`, `20252` |
| tanggal_mulai | date nullable | Tanggal mulai semester |
| tanggal_selesai | date nullable | Tanggal selesai semester |
| is_aktif | boolean | Penanda semester aktif |
| created_at | timestamp nullable | Timestamp Laravel |
| updated_at | timestamp nullable | Timestamp Laravel |
| deleted_at | timestamp nullable | Soft delete |

Constraint:

- `kode` unique
- unique gabungan `tahun` + `nama`
- index `tahun`
- index `is_aktif`

Catatan:

- Secara aplikasi, idealnya hanya satu semester yang aktif.
- Format kode disepakati: `YYYY1` untuk ganjil, `YYYY2` untuk genap, dan `YYYY3` untuk pendek jika dibutuhkan.
- Contoh: `20251` berarti semester ganjil tahun 2025, `20252` berarti semester genap tahun 2025.

### 5. mata_kuliah

Menyimpan master mata kuliah yang dapat dipetakan ke blok.

| Field | Tipe | Keterangan |
| --- | --- | --- |
| id | bigint unsigned | Primary key |
| prodi_id | bigint unsigned | Relasi ke `prodi.id_prodi` |
| kode | varchar | Kode mata kuliah |
| nama | varchar | Nama mata kuliah |
| sks | decimal(4,1) | Jumlah SKS |
| deskripsi | text nullable | Deskripsi mata kuliah |
| status | enum | `aktif`, `nonaktif` |
| created_at | timestamp nullable | Timestamp Laravel |
| updated_at | timestamp nullable | Timestamp Laravel |
| deleted_at | timestamp nullable | Soft delete |

Constraint:

- `prodi_id` foreign key ke `prodi.id_prodi`, restrict on delete
- unique gabungan `prodi_id` + `kode`
- index `status`

### 6. jenis_kegiatan

Menyimpan master jenis kegiatan pembelajaran blok.

| Field | Tipe | Keterangan |
| --- | --- | --- |
| id | bigint unsigned | Primary key |
| kode | varchar unique | Contoh: `TUTORIAL`, `PRAKTIKUM`, `KULIAH`, `SKILLS_LAB` |
| nama | varchar | Nama jenis kegiatan |
| jumlah_pertemuan_default | unsigned small integer | Standar jumlah pertemuan |
| durasi_menit_default | unsigned small integer | Standar durasi per pertemuan |
| deskripsi | text nullable | Deskripsi jenis kegiatan |
| status | enum | `aktif`, `nonaktif` |
| created_at | timestamp nullable | Timestamp Laravel |
| updated_at | timestamp nullable | Timestamp Laravel |
| deleted_at | timestamp nullable | Soft delete |

Constraint:

- `kode` unique
- index `status`

### 7. blok

Menyimpan data utama blok pada semester tertentu.

| Field | Tipe | Keterangan |
| --- | --- | --- |
| id | bigint unsigned | Primary key |
| prodi_id | bigint unsigned | Relasi ke `prodi.id_prodi` |
| semester_id | bigint unsigned | Relasi ke `semester.id_semester` |
| mata_kuliah_id | bigint unsigned | Relasi ke `mata_kuliah.id` |
| kode | varchar | Kode blok |
| nama | varchar | Nama blok |
| sks | decimal(4,1) | Total SKS blok |
| tanggal_mulai | date nullable | Tanggal mulai blok |
| tanggal_selesai | date nullable | Tanggal selesai blok |
| deskripsi | text nullable | Deskripsi blok |
| status | enum | `draft`, `aktif`, `selesai`, `arsip` |
| created_at | timestamp nullable | Timestamp Laravel |
| updated_at | timestamp nullable | Timestamp Laravel |
| deleted_at | timestamp nullable | Soft delete |

Constraint:

- `prodi_id` foreign key ke `prodi.id_prodi`, restrict on delete
- `semester_id` foreign key ke `semester.id_semester`, restrict on delete
- `mata_kuliah_id` foreign key ke `mata_kuliah.id`, restrict on delete
- unique gabungan `prodi_id` + `semester_id` + `kode`
- index `status`
- index `tanggal_mulai` dan `tanggal_selesai`

Catatan:

- Satu blok hanya terhubung ke satu mata kuliah melalui `mata_kuliah_id`.
- Satu mata kuliah tetap dapat memiliki banyak blok pada semester berbeda.

### 8. aturan_kegiatan_blok

Menyimpan konfigurasi jenis kegiatan yang berlaku pada suatu blok.

| Field | Tipe | Keterangan |
| --- | --- | --- |
| id | bigint unsigned | Primary key |
| blok_id | bigint unsigned | Relasi ke `blok.id` |
| jenis_kegiatan_id | bigint unsigned | Relasi ke `jenis_kegiatan.id` |
| durasi_menit | unsigned small integer | Durasi per pertemuan |
| jumlah_mahasiswa_per_kelompok | unsigned small integer nullable | Ukuran kelompok jika perlu kelompok |
| perlu_kelompok | boolean | Apakah kegiatan memakai kelompok |
| perlu_presensi | boolean | Apakah kegiatan perlu presensi |
| perlu_logbook | boolean | Apakah kegiatan membuka logbook mahasiswa |
| urutan | unsigned small integer | Urutan tampil |
| created_at | timestamp nullable | Timestamp Laravel |
| updated_at | timestamp nullable | Timestamp Laravel |
| deleted_at | timestamp nullable | Soft delete |

Constraint:

- `blok_id` foreign key ke `blok.id`, cascade on delete
- `jenis_kegiatan_id` foreign key ke `jenis_kegiatan.id`, restrict on delete
- unique gabungan `blok_id` + `jenis_kegiatan_id`
- index `urutan`

## Ringkasan Relasi

```text
users 1-1 dosen
users 1-1 mahasiswa

prodi 1-N mahasiswa
prodi 1-N dosen
prodi 1-N mata_kuliah
prodi 1-N blok
semester 1-N blok

mata_kuliah 1-N blok
blok 1-N aturan_kegiatan_blok
jenis_kegiatan 1-N aturan_kegiatan_blok
```

## Rekomendasi Urutan Migration

1. `create_prodi_table`
2. `create_dosen_table`
3. `create_mahasiswa_table`
4. `create_semester_table`
5. `create_mata_kuliah_table`
6. `create_jenis_kegiatan_table`
7. `create_blok_table`
8. `create_aturan_kegiatan_blok_table`

## Data Awal yang Disarankan

### Role

- `admin`
- `pengelola`
- `dosen`
- `mahasiswa`

### Prodi

| Kode | Nama | Jenjang |
| --- | --- | --- |
| PSPD | Pendidikan Dokter | S1 |
| PROFESI | Profesi Dokter | Profesi |
### Jenis Kegiatan

| Kode | Nama | Jumlah Pertemuan Default | Durasi Menit Default |
| --- | --- | ---: | ---: |
| TUTORIAL | Tutorial/PBL | 7 | 120 |
| PRAKTIKUM | Praktikum | 4 | 180 |
| KULIAH | Kuliah Pakar | 8 | 100 |
| SKILLS_LAB | Skills Lab/OSCE | 4 | 180 |

## Catatan Implementasi Laravel

- Model dapat memakai nama bahasa Indonesia: `Prodi`, `Dosen`, `Mahasiswa`, `Semester`, `MataKuliah`, `JenisKegiatan`, dan `Blok`.
- Karena nama tabel bahasa Indonesia tidak selalu mengikuti pluralisasi Laravel, setiap model sebaiknya mendefinisikan properti `$table`.
- Gunakan trait `SoftDeletes` pada tabel master.
- Gunakan `nullOnDelete()` untuk relasi opsional dari `dosen` dan `mahasiswa` ke `users`.
- Gunakan `restrictOnDelete()` untuk master yang tidak boleh dihapus saat masih dipakai.
- Gunakan `cascadeOnDelete()` pada pivot atau tabel turunan yang ikut hilang saat induk dihapus permanen.

## Batasan Task 1

Fitur berikut dikerjakan pada task berikutnya:

- Manajemen mahasiswa peserta blok
- Pembagian kelompok per jenis kegiatan
- Plotting dosen per pertemuan atau kelompok
- Jadwal detail sesi blok
- Repository modul pembelajaran
- Presensi dan monitoring kuliah
- Logbook mahasiswa
- Komponen penilaian dan rekap nilai
- Laporan semester













