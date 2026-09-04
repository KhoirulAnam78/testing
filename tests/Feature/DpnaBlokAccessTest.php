<?php

namespace Tests\Feature;

use App\Models\Blok;
use App\Models\Dosen;
use App\Models\PengelolaBlok;
use App\Models\Prodi;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DpnaBlokAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dengan_permission_dapat_membuka_daftar_dan_detail(): void
    {
        $user = User::factory()->create();
        Permission::findOrCreate('dpna-blok:');
        $user->givePermissionTo('dpna-blok:');
        $blok = $this->blok();

        $this->actingAs($user)->get(route('dpna-blok.index'))->assertOk()->assertSee('DPNA Blok');
        $this->get(route('dpna-blok.detail', Crypt::encrypt($blok->id)))->assertOk()->assertSee($blok->nama);
    }

    public function test_koordinator_hanya_dapat_membuka_blok_kelolaannya(): void
    {
        $user = User::factory()->create();
        $dosen = Dosen::create(['user_id' => $user->id, 'nama' => 'Koordinator DPNA']);
        $milik = $this->blok(['kode' => 'DPNA-A']);
        PengelolaBlok::create(['blok_id' => $milik->id, 'dosen_id' => $dosen->id_dosen, 'jabatan' => 'koordinator']);
        $lain = $this->blok(['kode' => 'DPNA-B']);

        $this->actingAs($user)->get(route('dpna-blok.index'))->assertOk();
        $this->get(route('dpna-blok.detail', Crypt::encrypt($milik->id)))->assertOk();
        $this->get(route('dpna-blok.detail', Crypt::encrypt($lain->id)))->assertRedirectToRoute('dashboard');
    }

    public function test_semua_jabatan_pengelola_dapat_membuka_dpna_bloknya(): void
    {
        foreach (['koordinator', 'asisten_koordinator', 'kontributor'] as $jabatan) {
            $user = User::factory()->create();
            $dosen = Dosen::create(['user_id' => $user->id, 'nama' => str($jabatan)->headline()]);
            $blok = $this->blok(['kode' => 'DPNA-'.str()->random(5)]);
            PengelolaBlok::create(compact('jabatan') + ['blok_id' => $blok->id, 'dosen_id' => $dosen->id_dosen]);

            $this->actingAs($user)->get(route('dpna-blok.detail', Crypt::encrypt($blok->id)))->assertOk();
            $this->get(route('blok-operasional.detail', Crypt::encrypt($blok->id)))->assertOk();
        }
    }

    public function test_dosen_biasa_dan_mahasiswa_ditolak(): void
    {
        $blok = $this->blok();

        foreach ([User::factory()->create(), User::factory()->create()] as $user) {
            $this->actingAs($user)->get(route('dpna-blok.index'))->assertRedirectToRoute('dashboard');
            $this->get(route('dpna-blok.detail', Crypt::encrypt($blok->id)))->assertRedirectToRoute('dashboard');
        }
    }

    private function blok(array $attributes = []): Blok
    {
        $prodi = Prodi::firstOrCreate(['kode' => 'DPNA'], ['nama' => 'Prodi DPNA']);
        $semester = Semester::firstOrCreate(
            ['nama' => 'ganjil', 'tahun' => 2026],
            ['kode' => '2026-DPNA']
        );

        return Blok::create(array_merge([
            'prodi_id' => $prodi->id_prodi,
            'semester_id' => $semester->id_semester,
            'kode' => 'DPNA-'.str()->random(5),
            'nama' => 'Blok Uji DPNA',
            'sks' => 4,
        ], $attributes));
    }
}
