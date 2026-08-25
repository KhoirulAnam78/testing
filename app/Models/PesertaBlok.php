<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PesertaBlok extends Model
{
    use SoftDeletes;

    protected $table = 'peserta_blok';

    protected $primaryKey = 'id_peserta_blok';

    protected $guarded = ['id_peserta_blok'];

    protected function casts(): array
    {
        return [
            'tanggal_masuk' => 'date',
        ];
    }

    public function blok(): BelongsTo
    {
        return $this->belongsTo(Blok::class, 'blok_id', 'id');
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id', 'id_mahasiswa');
    }

    /**
     * Rombel opsional. Null berarti peserta tidak dibagi ke rombel manapun.
     */
    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id', 'id_kelas');
    }

    public function anggota_kelompok_blok(): HasMany
    {
        return $this->hasMany(AnggotaKelompokBlok::class, 'peserta_blok_id', 'id_peserta_blok');
    }

    public function presensi_pertemuan_blok(): HasMany
    {
        return $this->hasMany(PresensiPertemuanBlok::class, 'peserta_blok_id', 'id_peserta_blok');
    }

    public function nilai_pertemuan_blok(): HasMany
    {
        return $this->hasMany(NilaiPertemuanBlok::class, 'peserta_blok_id', 'id_peserta_blok');
    }
}
