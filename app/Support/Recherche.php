<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Recherche "contient" sûre : les caractères spéciaux de LIKE (% et _) saisis
 * par l'utilisateur sont échappés, et la valeur passe toujours en paramètre lié.
 */
class Recherche
{
    public static function motif(string $terme): string
    {
        return '%'.strtr($terme, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
    }

    /** @param  list<string>  $colonnes */
    public static function appliquer(Builder $query, string $terme, array $colonnes): void
    {
        $motif = self::motif(mb_substr(trim($terme), 0, 100));
        $grammaire = $query->getQuery()->getGrammar();

        $query->where(function (Builder $w) use ($colonnes, $motif, $grammaire) {
            foreach ($colonnes as $colonne) {
                $w->orWhereRaw($grammaire->wrap($colonne)." LIKE ? ESCAPE '!'", [$motif]);
            }
        });
    }
}
