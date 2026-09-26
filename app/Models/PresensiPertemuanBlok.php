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
    public const SEMUA_STATUS = ['hadir', 'izin', 'sakit', 'alpa', 'lain_lain', 'dispensasi'];

    /**
     * Metadata status untuk tampilan presensi dan dokumen.
     *
     * @var array<string, array{kode: string, label: string, deskripsi: string, warna: string}>
     */
    public const STATUS = [
        'hadir' => [
            'kode' => 'H',
            'label' => 'Hadir',
            'deskripsi' => 'Mahasiswa hadir mengikuti perkuliahan',
            'warna' => 'success',
        ],
        'izin' => [
            'kode' => 'I',
            'label' => 'Izin',
            'deskripsi' => 'Mahasiswa berhalangan hadir dengan izin resmi',
            'warna' => 'info',
        ],
        'sakit' => [
            'kode' => 'S',
            'label' => 'Sakit',
            'deskripsi' => 'Mahasiswa sakit dengan surat keterangan',
            'warna' => 'warning',
        ],
        'alpa' => [
            'kode' => 'A',
            'label' => 'Alpa',
            'deskripsi' => 'Mahasiswa tidak hadir tanpa keterangan',
            'warna' => 'danger',
        ],
        'lain_lain' => [
            'kode' => 'L',
            'label' => 'Lain-lain',
            'deskripsi' => 'Alasan kedinasan / kegiatan kampus',
            'warna' => 'secondary',
        ],
        'dispensasi' => [
            'kode' => 'D',
            'label' => 'Dispensasi',
            'deskripsi' => 'Dispensasi khusus pimpinan fakultas',
            'warna' => 'primary',
        ],
    ];

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
