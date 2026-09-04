<?php

namespace App\Support;

use App\Models\MateriRinciBlok;
use App\Models\MonitoringPertemuanBlok;
use App\Models\PertemuanBlok;
use App\Models\User;

/**
 * Aturan akses seputar satu pertemuan blok, dikumpulkan di satu tempat.
 *
 * Dipakai tab Pertemuan dan tab Monitoring (pengelola), halaman Pertemuan Saya
 * (dosen), dan halaman Materi & Modul (mahasiswa). Parameter komponen Livewire
 * dikendalikan klien, jadi setiap aksi tulis wajib memeriksa ulang di sini dan tidak
 * boleh mengandalkan query yang sudah ter-scope saat render.
 */
class AksesPertemuanBlok
{
    /**
     * Izin pengelola operasional blok. Dipakai apa adanya karena alias middleware
     * Spatie tidak diregistrasi di `bootstrap/app.php`, sehingga penjagaan akses
     * dilakukan di dalam komponen seperti halaman blok-operasional lainnya.
     */
    public const IZIN_PENGELOLA = 'blok-operasional:';

    public static function pengelola(?User $user): bool
    {
        return (bool) $user?->can(self::IZIN_PENGELOLA);
    }

    /**
     * Lampiran default berlaku untuk semua kelompok, jadi hanya pengelola yang boleh
     * mengubahnya. Dosen satu kelompok tidak boleh menimpa materi kelompok lain.
     */
    public static function bolehKelolaLampiranDefault(?User $user, int $materiRinciId): bool
    {
        if (self::pengelola($user)) {
            return true;
        }

        $dosenId = $user?->dosen?->id_dosen;

        return $dosenId !== null && MateriRinciBlok::query()
            ->whereKey($materiRinciId)
            ->whereHas(
                'materi_blok.aturan_kegiatan_blok.blok.pengelola_blok',
                fn ($query) => $query->where('dosen_id', $dosenId)
            )
            ->exists();
    }

    public static function bolehKelolaPertemuan(?User $user, int $pertemuanId): bool
    {
        return self::pengelola($user)
            || self::pengelolaBlok($user, $pertemuanId)
            || self::dosenPengampu($user, $pertemuanId);
    }

    public static function bolehLihatPertemuan(?User $user, int $pertemuanId): bool
    {
        return self::bolehKelolaPertemuan($user, $pertemuanId)
            || self::mahasiswaAnggota($user, $pertemuanId);
    }

    public static function logbookAktif(int $pertemuanId): bool
    {
        return PertemuanBlok::query()
            ->whereKey($pertemuanId)
            ->whereHas('aturan_kegiatan_blok', fn ($query) => $query->where('perlu_logbook', true))
            ->exists();
    }

    public static function bolehUnggahLogbook(?User $user, int $pertemuanId, int $mahasiswaId): bool
    {
        return (int) ($user?->mahasiswa?->id_mahasiswa ?? 0) === $mahasiswaId
            && self::logbookAktif($pertemuanId)
            && self::terkunci($pertemuanId)
            && self::mahasiswaAnggota($user, $pertemuanId);
    }

    public static function bolehValidasiLogbook(?User $user, int $pertemuanId): bool
    {
        if (! self::logbookAktif($pertemuanId)) {
            return false;
        }

        if (self::bolehKelolaPertemuan($user, $pertemuanId)) {
            return true;
        }

        return self::pengelolaBlok($user, $pertemuanId);
    }

    public static function bolehUnduhLogbook(?User $user, int $pertemuanId, int $mahasiswaId): bool
    {
        return self::logbookAktif($pertemuanId)
            && (self::bolehValidasiLogbook($user, $pertemuanId)
                || ((int) ($user?->mahasiswa?->id_mahasiswa ?? 0) === $mahasiswaId
                    && self::mahasiswaAnggota($user, $pertemuanId)));
    }

    /**
     * Jurnal yang sudah divalidasi mengunci presensi dan jurnal pertemuan itu.
     * Tidak ada peran yang boleh mengubah selama terkunci; pengelola harus membuka
     * validasinya lebih dulu supaya jejaknya jelas.
     */
    public static function terkunci(int $pertemuanId): bool
    {
        return MonitoringPertemuanBlok::query()
            ->where('pertemuan_blok_id', $pertemuanId)
            ->whereNotNull('divalidasi_pada')
            ->exists();
    }

    public static function bolehIsiPelaksanaan(?User $user, int $pertemuanId): bool
    {
        return self::bolehKelolaPertemuan($user, $pertemuanId) && ! self::terkunci($pertemuanId);
    }

    /**
     * Penilaian sengaja TIDAK ikut `terkunci()`.
     *
     * Validasi jurnal mengunci presensi dan jurnal karena keduanya adalah catatan
     * pelaksanaan yang sudah final. Nilai berbeda: dosen pengampu sering baru selesai
     * menilai setelah pertemuan divalidasi, dan koreksi nilai adalah pekerjaan normal.
     * Jadi nilai tetap boleh diisi dosen pengampu dan pengelola tanpa membuka validasi.
     */
    public static function bolehIsiNilai(?User $user, int $pertemuanId): bool
    {
        return self::bolehKelolaPertemuan($user, $pertemuanId);
    }

    public static function bolehBukaValidasi(?User $user, int $pertemuanId): bool
    {
        return self::pengelola($user) || self::pengelolaBlok($user, $pertemuanId);
    }

    /**
     * `dosen.user_id` nullable dan `Dosen` memakai soft delete, jadi user berrole
     * dosen bisa saja tidak punya baris dosen yang bisa dipakai.
     */
    private static function dosenPengampu(?User $user, int $pertemuanId): bool
    {
        $dosenId = $user?->dosen?->id_dosen;

        if (! $dosenId) {
            return false;
        }

        return PertemuanBlok::query()
            ->whereKey($pertemuanId)
            ->whereHas('dosen_pertemuan_blok', fn ($query) => $query->where('dosen_id', $dosenId))
            ->exists();
    }

    private static function pengelolaBlok(?User $user, int $pertemuanId): bool
    {
        $dosenId = $user?->dosen?->id_dosen;

        return $dosenId !== null && PertemuanBlok::query()
            ->whereKey($pertemuanId)
            ->whereHas('blok.pengelola_blok', fn ($query) => $query->where('dosen_id', $dosenId))
            ->exists();
    }

    /**
     * Mahasiswa hanya boleh melihat pertemuan kelompoknya sendiri, dan hanya selama
     * kepesertaannya di blok masih berjalan.
     */
    private static function mahasiswaAnggota(?User $user, int $pertemuanId): bool
    {
        $mahasiswaId = $user?->mahasiswa?->id_mahasiswa;

        if (! $mahasiswaId) {
            return false;
        }

        return PertemuanBlok::query()
            ->whereKey($pertemuanId)
            ->whereHas('kelompok_blok.anggota_kelompok_blok.peserta_blok', fn ($query) => $query
                ->where('mahasiswa_id', $mahasiswaId)
                ->whereIn('status', ['aktif', 'mengulang']))
            ->exists();
    }
}
