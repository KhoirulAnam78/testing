<?php

namespace Tests\Feature;

use App\Models\Blok;
use App\Models\Dosen;
use App\Models\JenisKegiatan;
use App\Models\KomponenPenilaian;
use App\Models\Prodi;
use App\Models\Semester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class RincianMateriTanpaBatasPertemuanTest extends TestCase
{
    use RefreshDatabase;

    public function test_rincian_baru_memakai_durasi_kegiatan_dan_override_tetap_dipertahankan(): void
    {
        Livewire::test('pages::blok.add_edit', ['id' => 'add'])
            ->set('aturan.0.durasi_menit', 120)
            ->call('addRinci', 0, 0)
            ->assertSet('aturan.0.materi.0.rinci.0.durasi_menit_per_sesi', 120)
            ->set('aturan.0.materi.0.rinci.0.durasi_menit_per_sesi', 75)
            ->set('aturan.0.durasi_menit', 180)
            ->assertSet('aturan.0.materi.0.rinci.0.durasi_menit_per_sesi', 75);
    }

    public function test_jam_selesai_rencana_dihitung_dari_jumlah_dan_durasi_sesi(): void
    {
        Livewire::test('pages::blok.add_edit', ['id' => 'add'])
            ->set('aturan.0.durasi_menit', 120)
            ->call('addRinci', 0, 0)
            ->set('aturan.0.materi.0.rinci.0.jam_mulai_rencana', '08:00')
            ->assertSet('aturan.0.materi.0.rinci.0.jam_selesai_rencana', '10:00')
            ->set('aturan.0.materi.0.rinci.0.durasi_menit_per_sesi', 75)
            ->assertSet('aturan.0.materi.0.rinci.0.jam_selesai_rencana', '09:15')
            ->set('aturan.0.materi.0.rinci.0.jumlah_sesi', 2)
            ->assertSet('aturan.0.materi.0.rinci.0.jam_selesai_rencana', '10:30');
    }

    public function test_jam_selesai_rencana_bisa_diubah_setelah_dihitung_otomatis(): void
    {
        Livewire::test('pages::blok.add_edit', ['id' => 'add'])
            ->set('aturan.0.durasi_menit', 120)
            ->call('addRinci', 0, 0)
            ->set('aturan.0.materi.0.rinci.0.jam_mulai_rencana', '08:00')
            ->set('aturan.0.materi.0.rinci.0.jam_selesai_rencana', '09:30')
            ->assertSet('aturan.0.materi.0.rinci.0.jam_selesai_rencana', '09:30');
    }

    public function test_tab_review_menampilkan_total_pertemuan_dari_jumlah_sesi(): void
    {
        Livewire::test('pages::blok.add_edit', ['id' => 'add'])
            ->call('addRinci', 0, 0)
            ->call('addRinci', 0, 0)
            ->set('aturan.0.materi.0.rinci.0.jumlah_sesi', 3)
            ->set('aturan.0.materi.0.rinci.1.jumlah_sesi', 4)
            ->set('active_tab', 'review')
            ->assertSeeInOrder(['Pertemuan', '7']);
    }

    public function test_satu_kegiatan_menyimpan_banyak_rincian_materi_tanpa_batas_pertemuan(): void
    {
        $prodi = Prodi::create(['kode' => 'PU', 'nama' => 'Prodi Uji']);
        $semester = Semester::firstOrCreate(
            ['nama' => 'ganjil', 'tahun' => 2026],
            ['kode' => '2026-GANJIL']
        );
        $koordinator = Dosen::create(['nama' => 'Koordinator Uji']);
        $asisten = Dosen::create(['nama' => 'Asisten Uji']);
        $jenis = JenisKegiatan::create([
            'kode' => 'TUT-UJI',
            'nama' => 'Tutorial Uji',
            'jumlah_pertemuan_default' => 1,
            'durasi_menit_default' => 100,
        ]);

        $aturan = [[
            'id' => null,
            'jenis_kegiatan_id' => (string) $jenis->id,
            'durasi_menit' => 100,
            'jumlah_mahasiswa_per_kelompok' => null,
            'perlu_kelompok' => true,
            'perlu_presensi' => true,
            'perlu_logbook' => false,
            'perlu_penilaian' => false,
            'urutan' => 1,
            'komponen' => [],
            'materi' => [[
                'id' => null,
                'judul' => 'Materi utama',
                'deskripsi' => '',
                'capaian_pembelajaran' => '',
                'urutan' => 1,
                'status' => 'aktif',
                'rinci' => collect(range(1, 3))->map(fn (int $pertemuan) => [
                    'id' => null,
                    'judul' => "Rincian {$pertemuan}",
                    'deskripsi' => '',
                    'capaian_pembelajaran' => '',
                    'referensi' => '',
                    'pertemuan_ke' => $pertemuan,
                    'tanggal_rencana' => null,
                    'jam_mulai_rencana' => null,
                    'jam_selesai_rencana' => null,
                    'jumlah_sesi' => 1,
                    'durasi_menit_per_sesi' => $pertemuan === 2 ? 75 : 100,
                    'urutan' => $pertemuan,
                    'status' => 'aktif',
                ])->all(),
            ]],
        ]];

        Livewire::test('pages::blok.add_edit', ['id' => 'add'])
            ->set('prodi_id', (string) $prodi->id_prodi)
            ->set('semester_id', (string) $semester->id_semester)
            ->set('koordinator_id', (string) $koordinator->id_dosen)
            ->set('asisten_koordinator_id', (string) $asisten->id_dosen)
            ->set('kode', 'BLOK-UJI')
            ->set('nama', 'Blok Uji')
            ->set('sks', 1)
            ->set('aturan', $aturan)
            ->call('save')
            ->assertHasNoErrors();

        $blok = Blok::query()->where('kode', 'BLOK-UJI')->sole();
        $rincian = DB::table('materi_rinci_blok')
            ->join('materi_blok', 'materi_blok.id_materi_blok', '=', 'materi_rinci_blok.materi_blok_id')
            ->join('aturan_kegiatan_blok', 'aturan_kegiatan_blok.id', '=', 'materi_blok.aturan_kegiatan_blok_id')
            ->where('aturan_kegiatan_blok.blok_id', $blok->id);

        $this->assertSame(3, $rincian->count());
        $this->assertSame([1, 2, 3], $rincian->orderBy('materi_rinci_blok.urutan')->pluck('materi_rinci_blok.pertemuan_ke')->all());
        $this->assertSame([100, 75, 100], $rincian->orderBy('materi_rinci_blok.urutan')->pluck('materi_rinci_blok.durasi_menit_per_sesi')->all());
    }

    public function test_lanjut_mengisi_menyimpan_materi_lalu_berputar_dari_kegiatan_terakhir_ke_pertama(): void
    {
        $prodi = Prodi::create(['kode' => 'PL', 'nama' => 'Prodi Lanjut']);
        $semester = Semester::firstOrCreate(
            ['nama' => 'ganjil', 'tahun' => 2026],
            ['kode' => '2026-GANJIL']
        );
        $koordinator = Dosen::create(['nama' => 'Koordinator Lanjut']);
        $asisten = Dosen::create(['nama' => 'Asisten Lanjut']);
        $jenisKegiatan = collect(['Tutorial', 'Praktikum'])->map(fn (string $nama, int $index) => JenisKegiatan::create([
            'kode' => 'LANJUT-'.($index + 1),
            'nama' => $nama.' Lanjut',
            'jumlah_pertemuan_default' => 1,
            'durasi_menit_default' => 100,
        ]));
        $aturan = $jenisKegiatan->map(fn (JenisKegiatan $jenis, int $index) => [
            'id' => null,
            'jenis_kegiatan_id' => (string) $jenis->id,
            'durasi_menit' => 100,
            'jumlah_mahasiswa_per_kelompok' => null,
            'perlu_kelompok' => true,
            'perlu_presensi' => true,
            'perlu_logbook' => false,
            'perlu_penilaian' => false,
            'urutan' => $index + 1,
            'komponen' => [],
            'materi' => [[
                'id' => null,
                'judul' => $index === 0 ? 'Materi '.$jenis->nama : '',
                'deskripsi' => '',
                'capaian_pembelajaran' => '',
                'urutan' => 1,
                'status' => 'aktif',
                'rinci' => [],
            ]],
        ])->all();

        $component = Livewire::test('pages::blok.add_edit', ['id' => 'add'])
            ->set('prodi_id', (string) $prodi->id_prodi)
            ->set('semester_id', (string) $semester->id_semester)
            ->set('koordinator_id', (string) $koordinator->id_dosen)
            ->set('asisten_koordinator_id', (string) $asisten->id_dosen)
            ->set('kode', 'BLOK-LANJUT')
            ->set('nama', 'Blok Lanjut')
            ->set('sks', 1)
            ->set('aturan', $aturan)
            ->set('active_tab', 'materi')
            ->set('active_aturan_index', 0)
            ->assertSee('LANJUT MENGISI')
            ->call('saveAndNextKegiatan')
            ->assertHasNoErrors()
            ->assertSet('active_tab', 'materi')
            ->assertSet('active_aturan_index', 1);

        $this->assertDatabaseHas('materi_blok', ['judul' => 'Materi Tutorial Lanjut']);
        $this->assertDatabaseMissing('materi_blok', ['judul' => 'Materi Praktikum Lanjut']);

        $component
            ->set('aturan.1.materi.0.judul', 'Materi Praktikum Diperbarui')
            ->call('saveAndNextKegiatan')
            ->assertHasNoErrors()
            ->assertSet('active_tab', 'materi')
            ->assertSet('active_aturan_index', 0);

        $this->assertDatabaseHas('materi_blok', ['judul' => 'Materi Praktikum Diperbarui']);

        $component
            ->set('aturan.0.materi.0.judul', '')
            ->call('saveAndNextKegiatan')
            ->assertHasErrors(['aturan.0.materi.0.judul' => 'required'])
            ->assertSet('active_tab', 'materi')
            ->assertSet('active_aturan_index', 0);

        $this->assertDatabaseHas('materi_blok', ['judul' => 'Materi Tutorial Lanjut']);
    }

    public function test_lanjut_mengisi_menyimpan_penilaian_per_kegiatan_lalu_berputar_dan_mempertahankan_id(): void
    {
        $prodi = Prodi::create(['kode' => 'PN', 'nama' => 'Prodi Penilaian']);
        $semester = Semester::firstOrCreate(
            ['nama' => 'ganjil', 'tahun' => 2026],
            ['kode' => '2026-GANJIL']
        );
        $koordinator = Dosen::create(['nama' => 'Koordinator Penilaian']);
        $asisten = Dosen::create(['nama' => 'Asisten Penilaian']);
        $jenisKegiatan = collect(['Tutorial Nilai', 'Praktikum Nilai'])->map(fn (string $nama, int $index) => JenisKegiatan::create([
            'kode' => 'NILAI-'.($index + 1),
            'nama' => $nama,
            'jumlah_pertemuan_default' => 1,
            'durasi_menit_default' => 100,
        ]));
        $komponen = $jenisKegiatan->map(fn (JenisKegiatan $jenis, int $index) => KomponenPenilaian::create([
            'jenis_kegiatan_id' => $jenis->id,
            'kode' => 'KOMP-'.($index + 1),
            'nama' => 'Komponen '.($index + 1),
            'nilai_min_default' => 0,
            'nilai_maks_default' => 100,
            'urutan' => 1,
            'status' => 'aktif',
        ]));
        $aturan = $jenisKegiatan->map(fn (JenisKegiatan $jenis, int $index) => [
            'id' => null,
            'jenis_kegiatan_id' => (string) $jenis->id,
            'durasi_menit' => 100,
            'jumlah_mahasiswa_per_kelompok' => null,
            'perlu_kelompok' => true,
            'perlu_presensi' => true,
            'perlu_logbook' => false,
            'perlu_penilaian' => true,
            'urutan' => $index + 1,
            'komponen' => $index === 0 ? [[
                'id' => null,
                'komponen_penilaian_id' => (string) $komponen[$index]->id,
                'nilai_min' => 0,
                'nilai_maks' => 100,
                'urutan' => 1,
            ]] : [],
            'materi' => [[
                'id' => null,
                'judul' => 'Materi '.$jenis->nama,
                'deskripsi' => '',
                'capaian_pembelajaran' => '',
                'urutan' => 1,
                'status' => 'aktif',
                'rinci' => [],
            ]],
        ])->all();

        Livewire::test('pages::blok.add_edit', ['id' => 'add'])
            ->set('prodi_id', (string) $prodi->id_prodi)
            ->set('semester_id', (string) $semester->id_semester)
            ->set('koordinator_id', (string) $koordinator->id_dosen)
            ->set('asisten_koordinator_id', (string) $asisten->id_dosen)
            ->set('kode', 'BLOK-NILAI-GAGAL')
            ->set('nama', 'Blok Penilaian Gagal')
            ->set('sks', 1)
            ->set('aturan', $aturan)
            ->set('aturan.0.komponen.0.nilai_maks', 0)
            ->set('active_tab', 'penilaian')
            ->set('active_aturan_index', 0)
            ->call('saveAndNextKegiatan')
            ->assertHasErrors(['aturan.0.komponen.0.nilai_maks'])
            ->assertSet('edit_id', null)
            ->assertSet('active_aturan_index', 0);

        $this->assertDatabaseMissing('blok', ['kode' => 'BLOK-NILAI-GAGAL']);

        $component = Livewire::test('pages::blok.add_edit', ['id' => 'add'])
            ->set('prodi_id', (string) $prodi->id_prodi)
            ->set('semester_id', (string) $semester->id_semester)
            ->set('koordinator_id', (string) $koordinator->id_dosen)
            ->set('asisten_koordinator_id', (string) $asisten->id_dosen)
            ->set('kode', 'BLOK-NILAI')
            ->set('nama', 'Blok Penilaian')
            ->set('sks', 1)
            ->set('aturan', $aturan)
            ->set('active_tab', 'penilaian')
            ->set('active_aturan_index', 0)
            ->assertSee('LANJUT MENGISI')
            ->call('saveAndNextKegiatan')
            ->assertHasNoErrors()
            ->assertSet('active_tab', 'penilaian')
            ->assertSet('active_aturan_index', 1);

        $blokId = DB::table('blok')->where('kode', 'BLOK-NILAI')->value('id');
        $aturanIds = DB::table('aturan_kegiatan_blok')
            ->where('blok_id', $blokId)
            ->orderBy('urutan')
            ->pluck('id');
        $this->assertDatabaseHas('komponen_penilaian_blok', [
            'aturan_kegiatan_blok_id' => $aturanIds[0],
            'komponen_penilaian_id' => $komponen[0]->id,
        ]);
        $this->assertDatabaseMissing('komponen_penilaian_blok', [
            'aturan_kegiatan_blok_id' => $aturanIds[1],
            'komponen_penilaian_id' => $komponen[1]->id,
        ]);

        $component
            ->set('aturan.1.komponen', [[
                'id' => null,
                'komponen_penilaian_id' => (string) $komponen[1]->id,
                'nilai_min' => 0,
                'nilai_maks' => 100,
                'urutan' => 1,
            ]])
            ->call('saveAndNextKegiatan')
            ->assertHasNoErrors()
            ->assertSet('active_aturan_index', 0);

        $komponenPertama = DB::table('komponen_penilaian_blok')
            ->where('aturan_kegiatan_blok_id', $aturanIds[0])
            ->where('komponen_penilaian_id', $komponen[0]->id)
            ->first();

        $component
            ->set('aturan.0.komponen.0.nilai_maks', 90)
            ->call('saveAndNextKegiatan')
            ->assertHasNoErrors()
            ->assertSet('active_aturan_index', 1);

        $this->assertDatabaseHas('komponen_penilaian_blok', [
            'id' => $komponenPertama->id,
            'nilai_maks' => 90,
        ]);
        $this->assertSame(1, DB::table('komponen_penilaian_blok')
            ->where('aturan_kegiatan_blok_id', $aturanIds[0])
            ->where('komponen_penilaian_id', $komponen[0]->id)
            ->count());

        $component
            ->set('aturan.1.komponen.0.nilai_maks', 0)
            ->call('saveAndNextKegiatan')
            ->assertHasErrors(['aturan.1.komponen.0.nilai_maks'])
            ->assertSet('active_tab', 'penilaian')
            ->assertSet('active_aturan_index', 1);

        $this->assertDatabaseHas('komponen_penilaian_blok', [
            'aturan_kegiatan_blok_id' => $aturanIds[1],
            'komponen_penilaian_id' => $komponen[1]->id,
            'nilai_maks' => 100,
        ]);

        $component
            ->set('aturan.0.komponen.0.nilai_maks', 80)
            ->set('aturan.1.komponen.0.nilai_maks', 70)
            ->call('saveCurrentTab')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('komponen_penilaian_blok', [
            'aturan_kegiatan_blok_id' => $aturanIds[0],
            'nilai_maks' => 80,
        ]);
        $this->assertDatabaseHas('komponen_penilaian_blok', [
            'aturan_kegiatan_blok_id' => $aturanIds[1],
            'nilai_maks' => 70,
        ]);

        $component
            ->set('active_aturan_index', 0)
            ->set('aturan.0.perlu_penilaian', false)
            ->set('aturan.0.komponen', [])
            ->call('saveAndNextKegiatan')
            ->assertHasNoErrors()
            ->assertSet('active_aturan_index', 1);

        $this->assertSoftDeleted('komponen_penilaian_blok', [
            'aturan_kegiatan_blok_id' => $aturanIds[0],
            'komponen_penilaian_id' => $komponen[0]->id,
        ]);
        $this->assertDatabaseHas('komponen_penilaian_blok', [
            'aturan_kegiatan_blok_id' => $aturanIds[1],
            'komponen_penilaian_id' => $komponen[1]->id,
            'nilai_maks' => 70,
            'deleted_at' => null,
        ]);
    }
}
