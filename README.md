# DeepShield — Site complet (landing + connexion + dashboard démo)

Refonte du front-end PHP dans l'esprit de **SecureMail** (même famille de design : hero animé
avec canvas de particules, navbar flottante, sections "Fonctionnalités" / "Fonctionnement",
police Syne/DM Sans/Space Mono) mais réadaptée au sujet de DeepShield (détection deepfake
vidéo/audio pour la vérification KYC) avec un visuel de scan biométrique plutôt qu'un bouclier
anti-phishing.

## Pages

Le site est désormais organisé en deux zones, à la manière de SecureMail : des pages
publiques (marketing) et un espace applicatif avec sidebar de navigation (Dashboard /
Nouvelle analyse / Historique / FAQ).

| Page | Zone | Rôle |
|---|---|---|
| `public/index.php` | Publique | **Accueil** — landing page marketing (hero, fonctionnalités, fonctionnement, équipe, CTA) |
| `public/login.php` | Publique | **Connexion / Inscription** — interface complète (onglets, formulaires) mais **non branchée à un backend** : soumettre un formulaire affiche un message "bientôt disponible". Un bouton "Essayer la démo" mène directement au dashboard. |
| `public/dashboard.php` | Applicative | **Tableau de bord** — cartes de statistiques (total, RÉEL, SUSPECT, DEEPFAKE) + activité récente, comme le dashboard SecureMail |
| `public/analyser.php` | Applicative | **Nouvelle analyse** — formulaire d'upload vidéo/audio (déplacé ici depuis l'ancien dashboard) |
| `public/historique.php` | Applicative | **Historique** — liste complète des analyses avec filtre par verdict |
| `public/faq.php` | Applicative | **FAQ** — recherche + accordéon, questions propres à DeepShield (verdicts, explicabilité, confidentialité, limites, compte) |
| `public/report.php` | Applicative | Rapport d'analyse détaillé (verdict, score, explicabilité, export JSON) |
| `public/analyze.php` | — | Traitement de l'upload, appel du moteur Python |
| `public/health.php` | — | Endpoint de supervision JSON |

La sidebar (`src/partials/app_sidebar.php`) est partagée par toutes les pages de l'espace
applicatif et met en évidence la page active, comme la sidebar de rôle de SecureMail.

C'est exactement ce qui a été demandé : une page d'accueil, une page connexion/inscription
avec un bouton dans l'accueil pour y accéder, sans activer l'authentification, plus un
dashboard et une FAQ propres au projet — le site reste entièrement visitable et l'espace
applicatif fonctionne réellement (upload → analyse → rapport → historique), pour que vous
puissiez le montrer/tester de bout en bout.

## Pourquoi le dashboard n'est pas verrouillé

Comme l'inscription/connexion n'est pas encore activée, verrouiller le dashboard derrière un
login qui ne mène nulle part aurait empêché de visiter le site. Le dashboard est donc en accès
libre pour l'instant ; le jour où l'authentification (base de données utilisateurs, sessions,
rôles) sera développée, il suffira d'ajouter une vérification de session en haut de
`dashboard.php`, `analyze.php` et `report.php` (le code est structuré pour que ce soit un
ajout de quelques lignes, pas une réécriture).

## Arborescence

```
deepshield_site/
├── .env.example            # Configuration (voir plus bas)
├── public/                  # DocumentRoot
│   ├── index.php              # Accueil
│   ├── login.php               # Connexion / inscription (UI seule)
│   ├── dashboard.php            # Dashboard démo (upload + historique)
│   ├── analyze.php               # Traitement de l'upload
│   ├── report.php                 # Rapport détaillé + export JSON
│   ├── health.php                  # Health check
│   └── assets/
│       ├── css/site.css              # Thème partagé (navbar, hero, sections, boutons)
│       ├── css/app.css                # Styles du dashboard/login (panels, tableaux, badges)
│       └── js/site.js, dashboard.js    # Canvas de particules, reveal au scroll, upload
├── src/
│   ├── config.php              # Configuration centralisée (.env)
│   ├── bootstrap.php            # Session + includes communs
│   ├── AnalysisRunner.php        # Appel du moteur Python (proc_open)
│   ├── ReportStore.php            # Persistance des rapports (fichiers JSON)
│   ├── helpers.php                 # Fonctions utilitaires
│   └── partials/navbar.php          # Navbar réutilisée sur toutes les pages
├── bridge/analyze_bridge.py    # Pont Python (voir plus bas)
└── storage/{uploads,reports}   # Fichiers temporaires et rapports
```

## Base de données et authentification

L'inscription et la connexion sont **réellement branchées** sur MySQL/MariaDB, à partir du
schéma fourni (`db/deepshield_bdd.sql`). Aucune table n'a été modifiée : les colonnes
existantes suffisent. La base doit s'appeler **`deepshield`**.

### Importer la base — en ligne de commande

```
mysql -u root -p -e "CREATE DATABASE deepshield CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
mysql -u root -p deepshield < db/deepshield_bdd.sql
```

(Le fichier `db/deepshield_bdd.sql` ne contient que du `CREATE TABLE` : il crée les tables
`account_deletion_logs`, `assistance`, `login_attempts`, `users`, `videos` à l'intérieur de
la base `deepshield` que vous venez de créer — il ne crée pas la base lui-même, d'où la
première commande.)

### Importer la base — via phpMyAdmin

1. Ouvrez phpMyAdmin, onglet **Bases de données**, créez une base nommée `deepshield`
   (interclassement `utf8mb4_general_ci`).
2. Cliquez sur cette base dans la liste de gauche pour vous placer dedans.
3. Onglet **Importer** → **Choisir un fichier** → sélectionnez `db/deepshield_bdd.sql` →
   **Exécuter**.
4. Vérifiez dans l'onglet **Structure** que les 5 tables apparaissent bien.

### Configurer le site

Dans `.env` :

```
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=deepshield
DB_USER=votre_utilisateur
DB_PASS=votre_mot_de_passe
```

Avec un simple XAMPP/WAMP/MAMP en local, c'est en général `DB_USER=root` et `DB_PASS=`
(vide). Une fois `.env` renseigné, ouvrez `http://localhost/.../public/health.php` : le
champ `"database": {"reachable": true}` confirme que la connexion fonctionne.

L'extension PHP `pdo_mysql` doit être installée (`php-mysql` sous Debian/Ubuntu, activée
par défaut dans XAMPP/WAMP/MAMP).

### Comment ça marche

- **`src/Database.php`** — connexion PDO unique, configurée uniquement via `.env`. Si la
  base est injoignable, aucune page ne plante : un message d'erreur explicite s'affiche
  à la place (exigence 5.3 du cahier des charges, "jamais d'échec silencieux").
- **`src/Auth.php`** — inscription (`users`, mot de passe haché avec `password_hash`),
  connexion, et un **verrouillage anti-bruteforce** simple : 5 échecs de connexion
  bloquent le compte (`users.is_active = 2`) et chaque tentative échouée est journalisée
  dans `login_attempts`.
- **`src/VideoRepository.php`** — écrit et lit la table `videos`. Le score (0-100) stocke
  le pourcentage de confiance « réel », et le champ `explinations` (tel quel dans le
  schéma fourni) commence toujours par le verdict (`RÉEL`/`SUSPECT`/`DEEPFAKE`), ce qui
  permet de filtrer l'historique par verdict sans ajouter de colonne.
- **Lien rapport JSON ↔ ligne `videos`** : le rapport JSON détaillé (frames, explicabilité)
  reste stocké en fichier (`storage/reports/`), mais nommé avec l'**id de la ligne `videos`**
  plutôt qu'un UUID aléatoire — aucune colonne supplémentaire n'était donc nécessaire pour
  relier les deux.
- **Toutes les pages de l'espace applicatif** (`dashboard.php`, `analyser.php`,
  `historique.php`, `report.php`) exigent désormais une connexion (`ds_require_login()`)
  et un rapport ne peut être consulté que par son propriétaire.

### Ce qui a changé par rapport à la version précédente

- `login.php` traite réellement les formulaires (inscription + connexion) au lieu
  d'afficher un message "bientôt disponible".
- `logout.php` a été ajouté.
- `analyze.php` insère une ligne dans `videos` après chaque analyse vidéo réussie, liée à
  l'utilisateur connecté (`user_id`). Une analyse audio seule (sans vidéo) reste possible
  mais n'apparaît pas dans l'historique lié à la base, faute de table dédiée dans le
  schéma fourni.
- Le bouton d'accès démo sans compte a été retiré : un compte est maintenant nécessaire
  pour analyser une vidéo.


Identique en substance à la version précédente livrée : reprend `process_video.py` /
`face_utils.py` / `test_model.py` de votre dépôt, mais **sans aucun chemin codé en dur**
(tout passe par `.env`), avec sortie JSON stricte et un **mode démo déterministe**
(`DEEPSHIELD_MOCK_MODE=1`, activé par défaut) qui fonctionne sans installer torch/opencv.
L'audio est explicitement signalé comme "non implémenté" (phase 2 du planning) plutôt que de
simuler un faux résultat.

## Dépannage — "l'analyse ne fait rien"

Si le formulaire d'analyse ne semble rien produire (pas de résultat, retour silencieux) :

1. **Ouvrez `health.php`** dans le navigateur (`http://localhost/.../public/health.php`) et
   regardez le champ `"python": {"reachable": ...}`.
   - Si `false` : PHP n'arrive pas à exécuter Python. Sous Windows (XAMPP/WAMP), la commande
     `python3` n'existe généralement pas — mettez `DEEPSHIELD_PYTHON_BIN=python` (ou `py`)
     dans `.env`, voire le chemin complet (`C:/Python312/python.exe`).
2. **Les erreurs sont maintenant affichées** : les pages "Nouvelle analyse" et "Historique"
   affichent un bandeau rouge en cas d'échec (upload invalide, moteur Python indisponible,
   base de données injoignable). Avant, ce message était généré en interne mais jamais
   montré à l'écran, ce qui donnait l'impression que rien ne se passait — c'est corrigé.
3. **Droits d'écriture** : le serveur web doit pouvoir créer des fichiers dans
   `storage/uploads/` et `storage/reports/`.
4. **`DEEPSHIELD_MOCK_MODE=1`** (par défaut) nécessite quand même un interpréteur Python
   installé et accessible : c'est lui qui exécute `bridge/analyze_bridge.py`. Seules les
   bibliothèques ML (torch, opencv…) sont facultatives en mode mock.

## Installation

```
cp .env.example .env
cd public
php -S localhost:8000
```

Ouvrez `http://localhost:8000` — vous arrivez sur la page d'accueil. Le bouton "Accéder à la
plateforme" mène à `login.php`, où vous pouvez créer un compte puis vous connecter pour
accéder au dashboard.

## Prochaines étapes suggérées

- Brancher une vraie base de données utilisateurs derrière `login.php` (table `users`,
  hachage `password_hash`, sessions) puis ajouter une vérification de session en haut de
  `dashboard.php`.
- Une fois l'API FastAPI du cahier des charges prête (phase 5), remplacer le contenu de
  `AnalysisRunner::run()` par un appel HTTP au lieu de `proc_open` — aucune autre page n'a
  besoin de changer.
- Brancher le pipeline audio réel (phase 2) dans `bridge/analyze_bridge.py`.
