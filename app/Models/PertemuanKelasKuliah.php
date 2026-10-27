<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class PertemuanKelasKuliah extends Model
{
    protected $table = 'pertemuan_kelas_kuliah';

    protected $primaryKey = 'id_pertemuan_kelas_kuliah';

    protected $guarded = ['id_pertemuan_kelas_kuliah'];

    protected function casts(): array
    {
        return ['kuliah_ke' => 'integer', 'last_synced_at' => 'datetime'];
    }

    /** @param array<int, int> $nomorPerMateri */
    public static function kuliahKe(array $nomorPerMateri, int $materiRinciId): int
    {
        return $nomorPerMateri[$materiRinciId] ?? ((int) max([0, ...array_values($nomorPerMateri)]) + 1);
    }

    public static function petakan(KelasKuliah $kelas, MateriRinciBlok $materi): self
    {
        if ($kelas->blok_id === null || ! $materi->materi_blok()
            ->whereHas('aturan_kegiatan_blok', fn ($query) => $query->where('blok_id', $kelas->blok_id))
            ->exists()) {
            throw new DomainException('Materi pertemuan wajib berasal dari blok kelas kuliah.');
        }

        return DB::transaction(function () use ($kelas, $materi): self {
            KelasKuliah::query()->whereKey($kelas->id_kelas_kuliah)->lockForUpdate()->firstOrFail();
            $existing = self::query()
                ->where('kelas_kuliah_id', $kelas->id_kelas_kuliah)
                ->pluck('kuliah_ke', 'materi_rinci_blok_id')
                ->map(fn ($nomor) => (int) $nomor)
                ->all();

            $materiIds = MateriRinciBlok::query()
                ->select('materi_rinci_blok.id_materi_rinci_blok')
                ->join('materi_blok', 'materi_blok.id_materi_blok', '=', 'materi_rinci_blok.materi_blok_id')
                ->join('aturan_kegiatan_blok', 'aturan_kegiatan_blok.id', '=', 'materi_blok.aturan_kegiatan_blok_id')
                ->where('aturan_kegiatan_blok.blok_id', $kelas->blok_id)
                ->where('materi_blok.status', 'aktif')
                ->where('materi_rinci_blok.status', 'aktif')
                ->whereNull('materi_blok.deleted_at')
                ->whereNull('aturan_kegiatan_blok.deleted_at')
                ->orderBy('aturan_kegiatan_blok.urutan')
                ->orderBy('materi_blok.urutan')
                ->orderBy('materi_rinci_blok.pertemuan_ke')
                ->orderBy('materi_rinci_blok.urutan')
                ->pluck('materi_rinci_blok.id_materi_rinci_blok');

            foreach ($materiIds as $materiId) {
                if (isset($existing[$materiId])) {
                    continue;
                }

                $mapping = self::query()->create([
                    'kelas_kuliah_id' => $kelas->id_kelas_kuliah,
                    'materi_rinci_blok_id' => $materiId,
                    'kuliah_ke' => self::kuliahKe($existing, $materiId),
                ]);
                $existing[$materiId] = $mapping->kuliah_ke;
            }

            return self::query()
                ->where('kelas_kuliah_id', $kelas->id_kelas_kuliah)
                ->where('materi_rinci_blok_id', $materi->id_materi_rinci_blok)
                ->firstOrFail();
        });
    }

    public function kelas_kuliah(): BelongsTo
    {
        return $this->belongsTo(KelasKuliah::class, 'kelas_kuliah_id', 'id_kelas_kuliah');
    }

    public function materi_rinci_blok(): BelongsTo
    {
        return $this->belongsTo(MateriRinciBlok::class, 'materi_rinci_blok_id', 'id_materi_rinci_blok');
    }
}