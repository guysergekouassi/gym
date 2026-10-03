# Installer et mettre en marche la pointeuse (Hikvision DS-K1T808MFWX)

![Schéma de montage](installation/schema-montage.png)

Guide pas à pas, **sans connaissances réseau**. Comptez une heure la première fois.

## Ce qu'il faut savoir avant de commencer

- **Internet n'est pas nécessaire.** Ni GymFlow ni la pointeuse n'en ont besoin : il faut seulement que le PC et la pointeuse se « voient ».
- **Le PC portable peut servir de PC de caisse.** Il doit rester allumé et branché sur secteur pendant les heures d'ouverture.
- **Le câble jaune est un câble réseau (RJ45).** C'est lui qui relie la pointeuse au PC : gardez-le.
- **L'écran de la pointeuse affiche son propre logiciel Hikvision**, pas GymFlow : l'heure, puis « Vérification réussie » avec le nom, ou un refus. GymFlow s'affiche sur le PC (menu *Écran d'accueil*, vert ou rouge).
- **Les touches de la pointeuse servent seulement au responsable** : activer l'appareil, régler le réseau, enregistrer les doigts. Les membres posent seulement leur doigt (ou passent une carte, si vous en distribuez).
- **La pointeuse bloque d'elle-même les abonnements terminés.** À chaque paiement, GymFlow lui envoie la date de fin.

## Quel montage choisir ?

| Montage | Pour qui | Fiabilité |
|---|---|---|
| **A. Câble jaune direct PC ↔ pointeuse** (recommandé) | Vous, aujourd'hui : pas besoin de box, le Wi-Fi du PC continue à donner internet | ★★★ |
| B. PC et pointeuse branchés sur la même box | Quand vous aurez une box à la salle (même sans internet) | ★★★ |
| C. Pointeuse en Wi-Fi sur le même réseau que le PC | Seulement si vous gérez ce Wi-Fi | ★ (l'adresse peut changer, certains Wi-Fi isolent les appareils) |

Vos photos : le **câble jaune dans le port LAN** de la pointeuse est correct. Branché dans un **port jaune de la box**, c'est aussi correct (montage B). Pour l'alimentation, regardez l'**étiquette du chargeur** : elle doit indiquer **12 V ⎓ 1 A** (ou plus d'ampères). **Si l'écran de la pointeuse s'allume, l'alimentation est bonne.** Ne branchez jamais un chargeur de 5 V ou 24 V.

---

## Montage A — pas à pas

### 1. Activer la pointeuse (une seule fois)

1. Branchez le chargeur : l'écran s'allume.
2. À la première mise en route, la pointeuse demande de **l'activer** en créant un **mot de passe admin**. Prenez 8 à 16 caractères avec des lettres et des chiffres, par exemple `Gym2026Entree`.
3. **Notez ce mot de passe** : il sert à entrer dans le menu de la pointeuse et dans GymFlow.

> Si la pointeuse est déjà activée et que le mot de passe est perdu, il faut la réinitialiser (voir le manuel Hikvision ou le revendeur).

### 2. Donner une adresse fixe à la pointeuse

Sur la pointeuse : maintenez **OK / MENU** → saisissez le mot de passe admin → **Comm.** (Communication) → **Réseau filaire** :

| Champ | Valeur |
|---|---|
| DHCP | **Non** (désactivé) |
| Adresse IP | `192.168.50.64` |
| Masque de sous-réseau | `255.255.255.0` |
| Passerelle | `192.168.50.10` |

Validez, puis revenez à l'écran d'accueil (**ESC**).

> Les noms des menus peuvent varier un peu selon la version (Communication / Network / Wired Network).
> Autre solution depuis le PC : l'outil gratuit **SADP** de Hikvision (à télécharger sur hikvision.com) trouve la pointeuse sur le câble, permet de l'activer et de changer son adresse IP.

### 3. Brancher le câble jaune

Câble jaune : **port LAN de la pointeuse** → **prise réseau (Ethernet) du PC portable**.

### 4. Donner une adresse fixe à la prise réseau du PC (Windows 10 / 11)

1. **Paramètres** (touche Windows + I) → **Réseau et Internet** → **Ethernet**.
2. **Attribution d'adresse IP** → **Modifier** → choisissez **Manuel** → activez **IPv4**.
3. Remplissez :
   - Adresse IP : `192.168.50.10`
   - Masque de sous-réseau : `255.255.255.0` (ou « longueur du préfixe » : `24`)
   - Passerelle : **laisser vide**
   - DNS : **laisser vide**
4. **Enregistrer**.

Le Wi-Fi du PC n'est pas touché : vous gardez internet.

**Vérifier :** touche Windows → tapez `cmd` → Entrée → tapez :

```
ping 192.168.50.64
```

Si des lignes « Réponse de 192.168.50.64 » apparaissent, le PC voit la pointeuse. Si vous lisez « Délai d'attente dépassé », vérifiez le câble et les adresses.

### 5. Démarrer GymFlow

Double-cliquez sur **`demarrer-gymflow.bat`**. Deux fenêtres noires s'ouvrent, à laisser ouvertes toute la journée :

- **GymFlow** : l'application ;
- **GymFlow - Pointeuse** : le programme qui récupère les passages et envoie les membres toutes les 3 secondes.

### 6. Relier la pointeuse dans GymFlow

1. Connectez-vous en **administrateur** → **Administration → Pointeuses**.
2. Renseignez : adresse IP `192.168.50.64`, port `80`, identifiant `admin`, et le **mot de passe de l'étape 1**.
3. Cliquez sur **Tester** : le message « Liaison réussie… DS-K1T808MFWX » doit s'afficher.
4. Cliquez sur **Envoyer les membres**.

### 7. Enregistrer les doigts des membres

**Le doigt s'enregistre après la création de la fiche dans GymFlow**, au comptoir, pendant que le client est là :

1. GymFlow → **Clients → Nouveau client** → bouton **« N° … »** (numéro libre, par exemple 13) → Enregistrer.
2. Quelques secondes plus tard, le membre n° 13 apparaît sur la pointeuse avec son nom.
3. Sur la pointeuse : **OK / MENU** → mot de passe → **Utilisateur** → choisir le n° 13 → **Empreinte** → le membre pose **3 fois** le même doigt. Ajoutez un 2ᵉ doigt de secours.

Ensuite, à chaque passage :

- **abonnement valide** → la pointeuse affiche « Vérification réussie » et GymFlow enregistre l'entrée (vert à l'écran d'accueil) ;
- **abonnement terminé** → la pointeuse refuse, GymFlow affiche le motif en rouge ;
- **journalier** → il passe seulement le jour où il a payé à la caisse.

### 8. Pour que tout marche toute la journée

- PC branché sur secteur, et **mise en veille désactivée** : Paramètres → Système → Alimentation → « Mettre en veille » : **Jamais** (sur secteur). En veille, plus aucun passage n'arrive.
- Le matin, double-clic sur `demarrer-gymflow.bat`.

---

## Montage B — avec une box

1. Câble jaune : pointeuse (LAN) → **port jaune** de la box. Le PC est sur la box (câble ou Wi-Fi de cette box).
2. Sur la pointeuse, réseau filaire : **DHCP Oui** pour connaître l'adresse donnée par la box (affichée dans le menu), puis remettez cette adresse **en fixe** pour qu'elle ne change pas (même masque et même passerelle que la box, souvent `192.168.1.1`).
3. Dans GymFlow → Pointeuses : cette adresse → Tester.

## Montage C — en Wi-Fi

Sur la pointeuse : **Comm. → Wi-Fi** → choisir le réseau → mot de passe. L'adresse obtenue s'affiche dans le menu : renseignez-la dans GymFlow. Si l'adresse change un jour, modifiez-la dans GymFlow (bouton *Modifier*).

---

## En cas de problème

| Ce que vous voyez | Que faire |
|---|---|
| « Pointeuse injoignable » | Câble branché ? Pointeuse allumée ? `ping 192.168.50.64` répond ? Les deux adresses commencent bien par `192.168.50.` ? |
| « Identifiant ou mot de passe refusé » | Ressaisir le mot de passe de l'activation (Pointeuses → Modifier). |
| Le nom n'apparaît pas sur la pointeuse | La fenêtre « GymFlow - Pointeuse » est-elle ouverte ? Sinon relancer `demarrer-gymflow.bat`. |
| Écran rouge « Empreinte non reconnue » | Le doigt n'est pas enregistré, ou le n° de la fiche ne correspond pas à celui de la pointeuse. |
| Plus rien n'arrive en fin de journée | Le PC s'est mis en veille (voir point 8). |

## Sécurité et données personnelles

- GymFlow n'est accessible **que depuis le PC de caisse** (adresse 127.0.0.1) : rien n'est ouvert sur le réseau, il n'y a pas de règle de pare-feu à ajouter.
- Le mot de passe de la pointeuse est **chiffré** dans la base. GymFlow n'accepte que des adresses du réseau local.
- Les **empreintes restent dans la pointeuse** : GymFlow ne reçoit que le numéro du membre et l'heure.
- Protégez le menu de la pointeuse par son mot de passe admin, et ne le donnez pas aux membres.
- La collecte d'empreintes demande une **autorisation préalable de l'ARTCI** (loi n° 2013-450) et le **consentement écrit** de chaque membre.
