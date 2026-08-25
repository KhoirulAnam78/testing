<?php

use App\Models\Menu;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Permission::where('name', 'komponen-penilaian:')->delete();
        Menu::where('route', 'komponen-penilaian.index')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Menu sengaja tidak dibuat kembali: komponen dikelola melalui Jenis Kegiatan.
    }
};
