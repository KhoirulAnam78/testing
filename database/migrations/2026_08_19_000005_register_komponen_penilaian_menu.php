<?php

use App\Models\Menu;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Menu master "Komponen Penilaian" di bawah grup Akademik.
 *
 * Query navbar memakai inner join ke `permissions` dengan `main_permission = 1`, jadi
 * menu tanpa baris permission tidak pernah tampil dan menu child tanpa permission akan
 * error saat `@can(...)`. Karena itu menu dan permission selalu dibuat berpasangan.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Menu induk Akademik sudah dibuat sejak Task 2. Dicari dulu dan hanya dibuat bila
        // belum ada, supaya posisi, icon, dan deskripsi yang sudah diatur pengelola lewat
        // Kelola Menu tidak tertimpa.
        $parent = Menu::where('name', 'Akademik')->whereNull('parent_id')->first();

        if (! $parent) {
            $parent = Menu::create([
                'name' => 'Akademik',
                'parent_id' => null,
                'route' => null,
                'status' => true,
                'is_child_menu' => false,
                'position' => 20,
                'icon' => '<i class="ri-graduation-cap-line"></i>',
                'descriptions' => 'Menu master data akademik',
            ]);
        }

        $this->daftarkanPermission($parent, 'akademik:', 'akses Akademik', ['admin', 'pengelola']);

        $menu = Menu::updateOrCreate(
            ['route' => 'komponen-penilaian.index'],
            [
                'name' => 'Komponen Penilaian',
                'status' => true,
                'is_child_menu' => true,
                'parent_id' => $parent->id,
                'position' => 90,
                'icon' => null,
                'descriptions' => 'Menu Komponen Penilaian',
            ]
        );

        $this->daftarkanPermission(
            $menu,
            'komponen-penilaian:',
            'akses Komponen Penilaian',
            ['admin', 'pengelola']
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Permission::whereIn('name', ['komponen-penilaian:'])
            ->get()
            ->each(fn (Permission $permission) => $permission->delete());

        Menu::where('route', 'komponen-penilaian.index')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * `Role::findOrCreate` dipakai, bukan `Role::whereIn(...)->get()`, karena pada
     * `migrate --seed` yang bersih migration berjalan sebelum seeder role sehingga
     * pemberian izin akan gagal tanpa suara.
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
