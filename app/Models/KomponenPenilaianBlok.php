<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Rubrik penilaian milik satu `aturan_kegiatan_blok`, disalin dari standar jenis kegiatan
 * lalu boleh disesuaikan per blok. Inilah lapis yang dipakai `nilai_pertemuan_blok`,
 * sehingga batas nilai ikut terkunci pada blok tersebut.
 *
 * Memakai soft delete supaya komponen yang dibuang dari rubrik tidak meng-cascade
 * menghapus nilainya. Konsekuensinya baris soft-deleted tetap menempati unique index:
 * simpan lewat `withTrashed()->firstOrNew([...kunci bisnis...])` lalu `restore()`, jangan
 * `updateOrCreate`.
 */
class KomponenPenilaianBlok extends Model
{
    use SoftDeletes;

    protected $table = 'komponen_penilaian_blok';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'nilai_min' => 'decimal:2',
            'nilai_maks' => 'decimal:2',
        ];
    }

    public function aturan_kegiatan_blok(): BelongsTo
    {
        return $this->belongsTo(AturanKegiatanBlok::class, 'aturan_kegiatan_blok_id', 'id');
    }

    public function komponen_penilaian(): BelongsTo
    {
        return $this->belongsTo(KomponenPenilaian::class, 'komponen_penilaian_id', 'id');
    }

    public function nilai_pertemuan_blok(): HasMany
    {
        return $this->hasMany(NilaiPertemuanBlok::class, 'komponen_penilaian_blok_id', 'id');
    }
}
