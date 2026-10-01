# SalonFlow — Caisse hors ligne pour salon de coiffure

Caisse tactile pour un PC Windows d'accueil, **sans internet** : on clique sur les services, ils s'ajoutent au ticket,
le total se calcule tout seul, on encaisse et le ticket sort sur l'imprimante thermique.
La gérante suit ses ventes (jour, semaine, mois) avec des KPI clairs.

| Qui | Accès |
|---|---|
| **Gérante** | Tout : caisse, tableau de bord, historique et annulation de tickets, services & prix, comptes |
| **Assistante** (3 comptes actifs maximum) | La caisse et **ses propres** KPI, rien d'autre |

## 1. Installation sur le PC du salon (une seule fois, avec internet)

1. Installer **[Laragon](https://laragon.org/download/)** (version « Full »). Il fournit PHP 8.3+ et Composer.
   Vérifier dans un terminal Laragon : `php -v` affiche 8.3 ou plus.
2. Copier le dossier du projet, par exemple dans `C:\SalonFlow`, puis dans le terminal Laragon :

```bash
cd C:\SalonFlow
composer setup
```

`composer setup` installe les dépendances, crée le `.env`, la base SQLite (`database/database.sqlite`),
les tables, la liste de services d'exemple et le compte gérante.

3. Ouvrir `.env` et mettre les informations du salon (elles s'impriment en haut du ticket) :

```dotenv
SALON_NOM="Salon Belle Tresse"
SALON_ADRESSE="Yopougon, Abidjan"
SALON_TELEPHONE="07 00 00 00 00"
SALON_MESSAGE_TICKET="Merci de votre visite, à bientôt !"
TICKET_LARGEUR_MM=80        # 58 si le papier est étroit
```

Après ça, **plus besoin d'internet** : tout tourne sur le PC (base SQLite, aucun CDN, aucune police externe).

## 2. Utilisation au quotidien

- Double-cliquer sur **`demarrer-salon.bat`** (faites-en un raccourci sur le bureau).
  Il démarre le serveur local dans une fenêtre réduite (ne pas la fermer) et ouvre la caisse dans Chrome.
- Première connexion : `gerante@salon.local` / `ChangeMoi!2026`.
  **Changer le mot de passe tout de suite** (menu en haut à droite → *Mon mot de passe*).
- La gérante crée les comptes des assistantes dans **Comptes assistantes**.

### La caisse

- Cliquer sur une carte = +1 dans le ticket. Le badge vert sur la carte indique la quantité.
- Dans le ticket : `+` / `−` pour la quantité, `✕` pour retirer une ligne, *Vider* pour tout effacer.
- Recherche : taper le nom ou le code puis **Entrée** ajoute le premier résultat (touche `/` pour aller dans la recherche).
- Paiement **Espèces / Mobile Money / Carte**. En espèces, saisir le montant reçu (ou un bouton billet) :
  la monnaie à rendre s'affiche et l'encaissement est bloqué si le montant est insuffisant.
- **Encaisser & imprimer** : la vente est enregistrée, le ticket s'imprime et la caisse est prête pour la cliente suivante.

Les prix sont toujours recalculés par le serveur à partir du catalogue, et le ticket garde le nom et le prix du jour de la vente :
changer un prix plus tard ne modifie pas l'historique.

## 3. Imprimante ticket thermique

**Mode par défaut (`TICKET_DRIVER=navigateur`) — recommandé**

1. Installer le pilote Windows de l'imprimante (58 ou 80 mm) et la définir comme **imprimante par défaut**.
2. Dans les préférences de l'imprimante : format de papier = rouleau 80 mm (ou 58 mm), marges 0.
3. `demarrer-salon.bat` lance Chrome avec `--kiosk-printing` : le ticket part **directement** sur l'imprimante,
   sans fenêtre d'impression.

**Mode direct ESC/POS (`TICKET_DRIVER=escpos`)** — si l'imprimante doit couper le papier automatiquement ou si
le pilote Windows pose problème :

```bash
composer require mike42/escpos-php
```

```dotenv
TICKET_DRIVER=escpos
TICKET_CONNECTEUR=windows   # windows (imprimante partagée) | network (IP) | fichier
TICKET_CIBLE=TICKET         # nom de partage Windows de l'imprimante, ou son IP
TICKET_PORT=9100
```

Pour le connecteur `windows`, partager l'imprimante dans Windows sous le nom indiqué dans `TICKET_CIBLE`.

## 4. Tableau de bord de la gérante

Période : **Aujourd'hui / Semaine / Mois** ou dates libres.

| KPI | Détail |
|---|---|
| Chiffre d'affaires, tickets, panier moyen | avec l'évolution en % par rapport à la période précédente |
| Prestations réalisées | nombre de services vendus, tickets annulés et leur montant |
| CA par heure (jour) ou par jour (semaine, mois) | graphique en barres |
| Par catégorie et par mode de paiement | part de chaque catégorie / moyen de paiement |
| Top 10 des services | quantité et CA |
| Ventes par personne | tickets, CA et panier moyen de chaque assistante (et de la gérante) |
| Heures d'affluence, jours les plus rentables | pour organiser le planning |

Pour une période en cours, l'évolution compare la **même durée écoulée** : jeudi 18 h cette semaine contre
jeudi 18 h la semaine dernière, et pas contre la semaine dernière entière.

**Historique des ventes** : tous les tickets, filtrables par période, par personne ou par statut. On peut les réimprimer.
Un ticket annulé (avec motif obligatoire) reste visible mais ne compte plus dans les KPI.
Seule la gérante peut annuler un ticket.

**Services & prix** : ajouter, modifier, masquer de la caisse ou supprimer un service. On gère aussi les catégories
(nom, couleur des cartes, ordre d'affichage).

## 5. Sauvegardes

Toutes les ventes sont dans **un seul fichier** : `database/database.sqlite`.
Double-cliquer sur **`sauvegarder.bat`** chaque soir : il copie ce fichier dans `sauvegardes/` en le datant.
Copier régulièrement ce dossier sur une clé USB. Pour restaurer une sauvegarde, remettre le fichier à la place de
`database/database.sqlite`.

Mot de passe gérante oublié :

```bash
php artisan salon:mot-de-passe gerante@salon.local NouveauMotDePasse
```

## 6. Tests

```bash
php artisan test
```

Les tests vérifient : prix recalculés côté serveur, numérotation des tickets (`T-AAAAMMJJ-0001`, remise à zéro chaque jour),
espèces insuffisantes refusées, limite de 3 assistantes actives, accès refusé aux assistantes en dehors de la caisse
et de leurs KPI, tickets des autres invisibles, KPI jour / semaine / dates et évolution, tickets annulés exclus,
gestion des services.

## Arborescence

```
app/
  Http/Controllers/  Caisse, Ticket, Dashboard, MesKpi, Service, Categorie, Compte, MotDePasse, Auth/Login
  Http/Middleware/   VerifierRole (gerante / assistante)
  Models/            User, Categorie, Service, Vente, VenteLigne
  Services/          VenteService, KpiService, CompteService, TicketService (ESC/POS)
  Support/           Fcfa, Periode
config/salon.php     nom du salon, ticket, modes de paiement, maximum d'assistantes
public/css/app.css   styles (aucun CDN)
public/js/caisse.js  écran de caisse
resources/views/     caisse, tickets, dashboard, mes-kpi, ventes, services, comptes, auth
demarrer-salon.bat   lance le serveur et la caisse
sauvegarder.bat      sauvegarde de la base
```
