<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $now = now();

            DB::table('roles')->insertOrIgnore([
                ['name' => 'dosen', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'mahasiswa', 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now],
            ]);

            $roleIds = DB::table('roles')->whereIn('name', ['dosen', 'mahasiswa'])->pluck('id', 'name');

            foreach (DB::table('dosen')->orderBy('id_dosen')->get() as $dosen) {
                $username = strtolower(trim((string) ($dosen->nip ?: $dosen->nidn)));

                if ($username === '') {
                    $username = 'dosen-'.$dosen->id_dosen;
                }

                $userId = $dosen->user_id;

                if (! $userId) {
                    $email = $this->uniqueEmail($dosen->email, 'dosen-'.$dosen->id_dosen.'@local.invalid');
                    $userId = DB::table('users')->insertGetId([
                        'name' => $dosen->nama,
                        'username' => $username,
                        'email' => $email,
                        'password' => Hash::make($dosen->nidn ?: $dosen->nip ?: $username),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    DB::table('dosen')->where('id_dosen', $dosen->id_dosen)->update(['user_id' => $userId]);
                } else {
                    DB::table('users')->where('id', $userId)->update(['username' => $username, 'updated_at' => $now]);
                }

                $this->assignRole($userId, $roleIds['dosen']);
            }

            foreach (DB::table('mahasiswa')->orderBy('id_mahasiswa')->get() as $mahasiswa) {
                $username = strtolower(trim((string) $mahasiswa->nim));
                $userId = $mahasiswa->user_id;

                if (! $userId) {
                    $email = $this->uniqueEmail($mahasiswa->email, 'mahasiswa-'.$mahasiswa->id_mahasiswa.'@local.invalid');
                    $userId = DB::table('users')->insertGetId([
                        'name' => $mahasiswa->nama,
                        'username' => $username,
                        'email' => $email,
                        'password' => Hash::make($username),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    DB::table('mahasiswa')->where('id_mahasiswa', $mahasiswa->id_mahasiswa)->update(['user_id' => $userId]);
                } else {
                    DB::table('users')->where('id', $userId)->update(['username' => $username, 'updated_at' => $now]);
                }

                $this->assignRole($userId, $roleIds['mahasiswa']);
            }

            foreach (DB::table('users')->whereNull('username')->orderBy('id')->get() as $user) {
                $base = strtolower(strstr($user->email, '@', true) ?: 'user');
                $username = DB::table('users')->whereRaw('LOWER(username) = ?', [$base])->exists()
                    ? $base.'-'.$user->id
                    : $base;

                DB::table('users')->where('id', $user->id)->update(['username' => $username, 'updated_at' => $now]);
            }
        });
    }

    public function down(): void
    {
        // Akun, role, dan relasi hasil backfill sengaja dipertahankan.
    }

    private function uniqueEmail(?string $email, string $fallback): string
    {
        $email = strtolower(trim((string) $email));

        return $email !== '' && ! DB::table('users')->where('email', $email)->exists() ? $email : $fallback;
    }

    private function assignRole(int $userId, int $roleId): void
    {
        DB::table('model_has_roles')->insertOrIgnore([
            'role_id' => $roleId,
            'model_type' => User::class,
            'model_id' => $userId,
        ]);
    }
};
