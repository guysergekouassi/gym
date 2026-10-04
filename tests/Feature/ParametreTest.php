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

    public function test_horaires_des_seances_sur_le_ticket(): void
    {
        $this->seed();
        User::query()->update(['doit_changer_mdp' => false]);
        $admin = User::where('role', User::ROLE_ADMIN)->firstOrFail();
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-05 17:44:09')); // un lundi

        $base = ['nom' => 'Gym'];
        $this->actingAs($admin)->put('/admin/parametres', $base + ['horaires' => [1 => ['etat' => 'ouvert', 'debut' => '18:00', 'fin' => '08:00']]])
            ->assertSessionHasErrors('horaires.1.fin');
        $this->put('/admin/parametres', $base + ['horaires' => [1 => ['etat' => 'ouvert', 'debut' => '06:15', 'fin' => '08:00']]])
            ->assertSessionHasErrors('horaires.1.debut'); // heure hors liste

        $semaine = [];
        foreach (range(1, 5) as $j) {
            $semaine[$j] = ['etat' => 'ouvert', 'debut' => '06:00', 'fin' => '22:00'];
        }
        $semaine[6] = ['etat' => 'ouvert', 'debut' => '08:00', 'fin' => '14:00'];
        $semaine[7] = ['etat' => 'ferme', 'debut' => '06:00', 'fin' => '21:00'];
        $this->put('/admin/parametres', $base + ['horaires' => $semaine])->assertSessionHas('succes');

        $this->assertSame(['Lun–Ven 06:00 – 22:00', 'Sam 08:00 – 14:00', 'Dim Fermé'], \App\Support\Horaires::resume());
        $this->get('/admin/parametres')->assertOk()->assertSee('Horaires des séances');

        $this->post('/caisse/journalier', ['mode' => 'especes']);
        $this->get('/recus/'.Paiement::value('numero_recu'))
            ->assertSee('Arrivée : 17:44:09')
            ->assertSee('Séance du jour : 06:00 – 22:00');

        // Supprimer : tous les jours repassent à « non défini »
        $this->put('/admin/parametres', $base + ['horaires' => [1 => ['etat' => '']]])->assertSessionHas('succes');
        $this->assertSame([], \App\Support\Horaires::tous());
    }
}
