<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_tidak_dapat_membuka_halaman_profil(): void
    {
        $this->get('/profile')->assertRedirect('/login');
    }

    public function test_halaman_profil_dapat_dibuka_user_login(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('Profil & Akun', false)
            ->assertSee($user->username);
    }

    public function test_informasi_akun_dapat_diperbarui(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::profile')
            ->set('name', 'Nama Baru')
            ->set('username', 'username-baru')
            ->set('email', 'BARU@example.test')
            ->call('updateProfile')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertSame('Nama Baru', $user->name);
        $this->assertSame('username-baru', $user->username);
        $this->assertSame('baru@example.test', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_username_dan_email_harus_unik(): void
    {
        $user = User::factory()->create();
        $lain = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::profile')
            ->set('username', $lain->username)
            ->set('email', $lain->email)
            ->call('updateProfile')
            ->assertHasErrors(['username' => 'unique', 'email' => 'unique']);
    }

    public function test_foto_profil_dapat_diunggah_dan_foto_lama_dihapus(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('foto-profil/lama.jpg', 'foto-lama');
        $user = User::factory()->create(['foto_profil' => 'foto-profil/lama.jpg']);

        Livewire::actingAs($user)
            ->test('pages::profile')
            ->set('foto', UploadedFile::fake()->image('baru.jpg', 300, 300)->size(100))
            ->call('updateProfile')
            ->assertHasNoErrors();

        $pathBaru = $user->refresh()->foto_profil;
        $this->assertNotSame('foto-profil/lama.jpg', $pathBaru);
        Storage::disk('public')->assertExists($pathBaru);
        Storage::disk('public')->assertMissing('foto-profil/lama.jpg');
    }

    public function test_file_bukan_gambar_ditolak(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::profile')
            ->set('foto', UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'))
            ->call('updateProfile')
            ->assertHasErrors(['foto' => 'image']);

        $this->assertNull($user->refresh()->foto_profil);
    }

    public function test_foto_lebih_dari_dua_mb_ditolak(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::profile')
            ->set('foto', UploadedFile::fake()->image('besar.jpg')->size(2049))
            ->call('updateProfile')
            ->assertHasErrors(['foto' => 'max']);

        $this->assertNull($user->refresh()->foto_profil);
    }

    public function test_password_dapat_diperbarui_dengan_password_saat_ini(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::profile')
            ->set('current_password', 'password')
            ->set('password', 'password-baru')
            ->set('password_confirmation', 'password-baru')
            ->call('updatePassword')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('password-baru', $user->refresh()->password));
    }

    public function test_password_saat_ini_harus_benar(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::profile')
            ->set('current_password', 'salah')
            ->set('password', 'password-baru')
            ->set('password_confirmation', 'password-baru')
            ->call('updatePassword')
            ->assertHasErrors(['current_password' => 'current_password']);

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }
}
