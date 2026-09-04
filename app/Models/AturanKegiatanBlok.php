<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class AturanKegiatanBlok extends Model
{
    use SoftDeletes;

    protected $table = 'aturan_kegiatan_blok';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'perlu_kelompok' => 'boolean',
            'perlu_presensi' => 'boolean',
            'perlu_logbook' => 'boolean',
            'perlu_penilaian' => 'boolean',
            'nilai_masuk_dpna' => 'boolean',
            'bobot_nilai_dpna' => 'decimal:2',
        ];
    }

    public function blok(): BelongsTo
    {
        return $this->belongsTo(Blok::class, 'blok_id', 'id');
    }

    public function jenis_kegiatan(): BelongsTo
    {
        return $this->belongsTo(JenisKegiatan::class, 'jenis_kegiatan_id', 'id');
    }

    public function materi_blok(): HasMany
    {
        return $this->hasMany(MateriBlok::class, 'aturan_kegiatan_blok_id', 'id');
    }

    /**
     * Rincian materi kegiatan ini, menembus `materi_blok`.
     *
     * Jumlahnya adalah jumlah pertemuan yang direncanakan untuk SATU kelompok, karena
     * satu `pertemuan_blok` lahir dari satu rincian materi dikali satu kelompok. Kolom
     * `jumlah_pertemuan` yang dulu menyimpan angka ini sudah dihapus migrasi
     * `2026_08_24_000003` justru supaya tidak ada dua sumber kebenaran; pakai
     * `withCount('materi_rinci_blok')` alih-alih menghidupkannya kembali.
     *
     * `hasManyThrough` hanya menerapkan global scope model tujuan, bukan model
     * perantaranya, jadi soft delete `materi_blok` disaring manual. Tanpa ini rincian
     * milik materi yang sudah dibuang tetap ikut terhitung dan kelengkapan DPNA jadi
     * tidak pernah terpenuhi.
     */
    public function materi_rinci_blok(): HasManyThrough
    {
        return $this->hasManyThrough(
            MateriRinciBlok::class,
            MateriBlok::class,
            'aturan_kegiatan_blok_id',
            'materi_blok_id',
            'id',
            'id_materi_blok',
        )->whereNull('materi_blok.deleted_at');
    }

    public function kelompok_blok(): HasMany
    {
        return $this->hasMany(KelompokBlok::class, 'aturan_kegiatan_blok_id', 'id');
    }

    public function pertemuan_blok(): HasMany
    {
        return $this->hasMany(PertemuanBlok::class, 'aturan_kegiatan_blok_id', 'id');
    }

    public function komponen_penilaian_blok(): HasMany
    {
        return $this->hasMany(KomponenPenilaianBlok::class, 'aturan_kegiatan_blok_id', 'id');
    }
}
