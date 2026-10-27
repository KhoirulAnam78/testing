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
        $parent = Menu::updateOrCreate(
            ['name' => 'Sinkronisasi', 'parent_id' => null],
            [
                'route' => null,
                'status' => true,
                'is_child_menu' => false,
                'position' => 28,
                'icon' => '<i class="ri-exchange-line"></i>',
                'descriptions' => 'Kelas kuliah outbound dan DPNA final',
            ]
        );

        $this->daftarkan($parent, 'sinkronisasi:', 'akses Sinkronisasi');
        $this->daftarkanMenu($parent, 'Dashboard Sinkronisasi', 'sinkronisasi.index', 'sinkronisasi-kelola:', 10);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Sengaja kosong: menu dan permission dinonaktifkan, bukan dihapus otomatis.
    }

    private function daftarkanMenu(Menu $parent, string $nama, string $route, string $permission, int $position): void
    {
        $menu = Menu::updateOrCreate(['route' => $route], [
            'name' => $nama,
            'status' => true,
            'is_child_menu' => true,
            'parent_id' => $parent->id,
            'position' => $position,
            'icon' => null,
            'descriptions' => 'Menu '.$nama,
        ]);

        $this->daftarkan($menu, $permission, 'akses '.$nama);
    }

    private function daftarkan(Menu $menu, string $nama, string $deskripsi): void
    {
        $permission = Permission::updateOrCreate(
            ['name' => $nama, 'guard_name' => 'web'],
            ['menu_id' => $menu->id, 'main_permission' => true, 'descriptions' => $deskripsi]
        );

        foreach (['admin', 'pengelola'] as $role) {
            Role::findOrCreate($role, 'web')->givePermissionTo($permission);
        }
    }
};
