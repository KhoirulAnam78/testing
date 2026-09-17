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
        $kelolaBlok = Menu::where('name', 'Kelola Blok')->whereNull('parent_id')->first()
            ?? Menu::create([
                'name' => 'Kelola Blok',
                'route' => null,
                'status' => true,
                'is_child_menu' => false,
                'parent_id' => null,
                'position' => 25,
                'icon' => '<i class="ri-layout-grid-line"></i>',
                'descriptions' => 'Menu pengelolaan blok',
            ]);

        $this->daftarkanPermission($kelolaBlok, 'kelola-blok:', 'akses Kelola Blok', ['admin', 'pengelola']);

        $rekapPengelola = Menu::updateOrCreate(
            ['route' => 'rekap-sks-dosen.index'],
            [
                'name' => 'Rekap SKS Dosen',
                'status' => true,
                'is_child_menu' => true,
                'parent_id' => $kelolaBlok->id,
                'position' => 20,
                'icon' => null,
                'descriptions' => 'Rekap pembagian SKS dosen per pertemuan',
            ]
        );
        $this->daftarkanPermission($rekapPengelola, 'rekap-sks-dosen:', 'akses Rekap SKS Dosen', ['admin', 'pengelola']);

        $portal = Menu::where('name', 'Portal Saya')->whereNull('parent_id')->first()
            ?? Menu::create([
                'name' => 'Portal Saya',
                'route' => null,
                'status' => true,
                'is_child_menu' => false,
                'parent_id' => null,
                'position' => 30,
                'icon' => '<i class="ri-user-star-line"></i>',
                'descriptions' => 'Menu portal dosen dan mahasiswa',
            ]);

        $this->daftarkanPermission($portal, 'portal-saya:', 'akses Portal Saya', ['dosen', 'mahasiswa']);

        $rekapDosen = Menu::updateOrCreate(
            ['route' => 'rekap-sks-saya.index'],
            [
                'name' => 'Rekap SKS Saya',
                'status' => true,
                'is_child_menu' => true,
                'parent_id' => $portal->id,
                'position' => 20,
                'icon' => null,
                'descriptions' => 'Rekap pembagian SKS dosen yang login',
            ]
        );
        $this->daftarkanPermission($rekapDosen, 'rekap-sks-saya:', 'akses Rekap SKS Saya', ['dosen']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', ['rekap-sks-dosen:', 'rekap-sks-saya:'])->delete();
        Menu::whereIn('route', ['rekap-sks-dosen.index', 'rekap-sks-saya.index'])->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @param  array<int, string>  $roles
     */
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
