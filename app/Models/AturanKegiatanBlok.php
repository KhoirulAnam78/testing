<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AturanKegiatanBlok extends Model
{
    use SoftDeletes;

    protected $table = 'aturan_kegiatan_blok';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'perlu_kelompok' => 'boolean',
            'perlu_presensi' => 'boolean',
            'perlu_logbook' => 'boolean',
            'perlu_penilaian' => 'boolean',
            'nilai_masuk_dpna' => 'boolean',
            'bobot_nilai_dpna' => 'decimal:2',
        ];
    }

    public function blok(): BelongsTo
    {
        return $this->belongsTo(Blok::class, 'blok_id', 'id');
    }

    public function jenis_kegiatan(): BelongsTo
    {
        return $this->belongsTo(JenisKegiatan::class, 'jenis_kegiatan_id', 'id');
    }

    public function materi_blok(): HasMany
    {
        return $this->hasMany(MateriBlok::class, 'aturan_kegiatan_blok_id', 'id');
    }

    public function kelompok_blok(): HasMany
    {
        return $this->hasMany(KelompokBlok::class, 'aturan_kegiatan_blok_id', 'id');
    }

    public function pertemuan_blok(): HasMany
    {
        return $this->hasMany(PertemuanBlok::class, 'aturan_kegiatan_blok_id', 'id');
    }

    public function komponen_penilaian_blok(): HasMany
    {
        return $this->hasMany(KomponenPenilaianBlok::class, 'aturan_kegiatan_blok_id', 'id');
    }
}
