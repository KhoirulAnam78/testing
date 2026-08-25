<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogbookPertemuanBlok extends Model
{
    protected $table = 'logbook_pertemuan_blok';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'ukuran_file' => 'integer',
            'diunggah_pada' => 'datetime',
            'divalidasi_pada' => 'datetime',
        ];
    }

    public function pertemuan_blok(): BelongsTo
    {
        return $this->belongsTo(PertemuanBlok::class, 'pertemuan_blok_id', 'id_pertemuan_blok');
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id', 'id_mahasiswa');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'divalidasi_oleh_user_id');
    }
}
