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
        $this->assertStringContainsString('#[Url(as: \'semester\')]', $contents);
        $this->assertStringContainsString('<th>Jadwal / Realisasi</th>', $contents);
        $this->assertStringContainsString('$item->monitoring_pertemuan_blok?->tanggal_realisasi', $contents);
        $this->assertStringContainsString('<option value="">Semua semester</option>', $contents);
        $this->assertStringNotContainsString("#[Url(as: 'urut')]", $contents);
        $this->assertStringNotContainsString('wire:model.live="urutan"', $contents);
        $this->assertMatchesRegularExpression('/<div class="col-12">\s*<label class="form-label">Cari<\/label>/', $contents);
        $this->assertStringContainsString('<th>Kelengkapan</th>', $contents);
        $this->assertStringContainsString('<div class="d-flex flex-column align-items-start gap-1">', $contents);
        $this->assertStringContainsString('<div class="small fw-semibold text-wrap">', $contents);
        $this->assertStringContainsString('<div class="text-muted small">Jadwal Rencana</div>', $jurnalContents);
        $this->assertStringContainsString("wire:click=\"kelolaPelaksanaan('{{ \$item->id_pertemuan_blok }}', 'pelaksanaan')\"", $contents);
        $this->assertStringContainsString("{{ \$jurnal?->divalidasi_pada ? 'Lihat Monitoring' : 'Isi Monitoring' }}", $contents);
        $this->assertStringContainsString('class="btn btn-primary btn-sm"', $contents);
        $this->assertStringContainsString('class="btn btn-secondary btn-sm mt-1"', $contents);
        $this->assertStringContainsString("wire:click=\"kelolaPelaksanaan('{{ \$item->id_pertemuan_blok }}', 'nilai')\"", $contents);
        $this->assertStringContainsString('class="btn btn-info btn-sm mt-1"', $contents);
        $this->assertStringContainsString('<i class="ri-graduation-cap-line"></i> Nilai', $contents);
        $this->assertStringContainsString('class="btn btn-warning btn-sm mt-1"', $contents);
        $this->assertStringNotContainsString('dropdown-toggle-split', $contents);
        $this->assertStringContainsString('<h5 class="modal-title">Monitoring Pertemuan</h5>', $contents);
        $this->assertStringContainsString('@if ($pelaksanaan_perlu_penilaian)', $contents);
        $this->assertStringContainsString('@if ($pelaksanaan_perlu_logbook)', $contents);
        $this->assertStringNotContainsString('> Pelaksanaan', $contents);
        $this->assertStringContainsString('<livewire:blok-operasional.jurnal-pertemuan', $contents);
        $this->assertStringContainsString('<livewire:blok-operasional.presensi-pertemuan', $contents);
        $this->assertSame(2, substr_count($contents, ':tampilkan_tombol_simpan="false"'));
        $this->assertStringContainsString('wire:click="simpanPelaksanaan"', $contents);
        $this->assertStringContainsString('wire:click="validasiPelaksanaan"', $contents);
        $this->assertMatchesRegularExpression('/<div class="modal-footer">.*wire:click="simpanPelaksanaan".*wire:click="validasiPelaksanaan"/s', $contents);
        $this->assertStringContainsString('$this->jurnal_tersimpan', $contents);
        $this->assertStringContainsString('$this->presensi_tersimpan', $contents);
        $this->assertMatchesRegularExpression(
            '/validasi_setelah_simpan\s+&& \$this->jurnal_tersimpan\s+&& \$this->presensi_tersimpan/',
            $contents
        );
    }
}
