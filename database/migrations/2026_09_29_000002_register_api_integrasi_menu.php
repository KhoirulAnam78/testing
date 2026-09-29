<?php

use App\Models\Menu;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $parent = Menu::firstOrCreate(
            ['name' => 'Pengaturan Aplikasi', 'parent_id' => null],
            [
                'route' => null,
                'status' => true,
                'is_child_menu' => false,
                'position' => 1,
                'icon' => '<i class="ri-settings-5-line"></i>',
                'descriptions' => 'Pengaturan aplikasi',
            ]
        );

        $this->daftarkanPermission($parent, 'pengaturan-aplikasi:', 'akses Pengaturan Aplikasi');

        $menu = Menu::updateOrCreate(
            ['route' => 'api-integrasi.index'],
            [
                'name' => 'API Integrasi',
                'status' => true,
                'is_child_menu' => true,
                'parent_id' => $parent->id,
                'position' => 5,
                'icon' => null,
                'descriptions' => 'Pengaturan akun API integrasi',
            ]
        );

        $this->daftarkanPermission($menu, 'api-integrasi:', 'akses API Integrasi');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        throw new RuntimeException('Rollback diblokir: menu dan permission API Integrasi tidak boleh dihapus otomatis.');
    }

    private function daftarkanPermission(Menu $menu, string $name, string $description): void
    {
        $permission = Permission::updateOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            ['menu_id' => $menu->id, 'main_permission' => true, 'descriptions' => $description]
        );

        Role::findOrCreate('admin', 'web')->givePermissionTo($permission);
    }
};
