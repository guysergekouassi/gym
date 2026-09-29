# GymFlow — Gestion de salle de sport (Laravel)

Abonnés et journaliers, pointage par empreinte, encaissement en caisse avec reçu, historique des passages et KPI (actifs, moins actifs, renouvellements).

## 1. Installer le projet

Prérequis : PHP 8.3+, Composer, et MySQL si tu ne veux pas SQLite (Laragon ou XAMPP font l'affaire).

```bash
git clone https://github.com/guysergekouassi/gym.git
cd gym
composer install
copy .env.example .env      # Windows (Linux/Mac : cp .env.example .env)
php artisan key:generate
```

## 2. Configurer le `.env`

Par défaut le projet utilise SQLite (aucune installation : à la question de `php artisan migrate`, réponds `yes` pour créer le fichier). Pour MySQL, crée la base `gymflow` puis :

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gymflow
DB_USERNAME=root
DB_PASSWORD=
```

Les réglages de la salle sont déjà dans `.env.example` (`SALLE_NOM`, `SALLE_ADRESSE`, `SALLE_TELEPHONE`, `SALLE_TARIF_JOURNALIER`, `RECU_DRIVER`).

L'interface charge Tailwind depuis son CDN : le poste doit avoir accès à internet pour l'affichage.

## 3. Lancer les tests

```bash
php artisan test
```

## 4. Installer la base

```bash
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

Le seeder affiche **le token du lecteur d'empreinte** : copie-le, il ne sera plus affiché.

Comptes créés (à changer immédiatement) :

| Rôle | E-mail | Mot de passe |
|---|---|---|
| Admin | admin@gymflow.local | ChangeMoi!2026 |
| Caissière | caisse@gymflow.local | ChangeMoi!2026 |

## 4 bis. Espaces et caisses

- **Espace administrateur** : tableau de bord, passages, clients, **Caisses** et **Caissières**. L'administrateur n'encaisse pas.
- **Espace caisse** : caisse, clients et passages du jour. Une caissière n'encaisse que sur la caisse qui lui est attribuée ; sans caisse active, la page caisse est bloquée.
- L'administrateur crée autant de caisses qu'il veut (`/admin/caisses`), puis un compte par caissière (`/admin/caissiers`) rattaché à une caisse. Chaque paiement enregistre sa caisse : le journal d'une caisse montre, jour par jour, les espèces à remettre et le Mobile Money encaissé.
- À l'installation, la caissière de démonstration est rattachée à « Caisse principale ».

## 4 ter. Fonctionnalités

| Domaine | Ce que fait GymFlow | Où |
|---|---|---|
| Caisse | Abonnements, carnets d'entrées, packs de coaching, ventes au bar avec stock, codes promo, frais d'inscription | Caisse |
| Fiabilité | Annulation d'un reçu avec motif (la caissière le jour même, l'admin toujours), clôture journalière avec comptage des billets FCFA et écart, journal des actions | Reçu, Clôturer la caisse, Journal |
| Accès | Empreinte, badge RFID ou QR code personnel ; gel d'abonnement ; ouverture de porte optionnelle ; écran d'accueil qui tient pendant une coupure réseau | Écran d'accueil (`/accueil`), fiche client |
| Relation membre | Rappels (échéance J-3 et J0, inactifs, anniversaires, essais), campagnes ciblées, lien WhatsApp en un clic | À faire, Campagnes |
| Ventes | Prospects et séances d'essai, parrainage | Prospects, fiche client |
| Cours | Planning hebdomadaire, réservations, liste d'attente, présences, coachs et commissions | Planning, Coachs et cours |
| Membre | Espace mobile installable : jours restants, QR code, réservation, progression, renouvellement en ligne | `/membre` (lien personnel envoyé par WhatsApp) |
| Pilotage | Plusieurs salles, rapports (recettes sur 12 mois, rétention, heures creuses, recette attendue), exports Excel | Tableau de bord, Rapports, Exports |
| Recherche | Ctrl + K ou « / » partout : client par nom, téléphone, n° d'empreinte ou de carte | Toutes les pages |

### Tâche planifiée (rappels du matin)

Les rappels sont préparés chaque jour à `RAPPELS_HEURE` par `php artisan salle:rappels`. Il faut que le planificateur Laravel tourne chaque minute :

- **Windows** : Planificateur de tâches → nouvelle tâche toutes les minutes : `php C:\chemin\vers\gym\artisan schedule:run`.
- **Linux** : `* * * * * cd /chemin/vers/gym && php artisan schedule:run >> /dev/null 2>&1`.

### WhatsApp

- `MESSAGERIE_DRIVER=manuel` (défaut) : les messages attendent dans **À faire** ; un clic ouvre WhatsApp avec le texte prêt.
- `MESSAGERIE_DRIVER=whatsapp_cloud` : envoi automatique via l'API WhatsApp Business de Meta (`WHATSAPP_TOKEN`, `WHATSAPP_PHONE_ID`). Meta impose des **modèles de messages approuvés** pour écrire à un client qui ne vous a pas écrit dans les 24 h : faites valider vos modèles avant d'activer ce mode.
- Les textes des messages se modifient dans `config/salle.php` (`messagerie.modeles`).

### Paiement en ligne (CinetPay)

Renseignez `CINETPAY_APIKEY` et `CINETPAY_SITE_ID` (compte marchand CinetPay). L'adresse de notification `https://votre-domaine/paiement/notification` doit être **accessible depuis internet en HTTPS** : ce mode ne fonctionne pas sur un serveur uniquement local. Chaque notification est revérifiée auprès de CinetPay avant d'enregistrer l'abonnement. Les paiements en ligne arrivent dans la caisse « Paiements en ligne ».

### Porte ou tourniquet

`PORTE_DRIVER=http` et `PORTE_URL=http://192.168.1.50/relay/0?turn=on&timer=3` : GymFlow appelle cette URL à chaque accès autorisé (relais réseau type Shelly ou contrôleur de porte). L'API renvoie aussi `ouvrir_porte: true` pour les lecteurs qui pilotent eux-mêmes la porte.

### Commandes utiles

```bash
php artisan salle:rappels                         # préparer les rappels du jour maintenant
php artisan salle:mot-de-passe admin@gymflow.local # nouveau mot de passe si un compte est bloqué
php artisan salle:lecteur "Entrée secondaire"      # créer un lecteur (aussi possible dans Salles et lecteurs)
```

## 5. Tester le pointage sans lecteur

1. Crée un client abonné avec `ID empreinte = 1`, puis abonne-le depuis la caisse.
2. Ouvre l'**écran d'accueil** dans un autre onglet : http://127.0.0.1:8000/accueil (connecté en caissière ou admin).
3. Simule un scan :

```bash
curl -X POST http://127.0.0.1:8000/api/pointage/empreinte \
  -H "Authorization: Bearer TON_TOKEN" \
  -H "Accept: application/json" \
  -d "empreinte_id=1"
```

L'écran d'accueil passe au vert avec la fiche du client. Avec un ID inconnu ou un abonnement expiré : écran rouge + motif.

## 6. Brancher le vrai lecteur d'empreinte

L'API attend `POST /api/pointage/empreinte` avec `empreinte_id` et le token en `Authorization: Bearer`.

- **Enrôlement** : le doigt est enregistré sur l'appareil, qui attribue un numéro. Ce numéro est saisi dans la fiche client (champ « ID empreinte »). On ne stocke jamais l'image de l'empreinte.
- **Transmission du scan** : soit l'appareil pousse lui-même vers une URL HTTP (mode push/ADMS de nombreux ZKTeco, via un petit adaptateur), soit un agent local sur le PC d'accueil lit le SDK du lecteur et appelle l'API.
- Un lecteur supplémentaire : `php artisan salle:lecteur "Entrée secondaire"`.

## 7. Impression des reçus

| Mode | Quand l'utiliser |
|---|---|
| `navigateur` (défaut) | Toujours fonctionnel. Reçu HTML 80 mm, impression automatique. Dans Chrome, régler l'imprimante thermique par défaut ; en mode kiosque (`--kiosk-printing`) l'impression part sans boîte de dialogue. |
| `escpos` | Impression directe. **Uniquement si le serveur Laravel est sur le réseau local de la salle** (un hébergement mutualisé distant ne voit pas l'imprimante). |

Pour `escpos` :

```bash
composer require mike42/escpos-php
```

```dotenv
RECU_DRIVER=escpos
RECU_CONNECTEUR=network   # network | windows | fichier
RECU_CIBLE=192.168.1.100  # IP de l'imprimante, nom de partage Windows, ou /dev/usb/lp0
RECU_PORT=9100
```

## 8. Règles des KPI (réglables dans `config/salle.php`)

| KPI | Définition |
|---|---|
| Abonné actif | Abonnement en cours + au moins une venue sur les 7 derniers jours |
| Moins actif | Abonnement en cours + aucune venue depuis 14 jours (liste de relance) |
| Renouvellement | Abonnement échu suivi d'un nouveau dans les 15 jours ; « en attente » tant que le délai court |
| Expire bientôt | Fin dans les 7 jours, sans prolongation déjà payée |

Un renouvellement anticipé ne fait perdre aucun jour : le nouvel abonnement démarre le lendemain de la fin de l'actuel.

## 9. Conformité

La collecte de données biométriques en Côte d'Ivoire nécessite une **autorisation préalable de l'ARTCI** (loi n° 2013-450 sur la protection des données personnelles). Prévoir aussi le consentement écrit des clients à l'enrôlement.

## Arborescence

```
app/
  Http/Controllers/  Accueil, Caisse, Client, Dashboard, Recu, Auth/Login, Api/Pointage
  Http/Middleware/   AuthentifierLecteur (token lecteur), VerifierRole
  Models/            Client, Abonnement, Formule, Paiement, Passage, Lecteur, User
  Services/          PointageService, CaisseService, KpiService, RecuService
  Support/Fcfa.php
config/salle.php
database/migrations, database/seeders
resources/views/     dashboard, caisse, clients, accueil, recus, auth, layouts
routes/              web.php, api.php, console.php
tests/Feature/       GymFlowTest.php
```
