<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class PertemuanBlok extends Model
{
    use SoftDeletes;

    protected $table = 'pertemuan_blok';

    protected $primaryKey = 'id_pertemuan_blok';

    protected $guarded = ['id_pertemuan_blok'];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }

    public function blok(): BelongsTo
    {
        return $this->belongsTo(Blok::class, 'blok_id', 'id');
    }

    public function aturan_kegiatan_blok(): BelongsTo
    {
        return $this->belongsTo(AturanKegiatanBlok::class, 'aturan_kegiatan_blok_id', 'id');
    }

    public function materi_rinci_blok(): BelongsTo
    {
        return $this->belongsTo(MateriRinciBlok::class, 'materi_rinci_blok_id', 'id_materi_rinci_blok');
    }

    public function kelompok_blok(): BelongsTo
    {
        return $this->belongsTo(KelompokBlok::class, 'kelompok_blok_id', 'id_kelompok_blok');
    }

    public function dosen_pertemuan_blok(): HasMany
    {
        return $this->hasMany(DosenPertemuanBlok::class, 'pertemuan_blok_id', 'id_pertemuan_blok');
    }

    /**
     * Lampiran khusus pertemuan ini saja. Lampiran default materi tidak ikut karena
     * `pertemuan_blok_id`-nya null; gabungkan lewat scope `untukPertemuan`.
     */
    public function lampiran_materi_blok(): HasMany
    {
        return $this->hasMany(LampiranMateriBlok::class, 'pertemuan_blok_id', 'id_pertemuan_blok');
    }

    public function presensi_pertemuan_blok(): HasMany
    {
        return $this->hasMany(PresensiPertemuanBlok::class, 'pertemuan_blok_id', 'id_pertemuan_blok');
    }

    /**
     * Satu baris per peserta per komponen rubrik. Baris hanya ada bila sudah dinilai.
     */
    public function nilai_pertemuan_blok(): HasMany
    {
        return $this->hasMany(NilaiPertemuanBlok::class, 'pertemuan_blok_id', 'id_pertemuan_blok');
    }

    public function logbook_pertemuan_blok(): HasMany
    {
        return $this->hasMany(LogbookPertemuanBlok::class, 'pertemuan_blok_id', 'id_pertemuan_blok');
    }

    /**
     * Jurnal pelaksanaan, satu per pertemuan. Null berarti belum diisi.
     */
    public function monitoring_pertemuan_blok(): HasOne
    {
        return $this->hasOne(MonitoringPertemuanBlok::class, 'pertemuan_blok_id', 'id_pertemuan_blok');
    }
}
