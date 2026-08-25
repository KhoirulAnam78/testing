<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Semester extends Model
{
    use SoftDeletes;

    protected $table = 'semester';

    protected $primaryKey = 'id_semester';

    protected $guarded = ['id_semester'];

    protected function casts(): array
    {
        return [
            'is_aktif' => 'boolean',
        ];
    }

    protected function tanggalMulai(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->parseDateValue($value),
            set: fn ($value) => $this->normalizeDateValue($value),
        );
    }

    protected function tanggalSelesai(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->parseDateValue($value),
            set: fn ($value) => $this->normalizeDateValue($value),
        );
    }

    private function parseDateValue(?string $value): ?CarbonImmutable
    {
        if (! $value) {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $value);

            if ($date && $date->format($format) === $value) {
                return CarbonImmutable::instance($date);
            }
        }

        return CarbonImmutable::parse($value);
    }

    private function normalizeDateValue(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $value = trim($value);

        foreach (['Y-m-d', 'd/m/Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $value);

            if ($date && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return $value;
    }

    public function blok(): HasMany
    {
        return $this->hasMany(Blok::class, 'semester_id', 'id_semester');
    }
}
