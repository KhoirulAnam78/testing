<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MataKuliah extends Model
{
    use SoftDeletes;

    public const STATUS_SYNC_PENDING = 'pending';

    public const STATUS_SYNC_SYNCED = 'synced';

    protected $table = 'mata_kuliah';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'synced_at' => 'datetime',
        ];
    }

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class, 'prodi_id', 'id_prodi');
    }

    public function blok(): HasMany
    {
        return $this->hasMany(Blok::class, 'mata_kuliah_id', 'id');
    }

    public function kurikulum_mata_kuliah(): HasMany
    {
        return $this->hasMany(KurikulumMataKuliah::class, 'mata_kuliah_id', 'id');
    }
}
