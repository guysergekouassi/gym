<?php

namespace App\Services;

use App\Models\Abonnement;
use App\Models\Client;
use App\Models\Message;
use App\Models\Paiement;
use App\Models\Passage;
use App\Models\Prospect;
use App\Support\Fcfa;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Messages aux membres : rappels automatiques, reçus, campagnes.
 * Selon le driver, ils partent seuls (WhatsApp Business) ou attendent dans « À faire ».
 */
class MessageService
{
    /** Remplace {prenom}, {fin}, {salle}… dans un modèle de message. */
    public function rediger(string $modele, array $variables): string
    {
        $variables += ['salle' => config('salle.nom')];

        return strtr($modele, collect($variables)->mapWithKeys(fn ($v, $k) => ['{'.$k.'}' => (string) $v])->all());
    }

    public function variablesClient(Client $client): array
    {
        $fin = $client->finDesDroits();
        $abonnement = $client->abonnementActif();

        return [
            'prenom' => $client->appel,
            'nom' => $client->nom_complet,
            'fin' => $fin?->format('d/m/Y') ?? '—',
            'formule' => $abonnement?->formule?->nom ?? '',
            'jours_restants' => $fin ? max(0, (int) today()->diffInDays($fin, false)) : 0,
        ];
    }

    /** Crée le message (une seule fois par client, type et jour) puis tente l'envoi automatique. */
    public function preparer(?Client $client, string $type, string $contenu, array $options = []): ?Message
    {
        $telephone = $options['telephone'] ?? $client?->telephone;
        $pourLe = $options['pour_le'] ?? today();

        if (! $telephone) {
            return null;
        }

        if ($client && empty($options['campagne_id']) && Message::where('client_id', $client->id)
            ->where('type', $type)->whereDate('pour_le', $pourLe->toDateString())->exists()) {
            return null;
        }

        $message = Message::create([
            'client_id' => $client?->id,
            'prospect_id' => $options['prospect_id'] ?? null,
            'campagne_id' => $options['campagne_id'] ?? null,
            'type' => $type,
            'telephone' => $telephone,
            'contenu' => $contenu,
            'pour_le' => $pourLe,
        ]);

        $this->envoyer($message);

        return $message;
    }

    /** Envoie si un canal automatique est configuré ; sinon le message reste « à envoyer ». */
    public function envoyer(Message $message): void
    {
        $driver = config('salle.messagerie.driver');

        if ($driver === 'log') {
            Log::info("Message {$message->type} pour {$message->telephone} : {$message->contenu}");
            $this->marquerEnvoye($message);

            return;
        }

        if ($driver !== 'whatsapp_cloud' || ! config('salle.messagerie.whatsapp_token')) {
            return; // mode manuel : la caissière l'enverra depuis « À faire »
        }

        try {
            $numero = preg_replace('/\D/', '', $message->telephone);
            if (strlen($numero) === 10) {
                $numero = config('salle.indicatif').$numero;
            }

            Http::withToken(config('salle.messagerie.whatsapp_token'))
                ->timeout(10)
                ->post('https://graph.facebook.com/v20.0/'.config('salle.messagerie.whatsapp_phone_id').'/messages', [
                    'messaging_product' => 'whatsapp',
                    'to' => $numero,
                    'type' => 'text',
                    'text' => ['body' => $message->contenu],
                ])->throw();

            $this->marquerEnvoye($message);
        } catch (Throwable $e) {
            report($e);
            $message->update(['statut' => 'echec', 'erreur' => mb_substr($e->getMessage(), 0, 255)]);
        }
    }

    public function marquerEnvoye(Message $message, ?int $userId = null): void
    {
        $message->update(['statut' => 'envoye', 'envoye_le' => now(), 'user_id' => $userId, 'erreur' => null]);
    }

    /** Message de reçu après un paiement (envoyé seulement si un canal automatique est actif). */
    public function recu(Paiement $paiement): ?Message
    {
        $client = $paiement->client;
        if (! $client?->telephone || config('salle.messagerie.driver') === 'manuel') {
            return null;
        }

        return $this->preparer($client, 'recu', $this->rediger(config('salle.messagerie.modeles.recu'), [
            'prenom' => $client->appel,
            'montant' => Fcfa::format($paiement->montant),
            'objet' => $paiement->objet(),
            'recu' => $paiement->numero_recu,
        ]));
    }

    /**
     * Rappels du jour : échéances à J-3 et J0, inactifs, anniversaires, essais non convertis.
     *
     * @return array<string, int> nombre de messages créés par type
     */
    public function genererRappels(?Carbon $jour = null): array
    {
        $jour ??= today();
        $modeles = config('salle.messagerie.modeles');
        $compte = ['expiration' => 0, 'inactif' => 0, 'anniversaire' => 0, 'essai' => 0];

        // Échéances : abonnements à la durée qui finissent dans 3 jours ou aujourd'hui, sans suite déjà payée
        foreach ([3 => 'expiration', 0 => 'expiration_jour'] as $delai => $modele) {
            $fins = Abonnement::with(['client', 'formule'])->duree()
                ->where('statut', Abonnement::STATUT_ACTIF)
                ->whereDate('date_fin', $jour->copy()->addDays($delai)->toDateString())
                ->get()
                ->filter(fn (Abonnement $a) => $a->client && $a->client->finDesDroits()?->isSameDay($a->date_fin));

            foreach ($fins as $abonnement) {
                $vars = ['prenom' => $abonnement->client->appel, 'formule' => $abonnement->formule->nom, 'fin' => $abonnement->date_fin->format('d/m/Y')];
                if ($this->preparer($abonnement->client, 'expiration', $this->rediger($modeles[$modele], $vars), ['pour_le' => $jour])) {
                    $compte['expiration']++;
                }
            }
        }

        // Inactifs : abonnement en cours, aucune venue depuis N jours, pas relancés depuis N jours
        $seuil = (int) config('salle.kpi.inactif_jours');
        $inactifs = Client::abonnes()
            ->whereHas('abonnements', fn ($q) => $q->enCours($jour))
            ->whereDoesntHave('passages', fn ($q) => $q->where('statut', Passage::STATUT_AUTORISE)->where('passe_le', '>=', $jour->copy()->subDays($seuil)))
            ->whereDoesntHave('messages', fn ($q) => $q->where('type', 'inactif')->whereDate('pour_le', '>', $jour->copy()->subDays($seuil)->toDateString()))
            ->whereDoesntHave('gels', fn ($q) => $q->whereDate('du', '<=', $jour->toDateString())->whereDate('au', '>=', $jour->toDateString()))
            ->withMax(['passages as dernier' => fn ($q) => $q->where('statut', Passage::STATUT_AUTORISE)], 'passe_le')
            ->get();

        foreach ($inactifs as $client) {
            $jours = $client->dernier ? (int) Carbon::parse($client->dernier)->diffInDays($jour) : $seuil;
            $vars = ['jours' => $jours] + $this->variablesClient($client);
            if ($this->preparer($client, 'inactif', $this->rediger($modeles['inactif'], $vars), ['pour_le' => $jour])) {
                $compte['inactif']++;
            }
        }

        // Anniversaires
        $anniversaires = Client::whereNotNull('date_naissance')->get()
            ->filter(fn (Client $c) => $c->date_naissance->format('m-d') === $jour->format('m-d'));
        foreach ($anniversaires as $client) {
            if ($this->preparer($client, 'anniversaire', $this->rediger($modeles['anniversaire'], ['prenom' => $client->appel]), ['pour_le' => $jour])) {
                $compte['anniversaire']++;
            }
        }

        // Séances d'essai faites il y a 2 jours, toujours pas inscrits
        $essais = Prospect::where('statut', 'essai_fait')
            ->whereDate('essai_le', $jour->copy()->subDays(2)->toDateString())
            ->whereNotNull('telephone')
            ->get();
        foreach ($essais as $prospect) {
            $deja = Message::where('prospect_id', $prospect->id)->where('type', 'essai')->exists();
            if (! $deja) {
                $prenom = explode(' ', trim($prospect->nom))[0];
                $this->preparer(null, 'essai', $this->rediger($modeles['essai'], ['prenom' => $prenom]), [
                    'telephone' => $prospect->telephone, 'prospect_id' => $prospect->id, 'pour_le' => $jour,
                ]);
                $compte['essai']++;
            }
        }

        return $compte;
    }
}
