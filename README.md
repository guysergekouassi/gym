# GymFlow — Gestion de salle de sport (Laravel)

Deux accès :

- **Caissière** : encaisse les **passages** (séance à l'unité) et les **abonnements**, crée les fiches clients, attribue le n° de pointeuse, imprime le ticket. Elle ne voit que sa propre caisse et ne peut ni annuler un ticket ni modifier un prix.
- **Responsable (admin)** : tout ce que fait la caissière + tableau de bord, journal des encaissements (filtre, export Excel, annulation motivée), formules & tarifs, comptes du personnel, pointeuses.

Contrôle d'accès par **empreinte digitale** (pointeuse Hikvision), ticket sur **imprimante thermique** 80 ou 58 mm.

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

L'interface est **déjà compilée** dans `public/build` : ni Node.js ni internet ne sont nécessaires sur le poste de la salle. (Après une modification des vues : `npm install && npm run build`.)

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

Comptes créés — **le mot de passe doit être changé à la première connexion** (imposé par l'application) :

| Rôle | E-mail | Mot de passe |
|---|---|---|
| Admin | admin@gymflow.local | ChangeMoi!2026 |
| Caissière | caisse@gymflow.local | ChangeMoi!2026 |

## 5. Pointeuse à empreinte (Hikvision DS-K1T808MFWX)

Guide complet, avec schéma de montage : **[docs/INSTALLATION.md](docs/INSTALLATION.md)**.

En résumé : GymFlow dialogue avec la pointeuse par son API HTTP « ISAPI » (adresse IP + mot de passe admin de la pointeuse, stocké chiffré). Le programme `php artisan pointeuse:ecouter` (lancé par `demarrer-gymflow.bat`) récupère les passages toutes les 3 s et envoie les membres avec la date de fin de leurs droits : la pointeuse refuse d'elle-même un abonnement terminé.

## 6. Imprimante thermique (tickets)

| Mode | Quand l'utiliser |
|---|---|
| `navigateur` (défaut, recommandé) | Installer le pilote de l'imprimante sous Windows et la mettre **par défaut**. Le ticket s'imprime à chaque encaissement. Pour supprimer la boîte de dialogue : lancer Chrome avec `--kiosk-printing`. Régler `RECU_LARGEUR=58` pour un rouleau 58 mm. |
| `escpos` | Impression directe sans navigateur, **seulement si Laravel tourne sur le PC de la salle** ou sur le même réseau que l'imprimante. `composer require mike42/escpos-php` puis `RECU_DRIVER=escpos`, `RECU_CONNECTEUR=network\|windows\|fichier`, `RECU_CIBLE=192.168.1.100`. |

## 7. Sécurité (déjà en place)

- Rôles vérifiés côté serveur sur chaque route (une caissière qui tape une URL admin reçoit 403).
- Mots de passe : 10 caractères min., majuscule/minuscule/chiffre ; changement **obligatoire** à la première connexion et après réinitialisation par l'admin.
- Anti force brute : 5 essais par compte et par IP puis blocage 5 min ; échecs journalisés.
- Désactivation/réinitialisation d'un compte : déconnexion immédiate de toutes ses sessions.
- Montant du passage imposé par le serveur (la caissière ne peut pas saisir 0) ; aucun ticket supprimable, seule l'annulation motivée par l'admin (tracée : qui, quand, pourquoi).
- En-têtes HTTP : CSP stricte (aucun script inline ou tiers), anti-clickjacking, `nosniff`, pas de cache des pages connectées (poste partagé).
- Aucune ressource externe (CDN, polices) : rien ne peut être injecté par un tiers, et l'appli marche hors ligne.
- Photos : JPG/PNG/WebP uniquement, renommées aléatoirement. Recherches protégées (paramètres liés, jokers échappés). Export CSV protégé contre l'injection de formules Excel.
- Pointeuse : mot de passe chiffré, adresses du réseau local uniquement, aucune redirection suivie, aucune empreinte stockée par l'application ; GymFlow n'écoute que sur 127.0.0.1.

**Checklist de mise en production** : `APP_ENV=production`, `APP_DEBUG=false`, `php artisan key:generate` (clé unique), `SESSION_ENCRYPT=true`, HTTPS si accessible hors de la salle (+ `SESSION_SECURE_COOKIE=true`), `expose_php=Off` dans php.ini, sauvegarde quotidienne de la base, `php artisan config:cache route:cache view:cache`.

## 8. Règles des KPI (réglables dans `config/salle.php`)

| KPI | Définition |
|---|---|
| Abonné actif | Abonnement en cours + au moins une venue sur les 7 derniers jours |
| Moins actif | Abonnement en cours + aucune venue depuis 14 jours (liste de relance) |
| Renouvellement | Abonnement échu suivi d'un nouveau dans les 15 jours ; « en attente » tant que le délai court |
| Expire bientôt | Fin dans les 7 jours, sans prolongation déjà payée |

Un renouvellement anticipé ne fait perdre aucun jour : le nouvel abonnement démarre le lendemain de la fin de l'actuel.

## 9. Conformité

Les fiches clients sont des données personnelles (loi ivoirienne n° 2013-450) : déclaration du traitement à l'**ARTCI** et information des clients. Si un jour un lecteur à **empreinte** est utilisé, une autorisation préalable de l'ARTCI est obligatoire.

## Arborescence

```
app/Http/Controllers/        Caisse, Client, Dashboard, Recu, Accueil, Auth/{Login,MotDePasse}, Api/Pointage
app/Http/Controllers/Admin/  Paiement, Formule, Utilisateur, Lecteur
app/Http/Middleware/         VerifierRole, AuthentifierLecteur, ForcerChangementMotDePasse, EnTetesSecurite
app/Services/                CaisseService, PointageService, KpiService, RecuService
resources/views/             layouts, caisse, clients, dashboard, admin/*, accueil, recus, auth, errors
resources/js/                app.js (interface), accueil.js (écran d'entrée + lecteur USB)
tests/Feature/               GymFlowTest, SecuriteEtAdminTest
```
