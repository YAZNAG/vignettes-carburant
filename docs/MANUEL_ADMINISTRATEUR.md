# Manuel de l'administrateur

Gestion des vignettes carburant — rôle **Administrateur**.

## 1. Première connexion

1. Ouvrez l'application et connectez-vous avec l'identifiant et le mot de passe
   provisoire communiqués.
2. L'application impose immédiatement la définition d'un **nouveau mot de passe**
   (10 caractères minimum, une majuscule, une minuscule, un chiffre ; les mots de
   passe trop courants sont refusés).
3. L'application impose ensuite l'**enrôlement 2FA** : scannez le QR code avec
   Google Authenticator ou Microsoft Authenticator, saisissez le code à 6 chiffres,
   puis **conservez précieusement les 8 codes de secours** affichés une seule fois.

## 2. Gestion des utilisateurs (menu Utilisateurs)

- **Créer un compte** : renseignez nom, prénom, e-mail, identifiant, rôle et un
  mot de passe provisoire. L'utilisateur devra le changer à sa première connexion.
- **Rôles disponibles** :
  - *Administrateur* — accès total (2FA obligatoire) ;
  - *Gestionnaire de parc* — crée et modifie les référentiels, sans désactivation ;
  - *Valideur* — lecture + validation des opérations (lot 2) ;
  - *Consultation* — lecture seule, y compris le journal d'audit.
- **Désactiver un compte** : un compte ne se supprime jamais ; désactivé, il ne
  peut plus se connecter mais reste visible dans l'historique. Le système refuse
  de désactiver le **dernier administrateur actif**, votre propre compte, et vous
  ne pouvez pas modifier votre propre rôle.
- **Réinitialiser un mot de passe** : définissez un mot de passe provisoire ;
  le changement sera forcé à la connexion suivante et toutes les sessions du
  compte sont fermées.
- **Déverrouiller un compte** : après 5 échecs de connexion, un compte est
  verrouillé 15 minutes ; le bouton « Déverrouiller » lève le blocage immédiatement.
- **Dernières connexions** : chaque fiche affiche les tentatives récentes
  (réussies ou non) avec adresse IP.

## 3. Référentiels

Tous les écrans fonctionnent de la même façon : recherche (insensible aux accents
et à la casse), filtres, tri, pagination, export Excel.

- **Création** : les saisies sont normalisées automatiquement (majuscules pour
  les immatriculations, capitalisation des noms, espaces superflus supprimés).
  Si une valeur **très proche** existe déjà (ex. M214134 alors que M2214134
  existe), l'application demande une confirmation explicite avant de créer.
- **Désactivation** : un élément utilisé ne peut jamais être supprimé, seulement
  désactivé ; il disparaît des listes de saisie mais reste en consultation.
  Un élément jamais utilisé peut, lui, être supprimé.
- **Types de vignette** : impossible de désactiver un type tant qu'une de ses
  coupures est active.
- **Exercices** : un seul exercice ouvert à la fois ; la clôture avec report du
  disponible arrive au lot 2.

## 4. Import initial (menu Import)

1. Choisissez le type (Véhicules ou Bénéficiaires) et téléchargez le **modèle**.
2. Remplissez-le puis chargez le fichier (`.xlsx` ou `.csv`, 5 Mo max).
3. La **prévisualisation** signale ligne par ligne les erreurs (en rouge :
   doublons, champs manquants, valeurs inconnues) et les avertissements (en
   orange : quasi-doublons).
4. L'import ne s'exécute que si **aucune ligne n'est en erreur** : soit tout
   passe, soit rien n'est inséré.

## 5. Paramètres (menu Paramètres)

- Nom de l'organisme et logo (utilisé dans les futurs états PDF) ;
- **Durée d'inactivité** avant déconnexion (5 à 480 minutes, 30 par défaut) —
  un avertissement s'affiche 2 minutes avant l'expiration ;
- Seuil d'alerte de stock bas et format des numéros de pièce (exploités au lot 2) ;
- **2FA obligatoire par rôle** (toujours active pour Administrateur).

## 6. Journal d'audit (menu Journal d'audit)

Chaque connexion, échec, création, modification, désactivation et accès refusé
est enregistré avec l'utilisateur, la date, l'adresse IP et les **valeurs avant
et après**. Le journal est en ajout seul : personne, pas même un administrateur,
ne peut le modifier ou le purger. Filtres par période, utilisateur, action et
entité ; export Excel.

## 7. Bonnes pratiques de sécurité

- Créez un compte **nominatif** par utilisateur ; ne partagez jamais un compte.
- Vérifiez régulièrement le journal d'audit (actions `acces_refuse`,
  `verrouillage_compte`).
- Gardez au moins deux comptes administrateurs actifs.
- En cas de compromission suspectée d'un compte : réinitialisez son mot de passe
  (les sessions sont fermées automatiquement) puis consultez ses dernières
  connexions.
