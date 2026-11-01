"""
Banc de mesure de la recherche de l'assistant (le « R » du RAG), sans modèle de langage.

Pour chaque question de client, on regarde si la bonne fiche sort en premier (top-1) ou dans les 3 premières (top-3),
et si les questions hors sujet sont bien refusées. Les questions sont écrites comme un client les taperait
(fautes, tournures orales), pas avec les mots des fiches.

    python evaluer_assistant.py                 # encodeur du service : modèle de phrases MiniLM multilingue
    python evaluer_assistant.py --clip          # comparaison : CLIP multilingue (celui du diagnostic)
    python evaluer_assistant.py --semantique    # sans les mots-clés, pour mesurer leur apport

Mesures du 2026-11-01. « Réglage » : 28 questions qui ont servi à ajuster les mots-clés des fiches (donc optimistes) ;
« nouvelles » : 10 questions écrites ensuite, jamais utilisées pour régler (la mesure honnête) ; 4 questions hors sujet.
                                   réglage top-1   nouvelles top-1 / top-3   hors sujet refusés
    MiniLM + mots-clés (retenu)        100 %            90 % / 100 %                4/4
    MiniLM sans mots-clés               46 %            70 % /  80 %                4/4
    CLIP multilingue + mots-clés        93 %            60 % / 100 %                0/4
Avec CLIP, toutes les similarités sont entre 0,85 et 0,95 : impossible de reconnaître une question hors sujet.
"""

from __future__ import annotations

import argparse
import os
import time

os.environ.setdefault("HF_HUB_OFFLINE", "1")

from sentence_transformers import SentenceTransformer  # noqa: E402

import assistant  # noqa: E402
from diagnostic import MODELE_PHRASES, MODELE_TEXTE_MULTILINGUE  # noqa: E402

QUESTIONS = [
    ("j'ai fait tomber de l'huile sur mon pantalon", "tache-gras"),
    ("comment enlever une tache de sauce tomate grasse", "tache-gras"),
    ("du café renversé sur ma chemise blanche", "tache-vin-cafe"),
    ("tache de vin rouge sur la nappe en coton", "tache-vin-cafe"),
    ("je me suis coupé et il y a du sang sur mon tee shirt", "tache-sang"),
    ("mon jean est troué au genou", "trou-jean"),
    ("jean déchiré entre les jambes, ça se répare ?", "trou-jean"),
    ("la couture de ma chemise s'est ouverte sous le bras", "dechirure-couture"),
    ("mon zip ne ferme plus", "fermeture-eclair"),
    ("la fermeture éclair de mon blouson est coincée", "fermeture-eclair"),
    ("j'ai perdu un bouton de ma veste", "bouton"),
    ("comment recoudre un bouton solidement", "bouton"),
    ("mon pantalon est trop long", "ourlet"),
    ("l'ourlet de ma jupe se découd", "ourlet"),
    ("il y a un trou de mite dans mon pull en laine", "maille-tricot"),
    ("une maille a filé sur mon gilet", "maille-tricot"),
    ("mon pull fait plein de petites boules", "bouloches"),
    ("à quelle température laver un pull en cachemire", "entretien-laine"),
    ("mon pull a rétréci à la machine", "retrecissement"),
    ("comment repasser une chemise en lin froissée", "entretien-coton-lin"),
    ("l'intérieur de mon manteau est déchiré", "doublure"),
    ("ma robe est trop large à la taille", "ajustement"),
    ("réparer une robe en soie", "textile-delicat"),
    ("que faire d'un vieux jean que je ne porte plus", "upcycling"),
    ("ça vaut le coup de réparer ou je le donne ?", "reparer-ou-pas"),
    ("pourquoi réparer au lieu d'acheter neuf", "impact"),
    ("combien coûte une retouche et quoi apporter", "preparer-rdv"),
    ("à quoi sert le QR code cousu sur ma veste", "passeport"),
]

# Questions écrites après le réglage des mots-clés, jamais utilisées pour l'ajuster : mesure honnête.
QUESTIONS_NOUVELLES = [
    ("j'ai une trace d'huile de friture sur mon sweat", "tache-gras"),
    ("le curseur de ma fermeture s'est cassé", "fermeture-eclair"),
    ("mon chat a fait un trou dans mon cardigan", "maille-tricot"),
    ("faut-il laver à l'eau chaude une tache de sang", "tache-sang"),
    ("ma chemise est trop grande aux épaules", "ajustement"),
    ("la poche de ma veste est trouée à l'intérieur", "doublure"),
    ("je voudrais faire un short avec mon jean", "upcycling"),
    ("je peux revendre une pièce réparée avec sa garantie ?", "passeport"),
    ("mon pantalon a craqué à la couture des fesses", "dechirure-couture"),
    ("comment entretenir une robe en dentelle", "textile-delicat"),
]

HORS_SUJET = [
    "quelle est la capitale de l'Australie",
    "donne-moi une recette de couscous",
    "qui a gagné le match hier soir",
    "écris un poème sur la mer",
]


def encodeur_de(nom: str):
    modele = SentenceTransformer(nom)
    return lambda textes: modele.encode(textes, convert_to_tensor=True, normalize_embeddings=True)


def main() -> None:
    parametres = argparse.ArgumentParser()
    parametres.add_argument("--clip", action="store_true", help="encodeur CLIP multilingue au lieu de MiniLM")
    parametres.add_argument("--semantique", action="store_true", help="sans les mots-clés")
    args = parametres.parse_args()

    if args.semantique:
        assistant.POIDS_SEMANTIQUE = 1.0

    nom = MODELE_TEXTE_MULTILINGUE if args.clip else MODELE_PHRASES
    debut = time.perf_counter()
    index = assistant.IndexConnaissances(assistant.charger_fiches(), encodeur_de(nom))
    print(f"Encodeur : {nom} ({time.perf_counter() - debut:.1f} s pour charger et indexer {len(index.fiches)} fiches)")

    top1 = top3 = refuses_a_tort = 0
    durees = []
    for question, attendue in QUESTIONS:
        debut = time.perf_counter()
        passages, pertinent = index.rechercher(question)
        durees.append(time.perf_counter() - debut)
        ids = [p.fiche.id for p in passages]
        top1 += ids[0] == attendue
        top3 += attendue in ids
        refuses_a_tort += not pertinent
        if ids[0] != attendue:
            print(f"  ✗ « {question} » -> {ids} (attendu {attendue}, sém. {passages[0].semantique:.2f})")

    bien_refuses = 0
    for question in HORS_SUJET:
        passages, pertinent = index.rechercher(question)
        bien_refuses += not pertinent
        if pertinent:
            print(f"  ✗ hors sujet accepté : « {question} » -> {passages[0].fiche.id} (sém. {passages[0].semantique:.2f}, mots {passages[0].mots_trouves})")

    nouvelles_top1 = nouvelles_top3 = 0
    for question, attendue in QUESTIONS_NOUVELLES:
        ids = [p.fiche.id for p in index.rechercher(question)[0]]
        nouvelles_top1 += ids[0] == attendue
        nouvelles_top3 += attendue in ids
        if ids[0] != attendue:
            print(f"  ✗ (nouvelle) « {question} » -> {ids} (attendu {attendue})")

    n = len(QUESTIONS)
    m = len(QUESTIONS_NOUVELLES)
    print(f"\nTop-1 : {top1}/{n} = {top1 / n:.0%}   Top-3 : {top3}/{n} = {top3 / n:.0%}")
    print(f"Nouvelles questions : top-1 {nouvelles_top1}/{m} = {nouvelles_top1 / m:.0%}   top-3 {nouvelles_top3}/{m} = {nouvelles_top3 / m:.0%}")
    print(f"Questions utiles refusées à tort : {refuses_a_tort}/{n}")
    print(f"Hors sujet bien refusées : {bien_refuses}/{len(HORS_SUJET)}")
    print(f"Durée moyenne d'une recherche : {sum(durees) / n * 1000:.0f} ms")


if __name__ == "__main__":
    main()
