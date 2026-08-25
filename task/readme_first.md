Prompt Project: Pengembangan Sistem Informasi Akademik (SIAKAD) Sistem Blok Kedokteran
Tujuan
Membangun aplikasi SIAKAD khusus dengan metode pembelajaran sistem Blok. Pengembangan akan dilakukan secara bertahap melalui pembagian tugas di dalam folder ini (`task_1`, `task_2`, dan seterusnya). Selesaikan setiap tugas secara sekuensial hingga sistem siap digunakan.
Gambaran Umum Arsitektur & Fitur Aplikasi
1. Manajemen Master Data & Pengguna
Modul Dosen: CRUD data dosen beserta pengelolaan profil dasar.
Modul Kelola Semester: Pengaturan semester aktif (contoh: Ganjil 2026/2027).
Autentikasi & Hak Akses: Login multi-user (Admin/Pengelola, Dosen/Tutor, Mahasiswa).
2. Modul Jenis Blok (Aturan Dasar)
CRUD Jenis Kegiatan Blok (Praktikum, Tutorial/PBL, Kuliah Pakar, Skills Lab/OSCE).
Pengaturan standar jumlah pertemuan dan durasi waktu per jenis kegiatan.
3. Modul Utama: Manajemen Blok (Kompleks)
Struktur Blok: CRUD data Blok yang mencakup pemetaan Mata Kuliah terkait, bobot SKS, dan detail materi di setiap blok.
Manajemen Mahasiswa Blok: Input daftar mahasiswa yang mengambil blok tersebut pada semester aktif.
Pembagian Kelompok Dinamis: Mekanisme untuk memecah mahasiswa blok ke dalam kelompok belajar kecil yang disesuaikan per jenis kegiatan (misal: Kelompok Tutorial A1-A10, Kelompok Praktikum P1-P5).
Plotting Dosen Pengampu: Penjadwalan dan penugasan dosen/tutor spesifik untuk setiap pertemuan atau kelompok di dalam blok.
Repositori Modul: Fitur unggah dan unduh modul pembelajaran (modul praktikum, panduan tutorial, bahan kuliah) oleh pengelola atau dosen terkait.
4. Monitoring, Logbook, & Penilaian
Presensi & Monitoring Kuliah: Pengisian kehadiran mahasiswa dan jurnal perkuliahan real-time oleh dosen atau pengelola.
Logbook Mahasiswa: Mahasiswa dapat mengisi logbook/refleksi harian setelah dosen melakukan validasi monitoring kehadiran pada hari atau sesi tersebut.
Komponen & Standar Penilaian: CRUD parameter penilaian khusus per jenis blok (misal: Tutorial = Keaktifan & Perilaku; Kuliah = MCQ/CBT; Skills Lab = OSCE).
5. Sistem Rekapitulasi & Keamanan Data
Rekapitulasi Semester: Laporan performa dan status blok setiap semester.
Rekap Nilai Blok: Transkrip nilai akhir blok berdasarkan integrasi komponen penilaian yang telah diatur.
Mekanisme Penghapusan: Terapkan Soft Delete (untuk arsip sementara/restorasi data) dan Permanent Delete (pembersihan total dari database).
Instruksi Eksekusi
Bacalah file panduan pada setiap `task_x` sebelum menulis kode. Pastikan seluruh relasi database aman, performa query optimal, dan fungsionalitas modular terjaga dengan baik.
