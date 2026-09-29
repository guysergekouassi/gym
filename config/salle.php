<?php

return [

    'nom' => env('SALLE_NOM', 'GymFlow'),
    'adresse' => env('SALLE_ADRESSE', "Abidjan, Côte d'Ivoire"),
    'telephone' => env('SALLE_TELEPHONE', ''),

    // Tarif par défaut d'une entrée journalière (FCFA)
    'tarif_journalier' => (int) env('SALLE_TARIF_JOURNALIER', 2000),

    // Frais d'inscription ajoutés au premier abonnement d'un client (0 = aucun)
    'frais_inscription' => (int) env('SALLE_FRAIS_INSCRIPTION', 0),

    // Fond de caisse proposé par défaut à la clôture (FCFA)
    'fond_caisse' => (int) env('SALLE_FOND_CAISSE', 0),

    // Indicatif ajouté aux numéros à 10 chiffres pour WhatsApp (Côte d'Ivoire : 225)
    'indicatif' => env('SALLE_INDICATIF', '225'),

    /*
    | Messages aux membres (rappels, reçus, campagnes)
    | manuel         : les messages s'affichent dans « À faire », la caissière les envoie en un clic (WhatsApp s'ouvre)
    | whatsapp_cloud : envoi automatique via l'API WhatsApp Business de Meta (jeton + identifiant du numéro requis)
    | log            : écrit les messages dans storage/logs (tests)
    */
    'messagerie' => [
        'driver' => env('MESSAGERIE_DRIVER', 'manuel'),
        'whatsapp_token' => env('WHATSAPP_TOKEN'),
        'whatsapp_phone_id' => env('WHATSAPP_PHONE_ID'),
        'heure_rappels' => env('RAPPELS_HEURE', '08:00'),
        'modeles' => [
            'expiration' => "Bonjour {prenom}, votre abonnement {formule} se termine le {fin}. Renouvelez à l'accueil ou depuis votre espace membre pour ne perdre aucun jour. À bientôt chez {salle} !",
            'expiration_jour' => "Bonjour {prenom}, votre abonnement {formule} se termine aujourd'hui. Passez à l'accueil pour le renouveler. À bientôt chez {salle} !",
            'inactif' => "Bonjour {prenom}, on ne vous a pas vu depuis {jours} jours à {salle}. Votre abonnement court jusqu'au {fin} : on vous attend ! Répondez à ce message si vous souhaitez le geler.",
            'anniversaire' => "Joyeux anniversaire {prenom} ! Toute l'équipe de {salle} vous souhaite une excellente journée.",
            'essai' => "Bonjour {prenom}, merci d'être venu(e) faire votre séance d'essai à {salle} ! Envie de continuer ? Passez à l'accueil pour vous inscrire.",
            'recu' => "Bonjour {prenom}, merci pour votre paiement de {montant} à {salle} ({objet}). Reçu n° {recu}.",
            'lien_membre' => "Bonjour {prenom}, voici votre espace membre {salle} : jours restants, QR code d'accès, réservation des cours. {lien}",
        ],
    ],

    /*
    | Paiement en ligne depuis l'espace membre (Mobile Money via CinetPay)
    | Laisser vide pour désactiver : le membre est alors invité à payer à l'accueil.
    */
    'paiement_en_ligne' => [
        'cinetpay_apikey' => env('CINETPAY_APIKEY'),
        'cinetpay_site_id' => env('CINETPAY_SITE_ID'),
        'cinetpay_url' => env('CINETPAY_URL', 'https://api-checkout.cinetpay.com/v2'),
    ],

    /*
    | Ouverture de porte ou de tourniquet après un accès autorisé
    | none : rien ; http : appel d'une URL (relais réseau type Shelly, contrôleur de porte…)
    */
    'porte' => [
        'driver' => env('PORTE_DRIVER', 'none'),
        'url' => env('PORTE_URL'), // ex. http://192.168.1.50/relay/0?turn=on&timer=3
    ],

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
