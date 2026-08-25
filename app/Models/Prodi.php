<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Prodi extends Model
{
    use SoftDeletes;

    protected $table = 'prodi';

    protected $primaryKey = 'id_prodi';

    protected $guarded = ['id_prodi'];

    public function dosen(): HasMany
    {
        return $this->hasMany(Dosen::class, 'prodi_id', 'id_prodi');
    }

    public function mahasiswa(): HasMany
    {
        return $this->hasMany(Mahasiswa::class, 'prodi_id', 'id_prodi');
    }

    public function mata_kuliah(): HasMany
    {
        return $this->hasMany(MataKuliah::class, 'prodi_id', 'id_prodi');
    }

    public function blok(): HasMany
    {
        return $this->hasMany(Blok::class, 'prodi_id', 'id_prodi');
    }
}
