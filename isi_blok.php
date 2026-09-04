<?php

/**
 * Mengisi pelaksanaan satu blok secara lengkap dan idempoten:
 * pertemuan + dosen pengampu, presensi, jurnal monitoring, nilai per komponen,
 * rekap nilai pertemuan, dan bobot DPNA.
 *
 *   php isi_blok.php          -> hanya mencetak rencana, tidak menulis apa pun
 *   php isi_blok.php --apply  -> menulis ke database
 *   php isi_blok.php --apply --tanpa-validasi   -> jurnal diisi tapi tidak divalidasi
 *
 * Butuh PHP 8.3:
 *   & "D:\laragon\bin\php\php-8.3.26-Win32-vs16-x64\php.exe" isi_blok.php
 *
 * Skrip sekali pakai, boleh dihapus setelah selesai. Semua tulisan memakai kunci bisnis
 * tabelnya masing-masing: `withTrashed()->firstOrNew()` + `restore()` untuk tabel
 * bersoft-delete, `updateOrCreate` untuk tabel tanpa soft delete. Jalan dua kali tidak
 * menduplikasi baris dan tidak menimpa jadwal yang sudah diatur manual.
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AnggotaKelompokBlok;
use App\Models\AturanKegiatanBlok;
use App\Models\Blok;
use App\Models\Dosen;
use App\Models\DosenPertemuanBlok;
use App\Models\KelompokBlok;
use App\Models\KomponenPenilaianBlok;
use App\Models\MonitoringPertemuanBlok;
use App\Models\NilaiPertemuanBlok;
use App\Models\PertemuanBlok;
use App\Models\PesertaBlok;
use App\Models\PresensiPertemuanBlok;
use App\Models\RekapNilaiPertemuanBlok;
use App\Models\User;
use App\Support\PerhitunganDpnaBlok;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Konfigurasi
|--------------------------------------------------------------------------
|
| Keputusan akademik ditaruh di sini supaya bisa dibaca dan diubah tanpa
| menelusuri isi skrip.
|
| - `blok`            : id blok yang diisi.
| - `bobot_kehadiran` : bobot kehadiran di DPNA, 0 berarti kehadiran nonaktif.
| - `bobot_kegiatan`  : kode jenis kegiatan => bobot DPNA. Kegiatan yang tidak
|                       disebut di sini otomatis nonaktif dengan bobot 0.
|
| Total bobot kehadiran + seluruh bobot kegiatan wajib tepat 100.
|
| Rubrik penilaian TIDAK dibuat oleh skrip ini. Kegiatan yang penanda
| `perlu_penilaian`-nya mati atau rubriknya kosong dilewati saja, mengikuti
| keputusan "ikut apa adanya". Kuliah Pakar dan Ujian OSPE karena itu hanya
| terisi presensi dan jurnal.
*/
$konfigurasi = [
    'blok' => 6,
    'bobot_kehadiran' => 10,
    'bobot_kegiatan' => [
        'TUT' => 25,
        'PRK' => 25,
        'MCQ' => 40,
    ],
];

// Jendela jam yang dipakai saat menurunkan jadwal untuk pertemuan yang belum punya
// rencana. Kelompok pada kegiatan yang sama dijadwalkan bergiliran di hari yang sama.
const JAM_KERJA_MULAI = 8 * 60;
const JAM_KERJA_SELESAI = 18 * 60;
const JEDA_ANTAR_SLOT = 30;

$apply = in_array('--apply', $argv, true);
$validasiJurnal = ! in_array('--tanpa-validasi', $argv, true);

$blok = Blok::find($konfigurasi['blok']);

if (! $blok) {
    fwrite(STDERR, "Blok #{$konfigurasi['blok']} tidak ditemukan atau sudah dihapus.\n");
    exit(1);
}

$totalBobot = $konfigurasi['bobot_kehadiran'] + array_sum($konfigurasi['bobot_kegiatan']);

if ($totalBobot !== 100) {
    fwrite(STDERR, "Total bobot DPNA harus tepat 100, sekarang {$totalBobot}.\n");
    exit(1);
}

/** Pseudo-acak deterministik supaya dua kali jalan menghasilkan angka yang sama. */
$acak = fn (string ...$bagian) => (int) sprintf('%u', crc32(implode('-', $bagian)));

$garis = fn () => str_repeat('-', 78);

echo $garis().PHP_EOL;
echo ($apply ? '>> MODE TULIS' : '>> MODE RENCANA (tidak menulis apa pun)').PHP_EOL;
echo 'Blok #'.$blok->id.' '.$blok->kode.' - '.$blok->nama.PHP_EOL;
echo 'Periode '.$blok->tanggal_mulai?->toDateString().' s/d '.$blok->tanggal_selesai?->toDateString().PHP_EOL;
echo 'Jurnal '.($validasiJurnal ? 'langsung divalidasi' : 'dibiarkan belum divalidasi').PHP_EOL;
echo $garis().PHP_EOL;

// -------------------------------------------------------------------------
// Prasyarat
// -------------------------------------------------------------------------

$aturanList = AturanKegiatanBlok::where('blok_id', $blok->id)
    ->with('jenis_kegiatan:id,kode,nama')
    ->withCount(['komponen_penilaian_blok', 'materi_rinci_blok'])
    ->orderBy('urutan')
    ->get();

if ($aturanList->isEmpty()) {
    fwrite(STDERR, "Blok ini belum punya jenis kegiatan.\n");
    exit(1);
}

// `mahasiswa` juga punya kolom `status`, jadi kolom peserta wajib dikualifikasi.
$peserta = PesertaBlok::query()
    ->where('peserta_blok.blok_id', $blok->id)
    ->whereIn('peserta_blok.status', ['aktif', 'mengulang'])
    ->join('mahasiswa', 'mahasiswa.id_mahasiswa', '=', 'peserta_blok.mahasiswa_id')
    ->with('mahasiswa:id_mahasiswa,nim,nama')
    ->orderBy('mahasiswa.nama')
    ->select('peserta_blok.*')
    ->get();

if ($peserta->isEmpty()) {
    fwrite(STDERR, "Blok ini belum punya peserta aktif.\n");
    exit(1);
}

// Tidak difilter per prodi: `dosenOptions()` pada tab Pertemuan juga tidak memfilter,
// dan blok ini memang sudah memakai dosen dari dua prodi berbeda.
$dosenTersedia = Dosen::where('status', 'aktif')->orderBy('id_dosen')->get(['id_dosen', 'nama']);

if ($dosenTersedia->isEmpty()) {
    fwrite(STDERR, "Tidak ada dosen aktif.\n");
    exit(1);
}

$userPencatat = User::query()
    ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'pengelola']))
    ->orderBy('id')
    ->first() ?? User::orderBy('id')->first();

echo 'Peserta aktif: '.$peserta->count()
    .' | Dosen aktif: '.$dosenTersedia->count()
    .' | Dicatat sebagai: '.($userPencatat?->username ?? $userPencatat?->email ?? 'NULL').PHP_EOL;

$pesertaAktifIds = $peserta->pluck('id_peserta_blok')->map(fn ($id) => (int) $id)->all();
$mulaiBlok = $blok->tanggal_mulai->copy();
$selesaiBlok = $blok->tanggal_selesai->copy();
$rentangHari = max(1, $mulaiBlok->diffInDays($selesaiBlok));

$hitung = ['pertemuan' => 0, 'dosen' => 0, 'anggota' => 0, 'presensi' => 0, 'jurnal' => 0, 'nilai' => 0, 'rekap' => 0];
$tulis = fn (callable $aksi) => $apply ? $aksi() : null;

// -------------------------------------------------------------------------
// 1. Kelengkapan kelompok
// -------------------------------------------------------------------------

echo PHP_EOL.'== 1. Kelompok dan anggota =='.PHP_EOL;

$kelompokPerAturan = [];

foreach ($aturanList as $aturan) {
    $nama = $aturan->jenis_kegiatan?->nama ?? ('kegiatan #'.$aturan->id);

    $kelompok = KelompokBlok::where('blok_id', $blok->id)
        ->where('aturan_kegiatan_blok_id', $aturan->id)
        ->where('status', 'aktif')
        ->orderBy('kode')
        ->get(['id_kelompok_blok', 'kode', 'nama']);

    if ($kelompok->isEmpty()) {
        echo "  {$nama}: TIDAK ADA kelompok aktif, kegiatan ini dilewati".PHP_EOL;
        $kelompokPerAturan[$aturan->id] = collect();

        continue;
    }

    $kelompokPerAturan[$aturan->id] = $kelompok;

    $isi = [];
    foreach ($kelompok as $k) {
        $isi[$k->id_kelompok_blok] = AnggotaKelompokBlok::where('kelompok_blok_id', $k->id_kelompok_blok)->count();
    }

    $sudah = AnggotaKelompokBlok::whereIn('kelompok_blok_id', $kelompok->pluck('id_kelompok_blok'))
        ->pluck('peserta_blok_id')->map(fn ($id) => (int) $id)->all();
    $belum = $peserta->reject(fn ($p) => in_array((int) $p->id_peserta_blok, $sudah, true))->values();

    echo "  {$nama}: {$kelompok->count()} kelompok, "
        .array_sum($isi).' dari '.$peserta->count().' peserta sudah punya kelompok'.PHP_EOL;

    // Peserta tanpa kelompok pada satu kegiatan membuat kehadiran dan nilainya di DPNA
    // permanen "Belum Lengkap", jadi sisanya disebar ke kelompok paling lengang.
    foreach ($belum as $p) {
        asort($isi);
        $tujuan = array_key_first($isi);
        $isi[$tujuan]++;
        $hitung['anggota']++;

        // `anggota_kelompok_blok.peran` sudah dihapus migrasi 2026_08_24_000001.
        $tulis(fn () => AnggotaKelompokBlok::updateOrCreate(
            ['kelompok_blok_id' => $tujuan, 'peserta_blok_id' => $p->id_peserta_blok],
            []
        ));

        echo "    + {$p->mahasiswa?->nim} {$p->mahasiswa?->nama} disebar ke kelompok #{$tujuan}".PHP_EOL;
    }
}

// -------------------------------------------------------------------------
// 2. Pertemuan dan dosen pengampu
// -------------------------------------------------------------------------

echo PHP_EOL.'== 2. Pertemuan dan dosen pengampu =='.PHP_EOL;

$dosenUrut = 0;

foreach ($aturanList as $aturan) {
    $nama = $aturan->jenis_kegiatan?->nama ?? ('kegiatan #'.$aturan->id);
    $kelompok = $kelompokPerAturan[$aturan->id];

    if ($kelompok->isEmpty()) {
        continue;
    }

    $rinciList = DB::table('materi_rinci_blok')
        ->join('materi_blok', 'materi_blok.id_materi_blok', '=', 'materi_rinci_blok.materi_blok_id')
        ->where('materi_blok.aturan_kegiatan_blok_id', $aturan->id)
        ->whereNull('materi_blok.deleted_at')
        ->whereNull('materi_rinci_blok.deleted_at')
        ->orderBy('materi_blok.urutan')
        ->orderBy('materi_rinci_blok.urutan')
        ->get([
            'materi_rinci_blok.id_materi_rinci_blok',
            'materi_rinci_blok.judul',
            'materi_rinci_blok.tanggal_rencana',
            'materi_rinci_blok.jam_mulai_rencana',
            'materi_rinci_blok.jam_selesai_rencana',
            'materi_rinci_blok.jumlah_sesi',
            'materi_rinci_blok.durasi_menit_per_sesi',
        ]);

    if ($rinciList->isEmpty()) {
        echo "  {$nama}: tidak ada rincian materi, dilewati".PHP_EOL;

        continue;
    }

    $jumlahRinci = $rinciList->count();
    $target = $jumlahRinci * $kelompok->count();
    $adaSekarang = PertemuanBlok::where('blok_id', $blok->id)
        ->where('aturan_kegiatan_blok_id', $aturan->id)->count();

    echo "  {$nama}: {$jumlahRinci} rincian x {$kelompok->count()} kelompok = {$target} pertemuan"
        ." (sekarang {$adaSekarang})".PHP_EOL;

    foreach ($kelompok as $kelompokIndex => $k) {
        // Satu dosen pengampu tetap per kelompok per kegiatan, meniru kebiasaan tutor.
        // Hanya dipakai untuk pertemuan yang belum punya dosen sama sekali.
        $dosen = $dosenTersedia[$dosenUrut % $dosenTersedia->count()];
        $dosenUrut++;
        $dipakai = 0;

        foreach ($rinciList as $rinciIndex => $r) {
            $sesi = max(1, (int) ($r->jumlah_sesi ?: 1));
            $durasi = max(1, (int) ($r->durasi_menit_per_sesi ?: $aturan->durasi_menit ?: 100));
            $rentangSesi = $sesi * $durasi;

            // Kelompok bergiliran memakai slot jam pada hari yang sama. Bila slotnya
            // habis, kelompok berikutnya bergeser ke hari setelahnya.
            $slotPerHari = max(1, intdiv(JAM_KERJA_SELESAI - JAM_KERJA_MULAI, $rentangSesi + JEDA_ANTAR_SLOT));
            $geserHari = intdiv($kelompokIndex, $slotPerHari);
            $slot = $kelompokIndex % $slotPerHari;

            // Rencana pada materi dipakai apa adanya bila ada; sisanya disebar merata
            // sepanjang periode blok memakai urutan rincian, bukan `pertemuan_ke`
            // (nilai itu berulang dari 1 di setiap materi pokok).
            $tanggal = $r->tanggal_rencana
                ? Carbon::parse($r->tanggal_rencana)
                : $mulaiBlok->copy()->addDays(
                    $jumlahRinci > 1
                        ? (int) round($rinciIndex * $rentangHari / ($jumlahRinci - 1))
                        : intdiv($rentangHari, 2)
                );
            $tanggal = $tanggal->addDays($geserHari);

            if ($tanggal->lt($mulaiBlok)) {
                $tanggal = $mulaiBlok->copy();
            }
            if ($tanggal->gt($selesaiBlok)) {
                $tanggal = $selesaiBlok->copy();
            }

            if ($r->jam_mulai_rencana) {
                $menitMulai = (int) substr((string) $r->jam_mulai_rencana, 0, 2) * 60
                    + (int) substr((string) $r->jam_mulai_rencana, 3, 2)
                    + $slot * ($rentangSesi + JEDA_ANTAR_SLOT);
            } else {
                $menitMulai = JAM_KERJA_MULAI + $slot * ($rentangSesi + JEDA_ANTAR_SLOT);
            }

            $menitMulai = max(0, min($menitMulai, 23 * 60 - $rentangSesi));
            $jamMulai = sprintf('%02d:%02d', intdiv($menitMulai, 60) % 24, $menitMulai % 60);
            $jamSelesai = sprintf('%02d:%02d', intdiv($menitMulai + $rentangSesi, 60) % 24, ($menitMulai + $rentangSesi) % 60);

            $model = PertemuanBlok::withTrashed()->firstOrNew([
                'blok_id' => $blok->id,
                'materi_rinci_blok_id' => $r->id_materi_rinci_blok,
                'kelompok_blok_id' => $k->id_kelompok_blok,
            ]);

            if (! $model->exists || $model->trashed()) {
                $hitung['pertemuan']++;
            }

            $tulis(function () use ($model, $aturan, $r, $k, $tanggal, $jamMulai, $jamSelesai, $sesi, $durasi) {
                $model->fill([
                    'aturan_kegiatan_blok_id' => $aturan->id,
                    // Jadwal yang sudah diatur manual tidak ditimpa.
                    'tanggal' => $model->tanggal ?: $tanggal->toDateString(),
                    'jam_mulai' => $model->jam_mulai ?: $jamMulai,
                    'jam_selesai' => $model->jam_selesai ?: $jamSelesai,
                    'ruangan' => $model->ruangan ?: 'R. '.$k->kode,
                    'topik' => $r->judul,
                    'jumlah_sesi' => $sesi,
                    'durasi_menit_per_sesi' => $durasi,
                    'status' => 'terjadwal',
                ]);

                if ($model->trashed()) {
                    $model->restore();
                }

                $model->save();
            });

            if ($apply) {
                $segar = PertemuanBlok::where('blok_id', $blok->id)
                    ->where('materi_rinci_blok_id', $r->id_materi_rinci_blok)
                    ->where('kelompok_blok_id', $k->id_kelompok_blok)
                    ->first();

                if ($segar && ! DosenPertemuanBlok::where('pertemuan_blok_id', $segar->id_pertemuan_blok)->exists()) {
                    DosenPertemuanBlok::updateOrCreate(
                        ['pertemuan_blok_id' => $segar->id_pertemuan_blok, 'dosen_id' => $dosen->id_dosen],
                        ['peran' => 'pengampu']
                    );
                    $hitung['dosen']++;
                    $dipakai++;
                }
            } else {
                $sudahAdaDosen = $model->exists
                    && DosenPertemuanBlok::where('pertemuan_blok_id', $model->id_pertemuan_blok)->exists();

                if (! $sudahAdaDosen) {
                    $hitung['dosen']++;
                    $dipakai++;
                }
            }
        }

        echo "    {$k->kode}: pengampu {$dosen->nama} untuk {$dipakai} pertemuan yang belum ada dosen".PHP_EOL;
    }
}

// -------------------------------------------------------------------------
// 3. Presensi, nilai, dan jurnal
// -------------------------------------------------------------------------

echo PHP_EOL.'== 3. Presensi, nilai, jurnal =='.PHP_EOL;

$rubrikPerAturan = [];
$dinilai = [];

foreach ($aturanList as $aturan) {
    $nama = $aturan->jenis_kegiatan?->nama ?? ('kegiatan #'.$aturan->id);
    $kode = strtoupper((string) $aturan->jenis_kegiatan?->kode);
    $rubrik = KomponenPenilaianBlok::where('aturan_kegiatan_blok_id', $aturan->id)
        ->orderBy('urutan')
        ->get(['id', 'nilai_min', 'nilai_maks']);

    $rubrikPerAturan[$aturan->id] = $rubrik;
    $layak = $aturan->perlu_penilaian && $rubrik->isNotEmpty();
    $dinilai[$aturan->id] = $layak;

    $alasan = match (true) {
        $layak => $rubrik->count().' komponen, total maksimum '.$rubrik->sum(fn ($k) => (float) $k->nilai_maks),
        ! $aturan->perlu_penilaian => 'penanda perlu_penilaian mati, hanya presensi dan jurnal',
        default => 'rubrik kosong, hanya presensi dan jurnal',
    };

    echo "  {$nama} ({$kode}): {$alasan}".PHP_EOL;

    if (isset($konfigurasi['bobot_kegiatan'][$kode]) && ! $layak) {
        echo "    PERINGATAN: kegiatan ini diberi bobot DPNA tapi tidak bisa dinilai.".PHP_EOL;
    }
}

$pertemuanList = PertemuanBlok::where('blok_id', $blok->id)
    ->with(['materi_rinci_blok:id_materi_rinci_blok,judul', 'kelompok_blok:id_kelompok_blok,kode'])
    ->orderBy('aturan_kegiatan_blok_id')
    ->orderBy('kelompok_blok_id')
    ->orderBy('tanggal')
    ->get();

echo '  Pertemuan yang ada di database sekarang: '.$pertemuanList->count().PHP_EOL;

$anggotaPerKelompok = AnggotaKelompokBlok::query()
    ->whereIn('kelompok_blok_id', $pertemuanList->pluck('kelompok_blok_id')->unique())
    ->get(['kelompok_blok_id', 'peserta_blok_id'])
    ->groupBy('kelompok_blok_id');

$aturanById = $aturanList->keyBy('id');

foreach ($pertemuanList as $pertemuan) {
    $aturan = $aturanById->get($pertemuan->aturan_kegiatan_blok_id);
    $anggota = ($anggotaPerKelompok[$pertemuan->kelompok_blok_id] ?? collect())
        ->pluck('peserta_blok_id')
        ->map(fn ($id) => (int) $id)
        ->filter(fn ($id) => in_array($id, $pesertaAktifIds, true))
        ->values();

    if (! $aturan || $anggota->isEmpty()) {
        continue;
    }

    // Presensi. Baris yang sudah ada tidak diubah.
    if ($aturan->perlu_presensi) {
        foreach ($anggota as $pesertaId) {
            if (PresensiPertemuanBlok::where('pertemuan_blok_id', $pertemuan->id_pertemuan_blok)
                ->where('peserta_blok_id', $pesertaId)->exists()) {
                continue;
            }

            $r = $acak('presensi', (string) $pertemuan->id_pertemuan_blok, (string) $pesertaId) % 100;
            $status = match (true) {
                $r < 88 => 'hadir',
                $r < 93 => 'sakit',
                $r < 97 => 'izin',
                default => 'alpa',
            };
            $hitung['presensi']++;

            $tulis(fn () => PresensiPertemuanBlok::updateOrCreate(
                ['pertemuan_blok_id' => $pertemuan->id_pertemuan_blok, 'peserta_blok_id' => $pesertaId],
                [
                    'status' => $status,
                    'keterangan' => match ($status) {
                        'sakit' => 'Surat keterangan sakit.',
                        'izin' => 'Izin kegiatan akademik.',
                        'alpa' => 'Tanpa keterangan.',
                        default => null,
                    },
                    'dicatat_oleh_user_id' => $userPencatat?->id,
                ]
            ));
        }
    }

    // Nilai dan rekapnya.
    $rubrik = $rubrikPerAturan[$aturan->id];

    if ($dinilai[$aturan->id]) {
        $nilaiMaksTotal = (float) $rubrik->sum(fn ($k) => (float) $k->nilai_maks);

        foreach ($anggota as $pesertaId) {
            $total = 0.0;

            foreach ($rubrik as $k) {
                $min = (float) $k->nilai_min;
                $maks = (float) $k->nilai_maks;
                $span = max(0.0, $maks - $min);
                $r = $acak('nilai', (string) $pertemuan->id_pertemuan_blok, (string) $pesertaId, (string) $k->id) % 41;
                // 0,60 sampai 1,00 dari rentang komponen. Rubrik berskala kecil seperti
                // 1-5 dibulatkan ke bilangan bulat supaya wajar dibaca.
                $mentah = $min + $span * (0.60 + $r / 100);
                $nilai = $span <= 10 ? round(min($maks, $mentah)) : round(min($maks, $mentah), 2);
                $total += $nilai;
                $hitung['nilai']++;

                $tulis(fn () => NilaiPertemuanBlok::updateOrCreate(
                    [
                        'pertemuan_blok_id' => $pertemuan->id_pertemuan_blok,
                        'peserta_blok_id' => $pesertaId,
                        'komponen_penilaian_blok_id' => $k->id,
                    ],
                    ['nilai' => $nilai, 'dinilai_oleh_user_id' => $userPencatat?->id]
                ));
            }

            $hitung['rekap']++;

            $tulis(fn () => RekapNilaiPertemuanBlok::updateOrCreate(
                ['pertemuan_blok_id' => $pertemuan->id_pertemuan_blok, 'peserta_blok_id' => $pesertaId],
                [
                    'total' => $total,
                    'nilai_akhir' => RekapNilaiPertemuanBlok::hitungNilaiAkhir($total, $nilaiMaksTotal),
                ]
            ));
        }
    }

    // Jurnal paling akhir: validasinya mengunci presensi pertemuan itu.
    $jurnal = MonitoringPertemuanBlok::where('pertemuan_blok_id', $pertemuan->id_pertemuan_blok)->first();

    if ($jurnal?->divalidasi_pada) {
        continue;
    }

    if (! $jurnal) {
        $hitung['jurnal']++;
    }

    $tulis(function () use ($pertemuan, $userPencatat, $validasiJurnal) {
        DB::transaction(function () use ($pertemuan, $userPencatat, $validasiJurnal) {
            MonitoringPertemuanBlok::updateOrCreate(
                ['pertemuan_blok_id' => $pertemuan->id_pertemuan_blok],
                [
                    'status_pelaksanaan' => 'terlaksana',
                    'tanggal_realisasi' => $pertemuan->tanggal?->toDateString(),
                    'jam_mulai_realisasi' => $pertemuan->jam_mulai,
                    'jam_selesai_realisasi' => $pertemuan->jam_selesai,
                    'topik_realisasi' => $pertemuan->materi_rinci_blok?->judul ?: $pertemuan->topik,
                    'catatan_pelaksanaan' => 'Pertemuan terlaksana sesuai rencana pada kelompok '
                        .($pertemuan->kelompok_blok?->kode ?? '-').'.',
                    'diisi_oleh_user_id' => $userPencatat?->id,
                    'divalidasi_pada' => $validasiJurnal ? now() : null,
                    'divalidasi_oleh_user_id' => $validasiJurnal ? $userPencatat?->id : null,
                ]
            );

            PertemuanBlok::whereKey($pertemuan->id_pertemuan_blok)
                ->update(['status' => MonitoringPertemuanBlok::STATUS_PERTEMUAN['terlaksana']]);
        });
    });
}

printf(
    "  Baris yang %s: pertemuan=%d dosen=%d anggota=%d presensi=%d jurnal=%d nilai=%d rekap=%d\n",
    $apply ? 'ditulis' : 'akan ditulis',
    $hitung['pertemuan'], $hitung['dosen'], $hitung['anggota'],
    $hitung['presensi'], $hitung['jurnal'], $hitung['nilai'], $hitung['rekap']
);

// -------------------------------------------------------------------------
// 4. Bobot DPNA
// -------------------------------------------------------------------------

echo PHP_EOL.'== 4. Bobot sumber DPNA =='.PHP_EOL;

$bobotPerAturan = [];

foreach ($aturanList as $aturan) {
    $kode = strtoupper((string) $aturan->jenis_kegiatan?->kode);
    $bobot = $konfigurasi['bobot_kegiatan'][$kode] ?? 0;
    $bobotPerAturan[$aturan->id] = $bobot;

    printf(
        "  %-16s %s\n",
        $aturan->jenis_kegiatan?->nama,
        $bobot > 0 ? 'aktif '.$bobot.'%' : 'nonaktif 0%'
    );
}

printf(
    "  %-16s %s\n  Total aktif: %d%%\n",
    'Kehadiran',
    $konfigurasi['bobot_kehadiran'] > 0 ? 'aktif '.$konfigurasi['bobot_kehadiran'].'%' : 'nonaktif 0%',
    $konfigurasi['bobot_kehadiran'] + array_sum($bobotPerAturan)
);

$tulis(function () use ($blok, $konfigurasi, $bobotPerAturan) {
    DB::transaction(function () use ($blok, $konfigurasi, $bobotPerAturan) {
        Blok::whereKey($blok->id)->update([
            'kehadiran_masuk_dpna' => $konfigurasi['bobot_kehadiran'] > 0,
            'bobot_kehadiran_dpna' => $konfigurasi['bobot_kehadiran'],
        ]);

        foreach ($bobotPerAturan as $aturanId => $bobot) {
            AturanKegiatanBlok::whereKey($aturanId)->update([
                'nilai_masuk_dpna' => $bobot > 0,
                'bobot_nilai_dpna' => $bobot,
            ]);
        }
    });
});

// -------------------------------------------------------------------------
// 5. Hasil DPNA
// -------------------------------------------------------------------------

echo PHP_EOL.'== 5. Matriks DPNA =='.PHP_EOL;

$rekap = app(PerhitunganDpnaBlok::class)->rekap($blok->fresh());
$kegiatanDpna = $rekap['kegiatan'];

$judul = [str_pad('NIM', 11), str_pad('Mahasiswa', 22), str_pad('Hadir', 7)];
foreach ($kegiatanDpna as $a) {
    $judul[] = str_pad(substr((string) $a->jenis_kegiatan?->nama, 0, 12), 12);
}
$judul[] = 'AKHIR';
echo '  '.implode(' ', $judul).PHP_EOL;

$lengkap = 0;

foreach ($rekap['baris'] as $row) {
    $kolom = [
        str_pad((string) $row['peserta']->mahasiswa?->nim, 11),
        str_pad(substr((string) $row['peserta']->mahasiswa?->nama, 0, 22), 22),
        str_pad($row['kehadiran'] === null ? '-' : number_format($row['kehadiran'], 2), 7),
    ];

    foreach ($kegiatanDpna as $a) {
        $n = $row['nilai_kegiatan'][$a->id] ?? null;
        $kolom[] = str_pad($n === null ? '-' : number_format($n, 2), 12);
    }

    $kolom[] = $row['nilai_akhir'] === null ? 'BELUM LENGKAP' : number_format($row['nilai_akhir'], 2);
    $lengkap += $row['nilai_akhir'] === null ? 0 : 1;

    echo '  '.implode(' ', $kolom).PHP_EOL;
}

echo PHP_EOL.'Nilai akhir DPNA terhitung untuk '.$lengkap.' dari '.$rekap['baris']->count().' peserta.'.PHP_EOL;

if (! $apply) {
    echo PHP_EOL.'Tidak ada perubahan yang ditulis. Jalankan ulang dengan --apply bila rencana di atas sudah sesuai.'.PHP_EOL;
}
