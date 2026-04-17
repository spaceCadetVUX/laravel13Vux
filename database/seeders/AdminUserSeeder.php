<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // User model does not have HasUuids or HasRoles yet (added in S11).
        // UUID is supplied explicitly; Spatie role is assigned via raw DB insert.
        // Email is encrypted — must look up via email_hash (SHA-256 of plaintext).
        $emailHash = hash('sha256', strtolower('admin@example.com'));
        $user      = User::where('email_hash', $emailHash)->first();

        if (! $user) {
            $user = new User();
            $user->id                = (string) Str::uuid();
            $user->name              = 'Admin';
            $user->email             = 'admin@example.com'; // setter encrypts + sets email_hash
            $user->password          = 'password';           // 'hashed' cast bcrypts this — do NOT pre-hash
            $user->role              = 'admin';
            $user->email_verified_at = now();
            $user->save();
        }

        // Assign Spatie 'admin' role via direct DB insert (bypasses HasRoles trait
        // which is not yet on the User model — added in S11).
        $adminRole = Role::where('name', 'admin')->where('guard_name', 'web')->first();

        DB::table('model_has_roles')->updateOrInsert(
            [
                'role_id'    => $adminRole->id,
                'model_id'   => (string) $user->id,
                'model_type' => 'App\\Models\\User',
            ]
        );

        $this->command->info("Admin user seeded: admin@example.com (role: admin)");
    }
}
