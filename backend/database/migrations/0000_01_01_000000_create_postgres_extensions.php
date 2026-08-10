<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Extensions PostgreSQL requises :
     * - citext        : unicité insensible à la casse (immatriculations, matricules, e-mails)
     * - unaccent      : recherche insensible aux accents
     * - fuzzystrmatch : distance de Levenshtein pour la détection de quasi-doublons
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS citext');
        DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');
        DB::statement('CREATE EXTENSION IF NOT EXISTS fuzzystrmatch');

        // unaccent() n'est pas IMMUTABLE : wrapper indexable pour les index fonctionnels
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION f_unaccent(text) RETURNS text AS
            $func$ SELECT public.unaccent('public.unaccent', $1) $func$
            LANGUAGE sql IMMUTABLE PARALLEL SAFE STRICT
        SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP FUNCTION IF EXISTS f_unaccent(text)');
    }
};
