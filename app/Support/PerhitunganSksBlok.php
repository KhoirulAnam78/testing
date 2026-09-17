<?php

namespace App\Support;

final class PerhitunganSksBlok
{
    public static function keSkala(mixed $nilai): int
    {
        $nilai = trim((string) $nilai);

        if (! preg_match('/^\d+(?:\.\d{1,4})?$/', $nilai)) {
            return 0;
        }

        [$bulat, $desimal] = array_pad(explode('.', $nilai, 2), 2, '');

        return ((int) $bulat * 10000) + (int) str_pad($desimal, 4, '0');
    }

    public static function bobotPertemuan(float $bobotKegiatan, int $jumlahPertemuan): float
    {
        return $jumlahPertemuan > 0 ? $bobotKegiatan / $jumlahPertemuan : 0.0;
    }

    public static function bobotDosen(float $bobotPertemuan, int $jumlahDosen): float
    {
        return $jumlahDosen > 0 ? $bobotPertemuan / $jumlahDosen : 0.0;
    }
}
