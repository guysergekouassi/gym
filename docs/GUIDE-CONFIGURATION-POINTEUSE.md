# Guide pas à pas : relier la pointeuse Hikvision à GymFlow

Pointeuse **Hikvision DS-K1T808MFWX** · PC Windows 10 ou 11 · aucune connaissance réseau nécessaire.

![Schéma de montage](installation/schema-montage.png)

---

## 0. Avant de commencer

### Ce qu'il vous faut

| Élément | Où le trouver | Rôle |
|---|---|---|
| La pointeuse | — | Reconnaît le doigt du membre |
| Son chargeur | Dans la boîte ; l'étiquette doit indiquer **12 V ⎓ 1 A** (ou plus d'ampères) | Alimente la pointeuse |
| Le **câble jaune** (câble réseau RJ45) | Vous l'avez déjà | Relie la pointeuse au PC (montage par câble) |
| Le PC de caisse (votre PC portable convient) | — | Fait tourner GymFlow |
| Un papier et un stylo | — | Pour noter le mot de passe et les adresses (fiche page suivante) |

### Trois mots de vocabulaire

- **Adresse IP** : le « numéro de téléphone » d'un appareil sur le réseau, par exemple `192.168.50.64`. Le PC et la pointeuse doivent avoir des adresses qui commencent pareil (`192.168.50.…`) pour se parler.
- **Masque** : réglage technique qui accompagne l'adresse. Mettez toujours `255.255.255.0`.
- **Passerelle** : la « porte de sortie » du réseau. Avec le câble direct, on met l'adresse du PC.

### Ce que vous n'avez PAS besoin d'avoir

- **Internet** : ni GymFlow ni la pointeuse n'en ont besoin.
- **Une box** : avec le câble jaune direct, la box est inutile.

### Fiche à remplir (gardez-la en lieu sûr, pas à la vue des clients)

```
Mot de passe admin de la pointeuse : ............................
Adresse IP de la pointeuse         : 192.168.50.64  (ou ............ en Wi-Fi)
Adresse IP du PC (prise réseau)    : 192.168.50.10
Compte admin GymFlow               : ............................
```

---

## 1. Allumer et activer la pointeuse (une seule fois)

1. Branchez la **fiche ronde du chargeur** dans la **prise ronde du faisceau de fils** (fils rouge + noir) qui sort du dos de la pointeuse, puis le chargeur dans la prise murale.
   → **L'écran s'allume** : l'alimentation est bonne. Sinon, vérifiez que le chargeur indique bien 12 V.
2. Au premier allumage, l'écran demande d'**activer l'appareil** : il faut créer le **mot de passe admin**.
   - 8 à 16 caractères, avec des lettres **et** des chiffres (exemple : `Gym2026Entree`).
   - Le clavier de la pointeuse écrit des lettres comme un ancien téléphone : appuyez plusieurs fois sur `2` pour A, B, C… La touche **≡** sert en général à passer des chiffres aux lettres. **←** efface.
   - Saisissez-le deux fois puis validez avec **OK**.
3. **Notez ce mot de passe sur la fiche.** Il protège le menu de la pointeuse et GymFlow en a besoin.

> Mot de passe perdu ? Il faut réinitialiser la pointeuse (voir le manuel Hikvision ou le revendeur) : tous les réglages et les empreintes seront effacés.

**Les touches de la pointeuse :** **OK/MENU** (en haut à droite) ouvre le menu et valide ; **ESC** revient en arrière ; les **flèches** déplacent la sélection ; **←** efface. Les membres, eux, n'utilisent jamais les touches : ils posent seulement leur doigt.

**Le port USB** de la pointeuse sert à brancher une clé USB : export ou import des utilisateurs et des pointages, mise à jour du logiciel de la pointeuse. **GymFlow ne s'en sert pas**, inutile d'y toucher.

---

## 2. Choisir le montage

| | **A. Câble jaune direct** (recommandé) | **B. Wi-Fi** |
|---|---|---|
| Matériel | Câble jaune entre la pointeuse et le PC | Un Wi-Fi auquel le PC est déjà connecté |
| Le PC garde internet ? | Oui, par son Wi-Fi | Oui |
| Fiabilité | Excellente | Moyenne : l'adresse peut changer, certains Wi-Fi empêchent les appareils de se voir |
| Contrainte | Pointeuse et PC à moins de 100 m (longueur du câble) | La pointeuse doit capter le Wi-Fi |

Faites **A** si possible. Passez à **B** seulement si le câble est impossible.
*(Plus tard, avec une box à la salle, voir la partie C.)*

---

## 3A. Montage par CÂBLE (recommandé)

### 3A-1. Régler l'adresse de la pointeuse

Sur la pointeuse :

1. Maintenez **OK/MENU** jusqu'à la demande de mot de passe → saisissez le **mot de passe admin** → **OK**.
2. Avec les flèches, allez sur **Comm.** (Communication) → **OK**.
3. Choisissez **Réseau filaire** (ou *Ethernet*, *Wired Network*) → **OK**.
4. Réglez exactement :

| Champ sur l'écran | Valeur à mettre |
|---|---|
| DHCP | **Désactivé** (Non / OFF) |
| Adresse IP | `192.168.50.64` |
| Masque de sous-réseau | `255.255.255.0` |
| Passerelle | `192.168.50.10` |

Puis **validez** (**OK** ou *Enregistrer*) et appuyez plusieurs fois sur **ESC** pour revenir à l'écran d'accueil.

> Les noms des menus varient un peu selon la version de la pointeuse. Cherchez ce qui ressemble à « Comm. », « Réseau » ou « Network ».

### 3A-2. Brancher le câble jaune

- Une extrémité dans le port marqué **LAN**, au dos de la pointeuse.
- L'autre dans la **prise réseau du PC portable** : prise rectangulaire un peu plus large qu'une prise de téléphone, sur le côté du PC, souvent avec le symbole « <···> ».

### 3A-3. Régler l'adresse de la prise réseau du PC (Windows 11)

1. Appuyez sur **Windows + I** (touche Windows et touche I en même temps) : les **Paramètres** s'ouvrent.
2. Colonne de gauche : **Réseau et Internet**.
3. Cliquez sur **Ethernet**. Le câble jaune doit être branché, sinon Ethernet n'apparaît pas.
4. Ligne **Attribution d'adresse IP** → bouton **Modifier**.
5. Dans la liste déroulante, choisissez **Manuel**.
6. Activez l'interrupteur **IPv4**.
7. Remplissez :
   - **Adresse IP** : `192.168.50.10`
   - **Masque de sous-réseau** : `255.255.255.0`
   - **Passerelle** : *laisser vide*
   - **DNS préféré** : *laisser vide*
8. **Enregistrer**.

**Windows 10 :** Windows + I → **Réseau et Internet** → **Ethernet** (colonne de gauche) → **Modifier les options d'adaptateur** → clic droit sur **Ethernet** → **Propriétés** → double-clic sur **Protocole Internet version 4 (TCP/IPv4)** → **Utiliser l'adresse IP suivante** → mêmes valeurs → **OK**.

Votre **Wi-Fi n'est pas modifié** : le PC garde internet par le Wi-Fi.

### 3A-4. Vérifier que le PC voit la pointeuse

1. Touche **Windows** → tapez `cmd` → **Entrée** : une fenêtre noire s'ouvre.
2. Tapez exactement (puis Entrée) :
   ```
   ping 192.168.50.64
   ```
3. Lisez le résultat :
   - « **Réponse de 192.168.50.64** … » → la liaison fonctionne, passez à l'étape 4.
   - « **Délai d'attente de la demande dépassé** » ou « **Impossible de joindre l'hôte** » → vérifiez le câble aux deux bouts, la pointeuse allumée, et refaites 3A-1 et 3A-3 en comparant chaque chiffre.

---

## 3B. Montage en WI-FI

> La pointeuse et le PC doivent être sur **le même Wi-Fi**.

### 3B-1. Connaître le réseau Wi-Fi du PC

1. Touche **Windows** → `cmd` → **Entrée**.
2. Tapez `ipconfig` puis **Entrée**.
3. Repérez le bloc **« Carte réseau sans fil Wi-Fi »** et notez :
   - **Adresse IPv4** : par exemple `192.168.100.39`. Le début (`192.168.100.`) est celui de votre Wi-Fi.
   - **Passerelle par défaut** : par exemple `192.168.100.1`.

### 3B-2. Connecter la pointeuse au Wi-Fi

Sur la pointeuse :

1. **OK/MENU** → mot de passe admin → **Comm.** → **Wi-Fi**.
2. Activez le Wi-Fi, choisissez **le même réseau que le PC**, saisissez son **mot de passe Wi-Fi** → **OK**.
3. Une fois connectée, la pointeuse affiche son **adresse IP** dans ce même menu (ex. `192.168.100.57`). **Notez-la.**

### 3B-3. Fixer l'adresse (conseillé)

Pour que l'adresse ne change pas d'un jour à l'autre :

1. Choisissez une adresse libre avec le même début que le PC, et une fin élevée : par exemple `192.168.100.200`.
2. Vérifiez qu'elle est libre : dans la fenêtre noire, `ping 192.168.100.200` doit répondre **« Délai d'attente dépassé »** (personne ne l'utilise).
3. Sur la pointeuse, Wi-Fi → désactivez le **DHCP** et mettez : IP `192.168.100.200`, masque `255.255.255.0`, passerelle = la **passerelle par défaut** notée en 3B-1.

### 3B-4. Vérifier

`ping 192.168.100.200` (ou l'adresse notée) doit afficher « Réponse de… ».
Si le ping échoue alors que tout est bien réglé, ce Wi-Fi isole les appareils les uns des autres (fréquent sur les Wi-Fi publics ou « invités ») : utilisez le **montage par câble**.

---

## 3C. Plus tard : avec une box à la salle

1. Câble jaune : pointeuse (port **LAN**) → un **port jaune** de la box. Le PC est connecté à cette box (Wi-Fi ou câble).
2. Sur le PC, `ipconfig` donne le début des adresses de la box (souvent `192.168.1.`) et la passerelle (souvent `192.168.1.1`).
3. Sur la pointeuse, Réseau filaire : DHCP **désactivé**, IP `192.168.1.200` (vérifiée libre avec ping), masque `255.255.255.0`, passerelle = celle de la box.
4. Dans GymFlow, mettez cette adresse (étape 4).

La box n'a pas besoin d'internet : elle sert seulement à relier les appareils.

---

## 4. Relier la pointeuse dans GymFlow

1. Double-cliquez sur **`demarrer-gymflow.bat`** (dossier de GymFlow, à côté du fichier `artisan`). GymFlow s'ouvre dans le navigateur.
2. Connectez-vous avec le compte **administrateur**.
3. Barre latérale → **Administration** → **Pointeuses**.
4. Formulaire de droite :
   - **Nom** : `Entrée principale`
   - **Adresse IP** : `192.168.50.64` (câble) ou l'adresse notée en 3B (Wi-Fi)
   - **Port** : `80`
   - **Identifiant** : `admin`
   - **Mot de passe** : celui de l'étape 1
5. **Ajouter**, puis **Tester** → le message **« Liaison réussie… DS-K1T808MFWX »** doit s'afficher.
6. Cliquez sur **Envoyer les membres**.

En cas d'échec, lisez le message :

| Message | Cause | Solution |
|---|---|---|
| Pointeuse injoignable | Câble, adresse ou pointeuse éteinte | Refaire le `ping` de l'étape 3 |
| Identifiant ou mot de passe refusé | Mauvais mot de passe | Pointeuses → **Modifier** → ressaisir |
| Adresse IP refusée | Adresse qui n'est pas celle d'un réseau local | Utiliser une adresse en `192.168.…` |

---

## 5. Enregistrer les doigts des membres

### Quand ?

**Après avoir créé la fiche dans GymFlow**, au comptoir, avec le client présent.

1. GymFlow → **Clients** → **Nouveau client** → remplissez → bouton **« N° … »** pour attribuer un numéro libre (ex. 13) → **Enregistrer**.
2. Quelques secondes plus tard, le **n° 13 apparaît sur la pointeuse avec le nom** (envoyé par GymFlow).
3. Sur la pointeuse : restez appuyé sur **OK/MENU** → identifiez-vous (doigt d'un administrateur ou mot de passe) → **User** → **Person List** → choisissez le **n° 13** → **Fingerprint** → **+**. N'utilisez jamais **Add Person** pour un client : c'est GymFlow qui crée les membres.
4. Le client pose son doigt **3 fois** de suite, comme demandé à l'écran (retirer puis reposer à chaque fois).
5. Recommencez avec un **2ᵉ doigt de secours**, puis validez.

### Quels doigts, dans quel ordre ?

1. **Index droit** (ou index de la main la plus utilisée) : c'est le doigt principal.
2. **Index gauche** ou **majeur droit** : le doigt de secours (doigt blessé, pansement…).

Conseils : doigt **propre et sec**, posé **à plat** au centre du capteur (la partie charnue, pas le bout). Si la lecture échoue, essuyez le doigt et recommencez. Pour les mains très sèches, soufflez légèrement sur le doigt.

### Peut-on créer plusieurs clients d'abord, puis enregistrer les doigts plus tard ?

**Oui.** Créez autant de fiches que vous voulez dans GymFlow : chaque client reçoit son numéro, envoyé à la pointeuse. Chaque personne vient ensuite, quand elle veut, enregistrer son doigt sur **son** numéro. En attendant, la caissière peut faire entrer le membre en tapant son numéro sur l'**Écran d'accueil** (secours).

### Le personnel (ouvrir le menu avec son doigt)

Pour que l'admin ou la caissière ouvre le menu de la pointeuse avec son doigt, sans connaître le mot de passe :

1. **OK/MENU** (appui long) → mot de passe → **User** → **Add Person** (c'est le seul cas où on l'utilise).
2. **Employee ID** : **900000001** pour la 1ʳᵉ personne, 900000002 pour la 2ᵉ… Ces numéros sont réservés au personnel : GymFlow les ignore (ce ne sont pas des passages) et ne les donne jamais à un client.
3. **Name** : le prénom de la personne.
4. **Department** : laissez **Company**. Ce n'est qu'un service (étiquette), cela ne donne **aucun droit**.
5. **User Role** (ou *Authority* / *Permission*, selon la version) : **Administrator**. C'est ce réglage qui ouvre le menu.
6. **Fingerprint** → **+** → posez le doigt 3 fois → enregistrez.

Ensuite : appui long sur **OK/MENU**, puis posez ce doigt : le menu s'ouvre. Si le menu ne s'ouvre pas, le rôle est resté sur *Normal User* : ouvrez la personne dans **Person List** et passez-la en **Administrator**.

---

## 6. Au quotidien

- **Le matin** : allumer le PC. Si le démarrage automatique est activé (voir DEPLOIEMENT.md), GymFlow se lance tout seul. Sinon, double-clic sur `demarrer-gymflow.bat`.
- **Pendant la journée** : ne fermez pas les deux fenêtres réduites « GymFlow - Application » et « GymFlow - Pointeuse ». Si l'une s'arrête, elle redémarre seule en 5 secondes.
- **PC portable** : branché sur secteur, et mise en veille désactivée (Windows + I → **Système** → **Alimentation** → *Mettre en veille* : **Jamais** quand il est branché).

### Ce que fait la pointeuse

| Situation | Écran de la pointeuse | Écran d'accueil GymFlow |
|---|---|---|
| Abonnement en cours, 1ᵉʳ badge du jour | « Authenticated » + nom | **Vert** « Bienvenue ! », jours restants |
| 2ᵉ badge du jour (sortie) | « Authenticated » + nom | **Bleu** « À bientôt ! » |
| 3ᵉ badge et suivants | « Authenticated » + nom | **Orange** « Déjà enregistré » |
| Abonnement terminé | Refus | **Rouge** « Abonnement expiré » |
| Journalier qui a payé aujourd'hui | Réussie | **Vert** |
| Doigt non enregistré | Refus | **Rouge** « Empreinte non reconnue » |

L'écran de la pointeuse affiche son **propre logiciel Hikvision**, pas GymFlow : il montre toujours le nom et « Authenticated », sans message personnalisé (la pointeuse décide seule, avant que GymFlow ne reçoive le passage). GymFlow s'affiche sur le PC : menu **Écran d'accueil**, à mettre en plein écran sur un 2ᵉ écran tourné vers les clients si vous en avez un.

---

## 7. Dépannage rapide

| Problème | Vérifier |
|---|---|
| Pointeuses : « Erreur », « injoignable » | Câble branché ? Pointeuse allumée ? `ping` OK ? Fenêtre « GymFlow - Pointeuse » ouverte ? |
| Le nom n'apparaît pas sur la pointeuse | Pointeuses → colonne **Envois** : « en attente » ? Alors la fenêtre « GymFlow - Pointeuse » est fermée : relancer `demarrer-gymflow.bat` |
| Plus rien n'arrive en fin de journée | Le PC s'est mis en veille |
| En Wi-Fi, ça marchait hier mais plus aujourd'hui | L'adresse de la pointeuse a changé : la relire dans son menu Wi-Fi et la corriger dans GymFlow (**Modifier**) |
| « Empreinte non reconnue » pour un membre inscrit | Le doigt n'a pas été enregistré sur **son** numéro : refaire l'étape 5 |

---

## 8. Sécurité

- Le **mot de passe admin** de la pointeuse ne doit jamais être donné aux membres : il ouvre le menu.
- Dans GymFlow, ce mot de passe est **chiffré** et n'est jamais réaffiché.
- GymFlow ne contacte que des adresses du **réseau local** (192.168.x.x, 10.x.x.x, 172.16-31.x.x), c'est-à-dire celles de la salle : c'est le cas dans tous les montages ci-dessus.
- Les **empreintes restent dans la pointeuse**. GymFlow ne reçoit que le numéro du membre et l'heure de passage.
- Obligations légales : **autorisation préalable de l'ARTCI** pour la collecte d'empreintes (loi n° 2013-450) et **consentement écrit** de chaque membre.
