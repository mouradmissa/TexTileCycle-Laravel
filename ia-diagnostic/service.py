"""
Microservice de diagnostic textile : une API HTTP autour du moteur CLIP (diagnostic.py), appelée par Laravel.

Lancement (voir README.md) :
    set IA_DIAGNOSTIC_JETON=un-secret-long          (PowerShell : $env:IA_DIAGNOSTIC_JETON="…")
    uvicorn service:app --host 127.0.0.1 --port 8001

Routes :
    GET  /sante       état du service (sans jeton, aucune donnée sensible)
    POST /diagnostic  photo + catalogue de services (+ texte du client, + défaut choisi par le client)
                      -> défauts et services classés
    POST /assistant/recherche  question d'un client -> fiches conseils les plus proches (partie « R » du RAG)
"""

from __future__ import annotations

import hmac
import io
import json
import logging
import os
import threading
import time
from contextlib import asynccontextmanager

from fastapi import Depends, FastAPI, File, Form, Header, HTTPException, UploadFile
from PIL import Image, ImageOps, UnidentifiedImageError
from pydantic import BaseModel, Field, ValidationError

import assistant
from diagnostic import CLES_DEFAUTS, MODELE_CLIP, MoteurDiagnostic, Options, Service, charger_modele_phrases

VERSION = "clip-vit-b-32/v8"

TAILLE_MAX = 5 * 1024 * 1024           # 5 Mo, comme la photo d'un vêtement côté Laravel
PIXELS_MAX = 40_000_000                # protège contre les images « bombes » de décompression
FORMATS = {"JPEG", "PNG", "WEBP"}
SERVICES_MAX = 200
TEXTE_MAX = 300
NB_RESULTATS = 3
QUESTION_MAX = 300

journal = logging.getLogger("ia-diagnostic")
Image.MAX_IMAGE_PIXELS = PIXELS_MAX

# Une seule analyse à la fois : chaque analyse charge le processeur et la mémoire (8 Go sur la machine de dev).
_verrou = threading.Lock()
_etat: dict[str, object] = {}


def jeton_attendu() -> str:
    return os.environ.get("IA_DIAGNOSTIC_JETON", "")


@asynccontextmanager
async def cycle_de_vie(app: FastAPI):
    if len(jeton_attendu()) < 16:
        raise RuntimeError("Définissez IA_DIAGNOSTIC_JETON (16 caractères minimum) avant de lancer le service.")
    debut = time.perf_counter()
    _etat["moteur"] = MoteurDiagnostic(Options())
    journal.warning("Modèles chargés en %.1f s", time.perf_counter() - debut)
    # L'assistant est un bonus : s'il ne se charge pas, le diagnostic par photo reste disponible.
    try:
        debut = time.perf_counter()
        modele = charger_modele_phrases()
        encodeur = lambda textes: modele.encode(textes, convert_to_tensor=True, normalize_embeddings=True)
        _etat["index"] = assistant.IndexConnaissances(assistant.charger_fiches(), encodeur)
        journal.warning("Assistant : %d fiches indexées en %.1f s", len(_etat["index"].fiches), time.perf_counter() - debut)
    except Exception:  # noqa: BLE001 - on journalise et on continue sans assistant
        journal.exception("Assistant indisponible : fiches ou modèle de phrases introuvables.")
    yield
    _etat.clear()


app = FastAPI(title="TexTileCycle · Diagnostic textile", version=VERSION, lifespan=cycle_de_vie)


# ---------------------------------------------------------------- dépendances

def obtenir_moteur() -> MoteurDiagnostic:
    moteur = _etat.get("moteur")
    if moteur is None:
        raise HTTPException(503, "Le modèle n'est pas encore chargé.")
    return moteur


def obtenir_index() -> assistant.IndexConnaissances:
    index = _etat.get("index")
    if index is None:
        raise HTTPException(503, "L'assistant n'est pas disponible.")
    return index


def verifier_jeton(authorization: str | None = Header(default=None)) -> None:
    attendu = jeton_attendu()
    recu = (authorization or "").removeprefix("Bearer ").strip()
    # compare_digest : temps de comparaison constant, pour ne pas laisser deviner le jeton.
    if not attendu or not hmac.compare_digest(recu.encode(), attendu.encode()):
        raise HTTPException(401, "Jeton manquant ou invalide.")


# ---------------------------------------------------------------- schémas

class ServiceEntree(BaseModel):
    id: str = Field(min_length=1, max_length=64)
    nom: str = Field(min_length=1, max_length=120)
    description: str = Field(default="", max_length=1000)


class Classement(BaseModel):
    cle: str
    probabilite: float


class ServicePropose(BaseModel):
    id: str
    score: float


class Question(BaseModel):
    question: str = Field(min_length=3, max_length=QUESTION_MAX)


class PassageSortie(BaseModel):
    id: str
    titre: str
    texte: str
    services: list[str]
    score: float


class ReponseAssistant(BaseModel):
    pertinent: bool
    passages: list[PassageSortie]
    duree_ms: int


class Reponse(BaseModel):
    vetements: list[Classement]
    defauts: list[Classement]
    services: list[ServicePropose]
    certain: bool
    duree_ms: int
    version: str


# ---------------------------------------------------------------- lecture des entrées

def lire_services(brut: str) -> list[Service]:
    try:
        donnees = json.loads(brut)
        if not isinstance(donnees, list) or not 1 <= len(donnees) <= SERVICES_MAX:
            raise ValueError
        services = [ServiceEntree.model_validate(element) for element in donnees]
    except (ValueError, ValidationError):
        raise HTTPException(422, f"« services » doit être une liste JSON de 1 à {SERVICES_MAX} services (id, nom, description).")
    if len({service.id for service in services}) != len(services):
        raise HTTPException(422, "Les identifiants de services doivent être uniques.")
    return [Service(s.id, s.nom.strip(), s.description.strip()) for s in services]


def lire_photo(photo: UploadFile) -> Image.Image:
    contenu = photo.file.read(TAILLE_MAX + 1)
    if len(contenu) > TAILLE_MAX:
        raise HTTPException(413, "La photo ne doit pas dépasser 5 Mo.")
    try:
        image = Image.open(io.BytesIO(contenu))
        if image.format not in FORMATS:
            raise HTTPException(415, "La photo doit être au format JPG, PNG ou WebP.")
        image.verify()                                  # détecte un fichier tronqué ou corrompu
        image = Image.open(io.BytesIO(contenu))          # verify() rend l'image inutilisable : on la rouvre
        return ImageOps.exif_transpose(image).convert("RGB")   # photos de téléphone : orientation EXIF
    except HTTPException:
        raise
    except (UnidentifiedImageError, OSError, Image.DecompressionBombError, SyntaxError, ValueError):
        raise HTTPException(422, "Le fichier envoyé n'est pas une image lisible.")


# ---------------------------------------------------------------- routes

@app.get("/sante")
def sante() -> dict:
    return {
        "statut": "pret" if "moteur" in _etat else "chargement",
        "modele": MODELE_CLIP,
        "version": VERSION,
        "assistant": "pret" if "index" in _etat else "indisponible",
    }


@app.post("/diagnostic", response_model=Reponse, dependencies=[Depends(verifier_jeton)])
def diagnostic(
    photo: UploadFile = File(...),
    services: str = Form(...),
    texte: str | None = Form(default=None),
    defaut: str | None = Form(default=None),
    moteur: MoteurDiagnostic = Depends(obtenir_moteur),
) -> Reponse:
    if texte is not None and len(texte) > TEXTE_MAX:
        raise HTTPException(422, f"La précision ne doit pas dépasser {TEXTE_MAX} caractères.")
    if defaut is not None and defaut not in CLES_DEFAUTS:
        raise HTTPException(422, "Le défaut choisi est inconnu.")
    catalogue = lire_services(services)
    image = lire_photo(photo)

    debut = time.perf_counter()
    with _verrou:
        resultat = moteur.diagnostiquer(image, catalogue, (texte or "").strip() or None, defaut)
    duree = int((time.perf_counter() - debut) * 1000)

    # Aucune donnée personnelle dans le journal : seulement la durée et les étiquettes.
    journal.info("diagnostic %d ms : %s / %s", duree, resultat.vetements[0][0], resultat.defauts[0][0])

    arrondir = lambda valeur: round(valeur, 4)
    return Reponse(
        vetements=[Classement(cle=c, probabilite=arrondir(p)) for c, p in resultat.vetements[:NB_RESULTATS]],
        defauts=[Classement(cle=c, probabilite=arrondir(p)) for c, p in resultat.defauts[:NB_RESULTATS]],
        services=[ServicePropose(id=i, score=arrondir(s)) for i, s in resultat.services[:NB_RESULTATS]],
        certain=resultat.certain,
        duree_ms=duree,
        version=VERSION,
    )


@app.post("/assistant/recherche", response_model=ReponseAssistant, dependencies=[Depends(verifier_jeton)])
def assistant_recherche(entree: Question, index: assistant.IndexConnaissances = Depends(obtenir_index)) -> ReponseAssistant:
    debut = time.perf_counter()
    with _verrou:
        passages, pertinent = index.rechercher(entree.question.strip(), NB_RESULTATS)
    duree = int((time.perf_counter() - debut) * 1000)

    # La question du client n'est pas journalisée : seulement la fiche trouvée et la durée.
    journal.info("assistant %d ms : %s (pertinent=%s)", duree, passages[0].fiche.id, pertinent)

    return ReponseAssistant(
        pertinent=pertinent,
        passages=[
            PassageSortie(id=p.fiche.id, titre=p.fiche.titre, texte=p.fiche.texte, services=list(p.fiche.services), score=round(p.score, 4))
            for p in passages
        ],
        duree_ms=duree,
    )
