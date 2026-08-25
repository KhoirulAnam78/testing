<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Blok extends Model
{
    use SoftDeletes;

    protected $table = 'blok';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'kehadiran_masuk_dpna' => 'boolean',
            'bobot_kehadiran_dpna' => 'decimal:2',
        ];
    }

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class, 'prodi_id', 'id_prodi');
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id', 'id_semester');
    }

    public function koordinator(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'koordinator_id', 'id_dosen');
    }

    public function asisten_koordinator(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'asisten_koordinator_id', 'id_dosen');
    }

    public function scopeDapatDikelolaOleh(Builder $query, ?Authenticatable $user): Builder
    {
        if ($user?->can('blok-operasional:') || $user?->can('dpna-blok:')) {
            return $query;
        }

        $dosenId = $user?->dosen?->id_dosen;

        if ($dosenId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(
            fn (Builder $query) => $query
                ->where('koordinator_id', $dosenId)
                ->orWhere('asisten_koordinator_id', $dosenId)
        );
    }

    public function dapatDikelolaOleh(?Authenticatable $user): bool
    {
        if ($user?->can('blok-operasional:') || $user?->can('dpna-blok:')) {
            return true;
        }

        $dosenId = $user?->dosen?->id_dosen;

        return $dosenId !== null && in_array($dosenId, [
            $this->koordinator_id,
            $this->asisten_koordinator_id,
        ]);
    }

    public function aturan_kegiatan_blok(): HasMany
    {
        return $this->hasMany(AturanKegiatanBlok::class, 'blok_id', 'id');
    }

    public function materi_blok(): HasManyThrough
    {
        return $this->hasManyThrough(
            MateriBlok::class,
            AturanKegiatanBlok::class,
            'blok_id',
            'aturan_kegiatan_blok_id',
            'id',
            'id',
        );
    }

    public function mata_kuliah(): HasMany
    {
        return $this->hasMany(MataKuliah::class, 'blok_id', 'id');
    }

    /**
     * Rombel opsional. Blok tetap berjalan penuh tanpa satu baris kelas pun.
     */
    public function kelas(): HasMany
    {
        return $this->hasMany(Kelas::class, 'blok_id', 'id');
    }

    public function peserta_blok(): HasMany
    {
        return $this->hasMany(PesertaBlok::class, 'blok_id', 'id');
    }

    public function kelompok_blok(): HasMany
    {
        return $this->hasMany(KelompokBlok::class, 'blok_id', 'id');
    }

    public function pertemuan_blok(): HasMany
    {
        return $this->hasMany(PertemuanBlok::class, 'blok_id', 'id');
    }
}
