<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiIntegrasi extends Model
{
    protected $table = 'api_integrasi';

    protected $guarded = ['id'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'is_aktif' => 'boolean',
            'timeout' => 'integer',
        ];
    }
}
