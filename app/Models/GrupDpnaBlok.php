<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrupDpnaBlok extends Model
{
    protected $table = 'grup_dpna_blok';

    protected $primaryKey = 'id_grup_dpna_blok';

    protected $guarded = ['id_grup_dpna_blok'];

    protected function casts(): array
    {
        return ['bobot' => 'decimal:2', 'aktif' => 'boolean'];
    }

    public function blok(): BelongsTo
    {
        return $this->belongsTo(Blok::class, 'blok_id');
    }

    public function anggota_grup_dpna_blok(): HasMany
    {
        return $this->hasMany(AnggotaGrupDpnaBlok::class, 'grup_dpna_blok_id', 'id_grup_dpna_blok');
    }
}
