<?php

namespace App\Imports;

use App\Models\Dosen;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class DosenImport implements SkipsEmptyRows, ToCollection, WithHeadingRow
{
    public function collection(Collection $rows): void
    {
        DB::transaction(function () use ($rows) {
            foreach ($rows as $index => $row) {
                $line = $index + 2;
                $email = strtolower(trim((string) ($row['email'] ?? '')));
                $nama = trim((string) ($row['nama'] ?? ''));
                $nidn = $this->nullableString($row['nidn'] ?? null);
                $nip = $this->nullableString($row['nip'] ?? null);
                $kodeProdi = $this->nullableString($row['kode_prodi'] ?? null);
                $status = strtolower(trim((string) ($row['status'] ?? 'aktif')));
                $username = strtolower($nip ?: $nidn ?: '');

                if ($nama === '' || $email === '' || $username === '') {
                    throw ValidationException::withMessages([
                        'import_dosen' => "Baris {$line}: nama, email, serta NIP atau NIDN wajib diisi.",
                    ]);
                }

                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw ValidationException::withMessages([
                        'import_dosen' => "Baris {$line}: format email tidak valid.",
                    ]);
                }

                if (! in_array($status, ['aktif', 'nonaktif'], true)) {
                    throw ValidationException::withMessages([
                        'import_dosen' => "Baris {$line}: status harus aktif atau nonaktif.",
                    ]);
                }

                $prodiId = null;
                if ($kodeProdi) {
                    $prodiId = Prodi::where('kode', $kodeProdi)->value('id_prodi');

                    if (! $prodiId) {
                        throw ValidationException::withMessages([
                            'import_dosen' => "Baris {$line}: kode_prodi {$kodeProdi} tidak ditemukan.",
                        ]);
                    }
                }

                $dosen = $this->findDosen($nidn, $nip, $email);
                $user = $dosen?->user ?: User::where('email', $email)->first();

                if ($user && Dosen::where('user_id', $user->id)->when($dosen, fn ($query) => $query->whereKeyNot($dosen->getKey()))->exists()) {
                    throw ValidationException::withMessages([
                        'import_dosen' => "Baris {$line}: user email {$email} sudah terhubung dengan dosen lain.",
                    ]);
                }

                if (User::where('username', $username)->when($user, fn ($query) => $query->whereKeyNot($user->id))->exists()) {
                    throw ValidationException::withMessages([
                        'import_dosen' => "Baris {$line}: username {$username} sudah digunakan.",
                    ]);
                }

                if (! $user) {
                    $user = User::create([
                        'name' => $nama,
                        'username' => $username,
                        'email' => $email,
                        'password' => Hash::make($nidn ?: $nip ?: 'password'),
                    ]);
                } else {
                    $user->update([
                        'name' => $nama,
                        'username' => $username,
                        'email' => $email,
                    ]);
                }

                $user->assignRole('dosen');

                Dosen::updateOrCreate(
                    ['id_dosen' => $dosen?->id_dosen],
                    [
                        'user_id' => $user->id,
                        'prodi_id' => $prodiId,
                        'nidn' => $nidn,
                        'nip' => $nip,
                        'nama' => $nama,
                        'email' => $email,
                        'no_hp' => $this->nullableString($row['no_hp'] ?? null),
                        'gelar_depan' => $this->nullableString($row['gelar_depan'] ?? null),
                        'gelar_belakang' => $this->nullableString($row['gelar_belakang'] ?? null),
                        'bidang_keahlian' => $this->nullableString($row['bidang_keahlian'] ?? null),
                        'status' => $status,
                    ],
                );
            }
        });
    }

    private function findDosen(?string $nidn, ?string $nip, string $email): ?Dosen
    {
        return Dosen::query()
            ->when($nidn, fn ($query) => $query->orWhere('nidn', $nidn))
            ->when($nip, fn ($query) => $query->orWhere('nip', $nip))
            ->orWhere('email', $email)
            ->first();
    }

    private function nullableString($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
