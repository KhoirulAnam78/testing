<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinalisasiDpnaBlok extends Model
{
    protected $table = 'finalisasi_dpna_blok';

    protected $primaryKey = 'id_finalisasi_dpna_blok';

    protected $guarded = ['id_finalisasi_dpna_blok'];

    protected function casts(): array
    {
        return [
            'konfigurasi_json' => 'array',
            'difinalisasi_pada' => 'datetime',
            'dibuka_pada' => 'datetime',
        ];
    }

    public function blok(): BelongsTo
    {
        return $this->belongsTo(Blok::class, 'blok_id');
    }

    public function snapshot_dpna_peserta(): HasMany
    {
        return $this->hasMany(SnapshotDpnaPeserta::class, 'finalisasi_dpna_blok_id', 'id_finalisasi_dpna_blok');
    }

    public function difinalisasi_oleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'difinalisasi_oleh_user_id');
    }

    public function dibuka_oleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuka_oleh_user_id');
    }
}
