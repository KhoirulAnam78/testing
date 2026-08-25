<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DosenPertemuanBlok extends Model
{
    protected $table = 'dosen_pertemuan_blok';

    protected $primaryKey = 'id_dosen_pertemuan_blok';

    protected $guarded = ['id_dosen_pertemuan_blok'];

    public function pertemuan_blok(): BelongsTo
    {
        return $this->belongsTo(PertemuanBlok::class, 'pertemuan_blok_id', 'id_pertemuan_blok');
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'dosen_id', 'id_dosen');
    }
}
