<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Prospect;
use App\Services\KpiService;
use App\Services\MessageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** « À faire aujourd'hui » : messages prêts à envoyer, essais du jour, refus à régulariser. */
class TacheController extends Controller
{
    public function index(Request $request, KpiService $kpi): View
    {
        $type = $request->string('type')->value();
        $messages = Message::with(['client', 'prospect'])->aEnvoyer()->latest('pour_le')->get();

        return view('taches.index', [
            'type' => $type,
            'messages' => $type ? $messages->where('type', $type)->values() : $messages,
            'compteParType' => $messages->countBy('type'),
            'total' => $messages->count(),
            'essais' => Prospect::where('statut', 'essai_prevu')->whereDate('essai_le', today()->toDateString())->orderBy('essai_le')->get(),
            'aRegulariser' => $kpi->refusARegulariser(),
            'echecs' => Message::where('statut', 'echec')->whereDate('pour_le', '>=', today()->subDays(7)->toDateString())->count(),
        ]);
    }

    public function envoye(Request $request, Message $message, MessageService $messages): RedirectResponse
    {
        $messages->marquerEnvoye($message, $request->user()->id);

        return back()->with('succes', 'Message marqué comme envoyé.');
    }

    public function ignorer(Message $message): RedirectResponse
    {
        $message->update(['statut' => 'ignore']);

        return back()->with('succes', 'Message retiré de la liste.');
    }
}