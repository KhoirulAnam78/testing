<?php

namespace Tests\Feature;

use App\Livewire\TableUsers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class LoginAsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_dengan_permission_dapat_login_as_dan_kembali(): void
    {
        $admin = User::factory()->create();
        $target = User::factory()->create();
        Permission::create(['name' => 'kelola-user:']);
        $admin->givePermissionTo('kelola-user:');

        $this->actingAs($admin);

        Livewire::test(TableUsers::class)
            ->call('loginAs', Crypt::encrypt($target->id))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($target);
        $this->assertSame($admin->id, session('login_as_original_user_id'));

        Livewire::test('layouts::header')
            ->call('stopLoginAs')
            ->assertRedirect(route('users.index'));

        $this->assertAuthenticatedAs($admin);
        $this->assertFalse(session()->has('login_as_original_user_id'));
    }

    public function test_user_tanpa_permission_ditolak(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(TableUsers::class)
            ->call('loginAs', Crypt::encrypt($target->id))
            ->assertForbidden();

        $this->assertAuthenticatedAs($user);
        $this->assertFalse(session()->has('login_as_original_user_id'));
    }

    public function test_login_as_diri_sendiri_ditolak(): void
    {
        $admin = User::factory()->create();
        Permission::create(['name' => 'kelola-user:']);
        $admin->givePermissionTo('kelola-user:');

        $this->actingAs($admin);

        Livewire::test(TableUsers::class)
            ->call('loginAs', Crypt::encrypt($admin->id))
            ->assertStatus(422);

        $this->assertAuthenticatedAs($admin);
    }

    public function test_hapus_user_tanpa_child_permission_ditolak(): void
    {
        $admin = User::factory()->create();
        $target = User::factory()->create();
        Permission::create(['name' => 'kelola-user:']);
        $admin->givePermissionTo('kelola-user:');

        $this->actingAs($admin);

        Livewire::test(TableUsers::class)
            ->call('deleteUser', Crypt::encrypt($target->id))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_hapus_user_dengan_child_permission_berhasil(): void
    {
        $admin = User::factory()->create();
        $target = User::factory()->create();
        Permission::create(['name' => 'kelola-user:hapus-user']);
        $admin->givePermissionTo('kelola-user:hapus-user');

        $this->actingAs($admin);

        Livewire::test(TableUsers::class)
            ->call('deleteUser', Crypt::encrypt($target->id))
            ->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_login_as_bertingkat_ditolak(): void
    {
        $admin = User::factory()->create();
        $target = User::factory()->create();
        Permission::create(['name' => 'kelola-user:']);
        $admin->givePermissionTo('kelola-user:');

        $this->actingAs($admin)->withSession(['login_as_original_user_id' => 999]);

        Livewire::test(TableUsers::class)
            ->call('loginAs', Crypt::encrypt($target->id))
            ->assertForbidden();

        $this->assertAuthenticatedAs($admin);
    }
}
