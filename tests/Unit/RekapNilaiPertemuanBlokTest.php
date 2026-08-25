<?php

namespace Tests\Unit;

use App\Models\RekapNilaiPertemuanBlok;
use PHPUnit\Framework\TestCase;

class RekapNilaiPertemuanBlokTest extends TestCase
{
    public function test_nilai_akhir_dinormalisasi_ke_seratus(): void
    {
        $this->assertEqualsWithDelta(
            87.5,
            RekapNilaiPertemuanBlok::hitungNilaiAkhir(70, 80),
            0.0001
        );
    }

    public function test_nilai_akhir_nol_saat_maksimum_nol(): void
    {
        $this->assertSame(0.0, RekapNilaiPertemuanBlok::hitungNilaiAkhir(0, 0));
    }
}