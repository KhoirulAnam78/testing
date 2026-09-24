<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SkalaNilaiDetail extends Model
{
    protected $table = 'skala_nilai_detail';

    protected $primaryKey = 'id_skala_nilai_detail';

    protected $guarded = ['id_skala_nilai_detail'];

    protected function casts(): array
    {
        return [
            'nilai_angka_min' => 'decimal:2',
            'nilai_angka_max' => 'decimal:2',
            'nilai_indeks' => 'decimal:2',
            'lulus' => 'boolean',
            'boleh_perbaikan' => 'boolean',
        ];
    }

    public function skala_nilai(): BelongsTo
    {
        return $this->belongsTo(SkalaNilai::class, 'skala_nilai_id', 'id_skala_nilai');
    }

    public function prasyarat_mata_kuliah(): HasMany
    {
        return $this->hasMany(PrasyaratMataKuliah::class, 'skala_nilai_detail_id', 'id_skala_nilai_detail');
    }
}
