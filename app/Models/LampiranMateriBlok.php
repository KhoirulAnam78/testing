<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LampiranMateriBlok extends Model
{
    use SoftDeletes;

    protected $table = 'lampiran_materi_blok';

    protected $primaryKey = 'id_lampiran_materi_blok';

    protected $guarded = ['id_lampiran_materi_blok'];

    public function blok(): BelongsTo
    {
        return $this->belongsTo(Blok::class, 'blok_id', 'id');
    }

    public function materi_rinci_blok(): BelongsTo
    {
        return $this->belongsTo(MateriRinciBlok::class, 'materi_rinci_blok_id', 'id_materi_rinci_blok');
    }

    /**
     * Null berarti lampiran default yang berlaku untuk semua kelompok pada materi ini.
     */
    public function pertemuan_blok(): BelongsTo
    {
        return $this->belongsTo(PertemuanBlok::class, 'pertemuan_blok_id', 'id_pertemuan_blok');
    }

    public function dibuat_oleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh_user_id', 'id');
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('status', 'aktif');
    }

    /**
     * Lampiran default materi ini ditambah lampiran milik satu pertemuan.
     * Default selalu diurutkan lebih dulu supaya jelas mana yang diwarisi.
     */
    public function scopeUntukPertemuan(Builder $query, int $materiRinciId, ?int $pertemuanId = null): Builder
    {
        return $query
            ->where('materi_rinci_blok_id', $materiRinciId)
            ->where(fn (Builder $where) => $where
                ->whereNull('pertemuan_blok_id')
                ->when($pertemuanId, fn (Builder $atau) => $atau->orWhere('pertemuan_blok_id', $pertemuanId)))
            ->orderByRaw('pertemuan_blok_id IS NULL DESC')
            ->orderBy('jenis')
            ->orderBy('urutan')
            ->orderBy('id_lampiran_materi_blok');
    }

    /**
     * Hanya lampiran default: yang disiapkan pengelola di level materi.
     */
    public function scopeDefaultMateri(Builder $query, int $materiRinciId): Builder
    {
        return $query
            ->where('materi_rinci_blok_id', $materiRinciId)
            ->whereNull('pertemuan_blok_id')
            ->orderBy('jenis')
            ->orderBy('urutan')
            ->orderBy('id_lampiran_materi_blok');
    }

    /**
     * Hanya lampiran milik satu pertemuan, tanpa default yang diwarisi.
     */
    public function scopeMilikPertemuan(Builder $query, int $pertemuanId): Builder
    {
        return $query
            ->where('pertemuan_blok_id', $pertemuanId)
            ->orderBy('jenis')
            ->orderBy('urutan')
            ->orderBy('id_lampiran_materi_blok');
    }
}
