# ia-diagnostic · Diagnostic de réparation par photo

Microservice Python (FastAPI) du module **Ateliers & services** de TexTileCycle. À partir de la photo d'un vêtement
abîmé (et d'une précision facultative du client), il propose les **services du catalogue** qui conviennent.
Laravel l'appelle en HTTP ; il ne lit ni n'écrit aucune base de données.

## Principe

Le modèle **CLIP** (open source, gratuit, exécuté en local) place images et textes dans un même espace de vecteurs.
On compare la photo à des descriptions de défauts sans entraîner de modèle (**classification zero-shot**),
puis on relie le défaut aux services du catalogue reçu de Laravel.

```
Laravel ──POST /diagnostic (photo, services, texte?)──► service.py ──► diagnostic.py (CLIP)
        ◄── JSON : vêtement, défaut, 3 meilleurs services ──┘
```

| Fichier | Rôle |
|---|---|
| `diagnostic.py` | Moteur : encodage CLIP, zero-shot, appariement avec le catalogue |
| `service.py` | API HTTP : jeton, contrôle des photos, réponse JSON |
| `evaluer.py` | Banc de mesure sur des photos annotées et des phrases de client |
| `prototype.py` | Premier script pédagogique (version de base, sans les améliorations) |
| `tests/` | Tests du service (faux moteur ; test avec le vrai modèle si `IA_TEST_MODELE=1`) |
| `assistant.py` | Assistant de réparation : recherche hybride dans les fiches conseils (le « R » du RAG) |
| `connaissances/fiches.json` | 21 fiches conseils (taches, trous, fermetures, entretien…) et services liés |
| `evaluer_assistant.py` | Banc de mesure de la recherche de l'assistant (top-1, top-3, hors sujet) |
| `telecharger_modeles.py` | Télécharge une fois les trois modèles (à lancer avant le premier démarrage) |

## Assistant de réparation (RAG)

`POST /assistant/recherche` (jeton obligatoire), corps JSON `{"question": "…"}` (3 à 300 caractères) : rend les
3 fiches les plus proches et `pertinent` (faux pour une question hors sujet). Laravel (`AssistantService`) envoie
ensuite ces fiches à Gemini, qui rédige la réponse en les citant ; sans Gemini, le site montre les fiches telles quelles.

- Encodeur : `paraphrase-multilingual-MiniLM-L12-v2` (environ 470 Mo, chargé au démarrage en plus de CLIP).
  Sur un nouveau PC, il est téléchargé par `telecharger_modeles.py`, avec les modèles CLIP.
  S'il manque, seul l'assistant est indisponible (`/sante` : `"assistant": "indisponible"`), le diagnostic marche.
- Score = 0,6 × similarité des vecteurs + 0,4 × mots-clés de la fiche ; hors sujet = aucun mot-clé et similarité < 0,55.
- Mesure (`python evaluer_assistant.py`, 10 questions jamais utilisées pour le réglage) : bonne fiche en 1re position
  9 fois sur 10, dans les 3 premières 10 fois sur 10, 4 questions hors sujet sur 4 refusées. Sans les mots-clés : 7/10.
  CLIP multilingue à la place de MiniLM : 6/10, et aucune question hors sujet reconnue.

## Installation (une fois)

Placez l'environnement Python **hors de OneDrive** (environ 1,5 Go).

```bash
python -m venv "$USERPROFILE/.textilecycle-ia/venv"
"$USERPROFILE/.textilecycle-ia/venv/Scripts/python.exe" -m pip install torch --index-url https://download.pytorch.org/whl/cpu
"$USERPROFILE/.textilecycle-ia/venv/Scripts/python.exe" -m pip install -r requirements.txt
```

Puis téléchargez **une fois** les trois modèles (environ 1,6 Go, depuis Hugging Face) : le service démarre ensuite
hors ligne (`HF_HUB_OFFLINE=1`) et ne télécharge rien de lui-même.

```bash
"$USERPROFILE/.textilecycle-ia/venv/Scripts/python.exe" telecharger_modeles.py
```

Guide complet d'installation du module (logiciels, `.env`, dépannage) : [`app/Modules/Ateliers/README.md`](../app/Modules/Ateliers/README.md).

## Lancement

Le plus simple : `lancer.ps1` lit `IA_DIAGNOSTIC_JETON` dans le `.env` de Laravel (un seul secret à gérer).

```powershell
powershell -ExecutionPolicy Bypass -File ia-diagnostic\lancer.ps1
```

Côté Laravel, `.env` contient `IA_DIAGNOSTIC_URL=http://127.0.0.1:8001` et `IA_DIAGNOSTIC_JETON=…` (voir `.env.example`).
La page du site est `/ateliers/diagnostic` ; si le service est arrêté, elle affiche un message et renvoie vers la recherche.

Ou à la main, depuis le dossier `ia-diagnostic` (PowerShell) :

```powershell
$env:IA_DIAGNOSTIC_JETON = "un-secret-long-de-16-caracteres-minimum"
$env:HF_HUB_OFFLINE = "1"
& "$env:USERPROFILE\.textilecycle-ia\venv\Scripts\python.exe" -m uvicorn service:app --host 127.0.0.1 --port 8001
```

Le chargement des modèles prend une vingtaine de secondes ; `GET /sante` répond alors `"statut": "pret"`.
Le même jeton sera déclaré dans le `.env` de Laravel.

## API

### `GET /sante`
Sans jeton. `{"statut": "pret" | "chargement", "modele": "clip-ViT-B-32", "version": "clip-vit-b-32/v8"}`

### `POST /diagnostic`
En-tête `Authorization: Bearer <IA_DIAGNOSTIC_JETON>`, corps `multipart/form-data` :

| Champ | Contenu |
|---|---|
| `photo` | JPG, PNG ou WebP, 5 Mo maximum |
| `services` | JSON : liste de 1 à 200 `{"id", "nom", "description"}` (ids uniques, nom ≤ 120, description ≤ 1000) |
| `texte` | Facultatif, 300 caractères maximum (ex. « trou près de la poche ») |

Réponse 200 :

```json
{
  "vetements": [{"cle": "t-shirt", "probabilite": 0.59}, …],
  "defauts":   [{"cle": "trou", "probabilite": 0.32}, …],
  "services":  [{"id": "652f…a1", "score": 0.41}, …],
  "certain": false,
  "duree_ms": 640,
  "version": "clip-vit-b-32/v8"
}
```

Erreurs (message en français dans `detail`) : 401 jeton, 413 photo trop lourde, 415 format, 422 image illisible,
catalogue ou texte invalide, 503 modèle en cours de chargement.

## Sécurité

- Écoute sur `127.0.0.1` uniquement ; jeton obligatoire (refus de démarrer sans), comparé en temps constant.
- Photo vérifiée (format réel, fichier tronqué, limite de pixels contre les images piégées, orientation EXIF).
- Une analyse à la fois (mémoire limitée) ; aucune donnée personnelle dans les journaux ; photo jamais enregistrée.

## Évaluation

`python evaluer.py` (photos dans `photos-test/`, annotations dans `photos-test/annotations.csv`, jamais versionnées).
Jeu actuel : 11 photos libres de Wikimedia Commons (trous, déchirures, taches), crédits dans `photos-test/SOURCES.csv`.

Deux catalogues :
- **Démo** : `CATALOGUE_DEMO`, descriptions écrites pendant le développement. Mesure **optimiste** : elles reprennent les
  mots des phrases de réparation (biais de mesure découvert en testant sur les vraies données).
- **Réel** : export en lecture seule de la base (`php ia-diagnostic/exporter_catalogue.php`), avec les vraies descriptions
  des ateliers et des services de test sans signification (« smail », « sss »…). **C'est la mesure de référence.**

| Version | Défaut (trou ≈ déchirure) | Démo top-1 | Réel top-1 | Réel top-3 | Réel + texte top-1 | ms/photo |
|---|---|---|---|---|---|---|
| V0 base | 18 % | 27 % | 27 % | 82 % | 18 % | 185 |
| V3 anglais + plusieurs phrases + calibration | 36 % | 27 % | 27 % | 82 % | 18 % | 180 |
| V4 + appariement défaut → service en 2 temps | 36 % | 55 % | 36 % | 91 % | 9 % | 187 |
| V5 + zoom multi-recadrage | 36 % | 73 % | 27 % | 100 % | 9 % | 581 |
| V6 = V5 + modèle de phrases (NLP) | 36 % | 45 % | 18 % | 55 % | 18 % | 915 |
| V7 = V5 + calibration des services | 36 % | 18 % | 18 % | 36 % | 45 % | 659 |
| **V8 = V5 + vocabulaire métier (version utilisée)** | 36 % | 64 % | **64 %** | **100 %** | **91 %** | 659 |

Texte seul → service (16 phrases de client), top-1 sur le catalogue réel : CLIP multilingue 50 %, modèle de phrases 75 %,
**vocabulaire métier 94 %**.

**Pourquoi V8** : sur le catalogue réel, les similarités texte-texte de CLIP sont toutes entre 0,89 et 0,95 (services de test
compris) : l'appariement défaut → service était du bruit et « smail » sortait en tête. La recherche hybride (vocabulaire
métier par défaut et par vêtement, 2 points dans le nom, 1 dans la description, la similarité vectorielle départage) rend
les services sans signification inoffensifs et exploite la précision du client.

**Décisions** : afficher les 3 réparations proposées et les ateliers classés (côté Laravel : note bayésienne, distance,
prix, ouverture, avec la raison affichée) ; présenter le défaut comme une piste (~36 %) avec « Ce n'est pas ça ? » pour le
corriger ; ne pas présenter « certain » comme une garantie. NLP et calibration des services : rejetés après mesure.

**Limites** : 11 photos ne suffisent pas pour des chiffres solides, et aucune photo de fermeture, bouton ou ourlet.
Prochaine étape : 5 photos ou plus par défaut, prises sur de vrais vêtements.
