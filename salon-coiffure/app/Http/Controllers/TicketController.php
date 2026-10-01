<?php

namespace App\Http\Controllers;

use App\Models\Vente;
use App\Services\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class TicketController extends Controller
{
    public function show(Request $request, Vente $vente, TicketService $tickets): View
    {
        $this->autoriser($request, $vente);
        $vente->load(['lignes', 'user']);

        // Mode escpos : le ticket part directement sur l'imprimante, la page sert d'aperçu
        if ($request->boolean('imprimer') && config('salon.ticket.driver') === 'escpos') {
            try {
                $tickets->imprimer($vente);
                session()->now('succes', 'Ticket envoyé à l\'imprimante.');
            } catch (Throwable $e) {
                report($e);
                session()->now('erreur', 'Impression directe impossible : '.$e->getMessage());
            }
        }

        return view('tickets.show', ['vente' => $vente]);
    }

    public function imprimer(Request $request, Vente $vente, TicketService $tickets): RedirectResponse
    {
        $this->autoriser($request, $vente);

        try {
            $tickets->imprimer($vente);

            return back()->with('succes', 'Ticket envoyé à l\'imprimante.');
        } catch (Throwable $e) {
            report($e);

            return back()->with('erreur', 'Impression directe impossible : '.$e->getMessage());
        }
    }

    /** Une assistante ne peut rouvrir que ses propres tickets */
    private function autoriser(Request $request, Vente $vente): void
    {
        $user = $request->user();

        abort_unless($user->isGerante() || $vente->user_id === $user->id, 403, 'Ce ticket ne vous appartient pas.');
    }
}
