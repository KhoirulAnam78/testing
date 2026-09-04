<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('dosen')
            ->join('users', 'users.id', '=', 'dosen.user_id')
            ->whereNotNull('dosen.nip')
            ->whereNotNull('dosen.nidn')
            ->select([
                'dosen.id_dosen',
                'dosen.nip',
                'dosen.nidn',
                'users.id as user_id',
                'users.username',
                'users.password',
            ])
            ->chunkById(100, function ($dosen): void {
                foreach ($dosen as $item) {
                    $username = strtolower(trim((string) $item->nip));
                    $oldDefaultPassword = trim((string) $item->nidn);

                    if ($username === '' || $oldDefaultPassword === '' || $username !== strtolower(trim((string) $item->username))) {
                        continue;
                    }

                    if (Hash::check($oldDefaultPassword, $item->password) && ! Hash::check($username, $item->password)) {
                        DB::table('users')->where('id', $item->user_id)->update([
                            'password' => Hash::make($username),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }, 'dosen.id_dosen', 'id_dosen');
    }

    public function down(): void
    {
        // Password lama tidak dipulihkan agar password pengguna tidak ditimpa.
    }
};
