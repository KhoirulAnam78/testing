<?php

namespace Tests\Feature;

use App\Imports\DosenImport;
use App\Imports\MahasiswaImport;
use App\Models\Dosen;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PersonAccountCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('dosen');
        Role::findOrCreate('mahasiswa');
    }

    public function test_input_dosen_membuat_akun_yang_bisa_login(): void
    {
        $prodi = Prodi::create(['kode' => 'MED', 'nama' => 'Kedokteran']);

        Livewire::test('pages::dosen.add_edit', ['id' => 'add'])
            ->set('prodi_id', $prodi->id_prodi)
            ->set('nidn', '0123456789')
            ->set('nama', 'Dosen Input')
            ->set('email', 'DOSEN.INPUT@example.test')
            ->call('save')
            ->assertHasNoErrors();

        $user = User::where('email', 'dosen.input@example.test')->firstOrFail();

        $this->assertSame($user->id, Dosen::where('nidn', '0123456789')->value('user_id'));
        $this->assertTrue($user->hasRole('dosen'));
        $this->assertTrue(Auth::attempt([
            'username' => '0123456789',
            'password' => '0123456789',
        ]));
    }

    public function test_input_mahasiswa_membuat_akun_yang_bisa_login(): void
    {
        $prodi = Prodi::create(['kode' => 'MED', 'nama' => 'Kedokteran']);

        Livewire::test('pages::mahasiswa.add_edit', ['id' => 'add'])
            ->set('prodi_id', $prodi->id_prodi)
            ->set('nim', '20260001')
            ->set('nama', 'Mahasiswa Input')
            ->set('email', 'MAHASISWA.INPUT@example.test')
            ->set('angkatan', 2026)
            ->call('save')
            ->assertHasNoErrors();

        $user = User::where('email', 'mahasiswa.input@example.test')->firstOrFail();

        $this->assertSame($user->id, Mahasiswa::where('nim', '20260001')->value('user_id'));
        $this->assertTrue($user->hasRole('mahasiswa'));
        $this->assertTrue(Auth::attempt([
            'username' => '20260001',
            'password' => '20260001',
        ]));
    }

    public function test_import_dosen_membuat_akun_yang_bisa_login(): void
    {
        Prodi::create(['kode' => 'MED', 'nama' => 'Kedokteran']);

        (new DosenImport)->collection(new Collection([
            new Collection([
                'nidn' => '0123456789',
                'nama' => 'Dosen Uji',
                'email' => 'DOSEN@example.test',
                'kode_prodi' => 'MED',
                'status' => 'aktif',
            ]),
        ]));

        $user = User::where('email', 'dosen@example.test')->firstOrFail();
        $dosen = Dosen::where('nidn', '0123456789')->firstOrFail();

        $this->assertSame($user->id, $dosen->user_id);
        $this->assertTrue($user->hasRole('dosen'));
        $this->assertTrue(Hash::check('0123456789', $user->password));
        $this->assertTrue(Auth::attempt([
            'username' => '0123456789',
            'password' => '0123456789',
        ]));
    }

    public function test_import_mahasiswa_membuat_akun_yang_bisa_login(): void
    {
        Prodi::create(['kode' => 'MED', 'nama' => 'Kedokteran']);

        (new MahasiswaImport)->collection(new Collection([
            new Collection([
                'nim' => '20260001',
                'nama' => 'Mahasiswa Uji',
                'email' => 'MAHASISWA@example.test',
                'kode_prodi' => 'MED',
                'angkatan' => '2026',
                'status' => 'aktif',
            ]),
        ]));

        $user = User::where('email', 'mahasiswa@example.test')->firstOrFail();
        $mahasiswa = Mahasiswa::where('nim', '20260001')->firstOrFail();

        $this->assertSame($user->id, $mahasiswa->user_id);
        $this->assertTrue($user->hasRole('mahasiswa'));
        $this->assertTrue(Hash::check('20260001', $user->password));
        $this->assertTrue(Auth::attempt([
            'username' => '20260001',
            'password' => '20260001',
        ]));
    }

    public function test_import_ulang_memakai_akun_lama_dan_tidak_mengubah_password(): void
    {
        Prodi::create(['kode' => 'MED', 'nama' => 'Kedokteran']);
        $rows = new Collection([
            new Collection([
                'nim' => '20260001',
                'nama' => 'Nama Awal',
                'email' => 'mahasiswa@example.test',
                'kode_prodi' => 'MED',
                'angkatan' => '2026',
                'status' => 'aktif',
            ]),
        ]);

        $import = new MahasiswaImport;
        $import->collection($rows);

        $user = User::where('email', 'mahasiswa@example.test')->firstOrFail();
        $user->update(['password' => 'password-baru']);

        $rows[0]['nama'] = 'Nama Diperbarui';
        $import->collection($rows);

        $this->assertSame(1, User::where('email', 'mahasiswa@example.test')->count());
        $this->assertSame($user->id, Mahasiswa::where('nim', '20260001')->value('user_id'));
        $this->assertTrue(Hash::check('password-baru', $user->fresh()->password));
    }

    public function test_import_ulang_menyinkronkan_nim_ke_username_tanpa_mengubah_password(): void
    {
        Prodi::create(['kode' => 'MED', 'nama' => 'Kedokteran']);
        $rows = new Collection([
            new Collection([
                'nim' => '20260001',
                'nama' => 'Mahasiswa Uji',
                'email' => 'mahasiswa@example.test',
                'kode_prodi' => 'MED',
                'angkatan' => '2026',
                'status' => 'aktif',
            ]),
        ]);

        $import = new MahasiswaImport;
        $import->collection($rows);
        $user = User::where('email', 'mahasiswa@example.test')->firstOrFail();
        $user->update(['password' => 'password-baru']);

        $rows[0]['nim'] = '20260002';
        $import->collection($rows);

        $this->assertSame('20260002', $user->fresh()->username);
        $this->assertSame('20260002', Mahasiswa::where('user_id', $user->id)->value('nim'));
        $this->assertTrue(Hash::check('password-baru', $user->fresh()->password));
    }

    public function test_import_ulang_menyinkronkan_nip_ke_username_tanpa_mengubah_password(): void
    {
        $rows = new Collection([
            new Collection([
                'nidn' => '0123456789',
                'nama' => 'Dosen Uji',
                'email' => 'dosen@example.test',
                'status' => 'aktif',
            ]),
        ]);

        $import = new DosenImport;
        $import->collection($rows);
        $user = User::where('email', 'dosen@example.test')->firstOrFail();
        $user->update(['password' => 'password-baru']);

        $rows[0]['nip'] = '198001012010011001';
        $import->collection($rows);

        $this->assertSame('198001012010011001', $user->fresh()->username);
        $this->assertTrue(Hash::check('password-baru', $user->fresh()->password));
    }

    public function test_import_menolak_username_identifier_yang_sudah_digunakan(): void
    {
        Prodi::create(['kode' => 'MED', 'nama' => 'Kedokteran']);
        User::factory()->create(['username' => '20260001']);

        $this->expectException(ValidationException::class);

        (new MahasiswaImport)->collection(new Collection([
            new Collection([
                'nim' => '20260001',
                'nama' => 'Mahasiswa Konflik',
                'email' => 'konflik@example.test',
                'kode_prodi' => 'MED',
                'angkatan' => '2026',
                'status' => 'aktif',
            ]),
        ]));
    }
}
