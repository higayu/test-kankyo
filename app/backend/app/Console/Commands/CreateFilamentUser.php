<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateFilamentUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:filament-user';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Filament user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = $this->ask('Name');
        $loginCode = $this->ask('Login Code');
        $email = $this->ask('Email address (optional)');
        $password = $this->secret('Password');
        $confirmPassword = $this->secret('Confirm password');

        if ($password !== $confirmPassword) {
            $this->error('Passwords do not match!');
            return 1;
        }

        $user = User::create([
            'name' => $name,
            'login_code' => $loginCode,
            'email' => $email,
            'password' => Hash::make($password),
            'is_admin' => true,
        ]);

        $this->info('User created successfully!');
        $this->table(
            ['ID', 'Name', 'Login Code', 'Email'],
            [[$user->id, $user->name, $user->login_code, $user->email]]
        );

        return 0;
    }
}
