<?php

namespace App\Models;

use App\Support\PerhitunganSksBlok;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DosenPertemuanBlok extends Model
{
    protected $table = 'dosen_pertemuan_blok';

    protected $primaryKey = 'id_dosen_pertemuan_blok';

    protected $guarded = ['id_dosen_pertemuan_blok'];

    /**
     * Bobot kegiatan dibagi ke seluruh pertemuan, lalu rata ke seluruh dosen pada sesi yang sama.
     * Nilai dihitung saat dibaca agar selalu mengikuti perubahan plotting dosen.
     */
    protected function bobotSks(): Attribute
    {
        return Attribute::make(
            get: function (): float {
                $pertemuan = $this->pertemuan_blok;

                if (! $pertemuan) {
                    return 0.0;
                }

                $jumlahDosen = $pertemuan->relationLoaded('dosen_pertemuan_blok')
                    ? $pertemuan->dosen_pertemuan_blok->count()
                    : $pertemuan->dosen_pertemuan_blok()->count();

                if ($jumlahDosen === 0) {
                    return 0.0;
                }

                $bobotPertemuan = $pertemuan->aturan_kegiatan_blok?->bobotSksPerPertemuan() ?? 0.0;

                return PerhitunganSksBlok::bobotDosen($bobotPertemuan, $jumlahDosen);
            },
        );
    }

    public function pertemuan_blok(): BelongsTo
    {
        return $this->belongsTo(PertemuanBlok::class, 'pertemuan_blok_id', 'id_pertemuan_blok');
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'dosen_id', 'id_dosen');
    }
}
