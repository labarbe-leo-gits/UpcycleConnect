# Document d'Architecture Technique — UpcycleConnect
## Réponse à Appel d'Offre

**Projet** : UpcycleConnect  
**Version** : 1.0  
**Date** : Mars 2026  
**Statut** : Document de référence architecture

---

## Table des matières

1. [Présentation du projet](#1-présentation-du-projet)
2. [Vue d'ensemble de l'architecture](#2-vue-densemble-de-larchitecture)
3. [Technologies retenues et justifications](#3-technologies-retenues-et-justifications)
   - 3.1 [Frontend — PHP natif, JavaScript, CSS](#31-frontend--php-natif-javascript-css)
   - 3.2 [Serveur web — Apache HTTP Server](#32-serveur-web--apache-http-server)
   - 3.3 [API Backend — Go (Golang)](#33-api-backend--go-golang)
   - 3.4 [Base de données — MySQL](#34-base-de-données--mysql)
   - 3.5 [Bibliothèques et dépendances externes — Composer](#35-bibliothèques-et-dépendances-externes--composer)
   - 3.6 [Conteneurisation — Docker](#36-conteneurisation--docker)
   - 3.7 [Application mobile — Kotlin (Android)](#37-application-mobile--kotlin-android)
4. [Architecture des containers Docker](#4-architecture-des-containers-docker)
5. [Sécurité applicative](#5-sécurité-applicative)
6. [Schéma d'architecture global](#6-schéma-darchitecture-global)
7. [Synthèse des choix technologiques](#7-synthèse-des-choix-technologiques)

---

## 1. Présentation du projet

UpcycleConnect est une plateforme **nationale** de mise en relation entre particuliers souhaitant valoriser des matériaux en fin de vie et des prestataires spécialisés dans l'upcycling (surcyclage). La plateforme doit permettre :

- La création et la gestion de comptes utilisateurs (particuliers, professionnels, administrateurs)
- La publication et la consultation d'annonces de matériaux à recycler
- La gestion de conteneurs de dépôt géolocalisés
- La prise en charge et le suivi de commandes de services d'upcycling
- Le traitement de paiements sécurisés entre clients et prestataires
- Un système de notation et de score d'impact environnemental
- L'accès à une interface mobile native pour les utilisateurs en déplacement

Ce document expose les technologies choisies pour répondre à ces besoins, ainsi que la justification des choix opérés.

---

## 2. Vue d'ensemble de l'architecture

L'architecture retenue suit un modèle en couches découplées (*separation of concerns*). Trois composants principaux communiquent entre eux de manière clairement définie :

```
┌───────────────────────────────────────────────────────────────────┐
│                          Utilisateur final                        │
│                   (Navigateur web ou App mobile)                  │
└─────────────────┬─────────────────────────────────┬──────────────┘
                  │ HTTPS                            │ HTTPS/REST
     ┌────────────▼──────────┐          ┌────────────▼──────────────┐
     │   Container Apache    │          │   Application Kotlin      │
     │   + PHP Frontend      │          │   (Android natif)         │
     └────────────┬──────────┘          └────────────┬──────────────┘
                  │ REST/HTTP                         │ REST/HTTP
     ┌────────────▼──────────────────────────────────▼──────────────┐
     │                   Container API Go (port 9999)                │
     └────────────────────────────┬──────────────────────────────────┘
                                  │ SQL
                     ┌────────────▼──────────┐
                     │  Container MySQL 8.0  │
                     │   (Base de données)   │
                     └───────────────────────┘
```

Cette organisation garantit une indépendance entre les couches de présentation, de logique métier et de persistance des données. Chaque composant peut évoluer, être remplacé ou mis à l'échelle indépendamment des autres.

---

## 3. Technologies retenues et justifications

### 3.1 Frontend — PHP natif, JavaScript, CSS

#### Description

Le site principal est développé en **PHP sans framework**, accompagné de **JavaScript vanilla** et de **CSS pur**. Aucun framework front-end (React, Vue, Angular) ni back-end (Laravel, Symfony) n'est utilisé à ce stade.

#### Justifications

**Maîtrise et lisibilité du code**  
Le PHP natif permet une lecture directe et sans couche d'abstraction supplémentaire. Chaque développeur intervenant sur le projet comprend immédiatement ce que fait chaque ligne, sans nécessiter de connaître les conventions d'un framework tiers. Cela réduit les coûts d'onboarding et de maintenance.

**Contrôle total sur les comportements**  
L'absence de framework signifie qu'aucun mécanisme automatique n'agit en fond à l'insu de l'équipe. Les sessions, les redirections, les en-têtes HTTP, les requêtes sortantes vers l'API — tout est explicitement écrit et auditable. Pour une plateforme impliquant des paiements et des données personnelles, cette transparence est un avantage de sécurité.

**Légèreté et performance**  
Un projet PHP natif ne charge que le strict nécessaire à chaque requête. Il n'y a pas de conteneur IoC, pas de système d'événements complexe, pas de résolution de dépendances à l'exécution. Sur un hébergement mutualisé ou un serveur aux ressources modestes, cela se traduit par des temps de réponse plus courts.

**Courbe d'apprentissage nulle pour l'équipe**  
L'équipe projet maîtrise PHP, JavaScript et CSS. Introduire un framework — et ses dizaines de concepts spécifiques — aurait allongé les délais sans apporter de valeur directe au produit livré.

**JavaScript et CSS natifs**  
Pour les mêmes raisons, les interactions dynamiques côté navigateur sont assurées par du JavaScript sans bibliothèque tierce lourde (pas de bundler, pas de transpileur). Le CSS est écrit directement, permettant un contrôle pixel par pixel du rendu visuel, sans dépendre d'un système de classes utilitaires qui grossit inutilement les fichiers téléchargés par l'utilisateur.

---

### 3.2 Serveur web — Apache HTTP Server

#### Description

Le serveur web retenu est **Apache HTTP Server 2.4**, le serveur web open source le plus répandu dans l'écosystème PHP.

#### Justifications

**Intégration native avec PHP**  
Apache et PHP ont une histoire commune de plus de vingt ans. Le module `mod_php` (ou PHP-FPM en configuration container) permet d'exécuter les scripts PHP directement au sein du processus web, sans configuration complexe. La mise en route est rapide et documentée extensively.

**Module mod_rewrite — gestion des URL propres**  
Apache embarque `mod_rewrite`, qui permet de définir des règles de réécriture d'URL dans des fichiers `.htaccess`. Ce mécanisme est utilisé sur UpcycleConnect pour présenter des URL lisibles aux utilisateurs et aux moteurs de recherche, sans modifier l'arborescence physique des fichiers.

**Adéquation avec le profil de charge attendu**  
UpcycleConnect est une plateforme à portée **nationale**, mais elle s'adresse à une niche engagée dans l'économie circulaire et l'upcycling — non à un réseau social grand public ou à une place de marché généraliste. Ce contexte implique un trafic maîtrisé et prévisible : des milliers d'utilisateurs actifs, non des millions. Apache gère sans effort plusieurs centaines de connexions simultanées sur du matériel standard, et sa configuration multi-processus (MPM Worker ou Event) permet d'absorber des pics ponctuels de charge sans dégradation. S'orienter vers un serveur événementiel comme Nginx ou Caddy aurait représenté une complexité de configuration supplémentaire sans bénéfice mesurable pour ce profil d'usage.

**Maturité et documentation abondante**  
Avec plus de trente ans d'existence, Apache dispose d'une documentation exhaustive, d'une communauté massive et d'une compatibilité testée avec la quasi-totalité des configurations PHP. Le débogage d'un problème Apache est facilité par des années de questions/réponses disponibles en ligne.

**Écosystème de développement identique en production et en développement**  
L'environnement de développement local repose sur XAMPP, qui embarque Apache. Cela garantit que le comportement observé en développement est identique à ce qui sera exécuté en production, réduisant les effets de bord au moment du déploiement.

---

### 3.3 API Backend — Go (Golang)

#### Description

La logique métier, les accès à la base de données et les règles de traitement des données sont centralisés dans une **API RESTful écrite en Go**, exposée sur le port 9999. Ce choix a été imposé par le cahier des charges client.

#### Justifications

**Exigence client**  
Le client a explicitement requis l'utilisation de Go pour le développement du service backend. Cette contrainte est respectée.

**Performances et concurrence**  
Go est un langage compilé à typage statique conçu par Google pour des systèmes concurrents à haut débit. Son modèle de goroutines (threads ultra-légers gérés par le runtime Go) permet de traiter de nombreuses requêtes HTTP simultanées avec une consommation mémoire très faible, sans la complexité du multithreading traditionnel.

**Binaire autonome, déploiement simplifié**  
Un programme Go se compile en un seul binaire statique sans dépendance externe. Cela facilite considérablement le déploiement dans un container Docker : l'image peut être construite sur une base `scratch` ou `alpine`, résultant en une image de quelques mégaoctets seulement.

**Bibliothèque standard puissante**  
Go fournit nativement un package `net/http` complet, permettant de construire un serveur HTTP sans framework tiers. Le routeur, la gestion des middlewares CORS, la sérialisation JSON — tout est disponible dans la bibliothèque standard, ce qui réduit les dépendances et les vecteurs d'attaque liés aux packages tiers.

**Typage statique et robustesse**  
Le typage fort de Go détecte à la compilation une large classe d'erreurs qui passeraient inaperçues dans un langage dynamique. Cela se traduit par une API plus fiable en production.

---

### 3.4 Base de données — MySQL

#### Description

La persistance des données est assurée par **MySQL 8.0**, un système de gestion de base de données relationnelle (SGBDR) open source.

#### Justifications

**Modèle relationnel adapté au domaine métier**  
Les données d'UpcycleConnect sont hautement relationnelles : un utilisateur possède des annonces, des annonces sont associées à des commandes, des commandes à des paiements, des paiements à des prestataires. Un modèle relationnel avec clés étrangères et contraintes d'intégrité représente naturellement ces liens et prévient les incohérences de données.

**Maturité et fiabilité éprouvée**  
MySQL est utilisé en production par des millions d'applications dans le monde depuis plus de vingt-cinq ans. Sa fiabilité est établie, ses comportements sont prévisibles, et les cas limites sont connus et documentés.

**Compatibilité totale avec Go et PHP**  
Go dispose du driver `go-sql-driver/mysql`, performant et maintenu activement. PHP dispose des extensions `PDO_MySQL` et `MySQLi`, intégrées nativement dans toutes les distributions PHP standard. Aucune configuration exotique n'est nécessaire.

**UUID comme clés primaires**  
Le schéma utilise des UUID (format CHAR(36)) comme identifiants primaires plutôt que des entiers auto-incrémentés. Ce choix rend les identifiants non prédictibles (sécurité), facilite les fusions de données entre environnements et prépare une éventuelle migration vers une architecture distribuée.

**Transactions et intégrité référentielle**  
Le moteur InnoDB de MySQL, utilisé par défaut depuis la version 5.5, supporte les transactions ACID et les contraintes de clés étrangères. Cela garantit que les opérations critiques (prise de commande, paiement, mise à jour de solde) s'exécutent de manière atomique ou pas du tout.

---

### 3.5 Bibliothèques et dépendances externes — Composer

#### Description

Le gestionnaire de dépendances PHP **Composer** est utilisé pour intégrer des bibliothèques tierces validées et maintenues. Le projet comporte **deux fichiers `composer.json`** distincts, correspondant aux deux périmètres fonctionnels du système.

**`PA - Site Principal/composer.json`** — dépendances du frontend web :

| Package Composer | Domaine | Justification |
|---|---|---|
| `phpmailer/phpmailer ^7.0` | Envoi d'e-mails transactionnels | Standard de facto pour l'envoi SMTP en PHP ; supporte TLS/SSL, HTML, pièces jointes, confirmation de remise |
| `google/apiclient ^2.19` | OAuth 2.0 Google | SDK officiel Google pour la gestion complète du flux d'autorisation OpenID Connect (échange de code, récupération du profil utilisateur) |

**`composer.json`** (racine du projet) — dépendances transverses et sécurité :

| Package Composer | Domaine | Justification |
|---|---|---|
| `stripe/stripe-php ^19.3` | Paiements en ligne — **en production** | SDK officiel Stripe, utilisé activement pour les paiements clients, les virements sortants (*payouts*) vers les comptes professionnels et les demandes de paiement entre utilisateurs |
| `robthree/twofactorauth ^3.0` | Authentification TOTP (2FA) | Implémentation PHP du standard RFC 6238 ; génère et valide les codes temporels à 6 chiffres, gère les codes de secours |
| `endroid/qr-code 4.0` | Génération de QR codes | Produit le QR code présenté à l'utilisateur lors de l'activation du 2FA, scannable par Google Authenticator, Authy ou toute application TOTP compatible |

#### Justification de l'approche Composer

Utiliser Composer pour gérer ces intégrations garantit :

- La **traçabilité des versions** : le fichier `composer.lock` fige exactement la version de chaque dépendance utilisée en production, rendant les builds reproductibles.
- La **mise à jour maîtrisée** : une commande `composer update` identifie les nouvelles versions disponibles. L'équipe décide quand et quoi mettre à jour.
- La **séparation du code projet et du code tiers** : le répertoire `vendor/` n'est jamais modifié manuellement, ce qui simplifie les mises à jour et les audits de sécurité.

#### Authentification TOTP (2FA)

Le protocole **TOTP** (Time-based One-Time Password, RFC 6238) est implémenté pour offrir une double authentification aux utilisateurs qui le souhaitent. Le principe repose sur un secret partagé entre le serveur et l'application d'authentification de l'utilisateur (Google Authenticator, Authy, etc.). À chaque connexion avec le 2FA activé, un code à 6 chiffres valide seulement 30 secondes est requis en complément du mot de passe. Ce mécanisme rend une attaque par vol de mot de passe seul inopérante.

#### Intégration Stripe — paiements en production

Stripe est le prestataire de paiement **activement utilisé** sur UpcycleConnect. Il est intégré via le SDK officiel `stripe/stripe-php` et couvre l'intégralité des flux financiers de la plateforme :

- **Paiements clients** : encaissement sécurisé lors de la commande d'un service d'upcycling
- **Virements sortants (*payouts*)** : transfert automatique de fonds vers le compte bancaire des prestataires professionnels inscrits
- **Demandes de paiement (*payment requests*)** : permettre à un prestataire d'initier une demande de règlement auprès d'un client
- **Gestion des soldes** : chaque utilisateur dispose d'un solde interne (champ `balance` en base) alimenté et débité via les webhooks Stripe

Stripe est retenu pour sa conformité **PCI-DSS niveau 1** (la certification la plus exigeante du secteur des paiements), sa documentation exhaustive, et son API reconnue comme référence dans l'industrie. Les données de carte bancaire ne transitent jamais par les serveurs UpcycleConnect : Stripe les tokenise côté navigateur via Stripe.js, éliminant l'essentiel des obligations de conformité PCI.

---

### 3.6 Conteneurisation — Docker

#### Description

L'ensemble de la stack applicative est encapsulée dans **4 containers Docker** orchestrés via Docker Compose. Cette approche garantit la portabilité, la reproductibilité et l'isolation de l'environnement d'exécution.

#### Les 4 containers

| # | Container | Image de base | Rôle |
|---|---|---|---|
| 1 | `apache-php` | `php:8.x-apache` | Serveur web Apache + interpréteur PHP, sert le frontend |
| 2 | `go-api` | `golang:alpine` → `alpine` (multi-stage) | API REST Go, exposée sur le port 9999 |
| 3 | `mysql` | `mysql:8.0` | Serveur de base de données MySQL |
| 4 | `phpmyadmin` *(ou proxy/reverse)* | `phpmyadmin` *(ou `nginx:alpine`)* | Interface d'administration BDD ou reverse-proxy pour le routage |

#### Justifications

**Portabilité totale**  
Un container Docker encapsule l'application et toutes ses dépendances système. L'environnement de développement est strictement identique à l'environnement de production : même version de PHP, même version de Go, même configuration MySQL. Cela élimine la classe entière des bugs "ça marche chez moi".

**Isolation des services**  
Chaque composant tourne dans son propre container avec son propre espace de processus, son propre filesystem et ses propres ressources réseau. Une faille ou un crash dans le container PHP n'affecte pas le container Go, et inversement. Cette isolation améliore la sécurité et la résilience globale.

**Déploiement reproductible**  
Un `docker-compose up` suffit à démarrer l'intégralité de la stack sur n'importe quel serveur disposant de Docker. Cela raccourcit considérablement les procédures de déploiement et réduit le risque d'erreur humaine lors des mises en production.

**Build multi-stage pour Go**  
Le container de l'API Go utilise un build multi-stage : la première étape compile le binaire dans une image Go complète, la seconde étape copie uniquement le binaire dans une image Alpine minimaliste (~5 MB). L'image de production est légère et ne contient pas les outils de compilation, réduisant la surface d'attaque.

**Scalabilité future**  
Docker Compose convient parfaitement à l'échelle actuelle du projet. Si UpcycleConnect venait à connaître une croissance importante du trafic, la migration vers une orchestration Kubernetes serait facilitée par le fait que les services sont déjà conteneurisés et stateless.

---

### 3.7 Application mobile — Kotlin (Android)

#### Description

Une application mobile native Android est développée en **Kotlin**, le langage officiel recommandé par Google pour le développement Android depuis 2017.

#### Justifications

**Langage officiel Android**  
Google a déclaré Kotlin langage "first-class" pour Android en 2017 et "preferred language" en 2019. L'ensemble des nouvelles API Android, des exemples officiels et de la documentation Google sont écrits en Kotlin. Développer en Kotlin garantit l'accès aux fonctionnalités les plus récentes du SDK Android et un support à long terme.

**Sécurité de type et null-safety**  
Kotlin intègre nativement la gestion des valeurs nulles dans son système de types. Les `NullPointerException`, cause historique numéro un des crashs d'applications Android en Java, sont détectées à la compilation. Cela se traduit directement par une application plus stable.

**Interopérabilité Java**  
Kotlin est 100% interopérable avec Java. L'ensemble de l'écosystème de bibliothèques Android (Retrofit pour les appels REST, Room pour la persistance locale, etc.) est utilisable directement.

**Consommation de l'API Go**  
L'application mobile consomme la même API RESTful Go que le frontend web. Il n'y a donc pas de surface backend supplémentaire à développer ou à maintenir. L'API unique sert les deux clients (web et mobile), garantissant la cohérence des données et des règles métier.

**Expérience native**  
Par rapport à une approche hybride (React Native, Flutter), une application native Kotlin offre les meilleures performances, un accès direct à toutes les API du système Android (notifications push, appareil photo, GPS) et un respect parfait des guidelines UX Android (Material Design). Pour une application destinée à des professionnels utilisant régulièrement la plateforme en mobilité, la fluidité de l'expérience est un critère de satisfaction non négligeable.

---

## 4. Architecture des containers Docker

```
┌─────────────────────────────────────────────────────────────────────┐
│                        Docker Network (bridge)                      │
│                                                                     │
│  ┌──────────────────────┐        ┌───────────────────────────────┐  │
│  │  Container 1         │        │  Container 2                  │  │
│  │  apache-php          │──────▶ │  go-api                       │  │
│  │  php:8.x-apache      │  REST  │  alpine (binaire Go compilé)  │  │
│  │  :80 / :443          │        │  :9999                        │  │
│  └──────────────────────┘        └───────────────┬───────────────┘  │
│                                                  │                  │
│  ┌──────────────────────┐        ┌───────────────▼───────────────┐  │
│  │  Container 4         │        │  Container 3                  │  │
│  │  admin / proxy       │──────▶ │  mysql:8.0                    │  │
│  │  (phpmyadmin/nginx)  │  SQL   │  :3306                        │  │
│  └──────────────────────┘        └───────────────────────────────┘  │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

### Communications inter-containers

- Le container `apache-php` communique avec `go-api` via des appels HTTP internes au réseau Docker (non exposés publiquement).
- Le container `go-api` est le seul à émettre des requêtes SQL vers `mysql`.
- Le réseau Docker bridge isole la totalité de la stack du reste de l'hôte.
- Seuls les ports 80 (HTTP) et 443 (HTTPS) du container Apache sont exposés à l'extérieur.

### Volumes persistants

- Un volume Docker dédié est monté sur le container MySQL pour garantir la persistance des données au redémarrage des containers.
- Un volume est également monté pour les fichiers uploadés par les utilisateurs (photos d'annonces, pièces jointes) afin qu'ils survivent aux cycles de redéploiement.

---

## 5. Sécurité applicative

La sécurité n'est pas une fonctionnalité ajoutée en fin de projet : elle est intégrée dès la conception au travers des choix technologiques suivants.

| Menace | Contre-mesure retenue |
|---|---|
| Vol de mot de passe (fuite BDD) | Hachage `bcrypt` avec facteur de coût 10 (Go : `golang.org/x/crypto/bcrypt`) |
| Attaque par rejeu de session | Sessions PHP côté serveur avec identifiants non prédictibles |
| Phishing / vol de compte | Authentification TOTP 2FA (RFC 6238) disponible pour tous les utilisateurs |
| Enumération des ressources | UUID aléatoires comme clés primaires (non séquentiels) |
| Scripts inter-sites (XSS) | Echappement systématique des sorties PHP (`htmlspecialchars`) |
| Injections SQL | Utilisation exclusive de requêtes préparées (PDO côté PHP, `database/sql` côté Go) |
| Robots et formulaires abusifs | Google reCAPTCHA v3 sur les formulaires publics sensibles |
| Fraude à l'identité OAuth | Validation des tokens auprès du fournisseur OAuth avant création de session |
| Exposition des données en transit | HTTPS obligatoire (certificat TLS via Let's Encrypt) |
| Fuites de clés API | Variables d'environnement chargées depuis des fichiers `.env` non versionnés |

---

## 6. Schéma d'architecture global

```
                         ┌──────────────────────────┐
                         │     Internet / HTTPS      │
                         └────────────┬─────────────┘
                                      │
                    ┌─────────────────┼──────────────────┐
                    │                 │                   │
          ┌─────────▼────────┐        │        ┌──────────▼────────┐
          │  Navigateur Web  │        │        │  App Kotlin        │
          │  (PHP Frontend)  │        │        │  (Android natif)  │
          └─────────┬────────┘        │        └──────────┬────────┘
                    │ HTTP/REST        │                   │ HTTP/REST
                    │                 │                   │
          ┌─────────▼─────────────────▼───────────────────▼────────┐
          │                  Container Go API :9999                  │
          │   - Routage REST       - Authentification bcrypt/TOTP   │
          │   - Logique métier     - Stripe Payouts / Paiements      │
          │   - Sérialisation JSON - Notifications                   │
          └───────────────────────────┬────────────────────────────┘
                                      │ SQL (réseau Docker interne)
                         ┌────────────▼──────────────┐
                         │   Container MySQL 8.0      │
                         │   Base : upcycle           │
                         │   UUID · InnoDB · ACID     │
                         └───────────────────────────┘

  Services externes :
  ┌──────────────┐  ┌─────────────────┐  ┌──────────────────┐
  │  Stripe API  │  │  Google OAuth   │  │  reCAPTCHA v3    │
  │  (paiements) │  │  Facebook OAuth │  │  (anti-robots)   │
  └──────────────┘  └─────────────────┘  └──────────────────┘
```

---

## 7. Synthèse des choix technologiques

| Composant | Technologie retenue | Catégorie de choix |
|---|---|---|
| Frontend web | PHP natif + JS vanilla + CSS pur | Simplicité, contrôle total |
| Serveur web | Apache HTTP Server 2.4 | Adéquation charge, maturité |
| API backend | Go (Golang) | Exigence client + performances |
| Base de données | MySQL 8.0 | Modèle relationnel, fiabilité |
| Gestion de dépendances PHP | Composer | Standard de l'écosystème PHP |
| Double authentification | TOTP via RobThree/TwoFactorAuth | Sécurité, standard ouvert RFC 6238 |
| Paiements | Stripe SDK PHP | Leader du marché, PCI-DSS niveau 1 |
| Connexion sociale | OAuth 2.0 Google + Facebook | Réduction de friction utilisateur |
| QR Code | Endroid QR Code | Provisionnement TOTP |
| Conteneurisation | Docker + Docker Compose (4 containers) | Portabilité, reproductibilité |
| Application mobile | Kotlin Android natif | Officiel Google, performances natives |

---

*Document rédigé dans le cadre du projet académique UpcycleConnect — Architecture & déploiement.*
