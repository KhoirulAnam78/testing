# Daftar Perbaikan

Catatan temuan saat mengisi pelaksanaan **Blok 3 Sistem Digestif** (`blok.id = 6`) secara
lengkap: dosen pengampu, presensi, jurnal monitoring, penilaian, sampai nilai akhir DPNA.
Diurutkan dari yang paling menghambat. Setiap butir menyebut lokasi kode, akibatnya, dan
usul perbaikannya.

Kondisi data blok itu setelah pengisian, sebagai konteks buat pembaca berikutnya: 11
peserta, 5 jenis kegiatan, 9 kelompok, 65 pertemuan (17 Kuliah Pakar + 36 Praktikum +
4 Tutorial + 6 OSPE + 2 MCQ), 396 baris presensi, 65 jurnal tervalidasi, 242 baris nilai,
176 baris rekap. Bobot DPNA: kehadiran 10%, Praktikum 25%, Tutorial 25%, MCQ 40%. Kuliah
Pakar dan Ujian OSPE sengaja hanya berisi presensi dan jurnal karena penanda
`perlu_penilaian`-nya mati dan rubriknya kosong — keputusan "ikut apa adanya", bukan
kelalaian. Seluruh 11 peserta sudah punya nilai akhir DPNA.

Status:

- **[SELESAI]** sudah dikerjakan, dicatat di sini sebagai jejak keputusan
- **[BLOKIR]** membuat halaman/fitur tidak bisa dipakai
- **[SALAH]** jalan tapi hasilnya keliru atau menyesatkan
- **[BERSIH]** kode/dokumen usang, tidak merusak perilaku
- **[GAP]** fitur yang memang belum ada

---

## 1. [SELESAI] `aturan_kegiatan_blok.jumlah_pertemuan` sudah dihapus tapi masih dibaca di 5 tempat

Migrasi `database/migrations/2026_08_24_000003_remove_jumlah_pertemuan_from_aturan_kegiatan_blok.php`
menghapus kolom `jumlah_pertemuan`, tetapi kolom itu masih dipakai:

| Lokasi | Cara pakai | Akibat |
| --- | --- | --- |
| `resources/views/components/blok-operasional/pertemuan.blade.php:73` | `->get([... 'jumlah_pertemuan' ...])` | SQL error `Unknown column`, **tab Pertemuan mati total** |
| `resources/views/components/blok-operasional/kelompok.blade.php:92` | `->get([... 'jumlah_pertemuan' ...])` | SQL error, **tab Kelompok mati total** |
| `resources/views/components/blok-operasional/kelompok.blade.php:493` | `{{ $aturanAktif->jumlah_pertemuan }} pertemuan` | tampil kosong |
| `app/Support/PerhitunganDpnaBlok.php:101` | `->sum('jumlah_pertemuan')` | selalu `0`, sehingga `$kehadiran` selalu `null` |
| `app/Support/PerhitunganDpnaBlok.php:117` | `$ids->count() === $aturan->jumlah_pertemuan` | perbandingan `int === null` selalu `false`, sehingga nilai tiap kegiatan selalu `null` |

Akibat di DPNA: karena `nilaiAkhir()` (`PerhitunganDpnaBlok.php:30`) mengembalikan `null`
begitu ada satu sumber aktif yang nilainya `null`, **kolom Nilai Akhir DPNA akan selalu
"Belum Lengkap"**, sebanyak apa pun data presensi dan nilai yang diisi.

Dua test juga masih menulis kolom itu dan akan gagal:
`tests/Feature/DashboardRoleTest.php:142`, `tests/Feature/LogbookPertemuanBlokTest.php:226`.

**Usul perbaikan.** Ganti sumber "berapa pertemuan yang seharusnya ada" dari kolom statis
menjadi hitungan nyata, karena satu pertemuan memang lahir dari satu `materi_rinci_blok`
dikali satu `kelompok_blok`.

Alternatif yang lebih murah tapi rapuh: hidupkan kembali kolomnya dan jaga agar selalu
sama dengan jumlah rincian materi. Tidak dipilih — dua sumber kebenaran untuk angka yang
sama adalah asal masalah ini, dan justru itu alasan kolomnya dihapus.

> **Terkonfirmasi di database.** Migrasi `2026_08_24_000003` sudah jalan (batch 27), dan
> `aturan_kegiatan_blok` sekarang berisi: `id, blok_id, jenis_kegiatan_id, durasi_menit,
> jumlah_mahasiswa_per_kelompok, perlu_kelompok, perlu_presensi, perlu_logbook,
> perlu_penilaian, nilai_masuk_dpna, bobot_nilai_dpna, urutan, created_at, updated_at,
> deleted_at` — tanpa `jumlah_pertemuan`. Tab Pertemuan dan tab Kelompok memang sudah
> error, dan DPNA memang tidak bisa menghasilkan nilai akhir sama sekali.

### Yang sudah dikerjakan

| File | Perubahan |
| --- | --- |
| `app/Models/AturanKegiatanBlok.php` | relasi baru `materi_rinci_blok()` (`HasManyThrough` lewat `materi_blok`), dengan `whereNull('materi_blok.deleted_at')` karena `hasManyThrough` tidak menerapkan soft delete tabel perantara |
| `app/Support/PerhitunganDpnaBlok.php` | `withCount` menambah `materi_rinci_blok`; `sum('jumlah_pertemuan')` → `sum('materi_rinci_blok_count')`; `=== $aturan->jumlah_pertemuan` → `=== (int) $aturan->materi_rinci_blok_count` |
| `resources/views/components/blok-operasional/pertemuan.blade.php` | `jumlah_pertemuan` dikeluarkan dari `->get([...])`, `withCount` menambah `materi_rinci_blok` |
| `resources/views/components/blok-operasional/kelompok.blade.php` | idem, dan teks "N pertemuan × M menit" memakai `materi_rinci_blok_count` dengan label "per kelompok" |
| `resources/views/pages/dpna-blok/detail.blade.php` | keterangan bobot memakai jumlah pertemuan per mahasiswa, bukan total seluruh kelompok |
| `tests/Feature/DashboardRoleTest.php`, `tests/Feature/LogbookPertemuanBlokTest.php` | berhenti menulis kolom yang sudah tidak ada |

**Keputusan desain yang perlu diketahui agent berikutnya.** Jumlah pertemuan yang
seharusnya ada untuk satu kelompok pada satu kegiatan = `withCount('materi_rinci_blok')`
pada `AturanKegiatanBlok`. Jangan menghidupkan kembali kolom `jumlah_pertemuan`.
`pertemuan_blok_count` bukan penggantinya: itu jumlah pertemuan seluruh kelompok.

Belum diverifikasi dengan menjalankan test — `php artisan test` belum dijalankan pada
perubahan ini.

---

## 2. [SALAH] `simpanBobot()` DPNA error bila ada kegiatan baru setelah halaman dibuka

`resources/views/pages/dpna-blok/detail.blade.php:93`

```php
foreach ($blok->aturan_kegiatan_blok as $aturan) {
    $config = $this->kegiatan[$aturan->id];   // <- tanpa ?? default
```

`$this->kegiatan` diisi sekali di `mount()` (baris 35-40). Bila kegiatan blok ditambah
dari form Blok sementara halaman DPNA masih terbuka, penyimpanan bobot melempar
`Undefined array key`. Baris 62 di method yang sama sudah memakai
`?? ['aktif' => false, 'bobot' => 0]`; baris 93 terlewat.

**Usul perbaikan.** Pakai default yang sama di baris 93, atau lebih baik: hitung `$sumber`
sekali lalu pakai ulang untuk validasi dan penyimpanan supaya tidak ada dua tempat yang
harus disamakan.

---

## 3. [SALAH] Rekap nilai pertemuan tetap ditulis walau semua nilai dikosongkan

`resources/views/components/blok-operasional/nilai-pertemuan.blade.php:172-209`

Loop `simpan()` menghapus baris `nilai_pertemuan_blok` untuk sel yang dikosongkan, tetapi
`RekapNilaiPertemuanBlok::updateOrCreate` di baris 202 **selalu** dijalankan. Akibatnya:

- mahasiswa yang seluruh nilainya dihapus tetap menyisakan baris rekap `total = 0`,
  `nilai_akhir = 0`. Dari sisi data, "belum dinilai" jadi tidak bisa dibedakan dari
  "dinilai nol";
- kalau baru sebagian komponen diisi, `nilai_akhir` dihitung dengan pembagi
  `$nilaiMaks` = jumlah `nilai_maks` **seluruh** komponen (baris 173). Nilai parsial jadi
  terlihat jauh lebih rendah daripada seharusnya, dan angka itulah yang dibaca DPNA
  lewat `rekap_nilai_pertemuan_blok.nilai_akhir`.

Saat ini kelengkapan masih tertolong karena `PerhitunganDpnaBlok` memeriksa
`$komponenLengkap` terpisah, jadi nilai parsial tidak lolos ke DPNA. Tapi baris rekap yang
salah tetap tersimpan dan tetap tampil di layar penilaian.

**Usul perbaikan.** Di dalam loop peserta, hitung jumlah komponen yang benar-benar terisi.
Bila nol, hapus baris rekapnya (`RekapNilaiPertemuanBlok::where(...)->delete()`), konsisten
dengan cara `nilai_pertemuan_blok` diperlakukan. Bila terisi sebagian, tulis rekap tapi
simpan juga penanda kelengkapannya, atau jangan tulis `nilai_akhir` sebelum lengkap —
pilih satu dan tulis alasannya di `task/task_5.md`.

---

## 4. [SALAH] `aturan_kegiatan_blok.perlu_logbook` bisa diatur tapi tidak pernah dibaca

Ada **dua** kolom `perlu_logbook` di dua tabel berbeda:

- `aturan_kegiatan_blok.perlu_logbook` — dari Task 1, punya switch di form Blok
  (`resources/views/pages/blok/add_edit.blade.php:1822`) dan ikut disimpan
  (baris 1042, 1452);
- `jenis_kegiatan.perlu_logbook` — ditambahkan
  `database/migrations/2026_08_23_000001_create_logbook_pertemuan_blok_table.php:12`.

Seluruh penentu aktif/tidaknya logbook membaca yang **kedua**:
`app/Support/AksesPertemuanBlok.php:55`,
`resources/views/components/blok-operasional/logbook.blade.php:28,55`,
`resources/views/pages/pertemuan-saya/index.blade.php:453`,
`resources/views/pages/materi-saya/index.blade.php:220`.

Jadi switch "Logbook" di form Blok tidak berpengaruh apa pun. Pengelola akan menyalakannya
lalu bingung kenapa logbook tidak muncul.

**Usul perbaikan.** Pilih satu lapis:

- kalau logbook adalah sifat jenis kegiatan (sesuai implementasi sekarang), hapus switch
  di form Blok, hapus kolom `aturan_kegiatan_blok.perlu_logbook` lewat migrasi, dan
  bersihkan `casts()` di `app/Models/AturanKegiatanBlok.php:23`;
- kalau logbook harus bisa berbeda antar blok, ubah semua pembacaan ke lapis
  `aturan_kegiatan_blok` dengan `jenis_kegiatan` sebagai default — pola yang sama dengan
  `durasi_menit` dan `tanggal_rencana`.

---

## 5. [SALAH] `dpna-blok:` memberi akses kelola ke SEMUA blok

`app/Models/Blok.php:53` dan `:72`

```php
if ($user?->can('blok-operasional:') || $user?->can('dpna-blok:')) {
    return $query;   // lolos tanpa filter koordinator
}
```

`dapatDikelolaOleh()` dipakai sebagai satu-satunya penjaga di
`resources/views/pages/blok-operasional/detail.blade.php:23` dan di seluruh tab
operasional. Artinya peran yang hanya diberi izin DPNA otomatis boleh mengelola
operasional blok mana pun — bukan hanya blok yang dikoordinasinya.

Sekarang belum berbahaya karena `dpna-blok:` cuma diberikan ke `admin` dan `pengelola`
(`2026_08_24_000002_add_dpna_blok.php:84`), dan `RouteMiddleware` masih meminta
`blok-operasional:` untuk route operasional. Tapi izinnya jadi tidak bisa dipisah, dan
satu perubahan role bisa membuka akses yang tidak dimaksud.

**Usul perbaikan.** Pisahkan cakupan: `dapatDikelolaOleh()` cukup memeriksa
`blok-operasional:` dan peran koordinator. Untuk DPNA, buat scope sendiri
(`dapatDilihatDpnaOleh()`) yang memeriksa `dpna-blok:`. `RouteMiddleware::bolehMengelolaDpna()`
memakai scope baru itu.

---

## 6. [SALAH] Rekap DPNA dihitung ulang penuh setiap render Livewire

`resources/views/pages/dpna-blok/detail.blade.php:133`

```blade
@php($data = $this->data())
```

`data()` memanggil `PerhitunganDpnaBlok::rekap()`, yang menjalankan ±6 query agregat plus
loop PHP atas seluruh peserta. Karena dipanggil dari body Blade, ia jalan ulang pada
**setiap** render — termasuk saat centang `wire:model.live="kehadiranAktif"` dan
`kegiatan.*.aktif` ditekan, dan saat baris peserta diklik (`pilihPeserta`). Untuk blok
dengan ratusan peserta dan ratusan pertemuan ini berat dan tidak perlu.

**Usul perbaikan.** Pindahkan `data()` ke `render()`/`with()` dan cache hasilnya per
request dalam properti privat (pola `pertemuan()` di `nilai-pertemuan.blade.php:83` sudah
melakukan ini). Checkbox bobot tidak perlu `.live` — cukup dievaluasi saat submit, atau
kalau tetap `.live`, jangan ikut memicu perhitungan matriks.

---

## 7. [BERSIH] `deleteMapping()` pada tab Pertemuan adalah kode mati yang berbahaya

`resources/views/components/blok-operasional/pertemuan.blade.php:498-509`

Method ini menghapus (soft delete) **semua** pertemuan satu `materi_rinci_blok` di seluruh
kelompok. Tidak ada satu pun `wire:click` yang memanggilnya di file itu, jadi sekarang ia
kode mati. Kalau nanti dipasangi tombol tanpa dibaca ulang, satu klik menghapus jadwal
seluruh kelompok — padahal seluruh UI di sekitarnya bekerja per kelompok. Method ini juga
tidak memeriksa `AksesPertemuanBlok` dan tidak memeriksa apakah pertemuan itu sudah punya
presensi/nilai.

**Usul perbaikan.** Hapus method-nya. Kalau memang dibutuhkan, tulis ulang dengan cakupan
satu kelompok, pemeriksaan izin, konfirmasi, dan penolakan bila sudah ada presensi/nilai
atau jurnalnya sudah divalidasi.

---

## 8. [BERSIH] Dokumentasi masih menyebut lapis `komponen_penilaian_kegiatan` yang sudah dihapus

`database/migrations/2026_08_20_000003_move_komponen_penilaian_kegiatan_to_komponen_penilaian.php`
membuang tabel `komponen_penilaian_kegiatan` dan memindahkan `jenis_kegiatan_id` +
`urutan` ke `komponen_penilaian`. Jadi lapisnya sekarang **dua**, bukan tiga: master
komponen sudah melekat ke satu jenis kegiatan, lalu disalin ke `komponen_penilaian_blok`.

Yang masih menyebut struktur lama:

- `task/task_5.md:21-31, 99-101, 114-121` (tabel tiga lapis, pola soft delete);
- `task/task_5.md:146-162` (menu `komponen-penilaian:` — sudah dihapus
  `2026_08_20_000002_remove_komponen_penilaian_menu.php`);
- `task/task_5.md:168-169` (halaman `pages/komponen-penilaian/*` — sudah tidak ada);
- `task/task_5.md:210-224` (checklist masih menandai menu itu selesai, dan masih
  menandai "Rekap nilai blok dan transkrip" belum padahal DPNA sudah ada);
- `AGENT.md:336-346`;
- `CLAUDE.md` bagian "Assessment rubric" dan "Three deliberately different soft-delete
  choices".

**Usul perbaikan.** Rapikan ketiganya sekaligus. Bagian DPNA sudah dikerjakan sebagian:
`CLAUDE.md` kini punya bagian *DPNA (nilai akhir blok)* yang menjelaskan dua tahap
agregasi, letak bobot di dua tabel, sifat `nilaiAkhir()` yang null begitu satu sumber tidak
lengkap, dan aturan `withCount('materi_rinci_blok')`. Yang belum: `AGENT.md` belum punya
padanannya, dan `task/task_5.md` masih menggambarkan tiga lapis komponen penilaian plus
menu `komponen-penilaian` yang sudah dihapus.

---

## 9. [BERSIH] Sisa Rombel di halaman Operasional Blok

`resources/views/pages/blok-operasional/detail.blade.php`

Tab Rombel dan kartu Rombel sudah dikomentari (baris 125-130 dan 172-178), tetapi:

- `withCount('kelas as rombel_count')` (baris 49) masih ikut di query ringkasan;
- daftar "Urutan Pengerjaan" (baris 229-230) masih menyuruh pengelola mengisi Rombel yang
  tabnya tidak ada.

**Usul perbaikan.** Hapus `rombel_count` dari `withCount`, hapus butir Rombel dari daftar
urutan pengerjaan, dan hapus blok komentar besarnya. Tabel `kelas` sendiri dibiarkan
sesuai keputusan yang ada.

---

## 10. [GAP] DPNA belum punya nilai huruf, tidak bisa dicetak, dan tidak pernah dibekukan

Halaman `resources/views/pages/dpna-blok/detail.blade.php` hanya menampilkan angka 0-100
yang dihitung ulang setiap kali dibuka. Untuk sebuah DPNA (Daftar Peserta dan Nilai Akhir)
yang dipakai sebagai dokumen akademik, tiga hal berikut belum ada:

1. **Nilai huruf / mutu.** Tidak ada konversi A/B/C/D/E dan tidak ada tabel rentangnya.
2. **Cetak / export.** Tidak ada tombol PDF maupun XLS. Project sudah punya
   `app/Exports/PowerGridExportToXLS.php` yang bisa dijadikan acuan.
3. **Pembekuan (finalisasi).** Nilai akhir tidak pernah disimpan. Begitu bobot atau satu
   nilai pertemuan diubah, DPNA lama tidak bisa direproduksi. `task/task_5.md:45-53` sudah
   menyadari ini dan menyarankan kolom `difinalisasi_pada` di tabel terpisah — belum
   dikerjakan.

Selain itu `dpna-blok/detail.blade.php:168` menulis "Rata-rata nilai
{{ $aturan->pertemuan_blok_count }} pertemuan", padahal `pertemuan_blok_count` adalah
jumlah pertemuan **seluruh kelompok** pada kegiatan itu, sedangkan rata-rata per mahasiswa
hanya atas pertemuan kelompoknya sendiri. Angka yang ditampilkan lebih besar dari yang
sebenarnya dipakai.

---

## 11. [GAP] Presensi tidak bisa diisi untuk kegiatan tanpa kelompok

`resources/views/components/blok-operasional/presensi-pertemuan.blade.php:85-93` dan
`nilai-pertemuan.blade.php:106-114` selalu mengambil peserta sesi dari
`anggota_kelompok_blok`. `AGENT.md:296-300` masih menjanjikan perilaku lain untuk
`perlu_kelompok = false` (peserta sesi = seluruh `peserta_blok` aktif), tetapi form Blok
memaksa `perlu_kelompok = true` (`blok/add_edit.blade.php:226`).

Ini konsisten dengan keputusan Task 4, jadi bukan bug — tapi `AGENT.md` perlu diperbaiki
supaya tidak menyesatkan, dan kalau nanti Kuliah Pakar ingin satu sesi untuk seluruh
angkatan, keputusan ini yang harus dibuka lagi.

---

## 12. [GAP] Belum ada isian nilai untuk kegiatan berbasis CBT

`jenis_kegiatan.pakai_cbt` (`2026_08_23_000002_add_pakai_cbt_to_jenis_kegiatan_table.php`)
hanya dipakai untuk memaksa satu komponen bernama "Nilai" 1-100 di form Jenis Kegiatan
(`pages/jenis-kegiatan/add_edit.blade.php:72-99`) dan sebagai kolom di
`app/Livewire/TableJenisKegiatan.php:77`. Tidak ada jalur import nilai CBT maupun tanda
apa pun di layar penilaian bahwa nilai kegiatan ini seharusnya datang dari CBT, bukan
diketik dosen.

**Usul perbaikan.** Minimal: tampilkan penanda di `nilai-pertemuan.blade.php` untuk
kegiatan `pakai_cbt` dan sediakan import nilai per pertemuan (XLS), memakai pola
`app/Imports` yang sudah ada.

---

## 13. [SALAH] Halaman DPNA memakai relasi tanpa nullsafe, padahal semua master pakai soft delete

`resources/views/livewire/table-dpna-blok.blade.php:34`

```blade
<td>{{ $blok->prodi->nama }}<div class="text-muted small">{{ ucfirst($blok->semester->nama) }} ...
```

`resources/views/pages/dpna-blok/detail.blade.php:146-147, 168, 186, 192, 210`

```blade
<div>{{ $blok->prodi->nama }}</div>
<div>{{ ucfirst($blok->semester->nama) }} {{ $blok->semester->tahun }}</div>
...
{{ $aturan->jenis_kegiatan->nama }}
```

`Prodi`, `Semester`, dan `JenisKegiatan` semuanya memakai soft delete, dan soft delete
tidak ditahan foreign key. Begitu salah satunya dihapus lembut sementara masih dipakai
blok yang hidup, relasinya `null` dan kedua halaman DPNA melempar
`Attempt to read property "nama" on null`.

Bukan hipotesis: di database ini blok `#3 BMD-2026` **sudah** punya `prodi_id` yang
relasinya null (prodi hasil seed contoh sudah dihapus). Blok itu kebetulan ikut
soft-deleted sehingga belum tampil di daftar, jadi masalahnya baru tersembunyi.

Seluruh kode operasional lain di project ini sudah konsisten memakai `?->`
(`monitoring.blade.php:440`, `pertemuan-saya/index.blade.php`, dan seterusnya). Dua file
DPNA ini yang keluar dari pola.

**Usul perbaikan.** Ganti ke `?->` dengan fallback `-`, sama seperti
`blok-operasional/detail.blade.php:97`. Kalau memang blok tanpa prodi/semester valid
dianggap tidak boleh ada, tambahkan pemeriksaan di form Blok, bukan mengandalkan view
tidak akan pernah menemuinya.

---

## 14. [SALAH] Normalisasi nilai mengabaikan `nilai_min`, jadi skalanya bukan 0-100

`app/Models/RekapNilaiPertemuanBlok.php:24-27`

```php
return $nilaiMaks > 0 ? ($total / $nilaiMaks) * 100 : 0;
```

Pembaginya hanya `nilai_maks`, `nilai_min` tidak ikut dihitung. Untuk rubrik yang
`nilai_min`-nya di atas 0, nilai terendah yang mungkin bukan 0.

Contoh nyata dari Blok 3 setelah diisi. Rubrik Tutorial PBL punya 4 komponen berskala
1-5, jadi total minimum 4 dan maksimum 20:

| Skor mentah | Hasil `hitungNilaiAkhir` | Seharusnya (0-100 penuh) |
| --- | --- | --- |
| 4 (semua komponen minimum) | 20,00 | 0,00 |
| 12 | 60,00 | 50,00 |
| 20 (semua maksimum) | 100,00 | 100,00 |

Mahasiswa yang mendapat nilai serendah mungkin di semua komponen tetap tercatat 20 pada
DPNA. Untuk komponen MCQ yang `nilai_min`-nya 1 dari 100, pergeserannya kecil dan tidak
terasa, tetapi untuk rubrik 1-5 pergeserannya 20 poin penuh dan itu masuk ke nilai akhir
lewat bobot 25%.

Efek samping lain yang terlihat di data: karena rubrik 1-5 hanya bernilai bulat, nilai
Tutorial per pertemuan selalu kelipatan 5, dan rata-rata dua pertemuan selalu kelipatan
2,5. Granularitasnya kasar dibanding Praktikum dan MCQ yang berskala 0-100.

**Usul perbaikan.** Putuskan dulu mana yang dimaksud, lalu tulis keputusannya di
`task/task_5.md`:

- kalau `nilai_min` adalah batas bawah skala penilaian, normalisasi seharusnya
  `($total - $minTotal) / ($maksTotal - $minTotal) * 100`. Perlu diperhatikan bahwa
  mengubah rumus ini menggeser seluruh `rekap_nilai_pertemuan_blok.nilai_akhir` yang sudah
  tersimpan, jadi butuh backfill;
- kalau `nilai_min` hanya batas validasi input (mencegah dosen mengetik 0 karena lupa,
  bukan menyatakan skala), rumus sekarang benar dan yang perlu diperbaiki adalah
  rubriknya: ubah batas Tutorial menjadi 0-5 supaya skalanya benar-benar penuh.

Pilihan kedua lebih murah dan tidak mengubah data lampau.

---

## Lampiran: skrip bantu

Dua skrip sekali pakai ditaruh di root project untuk pekerjaan pengisian ini. Keduanya
boleh dihapus setelah tidak dipakai.

- `cek_blok.php` — read-only. `php cek_blok.php 3` mencetak seluruh isi satu blok:
  aturan kegiatan, rubrik, materi, kelompok, peserta, pertemuan, dosen pengampu, dan
  jumlah baris presensi/jurnal/nilai/rekap.
- `isi_blok.php` — pengisian pelaksanaan, idempoten. `php isi_blok.php 3` hanya
  **mencetak rencana** tanpa menulis; `php isi_blok.php 3 --apply` yang menulis.
  Tambahkan `--tanpa-validasi` bila jurnal tidak ingin langsung divalidasi (validasi
  mengunci presensi dan jurnal, tapi tidak mengunci nilai).
