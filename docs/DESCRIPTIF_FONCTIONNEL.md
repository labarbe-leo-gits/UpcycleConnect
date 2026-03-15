# Descriptif fonctionnel - Mission 1 (UpcycleConnect)

## 1. Objectif

Ce document décrit les exigences fonctionnelles de la mission 1 (gestion des solutions applicatives) pour l’application UpcycleConnect.

Ce document se concentre uniquement sur la mission 1 et n’aborde pas les éléments d’infrastructure (mission 2) ou les modules supplémentaires (mission 3).

---

## 2. Acteurs et périmètre fonctionnel

### 2.1 Particuliers

Les particuliers utilisent l’espace « Site principal » pour :

- Consulter et mettre à jour leur profil (contact, adresse, photo, préférences).
- S’inscrire / se connecter (email+mot de passe, OAuth Google/Microsoft).
- Consulter leur tableau de bord personnel (solde, score Upcycling, notifications).
- Déposer une annonce (don ou vente) :
  - Définir titre, description, catégorie, photos, état, prix (ou don).
  - Envoi pour validation par l’équipe administrative (statut d’annonce).
- Demander un dépôt dans un conteneur :
  - Soumettre un objet (description, catégorie, photos, poids estimé).
  - Recevoir un code d’ouverture généré par le système.
  - Obtenir un code-barres / QR code pour la récupération par un artisan/professionnel.
- Consulter et réserver des prestations (formations, ateliers, services) :
  - Parcourir le catalogue de services/formations/événements.
  - Payer via Stripe.
  - Consulter l’historique des commandes.
- Gérer leur planning personnel :
  - Visualiser les sessions réservées (formations, ateliers, rendez-vous).
  - Consulter les horaires et lieux.
  - Ajouter leurs propre évènements
- Accéder aux conseils (blogs, tutoriels, documentation).
- Voir leur Upcycling Score (calcule l’impact écologique de leurs actions).
- Recevoir des notifications (email, interface web, éventuellement push) liées aux annonces, commandes ... .
- Découvrir un tutoriel d’utilisation :
  - Affiché à la première connexion.
  - Utilise des overlays progressifs pour expliquer l’interface.

### 2.2 Professionnels / artisans

Les professionnels/artisans utilisent le même espace principal, avec des fonctionnalités étendues :

- Gestion des comptes professionnels (profil, coordonnées).
- Gestion des abonnements (contrats, facturation, accès premium) :
  - Visualiser les plans et options.
  - Gérer les renouvellements et la facturation via Stripe (abonnements, factures, paiements).
- Accès aux annonces (dons/ventes) :
  - Rechercher et filtrer les annonces publiées par les particuliers.
- Récupération des objets déposés dans les conteneurs :
  - Scanner ou entrer le code-barres / QR code fourni au particulier.
  - Confirmer le retrait et mettre à jour le statut du dépôt.
- Gestion de projets Upcycling :
  - Créer et suivre des projets (description, étapes, matériaux utilisés).
  - Mettre en avant des projets (mise en avant / promotion).

### 2.3 Salariés (animateurs / formateurs)

Les salariés disposent d’un espace dédié (dans le site principal) pour :

- Créer et animer des formations, ateliers et événements :
  - Proposer des sessions, définir dates, capacités, tarifs.
  - Soumettre une proposition qui peut être validée par un responsable.
- Gérer leur planning :
  - Voir les sessions planifiées.
  - Consulter la liste des inscrits.
- Gérer les contenus de conseils :
  - Rédiger, modifier et publier des articles.
- Modérer les forums et commentaires (signalements, suppression, blocage).

### 2.4 Administration générale (Back Office)

L’interface Back Office (PA - BO) est utilisée par les administrateurs pour :

- Gérer les utilisateurs (création, modification, suppression, rôle, ...).
- Mettre hors lignes des annonces.
- Traiter les demandes de dépôt en conteneurs (générer et révoquer les codes d’accès).
- Suivre l’activité financière :
  - Consultations des revenus.
  - Statistiques sur la durée (Graphes)
- Gérer le catalogue de services/formations/événements.
- Modération générale de la plateforme

---

## 3. Fonctionnalités transverses

### 3.1 Authentification et sécurité

- Authentification par email/mot de passe.
- Authentification OAuth (Google / Microsoft).
- Gestion des sessions via JWT (API Go) et sessions PHP (front web).
- Mot de passe stocké haché (bcrypt).
- Restrictions de validation côté API (contrôle des formats et des accès selon rôle).

### 3.2 Gestion des rôles et des accès

- Types d’utilisateurs :
  - 1 = Particulier
  - 2 = Professionnel / Artisans
  - 3 = Administrateur / Back office
  - 4 = Salarié (part-time)
- Contrôle d’accès basé sur les rôles (middleware JWT + logique métier).
- Routes API protégées pour les opérations sensibles (gestion utilisateurs, validation de contenu, accès financier).

### 3.3 Multilingue

- Le site doit être multilingue sans nécessiter de modification du code pour ajouter une nouvelle langue.
- Prévoir des fichiers de traduction / ressources par langue (ex : JSON / PHP arrays) pour l’interface.
- L’utilisateur peut choisir sa langue via une option de profil.

### 3.4 Paiements et facturation

- Intégration Stripe pour :
  - Paiement de formations / services.
  - Abonnements (Stripe Subscriptions).

### 3.5 Notifications

- Envoi de notifications vers les utilisateurs (email, push) pour :
  - Mise hors ligne d’annonces.
  - Confirmation de paiement.
  - Rappels de sessions / formations.
- Architecture modulable pour intégrer un service de notification (ex : OneSignal) sans impacter les autres modules.

### 3.6 Conteneurs et dépôts d’objets

- Gestion des conteneurs / box :
  - Enregistrement des conteneurs.
  - Génération de codes d’ouverture (alphanumériques, durée limitée).
  - Association des codes à un dépôt d’objet.
  - Génération de codes-barres/QR codes pour récupération.
  - Suivi du statut de chaque dépôt (en attente, accepté, refusé, déposé, récupéré).

### 3.7 Catalogue et services

- Gestion du catalogue de services/formations/événements.
- Interface de consultation pour les utilisateurs.
- Processus de réservation + paiement.

### 3.8 Planning

- Planning centralisé pour les sessions, les formations et les rendez-vous.
- Réservation (particulier) et gestion (salarié).
- Vue calendrier et liste.

### 3.9 Tutoriel d’utilisation

- Tutoriel en overlay affiché à la première connexion.
- Doit pouvoir être réinitialisé (option « voir le tutoriel à nouveau »).
- Doit bloquer l’interface tant que l’utilisateur ne le ferme pas.

---

## 4. Données et API (implémentation existante)

### 4.1 API Go (PA - API)

L’API Go expose les ressources principales via des routes :

- `/users` : gestion des comptes (inscription, connexion, mise à jour, liste, bannissement).
- `/annonces` : dépôt et consultation d’annonces.
- `/conteneurs` : demandes de dépôt, génération de codes, vérification.
- `/orders` et `/paymentRequests` : gestion des commandes et paiements / virements.
- `/subscriptions` : gestion des abonnements professionnels.
- `/planning` : gestion des sessions et réservations.
- `/notifications` : création et envoi de notifications.
- `/projects` : gestion des projets Upcycling.
- `/forum` : gestion des forums et contenus collaboratifs.
- ...

### 4.2 Données principales

- Identifiants : UUID (standardisé).
- Authentification : JWT (token signé avec secret stocké en environnement).
- Relationnel : MySQL avec schéma central (table `users`, `annonces`, `orders`, `services`, etc.).

---

## 5. Évolutivité et extensions possibles

Le descriptif fonctionnel est conçu pour être évolutif :

- Ajout de nouvelles langues sans modification du code (chargement dynamique de fichiers de traduction).
- Ajout de nouveaux types d’utilisateurs (par exemple, « partenaire ») simplement en étendant la table `users` et le middleware d’accès.
- Ajout de modules (chat, marketplace avancée, API mobile) via l’API existante.
- Extension du catalogue de services (ajout de catégories, filtres, recherche avancée).

---

## 6. Annexes

Les détails techniques (architecture, base de données, déploiement) sont documentés dans :

- `docs/ARCHITECTURE_TECHNIQUE.md`
- `docs/DATABASE.md`
- `docs/SETUP.md`
- `docs/STRIPE_SETUP.md`
