<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MateriRinciBlok extends Model
{
    use SoftDeletes;

    protected $table = 'materi_rinci_blok';

    protected $primaryKey = 'id_materi_rinci_blok';

    protected $guarded = ['id_materi_rinci_blok'];

    protected function casts(): array
    {
        return [
            'tanggal_rencana' => 'date',
        ];
    }

    /** @param array<int, array<string, mixed>> $materi */
    public static function pertemuanBerikutnya(array $materi): int
    {
        return collect($materi)
            ->flatMap(fn (array $item) => $item['rinci'] ?? [])
            ->max(fn (array $item) => (int) ($item['pertemuan_ke'] ?? 0)) + 1;
    }

    /** @param array<int, array<string, mixed>> $materi */
    public static function pertemuanDuplikat(array $materi): array
    {
        return collect($materi)
            ->flatMap(fn (array $item) => $item['rinci'] ?? [])
            ->pluck('pertemuan_ke')
            ->filter(fn ($nomor) => $nomor !== null && $nomor !== '')
            ->map(fn ($nomor) => (int) $nomor)
            ->duplicates()
            ->unique()
            ->values()
            ->all();
    }

    public function materi_blok(): BelongsTo
    {
        return $this->belongsTo(MateriBlok::class, 'materi_blok_id', 'id_materi_blok');
    }

    public function pertemuan_blok(): HasMany
    {
        return $this->hasMany(PertemuanBlok::class, 'materi_rinci_blok_id', 'id_materi_rinci_blok');
    }

    /**
     * Termasuk lampiran default (pertemuan_blok_id null) maupun lampiran milik
     * pertemuan tertentu. Pakai scope pada model lampiran untuk memisahkannya.
     */
    public function lampiran_materi_blok(): HasMany
    {
        return $this->hasMany(LampiranMateriBlok::class, 'materi_rinci_blok_id', 'id_materi_rinci_blok');
    }
}
