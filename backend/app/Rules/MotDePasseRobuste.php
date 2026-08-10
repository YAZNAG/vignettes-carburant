<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Politique de mot de passe : 10 caractères minimum, au moins une majuscule,
 * une minuscule et un chiffre, et absent de la liste des mots de passe courants.
 */
class MotDePasseRobuste implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('Le mot de passe est invalide.');

            return;
        }

        if (mb_strlen($value) < 10) {
            $fail('Le mot de passe doit contenir au moins 10 caractères.');
        }
        if (! preg_match('/[A-Z]/u', $value)) {
            $fail('Le mot de passe doit contenir au moins une majuscule.');
        }
        if (! preg_match('/[a-z]/u', $value)) {
            $fail('Le mot de passe doit contenir au moins une minuscule.');
        }
        if (! preg_match('/[0-9]/', $value)) {
            $fail('Le mot de passe doit contenir au moins un chiffre.');
        }

        if (self::estCourant($value)) {
            $fail('Ce mot de passe est trop courant : choisissez un mot de passe plus original.');
        }
    }

    public static function estCourant(string $valeur): bool
    {
        static $liste = null;

        if ($liste === null) {
            $chemin = resource_path('data/mots_de_passe_courants.txt');
            $liste = is_file($chemin)
                ? array_flip(array_map('mb_strtolower', file($chemin, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)))
                : [];
        }

        return isset($liste[mb_strtolower(trim($valeur))]);
    }
}
