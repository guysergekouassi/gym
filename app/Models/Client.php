<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class Client extends Model
{
    use SoftDeletes;

    public const TYPE_ABONNE = 'abonne';
    public const TYPE_JOURNALIER = 'journalier';

    public const TYPES = [
        self::TYPE_ABONNE => 'Abonné',
        self::TYPE_JOURNALIER => 'Journalier',
    ];

    protected $fillable = [
        'type', 'nom', 'prenoms', 'telephone', 'email', 'date_naissance', 'date_adhesion',
        'sexe', 'photo_path', 'empreinte_id', 'notes',
    ];

    protected function casts(): array
    {
        return ['date_naissance' => 'date', 'date_adhesion' => 'date'];
    }

    public function abonnements(): HasMany
    {
        return $this->hasMany(Abonnement::class);
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }

    public function passages(): HasMany
    {
        return $this->hasMany(Passage::class);
    }

    public function dernierPassage(): HasOne
    {
        return $this->hasOne(Passage::class)->ofMany(
            ['passe_le' => 'max', 'id' => 'max'],
            fn (Builder $q) => $q->where('statut', Passage::STATUT_AUTORISE)
        );
    }

    /** Abonnement valide aujourd'hui (le plus long si plusieurs). */
    public function abonnementActif(): ?Abonnement
    {
        return $this->abonnements()->enCours()->with('formule')->orderByDesc('date_fin')->first();
    }

    /** Abonnement valide à cet instant (le plus long si plusieurs). */
    public function abonnementLe(CarbonInterface $quand): ?Abonnement
    {
        return $this->abonnements()->enCours($quand)->with('formule')->orderByDesc('date_fin')->first();
    }

    /** Passe « 1 séance par jour » dont le départ est déjà badgé ce jour-là : plus d'entrée avant demain. */
    public function seanceDuJourFaite(CarbonInterface $quand): bool
    {
        return (bool) $this->abonnementLe($quand)?->formule?->uneSeanceParJour()
            && $this->passages()
                ->where('statut', Passage::STATUT_AUTORISE)
                ->where('sens', Passage::SENS_DEPART)
                ->whereBetween('passe_le', [$quand->copy()->startOfDay(), $quand])
                ->exists();
    }

    /** Ticket journalier payé (et non annulé) ce jour-là. */
    public function aPayeJournalierLe(CarbonInterface $jour): bool
    {
        return $this->paiements()->valides()
            ->where('type', Paiement::TYPE_JOURNALIER)
            ->whereDate('created_at', $jour->toDateString())
            ->exists();
    }

    /** Carnets Fidélité non annulés, du plus ancien au plus récent, avec leurs séances utilisées. */
    public function carnets(): EloquentCollection
    {
        return $this->paiements()->valides()
            ->where('type', Paiement::TYPE_CARNET)
            ->withCount(['passages as seances_utilisees' => fn (Builder $q) => $q->where('sens', Passage::SENS_ENTREE)])
            ->orderBy('id')
            ->get();
    }

    /** Carnet à décompter à la prochaine arrivée : le plus ancien qui a encore des séances. */
    public function carnetEnCours(): ?Paiement
    {
        return $this->carnets()->first(fn (Paiement $carnet) => $carnet->seancesRestantes() > 0);
    }

    public function seancesCarnet(): int
    {
        return $this->carnets()->sum(fn (Paiement $carnet) => $carnet->seancesRestantes());
    }

    /** Une séance de carnet est déjà décomptée ce jour-là : le départ et les badges suivants sont gratuits. */
    public function carnetUtiliseLe(CarbonInterface $jour): bool
    {
        return $this->passages()
            ->where('sens', Passage::SENS_ENTREE)
            ->whereDate('passe_le', $jour->toDateString())
            ->whereHas('paiement', fn (Builder $q) => $q->where('type', Paiement::TYPE_CARNET)->whereNull('annule_le'))
            ->exists();
    }

    /** Date de fin des droits, renouvellements anticipés compris. */
    public function finDesDroits(): ?Carbon
    {
        $fin = $this->abonnements()
            ->where('statut', Abonnement::STATUT_ACTIF)
            ->whereDate('date_fin', '>=', today()->toDateString())
            ->max('date_fin');

        return $fin ? Carbon::parse($fin) : null;
    }

    public function scopeAbonnes(Builder $query): void
    {
        $query->where('type', self::TYPE_ABONNE);
    }

    public function scopeJournaliers(Builder $query): void
    {
        $query->where('type', self::TYPE_JOURNALIER);
    }

    protected function nomComplet(): Attribute
    {
        return Attribute::get(fn () => trim($this->nom.' '.($this->prenoms ?? '')));
    }

    protected function photoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null);
    }
}
