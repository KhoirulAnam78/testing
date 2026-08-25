<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nilai satu mahasiswa untuk satu komponen pada satu pertemuan.
 *
 * Tanpa soft delete: baris ditulis lewat `updateOrCreate` atas unique key
 * (pertemuan_blok_id, peserta_blok_id, komponen_penilaian_blok_id). Nilai yang
 * dikosongkan dosen dihapus permanen, jadi "ada baris" berarti "sudah dinilai".
 *
 * Berbeda dari presensi dan jurnal, nilai TIDAK dikunci oleh
 * `monitoring_pertemuan_blok.divalidasi_pada`. Lihat `AksesPertemuanBlok::bolehIsiNilai()`.
 */
class NilaiPertemuanBlok extends Model
{
    protected $table = 'nilai_pertemuan_blok';

    protected $primaryKey = 'id_nilai_pertemuan_blok';

    protected $guarded = ['id_nilai_pertemuan_blok'];

    protected function casts(): array
    {
        return [
            'nilai' => 'decimal:2',
        ];
    }

    public function pertemuan_blok(): BelongsTo
    {
        return $this->belongsTo(PertemuanBlok::class, 'pertemuan_blok_id', 'id_pertemuan_blok');
    }

    public function peserta_blok(): BelongsTo
    {
        return $this->belongsTo(PesertaBlok::class, 'peserta_blok_id', 'id_peserta_blok');
    }

    public function komponen_penilaian_blok(): BelongsTo
    {
        return $this->belongsTo(KomponenPenilaianBlok::class, 'komponen_penilaian_blok_id', 'id');
    }

    public function dinilai_oleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dinilai_oleh_user_id', 'id');
    }
}
