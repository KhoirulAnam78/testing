<?php

use App\Models\Menu;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Menu portal dosen: "Pertemuan Saya".
 *
 * Sebelum ini belum ada satu pun halaman yang di-scope ke user yang login, dan belum
 * ada permission yang diberikan ke role `dosen`, sehingga dosen login dengan sidebar
 * kosong. Menu ini jadi pintu masuk pertama untuk mereka.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->lengkapiPermissionAkademik();

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

        $this->daftarkanPermission(
            $parent,
            'portal-saya:',
            'akses Portal Saya',
            ['dosen', 'mahasiswa']
        );

        $menu = Menu::updateOrCreate(
            ['route' => 'pertemuan-saya.index'],
            [
                'name' => 'Pertemuan Saya',
                'status' => true,
                'is_child_menu' => true,
                'parent_id' => $parent->id,
                'position' => 10,
                'icon' => null,
                'descriptions' => 'Menu Pertemuan Saya',
            ]
        );

        $this->daftarkanPermission(
            $menu,
            'pertemuan-saya:',
            'akses Pertemuan Saya',
            ['dosen']
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Permission::whereIn('name', ['pertemuan-saya:'])->get()->each(fn (Permission $permission) => $permission->delete());
        Menu::where('route', 'pertemuan-saya.index')->delete();

        $parent = Menu::where('name', 'Portal Saya')->whereNull('parent_id')->first();

        // Menu induk hanya dibuang bila tidak ada anak lain yang masih memakainya.
        if ($parent && ! Menu::where('parent_id', $parent->id)->exists()) {
            Permission::where('name', 'portal-saya:')->get()->each(fn (Permission $permission) => $permission->delete());
            $parent->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Query navbar memakai inner join ke `permissions` dengan `main_permission = 1`,
     * jadi menu tanpa baris permission tidak pernah tampil. Menu induk "Akademik"
     * dibuat tanpa permission, sehingga pada database baru seluruh grup akademik
     * hilang dari sidebar. Idempoten bila permission itu sudah dibuat manual.
     */
    private function lengkapiPermissionAkademik(): void
    {
        $akademik = Menu::where('name', 'Akademik')->whereNull('parent_id')->first();

        if (! $akademik) {
            return;
        }

        $this->daftarkanPermission($akademik, 'akademik:', 'akses Akademik', ['admin', 'pengelola']);
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
