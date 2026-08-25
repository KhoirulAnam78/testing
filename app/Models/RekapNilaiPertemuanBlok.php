<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RekapNilaiPertemuanBlok extends Model
{
    protected $table = 'rekap_nilai_pertemuan_blok';

    protected $primaryKey = 'id_rekap_nilai_pertemuan_blok';

    protected $guarded = ['id_rekap_nilai_pertemuan_blok'];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'nilai_akhir' => 'decimal:2',
        ];
    }

    public static function hitungNilaiAkhir(float $total, float $nilaiMaks): float
    {
        return $nilaiMaks > 0 ? ($total / $nilaiMaks) * 100 : 0;
    }

    public function pertemuan_blok(): BelongsTo
    {
        return $this->belongsTo(PertemuanBlok::class, 'pertemuan_blok_id', 'id_pertemuan_blok');
    }

    public function peserta_blok(): BelongsTo
    {
        return $this->belongsTo(PesertaBlok::class, 'peserta_blok_id', 'id_peserta_blok');
    }
}
