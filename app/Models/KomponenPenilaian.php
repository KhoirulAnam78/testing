<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Komponen penilaian milik satu jenis kegiatan, misal Keaktifan pada Tutorial.
 *
 * `nilai_min_default`/`nilai_maks_default` hanya prefill: batas yang berlaku saat menilai
 * selalu dibaca dari `komponen_penilaian_blok`, supaya mengubah master tidak menggeser
 * tafsir nilai blok yang sudah lampau.
 */
class KomponenPenilaian extends Model
{
    use SoftDeletes;

    protected $table = 'komponen_penilaian';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'nilai_min_default' => 'decimal:2',
            'nilai_maks_default' => 'decimal:2',
        ];
    }

    public function jenis_kegiatan(): BelongsTo
    {
        return $this->belongsTo(JenisKegiatan::class, 'jenis_kegiatan_id', 'id');
    }

    public function komponen_penilaian_blok(): HasMany
    {
        return $this->hasMany(KomponenPenilaianBlok::class, 'komponen_penilaian_id', 'id');
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('status', 'aktif');
    }
}
