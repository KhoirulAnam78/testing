<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
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
        Role::findOrCreate('admin');
        Role::findOrCreate('Developer');
        Permission::findOrCreate('materi-saya:');
        Permission::findOrCreate('pertemuan-saya:');
        Carbon::setTestNow('2026-09-15 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_mahasiswa_melihat_dashboard_pribadi_dan_agenda_miliknya_saja(): void
    {
        [$user, $mahasiswaId] = $this->mahasiswa('Mahasiswa Dashboard', '20260001');
        [, $mahasiswaAsingId] = $this->mahasiswa('Mahasiswa Asing', '20260002');
        $user->givePermissionTo('materi-saya:');

        $this->buatAgendaMahasiswa($mahasiswaId, 'Agenda Mahasiswa Sendiri', 'K-SENDIRI', '2026-09-14');
        $this->buatAgendaMahasiswa($mahasiswaAsingId, 'Agenda Mahasiswa Asing', 'K-ASING', '2026-09-14');

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

        $this->buatAgendaDosen($dosenId, 'Agenda Dosen Sendiri', 'K-DOSEN', '2026-09-15');
        $this->buatAgendaDosen($dosenAsingId, 'Agenda Dosen Asing', 'K-ASING', '2026-09-15');

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Portal Dosen')
            ->assertSeeText('Pertemuan Saya')
            ->assertSeeText('Kalender Mengajar')
            ->assertSeeText('Agenda Dosen Sendiri')
            ->assertDontSeeText('Agenda Dosen Asing')
            ->assertDontSeeText('Portal Mahasiswa')
            ->assertDontSeeText('Pusat Pengelolaan');
    }

    public function test_mahasiswa_melihat_kalender_jadwal_milik_kelompoknya_saja(): void
    {
        [$user, $mahasiswaId] = $this->mahasiswa('Mahasiswa Kalender', '20260003');
        [, $mahasiswaAsingId] = $this->mahasiswa('Mahasiswa Kalender Asing', '20260004');

        $this->buatAgendaMahasiswa($mahasiswaId, 'Jadwal Mahasiswa Dibatalkan', 'K-MHS', '2026-09-20', 'batal');
        $this->buatAgendaMahasiswa($mahasiswaAsingId, 'Jadwal Mahasiswa Asing', 'K-ASING', '2026-09-20');
        $this->buatAgendaMahasiswa($mahasiswaId, 'Jadwal Mahasiswa Tanpa Tanggal', 'K-KOSONG', null);
        $this->actingAs($user);

        Livewire::test('pages::dashboard.index')
            ->assertSet('bulanAktif', '2026-09')
            ->assertCount('hariKalender', 35)
            ->assertSeeText('Kalender Perkuliahan')
            ->assertSeeText('Jadwal Mahasiswa Dibatalkan')
            ->assertDontSeeHtml('status-batal')
            ->assertDontSeeText('Legenda status')
            ->assertDontSeeText('Jadwal Mahasiswa Asing')
            ->assertDontSeeText('Jadwal Mahasiswa Tanpa Tanggal')
            ->call('bukaDetailTanggal', '2026-09-20')
            ->assertSet('modalDetailTerbuka', true)
            ->assertSeeText('Detail Kegiatan');
    }

    public function test_kalender_dosen_memakai_tanggal_rencana_tanpa_menampilkan_status(): void
    {
        [$user, $dosenId] = $this->dosen('Dosen Kalender', '1111111111');
        $this->buatAgendaDosen($dosenId, 'Agenda Lampau', 'K-LAMPAU', '2026-09-02', 'selesai');
        $pertemuanId = $this->buatAgendaDosen($dosenId, 'Agenda Dibatalkan', 'K-BATAL', '2026-09-20', 'batal');
        $this->buatAgendaDosen($dosenId, 'Agenda Tanpa Tanggal', 'K-KOSONG', null);
        DB::table('monitoring_pertemuan_blok')->insert([
            'pertemuan_blok_id' => $pertemuanId,
            'status_pelaksanaan' => 'terlaksana',
            'tanggal_realisasi' => '2026-09-22',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user);

        Livewire::test('pages::dashboard.index')
            ->assertSet('bulanAktif', '2026-09')
            ->assertSet('tanggalTerpilih', '2026-09-15')
            ->assertCount('hariKalender', 35)
            ->assertSeeHtml('wire:target="bulanSebelumnya,bulanBerikutnya,keHariIni,pilihTanggal,bukaDetailTanggal"')
            ->assertSeeText('Memuat kalender...')
            ->assertSeeText('Agenda Lampau')
            ->assertSeeText('Agenda Dibatalkan')
            ->assertDontSeeHtml('status-batal')
            ->assertDontSeeText('Terjadwal')
            ->assertDontSeeText('Agenda Tanpa Tanggal')
            ->call('bukaDetailTanggal', '2026-09-20')
            ->assertSeeHtml("detail-agenda-$pertemuanId")
            ->assertSeeText('Agenda Dibatalkan')
            ->assertSeeText('Realisasi: 22 September 2026')
            ->call('bukaDetailTanggal', '2026-09-22')
            ->assertSet('tanggalTerpilih', '2026-09-22')
            ->assertDontSeeHtml("detail-agenda-$pertemuanId");
    }

    public function test_dosen_dapat_navigasi_ke_bulan_lampau_dan_mendatang(): void
    {
        [$user, $dosenId] = $this->dosen('Dosen Navigasi', '2222222222');
        $this->buatAgendaDosen($dosenId, 'Agenda Agustus', 'K-AGUSTUS', '2026-08-10');
        $this->buatAgendaDosen($dosenId, 'Agenda Oktober', 'K-OKTOBER', '2026-10-10');
        $this->actingAs($user);

        Livewire::test('pages::dashboard.index')
            ->call('bulanSebelumnya')
            ->assertSet('bulanAktif', '2026-08')
            ->assertCount('hariKalender', 42)
            ->assertSeeText('Agenda Agustus')
            ->assertDontSeeText('Agenda Oktober')
            ->call('bulanBerikutnya')
            ->call('bulanBerikutnya')
            ->assertSet('bulanAktif', '2026-10')
            ->assertSeeText('Agenda Oktober')
            ->assertDontSeeText('Agenda Agustus')
            ->call('keHariIni')
            ->assertSet('bulanAktif', '2026-09')
            ->assertSet('tanggalTerpilih', '2026-09-15');
    }

    public function test_dosen_dapat_membuka_dan_menutup_modal_detail_kegiatan(): void
    {
        [$user, $dosenId] = $this->dosen('Dosen Modal', '3333333333');
        $this->buatAgendaDosen($dosenId, 'Agenda Dalam Modal', 'K-MODAL', '2026-09-20');
        $this->actingAs($user);

        Livewire::test('pages::dashboard.index')
            ->assertSet('modalDetailTerbuka', false)
            ->assertSeeHtml('has-agenda')
            ->call('bukaDetailTanggal', '2026-09-20')
            ->assertSet('tanggalTerpilih', '2026-09-20')
            ->assertSet('modalDetailTerbuka', true)
            ->assertSeeText('Detail Kegiatan')
            ->assertSeeText('Agenda Dalam Modal')
            ->call('tutupDetailTanggal')
            ->assertSet('modalDetailTerbuka', false)
            ->call('bukaDetailTanggal', '2026-09-21')
            ->assertSet('modalDetailTerbuka', false);
    }

    public function test_dosen_tanpa_profil_mendapat_peringatan_tanpa_data_kalender(): void
    {
        $user = User::factory()->create(['name' => 'Dosen Tanpa Profil']);
        $user->assignRole('dosen');

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Akun belum terhubung ke data dosen')
            ->assertSeeText('Kalender Mengajar')
            ->assertDontSeeText('Agenda Dosen Sendiri');
    }

    public function test_pengelola_tetap_mendapat_dashboard_operasional(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Pusat Pengelolaan')
            ->assertSeeText('Program Studi')
            ->assertSeeText('Kalender Akademik')
            ->assertDontSeeText('Portal Mahasiswa')
            ->assertDontSeeText('Portal Dosen');
    }

    public function test_admin_dan_developer_melihat_semua_pertemuan_di_kalender_akademik(): void
    {
        [, $dosenPertamaId] = $this->dosen('Dosen Kalender Pertama', '4444444444');
        [, $dosenKeduaId] = $this->dosen('Dosen Kalender Kedua', '5555555555');
        $this->buatAgendaDosen($dosenPertamaId, 'Agenda Semua Blok Pertama', 'K-GLOBAL-1', '2026-09-20');
        $this->buatAgendaDosen($dosenKeduaId, 'Agenda Semua Blok Kedua', 'K-GLOBAL-2', '2026-09-21', 'batal');
        $this->buatAgendaDosen($dosenKeduaId, 'Agenda Global Tanpa Tanggal', 'K-GLOBAL-3', null);

        foreach (['admin', 'Developer'] as $role) {
            $user = User::factory()->create(['name' => "Pengguna $role"]);
            $user->assignRole($role);
            $this->actingAs($user);

            Livewire::test('pages::dashboard.index')
                ->assertSet('bulanAktif', '2026-09')
                ->assertCount('hariKalender', 35)
                ->assertSeeText('Pusat Pengelolaan')
                ->assertSeeText('Kalender Akademik')
                ->assertSeeText('Agenda Semua Blok Pertama')
                ->assertSeeText('Agenda Semua Blok Kedua')
                ->assertDontSeeText('Agenda Global Tanpa Tanggal')
                ->call('bukaDetailTanggal', '2026-09-20')
                ->assertSet('modalDetailTerbuka', true)
                ->assertSeeText('Dosen Kalender Pertama')
                ->call('tutupDetailTanggal')
                ->call('bulanSebelumnya')
                ->assertSet('bulanAktif', '2026-08')
                ->call('bulanBerikutnya')
                ->call('bulanBerikutnya')
                ->assertSet('bulanAktif', '2026-10')
                ->call('keHariIni')
                ->assertSet('bulanAktif', '2026-09');
        }
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

    private function buatAgendaMahasiswa(int $mahasiswaId, string $judul, string $kode, ?string $tanggal, string $status = 'terjadwal'): void
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
        $this->pertemuan($blokId, $aturanId, $materiId, $kelompokId, $judul, $tanggal, $status);
    }

    private function buatAgendaDosen(int $dosenId, string $judul, string $kode, ?string $tanggal, string $status = 'terjadwal'): int
    {
        [$blokId, $aturanId, $materiId] = $this->operasional($judul);
        $pertemuanId = $this->pertemuan($blokId, $aturanId, $materiId, $this->kelompok($blokId, $aturanId, $kode), $judul, $tanggal, $status);
        DB::table('dosen_pertemuan_blok')->insert([
            'pertemuan_blok_id' => $pertemuanId, 'dosen_id' => $dosenId,
            'peran' => 'pengampu', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $pertemuanId;
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
            'prodi_id' => $this->prodi(), 'semester_id' => $semesterId,
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

    private function pertemuan(int $blokId, int $aturanId, int $materiId, int $kelompokId, string $judul, ?string $tanggal, string $status = 'terjadwal'): int
    {
        return DB::table('pertemuan_blok')->insertGetId([
            'blok_id' => $blokId, 'aturan_kegiatan_blok_id' => $aturanId,
            'materi_rinci_blok_id' => $materiId, 'kelompok_blok_id' => $kelompokId,
            'tanggal' => $tanggal, 'jam_mulai' => '08:00', 'topik' => $judul,
            'status' => $status, 'created_at' => now(), 'updated_at' => now(),
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
