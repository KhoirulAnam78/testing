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
        if (! Schema::hasColumn('jenis_kegiatan', 'sumber_nilai')) {
            Schema::table('jenis_kegiatan', function (Blueprint $table) {
                $table->enum('sumber_nilai', ['manual', 'cbt'])
                    ->default('manual')
                    ->after('bobot_sks_per_pertemuan')
                    ->index();
            });
        }

        if (! Schema::hasTable('nilai_cbt_blok')) {
            Schema::create('nilai_cbt_blok', function (Blueprint $table) {
                $table->id('id_nilai_cbt_blok');
                $table->foreignId('aturan_kegiatan_blok_id')->constrained('aturan_kegiatan_blok')->cascadeOnDelete();
                $table->foreignId('peserta_blok_id')->constrained('peserta_blok', 'id_peserta_blok')->cascadeOnDelete();
                $table->decimal('nilai_ujian_pertama', 5, 2)->nullable();
                $table->boolean('mengikuti_remedial')->default(false);
                $table->decimal('nilai_remedial', 5, 2)->nullable();
                $table->decimal('bobot_ujian_pertama', 5, 2)->default(100);
                $table->decimal('bobot_remedial', 5, 2)->default(0);
                $table->decimal('nilai_akhir', 5, 2)->nullable();
                $table->string('referensi_eksternal')->nullable();
                $table->timestamp('disinkronkan_pada')->nullable();
                $table->timestamps();

                $table->unique(['aturan_kegiatan_blok_id', 'peserta_blok_id'], 'nilai_cbt_blok_unique');
                $table->index('peserta_blok_id');
            });
        }

        if (! Schema::hasTable('grup_dpna_blok')) {
            Schema::create('grup_dpna_blok', function (Blueprint $table) {
                $table->id('id_grup_dpna_blok');
                $table->foreignId('blok_id')->constrained('blok')->cascadeOnDelete();
                $table->string('nama');
                $table->decimal('bobot', 5, 2);
                $table->unsignedSmallInteger('urutan')->default(1);
                $table->boolean('aktif')->default(true);
                $table->timestamps();

                $table->index(['blok_id', 'aktif', 'urutan']);
            });
        }

        if (! Schema::hasTable('anggota_grup_dpna_blok')) {
            Schema::create('anggota_grup_dpna_blok', function (Blueprint $table) {
                $table->id('id_anggota_grup_dpna_blok');
                $table->foreignId('grup_dpna_blok_id')->constrained('grup_dpna_blok', 'id_grup_dpna_blok')->cascadeOnDelete();
                $table->foreignId('aturan_kegiatan_blok_id')->constrained('aturan_kegiatan_blok')->cascadeOnDelete();
                $table->boolean('aktif')->default(true);
                $table->timestamps();

                $table->unique(['grup_dpna_blok_id', 'aturan_kegiatan_blok_id'], 'anggota_grup_dpna_unique');
                $table->index(['aturan_kegiatan_blok_id', 'aktif']);
            });
        }

        if (! Schema::hasTable('finalisasi_dpna_blok')) {
            Schema::create('finalisasi_dpna_blok', function (Blueprint $table) {
                $table->id('id_finalisasi_dpna_blok');
                $table->foreignId('blok_id')->constrained('blok')->cascadeOnDelete();
                $table->unsignedInteger('versi');
                $table->enum('status', ['final', 'dibuka_kembali'])->default('final');
                $table->json('konfigurasi_json');
                $table->foreignId('difinalisasi_oleh_user_id')->constrained('users')->restrictOnDelete();
                $table->timestamp('difinalisasi_pada');
                $table->foreignId('dibuka_oleh_user_id')->nullable()->constrained('users')->restrictOnDelete();
                $table->timestamp('dibuka_pada')->nullable();
                $table->text('alasan_buka')->nullable();
                $table->timestamps();

                $table->unique(['blok_id', 'versi']);
                $table->index(['blok_id', 'status']);
            });
        }

        if (! Schema::hasTable('snapshot_dpna_peserta')) {
            Schema::create('snapshot_dpna_peserta', function (Blueprint $table) {
                $table->id('id_snapshot_dpna_peserta');
                $table->foreignId('finalisasi_dpna_blok_id')->constrained('finalisasi_dpna_blok', 'id_finalisasi_dpna_blok')->cascadeOnDelete();
                $table->foreignId('peserta_blok_id')->nullable()->constrained('peserta_blok', 'id_peserta_blok')->nullOnDelete();
                $table->string('nim');
                $table->string('nama_mahasiswa');
                $table->decimal('nilai_akhir', 5, 2);
                $table->json('sumber_json');
                $table->timestamps();

                $table->unique(['finalisasi_dpna_blok_id', 'peserta_blok_id'], 'snapshot_dpna_peserta_unique');
            });
        }

        $menu = Menu::where('route', 'dpna-blok.index')->first();

        if ($menu) {
            $permission = Permission::updateOrCreate(
                ['name' => 'dpna-blok:finalisasi', 'guard_name' => 'web'],
                ['menu_id' => $menu->id, 'main_permission' => false, 'descriptions' => 'finalisasi dan buka kembali DPNA Blok']
            );

            foreach (['admin', 'pengelola'] as $role) {
                Role::findOrCreate($role, 'web')->givePermissionTo($permission);
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        // Sengaja kosong: rollback destruktif dilarang karena tabel menyimpan histori finalisasi.
    }
};
