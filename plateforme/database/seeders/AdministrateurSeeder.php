<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Crée le premier administrateur à partir de ADMIN_EMAIL et ADMIN_PASSWORD (.env).
 * Sans ADMIN_PASSWORD, un mot de passe aléatoire est généré et affiché une seule fois.
 */
class AdministrateurSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@appartement-vivi.local');

        if (User::where('email', $email)->exists()) {
            $this->command?->info("Administrateur {$email} déjà présent : rien à faire.");

            return;
        }

        $motDePasse = env('ADMIN_PASSWORD') ?: Str::password(16, symbols: false);

        $admin = User::create([
            'name' => 'Administrateur VIVI',
            'email' => $email,
            'password' => $motDePasse,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::Administrateur->value);

        $this->command?->warn("Administrateur créé : {$email}");
        if (! env('ADMIN_PASSWORD')) {
            $this->command?->warn("Mot de passe généré (à noter, il ne sera plus affiché) : {$motDePasse}");
        }
    }
}
