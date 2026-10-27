<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengampuKelasKuliah extends Model
{
    protected $table = 'pengampu_kelas_kuliah';

    protected $primaryKey = 'id_pengampu_kelas_kuliah';

    protected $guarded = ['id_pengampu_kelas_kuliah'];

    protected function casts(): array
    {
        return [
            'aktif_sumber' => 'boolean',
            'sumber_json' => 'array',
            'last_synced_at' => 'datetime',
            'missing_since' => 'datetime',
        ];
    }

    public function kelas_kuliah(): BelongsTo
    {
        return $this->belongsTo(KelasKuliah::class, 'kelas_kuliah_id', 'id_kelas_kuliah');
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'dosen_id', 'id_dosen');
    }
}