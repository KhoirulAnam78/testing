<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UsernameBackfillMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_creates_academic_accounts_and_preserves_existing_passwords(): void
    {
        $constraint = require database_path('migrations/2026_08_25_000003_make_users_username_required_and_unique.php');
        $column = require database_path('migrations/2026_08_25_000001_add_username_to_users_table.php');
        $backfill = require database_path('migrations/2026_08_25_000002_backfill_academic_user_accounts.php');

        $constraint->down();
        $column->down();

        $now = now();
        $oldPassword = Hash::make('password-lama');
        $dosenUserId = DB::table('users')->insertGetId([
            'name' => 'Dosen Lama',
            'email' => 'dosen@example.test',
            'password' => $oldPassword,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $firstGeneralId = DB::table('users')->insertGetId([
            'name' => 'User Satu',
            'email' => 'umum@example.test',
            'password' => Hash::make('password'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $secondGeneralId = DB::table('users')->insertGetId([
            'name' => 'User Dua',
            'email' => 'umum@example.org',
            'password' => Hash::make('password'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $prodiId = DB::table('prodi')->insertGetId([
            'kode' => 'MED',
            'nama' => 'Kedokteran',
            'status' => 'aktif',
            'created_at' => $now,
            'updated_at' => $now,
        ], 'id_prodi');
        DB::table('dosen')->insert([
            'user_id' => $dosenUserId,
            'nidn' => '0123456789',
            'nip' => '198001012010011001',
            'nama' => 'Dosen Lama',
            'email' => 'dosen@example.test',
            'status' => 'aktif',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('mahasiswa')->insert([
            'prodi_id' => $prodiId,
            'nim' => '20260001',
            'nama' => 'Mahasiswa Lama',
            'email' => 'mahasiswa@example.test',
            'angkatan' => 2026,
            'status' => 'aktif',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $column->up();
        $backfill->up();
        $constraint->up();

        $this->assertSame('198001012010011001', DB::table('users')->where('id', $dosenUserId)->value('username'));
        $this->assertTrue(Hash::check('password-lama', DB::table('users')->where('id', $dosenUserId)->value('password')));
        $this->assertSame('umum', DB::table('users')->where('id', $firstGeneralId)->value('username'));
        $this->assertSame('umum-'.$secondGeneralId, DB::table('users')->where('id', $secondGeneralId)->value('username'));

        $mahasiswaUserId = DB::table('mahasiswa')->where('nim', '20260001')->value('user_id');
        $this->assertNotNull($mahasiswaUserId);
        $this->assertSame('20260001', DB::table('users')->where('id', $mahasiswaUserId)->value('username'));
        $this->assertTrue(Hash::check('20260001', DB::table('users')->where('id', $mahasiswaUserId)->value('password')));
        $this->assertDatabaseHas('model_has_roles', ['model_id' => $dosenUserId, 'model_type' => User::class]);
        $this->assertDatabaseHas('model_has_roles', ['model_id' => $mahasiswaUserId, 'model_type' => User::class]);

        $this->expectException(QueryException::class);
        User::factory()->create(['username' => '20260001']);
    }
}
