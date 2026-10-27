<?php

namespace App\Support;

use App\Models\AnggotaKelasKuliah;
use App\Models\Blok;
use App\Models\KelasKuliah;
use App\Models\Mahasiswa;
use App\Models\PesertaBlok;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PembagianKelasKuliah
{
    public static function kodeOtomatis(int $urutan): string
    {
        if ($urutan < 1 || $urutan > 26) {
            throw new \OutOfRangeException('Jumlah kelas otomatis wajib antara 1 dan 26.');
        }

        return chr(64 + $urutan);
    }

    /**
     * @param  array<int, int>  $pesertaIds
     * @param  array<int, array{id: int, kode: string, kapasitas: int|null}>  $kelas
     * @return array<int, int> peserta_blok_id => kelas_kuliah_id
     */
    public static function rencana(array $pesertaIds, array $kelas): array
    {
        $pesertaIds = array_values(array_unique(array_map('intval', $pesertaIds)));
        sort($pesertaIds, SORT_NUMERIC);
        usort($kelas, fn (array $a, array $b) => [$a['kode'], $a['id']] <=> [$b['kode'], $b['id']]);

        $jumlah = array_fill_keys(array_column($kelas, 'id'), 0);
        $hasil = [];

        foreach ($pesertaIds as $pesertaId) {
            $tersedia = array_values(array_filter($kelas, fn (array $item) => $item['kapasitas'] === null || $jumlah[$item['id']] < $item['kapasitas']
            ));

            if ($tersedia === []) {
                throw new \OverflowException('Kapasitas kelas kuliah tidak mencukupi.');
            }

            usort($tersedia, fn (array $a, array $b) => [$jumlah[$a['id']], $a['kode'], $a['id']] <=> [$jumlah[$b['id']], $b['kode'], $b['id']]
            );

            $kelasId = $tersedia[0]['id'];
            $hasil[$pesertaId] = $kelasId;
            $jumlah[$kelasId]++;
        }

        return $hasil;
    }

    public function bagi(Blok $blok): int
    {
        return DB::transaction(function () use ($blok): int {
            $blok = $this->kunciBlokSejenis($blok);

            return $this->bagiTerkunci($blok);
        });
    }

    /** @param array{kode: string, nama: ?string, kapasitas: ?int} $data */
    public function buatManual(Blok $blok, array $data): KelasKuliah
    {
        return DB::transaction(function () use ($blok, $data): KelasKuliah {
            $blok = $this->kunciBlokSejenis($blok);
            $this->pastikanDikelolaLokal($blok);

            if (KelasKuliah::withTrashed()->where('blok_id', $blok->id)->where('kode', $data['kode'])->exists()) {
                throw ValidationException::withMessages(['kelas_baru_kode' => 'Kode kelas sudah pernah dipakai pada blok ini.']);
            }

            return KelasKuliah::query()->create([
                'blok_id' => $blok->id,
                'semester_id' => $blok->semester_id,
                'mata_kuliah_id' => $blok->mata_kuliah_id,
                'kode' => $data['kode'],
                'nama' => $data['nama'],
                'kapasitas' => $data['kapasitas'],
                'status' => 'aktif',
            ]);
        });
    }

    public function buatOtomatisDanBagi(Blok $blok, int $jumlahKelas): int
    {
        return DB::transaction(function () use ($blok, $jumlahKelas): int {
            $blok = $this->kunciBlokSejenis($blok);
            $this->pastikanDikelolaLokal($blok);

            try {
                self::kodeOtomatis($jumlahKelas);
            } catch (\OutOfRangeException $exception) {
                throw ValidationException::withMessages(['jumlah_kelas' => $exception->getMessage()]);
            }

            if (KelasKuliah::withTrashed()->where('blok_id', $blok->id)->exists()) {
                throw ValidationException::withMessages([
                    'jumlah_kelas' => 'Pembuatan otomatis hanya dapat dilakukan saat blok belum memiliki riwayat kelas.',
                ]);
            }

            $jumlahPeserta = PesertaBlok::query()
                ->where('blok_id', $blok->id)
                ->whereIn('status', ['aktif', 'mengulang'])
                ->lockForUpdate()
                ->count();
            $kapasitas = $jumlahPeserta === 0 ? null : (int) ceil($jumlahPeserta / $jumlahKelas);

            foreach (range(1, $jumlahKelas) as $urutan) {
                $kode = self::kodeOtomatis($urutan);
                KelasKuliah::query()->create([
                    'blok_id' => $blok->id,
                    'semester_id' => $blok->semester_id,
                    'mata_kuliah_id' => $blok->mata_kuliah_id,
                    'kode' => $kode,
                    'nama' => 'Kelas '.$kode,
                    'kapasitas' => $kapasitas,
                    'status' => 'aktif',
                ]);
            }

            return $this->bagiTerkunci($blok);
        });
    }

    private function bagiTerkunci(Blok $blok): int
    {
        $this->pastikanDikelolaLokal($blok);

        if (! $blok->mata_kuliah()->where('prodi_id', $blok->prodi_id)->exists()) {
            throw ValidationException::withMessages([
                'pembagian' => 'Program studi mata kuliah tidak sesuai dengan program studi blok.',
            ]);
        }

            $semuaKelas = KelasKuliah::query()
                ->where('semester_id', $blok->semester_id)
                ->where('mata_kuliah_id', $blok->mata_kuliah_id)
                ->lockForUpdate()
                ->orderBy('id_kelas_kuliah')
                ->get(['id_kelas_kuliah', 'kode', 'kapasitas', 'status']);
            $kelas = KelasKuliah::query()
                ->where('blok_id', $blok->id)
                ->where('semester_id', $blok->semester_id)
                ->where('mata_kuliah_id', $blok->mata_kuliah_id)
                ->where('status', 'aktif')
                ->orderBy('kode')
                ->get(['id_kelas_kuliah', 'kode', 'kapasitas']);

            if ($kelas->isEmpty()) {
                throw ValidationException::withMessages([
                    'pembagian' => 'Belum ada kelas aktif pada blok ini.',
                ]);
            }

            $pesertaIds = PesertaBlok::query()
                ->where('blok_id', $blok->id)
                ->whereIn('status', ['aktif', 'mengulang'])
                ->lockForUpdate()
                ->orderBy('id_peserta_blok')
                ->pluck('mahasiswa_id', 'id_peserta_blok');

            try {
                $rencana = self::rencana($pesertaIds->keys()->map(fn ($id) => (int) $id)->all(), $kelas->map(fn (KelasKuliah $item) => [
                    'id' => (int) $item->id_kelas_kuliah,
                    'kode' => $item->kode,
                    'kapasitas' => $item->kapasitas,
                ])->all());
            } catch (\OverflowException $e) {
                throw ValidationException::withMessages(['pembagian' => $e->getMessage()]);
            }

            $konflikAntarblok = AnggotaKelasKuliah::query()
                ->whereIn('mahasiswa_id', $pesertaIds->values())
                ->whereHas('kelas_kuliah', fn ($query) => $query
                    ->where('semester_id', $blok->semester_id)
                    ->where('mata_kuliah_id', $blok->mata_kuliah_id)
                    ->whereNotNull('blok_id')
                    ->where('blok_id', '!=', $blok->id))
                ->exists();

            if ($konflikAntarblok) {
                throw ValidationException::withMessages([
                    'pembagian' => 'Pembagian ditolak karena peserta sudah berada pada kelas di blok lain.',
                ]);
            }

            AnggotaKelasKuliah::query()
                ->whereIn('kelas_kuliah_id', $kelas->pluck('id_kelas_kuliah'))
                ->delete();
            AnggotaKelasKuliah::query()
                ->whereHas('kelas_kuliah', fn ($query) => $query
                    ->where('semester_id', $blok->semester_id)
                    ->where('mata_kuliah_id', $blok->mata_kuliah_id))
                ->whereIn('mahasiswa_id', $pesertaIds->values())
                ->delete();

            $sekarang = now();
            if ($rencana !== []) {
                $nim = Mahasiswa::query()->whereIn('id_mahasiswa', $pesertaIds->values())->pluck('nim', 'id_mahasiswa');
                AnggotaKelasKuliah::query()->insert(array_map(fn (int $kelasId, int $pesertaId) => [
                    'kelas_kuliah_id' => $kelasId,
                    'mahasiswa_id' => $pesertaIds[$pesertaId],
                    'nim_sumber' => strtoupper(trim($nim[$pesertaIds[$pesertaId]])),
                    'peserta_blok_id' => $pesertaId,
                    'sumber' => 'lokal',
                    'created_at' => $sekarang,
                    'updated_at' => $sekarang,
                ], $rencana, array_keys($rencana)));
            }

        return count($rencana);
    }

    private function kunciBlokSejenis(Blok $blok): Blok
    {
        $blokTerkunci = Blok::query()
            ->where('semester_id', $blok->semester_id)
            ->where('mata_kuliah_id', $blok->mata_kuliah_id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->firstWhere('id', $blok->id);

        abort_if($blokTerkunci === null, 404);

        return $blokTerkunci;
    }

    private function pastikanDikelolaLokal(Blok $blok): void
    {
        if (! $blok->semester()->firstOrFail()->dikelolaLokal()) {
            throw ValidationException::withMessages([
                'pembagian' => 'Pembuatan dan pembagian kelas lokal hanya diizinkan mulai semester 20262.',
            ]);
        }
    }
}
