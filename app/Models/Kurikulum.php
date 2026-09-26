<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Kurikulum extends Model
{
    use SoftDeletes;

    protected $table = 'kurikulum';

    protected $primaryKey = 'id_kurikulum';

    protected $guarded = ['id_kurikulum'];

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class, 'prodi_id', 'id_prodi');
    }

    public function skala_nilai(): BelongsTo
    {
        return $this->belongsTo(SkalaNilai::class, 'skala_nilai_id', 'id_skala_nilai');
    }

    public function mata_kuliah(): HasMany
    {
        return $this->hasMany(KurikulumMataKuliah::class, 'kurikulum_id', 'id_kurikulum');
    }
}
