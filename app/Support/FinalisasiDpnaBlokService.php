<?php

namespace App\Support;

use App\Models\Blok;
use App\Models\FinalisasiDpnaBlok;
use App\Models\SnapshotDpnaPeserta;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class FinalisasiDpnaBlokService
{
    public function finalisasi(Blok $blok, User $user): FinalisasiDpnaBlok
    {
        abort_unless($user->can('dpna-blok:finalisasi') && $blok->dapatDikelolaOleh($user), 403);

        return DB::transaction(function () use ($blok, $user) {
            $blok = Blok::query()->lockForUpdate()->findOrFail($blok->id);
            $terakhir = FinalisasiDpnaBlok::query()
                ->where('blok_id', $blok->id)
                ->lockForUpdate()
                ->orderByDesc('versi')
                ->first();

            if ($terakhir?->status === 'final') {
                throw ValidationException::withMessages(['finalisasi' => 'DPNA sudah final. Buka kembali sebelum finalisasi ulang.']);
            }

            $data = app(PerhitunganDpnaBlok::class)->rekap($blok->fresh());
            $tidakLengkap = $data['baris']->filter(fn ($row) => $row['nilai_akhir'] === null);
            if ($data['baris']->isEmpty() || $tidakLengkap->isNotEmpty()) {
                throw ValidationException::withMessages(['finalisasi' => 'Semua peserta aktif wajib memiliki DPNA lengkap sebelum finalisasi.']);
            }

            $kegiatanAktif = $data['kegiatan']
                ->where('nilai_masuk_dpna', true)
                ->whereNotIn('id', $data['anggota_grup_ids']);
            $sumber = $kegiatanAktif->concat($data['grup']);
            if ($data['menggunakan_grup'] && $data['grup']->contains(fn ($item) => $item->anggota_grup_dpna_blok->isEmpty())) {
                throw ValidationException::withMessages(['finalisasi' => 'Setiap grup DPNA wajib memiliki minimal satu kegiatan.']);
            }
            $anggotaGrup = $data['grup']->flatMap(fn ($item) => $item->anggota_grup_dpna_blok->pluck('aturan_kegiatan_blok_id'));
            if ($data['menggunakan_grup'] && $anggotaGrup->duplicates()->isNotEmpty()) {
                throw ValidationException::withMessages(['finalisasi' => 'Satu kegiatan hanya boleh menjadi anggota satu grup DPNA.']);
            }
            if ($sumber->isEmpty() && ! $blok->kehadiran_masuk_dpna) {
                throw ValidationException::withMessages(['finalisasi' => 'DPNA wajib memiliki minimal satu sumber aktif.']);
            }

            $bobotTidakValid = $data['grup']->contains(fn ($item) => (float) $item->bobot <= 0)
                || $kegiatanAktif->contains(fn ($item) => (float) $item->bobot_nilai_dpna <= 0);
            if ($bobotTidakValid || ($blok->kehadiran_masuk_dpna && (float) $blok->bobot_kehadiran_dpna <= 0)) {
                throw ValidationException::withMessages(['finalisasi' => 'Setiap sumber DPNA aktif wajib memiliki bobot lebih dari 0.']);
            }

            $totalBobot = ($blok->kehadiran_masuk_dpna ? (float) $blok->bobot_kehadiran_dpna : 0)
                + $data['grup']->sum('bobot')
                + $kegiatanAktif->sum('bobot_nilai_dpna');
            if (abs($totalBobot - 100) > .001) {
                throw ValidationException::withMessages(['finalisasi' => 'Total bobot sumber DPNA wajib tepat 100%.']);
            }

            $finalisasi = FinalisasiDpnaBlok::create([
                'blok_id' => $blok->id,
                'versi' => ($terakhir?->versi ?? 0) + 1,
                'status' => 'final',
                'konfigurasi_json' => [
                    'kehadiran' => ['aktif' => (bool) $blok->kehadiran_masuk_dpna, 'bobot' => (float) $blok->bobot_kehadiran_dpna],
                    'menggunakan_grup' => $data['menggunakan_grup'],
                    'grup' => $data['grup']->map(fn ($item) => [
                        'id' => $item->id_grup_dpna_blok,
                        'nama' => $item->nama,
                        'bobot' => (float) $item->bobot,
                        'aturan_ids' => $item->anggota_grup_dpna_blok
                            ->pluck('aturan_kegiatan_blok_id')
                            ->map(fn ($id) => (int) $id)
                            ->values()
                            ->all(),
                    ])->values()->all(),
                    'kegiatan' => $data['kegiatan']->map(fn ($item) => [
                        'id' => $item->id,
                        'nama' => $item->jenis_kegiatan?->nama,
                        'aktif' => ! $data['anggota_grup_ids']->contains($item->id) && (bool) $item->nilai_masuk_dpna,
                        'bobot' => (float) $item->bobot_nilai_dpna,
                    ])->values()->all(),
                ],
                'difinalisasi_oleh_user_id' => $user->id,
                'difinalisasi_pada' => now(),
            ]);

            foreach ($data['baris'] as $row) {
                SnapshotDpnaPeserta::create([
                    'finalisasi_dpna_blok_id' => $finalisasi->id_finalisasi_dpna_blok,
                    'peserta_blok_id' => $row['peserta']->id_peserta_blok,
                    'nim' => $row['peserta']->mahasiswa->nim,
                    'nama_mahasiswa' => $row['peserta']->mahasiswa->nama,
                    'nilai_akhir' => $row['nilai_akhir'],
                    'sumber_json' => $row['sumber_detail'],
                ]);
            }

            return $finalisasi;
        });
    }

    public function bukaKembali(Blok $blok, User $user, string $alasan): FinalisasiDpnaBlok
    {
        abort_unless($user->can('dpna-blok:finalisasi') && $blok->dapatDikelolaOleh($user), 403);

        $alasan = trim($alasan);
        if ($alasan === '') {
            throw ValidationException::withMessages(['alasanBuka' => 'Alasan buka kembali wajib diisi.']);
        }

        return DB::transaction(function () use ($alasan, $blok, $user) {
            $blok = Blok::query()->lockForUpdate()->findOrFail($blok->id);
            $finalisasi = FinalisasiDpnaBlok::query()
                ->where('blok_id', $blok->id)
                ->where('status', 'final')
                ->lockForUpdate()
                ->orderByDesc('versi')
                ->firstOrFail();

            $finalisasi->update([
                'status' => 'dibuka_kembali',
                'dibuka_oleh_user_id' => $user->id,
                'dibuka_pada' => now(),
                'alasan_buka' => $alasan,
            ]);

            return $finalisasi;
        });
    }
}
