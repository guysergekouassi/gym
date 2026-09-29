<?php

use App\Http\Controllers\AccueilController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\MotDePasseController;
use App\Http\Controllers\CaisseController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CompteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Membre;
use App\Http\Controllers\PassageController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\ProspectController;
use App\Http\Controllers\RechercheController;
use App\Http\Controllers\RecuController;
use App\Http\Controllers\TacheController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1');

    Route::get('/mot-de-passe-oublie', [MotDePasseController::class, 'demande'])->name('password.request');
    Route::post('/mot-de-passe-oublie', [MotDePasseController::class, 'envoyer'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reinitialiser/{token}', [MotDePasseController::class, 'formulaire'])->name('password.reset');
    Route::post('/reinitialiser', [MotDePasseController::class, 'reinitialiser'])->name('password.update');
});

// Notification serveur à serveur de l'agrégateur de paiement (vérifiée par un appel retour)
Route::post('/paiement/notification', [Membre\PaiementController::class, 'notification'])
    ->middleware('throttle:60,1')->name('paiement.notification');

// Espace membre (lien personnel, sans mot de passe)
Route::prefix('membre')->name('membre.')->group(function () {
    Route::get('/connexion', [Membre\ConnexionController::class, 'create'])->name('connexion');
    Route::post('/connexion', [Membre\ConnexionController::class, 'store'])->middleware('throttle:5,1');
    Route::get('/acces/{jeton}', [Membre\ConnexionController::class, 'lien'])->middleware('throttle:20,1')->name('lien');

    Route::middleware('membre')->group(function () {
        Route::get('/', [Membre\EspaceController::class, 'accueil'])->name('accueil');
        Route::get('/historique', [Membre\EspaceController::class, 'historique'])->name('historique');
        Route::get('/progression', [Membre\EspaceController::class, 'progression'])->name('progression');
        Route::get('/qr.svg', [Membre\EspaceController::class, 'qr'])->name('qr');
        Route::get('/cours', [Membre\EspaceController::class, 'cours'])->name('cours');
        Route::post('/cours/{cours}/reserver', [Membre\EspaceController::class, 'reserver'])->name('cours.reserver');
        Route::post('/reservations/{reservation}/annuler', [Membre\EspaceController::class, 'annulerReservation'])->name('reservations.annuler');
        Route::get('/renouveler', [Membre\PaiementController::class, 'formules'])->name('renouveler');
        Route::post('/renouveler', [Membre\PaiementController::class, 'payer'])->middleware('throttle:10,1');
        Route::get('/paiement/{transaction}', [Membre\PaiementController::class, 'retour'])->name('paiement.retour');
        Route::post('/deconnexion', [Membre\ConnexionController::class, 'destroy'])->name('deconnexion');
    });
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', fn () => redirect()->route(
        auth()->user()->isAdmin() ? 'dashboard' : 'caisse.index'
    ));

    // Espace administrateur : pilotage, catalogue, équipe (pas d'encaissement)
    Route::middleware('role:admin')->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::delete('/clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');

        Route::prefix('admin')->name('admin.')->group(function () {
            Route::resource('caisses', Admin\CaisseController::class)->except('destroy')->parameters(['caisses' => 'caisse']);
            Route::resource('caissiers', Admin\CaissierController::class)->except(['show', 'destroy'])->parameters(['caissiers' => 'caissier']);
            Route::resource('formules', Admin\FormuleController::class)->except(['show', 'destroy']);
            Route::resource('promos', Admin\CodePromoController::class)->except(['show', 'destroy'])->parameters(['promos' => 'promo']);
            Route::resource('coachs', Admin\CoachController::class)->except(['show', 'destroy'])->parameters(['coachs' => 'coach']);
            Route::get('commissions', [Admin\CoachController::class, 'commissions'])->name('coachs.commissions');
            Route::resource('cours', Admin\CoursController::class)->except(['show', 'destroy'])->parameters(['cours' => 'cours']);
            Route::resource('produits', Admin\ProduitController::class)->except(['show', 'destroy']);
            Route::post('produits/{produit}/stock', [Admin\ProduitController::class, 'stock'])->name('produits.stock');
            Route::resource('salles', Admin\SalleController::class)->except(['show', 'destroy']);
            Route::resource('lecteurs', Admin\LecteurController::class)->only(['index', 'store', 'update']);
            Route::get('journal', Admin\JournalController::class)->name('journal');
            Route::get('exports', [Admin\ExportController::class, 'index'])->name('exports');
            Route::get('exports/{type}', [Admin\ExportController::class, 'telecharger'])->whereIn('type', ['paiements', 'clients', 'passages'])->name('exports.telecharger');
            Route::get('rapports', Admin\RapportController::class)->name('rapports');
            Route::resource('campagnes', Admin\CampagneController::class)->only(['index', 'create', 'store']);
        });
    });

    // Espace caisse : encaissements, uniquement sur la caisse attribuée
    Route::middleware(['role:caissier', 'caisse'])->group(function () {
        Route::get('/caisse', [CaisseController::class, 'index'])->name('caisse.index');
        Route::post('/caisse/journalier', [CaisseController::class, 'journalier'])->name('caisse.journalier');
        Route::post('/caisse/abonnement', [CaisseController::class, 'abonnement'])->name('caisse.abonnement');
        Route::post('/caisse/vente', [CaisseController::class, 'vente'])->name('caisse.vente');
        Route::post('/caisse/coaching', [CaisseController::class, 'coaching'])->name('caisse.coaching');
        Route::get('/caisse/cloture', [CaisseController::class, 'cloture'])->name('caisse.cloture');
        Route::post('/caisse/cloture', [CaisseController::class, 'cloturer']);
        Route::get('/caisse/clients', [CaisseController::class, 'clients'])->name('caisse.clients');
    });

    // Commun aux deux espaces
    Route::middleware('role:admin,caissier')->group(function () {
        Route::get('/recherche', RechercheController::class)->name('recherche');
        Route::get('/a-faire', [TacheController::class, 'index'])->name('taches.index');
        Route::post('/messages/{message}/envoye', [TacheController::class, 'envoye'])->name('messages.envoye');
        Route::post('/messages/{message}/ignorer', [TacheController::class, 'ignorer'])->name('messages.ignorer');

        Route::get('/recus/{paiement}', [RecuController::class, 'show'])->name('recus.show');
        Route::post('/recus/{paiement}/annuler', [RecuController::class, 'annuler'])->name('recus.annuler');

        Route::get('/clients/capture', [ClientController::class, 'capture'])->name('clients.capture');
        Route::resource('clients', ClientController::class)->except('destroy');
        Route::post('/clients/{client}/gels', [ClientController::class, 'geler'])->name('clients.gels.store');
        Route::post('/gels/{gel}/terminer', [ClientController::class, 'terminerGel'])->name('gels.terminer');
        Route::post('/clients/{client}/mesures', [ClientController::class, 'mesure'])->name('clients.mesures.store');
        Route::post('/clients/{client}/suivi', [ClientController::class, 'suivi'])->name('clients.suivi');
        Route::post('/clients/{client}/lien-membre', [ClientController::class, 'lienMembre'])->name('clients.lien-membre');
        Route::post('/packs/{pack}/seance', [ClientController::class, 'seanceCoaching'])->name('packs.seance');

        Route::resource('prospects', ProspectController::class)->only(['index', 'store', 'update']);
        Route::post('/prospects/{prospect}/convertir', [ProspectController::class, 'convertir'])->name('prospects.convertir');

        Route::get('/planning', [PlanningController::class, 'index'])->name('planning.index');
        Route::get('/planning/{cours}/{date}', [PlanningController::class, 'seance'])->name('planning.seance');
        Route::post('/planning/{cours}/{date}/reservations', [PlanningController::class, 'inscrire'])->name('planning.inscrire');
        Route::post('/reservations/{reservation}/statut', [PlanningController::class, 'statut'])->name('reservations.statut');

        Route::get('/passages', PassageController::class)->name('passages.index');

        Route::get('/compte', [CompteController::class, 'edit'])->name('compte.edit');
        Route::put('/compte', [CompteController::class, 'update'])->name('compte.update');

        Route::get('/accueil', [AccueilController::class, 'index'])->name('accueil.index');
        Route::get('/accueil/dernier', [AccueilController::class, 'dernier'])->name('accueil.dernier');
        Route::post('/accueil/badge', [AccueilController::class, 'badge'])->middleware('throttle:60,1')->name('accueil.badge');
    });
});
