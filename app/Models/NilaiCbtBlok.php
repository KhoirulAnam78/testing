<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NilaiCbtBlok extends Model
{
    protected $table = 'nilai_cbt_blok';

    protected $primaryKey = 'id_nilai_cbt_blok';

    protected $guarded = ['id_nilai_cbt_blok'];

    protected function casts(): array
    {
        return [
            'nilai_ujian_pertama' => 'decimal:2',
            'mengikuti_remedial' => 'boolean',
            'nilai_remedial' => 'decimal:2',
            'bobot_ujian_pertama' => 'decimal:2',
            'bobot_remedial' => 'decimal:2',
            'nilai_akhir' => 'decimal:2',
            'disinkronkan_pada' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (NilaiCbtBlok $nilai): void {
            if (! $nilai->mengikuti_remedial) {
                $nilai->nilai_remedial = null;
                $nilai->bobot_ujian_pertama = 100;
                $nilai->bobot_remedial = 0;
            }

            $nilai->nilai_akhir = self::hitungNilaiAkhir(
                $nilai->nilai_ujian_pertama === null ? null : (float) $nilai->nilai_ujian_pertama,
                (bool) $nilai->mengikuti_remedial,
                $nilai->nilai_remedial === null ? null : (float) $nilai->nilai_remedial,
                (float) $nilai->bobot_ujian_pertama,
                (float) $nilai->bobot_remedial,
            );
        });
    }

    public static function hitungNilaiAkhir(
        ?float $ujianPertama,
        bool $mengikutiRemedial,
        ?float $remedial,
        float $bobotPertama,
        float $bobotRemedial,
    ): ?float {
        if ($ujianPertama === null || $ujianPertama < 0 || $ujianPertama > 100) {
            return null;
        }

        if (! $mengikutiRemedial) {
            return round($ujianPertama, 2);
        }

        if (
            $remedial === null
            || $ujianPertama < 0
            || $ujianPertama > 100
            || $remedial < 0
            || $remedial > 100
            || $bobotPertama < 0
            || $bobotRemedial < 0
            || abs(($bobotPertama + $bobotRemedial) - 100) > .001
        ) {
            return null;
        }

        return round(($ujianPertama * $bobotPertama + $remedial * $bobotRemedial) / 100, 2);
    }

    public function aturan_kegiatan_blok(): BelongsTo
    {
        return $this->belongsTo(AturanKegiatanBlok::class, 'aturan_kegiatan_blok_id');
    }

    public function peserta_blok(): BelongsTo
    {
        return $this->belongsTo(PesertaBlok::class, 'peserta_blok_id', 'id_peserta_blok');
    }
}
