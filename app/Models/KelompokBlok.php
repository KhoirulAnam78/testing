<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class KelompokBlok extends Model
{
    use SoftDeletes;

    protected $table = 'kelompok_blok';

    protected $primaryKey = 'id_kelompok_blok';

    protected $guarded = ['id_kelompok_blok'];

    public function blok(): BelongsTo
    {
        return $this->belongsTo(Blok::class, 'blok_id', 'id');
    }

    public function aturan_kegiatan_blok(): BelongsTo
    {
        return $this->belongsTo(AturanKegiatanBlok::class, 'aturan_kegiatan_blok_id', 'id');
    }

    /**
     * Rombel opsional. Jika terisi, anggota kelompok wajib berasal dari rombel ini.
     */
    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id', 'id_kelas');
    }

    public function anggota_kelompok_blok(): HasMany
    {
        return $this->hasMany(AnggotaKelompokBlok::class, 'kelompok_blok_id', 'id_kelompok_blok');
    }

    public function pertemuan_blok(): HasMany
    {
        return $this->hasMany(PertemuanBlok::class, 'kelompok_blok_id', 'id_kelompok_blok');
    }
}
