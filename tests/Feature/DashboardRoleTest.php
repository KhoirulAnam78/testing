<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('mahasiswa');
        Role::findOrCreate('dosen');
        Permission::findOrCreate('materi-saya:');
        Permission::findOrCreate('pertemuan-saya:');
    }

    public function test_mahasiswa_melihat_dashboard_pribadi_dan_agenda_miliknya_saja(): void
    {
        [$user, $mahasiswaId] = $this->mahasiswa('Mahasiswa Dashboard', '20260001');
        [, $mahasiswaAsingId] = $this->mahasiswa('Mahasiswa Asing', '20260002');
        $user->givePermissionTo('materi-saya:');

        $this->buatAgendaMahasiswa($mahasiswaId, 'Agenda Mahasiswa Sendiri', 'K-SENDIRI');
        $this->buatAgendaMahasiswa($mahasiswaAsingId, 'Agenda Mahasiswa Asing', 'K-ASING');

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Portal Mahasiswa')
            ->assertSeeText('Materi & Modul')
            ->assertSeeText('Agenda Mahasiswa Sendiri')
            ->assertDontSeeText('Agenda Mahasiswa Asing')
            ->assertDontSeeText('Portal Dosen')
            ->assertDontSeeText('Pusat Pengelolaan');
    }

    public function test_dosen_melihat_dashboard_pribadi_dan_agenda_miliknya_saja(): void
    {
        [$user, $dosenId] = $this->dosen('Dosen Dashboard', '0123456789');
        [, $dosenAsingId] = $this->dosen('Dosen Asing', '9876543210');
        $user->givePermissionTo('pertemuan-saya:');

        $this->buatAgendaDosen($dosenId, 'Agenda Dosen Sendiri', 'K-DOSEN');
        $this->buatAgendaDosen($dosenAsingId, 'Agenda Dosen Asing', 'K-ASING');

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Portal Dosen')
            ->assertSeeText('Pertemuan Saya')
            ->assertSeeText('Agenda Dosen Sendiri')
            ->assertDontSeeText('Agenda Dosen Asing')
            ->assertDontSeeText('Portal Mahasiswa')
            ->assertDontSeeText('Pusat Pengelolaan');
    }

    public function test_pengelola_tetap_mendapat_dashboard_operasional(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Pusat Pengelolaan')
            ->assertSeeText('Program Studi')
            ->assertDontSeeText('Portal Mahasiswa')
            ->assertDontSeeText('Portal Dosen');
    }

    private function mahasiswa(string $nama, string $nim): array
    {
        $user = User::factory()->create(['name' => $nama]);
        $user->assignRole('mahasiswa');
        $id = DB::table('mahasiswa')->insertGetId([
            'user_id' => $user->id, 'prodi_id' => $this->prodi(), 'nim' => $nim,
            'nama' => $nama, 'angkatan' => 2026, 'status' => 'aktif',
            'created_at' => now(), 'updated_at' => now(),
        ], 'id_mahasiswa');

        return [$user, $id];
    }

    private function dosen(string $nama, string $nidn): array
    {
        $user = User::factory()->create(['name' => $nama]);
        $user->assignRole('dosen');
        $id = DB::table('dosen')->insertGetId([
            'user_id' => $user->id, 'prodi_id' => $this->prodi(), 'nidn' => $nidn,
            'nama' => $nama, 'status' => 'aktif', 'created_at' => now(), 'updated_at' => now(),
        ], 'id_dosen');

        return [$user, $id];
    }

    private function buatAgendaMahasiswa(int $mahasiswaId, string $judul, string $kode): void
    {
        [$blokId, $aturanId, $materiId] = $this->operasional($judul);
        $kelompokId = $this->kelompok($blokId, $aturanId, $kode);
        $pesertaId = DB::table('peserta_blok')->insertGetId([
            'blok_id' => $blokId, 'mahasiswa_id' => $mahasiswaId, 'status' => 'aktif',
            'created_at' => now(), 'updated_at' => now(),
        ], 'id_peserta_blok');
        DB::table('anggota_kelompok_blok')->insert([
            'kelompok_blok_id' => $kelompokId, 'peserta_blok_id' => $pesertaId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->pertemuan($blokId, $aturanId, $materiId, $kelompokId, $judul);
    }

    private function buatAgendaDosen(int $dosenId, string $judul, string $kode): void
    {
        [$blokId, $aturanId, $materiId] = $this->operasional($judul);
        $pertemuanId = $this->pertemuan($blokId, $aturanId, $materiId, $this->kelompok($blokId, $aturanId, $kode), $judul);
        DB::table('dosen_pertemuan_blok')->insert([
            'pertemuan_blok_id' => $pertemuanId, 'dosen_id' => $dosenId,
            'peran' => 'pengampu', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function operasional(string $judul): array
    {
        $suffix = str_replace(' ', '-', strtolower($judul));
        $tahun = 2100 + DB::table('semester')->count();
        $semesterId = DB::table('semester')->insertGetId([
            'nama' => 'ganjil', 'tahun' => $tahun, 'kode' => "SEM-$suffix",
            'is_aktif' => false, 'created_at' => now(), 'updated_at' => now(),
        ], 'id_semester');
        $blokId = DB::table('blok')->insertGetId([
            'prodi_id' => $this->prodi(), 'semester_id' => $semesterId, 'kode' => "BL-$suffix",
            'nama' => "Blok $judul", 'sks' => 4, 'status' => 'aktif', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $jenisId = DB::table('jenis_kegiatan')->insertGetId([
            'kode' => "JK-$suffix", 'nama' => 'Tutorial', 'jumlah_pertemuan_default' => 1,
            'durasi_menit_default' => 100, 'status' => 'aktif', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $aturanId = DB::table('aturan_kegiatan_blok')->insertGetId([
            'blok_id' => $blokId, 'jenis_kegiatan_id' => $jenisId,
            'durasi_menit' => 100, 'perlu_kelompok' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $materiBlokId = DB::table('materi_blok')->insertGetId([
            'aturan_kegiatan_blok_id' => $aturanId, 'judul' => $judul,
            'status' => 'aktif', 'created_at' => now(), 'updated_at' => now(),
        ], 'id_materi_blok');
        $materiId = DB::table('materi_rinci_blok')->insertGetId([
            'materi_blok_id' => $materiBlokId, 'judul' => $judul, 'status' => 'aktif',
            'created_at' => now(), 'updated_at' => now(),
        ], 'id_materi_rinci_blok');

        return [$blokId, $aturanId, $materiId];
    }

    private function kelompok(int $blokId, int $aturanId, string $kode): int
    {
        return DB::table('kelompok_blok')->insertGetId([
            'blok_id' => $blokId, 'aturan_kegiatan_blok_id' => $aturanId, 'kode' => $kode,
            'nama' => $kode, 'status' => 'aktif', 'created_at' => now(), 'updated_at' => now(),
        ], 'id_kelompok_blok');
    }

    private function pertemuan(int $blokId, int $aturanId, int $materiId, int $kelompokId, string $judul): int
    {
        return DB::table('pertemuan_blok')->insertGetId([
            'blok_id' => $blokId, 'aturan_kegiatan_blok_id' => $aturanId,
            'materi_rinci_blok_id' => $materiId, 'kelompok_blok_id' => $kelompokId,
            'tanggal' => today(), 'jam_mulai' => '08:00', 'topik' => $judul,
            'status' => 'terjadwal', 'created_at' => now(), 'updated_at' => now(),
        ], 'id_pertemuan_blok');
    }

    private function prodi(): int
    {
        return DB::table('prodi')->value('id_prodi') ?? DB::table('prodi')->insertGetId([
            'kode' => 'KED', 'nama' => 'Kedokteran', 'status' => 'aktif',
            'created_at' => now(), 'updated_at' => now(),
        ], 'id_prodi');
    }
}
