<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Cours collectif récurrent (chaque semaine, même jour, même heure). */
class Cours extends Model
{
    protected $table = 'cours';

    public const JOURS = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];

    protected $fillable = ['nom', 'coach_id', 'jour_semaine', 'heure', 'duree_minutes', 'capacite', 'actif', 'salle_id'];

    protected function casts(): array
    {
        return ['actif' => 'boolean', 'jour_semaine' => 'integer', 'capacite' => 'integer', 'duree_minutes' => 'integer'];
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class);
    }

    public function salle(): BelongsTo
    {
        return $this->belongsTo(Salle::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function heureCourte(): string
    {
        return substr((string) $this->heure, 0, 5);
    }

    /** Prochaine date (aujourd'hui compris) à laquelle ce cours a lieu. */
    public function prochaineDate(?CarbonInterface $depuis = null): CarbonInterface
    {
        $date = ($depuis ?? today())->copy();
        while ((int) $date->isoWeekday() !== $this->jour_semaine) {
            $date->addDay();
        }

        return $date;
    }

    public function inscritsLe(string $date): int
    {
        return $this->reservations()->whereDate('date', $date)->whereIn('statut', ['reservee', 'presente'])->count();
    }
}