<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
        'type', 'nom', 'prenoms', 'telephone', 'email', 'date_naissance',
        'sexe', 'photo_path', 'empreinte_id', 'notes',
    ];

    protected function casts(): array
    {
        return ['date_naissance' => 'date'];
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
