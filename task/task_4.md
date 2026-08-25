# Task 4 - Blok sebagai Pusat Operasional

## Tujuan

Menyelaraskan implementasi dengan alur kerja pengelola yang sebenarnya:

```text
semester baru
-> buat Blok (nama, jenis kegiatan, materi, mapping mata kuliah, tanggal rencana pertemuan)
-> masukkan peserta blok
-> bagi peserta ke kelompok per jenis kegiatan
-> isi dosen pengampu dan jadwal per kelompok per pertemuan
```

## Keputusan Desain yang Menggantikan Task 2 dan Task 3

Task 2 memindahkan seluruh data operasional dari Blok ke `Kelas` dan secara eksplisit
melarang tabel `peserta_blok`, `kelompok_blok`, `anggota_kelompok_blok`, `pertemuan_blok`,
serta `dosen_pertemuan_blok`. **Task 4 membalik keputusan itu.**

Alasannya: pada sistem blok kedokteran, seluruh mahasiswa satu angkatan mengambil blok yang
sama. Mewajibkan pengelola membuat Kelas sebelum bisa memasukkan peserta menambah satu
langkah administratif yang tidak ada di alur nyata, dan membuat entitas pusat sistem menjadi
`kelas` padahal semua aturan akademik (kegiatan, materi, jumlah pertemuan, durasi) melekat
pada `blok`.

### Nasib lapisan Kelas

`Kelas` tetap ada, tetapi **turun pangkat menjadi rombel opsional di dalam satu Blok**.
Kelas tidak lagi memiliki peserta, kelompok, atau pertemuan. Ia hanya menjadi label pada
`peserta_blok.kelas_id` dan `kelompok_blok.kelas_id`, dipakai bila satu blok perlu dipecah
menjadi beberapa rombongan paralel.

Konsekuensi penting: **hanya ada satu jalur data operasional.** Seluruh kode berjalan lewat
`blok_id`. `kelas_id` memengaruhi tepat satu aturan validasi (anggota kelompok ber-rombel
harus berasal dari rombel yang sama) dan filter tampilan. Blok tanpa rombel bekerja penuh
tanpa satu baris `kelas` pun. Jangan mengembalikan `kelas` menjadi container operasional.

Kolom `prodi_id`, `semester_id`, dan `mata_kuliah_id` dibuang dari `kelas` karena semuanya
sudah dimiliki `blok`; menyimpannya dua kali hanya menciptakan risiko data drift.

### Yang belum dikerjakan dan alasannya

- **Mahasiswa mengambil blok sendiri (self-enroll)** belum dibuat. Peserta diinput pengelola.
  Skema `peserta_blok` sudah siap, sehingga self-enroll nanti cukup menambah halaman
  mahasiswa tanpa mengubah tabel.

## Struktur Domain Task 4

```text
blok (prodi, semester, kode, nama, sks, tanggal_mulai/selesai, status)
 |- mata_kuliah.blok_id                mapping mata kuliah, diatur dari form Blok
 |- aturan_kegiatan_blok               jenis kegiatan, perlu_kelompok selalu true
 |   `- materi_blok
 |       `- materi_rinci_blok          + tanggal_rencana, jam_mulai_rencana, jam_selesai_rencana
 |- kelas                              rombel OPSIONAL
 |- peserta_blok                       blok_id, mahasiswa_id, kelas_id?, status
 |- kelompok_blok                      blok_id, aturan_kegiatan_blok_id, kelas_id?, kode
 |   `- anggota_kelompok_blok          kelompok_blok_id, peserta_blok_id
 `- pertemuan_blok                     blok_id, aturan_kegiatan_blok_id, materi_rinci_blok_id, kelompok_blok_id
     `- dosen_pertemuan_blok           pertemuan_blok_id, dosen_id, peran
```

### Tanggal rencana pertemuan

`materi_rinci_blok` menyimpan `tanggal_rencana`, `jam_mulai_rencana`, dan
`jam_selesai_rencana`. Nilainya diisi di form Blok dan berfungsi sebagai **default**, bukan
jadwal final. Saat pengelola membuka mapping dosen pada satu rincian materi, tanggal dan jam
tiap kelompok terisi otomatis dari template dan **boleh diubah per kelompok**, karena
kegiatan seperti praktikum berjalan bergilir (P1 Senin, P2 Selasa, dan seterusnya).

Catatan penting: `copyFromBlok()` pada form Blok **tidak menyalin tanggal rencana**. Tanggal
bersifat spesifik per semester, menyalinnya akan menghasilkan jadwal salah secara diam-diam.
Tombol `Buat dari Pertemuan` mengusulkan tanggal mingguan dari `blok.tanggal_mulai`.

### Kolom NOT NULL yang disengaja

`pertemuan_blok.materi_rinci_blok_id` dan `pertemuan_blok.kelompok_blok_id` dibuat NOT NULL,
berbeda dari `pertemuan_kelas_blok` yang lama. Seluruh kegiatan blok sudah dipaksa berkelompok,
dan MySQL mengizinkan banyak baris NULL pada unique index, sehingga versi nullable tidak
melindungi `updateOrCreate` di level database.

### Pola soft delete pada tabel dengan kunci bisnis

`peserta_blok`, `kelompok_blok`, `pertemuan_blok`, dan `kelas` semuanya memakai soft delete,
dan unique index-nya tetap ditempati baris yang sudah dihapus lembut. Karena itu semua alur
simpan memakai pola yang sama:

```php
$model = Model::withTrashed()->firstOrNew([...kunci bisnis...]);
$model->fill([...]);
if ($model->trashed()) { $model->restore(); }
$model->save();
```

Jangan memakai `updateOrCreate()` tanpa `withTrashed()` pada tabel-tabel ini; kode akan gagal
dengan pelanggaran unique constraint begitu ada baris yang pernah dihapus.

Saat menghapus, turunannya dibersihkan manual di dalam `DB::transaction()`:

- hapus peserta -> keanggotaan kelompok dihapus permanen, peserta dihapus lembut;
- hapus kelompok -> keanggotaan dihapus permanen, pertemuan dihapus lembut, kelompok dihapus lembut;
- hapus rombel -> hanya boleh bila tidak dipakai peserta maupun kelompok.

## Menu, Route, dan Hak Akses

| Menu | Route | Permission | Fungsi |
| --- | --- | --- | --- |
| Blok | `blok.index`, `blok.add_edit` | `blok:` | Susunan blok: kegiatan, materi, mapping mata kuliah, tanggal rencana |
| Operasional Blok | `blok-operasional.index`, `blok-operasional.detail` | `blok-operasional:` | Peserta, rombel, kelompok, dosen pengampu per pertemuan |

Menu `Kelas` dan `Kelas Sistem Blok` beserta permission `kelas:` dan `kelas-sistem-blok:`
dipensiunkan. Rombel dikelola sebagai tab di dalam detail Operasional Blok.

Halaman Operasional Blok memeriksa hak akses sendiri dengan
`abort_unless(auth()->user()?->can('blok-operasional:'), 403)`, tidak hanya mengandalkan
menu yang tersembunyi.

## Halaman Detail Operasional Blok

Halaman shell `resources/views/pages/blok-operasional/detail.blade.php` hanya berisi
ringkasan blok, navigasi tab, dan script pengontrol modal. Isi tiap tab dipecah menjadi
komponen Livewire tersendiri di `resources/views/components/blok-operasional/`:

| Tab | Komponen | Isi |
| --- | --- | --- |
| Ringkasan | inline di shell | hitungan agregat dan urutan pengerjaan |
| Peserta | `peserta.blade.php` | cari dan tambah mahasiswa, ubah rombel/status, keluarkan peserta |
| Rombel | `rombel.blade.php` | CRUD rombel opsional |
| Kelompok | `kelompok.blade.php` | kelompok per jenis kegiatan, pemilihan anggota, `Bagi Merata` |
| Pertemuan | `pertemuan.blade.php` | daftar materi template, mapping dosen dan jadwal per kelompok |

Pemecahan ini disengaja: `pages/kelas-sistem-blok/detail.blade.php` yang lama sudah ~970
baris untuk 3 tab. Script modal diletakkan di shell karena `@push` dari komponen Livewire
anak tidak ikut terkirim pada request update.

### Bagi Merata

Tab Kelompok menyediakan pembuatan kelompok massal: masukkan jumlah kelompok dan awalan
kode, lalu peserta aktif dibagi merata mengikuti urutan nama. Ini yang membuat skenario
"Kuliah Pakar 2 kelompok, Praktikum 4 kelompok" tidak perlu diketik manual. Aksi ini
**mengganti** kelompok yang sudah ada pada jenis kegiatan dan lingkup rombel yang sama,
termasuk pertemuannya, dan sudah diberi konfirmasi eksplisit.

## Aturan Query dan Performa

Mengikuti `task/optimalization.md`:

- Pencarian dan pengurutan peserta, kandidat mahasiswa, dan anggota kelompok dijalankan di
  SQL dengan pagination. Kode lama memfilter koleksi PHP (`filteredPesertaKelas()`,
  `filteredMahasiswa()`), yang tidak bertahan ketika satu blok berisi ratusan peserta.
- Ringkasan blok memakai satu query `withCount` dengan subselect, bukan memuat koleksi.
- Daftar dosen pada modal mapping dibatasi 20 hasil pencarian; dosen yang sudah dipilih
  selalu ikut ditampilkan agar tidak hilang saat pencarian dipersempit.
- Seluruh penyimpanan multi-tabel memakai `DB::transaction()`.

## Bug Lama yang Diperbaiki

`pages/kelas-sistem-blok/detail.blade.php:54` melakukan `(int) $id` padahal
`TableKelasSistemBlok::actions()` mengirim id terenkripsi, sehingga `findOrFail()` pada
halaman detail itu selalu gagal. Halaman baru mendekripsi di dalam `try/catch` mengikuti pola
`pages/blok/add_edit.blade.php`.

## Status Implementasi

- [x] Migration `add_rencana_waktu_to_materi_rinci_blok`.
- [x] Migration `realign_blok_operasional_tables` (drop tabel `*_kelas_blok`, `kelas` jadi rombel, buat tabel `*_blok`).
- [x] Migration `realign_blok_operasional_menus`.
- [x] Migration `seed_example_blok_operasional_data`.
- [x] Model `PesertaBlok`, `KelompokBlok`, `AnggotaKelompokBlok`, `PertemuanBlok`, `DosenPertemuanBlok`.
- [x] Penyesuaian model `Blok`, `Kelas`, `AturanKegiatanBlok`, `MateriRinciBlok`, `Mahasiswa`.
- [x] Penghapusan model, tabel PowerGrid, dan halaman `Kelas` serta `Kelas Sistem Blok`.
- [x] Input tanggal rencana pertemuan pada form Blok beserta validasinya.
- [x] Peringatan rincian tanpa tanggal rencana pada tab Review form Blok.
- [x] Guard hapus Blok saat sudah punya peserta, kelompok, atau pertemuan.
- [x] `TableBlokOperasional` dan halaman index Operasional Blok.
- [x] Halaman detail Operasional Blok beserta empat komponen tab.
- [ ] `php -l` pada berkas baru/berubah (belum dijalankan pada sesi implementasi).
- [ ] `vendor/bin/pint --dirty` (belum dijalankan pada sesi implementasi).
- [ ] `php artisan migrate:fresh --seed` (belum dijalankan).
- [ ] Verifikasi manual end-to-end oleh pengelola.

## Verifikasi

Belum ada perintah verifikasi yang dijalankan pada sesi implementasi, jadi jalankan seluruh
langkah di bawah ini sebelum menganggap Task 4 selesai.

```bash
php artisan migrate:fresh --seed
php artisan route:list
php artisan view:clear
vendor/bin/pint --dirty
```

`php artisan view:clear` wajib dijalankan: cache Blade lama di `storage/framework/views`
masih memuat komponen `table-kelas-sistem-blok` dan route `kelas.add_edit` yang sudah dihapus.

Uji manual:

1. **Blok** -> Tambah Blok: identitas, mata kuliah, jenis kegiatan, materi dan rincian beserta
   tanggal rencana. Cek tab Review memperingatkan rincian yang tanggalnya masih kosong.
2. **Operasional Blok** -> daftar blok tampil dengan hitungan peserta/kelompok/pertemuan;
   klik `Kelola`.
3. Tab **Peserta**: cari dan tambah mahasiswa; pastikan mahasiswa prodi lain dan yang sudah
   terdaftar tidak muncul sebagai kandidat.
4. Tab **Rombel**: biarkan kosong dan pastikan seluruh alur tetap jalan. Lalu buat satu
   rombel, isikan ke sebagian peserta, dan coba hapus rombel yang masih dipakai.
5. Tab **Kelompok**: `Bagi Merata` 2 kelompok pada Kuliah Pakar dan 4 kelompok pada
   Praktikum; pastikan satu mahasiswa tidak bisa masuk dua kelompok pada kegiatan yang sama.
6. Tab **Pertemuan**: klik `Kelola` pada satu rincian materi; tanggal dan jam harus terisi
   otomatis dari tanggal rencana. Ubah tanggal salah satu kelompok, isi dosen pengampu,
   simpan, lalu buka ulang untuk memastikan perubahan per kelompok tersimpan.
7. Login dengan role tanpa permission `blok-operasional:` -> halaman harus 403.

## Backlog Setelah Task 4

Urutan yang disarankan, semuanya menggantung ke `pertemuan_blok` dan `peserta_blok`:

1. Mahasiswa mengambil blok sendiri (self-enroll) dengan periode buka/tutup.
2. Presensi mahasiswa per pertemuan; peserta sesi berasal dari `anggota_kelompok_blok`.
3. Monitoring atau jurnal pelaksanaan pertemuan dan validasinya oleh dosen.
4. Logbook atau refleksi mahasiswa.
5. Komponen penilaian, input nilai, dan rekap nilai blok.
6. Repositori modul pembelajaran.
7. Export/import dan laporan semester.
