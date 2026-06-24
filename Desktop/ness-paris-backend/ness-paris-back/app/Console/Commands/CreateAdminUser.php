<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class CreateAdminUser extends Command
{
    protected $signature = 'user:create-admin';
    protected $description = 'Crée un utilisateur admin';

    public function handle()
    {
        User::where('email', 'admin@ness.com')->delete();
        
        $user = User::create([
            'name' => 'Admin Ness',
            'email' => 'admin@ness.com',
            'password' => Hash::make('motdepasse123'),
            'role' => 'admin',
            'is_admin' => true,
        ]);

        $this->info('✅ Utilisateur créé : admin@ness.com / motdepasse123');
        return 0;
    }
}
