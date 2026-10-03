<?php

namespace Tests\Feature;

use App\Models\Paiement;
use App\Models\Parametre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParametreTest extends TestCase
{
    use RefreshDatabase;

    public function test_ladmin_modifie_le_nom_et_les_coordonnees_du_ticket(): void
    {
        $this->seed();
        User::query()->update(['doit_changer_mdp' => false]);
        $admin = User::where('role', User::ROLE_ADMIN)->firstOrFail();
        $caissiere = User::where('role', User::ROLE_CAISSIER)->firstOrFail();

        $this->actingAs($caissiere)->get('/admin/parametres')->assertForbidden();
        $this->put('/admin/parametres', ['nom' => 'Pirate'])->assertForbidden();

        $this->actingAs($admin)->get('/admin/parametres')->assertOk()->assertSee('Aperçu du ticket');
        $this->put('/admin/parametres', ['nom' => '', 'email' => 'pas-un-mail'])->assertSessionHasErrors(['nom', 'email']);
        $this->put('/admin/parametres', [
            'nom' => 'Gymnase Épikaïzo', 'adresse' => 'Angré', 'telephone' => '07 07 07 07 07',
            'email' => 'contact@epikaizo.ci', 'message_recu' => '<b>À bientôt</b>',
        ])->assertSessionHas('succes');

        $this->assertSame('contact@epikaizo.ci', Parametre::valeur('salle_email'));
        Parametre::appliquerALaConfig();

        $this->actingAs($caissiere)->post('/caisse/journalier', ['mode' => 'especes']);
        $this->get('/recus/'.Paiement::value('numero_recu'))
            ->assertSee('Gymnase Épikaïzo')->assertSee('contact@epikaizo.ci')
            ->assertSee('&lt;b&gt;À bientôt&lt;/b&gt;', false); // texte échappé, jamais interprété
        $this->get('/ma-journee')->assertSee('Gymnase Épikaïzo');
    }
}
