<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SnapshotDpnaPeserta extends Model
{
    protected $table = 'snapshot_dpna_peserta';

    protected $primaryKey = 'id_snapshot_dpna_peserta';

    protected $guarded = ['id_snapshot_dpna_peserta'];

    protected function casts(): array
    {
        return ['nilai_akhir' => 'decimal:2', 'sumber_json' => 'array'];
    }

    public function finalisasi_dpna_blok(): BelongsTo
    {
        return $this->belongsTo(FinalisasiDpnaBlok::class, 'finalisasi_dpna_blok_id', 'id_finalisasi_dpna_blok');
    }

    public function peserta_blok(): BelongsTo
    {
        return $this->belongsTo(PesertaBlok::class, 'peserta_blok_id', 'id_peserta_blok');
    }
}
