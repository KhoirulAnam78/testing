<?php

namespace Tests\Unit;

use App\Support\PerhitunganDpnaBlok;
use PHPUnit\Framework\TestCase;

class PerhitunganDpnaBlokTest extends TestCase
{
    public function test_normalisasi_dan_rata_rata_memakai_skala_seratus(): void
    {
        $this->assertSame(75.0, PerhitunganDpnaBlok::normalisasi(30, 40));
        $this->assertSame(0.0, PerhitunganDpnaBlok::normalisasi(10, 0));
        $this->assertSame(80.0, PerhitunganDpnaBlok::rataRata(collect([70, 90])));
        $this->assertNull(PerhitunganDpnaBlok::rataRata(collect()));
    }

    public function test_nilai_akhir_adalah_jumlah_nilai_dikali_bobot(): void
    {
        $this->assertSame(82.5, PerhitunganDpnaBlok::nilaiAkhir([
            ['aktif' => true, 'nilai' => 75.0, 'bobot' => 50.0],
            ['aktif' => true, 'nilai' => 90.0, 'bobot' => 50.0],
            ['aktif' => false, 'nilai' => 100.0, 'bobot' => 0.0],
        ]));
    }

    public function test_nilai_akhir_belum_lengkap_jika_sumber_aktif_kosong(): void
    {
        $this->assertNull(PerhitunganDpnaBlok::nilaiAkhir([
            ['aktif' => true, 'nilai' => 80.0, 'bobot' => 40.0],
            ['aktif' => true, 'nilai' => null, 'bobot' => 60.0],
        ]));

        $this->assertNull(PerhitunganDpnaBlok::nilaiAkhir([
            ['aktif' => false, 'nilai' => null, 'bobot' => 0.0],
        ]));
    }
}
