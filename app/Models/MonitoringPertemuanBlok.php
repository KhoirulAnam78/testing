<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jurnal pelaksanaan satu pertemuan.
 *
 * Tanpa soft delete: `pertemuan_blok_id` unik dan baris ditulis lewat `updateOrCreate`.
 */
class MonitoringPertemuanBlok extends Model
{
    protected $table = 'monitoring_pertemuan_blok';

    protected $primaryKey = 'id_monitoring_pertemuan_blok';

    protected $guarded = ['id_monitoring_pertemuan_blok'];

    protected function casts(): array
    {
        return [
            'tanggal_realisasi' => 'date',
            'divalidasi_pada' => 'datetime',
        ];
    }

    /**
     * Status pertemuan yang mengikuti tiap status pelaksanaan. `ditunda` sengaja
     * kembali ke `terjadwal` karena pertemuannya masih akan dilaksanakan.
     *
     * @var array<string, string>
     */
    public const STATUS_PERTEMUAN = [
        'terlaksana' => 'selesai',
        'ditunda' => 'terjadwal',
        'batal' => 'batal',
    ];

    public function pertemuan_blok(): BelongsTo
    {
        return $this->belongsTo(PertemuanBlok::class, 'pertemuan_blok_id', 'id_pertemuan_blok');
    }

    public function diisi_oleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diisi_oleh_user_id', 'id');
    }

    public function divalidasi_oleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'divalidasi_oleh_user_id', 'id');
    }

    /**
     * Jurnal dan presensi pertemuan ini tidak boleh diubah lagi selama terkunci.
     */
    public function terkunci(): bool
    {
        return $this->divalidasi_pada !== null;
    }
}
