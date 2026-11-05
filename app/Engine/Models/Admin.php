<?php

namespace App\Engine\Models;

use App\Engine\EngineServiceProvider;
use App\Engine\Filament\EnginePanelProvider;
use Database\Engine\Factories\AdminFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Utente del pannello Motore (/motore), separato dagli utenti del CMS.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 */
#[UseFactory(AdminFactory::class)]
class Admin extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<AdminFactory> */
    use HasFactory;

    /** @var string */
    protected $connection = EngineServiceProvider::CONNECTION;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === EnginePanelProvider::ID;
    }
}
