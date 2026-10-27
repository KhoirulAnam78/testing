# Dokumentasi API SIA Kedokteran

> Versi sumber: API `1.0.0`, OpenAPI `3.1` (`/openapi.json`).  
> Sumber awal: salinan Swagger API Service Kedokteran.  
> Cakupan: koneksi database Oracle SIA, SIAFOTO, SIREG, PEGAWAI, KTM, dan SKRIPSI.

## Status dokumen

Dokumen ini merapikan kontrak **request** yang tercantum pada Swagger. Kontrak **response** sebagian besar belum memiliki schema atau contoh nyata. Placeholder Swagger lama berikut bukan kontrak response:

```json
{
  "additionalProp1": {}
}
```

Setiap response memakai label berikut:

| Label | Arti |
|---|---|
| **Terkonfirmasi dokumentasi sumber** | Status HTTP atau schema tertulis pada sumber Swagger. Belum berarti sudah diuji terhadap server. |
| **Diasumsikan aplikasi** | Bentuk response yang sedang diharapkan kode aplikasi lokal. Wajib dicocokkan dengan response server. |
| **Belum dikonfirmasi** | Belum ada contoh response aktual atau schema yang memadai. Jangan dijadikan kontrak integrasi. |

## Aturan umum request

- Base URL dikonfigurasi terpisah dan wajib HTTPS pada aplikasi lokal.
- Endpoint selain `/token` diasumsikan memakai header `Authorization: Bearer <access_token>`.
- Client lokal mengirim `Accept: application/json` untuk endpoint JSON.
- Request `/token` memakai `application/x-www-form-urlencoded`.
- Request body endpoint tulis mengikuti JSON pada dokumentasi sumber, kecuali server menetapkan media type lain.
- Nama field dipertahankan sesuai sumber API, termasuk `kelas_pararel`.
- `limit` dan `offset` dipakai untuk pagination jika tersedia.

## Kontrak konsumsi `AkademikClient`

Bagian ini mendokumentasikan perilaku kode lokal, bukan hasil pengujian server. Seluruh butir berlabel **Diasumsikan aplikasi** dan status runtime tetap **Belum dikonfirmasi**.

- URL login dan base URL wajib memakai skema `https`.
- Redirect HTTP ditolak; URL harus menunjuk langsung ke endpoint tujuan.
- Login mengirim form `username` dan `password`, meminta JSON, serta hanya menerima response HTTP sukses.
- Response login wajib berupa JSON object dengan `access_token` string tidak kosong maksimal 16.384 byte dan `token_type` bernilai `bearer` tanpa membedakan kapital.
- `access_token` wajib berbentuk JWT tiga segmen dengan claim `exp` integer. Token disimpan sampai 60 detik sebelum `exp`; token dengan sisa masa aktif 60 detik atau kurang ditolak.
- Request data mengirim bearer token. Response `401` pertama menghapus cache token lalu mengulang login dan request tepat satu kali; `403` tidak diulang.
- Request koneksi atau response yang melewati timeout dikategorikan gagal. Redirect, HTTP non-sukses, dan response JSON yang bukan object kontrak juga ditolak.
- Method `get()` meminta `application/json`. Foto memakai `getRaw()` dengan nilai `Accept` default `*/*`.

## Kontrak parser sinkronisasi lokal

Semua bentuk response pada bagian ini **Diasumsikan aplikasi**. Ini menjelaskan input minimum yang diterima parser saat ini, bukan kontrak server terverifikasi.

### Envelope daftar ketat

Dipakai kurikulum, pemetaan mata kuliah-kurikulum, mata kuliah, KRS, kelas, daftar mahasiswa, dan dosen:

```json
{
  "status": "success",
  "data": [],
  "total_records": 0,
  "offset": 0
}
```

- `status` wajib bernilai `success` tanpa membedakan kapital.
- `data` wajib list JSON.
- `total_records` wajib integer nonnegatif, tetap sama pada seluruh halaman, dan sama dengan jumlah item yang akhirnya diterima.
- `offset` wajib integer dan sama dengan offset request.
- Halaman kosong sebelum `total_records` tercapai ditolak.

Pengecualian:

- Semester hanya mewajibkan `status` dan list `data`; pagination berhenti saat jumlah item kurang dari `limit` 500.
- Registrasi mahasiswa hanya mewajibkan `status` dan list `data`; `total_records` boleh diganti `total` atau tidak ada. Pagination lanjut selama halaman berisi 500 item dan total belum tercapai.

### Field minimum per operasi aktif

| Operasi | Field minimum yang diterima parser lokal |
|---|---|
| `POST /token` | `access_token`, `token_type`; syarat JWT dan `exp` mengikuti kontrak client di atas. |
| `GET /api/data/mahasiswa/registrasi` | Item object: `nim`, `id_semester`, `status_bayar`; `jenis_registrasi` boleh kosong. `id_semester` harus sama dengan filter. Nilai `status_bayar` `L` dipetakan aktif, nilai lain belum aktif. |
| `GET /api/data/semester` | Item object: `id_semester`, `kd_ta`, `kd_smt`, `is_semester_aktif`; tanggal opsional `tgl_mulai_kuliah1`, `tgl_akhir_kuliah1`, `tgl_mulai_krs`, `tgl_akhir_krs`. `kd_smt` hanya `1`, `2`, atau `3`; `id_semester` harus sama dengan gabungan `kd_ta` dan `kd_smt`. |
| `GET /api/data/kurikulum` | `kd_kur`, `nm_kur`, `kd_prodi`, `th_kur`, `min_sks_evaluasi_akhir`, `masa_studi_ideal`, `status`. Parser hanya menerima `status=A`. |
| `GET /api/data/matakuliah-kurikulum` | `kd_mk`, `kd_kur`, `kd_prodi`, `semester_paket`, `jenis_mk`; `jenis_mk` hanya `W` atau `P`. |
| `GET /api/data/matakuliah` | `kd_mk`, `nm_mk`, `kd_prodi`, `sks_mk`. |
| `GET /api/data/krs` | Header: `nim`, `id_semester`, list `detail`; `status_krs` boleh tidak ada, null, atau scalar. Detail: `kd_kelas`. |
| `GET /api/data/kelas` | `id_semester`, `kd_prodi`, `kd_mk`, `kelas_pararel`, `max_peserta`; `kd_kelas` boleh kosong karena parser membuat ID fallback. |
| `GET /api/data/mahasiswa` | Daftar hanya memakai `nim`, wajib unik. Setiap NIM kemudian diminta melalui endpoint detail. |
| `GET /api/data/mahasiswa/{nim}` | Root `status=success` dan `data` object. Data: `nim`, `nama`, `kd_prodi`, `kd_kur`, `angkatan`, `status`; `email_mhs` opsional; telepon memakai `hp_mhs` lalu fallback `telp_mhs`. NIM data wajib sama dengan path. |
| `GET /api/data/mahasiswa/{nim}/foto` | Body binary tidak kosong, maksimal 5 MiB; isi aktual harus terdeteksi sebagai JPEG, PNG, atau WebP. `404` diperlakukan sebagai foto tidak tersedia. |
| `GET /api/data/dosen` | Minimal satu identitas dari `kd_dosen`/`kode_dosen`, `nip`/`nip_dosen`, atau `nidn`, serta nama dari `nm_dosen`/`nama`/`nama_dosen`. Alias prodi: `kd_prodi`/`kode_prodi`; email: `email`/`email_dosen`; telepon: `no_hp`/`hp_dosen`/`telp_dosen`/`mobile`. |

Aturan nilai lain yang diterapkan parser:

- Tanggal semester non-null wajib persis `YYYY-MM-DDTHH:MM:SS` dan rentang mulai–selesai tidak boleh terbalik.
- SKS mata kuliah menerima 0,5–99,9 dengan maksimal satu angka desimal. SKS minimum lulus kurikulum menerima 0,5–999,9 dengan maksimal satu angka desimal.
- Status mahasiswa yang diterima: `A`/`AKTIF`, `CUTI`, `LULUS`, dan `NONAKTIF`/`NON-AKTIF`.
- Status dosen yang diterima: kosong/`A`/`AKTIF`/`1` untuk aktif dan `N`/`NONAKTIF`/`NON-AKTIF`/`0` untuk nonaktif.
- Jika `kd_dosen` dan NIP sama-sama ada, nilainya wajib sama. Field dosen lain yang dikenal parser: `gelar_depan`, `gelar_depan_dosen`, `gelar_belakang`, `gelar_belakang_dosen`, `bidang_keahlian`, `keahlian`, `bidang`, dan `nm_dosen_f` sebagai fallback gelar.
- Alias di atas adalah fallback parser lokal, bukan bukti bahwa server menjamin seluruh alias.

## Response umum yang perlu dipastikan

### Validation error `422`

**Terkonfirmasi dokumentasi sumber**, tetapi belum diverifikasi runtime:

```json
{
  "detail": [
    {
      "loc": ["query", "field_name"],
      "msg": "string",
      "type": "string",
      "input": "string",
      "ctx": {}
    }
  ]
}
```

Pastikan apakah `input` dan `ctx` selalu ada, apakah `loc` dapat berisi integer, serta apakah error bisnis juga memakai `422`.

### Status yang belum didokumentasikan

Response berikut perlu diminta atau diuji untuk setiap endpoint terkait:

| Status | Kondisi yang perlu diperiksa |
|---|---|
| `400` | Request valid secara schema tetapi ditolak aturan bisnis. Sumber menyebut pembatalan KRS bernilai dapat menghasilkan `400`. |
| `401` | Token hilang, salah, atau kedaluwarsa. Client lokal mencoba login ulang sekali saat menerima `401`. |
| `403` | Token valid tanpa hak akses. |
| `404` | Mahasiswa, dosen, kelas, kurikulum, foto, atau data lain tidak ditemukan. |
| `409` | Duplikasi atau konflik perubahan data. |
| `429` | Rate limit, jika diterapkan. |
| `500`/`502`/`503`/`504` | Gangguan aplikasi, database, atau gateway. |

Pastikan semua error mengembalikan `Content-Type`, schema JSON, kode error stabil, dan pesan aman untuk ditampilkan atau dicatat.

## Indeks 37 operasi API data

| No. | Method | Path | Ringkasan |
|---:|---|---|---|
| 1 | `GET` | `/api/data/mahasiswa/registrasi` | Registrasi pembayaran mahasiswa |
| 2 | `GET` | `/api/data/semester` | Daftar semester |
| 3 | `GET` | `/api/data/semester/aktif` | Semester aktif |
| 4 | `GET` | `/api/data/kurikulum` | Daftar kurikulum |
| 5 | `GET` | `/api/data/kurikulum/{kd_kur}` | Detail kurikulum |
| 6 | `GET` | `/api/data/kurikulum-mahasiswa` | Daftar kurikulum mahasiswa |
| 7 | `GET` | `/api/data/kurikulum-mahasiswa/{nim}` | Kurikulum per mahasiswa |
| 8 | `GET` | `/api/data/matakuliah` | Daftar mata kuliah |
| 9 | `GET` | `/api/data/matakuliah-kurikulum` | Mata kuliah per kurikulum |
| 10 | `GET` | `/api/data/matakuliah/{kd_mk}` | Detail mata kuliah |
| 11 | `GET` | `/api/data/krs` | Daftar KRS |
| 12 | `DELETE` | `/api/data/krs` | Batalkan KRS |
| 13 | `POST` | `/api/data/krs/store` | Simpan KRS |
| 14 | `POST` | `/api/data/krs/batal` | Batalkan KRS melalui POST |
| 15 | `GET` | `/api/data/kelas/pertemuan` | Daftar pertemuan dan presensi |
| 16 | `DELETE` | `/api/data/kelas/pertemuan` | Hapus pertemuan dan presensi |
| 17 | `GET` | `/api/data/kelas/pertemuan/{kd_kelas}/{kuliah_ke}` | Detail pertemuan |
| 18 | `POST` | `/api/data/kelas/pertemuan/store` | Simpan pertemuan |
| 19 | `PUT` | `/api/data/kelas/pertemuan/update` | Perbarui pertemuan |
| 20 | `GET` | `/api/data/kelas` | Daftar kelas |
| 21 | `GET` | `/api/data/kelas/{kd_kelas}` | Detail kelas |
| 22 | `PUT` | `/api/data/kelas/{kd_kelas}` | Perbarui kelas melalui path |
| 23 | `DELETE` | `/api/data/kelas/{kd_kelas}` | Hapus kelas |
| 24 | `POST` | `/api/data/kelas/store` | Buat kelas |
| 25 | `PUT` | `/api/data/kelas/update` | Perbarui kelas melalui body |
| 26 | `GET` | `/api/data/kelas-pengampu` | Daftar pengampu |
| 27 | `POST` | `/api/data/kelas-pengampu` | Simpan pengampu |
| 28 | `PUT` | `/api/data/kelas-pengampu` | Perbarui pengampu |
| 29 | `DELETE` | `/api/data/kelas-pengampu` | Hapus pengampu |
| 30 | `GET` | `/api/data/krs-mahasiswa` | DPNA/nilai mahasiswa |
| 31 | `POST` | `/api/data/krs-mahasiswa/store` | Simpan nilai |
| 32 | `PUT` | `/api/data/krs-mahasiswa/update` | Perbarui nilai |
| 33 | `GET` | `/api/data/mahasiswa` | Daftar mahasiswa |
| 34 | `GET` | `/api/data/mahasiswa/{nim}` | Detail mahasiswa |
| 35 | `GET` | `/api/data/mahasiswa/{nim}/foto` | Foto mahasiswa |
| 36 | `GET` | `/api/data/dosen` | Daftar dosen |
| 37 | `GET` | `/api/data/dosen/{kd_dosen}` | Detail dosen |

## Autentikasi

### `POST /token`

Login untuk memperoleh JWT access token.

**Request body — `application/x-www-form-urlencoded`**

| Field | Tipe | Wajib | Keterangan |
|---|---|:---:|---|
| `grant_type` | string/null | Tidak | Jika diisi harus cocok pola `^password$`. Client lokal saat ini tidak mengirim field ini. |
| `username` | string | Ya | Username integrasi. |
| `password` | string | Ya | Password integrasi. |
| `scope` | string | Tidak | Scope OAuth2. |
| `client_id` | string/null | Tidak | Client ID OAuth2. |
| `client_secret` | string/null | Tidak | Client secret OAuth2. |

**Response**

- `200` — **Terkonfirmasi dokumentasi sumber**:

```json
{
  "access_token": "string",
  "token_type": "bearer",
  "expires_in_minutes": 0
}
```

- `422` — validation error umum.

**Diasumsikan aplikasi dan wajib dipastikan:** `access_token` harus JWT tiga segmen dengan claim integer `exp`; `token_type` harus `bearer` tanpa membedakan kapital. `expires_in_minutes` tidak dipakai client lokal. Pastikan juga response kredensial salah (`400`, `401`, atau `422`) dan body error-nya.

## Endpoint pendukung

Endpoint berikut tercantum tanpa schema request/response lengkap dan tidak termasuk 37 operasi data:

| Method | Path | Keterangan | Status response |
|---|---|---|---|
| `GET` | `/health` | Status seluruh database Oracle | **Belum dikonfirmasi** |
| `GET` | `/health/{db_name}` | Status database tertentu | **Belum dikonfirmasi**; enum `db_name` perlu dipastikan |
| `GET` | `/examples/sia/sysdate` | Contoh query SIA | **Belum dikonfirmasi** |
| `GET` | `/examples/pegawai/sysdate` | Contoh query PEGAWAI | **Belum dikonfirmasi** |
| `GET` | `/examples/sireg/sysdate` | Contoh query SIREG | **Belum dikonfirmasi** |
| `GET` | `/` | Root | **Belum dikonfirmasi** |

## 1. Registrasi mahasiswa

### 1. `GET /api/data/mahasiswa/registrasi`

Data pembayaran mahasiswa FK dari registrasi CMB dan SPP.

| Query | Tipe | Default/batas | Keterangan |
|---|---|---|---|
| `id_semester` | string | `20261` | ID semester. |
| `kd_prodi` | string/null | — | Filter prodi; kosong berarti seluruh prodi FK. |
| `status_bayar` | string | `L` | `L` lunas, `B` belum lunas. |
| `jenis_registrasi` | string/null | — | `CMB` atau `SPP`. |
| `limit` | integer | `100`, 1–500 | Jumlah data. |
| `offset` | integer | `0`, min 0 | Offset pagination. |

**Response:** `200` dan `422` **terkonfirmasi dokumentasi sumber**. Body sukses **belum dikonfirmasi**. Pastikan envelope pagination, field item CMB/SPP, tipe nominal/tanggal, dan bentuk data kosong.

## 2. Semester

### 2. `GET /api/data/semester`

Daftar semester akademik FK beserta jadwal akademik dan penanda semester aktif.

| Query | Tipe | Default/batas | Keterangan |
|---|---|---|---|
| `is_aktif` | boolean/null | — | Hanya semester aktif. |
| `kd_prodi` | string/null | — | Filter prodi. |
| `id_semester` | string/null | — | Filter ID semester, contoh `20261`. |
| `mulai_tahun` | string/null | `2025` | Tahun awal minimal. |
| `limit` | integer | `100`, 1–500 | Jumlah data. |
| `offset` | integer | `0`, min 0 | Offset pagination. |

**Response:** `200` dan `422` **terkonfirmasi dokumentasi sumber**. Body sukses **belum dikonfirmasi**. Pastikan format seluruh tanggal, zona waktu, `is_semester_aktif`, dan pagination.

### 3. `GET /api/data/semester/aktif`

| Query | Tipe | Default | Keterangan |
|---|---|---|---|
| `kd_prodi` | string | `111` | Kode prodi. |

**Response:** `200` dan `422` **terkonfirmasi dokumentasi sumber**. Body sukses **belum dikonfirmasi**. Pastikan response saat tidak ada semester aktif dan apakah hasil berupa object tunggal atau envelope.

## 3. Kurikulum

### 4. `GET /api/data/kurikulum`

| Query | Tipe | Default/batas | Keterangan |
|---|---|---|---|
| `status` | string/null | — | `A` aktif, `N` nonaktif. |
| `kd_prodi` | string/null | — | Filter prodi. |
| `id_kurikulum` | string/null | — | Kode kurikulum, contoh `S1DOK2025`. |
| `limit` | integer | `100`, 1–500 | Jumlah data. |
| `offset` | integer | `0`, min 0 | Offset pagination. |

**Response:** `200` dan `422` **terkonfirmasi dokumentasi sumber**. Body sukses **belum dikonfirmasi**. Sumber menyebut field `kd_kur`, `nm_kur`, `th_kur`, `status`, `min_sks_evaluasi_akhir`, dan masa studi; nama serta tipe final perlu dipastikan.

### 5. `GET /api/data/kurikulum/{kd_kur}`

| Path | Tipe | Wajib | Keterangan |
|---|---|:---:|---|
| `kd_kur` | string | Ya | Kode kurikulum. |

**Response:** `200` dan `422` **terkonfirmasi dokumentasi sumber**. Body sukses dan response not found **belum dikonfirmasi**.

### 6. `GET /api/data/kurikulum-mahasiswa`

| Query | Tipe | Default/batas | Keterangan |
|---|---|---|---|
| `id_mahasiswa` | string/null | — | NIM/ID mahasiswa. |
| `nim` | string/null | — | Alias NIM. |
| `kd_kur` | string/null | — | Kode kurikulum. |
| `kd_prodi` | string/null | — | Kode prodi. |
| `angkatan` | string/null | — | Tahun angkatan. |
| `search` | string/null | — | Pencarian NIM atau nama. |
| `limit` | integer | `100`, 1–500 | Jumlah data. |
| `offset` | integer | `0`, min 0 | Offset pagination. |

**Response:** `200` dan `422` **terkonfirmasi dokumentasi sumber**. Body sukses **belum dikonfirmasi**. Sumber menyebut key tambahan `kurikulum` saat filter mahasiswa diberikan; pastikan apakah `data` tetap ada dan bagaimana kondisi banyak/nihil hasil.

### 7. `GET /api/data/kurikulum-mahasiswa/{nim}`

| Path | Tipe | Wajib |
|---|---|:---:|
| `nim` | string | Ya |

**Response:** `200` dan `422` **terkonfirmasi dokumentasi sumber**. Body sukses dan not found **belum dikonfirmasi**.

## 4. Mata kuliah

### 8. `GET /api/data/matakuliah`

| Query | Tipe | Default/batas | Keterangan |
|---|---|---|---|
| `kd_prodi` | string/null | — | Filter prodi. |
| `semester_paket` | integer/null | — | Semester paket. |
| `jenis_mk` | string/null | — | `W` wajib, `P` pilihan. |
| `search` | string/null | — | Kode atau nama mata kuliah. |
| `limit` | integer | `100`, 1–500 | Jumlah data. |
| `offset` | integer | `0`, min 0 | Offset pagination. |

**Response:** `200` dan `422` **terkonfirmasi dokumentasi sumber**. Body sukses **belum dikonfirmasi**. Pastikan field SKS total/teori/praktik/lapangan dan tipe numeriknya.

### 9. `GET /api/data/matakuliah-kurikulum`

| Query | Tipe | Default/batas | Keterangan |
|---|---|---|---|
| `id_kurikulum` | string | `S1DOK2025` | Kode/ID kurikulum. |
| `semester_paket` | integer/null | — | Semester paket. |
| `jenis_mk` | string/null | — | `W` atau `P`. |
| `kd_prodi` | string/null | — | Filter prodi. |
| `limit` | integer | `100`, 1–500 | Jumlah data. |
| `offset` | integer | `0`, min 0 | Offset pagination. |

**Response:** `200` dan `422` **terkonfirmasi dokumentasi sumber**. Body sukses **belum dikonfirmasi**.

### 10. `GET /api/data/matakuliah/{kd_mk}`

| Lokasi | Field | Tipe | Wajib | Keterangan |
|---|---|---|:---:|---|
| path | `kd_mk` | string | Ya | Kode mata kuliah. |
| query | `kd_kur` | string/null | Tidak | Kurikulum pemegang mata kuliah. |

**Response:** `200` dan `422` **terkonfirmasi dokumentasi sumber**. Body sukses dan not found **belum dikonfirmasi**.

## 5. KRS

### 11. `GET /api/data/krs`

Header KRS beserta nested detail mata kuliah.

| Query | Tipe | Default/batas | Keterangan |
|---|---|---|---|
| `id_mahasiswa` | string/null | — | NIM mahasiswa. |
| `nim` | string/null | — | Alias NIM. |
| `id_semester` | string/null | — | ID semester. |
| `kd_ta` | string/null | — | Tahun akademik. |
| `kd_smt` | string/null | — | Semester `1`/`2`. |
| `status_krs` | string/null | — | `0` belum disetujui, `1` disetujui. |
| `limit` | integer | `50`, 1–200 | Jumlah header KRS. |
| `offset` | integer | `0`, min 0 | Offset header KRS. |

**Response:** `200` dan `422` **terkonfirmasi dokumentasi sumber**, tetapi body sukses OpenAPI hanya placeholder.

**Diasumsikan aplikasi — wajib diverifikasi:**

```json
{
  "status": "success",
  "data": [
    {
      "nim": "111260031",
      "id_semester": "20261",
      "status_krs": "1",
      "detail": [
        {
          "kd_kelas": "111KN10506484"
        }
      ]
    }
  ],
  "total_records": 1,
  "offset": 0
}
```

Client sinkronisasi lokal mensyaratkan:

- `status` bernilai `success` tanpa membedakan kapital;
- `data` berupa list header KRS;
- `total_records` integer nonnegatif dan stabil selama pagination;
- `offset` sama dengan offset request;
- tiap header memiliki `nim`, `id_semester`, dan list `detail`;
- tiap detail memiliki `kd_kelas`;
- `status_krs` boleh tidak ada/null/scalar;
- ketika filter mahasiswa dipakai, sumber menyebut key `krs`; hubungan key itu dengan `data` perlu dipastikan.

### 12. `DELETE /api/data/krs`

Pembatalan mata kuliah KRS. Data dapat dikirim melalui query atau JSON body.

| Query | Tipe | Keterangan |
|---|---|---|
| `nim` | string/null | NIM mahasiswa. |
| `kd_kelas` | string/null | Kelas yang dibatalkan. |
| `id_semester` | string/null | ID semester. |
| `kd_ta` | string/null | Tahun akademik. |
| `kd_smt` | string/null | Semester. |
| `kd_krs` | string/null | Kode KRS langsung. |

```json
{
  "nim": "string",
  "id_semester": "string",
  "kd_ta": "string",
  "kd_smt": "string",
  "kd_krs": "string",
  "kd_kelas": "string",
  "kelas_ids": ["string"]
}
```

**Response:** `200` dan `422` **terkonfirmasi dokumentasi sumber**; sumber juga menyebut `400` jika nilai sudah terisi. Semua body **belum dikonfirmasi**. Pastikan prioritas jika query dan body dikirim bersamaan, partial success batch, jumlah data dihapus, dan idempotensi pembatalan ulang.

### 13. `POST /api/data/krs/store`

```json
{
  "nim": "string",
  "id_semester": "string",
  "kd_ta": "string",
  "kd_smt": "string",
  "semester": 0,
  "kelas_ids": ["string"],
  "keterangan": "KRS Online Siakad Blok"
}
```

**Response:** `201` dan `422` **terkonfirmasi dokumentasi sumber**. Body sukses **belum dikonfirmasi**. Pastikan field wajib, hasil kelas per item, penanganan kelas duplikat/penuh, transaksi batch, `kd_krs` hasil generate, dan idempotensi retry.

### 14. `POST /api/data/krs/batal`

Alternatif pembatalan bagi client yang tidak mendukung body pada `DELETE`.

```json
{
  "nim": "string",
  "id_semester": "string",
  "kd_ta": "string",
  "kd_smt": "string",
  "kd_krs": "string",
  "kd_kelas": "string",
  "kelas_ids": ["string"]
}
```

**Response:** `200` dan `422` **terkonfirmasi dokumentasi sumber**. Body dan kesetaraan perilaku dengan operasi nomor 12 **belum dikonfirmasi**.

## 6. Pertemuan dan kehadiran

### 15. `GET /api/data/kelas/pertemuan`

| Query | Tipe | Default/batas | Keterangan |
|---|---|---|---|
| `id_kelas` | string/null | — | ID kelas. |
| `kd_kelas` | string/null | — | Alias ID kelas. |
| `kuliah_ke` | integer/null | min 1 | Nomor pertemuan. |
| `include_presensi` | boolean/null | Dinamis | Default true jika `kuliah_ke` diisi, false jika daftar semua pertemuan. |
| `limit` | integer | `50`, 1–500 | Jumlah data. |
| `offset` | integer | `0`, min 0 | Offset pagination. |

**Response:** `200` bertipe `string` dan `422` **terkonfirmasi dokumentasi sumber**. Tipe `string` kemungkinan dokumentasi tidak lengkap dan **wajib dipastikan**. Pastikan JSON/object aktual, statistik kehadiran, nested presensi, dan pagination.

### 16. `DELETE /api/data/kelas/pertemuan`

| Query | Tipe | Wajib | Keterangan |
|---|---|:---:|---|
| `kd_kelas` | string | Ya | ID kelas. |
| `kuliah_ke` | integer | Ya | Pertemuan, min 1. |

**Response:** `200` bertipe `string` dan `422` **terkonfirmasi dokumentasi sumber**. Body sukses, not found, dan hasil penghapusan presensi terkait **belum dikonfirmasi**.

### 17. `GET /api/data/kelas/pertemuan/{kd_kelas}/{kuliah_ke}`

| Path | Tipe | Wajib |
|---|---|:---:|
| `kd_kelas` | string | Ya |
| `kuliah_ke` | integer | Ya |

**Response:** `200` bertipe `string` dan `422` **terkonfirmasi dokumentasi sumber**. Schema detail pertemuan/presensi dan not found **belum dikonfirmasi**.

### Body pertemuan

Dipakai operasi nomor 18 dan 19. Field `default_status` hanya tercantum pada operasi simpan.

```json
{
  "kd_kelas": "string",
  "kuliah_ke": 1,
  "tgl_kul": "string",
  "materi": "string",
  "pembelajaran": "string",
  "indikator": "string",
  "keterangan": "string",
  "default_status": "H",
  "presensi": [
    {
      "nim": "string",
      "kd_krs": "string",
      "status_hadir": "H",
      "catatan": "string",
      "keterangan": "string"
    }
  ]
}
```

### 18. `POST /api/data/kelas/pertemuan/store`

**Response:** `201` bertipe `string` dan `422` **terkonfirmasi dokumentasi sumber**. Body sukses **belum dikonfirmasi**. Pastikan format `tgl_kul`, enum status hadir, perilaku `default_status`, hasil batch presensi, dan transaksi saat satu item gagal.

### 19. `PUT /api/data/kelas/pertemuan/update`

Body sama seperti body pertemuan tanpa `default_status` pada schema sumber.

**Response:** `200` bertipe `string` dan `422` **terkonfirmasi dokumentasi sumber**. Body sukses, partial update, dan not found **belum dikonfirmasi**.

## 7. Kelas

### 20. `GET /api/data/kelas`

| Query | Tipe | Default/batas | Keterangan |
|---|---|---|---|
| `id_semester` | string/null | `20261` | ID semester. |
| `kd_ta` | string/null | — | Tahun akademik. |
| `kd_smt` | string/null | — | Semester `1`/`2`. |
| `kd_prodi` | string/null | — | Filter prodi. |
| `kd_mk` | string/null | — | Kode mata kuliah. |
| `kelas_pararel` | string/null | — | Nama kelas paralel. |
| `search` | string/null | — | Kode/nama mata kuliah atau ID kelas. |
| `limit` | integer | `100`, 1–500 | Jumlah data. |
| `offset` | integer | `0`, min 0 | Offset pagination. |

**Response:** `200` dan `422` **terkonfirmasi dokumentasi sumber**, tetapi body sukses OpenAPI hanya placeholder.

**Diasumsikan aplikasi — wajib diverifikasi:**

```json
{
  "status": "success",
  "data": [
    {
      "kd_kelas": "111KN10516243",
      "id_semester": "20261",
      "kd_prodi": "111",
      "kd_mk": "11016301",
      "kelas_pararel": "A",
      "max_peserta": 50
    }
  ],
  "total_records": 1,
  "offset": 0
}
```

Client sinkronisasi lokal mensyaratkan:

- `status` bernilai `success` tanpa membedakan kapital;
- `data` berupa list;
- `total_records` integer nonnegatif dan stabil selama pagination;
- `offset` sama dengan offset request;
- item memiliki `id_semester`, `kd_prodi`, `kd_mk`, `kelas_pararel`, dan integer `max_peserta` 0–65535;
- `kd_kelas` diharapkan sebagai identitas sumber, tetapi client membuat fallback gabungan field lain bila kosong.

Sumber juga menyebut SKS, `tim_ajar`, hari, jam, ruang, dan `terisi`; schema serta nullability seluruh field itu perlu dipastikan.

### 21. `GET /api/data/kelas/{kd_kelas}`

| Path | Tipe | Wajib |
|---|---|:---:|
| `kd_kelas` | string | Ya |

**Response:** `200` dan `422` **terkonfirmasi dokumentasi sumber**. Body detail dan not found **belum dikonfirmasi**.

### Body perubahan kelas

```json
{
  "kd_kelas": "string",
  "kelas_pararel": "string",
  "max_peserta": 1,
  "min_peserta": 0,
  "kd_hari": "string",
  "kd_ruang": "string",
  "jam_mulai": "string",
  "jam_selesai": "string",
  "kd_dosen": "string",
  "keterangan": "string"
}
```

### 22. `PUT /api/data/kelas/{kd_kelas}`

Path `kd_kelas` wajib. Body memakai body perubahan kelas; sumber tetap menampilkan `kd_kelas` di body.

**Response:** `200` dan `422` **terkonfirmasi dokumentasi sumber**. Body sukses **belum dikonfirmasi**. Pastikan prioritas dan validasi jika `kd_kelas` path berbeda dari body.

### 23. `DELETE /api/data/kelas/{kd_kelas}`

Kelas hanya dapat dihapus jika `TERISI = 0` menurut sumber.

**Response:** `200` dan `422` **terkonfirmasi dokumentasi sumber**. Body sukses, status konflik saat kelas terisi, dan idempotensi hapus ulang **belum dikonfirmasi**.

### 24. `POST /api/data/kelas/store`

```json
{
  "kd_mk": "string",
  "kd_kur": "string",
  "kd_prodi": "111",
  "id_semester": "string",
  "kd_ta": "string",
  "kd_smt": "string",
  "kelas_pararel": "string",
  "sks": 0,
  "max_peserta": 50,
  "min_peserta": 0,
  "kd_hari": "string",
  "kd_ruang": "string",
  "jam_mulai": "string",
  "jam_selesai": "string",
  "kd_dosen": "string",
  "keterangan": "Kelas Siakad Blok Kedokteran"
}
```

**Response:** `201` dan `422` **terkonfirmasi dokumentasi sumber**. Body sukses **belum dikonfirmasi**. Pastikan `kd_kelas` hasil generate, field wajib/null, format jam, validasi kapasitas, dan idempotensi retry.

### 25. `PUT /api/data/kelas/update`

Body memakai body perubahan kelas dan `kd_kelas` menjadi identitas kelas.

**Response:** `200` dan `422` **terkonfirmasi dokumentasi sumber**. Body sukses dan not found **belum dikonfirmasi**.

## 8. Dosen pengampu

### 26. `GET /api/data/kelas-pengampu`

| Query | Tipe | Default/batas | Keterangan |
|---|---|---|---|
| `id_kelas` | string/null | — | Filter ID kelas. |
| `kd_kelas` | string/null | — | Alias ID kelas. |
| `kd_dosen` | string/null | — | NIP/ID dosen. |
| `id_semester` | string/null | — | ID semester. |
| `kd_ta` | string/null | — | Tahun akademik. |
| `kd_smt` | string/null | — | Semester. |
| `limit` | integer | `100`, 1–500 | Jumlah data. |
| `offset` | integer | `0`, min 0 | Offset pagination. |

**Response:** `200` dan `422` **terkonfirmasi dokumentasi sumber**. Body sukses **belum dikonfirmasi**. Sumber menyebut key `tim_pengampu` saat kelas difilter; pastikan hubungan key itu dengan list utama dan field `posisi`/`sks`.

### 27. `POST /api/data/kelas-pengampu`

Mendukung satu dosen atau batch `dosen_list`.

```json
{
  "kd_kelas": "string",
  "kd_dosen": "string",
  "posisi": 1,
  "sks": 0,
  "dosen_list": [
    {
      "kd_dosen": "string",
      "posisi": 1,
      "sks": 0
    }
  ],
  "sync_koordinator": true
}
```

**Response:** `201` dan `422` **terkonfirmasi dokumentasi sumber**. Body sukses **belum dikonfirmasi**. Pastikan mode tunggal versus batch, partial success, duplikasi, total pembagian SKS, dan hasil sinkronisasi koordinator.

### 28. `PUT /api/data/kelas-pengampu`

```json
{
  "kd_kelas": "string",
  "kd_dosen": "string",
  "posisi": 0,
  "sks": 0,
  "sync_koordinator": true
}
```

**Response:** `200` dan `422` **terkonfirmasi dokumentasi sumber**. Body sukses dan not found **belum dikonfirmasi**.

### 29. `DELETE /api/data/kelas-pengampu`

| Query | Tipe | Wajib | Keterangan |
|---|---|:---:|---|
| `kd_kelas` | string | Ya | ID kelas. |
| `kd_dosen` | string | Ya | NIP/ID dosen. |

**Response:** `200` dan `422` **terkonfirmasi dokumentasi sumber**. Body sukses, sinkronisasi koordinator setelah hapus, dan idempotensi **belum dikonfirmasi**.

## 9. DPNA/nilai mahasiswa

### 30. `GET /api/data/krs-mahasiswa`

| Query | Tipe | Default/batas | Keterangan |
|---|---|---|---|
| `id_mahasiswa` | string/null | — | NIM mahasiswa. |
| `nim` | string/null | — | Alias NIM. |
| `id_kelas` | string/null | — | ID kelas/DPNA. |
| `kd_kelas` | string/null | — | Alias ID kelas. |
| `id_semester` | string/null | — | ID semester. |
| `kd_ta` | string/null | — | Tahun akademik. |
| `kd_smt` | string/null | — | Semester. |
| `limit` | integer | `50`, 1–500 | Jumlah data. |
| `offset` | integer | `0`, min 0 | Offset pagination. |

**Response:** `200` bertipe `string` dan `422` **terkonfirmasi dokumentasi sumber**. Schema aktual **belum dikonfirmasi**. Pastikan daftar nilai, ringkasan DPNA, nilai null, tipe bobot, pagination, dan pembeda nilai belum diisi versus nilai nol.

### Body nilai

Operasi simpan dan update mendukung satu item pada root atau batch pada `items`.

```json
{
  "nim": "string",
  "kd_krs": "string",
  "kd_kelas": "string",
  "nilai": "string",
  "bobot_nilai": 4,
  "bobot_nilai_hdr": 100,
  "bobot_nilai_tugas": 100,
  "bobot_nilai_mid": 100,
  "bobot_nilai_uas": 100,
  "bobot_nilai_par": 100,
  "bobot_nilai_skp": 100,
  "bobot_nilai_lan": 100,
  "keterangan": "string",
  "items": [
    {
      "nim": "string",
      "kd_krs": "string",
      "kd_kelas": "string",
      "nilai": "string",
      "bobot_nilai": 4,
      "bobot_nilai_hdr": 100,
      "bobot_nilai_tugas": 100,
      "bobot_nilai_mid": 100,
      "bobot_nilai_uas": 100,
      "bobot_nilai_par": 100,
      "bobot_nilai_skp": 100,
      "bobot_nilai_lan": 100,
      "keterangan": "string"
    }
  ]
}
```

### 31. `POST /api/data/krs-mahasiswa/store`

**Response:** `201` bertipe `string` dan `422` **terkonfirmasi dokumentasi sumber**. Body sukses **belum dikonfirmasi**. Pastikan enum `nilai`, rentang seluruh bobot, mode root versus `items`, partial success, transaksi batch, dan aturan nilai yang sudah ada.

### 32. `PUT /api/data/krs-mahasiswa/update`

Body sama dengan body nilai.

**Response:** `200` bertipe `string` dan `422` **terkonfirmasi dokumentasi sumber**. Body sukses, partial update, dan not found **belum dikonfirmasi**.

## 10. Mahasiswa

### 33. `GET /api/data/mahasiswa`

| Query | Tipe | Default/batas | Keterangan |
|---|---|---|---|
| `angkatan` | string/null | — | Tahun angkatan. |
| `kd_prodi` | string/null | — | Kode prodi. |
| `limit` | integer | `100`, 1–500 | Jumlah data. |
| `offset` | integer | `0`, min 0 | Offset pagination. |

**Response:** `200` dan `422` **terkonfirmasi dokumentasi sumber**. Body sukses **belum dikonfirmasi**. Sumber menyebut enrichment `nm_prodi` dan `kd_kur`; field biodata, nullability, dan klasifikasi data pribadi perlu dipastikan.

### 34. `GET /api/data/mahasiswa/{nim}`

| Path | Tipe | Wajib |
|---|---|:---:|
| `nim` | string | Ya |

**Response:** `200` dan `422` **terkonfirmasi dokumentasi sumber**. Body biodata dan not found **belum dikonfirmasi**. Pastikan field sensitif yang boleh dikonsumsi dan dicatat pada log.

### 35. `GET /api/data/mahasiswa/{nim}/foto`

| Path | Tipe | Wajib |
|---|---|:---:|
| `nim` | string | Ya |

**Response:** `200` dan `422` **terkonfirmasi dokumentasi sumber**. Sumber menyatakan response berupa binary `image/jpeg`, bukan string JSON. Pastikan `Content-Type`, `Content-Length`, fallback format gambar, cache header, ukuran maksimum, dan response saat foto tidak ditemukan.

## 11. Dosen

### 36. `GET /api/data/dosen`

| Query | Tipe | Default/batas | Keterangan |
|---|---|---|---|
| `search` | string/null | — | Nama, nama bergelar, NIP, NIDN, atau kode dosen. |
| `kd_prodi` | string/null | — | Kode prodi. |
| `status` | string/null | — | Contoh `A` aktif, `C` cuti. Enum lengkap belum tersedia. |
| `include_pengampu_fk` | boolean | `false` | Sertakan dosen luar FK yang mengajar kelas Kedokteran. |
| `limit` | integer | `50`, 1–200 | Jumlah data. |
| `offset` | integer | `0`, min 0 | Offset pagination. |

**Response:** `200` dan `422` **terkonfirmasi dokumentasi sumber**. Body sukses **belum dikonfirmasi**. Pastikan schema kontak, status, prodi asal, penanda pengampu FK, dan pagination.

### 37. `GET /api/data/dosen/{kd_dosen}`

| Path | Tipe | Wajib |
|---|---|:---:|
| `kd_dosen` | string | Ya |

**Response:** `200` dan `422` **terkonfirmasi dokumentasi sumber**. Body profil, nested riwayat kelas, pagination riwayat, dan not found **belum dikonfirmasi**.

## Checklist verifikasi response aktual

Isi checklist per endpoint sebelum response dianggap kontrak stabil:

- [ ] URL environment dan versi API dicatat.
- [ ] Request contoh sudah disamarkan dari data pribadi dan kredensial.
- [ ] HTTP status sukses sesuai (`200` atau `201`).
- [ ] `Content-Type` sesuai JSON atau binary foto.
- [ ] Body sukses disimpan sebagai contoh tersanitasi.
- [ ] Daftar field, tipe, nullability, enum, dan format tanggal/jam dicatat.
- [ ] Response data kosong diuji.
- [ ] `limit`, `offset`, `total_records`, urutan, dan batas maksimum diuji.
- [ ] Pagination tidak menghasilkan data hilang/duplikat saat data sumber berubah.
- [ ] Token hilang, salah, dan kedaluwarsa diuji.
- [ ] Parameter/path/body tidak valid diuji untuk memastikan schema `422`.
- [ ] Resource tidak ditemukan diuji.
- [ ] Konflik, duplikasi, atau aturan bisnis diuji.
- [ ] Batch diuji untuk atomicity atau partial success.
- [ ] Retry endpoint tulis diuji untuk idempotensi.
- [ ] Error database/gateway diuji atau dikonfirmasi dengan pemilik API.
- [ ] Tidak ada password, token, atau data pribadi sensitif pada log/contoh.

## Matriks cakupan integrasi lokal

| No. | Endpoint | Dipakai kode lokal | Kontrak response runtime |
|---:|---|:---:|---|
| — | `POST /token` | Ya | **Belum dikonfirmasi**; kontrak parser lokal tercatat di atas. |
| 1 | `GET /api/data/mahasiswa/registrasi` | Ya | **Belum dikonfirmasi**; kontrak parser lokal tercatat di atas. |
| 2 | `GET /api/data/semester` | Ya | **Belum dikonfirmasi**; kontrak parser lokal tercatat di atas. |
| 3 | `GET /api/data/semester/aktif` | Tidak | **Belum dikonfirmasi**. |
| 4 | `GET /api/data/kurikulum` | Ya | **Belum dikonfirmasi**; kontrak parser lokal tercatat di atas. |
| 5 | `GET /api/data/kurikulum/{kd_kur}` | Tidak | **Belum dikonfirmasi**. |
| 6 | `GET /api/data/kurikulum-mahasiswa` | Tidak | **Belum dikonfirmasi**. |
| 7 | `GET /api/data/kurikulum-mahasiswa/{nim}` | Tidak | **Belum dikonfirmasi**. |
| 8 | `GET /api/data/matakuliah` | Ya | **Belum dikonfirmasi**; kontrak parser lokal tercatat di atas. |
| 9 | `GET /api/data/matakuliah-kurikulum` | Ya | **Belum dikonfirmasi**; kontrak parser lokal tercatat di atas. |
| 10 | `GET /api/data/matakuliah/{kd_mk}` | Tidak | **Belum dikonfirmasi**. |
| 11 | `GET /api/data/krs` | Ya | **Belum dikonfirmasi**; kontrak parser lokal tercatat di atas. |
| 12–19 | Operasi tulis KRS dan pertemuan | Tidak | **Belum dikonfirmasi**. |
| 20 | `GET /api/data/kelas` | Ya | **Belum dikonfirmasi**; kontrak parser lokal tercatat di atas. |
| 21–25 | Detail dan operasi tulis kelas | Tidak | **Belum dikonfirmasi**. |
| 26–29 | Operasi kelas-pengampu | Tidak | **Belum dikonfirmasi**. |
| 30–32 | Operasi nilai KRS mahasiswa | Tidak | **Belum dikonfirmasi**. |
| 33 | `GET /api/data/mahasiswa` | Ya | **Belum dikonfirmasi**; kontrak parser lokal tercatat di atas. |
| 34 | `GET /api/data/mahasiswa/{nim}` | Ya | **Belum dikonfirmasi**; kontrak parser lokal tercatat di atas. |
| 35 | `GET /api/data/mahasiswa/{nim}/foto` | Ya | **Belum dikonfirmasi**; kontrak parser lokal tercatat di atas. |
| 36 | `GET /api/data/dosen` | Ya | **Belum dikonfirmasi**; kontrak parser lokal tercatat di atas. |
| 37 | `GET /api/data/dosen/{kd_dosen}` | Tidak | **Belum dikonfirmasi**. |

Total cakupan kode lokal: 12 operasi, terdiri dari `/token` dan 11 dari 37 operasi data. Operasi bertanda “Tidak” tetap didokumentasikan dari sumber Swagger, tetapi belum menjadi kontrak integrasi lokal.

Sampai response aktual lolos checklist, contoh berlabel **Diasumsikan aplikasi** tidak boleh diperlakukan sebagai jaminan dari server API.