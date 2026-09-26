<?php

namespace Database\Seeders;

use App\Models\SkalaNilai;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SkalaNilaiSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $skala = SkalaNilai::firstOrCreate(
                ['nama' => 'Skala Nilai Default', 'versi' => 1],
                ['aktif' => ! SkalaNilai::where('aktif', true)->exists(), 'keterangan' => 'Skala nilai global bawaan A sampai E.']
            );

            foreach ([
                ['nilai_angka_min' => 80, 'nilai_angka_max' => 100, 'nilai_huruf' => 'A', 'nilai_indeks' => 4, 'lulus' => true, 'boleh_perbaikan' => false],
                ['nilai_angka_min' => 70, 'nilai_angka_max' => 79.99, 'nilai_huruf' => 'B', 'nilai_indeks' => 3, 'lulus' => true, 'boleh_perbaikan' => false],
                ['nilai_angka_min' => 60, 'nilai_angka_max' => 69.99, 'nilai_huruf' => 'C', 'nilai_indeks' => 2, 'lulus' => true, 'boleh_perbaikan' => false],
                ['nilai_angka_min' => 50, 'nilai_angka_max' => 59.99, 'nilai_huruf' => 'D', 'nilai_indeks' => 1, 'lulus' => false, 'boleh_perbaikan' => true],
                ['nilai_angka_min' => 0, 'nilai_angka_max' => 49.99, 'nilai_huruf' => 'E', 'nilai_indeks' => 0, 'lulus' => false, 'boleh_perbaikan' => true],
            ] as $detail) {
                $skala->detail()->firstOrCreate(['nilai_huruf' => $detail['nilai_huruf']], $detail);
            }
        });
    }
}
