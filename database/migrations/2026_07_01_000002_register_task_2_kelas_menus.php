<?php

use App\Models\Menu;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('menus', function (Blueprint $table) {
            if (! Schema::hasColumn('menus', 'position')) {
                $table->unsignedSmallInteger('position')->default(0)->after('parent_id');
            }

            if (! Schema::hasColumn('menus', 'icon')) {
                $table->string('icon')->nullable()->after('position');
            }
        });

        Schema::table('permissions', function (Blueprint $table) {
            if (! Schema::hasColumn('permissions', 'main_permission')) {
                $table->boolean('main_permission')->default(false)->index()->after('menu_id');
            }

            if (! Schema::hasColumn('permissions', 'descriptions')) {
                $table->string('descriptions')->nullable()->after('main_permission');
            }
        });

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

        $this->registerMenu($parent->id, 'Kelas', 'kelas.index', 70, 'kelas:');
        $this->registerMenu($parent->id, 'Kelas Sistem Blok', 'kelas-sistem-blok.index', 80, 'kelas-sistem-blok:');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['kelas:', 'kelas-sistem-blok:'] as $permissionName) {
            Permission::where('name', $permissionName)->delete();
        }

        Menu::whereIn('route', ['kelas.index', 'kelas-sistem-blok.index'])->delete();
    }

    private function registerMenu(int $parentId, string $name, string $route, int $position, string $permissionName): void
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
