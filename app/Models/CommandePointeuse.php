<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommandePointeuse extends Model
{
    public const EN_ATTENTE = 'en_attente';
    public const ENVOYEE = 'envoyee';
    public const OK = 'ok';
    public const ERREUR = 'erreur';

    protected $table = 'commandes_pointeuse';

    protected $fillable = ['lecteur_id', 'commande', 'statut', 'retour', 'envoyee_le', 'terminee_le'];

    protected function casts(): array
    {
        return ['envoyee_le' => 'datetime', 'terminee_le' => 'datetime'];
    }

    public function lecteur(): BelongsTo
    {
        return $this->belongsTo(Lecteur::class);
    }
}
