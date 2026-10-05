<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Sessions
{
    /**
     * Déconnecte un utilisateur partout (sessions ouvertes + cookies "rester connecté"),
     * sauf éventuellement la session courante.
     */
    public static function revoquer(User $user, ?string $saufSessionId = null): void
    {
        $user->setRememberToken(Str::random(60));
        $user->save();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->when($saufSessionId, fn ($q) => $q->where('id', '!=', $saufSessionId))
                ->delete();
        }
    }
}
