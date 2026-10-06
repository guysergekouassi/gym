<?php

namespace App\Http\Controllers;

use App\Models\Paiement;
use Illuminate\View\View;

class RecuController extends Controller
{
    public function show(Paiement $paiement): View
    {
        $paiement->load(['client', 'user', 'abonnement.formule']);

        return view('recus.show', ['paiement' => $paiement]);
    }
}
