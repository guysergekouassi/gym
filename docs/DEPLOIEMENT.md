# Déployer GymFlow chez un client

## Où se trouvent les fichiers de lancement ?

Dans le **dossier principal de GymFlow**, à côté du fichier `artisan` (par exemple `C:\GymFlow\` ou `C:\laragon\www\gym\`) :

| Fichier | Quand l'utiliser |
|---|---|
| `installer-gymflow.bat` | **Une seule fois**, à l'installation chez le client |
| `activer-demarrage-auto.bat` | **Une seule fois** : GymFlow démarrera tout seul à chaque allumage du PC |
| `demarrer-gymflow.bat` | Pour lancer GymFlow à la main (inutile si le démarrage auto est activé) |
| `desactiver-demarrage-auto.bat` | Pour arrêter le démarrage automatique |

---

## 1. Installation chez le client (en local, sans internet)

C'est la bonne approche pour une salle : tout tourne sur le PC de caisse, ça marche même sans internet, et la pointeuse est à côté.

### Ce qu'il faut sur le PC du client

**PHP 8.3** (ou plus récent). Le plus simple est **Laragon** :

1. Téléchargez **Laragon** (laragon.org) et installez-le avec les options par défaut.
2. Vérifiez la version de PHP : clic droit dans Laragon → **PHP** → **Version**. Il faut la 8.3 ou plus. Sinon, ajoutez-la : clic droit → PHP → **Quick add** (ou téléchargez « PHP 8.3 Thread Safe x64 » sur windows.php.net et dézippez-le dans `C:\laragon\bin\php\`).
3. Rendez PHP accessible partout : Laragon → **Menu** → **Tools** → **Path** → **Add Laragon to Path**.
4. Contrôle : touche Windows → `cmd` → `php -v` doit afficher « PHP 8.3… ».

Pas besoin de MySQL : GymFlow utilise par défaut une base **SQLite** (un simple fichier).

### Préparer le dossier sur VOTRE PC (le développeur)

Dans le dossier du projet :

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
```

L'interface (`public/build`) est déjà compilée : Node.js est inutile chez le client.

Copiez ensuite **tout le dossier** du projet sur une clé USB, **sans** :

- le fichier `.env` (il contient VOS clés) ;
- `database/database.sqlite` (vos données de test) ;
- `node_modules` (inutile).

### Chez le client

1. Copiez le dossier dans `C:\GymFlow` (court et sans espaces, c'est plus sûr).
2. Double-clic sur **`installer-gymflow.bat`**. Il :
   - crée la configuration de production (`.env`, avec `APP_DEBUG=false`) ;
   - génère une **clé unique** pour ce client (qui sert à chiffrer les mots de passe de la pointeuse et les sessions) ;
   - crée la base de données et les comptes de départ.
3. Double-clic sur **`activer-demarrage-auto.bat`**.
4. Double-clic sur **`demarrer-gymflow.bat`**, puis connectez-vous et **changez les mots de passe** (c'est obligatoire à la première connexion).
5. Réglez la salle : Administration → **Paramètres** (nom, adresse, téléphone, e-mail), **Formules & tarifs**, **Utilisateurs** (un compte par caissière), **Pointeuses** (voir `GUIDE-CONFIGURATION-POINTEUSE.md`).

### « Est-ce la même commande que chez moi ? »

Oui, mais **vous n'avez plus à la taper** : `demarrer-gymflow.bat` lance

```
php artisan serve --host=127.0.0.1 --port=8005
php artisan pointeuse:ecouter
```

avec le port **8005** fixé. L'adresse à ouvrir dans le navigateur est toujours **http://127.0.0.1:8005**. Conseil : ajoutez-la aux favoris et créez un raccourci sur le bureau.

---

## 2. Si le PC s'éteint ou si GymFlow s'arrête

Tout est prévu pour qu'une personne qui n'est pas développeur n'ait **rien à taper** :

| Situation | Ce qui se passe |
|---|---|
| Le PC redémarre (coupure de courant, mise à jour Windows) | Grâce à `activer-demarrage-auto.bat`, GymFlow se relance à l'ouverture de session |
| GymFlow plante ou une fenêtre est fermée par erreur | Chaque partie redémarre seule au bout de 5 secondes |
| On double-clique deux fois sur `demarrer-gymflow.bat` | Le 2ᵉ clic ouvre seulement le navigateur, sans lancer un doublon |

Pour aller plus loin :

- **Session Windows sans mot de passe** sur le PC de caisse, pour que la session s'ouvre seule après une coupure : touche Windows → `netplwiz`. À réserver à un PC qui ne quitte pas la salle.
- **Redémarrage après coupure de courant** : dans le BIOS de certains PC fixes, l'option « Restore on AC power loss » = *Power On*.
- **Pas de mise en veille** : Windows + I → Système → Alimentation → Mettre en veille : *Jamais* (sur secteur).

### Imprimante à tickets (impression directe)

`demarrer-gymflow.bat` ouvre GymFlow dans une **fenêtre dédiée** de Chrome (ou Edge) réglée pour imprimer **sans fenêtre de confirmation** : à chaque encaissement, le ticket part tout seul sur l'**imprimante par défaut de Windows**.

1. Installez l'imprimante thermique avec le pilote du fabricant (CD ou site du fabricant), papier **80 mm**.
2. Windows + I → Bluetooth et appareils → **Imprimantes et scanners** :
   - désactivez **« Laisser Windows gérer mon imprimante par défaut »** ;
   - cliquez sur l'imprimante thermique → **Définir par défaut**.
3. Fermez toutes les fenêtres GymFlow et relancez `demarrer-gymflow.bat`.

| Situation | Ce qui se passe |
|---|---|
| Imprimante allumée | Le ticket sort aussitôt, la caisse revient toute seule |
| Imprimante éteinte, débranchée ou sans papier | Windows garde le ticket **en file d'attente** et l'imprime dès son retour ; il reste réimprimable depuis Encaissements |
| Aucune imprimante installée | Windows prend « Microsoft Print to PDF » et propose d'**enregistrer un PDF** : c'est ce qu'on voit pendant les tests, c'est normal |

> Ne pas ouvrir GymFlow depuis un Chrome ordinaire pour encaisser : il afficherait la fenêtre d'impression à chaque ticket. Utilisez la fenêtre ouverte par `demarrer-gymflow.bat` (la 1ʳᵉ fois, connectez-vous dedans : elle a son propre profil).

### Sauvegardes

À chaque démarrage, `demarrer-gymflow.bat` fait une **sauvegarde du jour** de la base dans `storage\app\sauvegardes\` (les 30 dernières sont gardées).
**Copiez ce dossier sur une clé USB une fois par semaine** : si le PC tombe en panne, c'est ce qui permet de tout récupérer.

### Mettre à jour GymFlow chez le client

1. Remplacez les fichiers par la nouvelle version, **sans** toucher à `.env`, `database\database.sqlite` ni `storage\`.
2. Dans le dossier, ouvrez `cmd` et tapez : `php artisan migrate --force`.
3. Relancez `demarrer-gymflow.bat`.

---

## 3. Plus tard : GymFlow en ligne

### Ce qui change

| | En local (aujourd'hui) | En ligne (plus tard) |
|---|---|---|
| Où tourne GymFlow | Sur le PC de caisse | Sur un serveur (VPS) avec un nom de domaine |
| Accès | Seulement depuis le PC de la salle | Depuis n'importe où (le gérant chez lui, sur son téléphone) |
| Internet | Inutile | **Obligatoire** à la salle |
| Base de données | SQLite (un fichier) | MySQL sur le serveur |
| Sécurité | Rien n'est ouvert sur internet | HTTPS obligatoire, mises à jour du serveur, sauvegardes automatiques |

### Le point clé : la pointeuse

Un serveur sur internet **ne peut pas joindre** la pointeuse, qui est sur le réseau local de la salle. Il ne faut surtout **pas** ouvrir la pointeuse sur internet (redirection de port sur la box) : ce serait une porte d'entrée pour des pirates.

La solution prévue : garder à la salle un **petit relais** sur le PC de caisse. C'est le programme `pointeuse:ecouter` qui tourne déjà, adapté pour :

1. lire les passages sur la pointeuse (réseau local, comme aujourd'hui) ;
2. les envoyer au serveur en ligne par HTTPS, avec un jeton secret (l'API `/api/pointage/empreinte` existe déjà) ;
3. récupérer auprès du serveur les membres et leurs dates de fin, pour les envoyer à la pointeuse.

Avantages : la pointeuse ne sort jamais du réseau local, et si internet coupe, le relais garde les passages et les envoie au retour de la connexion.
C'est un développement à faire le jour de la mise en ligne. Le reste de l'application (caisse, clients, tableaux de bord) est déjà prêt.

### Étapes de la mise en ligne (pour mémoire)

1. Louer un VPS (Ubuntu) et un nom de domaine.
2. Installer Nginx, PHP 8.3, MySQL, Certbot (certificat HTTPS gratuit Let's Encrypt).
3. Déployer le code, `.env` de production (`APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `APP_URL=https://…`).
4. `php artisan migrate --force`, `php artisan config:cache route:cache view:cache`.
5. Sauvegardes automatiques de la base (tous les jours, copie hors du serveur).
6. Installer le relais à la salle (point ci-dessus).
