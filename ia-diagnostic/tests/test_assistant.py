"""
Recherche de l'assistant (RAG) avec un faux encodeur : rapide, sans modèle. Vérifie le mélange sens + mots-clés,
le refus des questions hors sujet, et la route HTTP (jeton, validation, réponse).
"""

import sys
from pathlib import Path

import pytest
import torch
from fastapi.testclient import TestClient

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

import assistant  # noqa: E402
import service  # noqa: E402

JETON = "jeton-de-test-assez-long"

FICHES = [
    assistant.Fiche("fermeture", "Fermeture éclair bloquée", "Frottez les dents avec un crayon.", ("fermeture", "eclair", "zip"), ("Changer une fermeture",)),
    assistant.Fiche("tache", "Tache de gras", "Talc puis liquide vaisselle.", ("tache", "gras", "huile"), ()),
    assistant.Fiche("bouton", "Bouton perdu", "Recoudre avec une tige.", ("bouton",), ("Remplacement de boutons",)),
]


def faux_encodeur(similarites: dict[str, list[float]]):
    """Les fiches sont les vecteurs de base ; une question reçoit les similarités indiquées (0 par défaut)."""
    def encoder(textes):
        if len(textes) == len(FICHES) and textes[0].startswith(FICHES[0].titre):
            return torch.eye(len(FICHES))
        return torch.tensor([similarites.get(t, [0.0] * len(FICHES)) for t in textes])
    return encoder


def test_les_mots_cles_departagent_quand_le_sens_hesite():
    index = assistant.IndexConnaissances(FICHES, faux_encodeur({"ma fermeture coince": [0.40, 0.45, 0.42]}))

    passages, pertinent = index.rechercher("ma fermeture coince")

    assert passages[0].fiche.id == "fermeture"
    assert passages[0].mots_trouves == 1
    assert pertinent


def test_une_question_sans_mot_cle_et_lointaine_est_hors_sujet():
    index = assistant.IndexConnaissances(FICHES, faux_encodeur({"capitale de l'Australie": [0.20, 0.30, 0.10]}))

    _, pertinent = index.rechercher("capitale de l'Australie")

    assert not pertinent


def test_une_question_proche_par_le_sens_est_acceptee_sans_mot_cle():
    index = assistant.IndexConnaissances(FICHES, faux_encodeur({"ça glisse plus": [0.80, 0.10, 0.10]}))

    passages, pertinent = index.rechercher("ça glisse plus")

    assert passages[0].fiche.id == "fermeture" and pertinent


def test_les_racines_ignorent_accents_pluriels_et_mots_vides():
    assert assistant.indices_de("Comment enlever des Tâches sur mes boutons ?") == {"enle", "tach", "bout"}


def test_les_fiches_du_depot_sont_valides():
    fiches = assistant.charger_fiches()

    assert len(fiches) >= 15
    assert all(f.titre and len(f.texte) > 80 and f.mots for f in fiches)


@pytest.fixture
def client(monkeypatch):
    monkeypatch.setenv("IA_DIAGNOSTIC_JETON", JETON)
    index = assistant.IndexConnaissances(FICHES, faux_encodeur({"ma fermeture coince": [0.4, 0.1, 0.1]}))
    service.app.dependency_overrides[service.obtenir_index] = lambda: index
    yield TestClient(service.app)
    service.app.dependency_overrides.clear()


def entetes():
    return {"Authorization": f"Bearer {JETON}"}


def test_la_route_rend_les_fiches_classees(client):
    reponse = client.post("/assistant/recherche", json={"question": "ma fermeture coince"}, headers=entetes())

    assert reponse.status_code == 200
    donnees = reponse.json()
    assert donnees["pertinent"] is True
    assert [p["id"] for p in donnees["passages"]] == ["fermeture", "tache", "bouton"]
    assert donnees["passages"][0]["services"] == ["Changer une fermeture"]


def test_la_route_exige_le_jeton(client):
    assert client.post("/assistant/recherche", json={"question": "ma fermeture coince"}).status_code == 401


@pytest.mark.parametrize("question", ["", "ok", "x" * 301])
def test_la_question_est_controlee(client, question):
    assert client.post("/assistant/recherche", json={"question": question}, headers=entetes()).status_code == 422


def test_sans_index_charge_la_route_repond_503(monkeypatch):
    monkeypatch.setenv("IA_DIAGNOSTIC_JETON", JETON)
    service._etat.pop("index", None)

    reponse = TestClient(service.app).post("/assistant/recherche", json={"question": "ma fermeture coince"}, headers=entetes())

    assert reponse.status_code == 503
