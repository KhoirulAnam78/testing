<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class AnggotaKelasKuliah extends Model
{
    protected $table = 'anggota_kelas_kuliah';

    protected $primaryKey = 'id_anggota_kelas_kuliah';

    protected $guarded = ['id_anggota_kelas_kuliah'];

    protected function casts(): array
    {
        return [
            'aktif_sumber' => 'boolean',
            'sumber_json' => 'array',
            'last_synced_at' => 'datetime',
            'missing_since' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $anggota): void {
            $kelasBlokId = KelasKuliah::query()->whereKey($anggota->kelas_kuliah_id)->value('blok_id');

            if ($anggota->peserta_blok_id === null) {
                return;
            }

            $peserta = PesertaBlok::query()->find($anggota->peserta_blok_id, ['blok_id', 'mahasiswa_id']);

            if (! self::kaitanPesertaValid(
                $kelasBlokId === null ? null : (int) $kelasBlokId,
                $peserta?->blok_id === null ? null : (int) $peserta->blok_id,
                (int) $anggota->mahasiswa_id,
                $peserta?->mahasiswa_id === null ? null : (int) $peserta->mahasiswa_id,
            )) {
                throw ValidationException::withMessages([
                    'peserta_blok_id' => 'Peserta blok wajib cocok dengan blok kelas dan mahasiswa.',
                ]);
            }
        });
    }

    public static function kaitanPesertaValid(?int $kelasBlokId, ?int $pesertaBlokId, int $mahasiswaId, ?int $pesertaMahasiswaId): bool
    {
        return $kelasBlokId !== null
            && $pesertaBlokId === $kelasBlokId
            && $pesertaMahasiswaId === $mahasiswaId;
    }

    public function kelas_kuliah(): BelongsTo
    {
        return $this->belongsTo(KelasKuliah::class, 'kelas_kuliah_id', 'id_kelas_kuliah');
    }

    public function peserta_blok(): BelongsTo
    {
        return $this->belongsTo(PesertaBlok::class, 'peserta_blok_id', 'id_peserta_blok');
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id', 'id_mahasiswa');
    }
}
