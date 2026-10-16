<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;

class MakeAdmin extends Command
{
    protected $signature = 'foundry:make-admin {email} {--name=Administrator} {--password=}';
    protected $description = 'Create (or promote) an administrator account';

    public function handle(): int
    {
        $email = $this->argument('email');
        $password = $this->option('password') ?: $this->secret('Password (min 10 characters, letters and numbers)');
        if (strlen((string) $password) < 10) {
            $this->error('Password must be at least 10 characters.');
            return self::FAILURE;
        }
        $user = User::firstOrNew(['email' => $email]);
        $user->fill(['name' => $user->exists ? $user->name : $this->option('name'), 'password' => $password, 'is_active' => true, 'must_change_password' => false])->save();
        $user->roles()->syncWithoutDetaching([Role::where('code', 'ADMIN')->value('id')]);
        $this->info("Administrator ready: {$email}. Set FOUNDRY_REQUIRE_LOGIN=true in .env to switch login on.");
        return self::SUCCESS;
    }
}
