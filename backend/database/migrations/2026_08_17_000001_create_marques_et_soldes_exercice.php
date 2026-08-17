<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- Référentiel des marques (fin de la saisie libre) --------------
        Schema::create('marques', function (Blueprint $table) {
            $table->id();
            $table->string('libelle', 50)->unique();
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
        });

        Schema::table('vehicules', function (Blueprint $table) {
            $table->foreignId('marque_id')->nullable()->after('immatriculation')
                ->constrained('marques')->restrictOnDelete();
        });

        // reprise des marques déjà saisies en texte libre
        foreach (DB::table('vehicules')->whereNotNull('marque')->get(['id', 'marque']) as $v) {
            $libelle = mb_convert_case(mb_strtolower(trim($v->marque)), MB_CASE_TITLE, 'UTF-8');
            $marqueId = DB::table('marques')->where('libelle', $libelle)->value('id')
                ?? DB::table('marques')->insertGetId([
                    'libelle' => $libelle, 'actif' => true,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            DB::table('vehicules')->where('id', $v->id)->update(['marque_id' => $marqueId]);
        }

        Schema::table('vehicules', function (Blueprint $table) {
            $table->dropColumn('marque');
        });

        // --- Solde initial par exercice ET par type de vignette ------------
        // (état initial : vignette carburant, e-vignette, ticket…)
        Schema::create('exercice_soldes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercice_id')->constrained('exercices')->cascadeOnDelete();
            $table->foreignId('type_vignette_id')->constrained('types_vignette')->restrictOnDelete();
            $table->decimal('solde_initial', 14, 2)->default(0);
            $table->timestamps();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->unique(['exercice_id', 'type_vignette_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercice_soldes');
        Schema::table('vehicules', function (Blueprint $table) {
            $table->string('marque', 50)->nullable();
            $table->dropConstrainedForeignId('marque_id');
        });
        Schema::dropIfExists('marques');
    }
};
