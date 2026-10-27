<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class KelasKuliah extends Model
{
    use SoftDeletes;

    protected $table = 'kelas_kuliah';

    protected $primaryKey = 'id_kelas_kuliah';

    protected $guarded = ['id_kelas_kuliah'];

    protected function casts(): array
    {
        return [
            'kapasitas' => 'integer',
            'sumber_json' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public static function pemetaanDapatDiubah(?int $blokAsalId, ?int $blokTujuanId, int $jumlahPeserta): bool
    {
        return $blokAsalId === $blokTujuanId || $blokAsalId === null || $jumlahPeserta === 0;
    }

    public static function dapatDihapus(int $jumlahPeserta): bool
    {
        return $jumlahPeserta === 0;
    }

    public function blok(): BelongsTo
    {
        return $this->belongsTo(Blok::class, 'blok_id');
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id', 'id_semester');
    }

    public function mata_kuliah(): BelongsTo
    {
        return $this->belongsTo(MataKuliah::class, 'mata_kuliah_id');
    }

    public function anggota_kelas_kuliah(): HasMany
    {
        return $this->hasMany(AnggotaKelasKuliah::class, 'kelas_kuliah_id', 'id_kelas_kuliah');
    }

    public function pertemuan_kelas_kuliah(): HasMany
    {
        return $this->hasMany(PertemuanKelasKuliah::class, 'kelas_kuliah_id', 'id_kelas_kuliah');
    }

    public function pengampu_kelas_kuliah(): HasMany
    {
        return $this->hasMany(PengampuKelasKuliah::class, 'kelas_kuliah_id', 'id_kelas_kuliah');
    }

    public function tautkanPesertaBlok(): int
    {
        if ($this->blok_id === null) {
            return 0;
        }

        $anggota = $this->anggota_kelas_kuliah()
            ->whereNull('peserta_blok_id')
            ->lockForUpdate()
            ->get(['id_anggota_kelas_kuliah', 'mahasiswa_id']);
        $pesertaTerpakai = AnggotaKelasKuliah::query()
            ->where('kelas_kuliah_id', '!=', $this->id_kelas_kuliah)
            ->whereNotNull('peserta_blok_id')
            ->pluck('peserta_blok_id');
        $peserta = PesertaBlok::query()
            ->where('blok_id', $this->blok_id)
            ->whereIn('mahasiswa_id', $anggota->pluck('mahasiswa_id'))
            ->whereNotIn('id_peserta_blok', $pesertaTerpakai)
            ->whereIn('status', ['aktif', 'mengulang'])
            ->lockForUpdate()
            ->get(['id_peserta_blok', 'mahasiswa_id'])
            ->keyBy('mahasiswa_id');
        $ditautkan = 0;

        foreach ($anggota as $item) {
            $pesertaBlokId = $peserta->get($item->mahasiswa_id)?->id_peserta_blok;

            if ($pesertaBlokId !== null) {
                $item->update(['peserta_blok_id' => $pesertaBlokId]);
                $ditautkan++;
            }
        }

        return $ditautkan;
    }
}
