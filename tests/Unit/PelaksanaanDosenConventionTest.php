<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PelaksanaanDosenConventionTest extends TestCase
{
    public function test_dosen_uses_one_form_for_monitoring_and_presensi(): void
    {
        $contents = file_get_contents(__DIR__.'/../../resources/views/pages/pertemuan-saya/index.blade.php');
        $jurnalContents = file_get_contents(__DIR__.'/../../resources/views/components/blok-operasional/jurnal-pertemuan.blade.php');

        $this->assertStringContainsString("MODE_PELAKSANAAN = ['pelaksanaan', 'nilai', 'logbook']", $contents);
        $this->assertStringContainsString(
            '<x-full-page-loading target="kelolaModul,kelolaPelaksanaan" message="Memuat data pertemuan..." />',
            $contents
        );
        $this->assertStringContainsString('<th>Tanggal Pelaksanaan</th>', $contents);
        $this->assertStringContainsString('$item->monitoring_pertemuan_blok?->tanggal_realisasi', $contents);
        $this->assertStringNotContainsString('<th>Jadwal</th>', $contents);
        $this->assertStringContainsString('<div class="d-flex flex-column align-items-start gap-1">', $contents);
        $this->assertStringContainsString('<div class="small fw-semibold text-wrap">', $contents);
        $this->assertStringContainsString('<div class="text-muted small">Jadwal Rencana</div>', $jurnalContents);
        $this->assertStringContainsString("wire:click=\"kelolaPelaksanaan('{{ \$item->id_pertemuan_blok }}', 'pelaksanaan')\"", $contents);
        $this->assertStringContainsString('<i class="ri-booklet-line"></i> Isi Monitoring', $contents);
        $this->assertStringContainsString("wire:click=\"kelolaPelaksanaan('{{ \$item->id_pertemuan_blok }}', 'nilai')\"", $contents);
        $this->assertStringContainsString('<i class="ri-graduation-cap-line"></i> Nilai', $contents);
        $this->assertStringContainsString('<h5 class="modal-title">Monitoring Pertemuan</h5>', $contents);
        $this->assertStringNotContainsString('> Pelaksanaan', $contents);
        $this->assertStringContainsString('<livewire:blok-operasional.jurnal-pertemuan', $contents);
        $this->assertStringContainsString('<livewire:blok-operasional.presensi-pertemuan', $contents);
        $this->assertSame(2, substr_count($contents, ':tampilkan_tombol_simpan="false"'));
        $this->assertStringContainsString('wire:click="simpanPelaksanaan"', $contents);
        $this->assertStringContainsString('wire:click="validasiPelaksanaan"', $contents);
        $this->assertStringContainsString('$this->jurnal_tersimpan', $contents);
        $this->assertStringContainsString('$this->presensi_tersimpan', $contents);
        $this->assertMatchesRegularExpression(
            '/validasi_setelah_simpan\s+&& \$this->jurnal_tersimpan\s+&& \$this->presensi_tersimpan/',
            $contents
        );
    }
}
