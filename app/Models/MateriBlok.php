<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MateriBlok extends Model
{
    use SoftDeletes;

    protected $table = 'materi_blok';

    protected $primaryKey = 'id_materi_blok';

    protected $guarded = ['id_materi_blok'];

    public function aturan_kegiatan_blok(): BelongsTo
    {
        return $this->belongsTo(AturanKegiatanBlok::class, 'aturan_kegiatan_blok_id', 'id');
    }

    public function materi_rinci_blok(): HasMany
    {
        return $this->hasMany(MateriRinciBlok::class, 'materi_blok_id', 'id_materi_blok');
    }
}
