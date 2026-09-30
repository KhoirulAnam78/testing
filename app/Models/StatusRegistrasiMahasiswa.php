<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatusRegistrasiMahasiswa extends Model
{
    public const STATUS = ['aktif', 'belum_aktif', 'cuti', 'nonaktif'];

    protected $table = 'status_registrasi_mahasiswa';

    protected $primaryKey = 'id_status_registrasi_mahasiswa';

    protected $guarded = ['id_status_registrasi_mahasiswa'];

    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
        ];
    }

    public static function dapatDitetapkanUntuk(Mahasiswa $mahasiswa, Semester $semester): bool
    {
        return ! $semester->is_aktif || $mahasiswa->status !== 'lulus';
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id', 'id_mahasiswa');
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id', 'id_semester');
    }
}
