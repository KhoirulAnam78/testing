# Integrasi PDDIKTI, Kurikulum, KRS, dan Pengiriman DPNA Implementation Plan

> **For agentic workers:** Jalankan task berurutan dan centang setiap langkah. Task 1 adalah gate: jangan membuat migration atau kode integrasi sebelum kontrak API dan contoh payload tersedia serta plan ditinjau kembali.

**Goal:** Menyediakan salinan lokal terstruktur untuk data akademik berbasis PDDIKTI, memvalidasi kurikulum/KRS/prasyarat, menghubungkan peserta kelas kuliah ke `peserta_blok`, dan mengirim snapshot DPNA final secara idempotent tanpa mengubah mekanisme operasional blok.

**Architecture:** API akademik adalah sumber utama data master, registrasi mahasiswa, kurikulum, kelas kuliah, KRS, dan riwayat nilai. Database lokal adalah projection/cache terstruktur dengan identitas eksternal dan audit sinkronisasi. Operasional tetap berpusat pada `blok` dan `peserta_blok`. Nilai keluar hanya berasal dari `snapshot_dpna_peserta` melalui transactional outbox.

**Tech Stack:** PHP 8.3, Laravel 13, Eloquent, Laravel HTTP Client, queue/console command Laravel, Livewire 4, Spatie Permission, Bootstrap/Velzon.

**Supersedes:** `.kilo/plans/1789021298957-kurikulum-dan-krs-mahasiswa.md`. Draft lama menganggap aplikasi ini membuat dan menyetujui KRS lokal. Plan ini menunggu kontrak API dan memperlakukan peserta kelas kuliah/KRS dari API sebagai sumber utama.

---

## 1. Global Constraints

- Jangan mengubah alur operasional `blok`, `peserta_blok`, kelompok, pertemuan, presensi, penilaian, finalisasi, atau pembacaan snapshot DPNA.
- Tabel existing `kelas` tetap berarti rombel blok/lokal. Jangan rename, repurpose, atau menyamakannya dengan kelas kuliah PDDIKTI.
- Kelas kuliah upstream memakai entitas baru `kelas_kuliah`.
- API akademik menjadi sumber utama data upstream. Perubahan upstream tidak diedit melalui CRUD lokal kecuali kontrak API kemudian menyediakan operasi tulis yang disetujui.
- Semua migration fase ini additive. Dilarang `drop`, `truncate`, rename destruktif, hard delete transaksi, atau backfill yang menimpa nilai existing.
- `down()` migration integrasi kosong dengan komentar bahwa rollback destruktif sengaja dilarang. Rollback fitur dilakukan dengan feature flag, menghentikan scheduler/worker, dan menonaktifkan menu.
- Jangan menjalankan migration tanpa konfirmasi pengguna setelah migration selesai direview.
- Jangan menjalankan unit test sesuai aturan project. Pemeriksaan yang dibuat boleh dicatat untuk dijalankan manual oleh pengguna.
- Jangan menambah dependency. Gunakan Laravel HTTP Client, cache lock, queue, scheduler, validator, dan database transaction bawaan.
- Semua model upstream mempunyai `external_id`, `external_updated_at`, dan `last_synced_at`. Untuk tabel existing, kolom ditambahkan nullable agar data lama tetap valid.
- `external_id` adalah opaque string. Panjang, format UUID/non-UUID, dan case sensitivity ditetapkan setelah payload nyata tersedia.
- Tidak ada data dianggap hilang hanya karena tidak muncul pada satu page/batch API. Penonaktifan memerlukan tombstone/status eksplisit atau rekonsiliasi penuh yang terverifikasi.
- Payload mentah tidak disimpan secara default. Simpan payload teredaksi hanya pada error sinkronisasi/outbox bila dibutuhkan diagnosis.
- Token, password, API key, header otorisasi, dan data rahasia tidak boleh masuk database audit, log, exception message, atau UI.
- Semua nama endpoint, nama field JSON, enum, panjang kolom, batas batch, timeout, dan semantics error yang belum terverifikasi diberi tanda `[KONFIRMASI API]`.

---

## 2. Batas Domain

### 2.1 Domain upstream/projection

```text
program studi
periode akademik
mahasiswa
riwayat pendidikan mahasiswa
dosen
mata kuliah
kurikulum
mata kuliah kurikulum
kelas kuliah
aktivitas mengajar dosen
peserta kelas kuliah / KRS
nilai perkuliahan kelas
```

Data tersebut disinkronkan dari API dan disimpan lokal untuk relasi, pencarian, validasi, audit, serta ketahanan saat API tidak tersedia.

### 2.2 Domain operasional existing

```text
blok
├── kelas                         rombel lokal; bukan kelas kuliah PDDIKTI
├── peserta_blok
├── kelompok_blok
│   └── anggota_kelompok_blok
├── aturan_kegiatan_blok
├── pertemuan_blok
│   ├── dosen_pertemuan_blok
│   ├── presensi_pertemuan_blok
│   └── nilai_pertemuan_blok
└── finalisasi_dpna_blok
    └── snapshot_dpna_peserta
```

Semua presensi, pembagian kelompok, pertemuan, input nilai, kalkulasi DPNA, finalisasi, dan pembacaan nilai final tetap memakai domain existing.

### 2.3 Batas KRS

- Dalam nomenklatur PDDIKTI, pengambilan mata kuliah direpresentasikan oleh mahasiswa/registrasi yang menjadi peserta `kelas_kuliah`.
- Jangan membuat `krs` dan `krs_detail` lokal sebelum kontrak API membuktikan resource header/detail tersebut memang ada dan aplikasi harus memilikinya.
- Jika API menyediakan header KRS dengan workflow pusat, tambahkan projection header pada revisi plan. Jangan menciptakan workflow persetujuan lokal paralel.
- Sinkronisasi peserta kelas kuliah boleh menghasilkan/memulihkan `peserta_blok` melalui mapping eksplisit. Sinkronisasi tidak boleh mengubah data operasional turunannya.

---

## 3. Pemetaan Istilah

| Istilah PDDIKTI/API | Entitas lokal | Catatan |
| --- | --- | --- |
| Program Studi | `prodi` | Tambah metadata sinkronisasi |
| Periode Akademik/Semester | `semester` | Pertahankan konsep semester existing |
| Biodata Mahasiswa | `mahasiswa` | Identitas orang |
| Riwayat Pendidikan Mahasiswa | `riwayat_pendidikan_mahasiswa` | Identitas registrasi; business key upstream umumnya `id_registrasi_mahasiswa` `[KONFIRMASI API]` |
| Dosen | `dosen` | Tambah metadata sinkronisasi |
| Mata Kuliah | `mata_kuliah` | Master lintas kurikulum |
| Kurikulum | `kurikulum` | Versi kurikulum per prodi |
| Mata Kuliah Kurikulum | `kurikulum_mata_kuliah` | Penawaran, semester urutan, sifat wajib |
| Kelas Kuliah | `kelas_kuliah` | Bukan tabel `kelas` existing |
| Aktivitas Mengajar Dosen | `aktivitas_mengajar_dosen` | Assignment dosen pada kelas kuliah |
| Peserta Kelas Kuliah/KRS | `peserta_kelas_kuliah` | Pengambilan mata kuliah oleh satu registrasi |
| Nilai Perkuliahan Kelas | `nilai_perkuliahan_kelas` | Riwayat nilai upstream |
| Nilai final blok | `snapshot_dpna_peserta` | Sumber tunggal pengiriman keluar |
| Pengiriman nilai | `pengiriman_dpna` + detail | Outbox lokal |

Nama field PDDIKTI pada tabel mapping tidak boleh disalin sebelum payload nyata diverifikasi. `external_id` lokal menyimpan identifier resource upstream yang relevan.

---

## 4. Kepemilikan Data

| Data | Pemilik | Lokal boleh mengubah? | Konflik |
| --- | --- | --- | --- |
| Prodi, periode, mahasiswa, registrasi, dosen, mata kuliah | API akademik | Tidak | Upstream menang; data operasional lama tidak dihapus |
| Kurikulum dan mata kuliah kurikulum | API akademik | Tidak untuk field upstream | Upstream menang |
| Penetapan kurikulum mahasiswa | API bila tersedia; fallback lokal terkontrol | Hanya fallback | Assignment API menang; konflik masuk review |
| Kelas kuliah, pengajar, peserta/KRS | API akademik | Tidak | Upstream menang; pembatalan tidak menghapus histori blok |
| Aturan ganjil/genap dan prasyarat tambahan | Lokal | Ya, permission terbatas | Tidak ditimpa sync |
| Mapping kelas kuliah ke blok | Lokal | Ya, permission terbatas | Mismatch semester/prodi/mata kuliah ditolak |
| Kelompok, pertemuan, presensi, nilai draft | Lokal | Ya, sesuai rules existing | Tidak disentuh sync |
| Snapshot DPNA | Lokal, immutable per versi finalisasi | Tidak setelah final | Koreksi membuat versi finalisasi baru |
| Nilai perkuliahan historis | API akademik | Tidak | Upstream disimpan sebagai projection |
| Outbox/pengiriman DPNA | Lokal | State machine internal | Response API dicatat per detail |

Field lokal dan upstream harus terpisah. Mapper sinkronisasi hanya mengisi allowlist field upstream; jangan gunakan `fill($payload)`.

---

## 5. Rancangan Skema Additive

Nama, tipe, panjang, dan enum bertanda `[KONFIRMASI API]` wajib diperbarui setelah Task 1.

### 5.1 Metadata pada master existing

Tambahkan nullable ke `prodi`, `semester`, `mahasiswa`, `dosen`, dan `mata_kuliah`:

```text
external_id           varchar [KONFIRMASI API]
external_updated_at   timestamp nullable
last_synced_at        timestamp nullable
```

Constraint/index:

- `unique(external_id)` hanya setelah scope tenant/sumber API dipastikan. Bila beberapa sumber/tenant mungkin berbagi ID, gunakan `unique(source, external_id)`.
- Data existing tetap memiliki `external_id = null` sampai berhasil dicocokkan.
- Jangan mencocokkan mahasiswa hanya berdasarkan nama.
- Aturan matching pertama kali memakai business key yang disetujui: kode prodi, kode periode, NIM + registrasi, NIDN/NUPTK `[KONFIRMASI API]`, dan kode mata kuliah dalam scope prodi `[KONFIRMASI API]`.
- Collision menghentikan record terkait dan masuk audit; jangan memilih baris pertama.

### 5.2 `riwayat_pendidikan_mahasiswa`

```text
id_riwayat_pendidikan_mahasiswa bigint PK
external_id                     varchar [KONFIRMASI API]
mahasiswa_id                    FK mahasiswa, restrict
prodi_id                        FK prodi, restrict
semester_mulai_id               FK semester, nullable, restrict
nim                             varchar [KONFIRMASI API]
jenis_daftar                    varchar nullable [KONFIRMASI API]
jalur_daftar                    varchar nullable [KONFIRMASI API]
tanggal_daftar                  date nullable
status                          varchar nullable [KONFIRMASI API]
external_updated_at             timestamp nullable
last_synced_at                  timestamp nullable
timestamps
```

Constraint/index:

```text
unique(external_id) [setelah scope sumber terkonfirmasi]
index(mahasiswa_id, prodi_id)
index(prodi_id, nim)
index(status)
```

Satu `mahasiswa` dapat mempunyai beberapa riwayat pendidikan. Validasi kurikulum, KRS, dan nilai mengacu ke riwayat pendidikan, bukan hanya biodata mahasiswa.

### 5.3 `kurikulum`

```text
id_kurikulum          bigint PK
external_id           varchar [KONFIRMASI API]
prodi_id              FK prodi, restrict
semester_mulai_id     FK semester, nullable, restrict
kode                  varchar nullable [KONFIRMASI API]
nama                  varchar
jumlah_sks_lulus      decimal nullable [KONFIRMASI API]
jumlah_sks_wajib      decimal nullable [KONFIRMASI API]
jumlah_sks_pilihan    decimal nullable [KONFIRMASI API]
status                varchar [KONFIRMASI API]
external_updated_at   timestamp nullable
last_synced_at        timestamp nullable
timestamps
```

Constraint/index: `unique(external_id)` sesuai scope sumber, `index(prodi_id, status)`, dan `index(semester_mulai_id)`.

### 5.4 `kurikulum_mata_kuliah`

```text
id_kurikulum_mata_kuliah bigint PK
external_id                varchar nullable [KONFIRMASI API]
kurikulum_id               FK kurikulum, restrict
mata_kuliah_id             FK mata_kuliah, restrict
semester_urutan            unsigned smallint nullable
apakah_wajib               boolean nullable
sks_mata_kuliah            decimal nullable [KONFIRMASI API]
sks_tatap_muka             decimal nullable [KONFIRMASI API]
sks_praktek                decimal nullable [KONFIRMASI API]
sks_praktek_lapangan       decimal nullable [KONFIRMASI API]
sks_simulasi               decimal nullable [KONFIRMASI API]
external_updated_at        timestamp nullable
last_synced_at             timestamp nullable
timestamps
```

Constraint/index:

```text
unique(kurikulum_id, mata_kuliah_id)
unique(external_id) [bila resource mempunyai ID stabil]
index(kurikulum_id, semester_urutan)
```

Aturan prasyarat melekat ke baris ini, bukan ke master `mata_kuliah`.

### 5.5 `kurikulum_mahasiswa`

Tabel assignment diperlukan hanya jika API mengirim assignment eksplisit atau fallback penetapan berdasarkan angkatan disetujui.

```text
id_kurikulum_mahasiswa             bigint PK
external_id                        varchar nullable [KONFIRMASI API]
riwayat_pendidikan_mahasiswa_id    FK, restrict
kurikulum_id                       FK kurikulum, restrict
sumber                             enum api/manual/aturan_angkatan
aktif                              boolean
mulai_berlaku                      date nullable
external_updated_at                timestamp nullable
last_synced_at                     timestamp nullable
timestamps
```

Constraint harus menjamin paling banyak satu assignment aktif per riwayat pendidikan. Karena partial unique index berbeda antar DB, service wajib memakai transaction + `lockForUpdate()`; desain constraint final mengikuti DB project.

### 5.6 `kelas_kuliah`

```text
id_kelas_kuliah      bigint PK
external_id          varchar [KONFIRMASI API]
semester_id          FK semester, restrict
prodi_id             FK prodi, restrict
mata_kuliah_id       FK mata_kuliah, restrict
nama_kelas_kuliah    varchar [KONFIRMASI API]
sks                   decimal nullable [KONFIRMASI API]
kapasitas             unsigned integer nullable
status                varchar nullable [KONFIRMASI API]
external_updated_at   timestamp nullable
last_synced_at        timestamp nullable
timestamps
```

Constraint/index: `unique(external_id)` sesuai scope sumber, `index(semester_id, prodi_id)`, `index(mata_kuliah_id)`, `index(status)`.

### 5.7 `aktivitas_mengajar_dosen`

```text
id_aktivitas_mengajar_dosen bigint PK
external_id                  varchar nullable [KONFIRMASI API]
kelas_kuliah_id              FK kelas_kuliah, restrict
dosen_id                     FK dosen, restrict
sks_substansi                decimal nullable [KONFIRMASI API]
rencana_pertemuan            unsigned smallint nullable [KONFIRMASI API]
realisasi_pertemuan          unsigned smallint nullable [KONFIRMASI API]
status                       varchar nullable [KONFIRMASI API]
external_updated_at          timestamp nullable
last_synced_at               timestamp nullable
timestamps
```

Jangan otomatis membuat `dosen_pertemuan_blok`; mapping pengajar kelas kuliah dan plotting pertemuan blok mempunyai granularitas berbeda.

### 5.8 `peserta_kelas_kuliah`

```text
id_peserta_kelas_kuliah             bigint PK
external_id                          varchar nullable [KONFIRMASI API]
kelas_kuliah_id                      FK kelas_kuliah, restrict
riwayat_pendidikan_mahasiswa_id      FK, restrict
status                               varchar nullable [KONFIRMASI API]
external_updated_at                  timestamp nullable
last_synced_at                       timestamp nullable
timestamps
```

Constraint/index:

```text
unique(kelas_kuliah_id, riwayat_pendidikan_mahasiswa_id)
unique(external_id) [bila resource mempunyai ID stabil]
index(riwayat_pendidikan_mahasiswa_id, status)
```

### 5.9 `nilai_perkuliahan_kelas`

```text
id_nilai_perkuliahan_kelas bigint PK
external_id                 varchar nullable [KONFIRMASI API]
peserta_kelas_kuliah_id     FK peserta_kelas_kuliah, restrict
nilai_angka                 decimal nullable [KONFIRMASI API]
nilai_indeks                decimal nullable [KONFIRMASI API]
nilai_huruf                 varchar nullable [KONFIRMASI API]
status_lulus                boolean nullable
sumber                      enum api/pengiriman_dpna
external_updated_at         timestamp nullable
last_synced_at              timestamp nullable
timestamps
```

Gunakan `unique(peserta_kelas_kuliah_id)` hanya bila API memastikan satu hasil final per peserta kelas. Bila API memiliki histori percobaan/perbaikan dalam kelas yang sama, ubah business key setelah payload tersedia.

### 5.10 Aturan lokal kurikulum

`aturan_kurikulum_mata_kuliah`:

```text
id                                 bigint PK
kurikulum_mata_kuliah_id           FK unique, restrict
periode_pengambilan_ulang          enum mengikuti_semester_kurikulum/ganjil/genap/semua
aktif                              boolean default true
catatan                            text nullable
timestamps
```

`prasyarat_mata_kuliah`:

```text
id                                      bigint PK
kurikulum_mata_kuliah_id                FK, restrict
prasyarat_kurikulum_mata_kuliah_id      FK, restrict
nilai_huruf_minimal                     varchar nullable
nilai_indeks_minimal                    decimal nullable [KONFIRMASI SKALA]
aktif                                   boolean default true
catatan                                 text nullable
timestamps
```

Constraint:

```text
unique(kurikulum_mata_kuliah_id, prasyarat_kurikulum_mata_kuliah_id)
check(kurikulum_mata_kuliah_id <> prasyarat_kurikulum_mata_kuliah_id) [bila DB mendukung]
```

Semua prasyarat memakai `AND` pada fase pertama. Jangan menambah expression tree/group `OR` sebelum kebijakan nyata tersedia.

### 5.11 Skala nilai lokal

`skala_nilai`:

```text
id_skala_nilai bigint PK
prodi_id       FK prodi nullable, restrict
nama           varchar
aktif          boolean
timestamps
```

`skala_nilai_detail`:

```text
id_skala_nilai_detail bigint PK
skala_nilai_id        FK skala_nilai, restrict
nilai_angka_min       decimal [KONFIRMASI SKALA]
nilai_angka_max       decimal [KONFIRMASI SKALA]
nilai_huruf           varchar
nilai_indeks          decimal [KONFIRMASI SKALA]
lulus                 boolean
timestamps
```

Range tidak boleh overlap. Batas inklusif/eksklusif dan pembulatan menunggu kebijakan akademik. Jangan mengirim nilai sebelum skala aktif tervalidasi.

### 5.12 Bridge ke operasional blok

`mapping_kelas_kuliah_blok`:

```text
id                     bigint PK
kelas_kuliah_id        FK kelas_kuliah, restrict
blok_id                FK blok, restrict
kelas_id               FK kelas nullable, restrict
aktif                  boolean default true
timestamps
```

`sumber_peserta_blok`:

```text
id                         bigint PK
peserta_kelas_kuliah_id    FK peserta_kelas_kuliah, restrict
peserta_blok_id            FK peserta_blok, restrict
status_sinkronisasi        enum aktif/dibatalkan_perlu_review/konflik
catatan                    text nullable
timestamps
```

Constraint/index:

```text
unique(kelas_kuliah_id, blok_id)
unique(peserta_kelas_kuliah_id, peserta_blok_id)
index(blok_id, aktif)
index(peserta_blok_id)
```

Jangan mengasumsikan satu kelas kuliah selalu satu blok sebelum kontrak mapping dikonfirmasi. Bila satu kelas kuliah dapat mencakup beberapa blok, constraint di atas mendukung beberapa mapping per kelas kuliah. Satu peserta kelas dapat mempunyai beberapa `sumber_peserta_blok`, masing-masing untuk blok berbeda.

### 5.13 Audit sinkronisasi

`sinkronisasi_akademik`:

```text
id                    bigint PK
jenis_data            varchar
mode                  enum penuh/incremental
status                enum berjalan/berhasil/sebagian/gagal
cursor_awal           text nullable
cursor_akhir          text nullable
jumlah_diterima       unsigned integer default 0
jumlah_dibuat         unsigned integer default 0
jumlah_diubah         unsigned integer default 0
jumlah_dilewati       unsigned integer default 0
jumlah_gagal          unsigned integer default 0
dimulai_pada          timestamp
selesai_pada          timestamp nullable
pesan_error           text nullable
timestamps
```

`sinkronisasi_akademik_error`:

```text
id                         bigint PK
sinkronisasi_akademik_id   FK, restrict
external_id                varchar nullable
kode_error                 varchar nullable
pesan_error                text
payload_json               json nullable, wajib teredaksi
timestamps
```

### 5.14 Transactional outbox DPNA

`pengiriman_dpna`:

```text
id_pengiriman_dpna          bigint PK
finalisasi_dpna_blok_id     FK finalisasi_dpna_blok, restrict
kelas_kuliah_id             FK kelas_kuliah, restrict
idempotency_key             varchar unique
status                      enum pending/diproses/sebagian/terkirim/gagal
jumlah_data                 unsigned integer
jumlah_berhasil             unsigned integer default 0
jumlah_gagal                unsigned integer default 0
mulai_dikirim_pada          timestamp nullable
selesai_dikirim_pada        timestamp nullable
timestamps
```

`pengiriman_dpna_detail`:

```text
id_pengiriman_dpna_detail   bigint PK
pengiriman_dpna_id          FK pengiriman_dpna, restrict
snapshot_dpna_peserta_id    FK snapshot_dpna_peserta, restrict
peserta_kelas_kuliah_id     FK peserta_kelas_kuliah, restrict
idempotency_key             varchar unique
nilai_angka                 decimal [KONFIRMASI API]
nilai_indeks                decimal [KONFIRMASI API]
nilai_huruf                 varchar [KONFIRMASI API]
payload_json                json
status                      enum pending/diproses/terkirim/gagal_permanen
jumlah_percobaan            unsigned integer default 0
tersedia_dicoba_pada        timestamp nullable
terakhir_dicoba_pada        timestamp nullable
response_code               integer nullable
response_json               json nullable, wajib teredaksi
pesan_error                 text nullable
timestamps
```

Unique tambahan:

```text
unique(finalisasi_dpna_blok_id, kelas_kuliah_id)
unique(pengiriman_dpna_id, snapshot_dpna_peserta_id)
```

Outbox dibuat dalam transaction lokal. Worker mengambil baris dengan lock, menandai `diproses`, melakukan HTTP di luar transaction panjang, lalu menyimpan hasil. Lease/recovery detail `[KONFIRMASI OPERASIONAL]` wajib mencegah baris macet permanen setelah worker mati.

---

## 6. Aturan Sinkronisasi

### 6.1 Urutan dependency

```text
1. prodi
2. periode/semester
3. mahasiswa
4. riwayat pendidikan mahasiswa
5. dosen
6. mata kuliah
7. kurikulum
8. mata kuliah kurikulum
9. kurikulum mahasiswa, bila tersedia
10. kelas kuliah
11. aktivitas mengajar dosen
12. peserta kelas kuliah/KRS
13. nilai perkuliahan kelas
14. bridge peserta blok
```

Child tanpa parent tidak dibuat dengan foreign key palsu. Simpan error dan retry setelah parent tersedia.

### 6.2 Upsert dan perubahan

- Cari berdasarkan `external_id` dalam scope sumber yang sudah dikonfirmasi.
- First sync data legacy memakai matcher eksplisit dan dry-run report sebelum menulis `external_id`.
- Buat atau update hanya allowlist field upstream.
- Bila `external_updated_at` tersedia, abaikan payload lebih lama dari versi lokal.
- Bila timestamp tidak tersedia, gunakan normalized payload hash `[KONFIRMASI DESAIN]` hanya jika dibutuhkan untuk mengurangi write.
- Selalu perbarui `last_synced_at` saat record berhasil diproses.
- Proses per page/chunk dalam transaction terbatas; jangan membungkus seluruh full sync dalam satu transaction.
- Gunakan cache lock per `jenis_data` agar job sama tidak overlap.
- Simpan cursor hanya setelah batch commit.
- Retry transport error dengan exponential delay terbatas dan jitter. Jangan retry validation/business error tanpa perubahan data.
- HTTP sukses tidak otomatis berarti operasi bisnis sukses; parser wajib memeriksa envelope/error API `[KONFIRMASI API]`.

### 6.3 Penonaktifan dan penghapusan

- Tidak ada hard delete dari sync.
- Status/tombstone eksplisit mengubah status lokal menjadi nonaktif bila domain mendukung.
- Missing-from-page tidak berarti nonaktif.
- Full reconciliation hanya menandai kandidat hilang untuk review jika API tidak mempunyai tombstone.
- Record yang sudah dipakai `peserta_blok`, snapshot, atau pengiriman tetap dipertahankan.

---

## 7. Aturan Kurikulum, Ganjil/Genap, dan Prasyarat

### 7.1 Kurikulum mahasiswa

Urutan resolusi:

1. Assignment eksplisit dari API.
2. Assignment manual aktif yang sudah disetujui pengelola.
3. Aturan angkatan lokal yang terdokumentasi `[KONFIRMASI KEBIJAKAN]`.
4. Bila tidak ada hasil tunggal, gagal tertutup dan masukkan ke review.

Jangan memilih kurikulum aktif terbaru secara otomatis karena mahasiswa satu prodi dapat memakai versi berbeda.

### 7.2 Ganjil/genap

Default `mengikuti_semester_kurikulum`:

```text
semester_urutan 1, 3, 5, ... = ganjil
semester_urutan 2, 4, 6, ... = genap
```

`periode_pengambilan_ulang` hanya diterapkan pada pengambilan ulang. Pengambilan pertama mengikuti penawaran kelas dari API.

Semester pendek belum mempunyai semantics pasti. Sampai kebijakan tersedia:

- evaluasi semester pendek gagal tertutup untuk aturan `ganjil`, `genap`, dan `mengikuti_semester_kurikulum`;
- hanya aturan eksplisit `semua` yang boleh lolos;
- parser jenis periode memakai field API `[KONFIRMASI API]`, bukan menebak dari nama semester.

### 7.3 Prasyarat

- Prasyarat selalu di-scope ke satu `kurikulum` melalui dua baris `kurikulum_mata_kuliah`.
- Self-reference ditolak.
- Cross-curriculum reference ditolak.
- Siklus langsung/tidak langsung ditolak sebelum simpan.
- Semua baris aktif dievaluasi dengan `AND`.
- Riwayat nilai belum tersedia atau belum dapat dipetakan menyebabkan gagal tertutup.
- Nilai draft operasional tidak pernah memenuhi prasyarat.
- Snapshot DPNA final lokal boleh memenuhi prasyarat sambil pengiriman upstream masih pending.
- Nilai upstream menjadi histori authoritative setelah berhasil disinkronkan.
- Pemilihan nilai terbaik versus nilai terakhir masih `[KONFIRMASI KEBIJAKAN]`; jangan implementasikan evaluator final sebelum keputusan tersedia.
- `nilai_indeks_minimal` menjadi pembanding numerik. `nilai_huruf_minimal` hanya dipakai bila urutan skala nilai terdefinisi dan tervalidasi.

Hasil evaluator harus terstruktur:

```text
lolos: bool
kode: string
mata_kuliah: string
batas: string|null
nilai_ditemukan: string|null
pesan: string
```

---

## 8. Bridge `peserta_kelas_kuliah` ke `peserta_blok`

Alur:

```text
peserta_kelas_kuliah
  + mapping_kelas_kuliah_blok
  + riwayat_pendidikan_mahasiswa.mahasiswa_id
        |
        v
peserta_blok
        |
        v
sumber_peserta_blok
```

Rules:

- Mapping harus cocok pada semester, prodi, dan mata kuliah. Perbedaan ditolak server-side.
- Gunakan business key existing `peserta_blok` yang telah berlaku; jangan menambah peserta duplikat.
- Gunakan `withTrashed()->firstOrNew()` dan `restore()` hanya bila model existing memakai soft delete dan seluruh relasi cocok.
- Pembuatan/pemulihan `peserta_blok` dan `sumber_peserta_blok` berada dalam satu transaction.
- Sync ulang idempotent: menjalankan input sama tidak menambah peserta atau link baru.
- Jangan otomatis menempatkan mahasiswa ke kelompok.
- Jangan mengubah `kelas`, kelompok, anggota kelompok, pertemuan, dosen pengampu, presensi, nilai, finalisasi, atau snapshot.
- Peserta KRS dibatalkan sebelum mempunyai aktivitas lokal dapat ditandai nonaktif sesuai kemampuan model existing `[KONFIRMASI RULE]`.
- Peserta KRS dibatalkan setelah mempunyai kelompok/presensi/nilai/snapshot tidak dihapus atau dinonaktifkan otomatis. Set `status_sinkronisasi = dibatalkan_perlu_review`.
- Peserta lokal tanpa sumber KRS tetap sah untuk data legacy. Jangan membuat KRS retrospektif.

---

## 9. Pengiriman DPNA

### 9.1 Sumber dan precondition

- Hanya `snapshot_dpna_peserta` dari versi `finalisasi_dpna_blok` yang final boleh dikirim.
- Satu finalisasi dapat menghasilkan beberapa outbox bila satu blok dipetakan ke beberapa kelas kuliah.
- Semua snapshot harus mempunyai mapping tunggal ke `peserta_kelas_kuliah` target.
- Skala nilai aktif harus menghasilkan nilai angka, indeks, dan huruf yang valid untuk setiap detail.
- Peserta tanpa mapping, mapping ganda, kelas mismatch, atau nilai di luar skala menggagalkan pembuatan seluruh outbox kelas. Jangan mengirim sebagian secara diam-diam.
- Preview jumlah detail dan error mapping tersedia sebelum action kirim.

### 9.2 Idempotensi

Header key deterministik dari:

```text
finalisasi_dpna_blok_id + kelas_kuliah.external_id + versi_payload
```

Detail key deterministik dari:

```text
header_key + peserta_kelas_kuliah.external_id
```

Gunakan hash stabil. Jangan memasukkan timestamp request. Payload JSON, nilai, dan target disalin sekali saat outbox dibuat; retry tidak menghitung ulang snapshot atau skala.

### 9.3 State dan retry

- `pending`: siap dikirim.
- `diproses`: mempunyai lease worker aktif.
- `terkirim`: API mengonfirmasi sukses bisnis.
- `gagal_permanen`: validation/business error yang memerlukan koreksi atau kontrak API menandainya non-retryable.
- Transport error, timeout, HTTP 429, dan HTTP 5xx kembali tersedia setelah delay terbatas.
- HTTP 2xx dengan item error tetap gagal untuk item tersebut.
- Retry manual memakai outbox/detail sama, bukan membuat snapshot atau payload baru.
- Bila koreksi nilai diperlukan, buka kembali/finalisasi ulang sesuai mekanisme existing lalu buat outbox versi baru. Histori lama tidak ditimpa.
- Granularitas koreksi dan endpoint update nilai tetap `[KONFIRMASI API]`.

---

## 10. Adapter API dan Konfigurasi

Target minimum setelah kontrak tersedia:

```text
config/services.php
.env.example
app/Support/Akademik/AkademikClient.php
app/Support/Akademik/AkademikGateway.php
app/Support/Akademik/Dto/*
app/Support/Akademik/Mappers/*
app/Support/Akademik/Sync/*
app/Support/Akademik/KirimDpnaService.php
app/Console/Commands/SyncAkademik.php
app/Jobs/KirimDpna.php
```

Interfaces minimum:

```php
interface AkademikGateway
{
    public function page(string $resource, ?string $cursor = null, array $filters = []): AkademikPage;

    public function kirimNilai(array $payload, string $idempotencyKey): KirimNilaiResult;
}
```

Ketentuan:

- Endpoint, auth, envelope, pagination, filter waktu, dan method HTTP terisolasi di client/gateway.
- DTO menolak payload tanpa external ID atau foreign external ID wajib.
- Mapper menerjemahkan field API ke nama domain lokal; model/service lain tidak membaca array JSON mentah.
- Timeout connect/request eksplisit `[KONFIRMASI OPERASIONAL]`.
- Retry hanya untuk request idempotent atau request nilai dengan jaminan idempotensi yang sudah dikonfirmasi.
- TLS verification tidak boleh dimatikan.
- Base URL dan credential berasal dari environment.
- Tambahkan feature flags `sync_enabled` dan `dpna_push_enabled`, default `false` sampai konfigurasi production diverifikasi.

---

## 11. Fase Implementasi Executable

### Task 1: Kontrak API dan Fixture — Blocking Gate

**Files:**

- Create: `docs/superpowers/specs/2026-09-17-kontrak-api-akademik.md`
- Create: `tests/Fixtures/akademik/*.json` atau lokasi fixture non-test yang disepakati
- Modify: plan ini untuk mengganti seluruh `[KONFIRMASI API]`

**Produces:** Matriks resource, endpoint/action, auth, field, enum, pagination, incremental cursor, error envelope, rate limit, serta operasi kirim/koreksi nilai.

- [ ] Dapatkan dokumentasi dan contoh JSON sukses/kosong/gagal untuk setiap resource.
- [ ] Konfirmasi apakah target Neo Feeder langsung atau middleware API akademik kampus.
- [ ] Konfirmasi tenant/source scope untuk uniqueness `external_id`.
- [ ] Konfirmasi identifier setiap resource dan foreign identifier.
- [ ] Konfirmasi resource assignment kurikulum mahasiswa.
- [ ] Konfirmasi KRS dibuat di pusat atau lokal; bila lokal, hentikan eksekusi dan revisi batas domain/workflow lebih dulu.
- [ ] Konfirmasi semantics kelas kuliah, peserta kelas, pembatalan KRS, dan semester pendek.
- [ ] Konfirmasi endpoint serta business key riwayat nilai.
- [ ] Konfirmasi skala, pembulatan, nilai terbaik/terakhir, dan status lulus.
- [ ] Konfirmasi granularitas kirim/koreksi DPNA serta dukungan idempotency key.
- [ ] Redact fixture sebelum commit.
- [ ] Review ulang seluruh skema dan constraint plan. Jangan lanjut ke Task 2 selama placeholder kontrak masih memengaruhi skema.

### Task 2: Konfigurasi dan Client API

**Files:**

- Modify: `config/services.php`
- Modify: `.env.example`
- Create: `app/Support/Akademik/AkademikClient.php`
- Create: `app/Support/Akademik/AkademikGateway.php`
- Create: DTO/result minimum sesuai kontrak nyata

**Produces:** Client terisolasi dengan auth, timeout, pagination, error normalization, dan redaction.

- [ ] Tambahkan config tanpa credential nyata.
- [ ] Implementasikan autentikasi sesuai kontrak.
- [ ] Implementasikan satu primitive request dan parser envelope; jangan buat wrapper per endpoint bila parameter resource cukup.
- [ ] Validasi status HTTP dan status bisnis.
- [ ] Redact request/response sebelum logging.
- [ ] Tambahkan feature flags default `false`.
- [ ] Buat runnable fixture mapper check, tetapi jangan jalankan unit test.

### Task 3: Migration Projection Akademik

**Files:**

- Create: `database/migrations/<timestamp>_add_academic_sync_metadata_to_master_tables.php`
- Create: `database/migrations/<timestamp>_create_academic_projection_tables.php`
- Create: model Eloquent hanya untuk tabel yang disetujui Task 1
- Modify: model existing untuk casts/relations metadata

**Produces:** Projection master, registrasi, kurikulum, kelas kuliah, pengajar, peserta kelas, dan nilai historis.

- [ ] Buat migration additive saja.
- [ ] Tambahkan index/unique berdasarkan scope API yang terverifikasi.
- [ ] Gunakan `restrictOnDelete()` untuk histori/reference.
- [ ] Gunakan `down()` kosong dengan alasan non-destruktif.
- [ ] Buat casts tanggal, boolean, dan decimal konsisten pola project.
- [ ] Review statis migration untuk `drop`, `truncate`, destructive rename, cascade tak perlu, dan nullable compatibility.
- [ ] Minta konfirmasi pengguna sebelum menjalankan migration. Jangan menjalankannya dalam task implementasi otomatis.

### Task 4: Audit dan Sinkronisasi Master

**Files:**

- Create: migration audit sinkronisasi
- Create: `app/Models/SinkronisasiAkademik.php`
- Create: `app/Models/SinkronisasiAkademikError.php`
- Create: `app/Support/Akademik/Sync/SyncRunner.php`
- Create: mapper/upserter per dependency minimum
- Create: `app/Console/Commands/SyncAkademik.php`

**Produces:** `SyncRunner::run(string $resource, SyncMode $mode): SyncResult` dan command terjadwal/manual.

- [ ] Implementasikan urutan dependency dari bagian 6.1.
- [ ] Gunakan cache lock per resource.
- [ ] Commit per page/chunk dan simpan cursor setelah commit.
- [ ] Catat count dibuat/diubah/dilewati/gagal.
- [ ] Fail record tanpa parent; jangan buat placeholder master palsu.
- [ ] Sediakan dry-run matching report sebelum menautkan master legacy.
- [ ] Jangan hard delete data yang hilang dari response.

### Task 5: Aturan Kurikulum dan Prasyarat

**Files:**

- Create: migration tabel aturan/prasyarat/skala nilai
- Create: model terkait
- Create: `app/Support/Akademik/PengecekPrasyarat.php`
- Create: `app/Support/Akademik/KonverterNilai.php`
- Create/Modify: halaman read-only kurikulum dan form aturan lokal sesuai pola Livewire project
- Modify: `routes/web.php`
- Modify: `resources/views/layouts/navbar.blade.php`

**Produces:**

```php
PengecekPrasyarat::periksa(RiwayatPendidikanMahasiswa $registrasi, KurikulumMataKuliah $target): HasilPrasyarat
KonverterNilai::konversi(float $nilaiAngka, SkalaNilai $skala): HasilNilai
```

- [ ] Field upstream tampil read-only dengan waktu sinkronisasi terakhir.
- [ ] Form lokal hanya mengubah aturan pengambilan ulang, prasyarat, dan skala.
- [ ] Cegah self-reference, cross-curriculum reference, dan siklus.
- [ ] Evaluasi prasyarat `AND` dan gagal tertutup saat histori belum tersedia.
- [ ] Validasi range skala tidak overlap dan tidak berlubang pada domain yang diwajibkan.
- [ ] Authorization diperiksa ulang pada setiap action server-side.

### Task 6: Kelas Kuliah, KRS, dan Bridge Peserta Blok

**Files:**

- Create: migration `mapping_kelas_kuliah_blok` dan `sumber_peserta_blok`
- Create: model mapping/source
- Create: `app/Support/Akademik/SinkronisasiPesertaBlok.php`
- Create/Modify: UI mapping dan conflict review

**Produces:** `SinkronisasiPesertaBlok::handle(MappingKelasKuliahBlok $mapping): HasilSinkronisasiPesertaBlok`.

- [ ] Validasi kesamaan semester, prodi, dan mata kuliah saat mapping.
- [ ] Upsert peserta dan source link dalam transaction.
- [ ] Pulihkan soft-deleted row hanya bila seluruh business key cocok.
- [ ] Buktikan rerun idempotent melalui fixture/check manual.
- [ ] Jangan membuat kelompok atau mengubah data operasional.
- [ ] Pembatalan dengan aktivitas existing masuk review, bukan delete.
- [ ] Tampilkan peserta legacy tanpa source sebagai data sah.

### Task 7: Outbox DPNA

**Files:**

- Create: migration tabel `pengiriman_dpna` dan `pengiriman_dpna_detail`
- Create: model outbox
- Create: `app/Support/Akademik/BuatPengirimanDpna.php`
- Create: `app/Support/Akademik/KirimDpnaService.php`
- Create: `app/Jobs/KirimDpna.php`
- Modify: `resources/views/pages/dpna-blok/detail.blade.php`
- Modify: permission/menu migration secara additive

**Produces:**

```php
BuatPengirimanDpna::handle(FinalisasiDpnaBlok $finalisasi, KelasKuliah $kelas): PengirimanDpna
KirimDpnaService::kirim(PengirimanDpna $pengiriman): HasilPengirimanDpna
```

- [ ] Tolak sumber selain snapshot final.
- [ ] Validasi mapping lengkap sebelum membuat header/detail.
- [ ] Freeze payload dan nilai saat outbox dibuat.
- [ ] Buat idempotency key deterministik dan unique.
- [ ] Proses detail dengan lease/lock yang dapat pulih setelah worker mati.
- [ ] Pisahkan retryable transport error dari permanent business error.
- [ ] Simpan response teredaksi per detail.
- [ ] Tampilkan status, count, error, percobaan terakhir, dan action retry berpermission.
- [ ] Buka kembali/finalisasi ulang membuat versi outbox baru tanpa menimpa histori.

### Task 8: Scheduler, Operasional, dan Dokumentasi

**Files:**

- Modify: scheduler/console registration sesuai pola Laravel 13 project
- Modify: `CLAUDE.md`
- Modify: `AGENT.md`
- Create: `task/task_6.md`
- Create: runbook sinkronisasi dan pengiriman DPNA

- [ ] Jadwalkan sync hanya setelah feature flag aktif.
- [ ] Jangan overlap resource sama.
- [ ] Dokumentasikan urutan backfill, dry-run, monitoring, retry, dan feature disable.
- [ ] Dokumentasikan bahwa `kelas` bukan `kelas_kuliah`.
- [ ] Dokumentasikan bahwa peserta blok adalah projection operasional, bukan sumber KRS.
- [ ] Dokumentasikan prosedur koreksi DPNA tanpa menghapus histori.
- [ ] Tambahkan query/check dashboard untuk batch gagal, conflict review, dan outbox macet.

### Task 9: Verifikasi Statis dan Manual

- [ ] Review semua migration: additive, `down()` non-destruktif, tanpa `truncate`, tanpa delete data existing.
- [ ] Review mapper: allowlist field, trust-boundary validation, foreign ID wajib, dan payload redaction.
- [ ] Review query: eager load/chunk, index filter, tidak ada N+1 pada UI.
- [ ] Review authorization semua page/action/command sensitif.
- [ ] Review idempotensi sync peserta dan outbox.
- [ ] Review bahwa sync tidak menulis kelompok, pertemuan, presensi, nilai draft, finalisasi, atau snapshot.
- [ ] Tulis pemeriksaan kecil berbasis fixture untuk mapper, prasyarat, ganjil/genap, skala, dan idempotency key.
- [ ] Jangan jalankan unit test atau migration; serahkan verifikasi manual kepada pengguna sesuai aturan project.
- [ ] Catat semua pemeriksaan yang belum dijalankan pada laporan implementasi.

---

## 12. Verifikasi Manual End-to-End

Urutan ini dijalankan pengguna setelah migration dikonfirmasi dan diterapkan:

1. Aktifkan koneksi API pada environment non-production dan biarkan feature flags tulis tetap mati.
2. Jalankan sync master dari prodi sampai nilai historis; cocokkan count API dengan audit batch.
3. Jalankan input sama dua kali; pastikan baris tidak bertambah dan `last_synced_at` berubah.
4. Simulasikan payload child tanpa parent; pastikan record gagal tercatat tanpa foreign key palsu.
5. Simulasikan record hilang dari satu page; pastikan data lokal tidak terhapus/nonaktif otomatis.
6. Tetapkan kurikulum dua mahasiswa angkatan berbeda; pastikan resolver memilih versi masing-masing.
7. Buat aturan pengambilan ulang ganjil lalu evaluasi periode genap dan semester pendek; pastikan gagal sesuai rule.
8. Buat prasyarat A untuk B; uji belum punya nilai, nilai kurang, nilai lulus, dan histori belum tersinkron.
9. Coba membuat self-reference, cross-curriculum prerequisite, dan siklus; pastikan ditolak.
10. Mapping satu kelas kuliah ke blok yang cocok; sinkronkan peserta dua kali dan pastikan `peserta_blok` tidak duplikat.
11. Batalkan KRS tanpa aktivitas lalu dengan presensi/nilai existing; pastikan kasus kedua masuk review dan data operasional tetap ada.
12. Finalisasi DPNA, buat outbox, lalu ubah nilai draft; pastikan payload outbox tidak berubah.
13. Kirim response sukses, timeout, HTTP 429/5xx, HTTP 2xx dengan business error, dan validation error; pastikan state/retry tepat.
14. Retry outbox; pastikan idempotency key dan payload sama.
15. Buka kembali lalu finalisasi versi baru; pastikan histori pengiriman lama tetap utuh dan outbox baru memakai key baru.

---

## 13. Rollback dan Recovery Non-Destruktif

### Sebelum go-live

1. Set `sync_enabled=false` dan `dpna_push_enabled=false`.
2. Hentikan scheduler/job integrasi.
3. Biarkan tabel dan kolom additive tetap ada.
4. Sembunyikan menu/route integrasi melalui feature flag dan permission.
5. Perbaiki mapper/kontrak lalu lanjutkan dari cursor batch terakhir yang committed.

### Setelah pengiriman aktif

1. Matikan `dpna_push_enabled` untuk menghentikan request baru.
2. Jangan menghapus outbox atau response.
3. Identifikasi detail `diproses` dengan lease kedaluwarsa dan kembalikan ke retryable memakai command recovery yang diaudit.
4. Untuk nilai salah, ikuti prosedur koreksi API `[KONFIRMASI API]`; jangan mengedit payload outbox terkirim.
5. Buat finalisasi/outbox versi baru bila koreksi berasal dari aplikasi lokal.

Rollback tidak memakai `migrate:rollback` karena migration sengaja additive dan histori harus dipertahankan.

---

## 14. Open Questions / Blocking Decisions

- [ ] Base URL, autentikasi, token lifecycle, dan target Neo Feeder versus middleware.
- [ ] Contoh JSON seluruh resource serta error envelope.
- [ ] Scope uniqueness setiap external ID.
- [ ] KRS dibuat pusat atau lokal.
- [ ] Makna dan cardinality kelas kuliah terhadap blok.
- [ ] Endpoint/identifier aktivitas mengajar dosen.
- [ ] Endpoint dan business key nilai historis.
- [ ] Skala nilai, batas range, dan pembulatan.
- [ ] Nilai terbaik atau terakhir untuk prasyarat/pengulangan.
- [ ] Semantics semester pendek.
- [ ] Apakah prasyarat membutuhkan `OR`; fase pertama tetap `AND`.
- [ ] Granularitas kirim nilai, koreksi nilai, dan dukungan idempotensi native API.
- [ ] Rate limit, ukuran page/batch, timeout, dan jadwal sync.
- [ ] Tombstone/status nonaktif serta strategi full reconciliation.

Selama pertanyaan blocking belum selesai, implementasi berhenti pada Task 1. Jangan membuat migration berdasarkan tebakan payload.