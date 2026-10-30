<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class MakeNeetAdmin extends Command
{
    protected $signature = 'neet:make-admin {email} {--name=}';
    protected $description = 'Create or promote a NEET workspace administrator';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->components->error('Enter a valid email address.');
            return self::FAILURE;
        }
        $password = $this->secret('Set an admin password (at least 10 characters)');
        if (! is_string($password) || strlen($password) < 10) {
            $this->components->error('Password must be at least 10 characters.');
            return self::FAILURE;
        }
        $name = (string) ($this->option('name') ?: $this->ask('Admin name'));
        if ($name === '') return self::FAILURE;

        $user = User::firstOrNew(['email' => $email]);
        $user->name = $name;
        $user->password = Hash::make($password);
        $user->role = 'admin';
        $user->save();
        $this->components->info("Admin account ready for {$email}.");
        return self::SUCCESS;
    }
}
