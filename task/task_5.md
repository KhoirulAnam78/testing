# Task 5 - Komponen Penilaian dan Input Nilai per Pertemuan

## Tujuan

Menutup dua item Task 3 yang masih terbuka: **komponen penilaian** dan **input nilai
mahasiswa**. Alur kerja yang dilayani:

```text
susun master komponen penilaian (Keaktifan, Perilaku, MCQ, OSCE, ...)
-> tentukan standar per jenis kegiatan (Tutorial = Keaktifan 0-20 + Perilaku 0-30)
-> saat Blok disusun, rubrik disalin ke blok itu dan boleh disesuaikan
-> dosen pengampu mengisi nilai per mahasiswa pada setiap pertemuan
```

Ini melanjutkan `task/readme_first.md:21`: *"Komponen & Standar Penilaian: CRUD parameter
penilaian khusus per jenis blok (misal: Tutorial = Keaktifan & Perilaku; Kuliah = MCQ/CBT;
Skills Lab = OSCE)."*

## Keputusan Desain

### Tiga lapis, bukan satu

Nama tabel pada `task/task_3.md:58-60` belum dikunci, dan infiks `_kelas_` sudah usang
setelah Task 4 menurunkan `kelas` menjadi label. Nama final:

| Lapis | Tabel | Fungsi |
| --- | --- | --- |
| 1 | `komponen_penilaian` | master global, dipakai berulang lintas jenis kegiatan |
| 2 | `komponen_penilaian_kegiatan` | standar per `jenis_kegiatan`, hanya template |
| 3 | `komponen_penilaian_blok` | rubrik milik satu `aturan_kegiatan_blok` |
| data | `nilai_pertemuan_blok` | nilai satu mahasiswa untuk satu komponen pada satu pertemuan |

`nilai_pertemuan_blok` menggantung ke **lapis 3**, bukan ke master. Inilah alasan lapis 3
ada: batas `nilai_min`/`nilai_maks` ikut terkunci pada blok tersebut, sehingga mengubah
standar di kemudian hari tidak menggeser tafsir nilai blok yang sudah lampau. Pola yang
sama dengan `materi_rinci_blok.tanggal_rencana` yang sengaja tidak disalin antar blok.

### Batas per komponen, bukan bobot persen

Setiap komponen membawa `nilai_min` dan `nilai_maks` sendiri, misal Keaktifan 0-20 dan
Perilaku 0-30. Skor mentah dijumlah apa adanya; **tidak ada perhitungan pembobotan persen**
dan tidak ada validasi "total bobot harus 100". Total nilai maksimum per pertemuan adalah
jumlah `nilai_maks` seluruh komponen, ditampilkan di form Blok dan di form penilaian.

### Nilai tidak dikunci oleh validasi jurnal

`monitoring_pertemuan_blok.divalidasi_pada` mengunci presensi dan jurnal untuk semua peran.
**Penilaian sengaja dikecualikan.** Dosen pengampu sering baru selesai menilai setelah
pertemuan divalidasi, dan koreksi nilai adalah pekerjaan normal. Aturan ini berdiri sendiri
di `AksesPertemuanBlok::bolehIsiNilai()`, yang tidak memanggil `terkunci()`.

Konsekuensi yang harus disadari: tidak ada jejak finalisasi nilai. Bila nanti dibutuhkan,
tambahkan `difinalisasi_pada` pada tabel terpisah, jangan menumpang `divalidasi_pada`.

### Penanda `perlu_penilaian`

`aturan_kegiatan_blok` mendapat kolom `perlu_penilaian`, sejajar `perlu_presensi` dan
`perlu_logbook`, default `false` supaya blok yang sudah ada tidak berubah perilaku. Tombol
dan tab Nilai hanya muncul bila penanda ini menyala.

Karena penanda dan rubrik adalah dua hal terpisah, ada dua keadaan yang perlu ditangani UI:

- penanda menyala tapi rubrik kosong: form Blok menolak simpan, dan daftar pertemuan
  menampilkan badge `rubrik kosong`;
- penanda mati tapi nilai sudah ada: form penilaian tetap menampilkan nilainya dengan
  banner peringatan, tidak menyembunyikannya.

### Rubrik disusun di form Blok

Rubrik per blok diisi pada **tab Penilaian di `resources/views/pages/blok/add_edit.blade.php`**,
bukan di halaman Operasional Blok, karena rubrik adalah bagian dari susunan blok seperti
materi. Konsekuensinya form Blok kini punya empat lapis state:
`aturan[i].materi[j].rinci[k]` dan `aturan[i].komponen[j]`.

Tombol **Ambil dari Standar** menyalin lapis 2 ke lapis 3. Rubrik juga ikut tersalin oleh
`copyFromBlok()` dan `applyTemplateStandar()` — berbeda dari `tanggal_rencana`, komponen dan
batas nilai bukan data semester sehingga aman disalin.

### Yang belum dikerjakan dan alasannya

- **Rekap nilai blok.** Task ini hanya mengisi nilai per pertemuan. Agregasi nilai akhir per
  mahasiswa lintas pertemuan dan lintas jenis kegiatan, konversi huruf, dan transkrip
  (`task/readme_first.md:24`) belum dibuat.
- **Export/import nilai.** Belum ada.
- **Catatan per komponen.** `nilai_pertemuan_blok` hanya menyimpan angka. Umpan balik naratif
  per mahasiswa belum ada tempatnya; catatan sesi masih memakai
  `monitoring_pertemuan_blok.catatan_pelaksanaan`. Bila dibutuhkan, tambahkan kolom lewat
  migration terpisah.
- **Nilai untuk kegiatan tanpa kelompok.** Peserta sesi selalu dibaca dari
  `anggota_kelompok_blok`, mengikuti keputusan Task 4 bahwa semua kegiatan blok berbasis
  kelompok dan `pertemuan_blok.kelompok_blok_id` NOT NULL.

## Struktur Domain Task 5

```text
komponen_penilaian (id, kode unik, nama, nilai_min_default, nilai_maks_default, status)
 |  soft delete
 |
 +-- komponen_penilaian_kegiatan.komponen_penilaian_id   [cascade]
 |    jenis_kegiatan_id -> jenis_kegiatan.id             [cascade]
 |    unique (jenis_kegiatan_id, komponen_penilaian_id)  tanpa soft delete
 |
 +-- komponen_penilaian_blok.komponen_penilaian_id       [restrict]
      aturan_kegiatan_blok_id -> aturan_kegiatan_blok.id [cascade]
      unique (aturan_kegiatan_blok_id, komponen_penilaian_id)   dengan soft delete
       |
       +-- nilai_pertemuan_blok.komponen_penilaian_blok_id      [cascade]
            pertemuan_blok_id -> pertemuan_blok.id_pertemuan_blok  [cascade]
            peserta_blok_id   -> peserta_blok.id_peserta_blok      [cascade]
            unique (pertemuan_blok_id, peserta_blok_id, komponen_penilaian_blok_id)
            tanpa soft delete
```

### Pola soft delete yang berbeda antar tabel

Ini bukan ketidakkonsistenan, tiga tabel memakai tiga pola karena alasan berbeda:

- `komponen_penilaian_kegiatan` **tanpa** soft delete. Punya kunci bisnis, ditulis lewat
  `updateOrCreate`, dan tidak ada data operasional yang menggantung.
- `komponen_penilaian_blok` **dengan** soft delete, karena `nilai_pertemuan_blok` menggantung
  ke sini: komponen yang dibuang dari rubrik tidak boleh meng-cascade menghapus nilainya.
  Karena baris soft-deleted tetap menempati unique index, simpan lewat:

```php
$model = KomponenPenilaianBlok::withTrashed()->firstOrNew([
    'aturan_kegiatan_blok_id' => $aturan->id,
    'komponen_penilaian_id' => (int) $baris['komponen_penilaian_id'],
]);
$model->fill([...]);
if ($model->trashed()) { $model->restore(); }
$model->save();
```

- `nilai_pertemuan_blok` **tanpa** soft delete. Ditulis lewat `updateOrCreate` atas kunci
  bisnis. Nilai yang dikosongkan dosen dihapus permanen, sehingga "ada baris" berarti "sudah
  dinilai" dan badge kelengkapan bisa dihitung dengan `withCount` saja.

### Pengaman kehilangan nilai

`nilai_pertemuan_blok.komponen_penilaian_blok_id` memakai `cascadeOnDelete` supaya hapus
permanen blok dan `migrate:fresh` tetap jalan. Karena itu pengaman terhadap kehilangan nilai
**tidak** ada di foreign key, melainkan di `lolosPengecekanNilaiTersimpan()` pada form Blok:
komponen yang sudah punya nilai ditolak saat hendak dibuang dari rubrik, dengan pesan yang
menyebut nama komponennya. Pengecekan itu dua query, bukan satu per baris.

## Menu, Route, dan Hak Akses

| Menu | Route | Permission | Fungsi |
| --- | --- | --- | --- |
| Komponen Penilaian | `komponen-penilaian.index`, `komponen-penilaian.add_edit` | `komponen-penilaian:` | Master komponen penilaian |

Menu didaftarkan lewat `database/migrations/2026_08_19_000005_register_komponen_penilaian_menu.php`,
di bawah menu induk Akademik yang sudah ada. Migration itu **mencari** menu Akademik dan hanya
membuatnya bila belum ada, supaya posisi dan icon yang sudah diatur pengelola lewat Kelola Menu
tidak tertimpa. Izin diberikan dengan `Role::findOrCreate(...)`, bukan `Role::whereIn(...)->get()`.

Kedua halaman `komponen-penilaian` memeriksa hak akses sendiri dengan
`abort_unless(auth()->user()?->can('komponen-penilaian:'), 403)` di `mount()`, tidak hanya
mengandalkan menu yang tersembunyi.

Standar per jenis kegiatan tidak punya menu sendiri; ia diisi di dalam form Jenis Kegiatan yang
sudah ada, memakai permission `jenis-kegiatan:` yang berlaku di sana.

## Halaman dan Komponen

| Lokasi | Isi |
| --- | --- |
| `pages/komponen-penilaian/index.blade.php` | PowerGrid `TableKomponenPenilaian`, badge jumlah pemakaian |
| `pages/komponen-penilaian/add_edit.blade.php` | Master: kode, nama, deskripsi, batas default, status |
| `pages/jenis-kegiatan/add_edit.blade.php` | Card "Standar Komponen Penilaian", baris berulang |
| `pages/blok/add_edit.blade.php` | Tab Penilaian per kegiatan, tombol Ambil dari Standar, penanda `perlu_penilaian` |
| `components/blok-operasional/nilai-pertemuan.blade.php` | Matriks mahasiswa x komponen, satu implementasi untuk dua pemanggil |
| `pages/pertemuan-saya/index.blade.php` | Tab Nilai pada modal pelaksanaan (dosen) |
| `components/blok-operasional/monitoring.blade.php` | Tab Nilai pada modal pelaksanaan (pengelola) |

Modal pelaksanaan kini punya tiga tab. Daftar mode yang sah dipusatkan pada konstanta
`MODE_PELAKSANAAN` di masing-masing halaman host, dipakai untuk memvalidasi mode yang dikirim
klien baik saat membuka modal maupun saat berpindah tab.

## Aturan Query dan Performa

- Peserta sesi dan rubrik **selalu dibaca ulang dari database** saat menyimpan, tidak dari
  kunci array state Livewire, karena kunci array bisa disusupkan dari klien. Sama seperti
  presensi.
- Prefill nilai memakai satu query untuk seluruh matriks, lalu dipetakan di PHP. Tidak ada
  query per mahasiswa atau per komponen.
- Badge kelengkapan nilai pada daftar pertemuan memakai
  `withCount('nilai_pertemuan_blok as nilai_tercatat_count')` plus `withCount` bersarang
  `komponen_penilaian_blok` pada relasi `aturan_kegiatan_blok`. Target isian dihitung sebagai
  jumlah komponen dikali jumlah anggota kelompok.
- `applyTemplateStandar()` mengambil seluruh standar dalam satu query lalu `groupBy`, bukan
  satu query per jenis kegiatan di dalam loop.
- `pertemuan()` pada komponen penilaian di-cache per request karena dipakai `anggota()`,
  `komponen()`, dan `render()`.

## Validasi

- Batas nilai berbeda per komponen, jadi rule dibangun per sel matriks di
  `aturanValidasi()`: `['nullable', 'numeric', 'min:'.$item->nilai_min, 'max:'.$item->nilai_maks]`.
  Batas selalu dibaca dari `komponen_penilaian_blok`, bukan dari atribut `min`/`max` di HTML.
- Form Blok menolak: komponen duplikat dalam satu kegiatan, `nilai_maks <= nilai_min`, dan
  kegiatan bertanda `perlu_penilaian` tanpa komponen. Error diarahkan ke tab Penilaian lewat
  `failOnPenilaianTab()` dan `tabForValidationErrors()`.
- Form Jenis Kegiatan menolak komponen duplikat dan `nilai_maks <= nilai_min` pada standar.
- Hapus master komponen ditolak bila masih dipakai rubrik blok atau standar jenis kegiatan,
  dengan pesan yang menyebut jumlah pemakaiannya, alih-alih membiarkan foreign key gagal.

## Status Implementasi

- [x] Migration tabel `komponen_penilaian`, `komponen_penilaian_kegiatan`,
      `komponen_penilaian_blok`, `nilai_pertemuan_blok`.
- [x] Migration kolom `aturan_kegiatan_blok.perlu_penilaian`.
- [x] Migration menu dan permission `komponen-penilaian:`.
- [x] Model `KomponenPenilaian`, `KomponenPenilaianKegiatan`, `KomponenPenilaianBlok`,
      `NilaiPertemuanBlok`, plus relasi baru pada `JenisKegiatan`, `AturanKegiatanBlok`,
      `PertemuanBlok`, `PesertaBlok`.
- [x] `AksesPertemuanBlok::bolehIsiNilai()`.
- [x] CRUD master komponen penilaian.
- [x] Standar komponen penilaian per jenis kegiatan.
- [x] Tab Penilaian pada form Blok, termasuk salin dari standar dan salin antar blok.
- [x] Komponen pengisian nilai per pertemuan.
- [x] Tab Nilai pada halaman Pertemuan Saya dan tab Monitoring.
- [ ] Rekap nilai blok dan transkrip.
- [ ] Export nilai.

## Verifikasi

Jalankan sendiri:

```bash
php artisan migrate
php artisan route:list
php artisan view:clear
vendor/bin/pint --dirty
```

`view:clear` wajib karena Blade yang sudah terkompilasi masih merujuk struktur tab lama.

Langkah pemeriksaan manual:

1. Menu **Akademik > Komponen Penilaian** muncul untuk admin/pengelola. Tambah dua komponen,
   misal `KEAKTIFAN` 0-20 dan `PERILAKU` 0-30.
2. Buka **Jenis Kegiatan > Tutorial**, tambahkan kedua komponen pada card Standar Komponen
   Penilaian, simpan. Buka kembali dan pastikan barisnya kembali dengan batas yang sama.
3. Buka **Blok > Kelola** salah satu blok, masuk tab **Penilaian**, pilih kegiatan Tutorial,
   tekan **Ambil dari Standar**. Pastikan dua komponen masuk dan penanda "Kegiatan ini
   dinilai" menyala. Simpan.
4. Coba matikan penanda penilaian sambil komponen masih terisi, lalu coba nyalakan penanda
   dengan komponen dikosongkan — yang kedua harus ditolak dengan pesan di tab Penilaian.
5. Buka **Operasional Blok > Monitoring**, kolom Nilai menampilkan `belum diisi` dan tombol
   **Nilai** muncul untuk pertemuan Tutorial. Isi beberapa nilai, simpan, dan pastikan badge
   berubah menjadi `n/total isian` lalu `lengkap`.
6. Coba isi nilai di luar batas, misal 25 pada komponen bermaksimum 20 — harus ditolak dengan
   pesan yang menyebut nama komponennya.
7. Kosongkan satu nilai yang sudah tersimpan, simpan, dan pastikan barisnya hilang (badge
   kembali ke `n/total isian`).
8. Login sebagai dosen pengampu, buka **Pertemuan Saya**, pastikan tombol dan tab **Nilai**
   berfungsi dan hanya untuk pertemuan yang diampu.
9. Validasi jurnal pertemuan itu, lalu pastikan presensi terkunci **tetapi nilai masih bisa
   diubah**.
10. Kembali ke form Blok, coba buang komponen yang sudah punya nilai — harus ditolak dengan
    pesan yang menyebut nama komponennya.

## Backlog Setelah Task 5

1. Rekap nilai blok per mahasiswa lintas pertemuan dan lintas jenis kegiatan, plus transkrip.
2. Logbook atau refleksi mahasiswa.
3. Mahasiswa melihat nilainya sendiri di portal.
4. Export/import nilai dan laporan semester.
5. Finalisasi nilai bila kebijakan akademik nanti membutuhkan jejaknya.
