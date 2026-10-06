<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Formule extends Model
{
    protected $fillable = ['nom', 'duree_jours', 'prix', 'seances_par_jour', 'description', 'actif'];

    protected function casts(): array
    {
        return [
            'duree_jours' => 'integer',
            'prix' => 'integer',
            'seances_par_jour' => 'integer',
            'actif' => 'boolean',
        ];
    }

    public function abonnements(): HasMany
    {
        return $this->hasMany(Abonnement::class);
    }

    /** Passe « 1 séance par jour » : après son départ, le membre ne peut plus entrer avant le lendemain. */
    public function uneSeanceParJour(): bool
    {
        return $this->seances_par_jour === 1;
    }

    /** Durée et accès affichés dans les listes de formules, ex. « 30 jours · illimité ». */
    public function resume(): string
    {
        return "{$this->duree_jours}\u{00A0}jours · ".($this->uneSeanceParJour() ? "1\u{00A0}séance/jour" : 'illimité');
    }

    /** Avantages de la formule (serviettes, coaching…), un par ligne dans la description. */
    public function avantages(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $this->description))));
    }
}
