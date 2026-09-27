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

        $menu = Menu::updateOrCreate(['route' => 'khs-saya.index'], [
            'name' => 'KHS Saya',
            'status' => true,
            'is_child_menu' => true,
            'parent_id' => $parent->id,
            'position' => 40,
            'icon' => null,
            'descriptions' => 'Menu Kartu Hasil Studi Saya',
        ]);

        $permission = Permission::updateOrCreate(
            ['name' => 'khs-saya:', 'guard_name' => 'web'],
            ['menu_id' => $menu->id, 'main_permission' => true, 'descriptions' => 'akses KHS Saya']
        );

        Role::findOrCreate('mahasiswa', 'web')->givePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        throw new RuntimeException('Rollback diblokir: menu dan permission KHS saya tidak boleh dihapus otomatis.');
    }
};