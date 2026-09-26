<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class KurikulumMataKuliah extends Model
{
    use SoftDeletes;

    protected $table = 'kurikulum_mata_kuliah';

    protected $primaryKey = 'id_kurikulum_mata_kuliah';

    protected $guarded = ['id_kurikulum_mata_kuliah'];

    protected function casts(): array
    {
        return ['apakah_wajib' => 'boolean'];
    }

    public function kurikulum(): BelongsTo
    {
        return $this->belongsTo(Kurikulum::class, 'kurikulum_id', 'id_kurikulum');
    }

    public function mata_kuliah(): BelongsTo
    {
        return $this->belongsTo(MataKuliah::class, 'mata_kuliah_id');
    }

    public function aturan(): HasOne
    {
        return $this->hasOne(AturanKurikulumMataKuliah::class, 'kurikulum_mata_kuliah_id', 'id_kurikulum_mata_kuliah');
    }

    public function prasyarat(): HasMany
    {
        return $this->hasMany(PrasyaratMataKuliah::class, 'kurikulum_mata_kuliah_id', 'id_kurikulum_mata_kuliah');
    }
}
