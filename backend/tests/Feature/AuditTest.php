<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditTest extends TestCase
{
    use RefreshDatabase;

    private function uneEntree(): AuditLog
    {
        return AuditLog::create([
            'action' => 'test',
            'created_at' => now(),
        ]);
    }

    public function test_le_journal_est_en_ajout_seul_aucune_modification_possible(): void
    {
        $entree = $this->uneEntree();

        $this->expectException(QueryException::class);
        DB::table('audit_logs')->where('id', $entree->id)->update(['action' => 'falsifie']);
    }

    public function test_le_journal_est_en_ajout_seul_aucune_suppression_possible(): void
    {
        $entree = $this->uneEntree();

        $this->expectException(QueryException::class);
        DB::table('audit_logs')->where('id', $entree->id)->delete();
    }

    public function test_l_ecran_d_audit_filtre_par_periode_action_et_entite(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->postJson('/api/services', ['libelle' => 'Filtré', 'code' => 'FIL'])
            ->assertCreated();

        $this->actingAs($admin)
            ->getJson('/api/audit?action=creation&entite_type=Service')
            ->assertOk()
            ->assertJsonPath('data.0.action', 'creation')
            ->assertJsonPath('data.0.entite_type', 'Service');

        // filtre par période excluante : aucun résultat
        $this->actingAs($admin)
            ->getJson('/api/audit?date_fin=2020-01-01')
            ->assertOk()
            ->assertJsonPath('total', 0);
    }
}
