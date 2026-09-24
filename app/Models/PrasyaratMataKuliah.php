<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrasyaratMataKuliah extends Model
{
    protected $table = 'prasyarat_mata_kuliah';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function kurikulum_mata_kuliah(): BelongsTo
    {
        return $this->belongsTo(KurikulumMataKuliah::class, 'kurikulum_mata_kuliah_id', 'id_kurikulum_mata_kuliah');
    }

    public function mata_kuliah_prasyarat(): BelongsTo
    {
        return $this->belongsTo(KurikulumMataKuliah::class, 'prasyarat_kurikulum_mata_kuliah_id', 'id_kurikulum_mata_kuliah');
    }

    public function nilai_minimum(): BelongsTo
    {
        return $this->belongsTo(SkalaNilaiDetail::class, 'skala_nilai_detail_id', 'id_skala_nilai_detail');
    }
}
