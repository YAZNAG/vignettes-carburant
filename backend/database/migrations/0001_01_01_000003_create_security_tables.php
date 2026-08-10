<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Interdiction de réutiliser les 3 derniers mots de passe
        Schema::create('password_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('password');
            $table->timestamp('created_at');
        });

        // Journalisation de chaque tentative de connexion (réussie ou non)
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('identifiant', 255);       // valeur saisie, compte existant ou non
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('succes')->default(false);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->index();
            $table->index(['identifiant', 'created_at']);
        });

        // Codes de secours 2FA (8 codes à usage unique, stockés hachés)
        Schema::create('two_factor_recovery_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('code');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at');
        });

        // Journal d'audit — table en AJOUT SEUL (trigger de protection ci-dessous)
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('action', 50)->index();     // connexion, creation, modification, desactivation, acces_refuse…
            $table->string('entite_type', 100)->nullable()->index();
            $table->unsignedBigInteger('entite_id')->nullable();
            $table->jsonb('avant')->nullable();
            $table->jsonb('apres')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->index();
            $table->index(['entite_type', 'entite_id']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            // Aucune modification ni suppression possible, y compris par un superutilisateur applicatif
            DB::statement(<<<'SQL'
                CREATE OR REPLACE FUNCTION audit_logs_protect() RETURNS trigger AS
                $func$
                BEGIN
                    RAISE EXCEPTION 'Le journal d''audit est en ajout seul : % interdit', TG_OP;
                END;
                $func$ LANGUAGE plpgsql
            SQL);
            DB::statement(<<<'SQL'
                CREATE TRIGGER audit_logs_append_only
                BEFORE UPDATE OR DELETE ON audit_logs
                FOR EACH ROW EXECUTE FUNCTION audit_logs_protect()
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS audit_logs_append_only ON audit_logs');
            DB::statement('DROP FUNCTION IF EXISTS audit_logs_protect()');
        }
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('two_factor_recovery_codes');
        Schema::dropIfExists('login_attempts');
        Schema::dropIfExists('password_histories');
    }
};
