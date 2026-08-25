<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Rombel opsional di dalam satu Blok.
 *
 * Kelas tidak memiliki peserta, kelompok, atau pertemuan. Ia hanya menjadi label
 * pengelompokan pada `peserta_blok.kelas_id` dan `kelompok_blok.kelas_id`, dipakai
 * ketika satu blok perlu dipecah menjadi beberapa rombongan paralel. Prodi, semester,
 * dan mata kuliah dibaca dari blok induk agar tidak ada duplikasi data.
 */
class Kelas extends Model
{
    use SoftDeletes;

    protected $table = 'kelas';

    protected $primaryKey = 'id_kelas';

    protected $guarded = ['id_kelas'];

    public function blok(): BelongsTo
    {
        return $this->belongsTo(Blok::class, 'blok_id', 'id');
    }

    public function peserta_blok(): HasMany
    {
        return $this->hasMany(PesertaBlok::class, 'kelas_id', 'id_kelas');
    }

    public function kelompok_blok(): HasMany
    {
        return $this->hasMany(KelompokBlok::class, 'kelas_id', 'id_kelas');
    }
}
