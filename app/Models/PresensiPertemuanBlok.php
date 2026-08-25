<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tanpa soft delete: baris ditulis lewat `updateOrCreate` atas unique key
 * (pertemuan_blok_id, peserta_blok_id).
 */
class PresensiPertemuanBlok extends Model
{
    protected $table = 'presensi_pertemuan_blok';

    protected $primaryKey = 'id_presensi_pertemuan_blok';

    protected $guarded = ['id_presensi_pertemuan_blok'];

    /**
     * Status yang dianggap kehadiran sah saat merekap persentase.
     *
     * @var array<int, string>
     */
    public const STATUS_HADIR = ['hadir'];

    /**
     * @var array<int, string>
     */
    public const SEMUA_STATUS = ['hadir', 'sakit', 'izin', 'alpa'];

    public function pertemuan_blok(): BelongsTo
    {
        return $this->belongsTo(PertemuanBlok::class, 'pertemuan_blok_id', 'id_pertemuan_blok');
    }

    public function peserta_blok(): BelongsTo
    {
        return $this->belongsTo(PesertaBlok::class, 'peserta_blok_id', 'id_peserta_blok');
    }

    public function dicatat_oleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh_user_id', 'id');
    }

    public function scopeHadir(Builder $query): Builder
    {
        return $query->whereIn('status', self::STATUS_HADIR);
    }
}
