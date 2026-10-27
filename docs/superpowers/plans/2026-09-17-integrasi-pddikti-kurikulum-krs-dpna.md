# Sinkronisasi Kelas Kuliah Outbound dan DPNA

## Tujuan

Menyediakan modul `Sinkronisasi` terpisah untuk:

- membagi peserta blok ke kelas kuliah outbound;
- membaca DPNA final per kelas kuliah;
- memantau kesiapan pembagian dan finalisasi;
- menjaga operasional blok tetap menjadi sumber proses akademik lokal.

## Batas Domain

- `kelas` tetap rombel operasional blok. Tidak diubah atau dipakai sebagai kelas outbound.
- `kelas_kuliah` adalah wadah outbound lokal yang terpisah.
- `anggota_kelas_kuliah.mahasiswa_id` menjadi identitas tetap peserta KRS; `peserta_blok_id` hanya tautan operasional opsional.
- Satu mahasiswa maksimal berada pada satu kelas paralel untuk kombinasi semester dan mata kuliah yang sama.
- Kelas tanpa blok tetap dapat menyimpan anggota. Saat tautan `peserta_blok_id` terisi, blok dan mahasiswa wajib cocok.
- Menghapus kelas outbound hanya menghapus pemetaan outbound. `peserta_blok`, nilai, presensi, kelompok, pertemuan, finalisasi, dan snapshot tidak dihapus.
- DPNA kelas hanya membaca `snapshot_dpna_peserta` dari `finalisasi_dpna_blok` berstatus `final`. Nilai live tidak dihitung ulang.

## Implementasi Lokal

1. Buat tabel additive `kelas_kuliah` dan `anggota_kelas_kuliah`.
2. Buat model dan relasi tanpa mengubah alur model operasional.
3. Gunakan `PembagianKelasKuliah` untuk pembagian deterministik, merata, dan patuh kapasitas.
4. Sediakan halaman `Kelas Kuliah`, `DPNA Kelas`, dan `Monitoring` di menu `Sinkronisasi`.
5. Lindungi halaman dengan permission Spatie untuk role `admin` dan `pengelola`.
6. Sediakan pemeriksaan algoritma mandiri di `scripts/check_pembagian_kelas_kuliah.php`.

## Yang Ditunda

Kontrak API eksternal lain belum tersedia. Fitur berikut tidak dibuat sampai endpoint, autentikasi, contoh payload, enum, aturan idempotensi, timeout, dan semantics error disepakati:

- request API DPNA;
- retry, queue, cursor, audit batch, serta metadata sinkronisasi;
- pengiriman DPNA/outbox.

## Keamanan Data dan Rollback

- Migration bersifat additive: menambah identitas mahasiswa, melakukan backfill tervalidasi, lalu melonggarkan tautan peserta blok tanpa menghapus tabel atau data.
- `down()` sengaja non-destruktif agar pemetaan outbound tidak terhapus lewat rollback otomatis.
- Migration tidak dijalankan tanpa konfirmasi pengguna.
- Tidak ada hard delete terhadap data operasional.

## Verifikasi

- Lint seluruh file PHP baru/diubah.
- Periksa route `kelas-kuliah`, `dpna-kelas`, dan `monitoring-sinkronisasi`.
- Jalankan script pemeriksaan algoritma; jangan jalankan unit test.
- Periksa status migration tanpa menjalankan migration.