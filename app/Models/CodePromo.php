<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CodePromo extends Model
{
    protected $table = 'codes_promo';

    public const TYPES = ['pourcentage' => 'Pourcentage', 'montant' => 'Montant fixe (FCFA)'];

    protected $fillable = ['code', 'type', 'valeur', 'expire_le', 'utilisations_max', 'utilisations', 'actif'];

    protected function casts(): array
    {
        return ['expire_le' => 'date', 'actif' => 'boolean', 'valeur' => 'integer', 'utilisations' => 'integer', 'utilisations_max' => 'integer'];
    }

    public function estUtilisable(): bool
    {
        return $this->actif
            && (! $this->expire_le || $this->expire_le->gte(today()))
            && (! $this->utilisations_max || $this->utilisations < $this->utilisations_max);
    }

    public function remisePour(int $prix): int
    {
        $remise = $this->type === 'pourcentage' ? intdiv($prix * min(100, $this->valeur), 100) : $this->valeur;

        return min($prix, $remise);
    }

    public function libelle(): string
    {
        return $this->type === 'pourcentage' ? "-{$this->valeur} %" : '-'.number_format($this->valeur, 0, ',', ' ').' F';
    }
}