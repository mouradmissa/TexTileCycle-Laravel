"""
Assistant de réparation : partie « recherche » (Retrieval) d'un RAG.

Les fiches conseils (connaissances/fiches.json) sont transformées une fois en vecteurs au démarrage, avec le modèle
de phrases multilingue MiniLM (meilleur que CLIP pour comparer des phrases : voir evaluer_assistant.py). Pour une question,
on rend les fiches les plus proches ; Laravel les donne ensuite au modèle de langage (Gemini), qui rédige la réponse
en s'appuyant uniquement sur elles et en les citant. Le texte du client ne quitte la machine que vers ce modèle.

Recherche hybride, comme pour le diagnostic (V8) :
    score = 0,6 × similarité des vecteurs (sens de la phrase) + 0,4 × mots-clés trouvés (vocabulaire métier)
Une question sans aucun mot-clé et trop éloignée de toutes les fiches est déclarée « hors sujet » : l'assistant
dit alors qu'il ne sait pas, au lieu d'inventer.
"""

from __future__ import annotations

import json
from dataclasses import dataclass
from pathlib import Path
from typing import Callable

import torch

from diagnostic import mots_de

FICHES = Path(__file__).resolve().parent / "connaissances" / "fiches.json"

POIDS_SEMANTIQUE = 0.6
MOTS_POUR_SCORE_MAX = 2           # deux mots-clés trouvés suffisent pour la note lexicale maximale
SEUIL_SEMANTIQUE = 0.55           # en dessous, sans mot-clé, la question est hors sujet (mesuré avec evaluer_assistant.py)
LONGUEUR_RACINE = 4               # « taches » et « tache », « déchiré » et « déchirure » partagent leur début

# Mots trop courants pour compter comme indice.
MOTS_VIDES = {
    "comment", "pourquoi", "quoi", "quel", "quelle", "quels", "quelles", "est", "sont", "une", "des", "les", "mon",
    "ma", "mes", "sur", "dans", "avec", "pour", "pas", "plus", "faire", "fait", "peut", "peux", "faut", "que", "qui",
    "vous", "nous", "elle", "il", "ils", "leur", "cette", "ces", "son", "sa", "ses", "tres", "bien", "aussi", "avoir",
}

Encodeur = Callable[[list[str]], torch.Tensor]


@dataclass(frozen=True)
class Fiche:
    id: str
    titre: str
    texte: str
    mots: tuple[str, ...]
    services: tuple[str, ...]

    def document(self) -> str:
        """Texte encodé : le titre compte deux fois, il résume la fiche."""
        return f"{self.titre}. {self.titre}. {self.texte}"


@dataclass(frozen=True)
class Passage:
    fiche: Fiche
    score: float
    semantique: float
    mots_trouves: int


def charger_fiches(chemin: Path = FICHES) -> list[Fiche]:
    donnees = json.loads(chemin.read_text(encoding="utf-8"))
    fiches = [
        Fiche(d["id"], d["titre"], d["texte"], tuple(d.get("mots", [])), tuple(d.get("services", [])))
        for d in donnees
    ]
    if len({f.id for f in fiches}) != len(fiches):
        raise ValueError("Deux fiches ont le même identifiant.")
    return fiches


def indices_de(texte: str) -> set[str]:
    """Racines des mots porteurs de sens : sans accents, sans mots vides, coupées à LONGUEUR_RACINE lettres."""
    return {mot[:LONGUEUR_RACINE] for mot in mots_de(texte) if len(mot) >= LONGUEUR_RACINE and mot not in MOTS_VIDES}


class IndexConnaissances:
    def __init__(self, fiches: list[Fiche], encodeur: Encodeur):
        if not fiches:
            raise ValueError("Aucune fiche à indexer.")
        self.fiches = fiches
        self.encodeur = encodeur
        self.vecteurs = encodeur([f.document() for f in fiches])     # normalisés : produit scalaire = cosinus
        self.racines = [indices_de(" ".join(f.mots) + " " + f.titre) for f in fiches]

    def rechercher(self, question: str, nb: int = 3) -> tuple[list[Passage], bool]:
        """Les `nb` fiches les plus proches, et si la question relève bien des fiches (sinon : « je ne sais pas »)."""
        semantique = (self.encodeur([question]) @ self.vecteurs.T)[0]
        indices = indices_de(question)

        passages = []
        for i, fiche in enumerate(self.fiches):
            trouves = len(indices & self.racines[i])
            lexical = min(trouves, MOTS_POUR_SCORE_MAX) / MOTS_POUR_SCORE_MAX
            sem = float(semantique[i])
            passages.append(Passage(fiche, POIDS_SEMANTIQUE * sem + (1 - POIDS_SEMANTIQUE) * lexical, sem, trouves))

        passages.sort(key=lambda p: p.score, reverse=True)
        meilleurs = passages[:nb]
        pertinent = meilleurs[0].mots_trouves > 0 or meilleurs[0].semantique >= SEUIL_SEMANTIQUE
        return meilleurs, pertinent
