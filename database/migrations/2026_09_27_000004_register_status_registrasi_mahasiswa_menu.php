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

        $this->daftarkanPermission($parent, 'akademik:', 'akses Akademik');

        $menu = Menu::updateOrCreate(['route' => 'status-registrasi-mahasiswa.index'], [
            'name' => 'Status Registrasi Mahasiswa',
            'status' => true,
            'is_child_menu' => true,
            'parent_id' => $parent->id,
            'position' => 48,
            'icon' => null,
            'descriptions' => 'Menu Status Registrasi Mahasiswa',
        ]);

        $this->daftarkanPermission(
            $menu,
            'status-registrasi-mahasiswa:',
            'akses Status Registrasi Mahasiswa'
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        throw new RuntimeException('Rollback diblokir: menu dan permission status registrasi tidak boleh dihapus otomatis.');
    }

    private function daftarkanPermission(Menu $menu, string $name, string $description): void
    {
        $permission = Permission::updateOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            ['menu_id' => $menu->id, 'main_permission' => true, 'descriptions' => $description]
        );

        foreach (['admin', 'pengelola'] as $role) {
            Role::findOrCreate($role, 'web')->givePermissionTo($permission);
        }
    }
};
