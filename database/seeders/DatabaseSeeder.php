<?php

namespace Database\Seeders;

use App\Models\JenisKegiatan;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach (['admin', 'pengelola', 'dosen', 'mahasiswa'] as $role) {
            Role::findOrCreate($role);
        }

        $prodi = [
            ['kode' => 'PSPD', 'nama' => 'Pendidikan Dokter', 'jenjang' => 'S1'],
            ['kode' => 'PROFESI', 'nama' => 'Profesi Dokter', 'jenjang' => 'Profesi'],
        ];

        foreach ($prodi as $item) {
            Prodi::updateOrCreate(
                ['kode' => $item['kode']],
                $item + ['status' => 'aktif']
            );
        }

        $jenisKegiatan = [
            ['kode' => 'TUTORIAL', 'nama' => 'Tutorial/PBL', 'jumlah_pertemuan_default' => 7, 'durasi_menit_default' => 120],
            ['kode' => 'PRAKTIKUM', 'nama' => 'Praktikum', 'jumlah_pertemuan_default' => 4, 'durasi_menit_default' => 180],
            ['kode' => 'KULIAH', 'nama' => 'Kuliah Pakar', 'jumlah_pertemuan_default' => 8, 'durasi_menit_default' => 100],
            ['kode' => 'SKILLS_LAB', 'nama' => 'Skills Lab/OSCE', 'jumlah_pertemuan_default' => 4, 'durasi_menit_default' => 180],
        ];

        foreach ($jenisKegiatan as $item) {
            JenisKegiatan::updateOrCreate(
                ['kode' => $item['kode']],
                $item + ['status' => 'aktif']
            );
        }

        User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'username' => 'test',
                'password' => bcrypt('password'),
            ]
        );
    }
}
