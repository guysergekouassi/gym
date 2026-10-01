<?php

return [

    'nom' => env('SALON_NOM', 'SalonFlow'),
    'adresse' => env('SALON_ADRESSE', "Abidjan, Côte d'Ivoire"),
    'telephone' => env('SALON_TELEPHONE', ''),
    'message_ticket' => env('SALON_MESSAGE_TICKET', 'Merci de votre visite, à bientôt !'),

    // La gérante peut créer au maximum X comptes assistantes actifs
    'max_assistantes' => (int) env('SALON_MAX_ASSISTANTES', 3),

    'modes_paiement' => [
        'especes' => 'Espèces',
        'mobile_money' => 'Mobile Money',
        'carte' => 'Carte',
    ],

    'ticket' => [
        // Largeur du papier de l'imprimante thermique : 80 ou 58 (mm)
        'largeur_mm' => (int) env('TICKET_LARGEUR_MM', 80),
        // navigateur : ticket HTML imprimé par Chrome (fonctionne partout)
        // escpos     : impression directe sur l'imprimante (composer require mike42/escpos-php)
        'driver' => env('TICKET_DRIVER', 'navigateur'),
        'connecteur' => env('TICKET_CONNECTEUR', 'windows'), // windows | network | fichier
        'cible' => env('TICKET_CIBLE', 'TICKET'),           // nom de partage Windows, IP, ou /dev/usb/lp0
        'port' => (int) env('TICKET_PORT', 9100),
    ],

];
