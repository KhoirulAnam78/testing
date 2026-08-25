# Task 3 - Backlog Setelah Kelas Sistem Blok

> **SUDAH DIGANTIKAN OLEH `task_4.md`.** Basis domain di bawah ini (`kelas` sebagai induk
> operasional) sudah tidak dipakai. Task 4 memindahkan peserta, kelompok, dan pertemuan ke
> `blok`, dan menurunkan `Kelas` menjadi rombel opsional. Fondasi pertemuan dan plotting dosen
> yang dijelaskan di dokumen ini sudah dipindahkan ke `pertemuan_blok` dan
> `dosen_pertemuan_blok`. Untuk pekerjaan baru, ikuti `task_4.md`.

## Tujuan

Task 3 menjadi tempat fitur lanjutan yang tidak masuk batas Task 2. Dokumen ini boleh ditimpa ulang ketika Task 2 selesai dan kebutuhan operasional berikutnya sudah lebih jelas.

Basis domain Task 3 harus mengikuti alur baru:

```text
blok sebagai template
mata_kuliah memakai blok
kelas
peserta_kelas
kelas sistem blok
kelompok_kelas_blok
anggota_kelompok_kelas_blok
```

Jangan kembali memakai tabel operasional lama seperti `peserta_blok`, `kelompok_blok`, `anggota_kelompok_blok`, `pertemuan_blok`, atau `dosen_pertemuan_blok` kecuali ada keputusan desain baru yang eksplisit.

> **Keputusan desain baru itu sudah diambil di `task_4.md`:** tabel-tabel `*_blok` di atas
> justru menjadi struktur operasional resmi, dan tabel `*_kelas_blok` sudah dihapus.

## Fitur yang Dipindahkan dari Task 2

Fitur berikut tidak dikerjakan pada Task 2 dan disimpan untuk rancangan berikutnya:

- Penjadwalan detail pertemuan kelas sistem blok.
- Plotting dosen pada materi atau pertemuan kelas sistem blok.
- Presensi mahasiswa per pertemuan.
- Monitoring atau jurnal pelaksanaan pertemuan.
- Validasi pertemuan oleh dosen atau pengelola.
- Logbook atau refleksi mahasiswa.
- Komponen penilaian.
- Input nilai mahasiswa.
- Rekap nilai blok atau kelas.
- Upload dan repositori modul pembelajaran.
- Export/import peserta, presensi, nilai, atau laporan.
- Laporan semester dan dashboard monitoring.

## Rekomendasi Arah Desain

Jika Task 3 dimulai setelah Task 2 stabil, desain tabel operasional sebaiknya menggantung ke kelas, peserta kelas, dan kelompok kelas sistem blok.

Tabel yang kemungkinan dibutuhkan:

1. `pertemuan_kelas_blok`
2. `dosen_pertemuan_kelas_blok`
3. `monitoring_pertemuan_kelas_blok`
4. `presensi_mahasiswa_kelas_blok`
5. `logbook_mahasiswa_kelas_blok`
6. `komponen_penilaian_kelas_blok` atau `komponen_penilaian_blok`
7. `nilai_pertemuan_kelas_blok`
8. `nilai_komponen_kelas_blok`
9. `modul_kelas_blok` jika modul perlu dikelola per kelas

Keputusan nama final belum dikunci. Tentukan ulang saat Task 3 benar-benar dikerjakan.

## Prinsip Awal Task 3

- Pertemuan operasional harus berada pada konteks kelas, bukan langsung pada Blok template.
- Peserta presensi dan nilai berasal dari `peserta_kelas`.
- Semua jenis kegiatan Blok memakai kelompok belajar; peserta pertemuan berasal dari `anggota_kelompok_kelas_blok`.
- Materi pertemuan membaca template dari `materi_rinci_blok`, kecuali nanti diputuskan perlu snapshot per kelas.
- Dosen diplot ke pertemuan atau materi kelas, bukan ke template Blok.
- Presensi, monitoring, logbook, dan nilai hanya boleh dibuat untuk kelas yang valid dan aktif.
- Operasi massal seperti presensi, input nilai, dan copy komponen wajib memakai `DB::transaction()`.

## Status Implementasi Task 3

Scope Task 3 dikerjakan bertahap. Jangan mengerjakan seluruh backlog sekaligus jika belum ada kebutuhan operasional yang jelas.

- [x] Menambahkan standar waktu pada `materi_rinci_blok`.
- [x] Menambahkan standar waktu otomatis pada form Blok bagian Rincian Materi.
- [x] Membuat tabel `pertemuan_kelas_blok`.
- [x] Membuat tabel `dosen_pertemuan_kelas_blok`.
- [x] Membuat model `PertemuanKelasBlok`.
- [x] Membuat model `DosenPertemuanKelasBlok`.
- [x] Menambahkan relasi model untuk pertemuan dan plotting dosen.
- [x] Menambahkan tab `Pertemuan` pada detail Kelas Sistem Blok.
- [x] Menambahkan form tambah/edit/hapus pertemuan kelas blok.
- [x] Menambahkan pilihan dosen pada form pertemuan.
- [x] Menambahkan validasi dasar kelas, kegiatan, materi, kelompok, waktu, dan dosen.
- [x] Membuat tabel presensi mahasiswa per pertemuan.
      `presensi_pertemuan_blok`, dikunci ke `peserta_blok_id`, tanpa soft delete.
- [x] Membuat UI presensi mahasiswa.
      Komponen `blok-operasional.presensi-pertemuan`, dipakai dosen di menu Pertemuan Saya
      dan pengelola di tab Monitoring. Status awal semua `hadir`.
- [x] Membuat tabel monitoring atau jurnal pelaksanaan pertemuan.
      `monitoring_pertemuan_blok`, satu baris per pertemuan, tanpa soft delete.
- [x] Membuat UI monitoring atau jurnal pertemuan.
      Komponen `blok-operasional.jurnal-pertemuan` plus tab Monitoring
      (`blok-operasional.monitoring`) berisi ringkasan dan filter per blok.
- [x] Membuat validasi pertemuan oleh dosen atau pengelola.
      `monitoring_pertemuan_blok.divalidasi_pada` mengunci presensi dan jurnal untuk
      semua peran; hanya pengelola/admin yang bisa membukanya kembali. Inilah gerbang
      yang akan dibaca fitur logbook mahasiswa.
- [ ] Membuat logbook atau refleksi mahasiswa.
- [x] Membuat komponen penilaian.
      Tiga lapis: master `komponen_penilaian`, standar per jenis kegiatan
      `komponen_penilaian_kegiatan`, dan rubrik per blok `komponen_penilaian_blok`.
      Rincian pada `task/task_5.md`.
- [x] Membuat input nilai mahasiswa.
      `nilai_pertemuan_blok` plus komponen `blok-operasional.nilai-pertemuan`, dipakai dari
      halaman Pertemuan Saya (dosen) dan tab Monitoring (pengelola). Nilai sengaja tidak
      dikunci `divalidasi_pada`.
- [ ] Membuat rekap nilai blok atau kelas.
      Masih terbuka. Task 5 hanya mengerjakan input nilai per pertemuan; agregasi nilai akhir
      blok belum dibuat.
- [x] Membuat upload dan repositori modul pembelajaran.
      Terwujud sebagai repositori **tautan** (Google Drive dan video), bukan unggah file
      ke server: tabel `lampiran_materi_blok`, dikelola dari tab Pertemuan (pengelola)
      dan menu Pertemuan Saya (dosen pengampu), dibaca mahasiswa di menu Materi & Modul.
- [ ] Membuat export/import peserta, presensi, nilai, atau laporan.
- [ ] Membuat laporan semester dan dashboard monitoring.

## Implementasi Awal: Pertemuan Kelas Blok

Implementasi awal Task 3 dimulai dari fondasi jadwal pertemuan dan plotting dosen karena fitur presensi, monitoring, logbook, dan nilai akan bergantung pada data pertemuan.

### Standar Waktu Materi Rinci

`materi_rinci_blok` sekarang menyimpan standar waktu dengan pola:

```text
jumlah_sesi x durasi_menit_per_sesi
```

Contoh:

- `2 x 100 menit`
- `2 x 50 menit`
- `1 x 100 menit`

Field yang ditambahkan:

| Field | Keterangan |
| --- | --- |
| `jumlah_sesi` | Jumlah sesi atau unit waktu dalam satu rincian materi |
| `durasi_menit_per_sesi` | Durasi setiap sesi dalam menit, otomatis mengikuti durasi jenis kegiatan blok |

Catatan:

- Pada form Blok, pengguna mengisi jumlah sesi dan urutan/pertemuan materi; durasi per sesi mengikuti `aturan_kegiatan_blok.durasi_menit`.
- Semua jenis kegiatan Blok diperlakukan berbasis kelompok belajar, termasuk Kuliah Pakar.

- Nilai ini berada pada template materi Blok.
- Saat materi dipilih pada form pertemuan kelas, nilai `jumlah_sesi` dan `durasi_menit_per_sesi` otomatis dipakai sebagai default.
- Pada pertemuan kelas, nilai waktu tetap disimpan ulang agar jadwal operasional bisa menyesuaikan realita kelas tanpa mengubah template Blok.

### Tabel `pertemuan_kelas_blok`

Tabel ini menyimpan jadwal operasional kelas sistem blok.

Field utama:

| Field | Keterangan |
| --- | --- |
| `id_pertemuan_kelas_blok` | Primary key |
| `kelas_id` | Relasi ke `kelas.id_kelas` |
| `aturan_kegiatan_blok_id` | Jenis kegiatan dari Blok yang dipakai kelas |
| `materi_rinci_blok_id` | Template rincian materi yang dipakai, nullable |
| `kelompok_kelas_blok_id` | Kelompok kelas blok jika kegiatan perlu kelompok, nullable |
| `tanggal` | Tanggal pertemuan |
| `jam_mulai` | Jam mulai |
| `jam_selesai` | Jam selesai |
| `ruangan` | Ruangan pertemuan, nullable |
| `topik` | Topik operasional pertemuan, nullable |
| `jumlah_sesi` | Jumlah sesi operasional |
| `durasi_menit_per_sesi` | Durasi setiap sesi operasional |
| `status` | `draft`, `terjadwal`, `berlangsung`, `selesai`, `batal` |
| `catatan` | Catatan pertemuan, nullable |
| `deleted_at` | Soft delete |

Relasi delete:

- `kelas_id` memakai `cascadeOnDelete()` karena pertemuan mengikuti kelas.
- `aturan_kegiatan_blok_id` memakai `restrictOnDelete()`.
- `materi_rinci_blok_id` memakai `nullOnDelete()`.
- `kelompok_kelas_blok_id` memakai `nullOnDelete()`.

### Tabel `dosen_pertemuan_kelas_blok`

Tabel ini menyimpan plotting dosen pada pertemuan kelas.

Field utama:

| Field | Keterangan |
| --- | --- |
| `id_dosen_pertemuan_kelas_blok` | Primary key |
| `pertemuan_kelas_blok_id` | Relasi ke `pertemuan_kelas_blok` |
| `dosen_id` | Relasi ke `dosen.id_dosen` |
| `peran` | `pengampu`, `tutor`, `fasilitator` |
| `catatan` | Catatan plotting, nullable |

Constraint:

- unique gabungan `pertemuan_kelas_blok_id` dan `dosen_id`.

## Aturan Domain Pertemuan

- Pertemuan selalu berada pada konteks `kelas`, bukan langsung pada template `blok`.
- Pertemuan wajib mengacu ke `aturan_kegiatan_blok` dari Blok yang dipakai kelas.
- Pertemuan boleh mengacu ke `materi_rinci_blok` sebagai template topik dan standar waktu.
- Jika `aturan_kegiatan_blok.perlu_kelompok = true`, maka `kelompok_kelas_blok_id` wajib diisi.
- Jika `aturan_kegiatan_blok.perlu_kelompok = false`, maka `kelompok_kelas_blok_id` harus kosong.
- Kelompok yang dipilih harus berasal dari kelas yang sama dan kegiatan blok yang sama.
- Materi yang dipilih harus berada di bawah kegiatan blok yang sama.
- `jam_selesai` harus lebih besar dari `jam_mulai`.
- Dosen yang dipilih harus dosen aktif.
- Simpan pertemuan dan plotting dosen wajib memakai `DB::transaction()`.

## UI yang Sudah Ada

### Form Blok

Pada form Blok, bagian Rincian Materi sudah memiliki input:

- `Pertemuan Ke`
- `Jumlah Sesi`
- `Menit / Sesi`
- `Urutan`
- `Status`

Input ini digunakan untuk menyimpan standar waktu pada template materi.

### Detail Kelas Sistem Blok

Detail Kelas Sistem Blok sudah memiliki tab:

- `Ringkasan`
- `Kelompok`
- `Materi`
- `Pertemuan`

Tab `Pertemuan` menyediakan:

- pilihan jenis kegiatan;
- pilihan materi rinci berdasarkan jenis kegiatan;
- pilihan kelompok jika jenis kegiatan membutuhkan kelompok;
- tanggal, jam mulai, dan jam selesai;
- jumlah sesi dan menit per sesi;
- topik;
- ruangan;
- status;
- pilihan dosen;
- daftar pertemuan yang sudah dibuat;
- aksi `Kelola` dan `Hapus`.

## Rekomendasi Lanjutan

Urutan lanjutan yang disarankan setelah pertemuan stabil:

1. Presensi mahasiswa per pertemuan.
2. Monitoring atau jurnal pelaksanaan pertemuan.
3. Validasi pertemuan oleh dosen atau pengelola.
4. Logbook atau refleksi mahasiswa.
5. Komponen penilaian dan input nilai.
6. Rekap nilai blok atau kelas.
7. Modul pembelajaran.
8. Export/import dan laporan.

Untuk presensi, peserta sesi harus ditentukan dari aturan berikut:

- jika pertemuan tidak berkelompok, peserta berasal dari `peserta_kelas` aktif pada kelas tersebut;
- jika pertemuan berkelompok, peserta berasal dari `anggota_kelompok_kelas_blok` pada kelompok pertemuan tersebut.

## Verifikasi Implementasi Awal

Verifikasi yang sudah dilakukan:

- `php -l` pada model baru dan file Blade yang diubah.
- `php artisan migrate` berhasil.
- `php artisan view:cache` berhasil.
- `php artisan view:clear` dijalankan setelah compile view.

Catatan test:

- `php artisan test` masih gagal pada test bawaan auth/profile karena test masih memakai `Livewire\Volt\Volt`, `assertSeeVolt`, dan view `profile` yang tidak tersedia pada struktur proyek saat ini.
- Kegagalan tersebut tidak berasal dari implementasi Task 3.
