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
            ['name' => 'Portal Saya', 'parent_id' => null],
            [
                'route' => null,
                'status' => true,
                'is_child_menu' => false,
                'position' => 30,
                'icon' => '<i class="ri-user-star-line"></i>',
                'descriptions' => 'Menu portal dosen dan mahasiswa',
            ]
        );

        $this->daftarkanPermission($parent, 'portal-saya:', 'akses Portal Saya', ['dosen', 'mahasiswa']);

        $menu = Menu::updateOrCreate(['route' => 'kontrak-blok-saya.index'], [
            'name' => 'Kontrak Blok Saya',
            'status' => true,
            'is_child_menu' => true,
            'parent_id' => $parent->id,
            'position' => 10,
            'icon' => null,
            'descriptions' => 'Menu Kontrak Blok Saya',
        ]);

        $this->daftarkanPermission($menu, 'kontrak-blok-saya:', 'akses Kontrak Blok Saya', ['mahasiswa']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        throw new RuntimeException('Rollback diblokir: menu dan permission kontrak blok tidak boleh dihapus otomatis.');
    }

    private function daftarkanPermission(Menu $menu, string $name, string $description, array $roles): void
    {
        $permission = Permission::updateOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            ['menu_id' => $menu->id, 'main_permission' => true, 'descriptions' => $description]
        );

        foreach ($roles as $role) {
            Role::findOrCreate($role, 'web')->givePermissionTo($permission);
        }
    }
};
