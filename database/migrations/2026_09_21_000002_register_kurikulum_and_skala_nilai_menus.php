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
                'descriptions' => 'Menu akademik',
            ]);
        }

        $this->daftarkan($parent, 'akademik:', 'akses Akademik');
        $this->daftarkanMenu($parent, 'Kurikulum', 'kurikulum.index', 'kurikulum:', 45);
        $this->daftarkanMenu($parent, 'Skala Nilai', 'skala-nilai.index', 'skala-nilai:', 46);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', [
            'kurikulum:', 'kurikulum:tambah-data', 'kurikulum:edit-data',
            'skala-nilai:', 'skala-nilai:tambah-data', 'skala-nilai:edit-data',
        ])->get()->each->delete();
        Menu::whereIn('route', ['kurikulum.index', 'skala-nilai.index'])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function daftarkanMenu(Menu $parent, string $name, string $route, string $permission, int $position): void
    {
        $menu = Menu::updateOrCreate(['route' => $route], [
            'name' => $name,
            'status' => true,
            'is_child_menu' => true,
            'parent_id' => $parent->id,
            'position' => $position,
            'icon' => null,
            'descriptions' => 'Menu '.$name,
        ]);

        $this->daftarkan($menu, $permission, 'akses '.$name);
        $this->daftarkan($menu, $permission.'tambah-data', 'akses tambah '.$name, false);
        $this->daftarkan($menu, $permission.'edit-data', 'akses edit '.$name, false);
    }

    private function daftarkan(Menu $menu, string $name, string $description, bool $utama = true): void
    {
        $permission = Permission::updateOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            ['menu_id' => $menu->id, 'main_permission' => $utama, 'descriptions' => $description]
        );

        foreach (['admin', 'pengelola'] as $role) {
            Role::findOrCreate($role, 'web')->givePermissionTo($permission);
        }
    }
};
