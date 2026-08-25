<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RouteMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'route.permission'])->group(function (): void {
            Route::get('/middleware-test', fn () => 'OK')->name('middleware-test.index');
            Route::get('/middleware-test/{id}', fn () => 'OK')->name('middleware-test.add_edit');
            Route::get('/middleware-unregistered', fn () => 'OK')->name('middleware-unregistered.index');
        });
    }

    public function test_route_tanpa_menu_tetap_dapat_diakses(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/middleware-unregistered')
            ->assertOk()
            ->assertSeeText('OK');
    }

    public function test_user_dengan_permission_dapat_mengakses_menu(): void
    {
        [$user] = $this->createMenuAccess();
        $user->givePermissionTo('middleware-test:');

        $this->actingAs($user)
            ->get('/middleware-test')
            ->assertOk()
            ->assertSeeText('OK');
    }

    public function test_user_tanpa_permission_dialihkan_ke_dashboard(): void
    {
        $this->createMenuAccess();

        $this->actingAs(User::factory()->create())
            ->get('/middleware-test')
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('failed', 'Anda tidak memiliki akses ke halaman ini.');
    }

    public function test_route_turunan_dan_menu_nonaktif_ditolak(): void
    {
        [$user, $menu] = $this->createMenuAccess();
        $user->givePermissionTo('middleware-test:');
        $menu->update(['status' => false]);

        $this->actingAs($user)
            ->get('/middleware-test/edit')
            ->assertRedirect(route('dashboard'));
    }

    public function test_route_form_tambah_memerlukan_permission_tambah(): void
    {
        [$user] = $this->createMenuAccess();
        $this->createActionPermission('middleware-test:tambah data');

        $this->actingAs($user)
            ->get('/middleware-test/add')
            ->assertRedirect(route('dashboard'));

        $user->givePermissionTo('middleware-test:tambah data');

        $this->actingAs($user)
            ->get('/middleware-test/add')
            ->assertOk()
            ->assertSeeText('OK');
    }

    public function test_route_form_edit_memerlukan_permission_edit(): void
    {
        [$user] = $this->createMenuAccess();
        $this->createActionPermission('middleware-test:edit data');

        $this->actingAs($user)
            ->get('/middleware-test/encrypted-id')
            ->assertRedirect(route('dashboard'));

        $user->givePermissionTo('middleware-test:edit data');

        $this->actingAs($user)
            ->get('/middleware-test/encrypted-id')
            ->assertOk()
            ->assertSeeText('OK');
    }

    private function createMenuAccess(): array
    {
        $user = User::factory()->create();
        $menu = Menu::create([
            'name' => 'Middleware Test',
            'route' => 'middleware-test.index',
            'status' => true,
        ]);

        Permission::create([
            'name' => 'middleware-test:',
            'guard_name' => 'web',
            'menu_id' => $menu->id,
            'main_permission' => true,
        ]);

        return [$user, $menu];
    }

    private function createActionPermission(string $name): Permission
    {
        return Permission::create([
            'name' => $name,
            'guard_name' => 'web',
            'menu_id' => Menu::where('route', 'middleware-test.index')->value('id'),
            'main_permission' => false,
        ]);
    }
}
