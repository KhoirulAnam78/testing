<?php

namespace Tests\Feature;

use App\Models\LogbookPertemuanBlok;
use App\Models\User;
use App\Support\AksesPertemuanBlok;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class LogbookPertemuanBlokTest extends TestCase
{
    use RefreshDatabase;

    public function test_flag_nonaktif_menolak_akses_server(): void
    {
        $data = $this->fixture(false);

        $this->actingAs($data['mahasiswa']);

        $this->assertFalse(AksesPertemuanBlok::bolehUnggahLogbook(
            $data['mahasiswa'],
            $data['pertemuan'],
            $data['mahasiswa_id']
        ));

        Livewire::test('logbook-pertemuan', ['pertemuan_blok_id' => $data['pertemuan']])
            ->assertNotFound();
    }

    public function test_mahasiswa_hanya_dapat_mengunggah_logbook_pertemuan_kelompoknya(): void
    {
        Storage::fake('local');
        $data = $this->fixture();
        $orangLain = User::factory()->create();

        $this->actingAs($orangLain);
        Livewire::test('logbook-pertemuan', ['pertemuan_blok_id' => $data['pertemuan']])
            ->assertForbidden();

        $this->actingAs($data['mahasiswa']);
        Livewire::test('logbook-pertemuan', ['pertemuan_blok_id' => $data['pertemuan']])
            ->set('file', UploadedFile::fake()->createWithContent('logbook.pdf', "%PDF-1.4\nuji"))
            ->call('unggah')
            ->assertHasNoErrors();

        $logbook = LogbookPertemuanBlok::firstOrFail();
        $this->assertSame('menunggu', $logbook->status);
        $this->assertSame('logbook.pdf', $logbook->nama_file_asli);
        Storage::disk('local')->assertExists($logbook->path_file);
    }

    public function test_unggahan_harus_pdf_maksimal_10240_kb(): void
    {
        Storage::fake('local');
        $data = $this->fixture();
        $this->actingAs($data['mahasiswa']);

        Livewire::test('logbook-pertemuan', ['pertemuan_blok_id' => $data['pertemuan']])
            ->set('file', UploadedFile::fake()->create('catatan.txt', 1, 'text/plain'))
            ->call('unggah')
            ->assertHasErrors(['file']);

        Livewire::test('logbook-pertemuan', ['pertemuan_blok_id' => $data['pertemuan']])
            ->set('file', UploadedFile::fake()->create('terlalu-besar.pdf', 10241, 'application/pdf'))
            ->call('unggah')
            ->assertHasErrors(['file']);
    }

    public function test_validator_sah_dapat_validasi_dan_tolak_dengan_catatan_wajib(): void
    {
        Storage::fake('local');
        $data = $this->fixture();
        $logbook = $this->logbook($data);

        $this->actingAs($data['dosen']);
        Livewire::test('logbook-pertemuan', ['pertemuan_blok_id' => $data['pertemuan']])
            ->call('tolak', $logbook->id)
            ->assertHasErrors(["catatan.{$logbook->id}"])
            ->set("catatan.{$logbook->id}", 'Halaman pengesahan belum ada.')
            ->call('tolak', $logbook->id)
            ->assertHasNoErrors();

        $logbook->refresh();
        $this->assertSame('ditolak', $logbook->status);
        $this->assertSame('Halaman pengesahan belum ada.', $logbook->catatan_validasi);
        $this->assertSame($data['dosen']->id, $logbook->divalidasi_oleh_user_id);

        Livewire::test('logbook-pertemuan', ['pertemuan_blok_id' => $data['pertemuan']])
            ->call('validasi', $logbook->id);

        $logbook->refresh();
        $this->assertSame('valid', $logbook->status);
        $this->assertNull($logbook->catatan_validasi);
        $this->assertNotNull($logbook->divalidasi_pada);
    }

    public function test_validator_tidak_sah_ditolak_dan_pengelola_dapat_memvalidasi(): void
    {
        $data = $this->fixture();
        $logbook = $this->logbook($data);
        $bukanValidator = User::factory()->create();

        $this->assertFalse(AksesPertemuanBlok::bolehValidasiLogbook($bukanValidator, $data['pertemuan']));

        Permission::findOrCreate(AksesPertemuanBlok::IZIN_PENGELOLA);
        $bukanValidator->givePermissionTo(AksesPertemuanBlok::IZIN_PENGELOLA);
        $this->assertTrue(AksesPertemuanBlok::bolehValidasiLogbook($bukanValidator, $data['pertemuan']));

        $this->actingAs($bukanValidator);
        Livewire::test('logbook-pertemuan', ['pertemuan_blok_id' => $data['pertemuan']])
            ->call('validasi', $logbook->id);

        $this->assertSame('valid', $logbook->refresh()->status);
    }

    public function test_upload_ulang_menghapus_file_lama_dan_mengembalikan_status_menunggu(): void
    {
        Storage::fake('local');
        $data = $this->fixture();
        $logbook = $this->logbook($data, 'ditolak');
        $pathLama = $logbook->path_file;
        Storage::disk('local')->put($pathLama, 'lama');

        $this->actingAs($data['mahasiswa']);
        Livewire::test('logbook-pertemuan', ['pertemuan_blok_id' => $data['pertemuan']])
            ->set('file', UploadedFile::fake()->createWithContent('revisi.pdf', "%PDF-1.4\nrevisi"))
            ->call('unggah')
            ->assertHasNoErrors();

        $logbook->refresh();
        $this->assertSame('menunggu', $logbook->status);
        $this->assertNull($logbook->catatan_validasi);
        $this->assertNull($logbook->divalidasi_pada);
        $this->assertNotSame($pathLama, $logbook->path_file);
        Storage::disk('local')->assertMissing($pathLama);
        Storage::disk('local')->assertExists($logbook->path_file);
    }

    public function test_unduh_private_hanya_untuk_pemilik_dan_validator(): void
    {
        Storage::fake('local');
        $data = $this->fixture();
        $logbook = $this->logbook($data);
        Storage::disk('local')->put($logbook->path_file, '%PDF-1.4');

        $this->actingAs($data['mahasiswa'])
            ->get(route('logbook.download', $logbook))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($data['dosen'])
            ->get(route('logbook.download', $logbook))
            ->assertOk();

        $this->actingAs(User::factory()->create())
            ->get(route('logbook.download', $logbook))
            ->assertForbidden();
    }

    private function logbook(array $data, string $status = 'menunggu'): LogbookPertemuanBlok
    {
        return LogbookPertemuanBlok::create([
            'pertemuan_blok_id' => $data['pertemuan'],
            'mahasiswa_id' => $data['mahasiswa_id'],
            'path_file' => "logbook/{$data['pertemuan']}/lama.pdf",
            'nama_file_asli' => 'lama.pdf',
            'ukuran_file' => 100,
            'status' => $status,
            'catatan_validasi' => $status === 'ditolak' ? 'Perlu revisi.' : null,
            'diunggah_pada' => now(),
        ]);
    }

    private function fixture(bool $aktif = true): array
    {
        $mahasiswaUser = User::factory()->create();
        $dosenUser = User::factory()->create();

        $prodi = DB::table('prodi')->insertGetId([
            'kode' => fake()->unique()->lexify('P???'),
            'nama' => 'Prodi Uji',
        ], 'id_prodi');
        $semester = DB::table('semester')
            ->where('nama', 'ganjil')
            ->where('tahun', 2026)
            ->value('id_semester')
            ?? DB::table('semester')->insertGetId([
                'kode' => fake()->unique()->lexify('S???'),
                'nama' => 'ganjil',
                'tahun' => 2026,
            ], 'id_semester');
        $dosen = DB::table('dosen')->insertGetId([
            'user_id' => $dosenUser->id,
            'nama' => 'Validator Uji',
        ], 'id_dosen');
        $mahasiswa = DB::table('mahasiswa')->insertGetId([
            'user_id' => $mahasiswaUser->id,
            'prodi_id' => $prodi,
            'nim' => fake()->unique()->numerify('########'),
            'nama' => 'Mahasiswa Uji',
            'angkatan' => 2026,
        ], 'id_mahasiswa');
        $blok = DB::table('blok')->insertGetId([
            'prodi_id' => $prodi,
            'semester_id' => $semester,
            'kode' => fake()->unique()->lexify('B???'),
            'nama' => 'Blok Uji',
            'sks' => 4,
        ]);
        $jenis = DB::table('jenis_kegiatan')->insertGetId([
            'kode' => fake()->unique()->lexify('J???'),
            'nama' => 'Tutorial Uji',
            'jumlah_pertemuan_default' => 1,
            'durasi_menit_default' => 100,
            'perlu_logbook' => $aktif,
        ]);
        $aturan = DB::table('aturan_kegiatan_blok')->insertGetId([
            'blok_id' => $blok,
            'jenis_kegiatan_id' => $jenis,
            'jumlah_pertemuan' => 1,
            'durasi_menit' => 100,
            'perlu_kelompok' => true,
            'perlu_logbook' => $aktif,
        ]);
        $materi = DB::table('materi_blok')->insertGetId([
            'aturan_kegiatan_blok_id' => $aturan,
            'judul' => 'Materi Uji',
        ], 'id_materi_blok');
        $materiRinci = DB::table('materi_rinci_blok')->insertGetId([
            'materi_blok_id' => $materi,
            'judul' => 'Pertemuan Uji',
        ], 'id_materi_rinci_blok');
        $peserta = DB::table('peserta_blok')->insertGetId([
            'blok_id' => $blok,
            'mahasiswa_id' => $mahasiswa,
        ], 'id_peserta_blok');
        $kelompok = DB::table('kelompok_blok')->insertGetId([
            'blok_id' => $blok,
            'aturan_kegiatan_blok_id' => $aturan,
            'kode' => 'K1',
            'nama' => 'Kelompok 1',
        ], 'id_kelompok_blok');
        DB::table('anggota_kelompok_blok')->insert([
            'kelompok_blok_id' => $kelompok,
            'peserta_blok_id' => $peserta,
        ]);
        $pertemuan = DB::table('pertemuan_blok')->insertGetId([
            'blok_id' => $blok,
            'aturan_kegiatan_blok_id' => $aturan,
            'materi_rinci_blok_id' => $materiRinci,
            'kelompok_blok_id' => $kelompok,
        ], 'id_pertemuan_blok');
        DB::table('dosen_pertemuan_blok')->insert([
            'pertemuan_blok_id' => $pertemuan,
            'dosen_id' => $dosen,
        ]);

        return [
            'mahasiswa' => $mahasiswaUser,
            'mahasiswa_id' => $mahasiswa,
            'dosen' => $dosenUser,
            'pertemuan' => $pertemuan,
        ];
    }
}