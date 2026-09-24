<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SkalaNilai extends Model
{
    protected $table = 'skala_nilai';

    protected $primaryKey = 'id_skala_nilai';

    protected $guarded = ['id_skala_nilai'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function detail(): HasMany
    {
        return $this->hasMany(SkalaNilaiDetail::class, 'skala_nilai_id', 'id_skala_nilai')->orderByDesc('nilai_angka_min');
    }

    public function kurikulum(): HasMany
    {
        return $this->hasMany(Kurikulum::class, 'skala_nilai_id', 'id_skala_nilai')->withTrashed();
    }
}
