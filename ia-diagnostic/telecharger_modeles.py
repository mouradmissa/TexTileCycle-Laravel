"""
Télécharge une fois les trois modèles d'IA du module Ateliers (environ 1,6 Go au total, depuis Hugging Face).

Le service démarre ensuite hors ligne (lancer.ps1 active HF_HUB_OFFLINE=1) : sans ce téléchargement préalable,
il s'arrête au démarrage sur un nouveau PC. Relancer le script ne retélécharge rien (cache Hugging Face).

    "%USERPROFILE%\\.textilecycle-ia\\venv\\Scripts\\python.exe" telecharger_modeles.py
"""

from __future__ import annotations

import os
import sys
import time

# Le téléchargement a besoin d'Internet, même si la variable est restée active dans le terminal.
os.environ.pop("HF_HUB_OFFLINE", None)

from sentence_transformers import SentenceTransformer  # noqa: E402

from diagnostic import MODELE_CLIP, MODELE_PHRASES, MODELE_TEXTE_MULTILINGUE  # noqa: E402

MODELES = [
    (MODELE_CLIP, "diagnostic par photo : image et phrases anglaises", "≈ 580 Mo"),
    (MODELE_TEXTE_MULTILINGUE, "diagnostic par photo : précision du client en français", "≈ 520 Mo"),
    (MODELE_PHRASES, "assistant de réparation : recherche dans les fiches", "≈ 460 Mo"),
]


def main() -> int:
    erreurs = 0
    for nom, usage, taille in MODELES:
        print(f"→ {nom} ({taille}) — {usage}")
        debut = time.perf_counter()
        try:
            modele = SentenceTransformer(nom)
            modele.encode(["test"])          # vérifie que le modèle se charge réellement
        except Exception as erreur:          # noqa: BLE001 - on signale et on continue avec les suivants
            erreurs += 1
            print(f"  ✗ échec : {erreur}")
            continue
        print(f"  ✓ prêt en {time.perf_counter() - debut:.0f} s")

    if erreurs:
        print(f"\n{erreurs} modèle(s) non téléchargé(s) : vérifiez la connexion et la place disque, puis relancez.")
        return 1

    print("\nTous les modèles sont prêts : lancez demarrer.ps1 (ou ia-diagnostic\\lancer.ps1).")
    return 0


if __name__ == "__main__":
    sys.exit(main())
