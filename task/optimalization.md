# AI Development Guidelines

## Objective

Setiap kali membuat, mengubah, atau memperbaiki fitur, tujuan utama adalah menghasilkan kode yang:

1. Aman (Security First)
2. Cepat (Performance First)
3. Mudah di-maintain
4. Skalabel untuk data besar
5. Mengikuti best practice Laravel, PHP, MySQL, JavaScript, dan Vue.

Jangan pernah hanya membuat fitur yang "berjalan". Selalu pikirkan bagaimana fitur tersebut akan berjalan ketika jumlah data mencapai ratusan ribu hingga jutaan record.

---

# General Principles

Sebelum menulis kode selalu lakukan analisis berikut:

* Apakah query database sudah optimal?
* Apakah ada kemungkinan N+1 Query?
* Apakah data perlu di-cache?
* Apakah query memanfaatkan index?
* Apakah operasi dilakukan di database daripada di PHP?
* Apakah fitur tetap cepat ketika data sudah sangat banyak?
* Apakah terdapat potensi SQL Injection?
* Apakah ada validasi input?
* Apakah hak akses sudah diperiksa?
* Apakah operasi bisa menyebabkan bottleneck?

Jika menemukan pendekatan yang lebih efisien daripada permintaan awal, jelaskan alasannya lalu gunakan pendekatan yang lebih baik.

---

# Database Optimization

Selalu utamakan efisiensi query.

## Wajib

* Gunakan eager loading daripada N+1 Query.
* Hindari query di dalam loop.
* Ambil hanya kolom yang diperlukan menggunakan `select()`.
* Hindari `select *`.
* Gunakan pagination untuk data besar.
* Gunakan chunk atau cursor untuk proses massal.
* Gunakan aggregate SQL (`COUNT`, `SUM`, `AVG`, `MAX`, `MIN`) daripada menghitung di PHP.
* Gunakan EXISTS daripada COUNT jika hanya ingin mengetahui keberadaan data.
* Gunakan JOIN bila lebih efisien daripada query berulang.

## Selalu Evaluasi

Apakah query dapat dipercepat dengan:

* index
* composite index
* covering index

Jika iya, berikan rekomendasi migration untuk membuat index tersebut.

Contoh:

```
$table->index(['id_prodi','semester']);
```

---

# Query Rules

Hindari:

```
foreach (...) {
    DB::table(...)->first();
}
```

Gunakan:

* JOIN
* eager loading
* whereIn
* subquery
* aggregate query

---

# Large Dataset

Asumsikan tabel dapat berisi:

* 100.000 data
* 1 juta data
* bahkan lebih

Kode harus tetap layak digunakan.

Gunakan:

* chunk()
* chunkById()
* lazy()
* cursor()

untuk proses besar.

Jangan pernah:

```
Model::all()
```

jika data berpotensi besar.

---

# Pagination

Untuk tabel data:

Gunakan pagination.

Jangan load seluruh data sekaligus.

Jika menggunakan DataTables server-side:

* filtering dilakukan di database
* sorting dilakukan di database
* search dilakukan di database

bukan di Collection PHP.

---

# Caching

Evaluasi apakah data layak di-cache.

Contoh:

* master data
* daftar fakultas
* daftar prodi
* konfigurasi
* setting
* menu
* role

Gunakan cache bila sesuai.

---

# Validation

Semua input wajib divalidasi.

Gunakan Form Request bila memungkinkan.

Jangan percaya data dari frontend.

---

# Security

Selalu periksa:

## SQL Injection

Gunakan Query Builder atau Eloquent.

Jangan menggunakan raw query tanpa binding.

Hindari:

```
"... WHERE id = $id"
```

Gunakan:

```
->where('id',$id)
```

---

## Authorization

Pastikan setiap endpoint memiliki pengecekan hak akses.

Jangan hanya mengandalkan middleware bila masih diperlukan validasi kepemilikan data.

---

## Authentication

Pastikan user telah login sebelum mengakses resource yang membutuhkan autentikasi.

---

## XSS

Escape seluruh output yang berasal dari user.

Gunakan sanitasi bila diperlukan.

---

## CSRF

Gunakan CSRF protection pada seluruh form.

---

## File Upload

Selalu validasi:

* mime
* ukuran file
* ekstensi

Jangan percaya nama file dari client.

Gunakan nama acak.

---

# API

API harus:

* menggunakan validasi
* response konsisten
* HTTP Status Code sesuai
* tidak membocorkan stack trace
* tidak mengembalikan informasi sensitif

---

# Logging

Gunakan logging hanya untuk:

* error
* warning
* audit penting

Jangan log:

* password
* token
* secret
* session
* data sensitif

---

# Performance Checklist

Sebelum menyelesaikan fitur, lakukan evaluasi:

* Apakah query dapat dipercepat?
* Apakah ada query berulang?
* Apakah ada eager loading yang belum digunakan?
* Apakah ada data yang tidak perlu diambil?
* Apakah pagination sudah digunakan?
* Apakah filtering dilakukan di SQL?
* Apakah sorting dilakukan di SQL?
* Apakah operasi berat dilakukan di PHP padahal bisa di SQL?
* Apakah cache diperlukan?
* Apakah index database diperlukan?

Jika jawabannya ya, lakukan optimalisasi terlebih dahulu.

---

# Code Quality

Kode harus:

* bersih
* mudah dibaca
* memiliki nama variabel yang jelas
* mengikuti PSR-12
* tidak mengandung duplikasi
* reusable
* modular

---

# Laravel Best Practice

Utamakan:

* Eloquent bila efisien
* Query Builder bila lebih cepat
* Repository hanya jika memang diperlukan
* Service Class untuk business logic
* Form Request untuk validasi
* Resource untuk response API

Business logic tidak boleh menumpuk di Controller.

---

# Frontend

Untuk Vue/JavaScript:

* minimalkan request HTTP
* gunakan debounce untuk pencarian
* gunakan lazy loading bila memungkinkan
* hindari rendering ulang yang tidak perlu
* gunakan computed daripada watch bila sesuai

---

# Final Review

Sebelum menyatakan pekerjaan selesai, lakukan review mandiri dan pastikan:

✅ Tidak ada N+1 Query

✅ Query sudah optimal

✅ Aman dari SQL Injection

✅ Input tervalidasi

✅ Authorization sudah benar

✅ Menggunakan pagination bila diperlukan

✅ Siap untuk data skala besar

✅ Tidak ada kode duplikat

✅ Mudah di-maintain

Jika masih ada potensi peningkatan performa atau keamanan, lakukan perbaikan terlebih dahulu sebelum menyelesaikan implementasi.
