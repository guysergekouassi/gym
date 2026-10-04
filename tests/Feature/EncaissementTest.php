<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EncaissementTest extends TestCase
{
    use RefreshDatabase;

    public function test_on_voit_qui_a_encaisse_chaque_ticket(): void
    {
        $this->seed();
        User::query()->update(['doit_changer_mdp' => false]);
        $admin = User::where('role', User::ROLE_ADMIN)->firstOrFail();
        $caissiere = User::where('role', User::ROLE_CAISSIER)->firstOrFail();
        $caissiere->update(['name' => 'Awa Koné']);

        $this->actingAs($caissiere)->post('/caisse/journalier', ['mode' => 'especes']);

        $this->actingAs($admin)->get('/admin/paiements')
            ->assertOk()
            ->assertSeeInOrder(['Encaissé par', 'Awa Koné', 'Caissier']);
        $this->get('/admin/paiements/export')->assertOk();
    }
}
