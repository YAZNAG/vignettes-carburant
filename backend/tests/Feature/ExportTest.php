<?php

namespace Tests\Feature;

use App\Models\Vehicule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_exports_excel_sont_accessibles_a_tous_les_roles(): void
    {
        Vehicule::create(['immatriculation' => 'M111222']);

        foreach ([$this->admin(), $this->gestionnaire(), $this->consultation()] as $user) {
            $this->actingAs($user)
                ->get('/api/vehicules-export')
                ->assertOk()
                ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        }
    }

    public function test_l_export_de_l_audit_est_reserve(): void
    {
        $this->actingAs($this->gestionnaire())->get('/api/audit/export')->assertForbidden();
        $this->actingAs($this->admin())->get('/api/audit/export')->assertOk();
    }
}
