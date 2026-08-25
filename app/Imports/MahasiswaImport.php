<?php

namespace App\Imports;

use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class MahasiswaImport implements SkipsEmptyRows, ToCollection, WithHeadingRow
{
    public function collection(Collection $rows): void
    {
        DB::transaction(function () use ($rows) {
            foreach ($rows as $index => $row) {
                $line = $index + 2;
                $nim = strtolower(trim((string) ($row['nim'] ?? '')));
                $nama = trim((string) ($row['nama'] ?? ''));
                $email = strtolower(trim((string) ($row['email'] ?? '')));
                $kodeProdi = trim((string) ($row['kode_prodi'] ?? ''));
                $angkatan = trim((string) ($row['angkatan'] ?? ''));
                $status = strtolower(trim((string) ($row['status'] ?? 'aktif')));

                if ($nim === '' || $nama === '' || $email === '' || $kodeProdi === '' || $angkatan === '') {
                    throw ValidationException::withMessages([
                        'import_mahasiswa' => "Baris {$line}: nim, nama, email, kode_prodi, dan angkatan wajib diisi.",
                    ]);
                }

                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw ValidationException::withMessages([
                        'import_mahasiswa' => "Baris {$line}: format email tidak valid.",
                    ]);
                }

                if (! preg_match('/^\d{4}$/', $angkatan)) {
                    throw ValidationException::withMessages([
                        'import_mahasiswa' => "Baris {$line}: angkatan harus 4 digit.",
                    ]);
                }

                if (! in_array($status, ['aktif', 'nonaktif', 'lulus', 'cuti'], true)) {
                    throw ValidationException::withMessages([
                        'import_mahasiswa' => "Baris {$line}: status harus aktif, nonaktif, lulus, atau cuti.",
                    ]);
                }

                $prodiId = Prodi::where('kode', $kodeProdi)->value('id_prodi');
                if (! $prodiId) {
                    throw ValidationException::withMessages([
                        'import_mahasiswa' => "Baris {$line}: kode_prodi {$kodeProdi} tidak ditemukan.",
                    ]);
                }

                $mahasiswa = Mahasiswa::where('nim', $nim)->orWhere('email', $email)->first();
                $user = $mahasiswa?->user ?: User::where('email', $email)->first();

                if ($user && Mahasiswa::where('user_id', $user->id)->when($mahasiswa, fn ($query) => $query->whereKeyNot($mahasiswa->getKey()))->exists()) {
                    throw ValidationException::withMessages([
                        'import_mahasiswa' => "Baris {$line}: user email {$email} sudah terhubung dengan mahasiswa lain.",
                    ]);
                }

                if (User::where('username', $nim)->when($user, fn ($query) => $query->whereKeyNot($user->id))->exists()) {
                    throw ValidationException::withMessages([
                        'import_mahasiswa' => "Baris {$line}: username {$nim} sudah digunakan.",
                    ]);
                }

                if (! $user) {
                    $user = User::create([
                        'name' => $nama,
                        'username' => $nim,
                        'email' => $email,
                        'password' => Hash::make($nim),
                    ]);
                } else {
                    $user->update([
                        'name' => $nama,
                        'username' => $nim,
                        'email' => $email,
                    ]);
                }

                $user->assignRole('mahasiswa');

                Mahasiswa::updateOrCreate(
                    ['id_mahasiswa' => $mahasiswa?->id_mahasiswa],
                    [
                        'user_id' => $user->id,
                        'prodi_id' => $prodiId,
                        'nim' => $nim,
                        'nama' => $nama,
                        'email' => $email,
                        'no_hp' => $this->nullableString($row['no_hp'] ?? null),
                        'angkatan' => (int) $angkatan,
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
