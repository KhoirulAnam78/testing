# Task 2 - Manajemen Kelas dan Kelas Sistem Blok

> **SUDAH DIGANTIKAN OLEH `task_4.md`.** Task 4 memindahkan peserta, kelompok, dan pertemuan
> dari `kelas` kembali ke `blok`, dan menurunkan `Kelas` menjadi rombel opsional di dalam satu
> Blok. Dokumen ini disimpan sebagai catatan sejarah keputusan. Untuk pekerjaan baru, ikuti
> `task_4.md`; jangan menghidupkan kembali menu `Kelas`/`Kelas Sistem Blok` atau tabel
> `peserta_kelas`, `kelompok_kelas_blok`, `anggota_kelompok_kelas_blok`,
> `pertemuan_kelas_blok`, dan `dosen_pertemuan_kelas_blok`.

## Tujuan

Task 2 dibatasi pada alur setelah pengelolaan Blok selesai: pengelola membuka kelas pada semester aktif, memasukkan mata kuliah dan mahasiswa yang mengontrak kelas tersebut, lalu mengelola kelas sistem blok untuk membagi mahasiswa ke kelompok sesuai jenis kegiatan blok.

Blok tetap diperlakukan sebagai template akademik. Susunan kegiatan, materi pokok, dan rincian materi disiapkan di menu Blok. Saat kelas dibuka dari mata kuliah yang sudah memiliki mapping blok, menu Kelas Sistem Blok memakai susunan tersebut sebagai dasar operasional kelas.

## Batas Implementasi Task 2

Yang dikerjakan pada Task 2:

1. Menyelesaikan penataan Blok sebagai template akademik.
2. Memastikan mata kuliah dapat dipetakan ke Blok.
3. Membuat menu Kelas untuk membuka kelas pada semester aktif.
4. Membuat peserta kelas dari mahasiswa yang mengontrak kelas tersebut.
5. Membuat menu Kelas Sistem Blok berisi daftar kelas yang sudah dibuka.
6. Membuat kelompok per jenis kegiatan blok pada konteks kelas.
7. Memilih anggota kelompok dari peserta kelas.
8. Menampilkan materi kelas secara otomatis mengikuti susunan materi pada Blok dan jenis kegiatan yang dipilih.

Yang tidak dikerjakan pada Task 2:

- Presensi mahasiswa.
- Monitoring atau jurnal pertemuan.
- Validasi kehadiran oleh dosen.
- Logbook atau refleksi mahasiswa.
- Penilaian, input nilai, dan rekap nilai.
- Laporan semester.
- Upload modul pembelajaran.
- Export/import data.
- Penjadwalan detail kalender pertemuan jika belum diperlukan untuk kelompok dan mapping materi.

Fitur di luar batas ini dipindahkan ke Task 3 atau task setelahnya.

## Keputusan Domain

- `blok` adalah template akademik semester aktif, bukan tempat peserta atau kelompok operasional.
- `aturan_kegiatan_blok` atau kegiatan blok adalah penghubung antara Blok dan jenis kegiatan.
- `materi_blok` adalah materi pokok dan wajib berada di bawah kegiatan blok.
- `materi_rinci_blok` adalah rincian materi atau rencana pertemuan dan wajib berada di bawah materi pokok.
- Mata kuliah yang memakai blok dipilih dari form Blok.
- Kelas adalah realisasi mata kuliah pada prodi dan semester tertentu.
- Peserta kelas adalah mahasiswa yang mengontrak mata kuliah pada kelas tersebut.
- Kelas Sistem Blok adalah ruang kerja pengelola untuk memecah peserta kelas menjadi kelompok sesuai jenis kegiatan blok.
- Materi pada Kelas Sistem Blok tidak diinput ulang. Materi otomatis terbaca dari susunan Blok sesuai jenis kegiatan.

## Alur Utama

1. Pengelola menyelesaikan pengelolaan Blok.
2. Pada Blok, pengelola menyusun jenis kegiatan blok.
3. Pada setiap jenis kegiatan blok, pengelola menyusun materi pokok dan rincian materi.
4. Pada Blok, pengelola memilih mata kuliah yang memakai Blok tersebut.
5. Pengelola membuka menu Kelas.
6. Pengelola memilih semester aktif dan prodi.
7. Pengelola membuat kelas, misalnya kode `R001`.
8. Pengelola memilih mata kuliah yang sudah memiliki mapping Blok.
9. Sistem menyimpan snapshot `blok_id` dari mata kuliah saat kelas dibuat.
10. Pengelola memasukkan mahasiswa yang mengontrak kelas tersebut.
11. Pengelola membuka menu Kelas Sistem Blok.
12. Sistem menampilkan daftar kelas yang sudah dibuka.
13. Pengelola memilih kelas.
14. Sistem menampilkan ringkasan kelas, susunan jenis kegiatan blok, dan materi dari Blok terkait.
15. Pada setiap jenis kegiatan yang membutuhkan kelompok, pengelola membuat kelompok.
16. Saat kelompok dibuat, sistem otomatis mengetahui materi yang relevan dari jenis kegiatan blok tersebut.
17. Anggota kelompok dipilih dari peserta kelas.
18. Mahasiswa pada kelas besar dapat dipecah menjadi beberapa kelompok berbeda sesuai jenis kegiatan.

## Status Implementasi

- [x] Audit struktur lama Task 2 yang langsung menggantung ke Blok.
- [x] Revisi relasi Blok dan mata kuliah.
- [x] UI create/edit Blok dengan susunan jenis kegiatan dan materi awal.
- [x] Penyesuaian materi agar berada di bawah kegiatan Blok.
- [x] UI form Blok untuk memilih banyak mata kuliah yang memakai Blok.
- [x] Migration/model untuk `kelas`.
- [x] Migration/model untuk `peserta_kelas`.
- [x] Migration/model untuk `kelompok_kelas_blok`.
- [x] Migration/model untuk `anggota_kelompok_kelas_blok`.
- [x] UI menu Kelas dengan filter prodi dan semester.
- [x] UI tambah/edit Kelas.
- [x] UI input peserta kelas.
- [x] UI menu Kelas Sistem Blok.
- [x] UI detail Kelas Sistem Blok dengan ringkasan, kelompok, dan materi.
- [ ] Validasi domain, akses, dan performa.

## Struktur Domain Task 2

### 1. Blok

Blok menyimpan susunan akademik yang akan dipakai oleh mata kuliah dan kelas.

Blok menyimpan:

- kode blok,
- nama blok,
- prodi jika blok spesifik prodi,
- semester atau semester kurikulum yang dipakai,
- status,
- susunan kegiatan blok,
- susunan materi per kegiatan,
- mapping ke mata kuliah.

Blok tidak menyimpan:

- peserta mahasiswa,
- kelompok operasional kelas,
- presensi,
- nilai,
- logbook,
- laporan.

### 2. Kegiatan Blok

Kegiatan blok adalah aturan kegiatan pada sebuah Blok.

Field penting:

| Field | Keterangan |
| --- | --- |
| `blok_id` | Relasi ke Blok |
| `jenis_kegiatan_id` | Relasi ke master jenis kegiatan |
| `durasi_menit` | Durasi default |
| `perlu_kelompok` | Selalu `true`; semua jenis kegiatan blok memakai kelompok belajar |
| `urutan` | Urutan tampil |
| `status` | Status aktif/nonaktif |

Validasi:

- satu jenis kegiatan tidak boleh duplikat pada Blok yang sama,
- materi pokok wajib berada di bawah kegiatan blok,
- setiap kegiatan blok wajib dapat dibuatkan kelompok belajar, termasuk Kuliah Pakar.

### 3. Materi Blok

Materi disusun sebagai template, bukan data operasional per kelas.

Struktur:

```text
blok
aturan_kegiatan_blok
materi_blok
materi_rinci_blok
```

Field materi pokok:

| Field | Keterangan |
| --- | --- |
| `aturan_kegiatan_blok_id` | Relasi ke kegiatan blok |
| `judul` | Judul materi pokok |
| `deskripsi` | Deskripsi opsional |
| `urutan` | Urutan tampil |
| `status` | Status aktif/nonaktif |

Field rincian materi:

| Field | Keterangan |
| --- | --- |
| `materi_blok_id` | Relasi ke materi pokok |
| `judul` | Judul rincian materi |
| `deskripsi` | Deskripsi opsional |
| `pertemuan_ke` | Nomor pertemuan opsional |
| `jumlah_sesi` | Jumlah sesi atau unit waktu rincian materi |
| `durasi_menit_per_sesi` | Otomatis mengikuti `aturan_kegiatan_blok.durasi_menit` |
| `urutan` | Urutan tampil |
| `status` | Status aktif/nonaktif |

Validasi:

- materi pokok harus berada di bawah kegiatan blok yang valid,
- rincian materi harus berada di bawah materi pokok yang valid,
- `pertemuan_ke` adalah urutan atau rencana jadwal rincian materi dan tidak dibatasi jumlah kegiatan blok,
- durasi rincian materi tidak diisi manual dan mengikuti durasi jenis kegiatan blok.

### 4. Mata Kuliah Memakai Blok

Form Blok menyediakan pilihan mata kuliah yang memakai Blok tersebut.

Keputusan awal:

- gunakan `blok_id` pada tabel `mata_kuliah` selama satu mata kuliah hanya memakai satu Blok aktif,
- mapping dilakukan dari form Blok,
- menu Mata Kuliah cukup menampilkan informasi Blok yang sedang dipakai.

Validasi:

- mata kuliah yang dipilih harus aktif,
- prodi mata kuliah dan prodi Blok harus sesuai jika keduanya memiliki `prodi_id`,
- kelas hanya dapat dibuat dari mata kuliah yang sudah memiliki `blok_id`.

### 5. Kelas

Kelas adalah realisasi mata kuliah pada prodi dan semester tertentu.

Contoh:

- kode kelas: `R001`
- prodi: Kedokteran
- semester: Ganjil 2026/2027
- mata kuliah: `KDK-123`
- blok: mengikuti mapping pada mata kuliah

Field yang disarankan:

| Field | Tipe | Keterangan |
| --- | --- | --- |
| `id_kelas` | bigint unsigned | Primary key |
| `prodi_id` | bigint unsigned | Relasi ke `prodi.id_prodi` |
| `semester_id` | bigint unsigned | Relasi ke `semester.id_semester` |
| `mata_kuliah_id` | bigint unsigned | Relasi ke `mata_kuliah.id_mata_kuliah` |
| `blok_id` | bigint unsigned | Snapshot Blok dari mata kuliah |
| `kode` | varchar | Contoh `R001` |
| `nama` | varchar nullable | Nama kelas |
| `kapasitas` | unsigned small integer nullable | Kapasitas kelas |
| `status` | enum | `draft`, `aktif`, `selesai`, `arsip` |
| `created_at` | timestamp nullable | Timestamp Laravel |
| `updated_at` | timestamp nullable | Timestamp Laravel |
| `deleted_at` | timestamp nullable | Soft delete |

Constraint:

- unique gabungan `semester_id`, `mata_kuliah_id`, dan `kode`,
- index `prodi_id`,
- index `semester_id`,
- index `blok_id`,
- index `status`.

Validasi:

- semester harus aktif atau dapat dipilih sesuai hak pengelola,
- mata kuliah harus aktif,
- mata kuliah harus sudah memiliki Blok,
- prodi kelas harus sama dengan prodi mata kuliah jika mata kuliah memiliki prodi,
- kode kelas tidak boleh duplikat untuk mata kuliah dan semester yang sama.

### 6. Peserta Kelas

Peserta kelas adalah mahasiswa yang mengontrak atau mengambil mata kuliah pada kelas tertentu.

Field yang disarankan:

| Field | Tipe | Keterangan |
| --- | --- | --- |
| `id_peserta_kelas` | bigint unsigned | Primary key |
| `kelas_id` | bigint unsigned | Relasi ke `kelas.id_kelas` |
| `mahasiswa_id` | bigint unsigned | Relasi ke `mahasiswa.id_mahasiswa` |
| `status` | enum | `aktif`, `mengulang`, `batal`, `selesai` |
| `tanggal_masuk` | date nullable | Tanggal masuk kelas |
| `catatan` | text nullable | Catatan administratif |
| `created_at` | timestamp nullable | Timestamp Laravel |
| `updated_at` | timestamp nullable | Timestamp Laravel |
| `deleted_at` | timestamp nullable | Soft delete |

Constraint:

- unique gabungan `kelas_id` dan `mahasiswa_id`,
- index `status`.

Validasi:

- mahasiswa harus satu prodi dengan kelas,
- mahasiswa tidak boleh terdaftar dua kali pada kelas yang sama,
- mahasiswa nonaktif tidak bisa ditambahkan,
- jumlah peserta tidak boleh melebihi kapasitas kelas jika kapasitas diisi.

### 7. Kelas Sistem Blok

Menu `Kelas Sistem Blok` menampilkan daftar kelas yang sudah dibuka dan memiliki Blok.

Detail minimal:

1. Ringkasan
2. Kelompok
3. Materi

Ringkasan menampilkan:

- kode kelas,
- mata kuliah,
- Blok,
- prodi,
- semester,
- jumlah peserta,
- jumlah jenis kegiatan,
- jumlah kelompok,
- jumlah materi pokok,
- jumlah rincian materi.

Catatan UI:

- daftar kelas memakai filter prodi, semester, mata kuliah, dan status,
- detail kelas sistem blok tidak menginput ulang materi,
- tab Materi hanya membaca susunan materi dari Blok yang dipakai kelas,
- tab Kelompok menjadi tempat memecah peserta kelas menjadi kelompok kecil.

### 8. Kelompok Kelas Sistem Blok

Kelompok dibuat dalam konteks kelas dan kegiatan blok.

Field yang disarankan:

| Field | Tipe | Keterangan |
| --- | --- | --- |
| `id_kelompok_kelas_blok` | bigint unsigned | Primary key |
| `kelas_id` | bigint unsigned | Relasi ke `kelas.id_kelas` |
| `aturan_kegiatan_blok_id` | bigint unsigned | Relasi ke kegiatan blok |
| `kode` | varchar | Contoh `A1`, `A2`, `P1` |
| `nama` | varchar | Nama kelompok |
| `kapasitas` | unsigned small integer nullable | Batas anggota |
| `status` | enum | `aktif`, `nonaktif` |
| `created_at` | timestamp nullable | Timestamp Laravel |
| `updated_at` | timestamp nullable | Timestamp Laravel |
| `deleted_at` | timestamp nullable | Soft delete |

Constraint:

- unique gabungan `kelas_id`, `aturan_kegiatan_blok_id`, dan `kode`,
- index `status`.

Validasi:

- kegiatan blok harus berasal dari Blok yang dipakai kelas,
- kegiatan blok harus memiliki `perlu_kelompok = true`,
- kode kelompok tidak boleh duplikat pada kelas dan kegiatan blok yang sama.

### 9. Anggota Kelompok Kelas Sistem Blok

Anggota kelompok dipilih dari peserta kelas.

Field yang disarankan:

| Field | Tipe | Keterangan |
| --- | --- | --- |
| `id_anggota_kelompok_kelas_blok` | bigint unsigned | Primary key |
| `kelompok_kelas_blok_id` | bigint unsigned | Relasi ke `kelompok_kelas_blok.id_kelompok_kelas_blok` |
| `peserta_kelas_id` | bigint unsigned | Relasi ke `peserta_kelas.id_peserta_kelas` |
| `peran` | enum | `anggota`, `ketua` |
| `created_at` | timestamp nullable | Timestamp Laravel |
| `updated_at` | timestamp nullable | Timestamp Laravel |

Constraint:

- unique gabungan `kelompok_kelas_blok_id` dan `peserta_kelas_id`.

Validasi:

- peserta harus berasal dari kelas yang sama,
- peserta tidak boleh masuk dua kelompok pada kegiatan blok yang sama dalam kelas yang sama,
- jumlah anggota tidak boleh melebihi kapasitas kelompok jika kapasitas diisi.

UI pemilihan anggota:

- tampilkan peserta kelas dengan checkbox,
- dukung pilih banyak sekaligus,
- pagination 10 atau 20 data,
- sediakan pencarian nama/NIM,
- peserta yang sudah masuk kelompok lain pada kegiatan blok yang sama tidak bisa dipilih lagi,
- peserta yang sudah masuk kelompok aktif tetap boleh ditampilkan dengan status agar pengelola paham alasannya tidak bisa dipilih.

### 10. Mapping Materi Otomatis

Saat pengelola menambahkan kelompok pada jenis kegiatan tertentu, sistem tidak membuat materi baru. Sistem membaca materi dari template Blok:

```text
kelompok_kelas_blok.aturan_kegiatan_blok_id
-> aturan_kegiatan_blok
-> materi_blok
-> materi_rinci_blok
```

Implikasi:

- kelompok Tutorial otomatis melihat materi Tutorial dari Blok,
- kelompok Praktikum otomatis melihat materi Praktikum dari Blok,
- jika susunan materi di Blok berubah, tampilan materi kelas ikut membaca susunan terbaru selama belum ada kebutuhan snapshot,
- tabel operasional materi kelas belum dibuat pada Task 2.

Jika nanti kelas membutuhkan jadwal, ruangan, status pelaksanaan, presensi, atau dosen per pertemuan, tabel operasional dibuat di Task 3.

## Tabel Lama yang Harus Dihindari untuk Task 2

> **Catatan Task 4:** larangan di bawah ini **tidak berlaku lagi**. Task 4 justru memakai
> `peserta_blok`, `kelompok_blok`, `anggota_kelompok_blok`, `pertemuan_blok`, dan
> `dosen_pertemuan_blok` sebagai tabel operasional resmi, sementara tabel `*_kelas_blok`
> pengganti yang disebut di bagian ini sudah dihapus. Lihat `task_4.md`.

Kode lama Task 2 yang langsung menggantung ke Blok perlu dihapus, diabaikan, atau tidak dipakai lagi:

- `peserta_blok`
- `kelompok_blok`
- `anggota_kelompok_blok`
- `pertemuan_blok`
- `dosen_pertemuan_blok`
- `modul_blok`

Pengganti pada alur baru:

- `peserta_kelas`
- `kelompok_kelas_blok`
- `anggota_kelompok_kelas_blok`

Pertemuan, dosen, presensi, logbook, nilai, dan modul menjadi scope Task 3 atau task setelahnya.

## Rekomendasi Urutan Implementasi

1. Audit migration/model/view lama yang masih memakai operasional langsung dari Blok.
2. Pastikan `materi_blok` sudah memiliki `aturan_kegiatan_blok_id`.
3. Pastikan `materi_rinci_blok` sudah memiliki `pertemuan_ke` jika dipakai untuk urutan rencana pertemuan.
4. Pastikan form Blok dapat memilih mata kuliah yang memakai Blok.
5. Buat migration/model `Kelas`.
6. Buat migration/model `PesertaKelas`.
7. Buat migration/model `KelompokKelasBlok`.
8. Buat migration/model `AnggotaKelompokKelasBlok`.
9. Buat menu dan route `Kelas`.
10. Buat halaman index dan tambah/edit Kelas.
11. Buat UI peserta kelas.
12. Buat menu dan route `Kelas Sistem Blok`.
13. Buat halaman daftar Kelas Sistem Blok.
14. Buat detail Kelas Sistem Blok dengan tab Ringkasan, Kelompok, dan Materi.
15. Implementasi validasi anggota kelompok dan mapping materi otomatis.
16. Jalankan migrasi, validasi Blade/PHP, dan test yang relevan.

## Validasi Penting

- Kelas hanya bisa dibuat jika mata kuliah sudah memiliki Blok.
- Mahasiswa peserta kelas harus berasal dari prodi yang sama dengan kelas.
- Peserta kelas tidak boleh duplikat dalam kelas yang sama.
- Kegiatan blok untuk kelompok harus berasal dari Blok yang dipakai kelas.
- Kelompok hanya boleh dibuat pada kegiatan yang membutuhkan kelompok.
- Mahasiswa yang sudah masuk kelompok pada kegiatan blok tertentu tidak bisa dipilih lagi untuk kelompok lain pada kegiatan yang sama.
- Kapasitas kelas dan kelompok tidak boleh terlampaui jika kapasitas diisi.
- Materi yang tampil pada kelas sistem blok harus berasal dari kegiatan blok yang sama dengan kelompok atau tab yang sedang dibuka.
- Operasi simpan multi-tabel wajib memakai `DB::transaction()`.

## Catatan Penamaan Menu

Nama menu yang dipakai pada Task 2:

- `Kelas`
- `Kelas Sistem Blok`

Nama `Kelola Kelas Blok` boleh dianggap nama lama. Untuk implementasi berikutnya gunakan `Kelas Sistem Blok` agar sesuai alur yang disepakati.
