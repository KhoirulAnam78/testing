<?php

use App\Models\Menu;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Menu portal mahasiswa: "Materi & Modul".
 *
 * Dipisah dari migration menu dosen supaya tiap tahap bisa dijalankan dan diuji
 * sendiri: menu yang menunjuk route yang belum terdaftar akan membuat navbar
 * melempar RouteNotFoundException bagi user yang punya izinnya.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
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

        // Menu induk wajib punya permission sendiri: query navbar memakai inner join
        // ke `permissions` dengan `main_permission = 1` untuk menu tanpa parent.
        $this->daftarkanPermission($parent, 'portal-saya:', 'akses Portal Saya', ['dosen', 'mahasiswa']);

        $menu = Menu::updateOrCreate(
            ['route' => 'materi-saya.index'],
            [
                'name' => 'Materi & Modul',
                'status' => true,
                'is_child_menu' => true,
                'parent_id' => $parent->id,
                'position' => 20,
                'icon' => null,
                'descriptions' => 'Menu Materi & Modul',
            ]
        );

        $this->daftarkanPermission($menu, 'materi-saya:', 'akses Materi & Modul', ['mahasiswa']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Permission::where('name', 'materi-saya:')->get()->each(fn (Permission $permission) => $permission->delete());
        Menu::where('route', 'materi-saya.index')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * `Role::findOrCreate` dipakai karena pada `migrate --seed` yang bersih migration
     * berjalan sebelum seeder role, sehingga pemberian izin akan gagal tanpa suara.
     *
     * @param  array<int, string>  $roles
     */
    private function daftarkanPermission(Menu $menu, string $name, string $descriptions, array $roles): void
    {
        $permission = Permission::updateOrCreate(
            [
                'name' => $name,
                'guard_name' => 'web',
            ],
            [
                'menu_id' => $menu->id,
                'main_permission' => true,
                'descriptions' => $descriptions,
            ]
        );

        foreach ($roles as $role) {
            Role::findOrCreate($role, 'web')->givePermissionTo($permission);
        }
    }
};
