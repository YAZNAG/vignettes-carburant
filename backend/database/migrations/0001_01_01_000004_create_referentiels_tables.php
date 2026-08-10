<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajoute les colonnes d'auteur exigées sur chaque table métier.
     */
    private function auteurs(Blueprint $table): void
    {
        $table->foreignId('created_by')->nullable()->constrained('users');
        $table->foreignId('updated_by')->nullable()->constrained('users');
    }

    public function up(): void
    {
        Schema::create('beneficiaires', function (Blueprint $table) {
            $table->id();
            $table->string('matricule', 20)->unique();
            $table->string('nom', 50);
            $table->string('prenom', 50);
            $table->string('fonction', 80)->nullable();
            $table->foreignId('service_id')->nullable()->constrained('services')->restrictOnDelete();
            $table->foreignId('site_id')->nullable()->constrained('sites')->restrictOnDelete();
            $table->string('telephone', 20)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $this->auteurs($table);
        });

        Schema::create('vehicules', function (Blueprint $table) {
            $table->id();
            $table->string('immatriculation', 20)->unique();
            $table->string('marque', 50)->nullable();
            $table->string('modele', 50)->nullable();
            $table->string('type_vehicule', 20)->default('Voiture');
            $table->string('type_carburant', 20)->default('Gasoil');
            $table->foreignId('service_id')->nullable()->constrained('services')->restrictOnDelete();
            $table->foreignId('site_id')->nullable()->constrained('sites')->restrictOnDelete();
            $table->foreignId('conducteur_id')->nullable()->constrained('beneficiaires')->restrictOnDelete();
            $table->decimal('plafond_mensuel', 12, 2)->nullable();
            $table->string('statut', 20)->default('Actif');
            $table->date('date_mise_en_service')->nullable();
            $table->text('observation')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $this->auteurs($table);
        });

        Schema::create('types_vignette', function (Blueprint $table) {
            $table->id();
            $table->string('libelle', 100)->unique();
            $table->string('code', 20)->unique();
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $this->auteurs($table);
        });

        Schema::create('coupures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('type_vignette_id')->constrained('types_vignette')->restrictOnDelete();
            $table->decimal('valeur', 10, 2);
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $this->auteurs($table);
            $table->unique(['type_vignette_id', 'valeur']);
        });

        Schema::create('motifs_sortie', function (Blueprint $table) {
            $table->id();
            $table->string('libelle', 100)->unique();
            $table->string('code', 20)->unique();
            $table->text('description')->nullable();
            $table->boolean('necessite_validation')->default(false);
            $table->boolean('soumis_plafond')->default(false);
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $this->auteurs($table);
        });

        Schema::create('fournisseurs', function (Blueprint $table) {
            $table->id();
            $table->string('raison_sociale', 150);
            $table->string('identifiant_fiscal', 30)->nullable();
            $table->string('ice', 30)->nullable();
            $table->string('adresse', 255)->nullable();
            $table->string('ville', 100)->nullable();
            $table->string('telephone', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('contact', 100)->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $this->auteurs($table);
        });

        Schema::create('exercices', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('annee')->unique();
            $table->string('libelle', 100);
            $table->date('date_debut');
            $table->date('date_fin');
            $table->decimal('stock_initial', 14, 2)->default(0);
            $table->string('statut', 10)->default('ouvert'); // ouvert | cloture
            $table->timestamps();
            $this->auteurs($table);
        });

        // Paramètres généraux (clé / valeur, écran administrateur)
        Schema::create('parametres', function (Blueprint $table) {
            $table->id();
            $table->string('cle', 100)->unique();
            $table->text('valeur')->nullable();
            $table->string('libelle', 200)->nullable();
            $table->timestamps();
            $this->auteurs($table);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            // Unicité insensible à la casse garantie par la base
            DB::statement('ALTER TABLE vehicules ALTER COLUMN immatriculation TYPE citext');
            DB::statement('ALTER TABLE beneficiaires ALTER COLUMN matricule TYPE citext');

            // Un seul exercice ouvert à la fois
            DB::statement("CREATE UNIQUE INDEX exercices_un_seul_ouvert ON exercices ((statut)) WHERE statut = 'ouvert'");

            // Valeurs contraintes côté base
            DB::statement("ALTER TABLE vehicules ADD CONSTRAINT vehicules_type_vehicule_check CHECK (type_vehicule IN ('Voiture','Utilitaire','Camion','4x4','Autre'))");
            DB::statement("ALTER TABLE vehicules ADD CONSTRAINT vehicules_type_carburant_check CHECK (type_carburant IN ('Gasoil','Essence','Hybride','Électrique'))");
            DB::statement("ALTER TABLE vehicules ADD CONSTRAINT vehicules_statut_check CHECK (statut IN ('Actif','En réparation','Réformé'))");
            DB::statement("ALTER TABLE exercices ADD CONSTRAINT exercices_statut_check CHECK (statut IN ('ouvert','cloture'))");
            DB::statement('ALTER TABLE coupures ADD CONSTRAINT coupures_valeur_positive CHECK (valeur > 0)');

            // Recherche insensible aux accents et à la casse
            DB::statement('CREATE INDEX beneficiaires_nom_unaccent ON beneficiaires (f_unaccent(lower(nom)))');
            DB::statement('CREATE INDEX beneficiaires_prenom_unaccent ON beneficiaires (f_unaccent(lower(prenom)))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('parametres');
        Schema::dropIfExists('exercices');
        Schema::dropIfExists('fournisseurs');
        Schema::dropIfExists('motifs_sortie');
        Schema::dropIfExists('coupures');
        Schema::dropIfExists('types_vignette');
        Schema::dropIfExists('vehicules');
        Schema::dropIfExists('beneficiaires');
    }
};
