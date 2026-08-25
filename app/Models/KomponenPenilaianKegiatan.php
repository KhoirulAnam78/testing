<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Standar komponen penilaian per jenis kegiatan, misal Tutorial = Keaktifan 0-20 dan
 * Perilaku 0-30. Hanya template: rubrik yang dipakai menilai ada di
 * `komponen_penilaian_blok`.
 *
 * Tanpa soft delete. Baris disimpan lewat sync-and-prune atas kunci bisnis
 * (jenis_kegiatan_id, komponen_penilaian_id) dan baris soft-deleted akan tetap
 * menempati unique index.
 */
class KomponenPenilaianKegiatan extends Model
{
    protected $table = 'komponen_penilaian_kegiatan';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'nilai_min' => 'decimal:2',
            'nilai_maks' => 'decimal:2',
        ];
    }

    public function jenis_kegiatan(): BelongsTo
    {
        return $this->belongsTo(JenisKegiatan::class, 'jenis_kegiatan_id', 'id');
    }

    public function komponen_penilaian(): BelongsTo
    {
        return $this->belongsTo(KomponenPenilaian::class, 'komponen_penilaian_id', 'id');
    }
}
