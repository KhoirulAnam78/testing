<?php

use App\Models\Menu;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blok', function (Blueprint $table) {
            $table->boolean('kehadiran_masuk_dpna')->default(false)->after('status');
            $table->decimal('bobot_kehadiran_dpna', 5, 2)->default(0)->after('kehadiran_masuk_dpna');
        });

        Schema::table('aturan_kegiatan_blok', function (Blueprint $table) {
            $table->boolean('nilai_masuk_dpna')->default(false)->after('perlu_penilaian');
            $table->decimal('bobot_nilai_dpna', 5, 2)->default(0)->after('nilai_masuk_dpna');
        });

        $parent = Menu::where('name', 'Kelola Blok')->whereNull('parent_id')->first()
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

        $this->permission($parent, 'kelola-blok:', 'akses Kelola Blok');

        $menu = Menu::updateOrCreate(
            ['route' => 'dpna-blok.index'],
            [
                'name' => 'DPNA Blok',
                'status' => true,
                'is_child_menu' => true,
                'parent_id' => $parent->id,
                'position' => 10,
                'icon' => null,
                'descriptions' => 'Daftar Peserta dan Nilai Akhir Blok',
            ]
        );

        $this->permission($menu, 'dpna-blok:', 'akses DPNA Blok');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'dpna-blok:')->delete();
        Menu::where('route', 'dpna-blok.index')->delete();

        $parent = Menu::where('name', 'Kelola Blok')->whereNull('parent_id')->first();
        if ($parent && ! Menu::where('parent_id', $parent->id)->exists()) {
            Permission::where('name', 'kelola-blok:')->delete();
            $parent->delete();
        }

        Schema::table('aturan_kegiatan_blok', function (Blueprint $table) {
            $table->dropColumn(['nilai_masuk_dpna', 'bobot_nilai_dpna']);
        });
        Schema::table('blok', function (Blueprint $table) {
            $table->dropColumn(['kehadiran_masuk_dpna', 'bobot_kehadiran_dpna']);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function permission(Menu $menu, string $name, string $description): void
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
