<?php

use App\Models\Menu;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Task 4 - Menu operasional mengikuti Blok, bukan Kelas.
 *
 * Menu `Kelas` dan `Kelas Sistem Blok` dipensiunkan. Rombel sekarang dikelola
 * sebagai tab di dalam detail Operasional Blok, sehingga tidak lagi perlu menu sendiri.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $parent = Menu::updateOrCreate(
            ['name' => 'Akademik', 'parent_id' => null],
            [
                'route' => null,
                'status' => true,
                'is_child_menu' => false,
                'position' => 20,
                'icon' => '<i class="ri-graduation-cap-line"></i>',
                'descriptions' => 'Menu akademik',
            ]
        );

        $menu = Menu::updateOrCreate(
            ['route' => 'blok-operasional.index'],
            [
                'name' => 'Operasional Blok',
                'status' => true,
                'is_child_menu' => true,
                'parent_id' => $parent->id,
                'position' => 70,
                'icon' => null,
                'descriptions' => 'Menu Operasional Blok',
            ]
        );

        $permission = Permission::updateOrCreate(
            [
                'name' => 'blok-operasional:',
                'guard_name' => 'web',
            ],
            [
                'menu_id' => $menu->id,
                'main_permission' => true,
                'descriptions' => 'akses Operasional Blok',
            ]
        );

        Role::whereIn('name', ['admin', 'pengelola'])->get()->each(function (Role $role) use ($permission) {
            $role->givePermissionTo($permission);
        });

        $this->removeMenus(['kelas.index', 'kelas-sistem-blok.index'], ['kelas:', 'kelas-sistem-blok:']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $parent = Menu::where('name', 'Akademik')->whereNull('parent_id')->first();

        if ($parent) {
            $this->restoreMenu($parent->id, 'Kelas', 'kelas.index', 70, 'kelas:');
            $this->restoreMenu($parent->id, 'Kelas Sistem Blok', 'kelas-sistem-blok.index', 80, 'kelas-sistem-blok:');
        }

        $this->removeMenus(['blok-operasional.index'], ['blok-operasional:']);
    }

    /**
     * @param  array<int, string>  $routes
     * @param  array<int, string>  $permissionNames
     */
    private function removeMenus(array $routes, array $permissionNames): void
    {
        Permission::whereIn('name', $permissionNames)->get()->each(fn (Permission $permission) => $permission->delete());

        Menu::whereIn('route', $routes)->delete();
    }

    private function restoreMenu(int $parentId, string $name, string $route, int $position, string $permissionName): void
    {
        $menu = Menu::updateOrCreate(
            ['route' => $route],
            [
                'name' => $name,
                'status' => true,
                'is_child_menu' => true,
                'parent_id' => $parentId,
                'position' => $position,
                'icon' => null,
                'descriptions' => 'Menu '.$name,
            ]
        );

        $permission = Permission::updateOrCreate(
            [
                'name' => $permissionName,
                'guard_name' => 'web',
            ],
            [
                'menu_id' => $menu->id,
                'main_permission' => true,
                'descriptions' => 'akses '.$name,
            ]
        );

        Role::whereIn('name', ['admin', 'pengelola'])->get()->each(function (Role $role) use ($permission) {
            $role->givePermissionTo($permission);
        });
    }
};
