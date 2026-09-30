<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Kurikulum extends Model
{
    use SoftDeletes;

    public const STATUS_SYNC_PENDING = 'pending';

    public const STATUS_SYNC_SYNCED = 'synced';

    protected $table = 'kurikulum';

    protected $primaryKey = 'id_kurikulum';

    protected $guarded = ['id_kurikulum'];

    protected function casts(): array
    {
        return ['synced_at' => 'datetime'];
    }

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

    public function mahasiswa(): HasMany
    {
        return $this->hasMany(Mahasiswa::class, 'kurikulum_id', 'id_kurikulum');
    }
}
