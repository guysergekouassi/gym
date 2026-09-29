<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
        'sexe', 'photo_path', 'empreinte_id', 'carte_id', 'parrain_id', 'notes', 'objectif', 'programme',
    ];

    protected $hidden = ['jeton_membre'];

    protected function casts(): array
    {
        return ['date_naissance' => 'date'];
    }

    protected static function booted(): void
    {
        // Code personnel du QR d'accès, attribué une fois pour toutes
        static::creating(function (Client $client) {
            $client->code_acces ??= self::nouveauCodeAcces();
        });
    }

    public static function nouveauCodeAcces(): string
    {
        do {
            $code = 'GF'.strtoupper(Str::random(10));
        } while (self::withTrashed()->where('code_acces', $code)->exists());

        return $code;
    }

    /** Crée un nouveau lien personnel vers l'espace membre ; renvoie le jeton en clair. */
    public function genererJetonMembre(): string
    {
        $jeton = Str::random(40);
        $this->forceFill(['jeton_membre' => hash('sha256', $jeton)])->save();

        return $jeton;
    }

    public static function parJetonMembre(string $jeton): ?self
    {
        return self::where('jeton_membre', hash('sha256', $jeton))->first();
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

    public function gels(): HasMany
    {
        return $this->hasMany(Gel::class);
    }

    public function mesures(): HasMany
    {
        return $this->hasMany(Mesure::class);
    }

    public function packs(): HasMany
    {
        return $this->hasMany(PackCoaching::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function parrain(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parrain_id');
    }

    public function filleuls(): HasMany
    {
        return $this->hasMany(self::class, 'parrain_id');
    }

    public function dernierPassage(): HasOne
    {
        return $this->hasOne(Passage::class)->ofMany(
            ['passe_le' => 'max', 'id' => 'max'],
            fn (Builder $q) => $q->where('statut', Passage::STATUT_AUTORISE)
        );
    }

    /**
     * Abonnement qui donne accès aujourd'hui : d'abord un abonnement à la durée,
     * sinon un carnet dont il reste des entrées.
     */
    public function abonnementActif(): ?Abonnement
    {
        return $this->abonnements()->enCours()->with('formule')
            ->where(fn ($q) => $q->whereNull('entrees_restantes')->orWhere('entrees_restantes', '>', 0))
            ->orderByRaw('entrees_restantes is not null')
            ->orderByDesc('date_fin')
            ->first();
    }

    /** Date de fin des droits à la durée, renouvellements anticipés compris. */
    public function finDesDroits(): ?Carbon
    {
        $fin = $this->abonnements()
            ->duree()
            ->where('statut', Abonnement::STATUT_ACTIF)
            ->whereDate('date_fin', '>=', today()->toDateString())
            ->max('date_fin');

        return $fin ? Carbon::parse($fin) : null;
    }

    public function gelEnCours(): ?Gel
    {
        return $this->gels()
            ->whereDate('du', '<=', today()->toDateString())
            ->whereDate('au', '>=', today()->toDateString())
            ->first();
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

    /** Prénom si connu, sinon nom : pour s'adresser au membre dans les messages. */
    protected function appel(): Attribute
    {
        return Attribute::get(fn () => $this->prenoms ? explode(' ', trim($this->prenoms))[0] : $this->nom);
    }

    protected function initiales(): Attribute
    {
        return Attribute::get(fn () => mb_strtoupper(mb_substr($this->nom, 0, 1).mb_substr($this->prenoms ?? '', 0, 1)));
    }

    protected function photoUrl(): Attribute
    {
        return Attribute::get(fn () => $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null);
    }
}
