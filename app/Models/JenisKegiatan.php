<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class JenisKegiatan extends Model
{
    use SoftDeletes;

    protected $table = 'jenis_kegiatan';

    protected $guarded = ['id'];

    public function aturan_kegiatan_blok(): HasMany
    {
        return $this->hasMany(AturanKegiatanBlok::class, 'jenis_kegiatan_id', 'id');
    }

    /**
     * Standar komponen penilaian untuk jenis kegiatan ini. Dipakai sebagai template saat
     * pengelola menyusun rubrik penilaian pada form Blok.
     */
    public function komponen_penilaian(): HasMany
    {
        return $this->hasMany(KomponenPenilaian::class, 'jenis_kegiatan_id', 'id');
    }
}
