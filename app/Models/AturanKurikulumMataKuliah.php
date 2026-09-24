<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AturanKurikulumMataKuliah extends Model
{
    protected $table = 'aturan_kurikulum_mata_kuliah';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function kurikulum_mata_kuliah(): BelongsTo
    {
        return $this->belongsTo(KurikulumMataKuliah::class, 'kurikulum_mata_kuliah_id', 'id_kurikulum_mata_kuliah');
    }

    public function bolehDiambilUlangPada(string $jenisPeriode): bool
    {
        if (! $this->aktif) {
            return false;
        }

        if ($jenisPeriode === 'pendek') {
            return $this->periode_pengambilan_ulang === 'semua';
        }

        if (! in_array($jenisPeriode, ['ganjil', 'genap'], true)) {
            return false;
        }

        return match ($this->periode_pengambilan_ulang) {
            'semua' => true,
            'ganjil', 'genap' => $this->periode_pengambilan_ulang === $jenisPeriode,
            'mengikuti_semester_kurikulum' => ((int) $this->kurikulum_mata_kuliah->semester_urutan % 2 === 1 ? 'ganjil' : 'genap') === $jenisPeriode,
            default => false,
        };
    }
}
