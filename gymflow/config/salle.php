<?php

return [

    'nom' => env('SALLE_NOM', 'GymFlow'),
    'adresse' => env('SALLE_ADRESSE', "Abidjan, Côte d'Ivoire"),
    'telephone' => env('SALLE_TELEPHONE', ''),

    // Tarif par défaut d'une entrée journalière (FCFA)
    'tarif_journalier' => (int) env('SALLE_TARIF_JOURNALIER', 2000),

    'kpi' => [
        'actif_jours' => (int) env('KPI_ACTIF_JOURS', 7),               // venu au moins 1 fois sur X jours
        'inactif_jours' => (int) env('KPI_INACTIF_JOURS', 14),          // aucune venue depuis X jours
        'renouvellement_delai_jours' => (int) env('KPI_RENOUV_DELAI', 15), // délai de grâce après échéance
        'expiration_alerte_jours' => (int) env('KPI_ALERTE_EXPIRATION', 7),
    ],

    // Un même client qui scanne plusieurs fois dans ce délai = un seul passage
    'anti_doublon_secondes' => (int) env('ANTI_DOUBLON_SECONDES', 120),

    'impression' => [
        // navigateur : reçu HTML 80 mm imprimé via le navigateur (fonctionne partout)
        // escpos     : impression directe, uniquement si le serveur voit l'imprimante
        'driver' => env('RECU_DRIVER', 'navigateur'),
        'connecteur' => env('RECU_CONNECTEUR', 'network'), // network | windows | fichier
        'cible' => env('RECU_CIBLE', '192.168.1.100'),       // IP, nom de partage Windows, ou /dev/usb/lp0
        'port' => (int) env('RECU_PORT', 9100),
    ],

];
