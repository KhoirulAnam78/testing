<?php

/**
 * Inspeksi kondisi data satu blok. Read-only, tidak menulis apa pun.
 *
 * Jalankan: php cek_blok.php [kode-atau-id-blok]
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AturanKegiatanBlok;
use App\Models\Blok;
use App\Models\KomponenPenilaian;
use App\Models\KomponenPenilaianBlok;
use App\Models\MonitoringPertemuanBlok;
use App\Models\NilaiPertemuanBlok;
use App\Models\PertemuanBlok;
use App\Models\PresensiPertemuanBlok;
use App\Models\RekapNilaiPertemuanBlok;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function baris(string $judul): void
{
    echo PHP_EOL.'== '.$judul.' =='.PHP_EOL;
}

baris('SKEMA PENTING');
echo 'aturan_kegiatan_blok kolom: '.implode(', ', Schema::getColumnListing('aturan_kegiatan_blok')).PHP_EOL;
echo 'blok kolom: '.implode(', ', Schema::getColumnListing('blok')).PHP_EOL;
echo 'komponen_penilaian kolom: '.implode(', ', Schema::getColumnListing('komponen_penilaian')).PHP_EOL;
echo 'jenis_kegiatan kolom: '.implode(', ', Schema::getColumnListing('jenis_kegiatan')).PHP_EOL;
echo 'ada tabel komponen_penilaian_kegiatan: '.(Schema::hasTable('komponen_penilaian_kegiatan') ? 'ya' : 'tidak').PHP_EOL;

baris('MIGRASI TERAKHIR');
foreach (DB::table('migrations')->orderByDesc('id')->limit(10)->get() as $m) {
    echo $m->batch.' | '.$m->migration.PHP_EOL;
}

baris('SEMUA BLOK');
foreach (Blok::withTrashed()->with(['prodi:id_prodi,nama', 'semester:id_semester,nama,tahun'])->orderBy('id')->get() as $b) {
    printf(
        "#%d | %s | %s | prodi=%s | smt=%s %s | %s s/d %s | status=%s | koor=%s/%s | dpna_hadir=%s bobot=%s | %s\n",
        $b->id,
        $b->kode,
        $b->nama,
        $b->prodi?->nama ?? '-',
        $b->semester?->nama ?? '-',
        $b->semester?->tahun ?? '-',
        $b->tanggal_mulai?->toDateString() ?? '-',
        $b->tanggal_selesai?->toDateString() ?? '-',
        $b->status,
        $b->koordinator_id ?? '-',
        $b->asisten_koordinator_id ?? '-',
        $b->kehadiran_masuk_dpna ? 'ya' : 'tidak',
        $b->bobot_kehadiran_dpna,
        $b->deleted_at ? 'TERHAPUS' : 'aktif'
    );
}

$arg = $argv[1] ?? null;
$blok = $arg
    ? Blok::where('id', $arg)->orWhere('kode', $arg)->first()
    : Blok::orderBy('id')->skip(2)->first();

if (! $blok) {
    echo PHP_EOL.'Blok target tidak ditemukan.'.PHP_EOL;
    exit(1);
}

baris('BLOK TARGET: #'.$blok->id.' '.$blok->kode.' - '.$blok->nama);

baris('JENIS KEGIATAN (master)');
foreach (DB::table('jenis_kegiatan')->orderBy('id')->get() as $j) {
    printf(
        "#%d | %s | %s | pertemuan_default=%s | durasi=%s | perlu_logbook=%s | pakai_cbt=%s | %s\n",
        $j->id,
        $j->kode,
        $j->nama,
        $j->jumlah_pertemuan_default,
        $j->durasi_menit_default,
        property_exists($j, 'perlu_logbook') ? $j->perlu_logbook : 'n/a',
        property_exists($j, 'pakai_cbt') ? $j->pakai_cbt : 'n/a',
        $j->deleted_at ? 'TERHAPUS' : 'aktif'
    );
}

baris('KOMPONEN PENILAIAN (master)');
foreach (KomponenPenilaian::withTrashed()->with('jenis_kegiatan:id,kode')->orderBy('jenis_kegiatan_id')->orderBy('urutan')->get() as $k) {
    printf(
        "#%d | jk=%s | %s | %s | %s-%s | urut=%d | %s%s\n",
        $k->id,
        $k->jenis_kegiatan?->kode ?? 'NULL',
        $k->kode,
        $k->nama,
        $k->nilai_min_default,
        $k->nilai_maks_default,
        $k->urutan,
        $k->status,
        $k->deleted_at ? ' TERHAPUS' : ''
    );
}

baris('ATURAN KEGIATAN BLOK TARGET');
$aturanList = AturanKegiatanBlok::where('blok_id', $blok->id)
    ->with('jenis_kegiatan:id,kode,nama')
    ->withCount(['materi_blok', 'kelompok_blok', 'pertemuan_blok', 'komponen_penilaian_blok'])
    ->orderBy('urutan')
    ->get();

foreach ($aturanList as $a) {
    printf(
        "#%d | %s | durasi=%s | per_kelompok=%s | kelompok=%s presensi=%s logbook=%s penilaian=%s | dpna=%s bobot=%s | materi=%d kelompok=%d pertemuan=%d komponen=%d\n",
        $a->id,
        $a->jenis_kegiatan?->nama,
        $a->durasi_menit,
        $a->jumlah_mahasiswa_per_kelompok ?? '-',
        $a->perlu_kelompok ? 'y' : 'n',
        $a->perlu_presensi ? 'y' : 'n',
        $a->perlu_logbook ? 'y' : 'n',
        $a->perlu_penilaian ? 'y' : 'n',
        $a->nilai_masuk_dpna ? 'y' : 'n',
        $a->bobot_nilai_dpna,
        $a->materi_blok_count,
        $a->kelompok_blok_count,
        $a->pertemuan_blok_count,
        $a->komponen_penilaian_blok_count
    );

    foreach (KomponenPenilaianBlok::withTrashed()->where('aturan_kegiatan_blok_id', $a->id)->with('komponen_penilaian:id,nama')->orderBy('urutan')->get() as $kb) {
        printf(
            "    rubrik #%d | %s | %s-%s | urut=%d%s\n",
            $kb->id,
            $kb->komponen_penilaian?->nama,
            $kb->nilai_min,
            $kb->nilai_maks,
            $kb->urutan,
            $kb->deleted_at ? ' TERHAPUS' : ''
        );
    }

    $materi = DB::table('materi_blok')->where('aturan_kegiatan_blok_id', $a->id)->whereNull('deleted_at')->orderBy('urutan')->get();
    foreach ($materi as $m) {
        printf("    materi #%d | %s (urut %d)\n", $m->id_materi_blok, $m->judul, $m->urutan);
        $rinci = DB::table('materi_rinci_blok')->where('materi_blok_id', $m->id_materi_blok)->whereNull('deleted_at')->orderBy('urutan')->get();
        foreach ($rinci as $r) {
            printf(
                "        rinci #%d | P%s | %s | rencana=%s %s-%s | sesi=%s durasi=%s\n",
                $r->id_materi_rinci_blok,
                $r->pertemuan_ke ?? '-',
                $r->judul,
                $r->tanggal_rencana ?? '-',
                $r->jam_mulai_rencana ?? '-',
                $r->jam_selesai_rencana ?? '-',
                $r->jumlah_sesi ?? '-',
                $r->durasi_menit_per_sesi ?? '-'
            );
        }
    }

    foreach (DB::table('kelompok_blok')->where('aturan_kegiatan_blok_id', $a->id)->whereNull('deleted_at')->orderBy('kode')->get() as $kel) {
        $jml = DB::table('anggota_kelompok_blok')->where('kelompok_blok_id', $kel->id_kelompok_blok)->count();
        printf("    kelompok #%d | %s - %s | kapasitas=%s | status=%s | anggota=%d\n", $kel->id_kelompok_blok, $kel->kode, $kel->nama, $kel->kapasitas ?? '-', $kel->status, $jml);
    }
}

baris('PESERTA BLOK TARGET');
$peserta = DB::table('peserta_blok')
    ->join('mahasiswa', 'mahasiswa.id_mahasiswa', '=', 'peserta_blok.mahasiswa_id')
    ->where('peserta_blok.blok_id', $blok->id)
    ->whereNull('peserta_blok.deleted_at')
    ->orderBy('mahasiswa.nama')
    ->get(['peserta_blok.id_peserta_blok', 'peserta_blok.status', 'peserta_blok.kelas_id', 'mahasiswa.nim', 'mahasiswa.nama']);
echo 'total peserta: '.$peserta->count().PHP_EOL;
foreach ($peserta as $p) {
    printf("  #%d | %s | %s | %s | kelas=%s\n", $p->id_peserta_blok, $p->nim, $p->nama, $p->status, $p->kelas_id ?? '-');
}

baris('PERTEMUAN BLOK TARGET');
$pertemuan = PertemuanBlok::where('blok_id', $blok->id)
    ->with([
        'aturan_kegiatan_blok.jenis_kegiatan:id,kode',
        'kelompok_blok:id_kelompok_blok,kode',
        'materi_rinci_blok:id_materi_rinci_blok,judul,pertemuan_ke',
        'dosen_pertemuan_blok.dosen:id_dosen,nama',
        'monitoring_pertemuan_blok',
    ])
    ->withCount(['presensi_pertemuan_blok', 'nilai_pertemuan_blok'])
    ->orderBy('aturan_kegiatan_blok_id')
    ->orderBy('kelompok_blok_id')
    ->orderBy('tanggal')
    ->get();
echo 'total pertemuan: '.$pertemuan->count().PHP_EOL;
foreach ($pertemuan as $p) {
    printf(
        "  #%d | %s | %s | P%s %s | %s %s-%s | %s | status=%s | dosen=[%s] | presensi=%d nilai=%d | jurnal=%s%s\n",
        $p->id_pertemuan_blok,
        $p->aturan_kegiatan_blok?->jenis_kegiatan?->kode,
        $p->kelompok_blok?->kode,
        $p->materi_rinci_blok?->pertemuan_ke ?? '-',
        $p->materi_rinci_blok?->judul,
        $p->tanggal?->toDateString() ?? 'BELUM',
        substr((string) $p->jam_mulai, 0, 5) ?: '-',
        substr((string) $p->jam_selesai, 0, 5) ?: '-',
        $p->ruangan ?? '-',
        $p->status,
        $p->dosen_pertemuan_blok->pluck('dosen.nama')->filter()->join('; '),
        $p->presensi_pertemuan_blok_count,
        $p->nilai_pertemuan_blok_count,
        $p->monitoring_pertemuan_blok ? 'ada' : 'belum',
        $p->monitoring_pertemuan_blok?->divalidasi_pada ? ' TERVALIDASI' : ''
    );
}

baris('RINGKASAN PELAKSANAAN BLOK TARGET');
$pertemuanIds = $pertemuan->pluck('id_pertemuan_blok');
printf("presensi rows: %d\n", $pertemuanIds->isEmpty() ? 0 : PresensiPertemuanBlok::whereIn('pertemuan_blok_id', $pertemuanIds)->count());
printf("monitoring rows: %d (tervalidasi %d)\n",
    $pertemuanIds->isEmpty() ? 0 : MonitoringPertemuanBlok::whereIn('pertemuan_blok_id', $pertemuanIds)->count(),
    $pertemuanIds->isEmpty() ? 0 : MonitoringPertemuanBlok::whereIn('pertemuan_blok_id', $pertemuanIds)->whereNotNull('divalidasi_pada')->count());
printf("nilai rows: %d\n", $pertemuanIds->isEmpty() ? 0 : NilaiPertemuanBlok::whereIn('pertemuan_blok_id', $pertemuanIds)->count());
printf("rekap nilai rows: %d\n", $pertemuanIds->isEmpty() ? 0 : RekapNilaiPertemuanBlok::whereIn('pertemuan_blok_id', $pertemuanIds)->count());
printf("lampiran rows: %d\n", DB::table('lampiran_materi_blok')->where('blok_id', $blok->id)->count());

baris('DOSEN AKTIF (20 pertama)');
foreach (DB::table('dosen')->whereNull('deleted_at')->where('status', 'aktif')->orderBy('nama')->limit(20)->get() as $d) {
    printf("  #%d | %s | %s | prodi=%s | user=%s\n", $d->id_dosen, $d->nidn ?? '-', $d->nama, $d->prodi_id ?? '-', $d->user_id ?? '-');
}

baris('SELESAI');
