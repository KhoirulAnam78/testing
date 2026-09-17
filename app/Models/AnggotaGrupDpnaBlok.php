<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnggotaGrupDpnaBlok extends Model
{
    protected $table = 'anggota_grup_dpna_blok';

    protected $primaryKey = 'id_anggota_grup_dpna_blok';

    protected $guarded = ['id_anggota_grup_dpna_blok'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function grup_dpna_blok(): BelongsTo
    {
        return $this->belongsTo(GrupDpnaBlok::class, 'grup_dpna_blok_id', 'id_grup_dpna_blok');
    }

    public function aturan_kegiatan_blok(): BelongsTo
    {
        return $this->belongsTo(AturanKegiatanBlok::class, 'aturan_kegiatan_blok_id');
    }
}
