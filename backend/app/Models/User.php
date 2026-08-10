<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\Traceable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Auditable, Notifiable, Traceable;

    protected $fillable = [
        'nom', 'prenom', 'email', 'username', 'password', 'telephone',
        'role_id', 'service_id', 'site_id', 'actif', 'doit_changer_mdp',
    ];

    protected $hidden = [
        'password', 'remember_token', 'totp_secret',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'actif' => 'boolean',
            'doit_changer_mdp' => 'boolean',
            'totp_secret' => 'encrypted',
            'totp_confirme_at' => 'datetime',
            'verrouille_jusqua' => 'datetime',
            'derniere_connexion_at' => 'datetime',
        ];
    }

    // --- Relations ----------------------------------------------------------

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function historiquesMdp(): HasMany
    {
        return $this->hasMany(PasswordHistory::class)->latest('created_at');
    }

    public function codesSecours(): HasMany
    {
        return $this->hasMany(TwoFactorRecoveryCode::class);
    }

    // --- Autorisations ------------------------------------------------------

    public function aPermission(string $code): bool
    {
        return $this->role?->aPermission($code) ?? false;
    }

    public function estAdministrateur(): bool
    {
        return $this->role?->code === Role::ADMINISTRATEUR;
    }

    // --- État du compte -----------------------------------------------------

    public function estVerrouille(): bool
    {
        return $this->verrouille_jusqua !== null && $this->verrouille_jusqua->isFuture();
    }

    public function totpActive(): bool
    {
        return $this->totp_secret !== null && $this->totp_confirme_at !== null;
    }

    public function totpObligatoire(): bool
    {
        return (bool) $this->role?->totp_obligatoire;
    }

    protected function nomComplet(): Attribute
    {
        return Attribute::get(fn () => trim("{$this->prenom} {$this->nom}"));
    }

    /** Notification de réinitialisation en français, pointant vers le frontend. */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\ReinitialisationMotDePasse($token));
    }
}
