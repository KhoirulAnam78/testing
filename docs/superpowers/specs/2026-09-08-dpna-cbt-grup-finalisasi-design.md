# Desain DPNA: CBT Eksternal, Grup Bobot, dan Finalisasi

## Tujuan

Memperluas DPNA tanpa menghapus tabel, kolom, atau data yang sudah ada:

1. Kegiatan bersumber CBT tidak dapat diisi atau diimport manual oleh dosen maupun pengelola.
2. Struktur siap menerima nilai CBT dari aplikasi lain pada tahap berikutnya.
3. Beberapa kegiatan dapat digabung menjadi satu grup DPNA dengan rata-rata sama.
4. DPNA memiliki status draft dan final serta jejak buka kembali.
5. Nilai sumber dapat dilihat sampai tingkat pertemuan atau ujian CBT.
6. Finalisasi menyimpan snapshot agar nilai final tidak berubah ketika data operasional dikoreksi.

## Sumber Nilai CBT

Tambahkan `sumber_nilai` pada `jenis_kegiatan`, nilai `manual` atau `cbt`, default `manual`. Data lama otomatis tetap manual.

Kegiatan CBT:

- Tetap memakai `aturan_kegiatan_blok` sebagai konfigurasi kegiatan dalam blok.
- Tidak memakai matriks `nilai_pertemuan_blok` untuk input manual.
- Tombol simpan, template, dan import nilai disembunyikan dari UI.
- Semua action tulis nilai manual tetap menolak di server.
- UI menampilkan pesan bahwa nilai bersumber dari aplikasi CBT.

Tambahkan `nilai_cbt_blok`, satu baris per `aturan_kegiatan_blok` dan `peserta_blok`:

- `aturan_kegiatan_blok_id`
- `peserta_blok_id`
- `nilai_ujian_pertama`
- `mengikuti_remedial`
- `nilai_remedial`, nullable
- `bobot_ujian_pertama`, default 100
- `bobot_remedial`, default 0
- `nilai_akhir`
- `referensi_eksternal`, nullable
- `disinkronkan_pada`, nullable
- timestamps

Kunci unik: `aturan_kegiatan_blok_id + peserta_blok_id`. Tidak memakai soft delete karena data kelak ditulis dengan `updateOrCreate` dari proses sinkronisasi.

Aturan hitung:

- Tidak remedial: `nilai_akhir = nilai_ujian_pertama` dan bobot efektif `100 + 0`.
- Remedial: kedua nilai wajib ada, kedua bobot lebih dari atau sama dengan 0, total bobot tepat 100, lalu hasil merupakan jumlah nilai dikali bobot.
- Belum ada ujian pertama berarti sumber CBT belum lengkap.

Integrasi HTTP/API aplikasi lain belum dibuat. Tahap ini hanya menyediakan skema, model, perhitungan, akses baca, dan larangan input manual.

## Grup DPNA

Tambahkan `grup_dpna_blok`:

- `blok_id`
- `nama`
- `bobot`
- `urutan`
- timestamps

Tambahkan pivot `anggota_grup_dpna_blok`:

- `grup_dpna_blok_id`
- `aturan_kegiatan_blok_id`
- timestamps

Satu kegiatan hanya boleh menjadi anggota satu grup dalam blok yang sama. Validasi server memastikan kegiatan dan grup berasal dari blok sama.

Perhitungan grup:

- Nilai anggota manual adalah rata-rata nilai akhir pertemuan lengkap pada kegiatan itu.
- Nilai anggota CBT berasal dari `nilai_cbt_blok.nilai_akhir`.
- Nilai grup adalah rata-rata sama seluruh nilai anggota.
- Jika satu anggota belum lengkap, nilai grup belum lengkap.
- Grup beranggota satu menggantikan pembobotan kegiatan tunggal tanpa rumus khusus.
- Total bobot grup aktif ditambah bobot kehadiran wajib tepat 100%.

Kolom `aturan_kegiatan_blok.nilai_masuk_dpna` dan `bobot_nilai_dpna` tetap ada dan tidak dihapus. Saat blok belum memiliki grup baru, kalkulasi memakai konfigurasi lama agar data existing tetap berfungsi. Saat grup sudah dibuat, kalkulasi memakai grup dan mengabaikan konfigurasi lama untuk perhitungan, tetapi tidak menghapus nilainya.

## Draft dan Finalisasi

Tambahkan `finalisasi_dpna_blok`, satu riwayat per versi finalisasi:

- `blok_id`
- `versi`
- `status`: `final` atau `dibuka_kembali`
- `difinalisasi_oleh_user_id`
- `difinalisasi_pada`
- `dibuka_oleh_user_id`, nullable
- `dibuka_pada`, nullable
- `alasan_buka`, nullable
- timestamps

Status DPNA saat ini diturunkan dari riwayat terakhir:

- Tidak ada riwayat atau riwayat terakhir `dibuka_kembali`: draft.
- Riwayat terakhir `final`: final.

Tambahkan snapshot `snapshot_dpna_peserta`:

- `finalisasi_dpna_blok_id`
- `peserta_blok_id`
- `nim`
- `nama_mahasiswa`
- `nilai_akhir`
- `sumber_json`
- timestamps

`sumber_json` menyimpan snapshot terstruktur untuk audit:

- Kehadiran: nilai, jumlah hadir, jumlah wajib, bobot.
- Setiap grup: nama, bobot, nilai grup.
- Setiap anggota grup: jenis kegiatan, tipe sumber manual/CBT, nilai anggota.
- Manual: daftar pertemuan, materi, kelompok, nilai pertemuan, status lengkap.
- CBT: ujian pertama, status/nilai remedial, bobot ujian, nilai hasil, referensi eksternal, waktu sinkronisasi.

JSON dipakai pada snapshot karena bentuk detail manual dan CBT berbeda, datanya immutable, dan hanya dibaca untuk audit. Data operasional tetap berada di tabel relasional.

Finalisasi:

- Hanya user dengan permission `dpna-blok:finalisasi`, diberikan ke role `admin` dan `pengelola`.
- Seluruh peserta aktif/mengulang wajib memiliki nilai akhir lengkap.
- Konfigurasi bobot wajib valid.
- Snapshot seluruh peserta dibuat dalam satu transaksi.
- Versi naik satu dari versi terbesar blok.
- Setelah final, halaman matriks default membaca snapshot terbaru.
- Pengelola blok tanpa permission finalisasi hanya dapat melihat.

Buka kembali:

- Hanya permission `dpna-blok:finalisasi`.
- Alasan wajib diisi.
- Riwayat final terbaru ditandai `dibuka_kembali` beserta user dan waktu.
- Halaman kembali membaca kalkulasi draft dari data operasional.
- Snapshot lama tetap tersimpan dan dapat dilihat sebagai riwayat.
- Finalisasi berikutnya membuat versi baru; tidak menimpa snapshot lama.

Finalisasi DPNA tidak mengunci nilai pertemuan. Snapshot menjaga nilai final tetap stabil, sementara koreksi operasional dilakukan setelah DPNA dibuka kembali.

## Nilai Sumber pada UI

Halaman detail DPNA menampilkan:

- Badge `Draft` atau `Final vN`.
- Konfigurasi kehadiran dan grup DPNA.
- Matriks kolom per grup, bukan lagi kolom per kegiatan jika grup baru dipakai.
- Nilai akhir draft atau nilai snapshot final.
- Detail mahasiswa berjenjang: grup, anggota grup, lalu sumber pertemuan/CBT.
- Riwayat finalisasi dan alasan buka kembali.

Untuk konfigurasi awal grup, sediakan aksi `Buat dari konfigurasi lama` yang membuat satu grup untuk setiap kegiatan lama yang aktif dan menyalin bobotnya. Aksi hanya menambah baris; tidak mengubah atau menghapus konfigurasi lama.

## Error dan Keamanan

- Semua action mengambil ulang blok, kegiatan, grup, dan peserta dari database.
- ID dari state Livewire tidak dipercaya sebagai bukti relasi.
- Input manual, import, dan template untuk CBT ditolak server-side, bukan hanya disembunyikan.
- Finalisasi gagal bila sumber belum lengkap atau bobot tidak tepat 100%.
- Buka kembali gagal tanpa alasan.
- Operasi konfigurasi grup, finalisasi, dan buka kembali memakai transaksi.
- Tidak ada migration `dropTable`, `dropColumn`, truncate, atau migrasi data yang menghapus baris.

## Pengujian yang Ditulis

Pemeriksaan kode ditambahkan untuk:

- Kegiatan manual tetap dapat diisi.
- Kegiatan CBT menolak simpan, import, dan template manual.
- Rumus CBT tanpa remedial dan dengan remedial.
- Grup satu anggota dan beberapa anggota memakai rata-rata sama.
- Sumber tidak lengkap membuat DPNA belum lengkap.
- Fallback konfigurasi lama tetap menghasilkan nilai yang sama.
- Finalisasi menolak DPNA belum lengkap.
- Finalisasi membuat snapshot dan versi.
- Perubahan nilai operasional tidak mengubah snapshot final.
- Buka kembali memerlukan permission dan alasan.
- Finalisasi ulang membuat versi baru tanpa menghapus versi lama.

Test tidak dijalankan dalam sesi ini sesuai preferensi pengguna; pemeriksaan manual dilakukan pengguna.
