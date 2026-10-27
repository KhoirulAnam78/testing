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
        $parent = Menu::query()
            ->where('name', 'Sinkronisasi')
            ->whereNull('parent_id')
            ->firstOrFail();

        $parent->update(['descriptions' => 'Sinkronisasi master data dan pengelolaan kelas']);

        Menu::query()
            ->where('route', 'sinkronisasi.index')
            ->update(['name' => 'Dashboard Sinkronisasi', 'position' => 10]);

        $menu = Menu::updateOrCreate(['route' => 'sinkronisasi-kelas.index'], [
            'name' => 'Sinkronisasi Kelas',
            'status' => true,
            'is_child_menu' => true,
            'parent_id' => $parent->id,
            'position' => 20,
            'icon' => null,
            'descriptions' => 'Pengelolaan kelas lokal dan kesiapan sinkronisasi kelas',
        ]);

        $permission = Permission::updateOrCreate(
            ['name' => 'sinkronisasi-kelas:', 'guard_name' => 'web'],
            ['menu_id' => $menu->id, 'main_permission' => true, 'descriptions' => 'akses Sinkronisasi Kelas']
        );

        foreach (['admin', 'pengelola'] as $role) {
            Role::findOrCreate($role, 'web')->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()
            ->where('name', 'sinkronisasi-kelas:')
            ->get()
            ->each(fn (Permission $permission) => $permission->delete());
        Menu::query()->where('route', 'sinkronisasi-kelas.index')->delete();
        Menu::query()
            ->where('name', 'Sinkronisasi')
            ->whereNull('parent_id')
            ->update(['descriptions' => 'Kelas kuliah outbound dan DPNA final']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
