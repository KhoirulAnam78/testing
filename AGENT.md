# SIAKAD Sistem Blok Kedokteran

Project ini adalah aplikasi Laravel untuk Sistem Informasi Akademik (SIAKAD) dengan model pembelajaran sistem blok, khususnya untuk kebutuhan akademik kedokteran.

Pengembangan dilakukan bertahap berdasarkan dokumen pada folder `task`. Agent atau developer wajib membaca `task/readme_first.md`, lalu membaca task yang sedang dikerjakan secara berurutan sebelum menulis kode.

## Stack Utama

- Laravel 13
- PHP 8.3
- Livewire 4
- Laravel Breeze
- Spatie Laravel Permission
- Livewire PowerGrid
- Bootstrap admin template/Velzon assets
- Remix Icon untuk icon UI

## Cara Kerja Task

- Folder `task` adalah sumber arahan analisis dan implementasi.
- Kerjakan task secara sekuensial: `task_1`, lalu `task_2`, dan seterusnya.
- Jangan mengerjakan fitur di luar batasan task aktif kecuali diminta eksplisit.
- Jika task masih tahap analisis, jangan langsung implementasi migration/model/view.
- Setiap keputusan desain domain yang disepakati perlu ditulis kembali ke dokumen task atau README agar agent berikutnya punya konteks yang sama.

## Pattern Coding Project

Bagian ini adalah pegangan untuk agent/developer agar gaya coding tetap konsisten dengan project.

### Routing

- Route utama berada di `routes/web.php`.
- Route halaman authenticated dibungkus dalam `Route::middleware(['auth'])->group(...)`.
- Halaman Livewire Volt/anonymous component didaftarkan dengan `Route::livewire(...)`.
- Nama route memakai pola domain sederhana, contoh:
  - `menu.index`
  - `menu.add_edit`
  - `roles.index`
  - `users.add_edit`
- Form tambah/edit memakai parameter `{id}` dengan nilai `add` untuk tambah data.
- Link antar halaman memakai `wire:navigate` jika berpindah antar halaman Livewire.

### Struktur Halaman

- Halaman index diletakkan di `resources/views/pages/{domain}/index.blade.php`.
- Halaman tambah/edit diletakkan di `resources/views/pages/{domain}/add_edit.blade.php`.
- Halaman form menggunakan anonymous Livewire component langsung di file Blade:

```php
new #[Layout('layouts.app')] class extends Component {
    // state, mount, validation, save
};
```

- Layout utama memakai `layouts.app`.
- Gunakan struktur UI Bootstrap:
  - `page-title-box` untuk judul halaman
  - `row`
  - `col-*`
  - `card`
  - `card-header`
  - `card-body`
- Tombol simpan pada form mengikuti pola floating button `.fab-save` di bagian bawah halaman.

### Table/List

- Tabel data memakai Livewire PowerGrid, bukan tabel manual, untuk halaman CRUD utama.
- Komponen tabel disimpan di `app/Livewire/Table{Domain}.php`.
- Class tabel dibuat `final class`.
- Properti umum:

```php
public string $tableName = 'tableDomainTable';
public int $rowNumber = 0;
```

- `setUp()` menampilkan search input, pagination 10 data, dan record count.
- `datasource()` mengembalikan Eloquent `Builder` dan melakukan eager load relasi yang diperlukan.
- Nomor urut dibuat manual melalui field `no`, mengikuti halaman aktif dan `perPage`.
- Action tabel memakai PowerGrid `Button`.
- Tombol edit:
  - label: `Kelola`
  - class: `btn btn-info btn-sm mb-2`
  - icon: `ri-file-edit-line`
  - route membawa ID terenkripsi dengan `Crypt::encrypt($row->id)`
  - attributes memakai `wire:navigate`
- Tombol hapus:
  - label: `Hapus`
  - class: `btn btn-danger btn-sm mb-2`
  - icon: `ri-delete-bin-line`
  - memakai `confirm(...)`
  - memanggil method delete di komponen tabel

### Form Tambah/Edit

- Satu file `add_edit.blade.php` menangani tambah dan edit.
- State edit disimpan pada `$edit_id`.
- Pada `mount($id)`, jika `$id != 'add'`, ID harus didekripsi dengan `Crypt::decrypt($id)`.
- Jika decrypt gagal, abort `404`.
- Validasi diletakkan di method `save()` atau method domain terkait.
- Gunakan pesan validasi Bahasa Indonesia.
- Operasi create/update yang menyentuh lebih dari satu tabel dibungkus dengan `DB::transaction(...)`.
- Setelah berhasil simpan:
  - gunakan `session()->flash('success', '...')`
  - redirect ke halaman index dengan `return $this->redirect(route('domain.index'), navigate: true);`
- Untuk checkbox boolean, pola yang dipakai adalah `form-check form-switch`.

### Delete

- Delete dari tabel PowerGrid menerima ID terenkripsi.
- Method delete wajib decrypt ID terlebih dahulu.
- Jika decrypt gagal, abort `404`.
- Setelah delete berhasil, kirim notifikasi Livewire:

```php
$this->dispatch('notify', message: [
    'status' => 'success',
    'message' => 'Data berhasil dihapus !',
]);
```

- Untuk master data akademik yang dirancang di task, gunakan soft delete sesuai dokumen task.

### Model

- Model berada di `app/Models`.
- Jika nama tabel tidak mengikuti pluralisasi Laravel, definisikan `$table`.
- Jika primary key tidak standar, definisikan `$primaryKey`.
- Untuk model domain yang banyak field, gunakan:

```php
protected $guarded = ['id'];
```

- Relasi Eloquent harus eksplisit dan memakai return type relasi jika memungkinkan:
  - `BelongsTo`
  - `HasMany`
  - `BelongsToMany`
- Nama relasi yang sudah ada boleh memakai gaya snake_case, contoh `parent_menu`, agar konsisten dengan project.

### Permission dan Menu

- Hak akses memakai Spatie Permission.
- Menu dikelola melalui tabel `menus`.
- Permission utama menu ditandai dengan field `main_permission = 1` pada tabel `permissions`.
- Menu parent memiliki permission utama.
- Menu child dapat memiliki permission tambahan.
- Navbar membaca menu dari database dan menampilkan item berdasarkan `@can(...)`.
- Saat membuat fitur baru yang memiliki halaman:
  - tambahkan route
  - tambahkan menu melalui fitur Kelola Menu
  - buat permission utama
  - gunakan permission pada navigasi/akses jika dibutuhkan
- Query navbar memakai inner join ke `permissions` dengan `main_permission = 1`, jadi menu parent tanpa baris permission tidak akan pernah tampil dan menu child tanpa permission akan error saat `@can(...)`. Buat menu dan permission selalu berpasangan.
- Bila menu didaftarkan lewat migration, berikan izin dengan `Role::findOrCreate(...)`, bukan `Role::whereIn(...)->get()`: pada `migrate --seed` yang bersih migration berjalan sebelum seeder role sehingga pemberian izin gagal tanpa pesan. Contoh: `database/migrations/2026_08_18_000007_register_portal_dosen_menu.php`.
- Menu `Portal Saya` menampung halaman yang di-scope ke user yang login (`pertemuan-saya:` untuk dosen, `materi-saya:` untuk mahasiswa), bukan CRUD master data.

### Naming Domain Akademik

- Nama tabel dan field domain akademik menggunakan Bahasa Indonesia sesuai dokumen task.
- Field bawaan Laravel tetap `created_at`, `updated_at`, dan `deleted_at`.
- Tabel autentikasi tetap `users`.
- Role dasar:
  - `admin`
  - `pengelola`
  - `dosen`
  - `mahasiswa`
- Model akademik boleh memakai nama Bahasa Indonesia, contoh:
  - `Prodi`
  - `Dosen`
  - `Mahasiswa`
  - `Semester`
  - `MataKuliah`
  - `JenisKegiatan`
  - `Blok`

### Database dan Migration

- Gunakan migration Laravel dengan foreign key eksplisit.
- Gunakan `restrictOnDelete()` untuk master yang tidak boleh dihapus saat masih dipakai.
- Gunakan `nullOnDelete()` untuk relasi opsional.
- Gunakan `cascadeOnDelete()` untuk tabel turunan/pivot yang mengikuti induk saat permanent delete.
- Master data utama menggunakan soft delete.
- Pivot murni tidak wajib soft delete kecuali task menyebutkan sebaliknya.
- Field pencarian utama diberi `index()` atau `unique()`.
- Untuk enum status, ikuti nilai yang tertulis di dokumen task.

### Performa dan Keamanan

Saat membuat atau mengubah fitur, agent/developer wajib mempertimbangkan performa dan keamanan sejak desain awal, bukan setelah fitur selesai.

Prinsip performa:

- Hindari query N+1. Gunakan eager loading (`with`) atau query join/select eksplisit sesuai kebutuhan tampilan.
- Ambil hanya kolom yang dibutuhkan untuk list, dropdown, export, dan dashboard. Hindari `select *` pada query besar jika tidak perlu.
- Untuk tabel PowerGrid, pastikan kolom yang sering dipakai filter/sort/search memiliki index yang sesuai di migration.
- Jangan melakukan operasi berat berulang di closure row tabel, seperti query database, decrypt/encrypt massal tanpa kebutuhan, atau formatting kompleks yang bisa dipindah ke query/accessor ringan.
- Untuk dropdown besar, gunakan query terfilter, pagination/search async, atau minimal `get(['id', 'nama'])` sesuai field yang dibutuhkan.
- Untuk export/import data besar, pertimbangkan chunking, queue, validasi bertahap, dan batas ukuran file agar request tidak timeout.
- Hindari memuat relasi dalam yang tidak ditampilkan. Relasi nested hanya dipakai jika benar-benar diperlukan halaman.
- Gunakan cache secara selektif untuk data referensi yang jarang berubah, seperti menu, permission, prodi, atau semester aktif, dengan mekanisme invalidasi yang jelas.

Prinsip keamanan:

- Semua route/halaman fitur baru harus memiliki kontrol akses yang sesuai, tidak cukup hanya menyembunyikan menu dengan `@can`.
- ID yang dikirim melalui URL atau action sensitif tetap dienkripsi sesuai pola project, lalu wajib didekripsi dan divalidasi server-side.
- Validasi input wajib dilakukan di server dengan rule Laravel. Jangan bergantung pada atribut HTML atau state UI saja.
- Gunakan `Rule::unique(...)->ignore(...)` untuk update agar validasi unique tetap aman dan tepat pada primary key non-standar.
- Gunakan mass assignment secara hati-hati. Dengan `$guarded`, pastikan payload dibuat eksplisit dan tidak langsung menerima seluruh request/state.
- Operasi tulis multi-tabel wajib memakai `DB::transaction(...)`.
- Jangan menyimpan password, token, API key, atau data rahasia di repository, log, flash message, atau response.
- Untuk file upload/import, validasi mime, ukuran, ekstensi, dan isi data. Simpan file hanya di disk/path yang sesuai, bukan public path kecuali memang harus diakses publik.
- Data yang ditampilkan sebagai HTML harus aman dari XSS. Gunakan escaping Blade default, dan hanya pakai HTML mentah untuk markup yang dikontrol aplikasi.
- Delete, finalisasi, batal, dan aksi destruktif lain wajib memakai konfirmasi serta tetap memvalidasi permission dan kondisi domain di server.

### UI dan Bahasa

- UI memakai Bootstrap dan class template yang sudah ada.
- Icon memakai Remix Icon, contoh `ri-add-box-fill`, `ri-file-edit-line`, `ri-delete-bin-line`.
- Teks UI utama menggunakan Bahasa Indonesia.
- Label umum yang sudah dipakai:
  - `Tambah`
  - `Kelola`
  - `Hapus`
  - `SIMPAN`
  - `Aktif`
  - `Nonaktif`
- Pesan sukses menggunakan pola:
  - `Berhasil menambah data`
  - `Berhasil mengubah data`
  - `Data berhasil dihapus !`

### Tema UI SIAKAD Sistem Blok

Tema UI diarahkan sebagai aplikasi akademik-operasional untuk pengelola, dosen, dan mahasiswa kedokteran. Tampilan harus terasa rapi, tenang, profesional, dan mudah dipindai untuk pekerjaan berulang.

Prinsip tema:

- Prioritaskan keterbacaan, kepadatan informasi, dan kecepatan kerja.
- Hindari gaya landing page, hero besar, ilustrasi dekoratif, card berlebihan, atau warna terlalu ramai.
- Gunakan layout admin yang sudah ada: navbar, sidebar/menu, breadcrumb, card, table, form, badge, dan tab.
- Halaman modul akademik sebaiknya langsung menampilkan data/aksi utama, bukan teks penjelasan panjang.
- Gunakan warna status secara konsisten:
  - hijau untuk `aktif`, `hadir`, `tervalidasi`, `final`, atau berhasil
  - kuning/oranye untuk `draft`, `diajukan`, `berlangsung`, atau menunggu validasi
  - merah untuk `batal`, `alpa`, `revisi`, `nonaktif`, atau gagal
  - biru untuk informasi, kelola, jadwal, atau aksi netral
  - abu-abu untuk arsip, selesai, atau data tidak aktif
- Gunakan badge kecil untuk status akademik agar tabel mudah dipindai.
- Untuk halaman kompleks seperti manajemen blok, gunakan tab atau segmented navigation:
  - Ringkasan
  - Peserta
  - Kelompok
  - Materi
  - Jadwal
  - Dosen
  - Modul
  - Presensi
  - Nilai
- Untuk detail blok, tampilkan ringkasan konteks di bagian atas:
  - nama blok
  - semester
  - prodi
  - mata kuliah
  - status
  - periode tanggal
- Untuk form panjang, pecah menjadi beberapa card atau tab berdasarkan konteks, bukan satu form panjang tanpa struktur.
- Untuk tabel operasional, sediakan filter yang sering dipakai:
  - semester
  - prodi
  - blok
  - jenis kegiatan
  - status
  - tanggal
- Aksi primer cukup satu per area, misalnya `Tambah`, `Simpan`, `Finalisasi`, atau `Validasi`.
- Aksi berisiko seperti hapus, batal, atau buka finalisasi harus memakai konfirmasi.

Nuansa visual yang disarankan:

- Dasar warna tetap mengikuti Bootstrap/template Velzon.
- Warna dominan sebaiknya netral terang dengan aksen biru/hijau medis-akademik.
- Hindari dominasi ungu, gradient mencolok, warna gelap penuh, atau dekorasi visual yang tidak membantu pekerjaan.
- Gunakan icon Remix secara konsisten untuk aksi:
  - `ri-add-box-fill` untuk tambah
  - `ri-file-edit-line` untuk kelola/edit
  - `ri-delete-bin-line` untuk hapus
  - `ri-save-line` untuk simpan
  - `ri-check-line` untuk validasi/finalisasi
  - `ri-calendar-line` untuk jadwal
  - `ri-group-line` untuk peserta/kelompok
  - `ri-file-list-3-line` untuk logbook atau monitoring
  - `ri-graduation-cap-line` untuk akademik/nilai

## Catatan Analisis Blok

Untuk kegiatan blok yang tidak memerlukan kelompok:

- `aturan_kegiatan_blok.perlu_kelompok = false`
- `pertemuan_blok.kelompok_blok_id = NULL`
- peserta sesi dianggap seluruh `peserta_blok` aktif pada blok tersebut

Untuk kegiatan blok yang memerlukan kelompok:

- `aturan_kegiatan_blok.perlu_kelompok = true`
- `pertemuan_blok.kelompok_blok_id` wajib diisi
- peserta sesi diambil dari `anggota_kelompok_blok`

Aturan ini wajib diterapkan pada validasi aplikasi saat Task 2 diimplementasikan.

### Repositori Modul dan Video

Bahan ajar disimpan sebagai **tautan** pada tabel `lampiran_materi_blok`, bukan file yang diunggah ke server. Project ini tidak memiliki pola penyimpanan file permanen, dan modul praktikum umumnya sudah berada di Google Drive.

- `materi_rinci_blok_id` selalu terisi. `pertemuan_blok_id` NULL berarti tautan default yang berlaku untuk semua kelompok pada materi tersebut; terisi berarti tautan tambahan milik satu pertemuan (satu kelompok). Pola yang sama dengan `materi_rinci_blok.tanggal_rencana`.
- Lampiran melekat ke materi, tidak ke dosen pengampu: `dosen_pertemuan_blok` tanpa soft delete dan disinkronkan ulang setiap pemetaan disimpan, sehingga tautan akan hilang begitu dosen diganti.
- Tautan default hanya boleh diubah pengelola. Dosen pengampu mengelola tautan pertemuannya sendiri; lampiran default tampil untuk mereka tapi read-only.
- Seluruh aturan akses ada di `app/Support/AksesPertemuanBlok.php`, dan setiap jalur tulis memeriksanya ulang karena argumen aksi Livewire berasal dari klien.
- Tabel ini sengaja tanpa unique business key sehingga `create()`/`delete()` biasa aman; jangan menambahkan unique key lalu memakai `updateOrCreate`.

### Presensi dan Jurnal Pelaksanaan

Presensi dan monitoring pelaksanaan menggantung ke `pertemuan_blok`, bukan ke kelas.

- `presensi_pertemuan_blok`: satu baris per peserta per pertemuan, status `hadir|sakit|izin|alpa`. Dikunci ke `peserta_blok_id` supaya mustahil mencatat mahasiswa yang bukan peserta blok itu.
- `monitoring_pertemuan_blok`: jurnal pelaksanaan, satu baris per pertemuan. Dipisah dari `pertemuan_blok` karena kolom `catatan` di sana milik perencana dan barisnya ditulis ulang oleh `savePertemuan()`.
- **Kedua tabel tanpa soft delete.** Keduanya punya unique business key dan ditulis lewat `updateOrCreate`; baris soft-deleted akan tetap menempati unique index. Jangan menambahkan `softDeletes()` ke tabel yang dipakai `updateOrCreate` atas kunci bisnis.
- Peserta sesi diambil dari `anggota_kelompok_blok` pada kelompok pertemuan tersebut. Saat menyimpan, iterasi daftar anggota dari database, **bukan** kunci array state Livewire — kunci array bisa disusupkan dari klien.
- Halaman dosen `Pertemuan Saya` dan tab pengelola `Monitoring` menampilkan jurnal dan presensi dalam satu form `Monitoring`; satu aksi simpan memicu kedua komponen bersama.
- `divalidasi_pada` yang terisi mengunci presensi dan jurnal untuk semua peran. Hanya pengelola/admin yang boleh membuka kembali, supaya koreksi meninggalkan jejak.
- Menyimpan jurnal juga menaikkan `pertemuan_blok.status` lewat `MonitoringPertemuanBlok::STATUS_PERTEMUAN`, dalam satu `DB::transaction` bersama penulisan jurnalnya.
- Hormati `aturan_kegiatan_blok.perlu_presensi`; kolom itu sudah ada sejak Task 1 dan bisa diatur di form Blok.
- Aturan akses ada di `app/Support/AksesPertemuanBlok.php` (menggantikan `AksesModulBlok`), dan setiap jalur tulis memeriksanya ulang.

### Komponen Penilaian dan Input Nilai

Penilaian memakai tiga lapis, bukan satu tabel. Rincian ada di `task/task_5.md`.

- `komponen_penilaian` master global; `komponen_penilaian_kegiatan` standar per jenis kegiatan (template); `komponen_penilaian_blok` rubrik milik satu `aturan_kegiatan_blok`.
- `nilai_pertemuan_blok` menggantung ke `komponen_penilaian_blok`, **bukan** ke master, supaya `nilai_min`/`nilai_maks` terkunci pada blok itu. Mengubah standar tidak menggeser tafsir nilai blok lampau.
- Skala memakai batas per komponen (`nilai_min`/`nilai_maks`), **bukan bobot persen**. Skor mentah dijumlah apa adanya; jangan menambahkan perhitungan pembobotan.
- **Nilai sengaja tidak dikunci `divalidasi_pada`.** Presensi dan jurnal terkunci saat pertemuan divalidasi, penilaian tidak, karena dosen sering baru menilai setelah validasi dan koreksi nilai adalah pekerjaan normal. Aturannya berdiri sendiri di `AksesPertemuanBlok::bolehIsiNilai()` yang tidak memanggil `terkunci()`. Jangan menyatukan keduanya.
- Tiga pola soft delete yang berbeda dan disengaja: `komponen_penilaian_kegiatan` tanpa soft delete (kunci bisnis + `updateOrCreate`), `komponen_penilaian_blok` dengan soft delete supaya nilai tidak ikut ter-cascade sehingga wajib disimpan lewat `withTrashed()->firstOrNew()` + `restore()`, dan `nilai_pertemuan_blok` tanpa soft delete karena ditulis `updateOrCreate` atas kunci bisnis.
- Nilai yang dikosongkan dosen **dihapus permanen**, jadi "ada baris" berarti "sudah dinilai" dan badge kelengkapan cukup memakai `withCount`.
- `nilai_pertemuan_blok.komponen_penilaian_blok_id` memakai cascade agar hapus permanen blok dan `migrate:fresh` tetap jalan. Pengaman kehilangan nilai ada di `lolosPengecekanNilaiTersimpan()` pada form Blok, bukan di foreign key.
- `aturan_kegiatan_blok.perlu_penilaian` mengatur munculnya tombol dan tab Nilai, sejajar `perlu_presensi`. Penanda dan rubrik terpisah, jadi UI harus menangani penanda nyala dengan rubrik kosong (ditolak saat simpan, badge `rubrik kosong`) dan penanda mati dengan nilai sudah ada (tetap ditampilkan dengan banner).
- Rubrik per blok disusun di tab Penilaian pada `resources/views/pages/blok/add_edit.blade.php`, sehingga form itu punya empat lapis state: `aturan[i].materi[j].rinci[k]` dan `aturan[i].komponen[j]`. Rubrik ikut disalin `copyFromBlok()`, berbeda dari `tanggal_rencana`.
- Batas nilai berbeda per komponen, jadi rule validasi dibangun per sel matriks dan batasnya selalu dibaca dari database, bukan dari atribut `min`/`max` di HTML.

## Rekomendasi Pengembangan

Bagian ini berisi rekomendasi teknis untuk menjaga project tetap rapi saat modul akademik mulai kompleks.

### Pisahkan Logic Form Besar

Pola anonymous Livewire component langsung di file `add_edit.blade.php` tetap boleh dipakai untuk CRUD sederhana. Namun, jika form mulai panjang atau memiliki banyak validasi lintas tabel, pindahkan logic ke class Livewire biasa di `app/Livewire/Pages/...`.

Contoh modul yang kemungkinan perlu class sendiri:

- manajemen blok
- pembagian kelompok
- jadwal pertemuan blok
- plotting dosen
- presensi dan monitoring
- penilaian blok

### Kunci Akses di Route dan Halaman

Navbar sudah memakai `@can(...)`, tetapi fitur baru sebaiknya tidak hanya disembunyikan dari menu. Route atau halaman juga perlu dikunci dengan permission agar URL tidak bisa diakses langsung oleh user yang tidak berhak.

Gunakan permission Spatie sebagai standar akses modul.

### Bersihkan Comment Eksperimen

Jika pola implementasi sudah final, hapus blok kode lama yang dikomentari, terutama pada komponen tabel dan form. Comment yang dibiarkan terlalu lama dapat membuat agent/developer berikutnya bingung membedakan kode aktif dan catatan eksperimen.

### Validasi Domain Jangan Hanya di UI

Validasi akademik penting sebaiknya tidak hanya berada di form Blade/Livewire. Jika aturan dipakai ulang, buat service/helper domain agar perilaku konsisten.

Contoh validasi domain:

- mahasiswa harus satu `prodi` dengan `blok`
- mahasiswa harus menjadi `peserta_blok` sebelum masuk kelompok
- kelompok harus sesuai `jenis_kegiatan` pada `aturan_kegiatan_blok`
- pertemuan berkelompok wajib memiliki `kelompok_blok_id`
- kapasitas kelompok tidak boleh terlampaui
- `jam_selesai` harus lebih besar dari `jam_mulai`

### Gunakan Transaction Untuk Operasi Relasional

Untuk operasi akademik yang menyentuh lebih dari satu tabel, gunakan `DB::transaction(...)` sebagai default.

Contoh:

- membuat blok beserta aturan kegiatan
- membuat kelompok dan anggota kelompok
- membuat pertemuan dan plotting dosen
- menyimpan presensi dan monitoring
- menghitung atau menyimpan rekap nilai

### Review Performa Sebelum Selesai

Sebelum menyelesaikan fitur baru, lakukan review cepat terhadap query, render Livewire, dan struktur data yang dikirim ke browser.

Checklist minimum:

- Pastikan halaman index/list tidak melakukan query tambahan per baris.
- Pastikan tabel besar memakai pagination, filter yang masuk akal, dan index database untuk kolom penting.
- Pastikan komponen Livewire tidak menyimpan collection besar di public property jika tidak dibutuhkan.
- Pastikan query dashboard memakai agregasi database (`count`, `sum`, `exists`) daripada mengambil semua row ke memory.
- Jalankan `php artisan view:cache` atau validasi serupa setelah mengubah Blade kompleks jika memungkinkan.

### Review Keamanan Sebelum Selesai

Sebelum menyelesaikan fitur baru, lakukan review cepat terhadap akses, input, dan aksi sensitif.

Checklist minimum:

- Pastikan route atau halaman hanya bisa diakses user yang memiliki permission/role yang sesuai.
- Pastikan semua input divalidasi server-side dengan pesan Bahasa Indonesia.
- Pastikan ID terenkripsi dari URL/action didekripsi dengan `try/catch` dan gagal ke `abort(404)`.
- Pastikan payload create/update dibuat eksplisit, bukan mengambil seluruh state/request mentah.
- Pastikan file upload/import memiliki validasi ukuran, mime, dan ekstensi.
- Pastikan aksi hapus/finalisasi/batal memvalidasi kondisi domain sebelum menjalankan perubahan.

### Disiplin Primary Key Non-Standar

Beberapa tabel akademik memakai primary key non-standar seperti `id_prodi`, `id_dosen`, atau `id_mahasiswa`. Jika pola ini dipakai, setiap model wajib mendefinisikan `$primaryKey` dan setiap relasi wajib menuliskan foreign key serta owner key secara eksplisit.

Contoh:

```php
protected $primaryKey = 'id_dosen';

public function prodi(): BelongsTo
{
    return $this->belongsTo(Prodi::class, 'prodi_id', 'id_prodi');
}
```

### Gunakan Icon Remix Untuk Tombol Simpan

Hindari karakter emoji pada tombol simpan karena rawan rusak encoding. Gunakan Remix Icon agar konsisten dengan template.

Contoh:

```html
<i class="ri-save-line"></i> SIMPAN
```

## Perintah Umum

```bash
composer install
npm install
php artisan migrate
npm run dev
php artisan test
```

Di environment Laragon lokal, PHP dapat dijalankan dari binary Laragon jika diperlukan.
