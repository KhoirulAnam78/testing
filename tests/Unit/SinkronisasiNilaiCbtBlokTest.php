<?php

namespace Tests\Unit;

use App\Support\SinkronisasiNilaiCbtBlok;
use PHPUnit\Framework\TestCase;

final class SinkronisasiNilaiCbtBlokTest extends TestCase
{
    public function test_normalisasi_nilai_menolak_pembagi_invalid_dan_membulatkan_hasil(): void
    {
        $this->assertSame(75.56, SinkronisasiNilaiCbtBlok::normalisasiNilai(34, 45));
        $this->assertNull(SinkronisasiNilaiCbtBlok::normalisasiNilai(10, null));
        $this->assertNull(SinkronisasiNilaiCbtBlok::normalisasiNilai(10, 0));
        $this->assertNull(SinkronisasiNilaiCbtBlok::normalisasiNilai(10, -1));
        $this->assertNull(SinkronisasiNilaiCbtBlok::normalisasiNilai(11, 10));
    }
}
