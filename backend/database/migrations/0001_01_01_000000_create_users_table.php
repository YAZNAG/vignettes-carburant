<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- RBAC : rôles et permissions atomiques -------------------------
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('libelle', 100);
            $table->text('description')->nullable();
            $table->boolean('totp_obligatoire')->default(false);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique(); // nomenclature domaine.action
            $table->string('libelle', 150);
            $table->string('domaine', 50)->index();
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        // --- Référentiels auxiliaires (avant users : users.service_id) -----
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('libelle', 100);
            $table->string('code', 20)->unique();
            $table->string('responsable', 100)->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
        });

        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('libelle', 100)->unique();
            $table->string('ville', 100)->nullable();
            $table->string('region', 100)->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
        });

        // --- Utilisateurs ---------------------------------------------------
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 50);
            $table->string('prenom', 50);
            $table->string('email')->unique();
            $table->string('username', 50)->unique();
            $table->string('password');
            $table->string('telephone', 20)->nullable();
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->restrictOnDelete();
            $table->foreignId('site_id')->nullable()->constrained('sites')->restrictOnDelete();
            $table->boolean('actif')->default(true);
            $table->boolean('doit_changer_mdp')->default(true);
            // 2FA TOTP
            $table->text('totp_secret')->nullable();      // chiffré applicativement
            $table->timestamp('totp_confirme_at')->nullable();
            // anti force brute
            $table->unsignedSmallInteger('echecs_connexion')->default(0);
            $table->timestamp('verrouille_jusqua')->nullable();
            $table->timestamp('derniere_connexion_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
        });

        // FK différées : services/sites créés avant users
        Schema::table('services', function (Blueprint $table) {
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
        });
        Schema::table('sites', function (Blueprint $table) {
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
            // durée de vie maximale absolue (12 h) : instant de connexion
            $table->integer('login_at')->nullable();
        });

        // Identifiants insensibles à la casse (unicité garantie par la base)
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN email TYPE citext');
            DB::statement('ALTER TABLE users ALTER COLUMN username TYPE citext');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('sites');
        Schema::dropIfExists('services');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
