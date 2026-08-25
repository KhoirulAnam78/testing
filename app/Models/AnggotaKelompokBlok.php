<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnggotaKelompokBlok extends Model
{
    protected $table = 'anggota_kelompok_blok';

    protected $primaryKey = 'id_anggota_kelompok_blok';

    protected $guarded = ['id_anggota_kelompok_blok'];

    public function kelompok_blok(): BelongsTo
    {
        return $this->belongsTo(KelompokBlok::class, 'kelompok_blok_id', 'id_kelompok_blok');
    }

    public function peserta_blok(): BelongsTo
    {
        return $this->belongsTo(PesertaBlok::class, 'peserta_blok_id', 'id_peserta_blok');
    }
}
