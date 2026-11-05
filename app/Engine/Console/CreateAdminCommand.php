<?php

namespace App\Engine\Console;

use App\Engine\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Crea un utente del pannello Motore (/motore), o ne cambia la password se esiste già.
 */
class CreateAdminCommand extends Command
{
    protected $signature = 'engine:admin
        {email : Email di accesso}
        {--name= : Nome visualizzato}
        {--password= : Password (se assente viene chiesta)}';

    protected $description = 'Crea un utente del pannello Motore o ne aggiorna la password';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $password = $this->option('password') ?? $this->secret('Password (almeno 12 caratteri)');

        $validator = Validator::make(['email' => $email, 'password' => $password], [
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:12'],
        ], [
            'email.email' => "L'email non è valida.",
            'password.min' => 'La password deve avere almeno 12 caratteri.',
            'password.required' => 'La password è obbligatoria.',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $admin = Admin::query()->firstOrNew(['email' => $email]);
        $created = ! $admin->exists;
        $admin->name = (string) ($this->option('name') ?? $admin->name ?? strstr($email, '@', true));
        $admin->password = (string) $password;
        $admin->save();

        $this->components->info($created ? "Utente {$email} creato: accede da /motore." : "Password di {$email} aggiornata.");

        return self::SUCCESS;
    }
}
