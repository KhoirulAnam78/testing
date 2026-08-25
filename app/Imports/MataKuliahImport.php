<?php

namespace App\Imports;

use App\Models\MataKuliah;
use App\Models\Prodi;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class MataKuliahImport implements SkipsEmptyRows, ToCollection, WithHeadingRow
{
    public function collection(Collection $rows): void
    {
        DB::transaction(function () use ($rows) {
            foreach ($rows as $index => $row) {
                $line = $index + 2;
                $kodeProdi = trim((string) ($row['kode_prodi'] ?? ''));
                $kode = trim((string) ($row['kode'] ?? ''));
                $nama = trim((string) ($row['nama'] ?? ''));
                $sks = trim((string) ($row['sks'] ?? ''));
                $status = strtolower(trim((string) ($row['status'] ?? 'aktif')));

                if ($kodeProdi === '' || $kode === '' || $nama === '' || $sks === '') {
                    throw ValidationException::withMessages([
                        'import_mata_kuliah' => "Baris {$line}: kode_prodi, kode, nama, dan sks wajib diisi.",
                    ]);
                }

                if (! is_numeric($sks) || (float) $sks <= 0) {
                    throw ValidationException::withMessages([
                        'import_mata_kuliah' => "Baris {$line}: sks harus berupa angka lebih dari 0.",
                    ]);
                }

                if (! in_array($status, ['aktif', 'nonaktif'], true)) {
                    throw ValidationException::withMessages([
                        'import_mata_kuliah' => "Baris {$line}: status harus aktif atau nonaktif.",
                    ]);
                }

                $prodiId = Prodi::where('kode', $kodeProdi)->value('id_prodi');
                if (! $prodiId) {
                    throw ValidationException::withMessages([
                        'import_mata_kuliah' => "Baris {$line}: kode_prodi {$kodeProdi} tidak ditemukan.",
                    ]);
                }

                MataKuliah::updateOrCreate(
                    [
                        'prodi_id' => $prodiId,
                        'kode' => $kode,
                    ],
                    [
                        'nama' => $nama,
                        'sks' => (float) $sks,
                        'deskripsi' => $this->nullableString($row['deskripsi'] ?? null),
                        'status' => $status,
                    ],
                );
            }
        });
    }

    private function nullableString($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
