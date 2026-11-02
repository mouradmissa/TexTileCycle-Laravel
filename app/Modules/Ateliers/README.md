# Module 2 · Ateliers & services

Module de **TexTileCycle** qui met en relation les citoyens et les **ateliers de réparation textile** : annuaire,
fiches, avis vérifiés, passeport de réparation avec QR code, et quatre fonctionnalités d'**intelligence artificielle**.

Ce document explique **tout ce qu'il faut installer et télécharger** pour faire tourner le module, IA comprise.

---

## Sommaire

1. [Fonctionnalités](#1-fonctionnalités)
2. [Ce qu'il faut installer et télécharger](#2-ce-quil-faut-installer-et-télécharger)
3. [Installation pas à pas](#3-installation-pas-à-pas-windows--powershell)
4. [Variables du fichier .env](#4-variables-du-fichier-env)
5. [Lancer le projet](#5-lancer-le-projet)
6. [Vérifier que tout marche](#6-vérifier-que-tout-marche)
7. [Les quatre IA du module](#7-les-quatre-ia-du-module)
8. [Routes](#8-routes)
9. [Organisation du code](#9-organisation-du-code)
10. [Sécurité et données personnelles](#10-sécurité-et-données-personnelles)
11. [Tests](#11-tests)
12. [Dépannage](#12-dépannage)

---

## 1. Fonctionnalités

| Fonctionnalité | Pour qui | Adresse |
|---|---|---|
| Annuaire des ateliers : recherche, filtres (service, ville, note), tri, carte Leaflet | Tout le monde | `/ateliers` |
| Fiche atelier : services et prix, horaires, avis vérifiés, prise de RDV | Tout le monde | `/ateliers/{id}` |
| **Diagnostic de réparation par photo (IA)** | Tout le monde | `/ateliers/diagnostic` |
| **Assistant de réparation (IA, RAG)** | Tout le monde | `/ateliers/assistant` |
| Avis vérifiés (seulement après une réparation terminée) | Client connecté | fiche atelier, section « Avis » |
| Passeport de réparation : page publique ouverte par le QR code | Tout le monde | `/passeport/{code}` |
| Vérifier un passeport (code ou adresse copiée) | Tout le monde | `/passeport` |
| Mes passeports | Client connecté | `/mes-passeports` |
| Espace atelier : profil, services, passeports, étiquette QR à imprimer | Compte atelier | `/admin/ateliers/mon-profil` … |
| **Assistant de l'espace atelier (IA, appel de fonctions)**, bouton flottant | Compte atelier | toutes les pages de l'espace |
| Gestion des ateliers, statuts, révocation des passeports | Administrateur | `/admin/ateliers` |

---

## 2. Ce qu'il faut installer et télécharger

### Logiciels

| Outil | Version | Pourquoi |
|---|---|---|
| **PHP** | 8.0 ou plus (testé en 8.2) avec l'extension **`mongodb`** | Laravel |
| **Composer** | 2.x | Dépendances PHP (dont `bacon/bacon-qr-code` pour les QR codes) |
| **Python** | 3.10 ou plus (testé en **3.13**) | Microservice d'IA `ia-diagnostic/` |
| **Git** | — | Récupérer le dépôt |
| Navigateur récent | — | Interface (Chrome, Edge, Firefox) |

### Paquets Python (fichier `ia-diagnostic/requirements.txt`)

| Paquet | Rôle |
|---|---|
| `torch` (version **CPU**, sans carte graphique) | Calcul des réseaux de neurones |
| `sentence-transformers` | Chargement des modèles CLIP et MiniLM |
| `fastapi` + `uvicorn` | API HTTP du microservice |
| `pillow`, `python-multipart` | Lecture des photos envoyées |
| `pytest`, `httpx` | Tests |

Versions vérifiées : torch 2.14 (CPU), sentence-transformers 6.1, fastapi 0.143.

### Modèles d'IA à télécharger une fois (gratuits, Hugging Face)

| Modèle | Taille | Utilisé par |
|---|---|---|
| `clip-ViT-B-32` | ≈ 580 Mo | Diagnostic photo : compare la photo à des phrases qui décrivent les défauts |
| `sentence-transformers/clip-ViT-B-32-multilingual-v1` | ≈ 520 Mo | Diagnostic photo : comprend la précision du client en français |
| `sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2` | ≈ 460 Mo | Assistant de réparation : recherche dans les fiches conseils |

Le script `ia-diagnostic/telecharger_modeles.py` les télécharge tous (étape 6 ci-dessous).
**Ce téléchargement est obligatoire** : le service démarre ensuite hors ligne (`HF_HUB_OFFLINE=1`) et ne télécharge rien
de lui-même.

### Services en ligne

| Service | Obligatoire ? | Pourquoi |
|---|---|---|
| **MongoDB Atlas** | Oui | Base de données de l'équipe ; votre adresse IP doit être autorisée (Network Access) |
| **Clé API Gemini** (Google AI Studio, offre gratuite) | Non, mais conseillée | Rédige les réponses de l'assistant de réparation et comprend les questions de l'assistant atelier. Sans clé, les deux assistants marchent en mode dégradé (fiches seules, mots-clés). Le diagnostic photo n'en a pas besoin. |

### Place et matériel

| Élément | Besoin |
|---|---|
| Disque | ≈ **3 Go** : environnement Python ≈ 1,1 Go + modèles ≈ 1,6 Go |
| Mémoire vive | 8 Go suffisent : le service IA réserve environ 3 Go (mesuré avec les trois modèles chargés) ; fermez les applications lourdes |
| Carte graphique | Inutile : tout tourne sur le processeur |
| Internet | Pour l'installation ; ensuite seulement pour Atlas et Gemini |

> Mettez l'environnement Python **hors de OneDrive** (dans `%USERPROFILE%\.textilecycle-ia`), sinon la synchronisation
> de plus d'un gigaoctet de petits fichiers ralentit tout.

---

## 3. Installation pas à pas (Windows / PowerShell)

Depuis la racine du projet :

**1. Dépendances PHP**
```powershell
composer install
```

**2. Fichier d'environnement**
```powershell
copy .env.example .env
php artisan key:generate
```
Remplissez ensuite `.env` (section 4).

**3. Environnement Python, hors de OneDrive**
```powershell
python -m venv "$env:USERPROFILE\.textilecycle-ia\venv"
```

**4. PyTorch, version processeur (plus légère que la version GPU)**
```powershell
& "$env:USERPROFILE\.textilecycle-ia\venv\Scripts\python.exe" -m pip install torch --index-url https://download.pytorch.org/whl/cpu
```

**5. Autres paquets Python**
```powershell
& "$env:USERPROFILE\.textilecycle-ia\venv\Scripts\python.exe" -m pip install -r ia-diagnostic\requirements.txt
```

**6. Les trois modèles d'IA (≈ 1,6 Go, une seule fois)**
```powershell
cd ia-diagnostic
& "$env:USERPROFILE\.textilecycle-ia\venv\Scripts\python.exe" telecharger_modeles.py
cd ..
```
Chaque modèle doit afficher « ✓ prêt ».

> ⚠️ Ne lancez **ni migration ni seeder** sur la base partagée de l'équipe sans accord : `php artisan migrate`
> et `db:seed` modifient les données de tout le monde.

---

## 4. Variables du fichier .env

| Variable | Exemple | Rôle |
|---|---|---|
| `MONGODB_URI` | `mongodb+srv://…` | Connexion à MongoDB Atlas (secret, à demander à l'équipe) |
| `MONGODB_DATABASE` | `textilecycle` | Nom de la base |
| `IA_DIAGNOSTIC_URL` | `http://127.0.0.1:8001` | Adresse du microservice d'IA |
| `IA_DIAGNOSTIC_JETON` | 16 caractères ou plus | Secret partagé entre Laravel et le microservice ; inventez-le (le même sert aux deux côtés) |
| `GEMINI_API_KEY` | `AI…` | Clé Gemini (facultative, voir plus haut) |
| `GEMINI_MODEL` | `gemini-3.5-flash-lite` | Modèle Gemini ; un modèle de secours est essayé s'il est refusé |
| `APP_URL` | `http://127.0.0.1:8000` | Adresse écrite dans les QR codes. Pour scanner avec un téléphone, mettez l'IP du PC sur le réseau et lancez `php artisan serve --host=0.0.0.0` |

Réglages facultatifs (valeurs par défaut entre parenthèses) : `IA_DIAGNOSTIC_DELAI` (20 s), `IA_DIAGNOSTIC_PAR_MINUTE` (10),
`IA_ASSISTANT_PAR_MINUTE` (10), `IA_ASSISTANT_DELAI_REDACTION` (20 s).

> Ne mettez jamais `.env` sur Git : il contient les mots de passe et les clés.

---

## 5. Lancer le projet

Une seule commande lance le microservice d'IA (port 8001) en arrière-plan, puis Laravel (port 8000) :

```powershell
powershell -ExecutionPolicy Bypass -File demarrer.ps1
```

- Site : <http://127.0.0.1:8000>, connexion sur `/connexion`.
- Le service d'IA met environ **25 secondes** à charger ses trois modèles.
- `Ctrl + C` arrête les deux.
- Journal du service d'IA : `storage/logs/ia-diagnostic.log`.

Pour lancer le service d'IA seul : `powershell -ExecutionPolicy Bypass -File ia-diagnostic\lancer.ps1`.

---

## 6. Vérifier que tout marche

**Service d'IA** : <http://127.0.0.1:8001/sante> doit répondre :

```json
{"statut": "pret", "modele": "clip-ViT-B-32", "version": "clip-vit-b-32/v8", "assistant": "pret"}
```

- `"statut": "chargement"` : attendez quelques secondes.
- `"assistant": "indisponible"` : le modèle MiniLM manque, relancez `telecharger_modeles.py` (le diagnostic photo marche quand même).

**Pages à ouvrir :**

| Page | Ce qu'on doit voir |
|---|---|
| `/ateliers` | La liste des ateliers et la carte |
| `/ateliers/diagnostic` | Envoyez une photo de vêtement abîmé : le défaut et des ateliers sont proposés |
| `/ateliers/assistant` | Demandez « comment enlever une tache de café ? » : réponse avec la source [1] |
| `/admin/ateliers/mes-services` (compte atelier) | Bouton vert « Assistant » en bas à droite |

---

## 7. Les quatre IA du module

| IA | Technique | Modèle | Où ça tourne | Coût | Mesure |
|---|---|---|---|---|---|
| **Diagnostic par photo** | Classification *zero-shot* : la photo est comparée à des phrases de défauts, puis le vocabulaire métier relie le défaut aux services | CLIP + CLIP multilingue | Local (Python) | Gratuit | 11 photos : bon service dans les 3 premiers 100 %, en 1er 64 % ; avec la précision écrite 91 % |
| **Recommandation d'ateliers** | Score : note bayésienne 40 %, distance 35 %, prix 15 %, ouvert maintenant 10 % | — (calcul, pas d'IA) | Laravel | Gratuit | — |
| **Assistant de réparation** | **RAG** : recherche hybride (sens + mots-clés) dans 21 fiches conseils, puis rédaction par Gemini qui cite ses sources | MiniLM + Gemini | Local + Google | Gratuit (quota Gemini) | 10 questions nouvelles : bonne fiche en 1er 9/10, dans les 3 premières 10/10 ; hors sujet refusé 4/4 |
| **Assistant de l'espace atelier** | **Appel de fonctions** : Gemini choisit un outil parmi 6, Laravel lit les données et rédige la réponse | Gemini | Google (question seule) + Laravel | Gratuit (quota Gemini) | Testé en vrai : questions comprises, hors sujet et injection refusés |

Bancs de mesure : `ia-diagnostic/evaluer.py` (photos) et `ia-diagnostic/evaluer_assistant.py` (assistant).
Détails techniques du microservice : [`ia-diagnostic/README.md`](../../../ia-diagnostic/README.md).

---

## 8. Routes

**Front** (`Routes/front.php`)

| Méthode | Adresse | Nom |
|---|---|---|
| GET | `/ateliers` · `/ateliers/carte.json` · `/ateliers/{id}` | `front.ateliers`, `front.ateliers.map`, `front.ateliers.show` |
| GET, POST | `/ateliers/diagnostic` | `front.ateliers.diagnostic`, `.analyser` |
| GET, POST | `/ateliers/assistant` | `front.ateliers.assistant`, `.repondre` |
| POST, DELETE | `/ateliers/{id}/avis` | `front.ateliers.avis.store`, `.destroy` |
| GET | `/passeport` · `/passeport/recherche` · `/passeport/{code}` · `/passeport/{code}/qr.svg` | `front.passeport.verifier`, `.rechercher`, `front.passeport`, `.qr` |
| GET | `/mes-passeports` | `front.passeports` |

**Back office** (`Routes/back.php`, préfixe `/admin`)

| Adresse | Pour qui |
|---|---|
| `/admin/ateliers` (liste, création, édition, statut, suppression) | Administrateur |
| `/admin/ateliers/passeports` (révoquer / rétablir) | Administrateur |
| `/admin/ateliers/mon-profil` · `/mes-services` · `/mes-passeports` · `/mon-assistant` | Compte atelier |

---

## 9. Organisation du code

```
app/Modules/Ateliers/
├── Config/diagnostic.php            réglages du microservice et des assistants
├── Http/Controllers/Front|Back/     contrôleurs (minces : la logique est dans les services)
├── Http/Requests/                   contrôles de saisie (uniquement en PHP)
├── Models/                          Atelier, Service, Avis, Passeport
├── Services/
│   ├── AtelierService.php           recherche, filtres, gestion
│   ├── DiagnosticService.php        appel du microservice (photo)
│   ├── RecommandationService.php    classement des ateliers
│   ├── AssistantService.php         assistant de réparation (RAG + Gemini)
│   ├── AssistantAtelierService.php  assistant atelier (appel de fonctions)
│   ├── ActiviteAtelierService.php   les 6 « outils » en lecture seule
│   ├── AvisService.php · PasseportService.php
└── Routes/front.php · back.php

ia-diagnostic/                       microservice Python (FastAPI)
├── diagnostic.py · service.py       moteur CLIP et API HTTP
├── assistant.py                     recherche hybride de l'assistant
├── connaissances/fiches.json        21 fiches conseils
├── telecharger_modeles.py           téléchargement des 3 modèles
├── evaluer.py · evaluer_assistant.py bancs de mesure
└── tests/                           tests pytest
```

Vues : `resources/views/front/` (ateliers, diagnostic, assistant, passeport…) et `resources/views/back/ateliers/`.
Styles et scripts : `public/css/` et `public/js/` (fichiers `ateliers*`, `diagnostic`, `assistant*`, `passeport*`, `avis`).

---

## 10. Sécurité et données personnelles

- **Microservice d'IA** : il n'écoute que sur la machine (`127.0.0.1`), exige un jeton secret et vérifie les photos
  (format réel, 5 Mo au plus, protection contre les images piégées). La photo n'est jamais enregistrée.
- **Assistant de réparation** : seule la question part chez Google. Gemini ne doit répondre qu'à partir des fiches et ignore
  les consignes cachées dans la question. Les questions hors sujet n'appellent pas Gemini.
- **Assistant atelier** : l'IA ne fait que **choisir** un outil prévu. L'atelier vient toujours du compte connecté, les
  lectures sont limitées à cet atelier, aucune donnée de client n'est lue pour la réponse, et aucune donnée de la base n'est
  envoyée à Google.
- **Passeport** : code aléatoire de 22 caractères (impossible à deviner), aucune donnée du propriétaire sur la page
  publique, pages non indexées par les moteurs de recherche, révocation sans suppression.
- **Limites de requêtes** sur le diagnostic, les assistants, les avis et les passeports. Les avis ne sont possibles qu'après
  une réparation terminée et datée.

---

## 11. Tests

Les tests n'écrivent jamais dans Atlas : la base, le microservice et Gemini sont simulés.

```powershell
php artisan test tests/Feature/Ateliers
```
```powershell
php artisan test tests/Unit/Ateliers
```
```powershell
cd ia-diagnostic
& "$env:USERPROFILE\.textilecycle-ia\venv\Scripts\python.exe" -m pytest -q
```

Résultats attendus : 177 tests Feature, 60 tests Unit, 36 tests Python.
`php artisan test` ne prend qu'un dossier à la fois.

---

## 12. Dépannage

| Symptôme | Cause | Solution |
|---|---|---|
| Erreur 500 « TLS handshake failed … tlsv1 alert internal error » | Votre IP n'est pas autorisée dans Atlas (elle change souvent) | Faites ajouter votre IP dans Atlas > Network Access ; pour la retrouver : <https://api.ipify.org> |
| « Le diagnostic par photo est indisponible » | Service d'IA arrêté ou en chargement | Ouvrez `/sante` ; lisez `storage/logs/ia-diagnostic.log` |
| Le service d'IA s'arrête au démarrage | Modèles absents, ou `IA_DIAGNOSTIC_JETON` vide ou trop court | Lancez `telecharger_modeles.py` ; mettez un jeton d'au moins 16 caractères |
| `"assistant": "indisponible"` dans `/sante` | Modèle MiniLM absent | Relancez `telecharger_modeles.py` |
| L'assistant affiche « La rédaction automatique est indisponible » | Pas de clé Gemini, ou quota dépassé | Vérifiez `GEMINI_API_KEY` ; les fiches restent affichées |
| L'assistant atelier répond « Compris par mots-clés » | Gemini indisponible | Même chose : le mode de secours répond quand même |
| Erreur 429 | Trop de requêtes en une minute | Attendez une minute |
| Le QR code ne s'ouvre pas sur un téléphone | `APP_URL` vaut `127.0.0.1` | Mettez l'IP du PC sur le réseau dans `APP_URL` et lancez `php artisan serve --host=0.0.0.0` |
| Le port 8001 est déjà utilisé | Un ancien service d'IA tourne encore | `demarrer.ps1` le réutilise ; sinon fermez le processus Python correspondant |
