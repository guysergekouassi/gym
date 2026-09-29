<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Journal;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Qui a fait quoi : annulations, clôtures, modifications. */
class JournalController extends Controller
{
    public function __invoke(Request $request): View
    {
        $entrees = Journal::with('user:id,name')
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')->value()))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->latest('created_at')
            ->latest('id')
            ->paginate(40)
            ->withQueryString();

        return view('admin.journal', [
            'entrees' => $entrees,
            'utilisateurs' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }
}