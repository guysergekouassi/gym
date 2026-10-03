# Installer et mettre en marche GymFlow à la salle

![Schéma de montage](installation/schema-montage.png)

Ce guide est fait pour être suivi **sans connaissances en programmation**. Comptez environ une heure la première fois.

## Ce qu'il vous faut

| Matériel | Rôle |
|---|---|
| Pointeuse à empreinte + son chargeur 12 V | Reconnaît le doigt du membre à l'entrée |
| Câble réseau RJ45 | Relie la pointeuse à la box internet (ou au switch) |
| PC de caisse (Windows) | Fait tourner GymFlow |
| Imprimante thermique (à venir) | Imprime les tickets |
| Écran supplémentaire (facultatif) | Écran d'accueil tourné vers les clients |

La pointeuse et le PC doivent être sur **la même box / le même réseau**.

---

## Étape 1 — Brancher la pointeuse

1. **Alimentation** : branchez le chargeur 12 V. Sur le faisceau de fils, **rouge = + 12 V**, **noir = masse**. Si le chargeur a une prise ronde, elle se branche directement sur l'entrée « DC 12V » de la pointeuse.
2. **Réseau** : branchez le câble RJ45 entre la pointeuse et la box.
3. Les autres fils (serrure, bouton de sortie, sonnette, Wiegand) **ne servent pas à GymFlow**. Laissez-les isolés (ruban adhésif au bout). Une serrure électrique ou un tourniquet s'installe plus tard, par un électricien, avec sa propre alimentation.

> ⚠️ Ne branchez jamais la pointeuse directement sur le 220 V : uniquement via le chargeur 12 V.

## Étape 2 — Préparer le PC de caisse

1. **IP fixe** : dans la box (« bail DHCP statique ») ou dans Windows (Paramètres → Réseau → Ethernet → Modifier l'IP), donnez au PC une adresse fixe, par exemple `192.168.1.10`. Pour connaître la plage de votre box : ouvrir l'invite de commandes (touche Windows, taper `cmd`), puis taper `ipconfig`.
2. **Pare-feu** : clic droit sur `ouvrir-pare-feu.bat` → *Exécuter en tant qu'administrateur*. La règle n'autorise que les appareils du réseau local de la salle.
3. **Démarrer GymFlow** : double-clic sur `demarrer-gymflow.bat`. Une fenêtre noire reste ouverte toute la journée (ne la fermez pas), le navigateur s'ouvre sur GymFlow.

## Étape 3 — Déclarer la pointeuse dans GymFlow

1. Connectez-vous avec le compte **administrateur**.
2. Menu **Administration → Pointeuses**.
3. Saisissez un nom (« Entrée principale ») et le **n° de série** de la pointeuse : il figure sur l'étiquette au dos, ou dans le menu de la pointeuse *Infos système → Infos appareil*.

## Étape 4 — Régler la pointeuse

Sur la pointeuse, appuyez sur **M/OK** (menu), puis :

1. **Comm. → Ethernet** :
   - Adresse IP : `192.168.1.201` (une adresse libre du même réseau)
   - Masque : `255.255.255.0`
   - Passerelle : l'adresse de la box (souvent `192.168.1.1`)
   - DHCP : **Non**
2. **Comm. → Paramètre serveur Cloud** (parfois appelé **ADMS**) :
   - Adresse du serveur : l'IP du PC, ex. `192.168.1.10`
   - Port : `8005`
   - Nom de domaine : **Non** · Proxy : **Non**
3. Redémarrez la pointeuse.

Dans GymFlow (Administration → Pointeuses), l'état passe à **« En ligne »** en moins d'une minute. Sinon, voir « En cas de problème ».

> Les intitulés exacts des menus varient un peu selon le modèle et la langue de la pointeuse. Si vous ne trouvez pas « Serveur Cloud / ADMS », envoyez-nous une photo de l'étiquette au dos (modèle + n° de série).

## Étape 5 — Enregistrer les membres

Pour chaque membre :

1. Dans GymFlow, **Clients → Nouveau client**. Cliquez sur le bouton **« N° … »** : GymFlow attribue un numéro libre (ex. 13).
2. GymFlow envoie automatiquement le nom et le numéro à la pointeuse (quelques secondes).
3. Sur la pointeuse : **M/OK → Utilisateurs → Gérer** → choisissez le n° 13 → **Empreinte** → le membre pose **3 fois le même doigt**. Faites un 2ᵉ doigt en secours.

Pour les membres déjà inscrits : **Administration → Pointeuses → « Envoyer tous les membres »**.

À partir de là, quand un membre pose son doigt :

- la pointeuse envoie le passage à GymFlow ;
- GymFlow vérifie l'abonnement (ou le paiement du jour pour un journalier) ;
- l'**écran d'accueil** affiche le résultat : **vert** avec le nom et les jours restants, ou **rouge** avec le motif (abonnement expiré, empreinte inconnue…).

> Important : la pointeuse affiche « Vérifié » dès qu'elle reconnaît le doigt, même si l'abonnement est expiré. **C'est l'écran d'accueil de GymFlow (vert / rouge) qui fait foi.** Bloquer automatiquement une porte ou un tourniquet demande un réglage en plus, à faire quand une serrure sera installée.

## Étape 6 — Écran d'accueil

Sur le PC de caisse, menu **Écran d'accueil** : la page s'ouvre dans un nouvel onglet. Faites-la glisser sur le 2ᵉ écran, puis cliquez sur **Plein écran**.

**Secours** si la pointeuse est en panne : sur l'écran d'accueil, tapez le n° du membre au clavier puis **Entrée**.

## Étape 7 — Imprimante ticket (à son arrivée)

1. Branchez-la en USB et installez le pilote fourni.
2. Windows → Paramètres → Imprimantes : définissez-la **par défaut**, format de papier 80 mm (ou 58 mm).
3. Pour imprimer sans fenêtre de confirmation : créez un raccourci Chrome avec l'option `--kiosk-printing` et ouvrez GymFlow avec ce raccourci.
4. Rouleau de 58 mm : mettez `RECU_LARGEUR=58` dans le fichier `.env`.

## En cas de problème

| Symptôme | Solution |
|---|---|
| Pointeuse « Hors ligne » | Vérifier le câble ; la pointeuse et le PC sur la même box ; l'IP et le port dans *Serveur Cloud* ; `demarrer-gymflow.bat` ouvert ; pare-feu (étape 2.2). |
| Elle était en ligne, plus maintenant après un changement de box | L'adresse IP de la pointeuse a changé : Pointeuses → **Nouvelle IP**. |
| Le nom n'apparaît pas sur la pointeuse | Attendre 30 s, sinon Pointeuses → « Envoyer tous les membres ». |
| Écran rouge « Empreinte non reconnue » | Le n° de la pointeuse ne correspond à aucune fiche : renseigner le même n° dans la fiche client. |
| Le ticket ne s'imprime pas | L'imprimante est-elle « par défaut » ? Le ticket reste réimprimable depuis la caisse (lien du n° de ticket). |

## Sécurité et données personnelles

- GymFlow ne reçoit **que le numéro** du membre et l'heure : les empreintes restent dans la pointeuse et ne sont jamais copiées dans l'application.
- La pointeuse n'est acceptée que si son n° de série est déclaré **et** qu'elle se connecte depuis la même adresse IP qu'au premier contact.
- GymFlow doit rester sur le réseau local de la salle : **ne jamais ouvrir le port 8005 sur la box vers internet**.
- Collecte d'empreintes : **autorisation préalable de l'ARTCI** obligatoire (loi n° 2013-450), et consentement écrit de chaque membre (un formulaire signé à l'inscription).
