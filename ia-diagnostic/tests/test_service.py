"""
Tests du microservice. Un faux moteur remplace CLIP : les tests sont rapides et ne chargent aucun modèle.
Le test d'intégration avec le vrai modèle est ignoré par défaut (IA_TEST_MODELE=1 pour le lancer).
"""

import io
import json
import os
import sys
from pathlib import Path

import pytest
from fastapi.testclient import TestClient
from PIL import Image

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

import service  # noqa: E402
from diagnostic import Resultat  # noqa: E402

JETON = "jeton-de-test-assez-long"
SERVICES = [
    {"id": "652f000000000000000000a1", "nom": "Recoudre une déchirure", "description": "Réparer un trou."},
    {"id": "652f000000000000000000b2", "nom": "Changer une fermeture", "description": ""},
    {"id": "652f000000000000000000c3", "nom": "Reprise de tricot"},
    {"id": "652f000000000000000000d4", "nom": "Retouche denim"},
]


class FauxMoteur:
    def __init__(self):
        self.appels = []

    def diagnostiquer(self, image, services, texte=None, defaut=None):
        self.appels.append({"taille": image.size, "mode": image.mode, "services": services, "texte": texte, "defaut": defaut})
        return Resultat(
            vetements=[("jean", 0.7), ("pantalon", 0.2), ("jupe", 0.05), ("robe", 0.05)],
            defauts=[("trou", 0.6), ("déchirure", 0.3), ("tache", 0.1)],
            services=[(s.id, 1 / (rang + 1)) for rang, s in enumerate(services)],
            certain=True,
        )


def image(format_="JPEG", taille=(64, 48)) -> bytes:
    tampon = io.BytesIO()
    Image.new("RGB", taille, "navy").save(tampon, format=format_)
    return tampon.getvalue()


@pytest.fixture
def client(monkeypatch):
    monkeypatch.setenv("IA_DIAGNOSTIC_JETON", JETON)
    moteur = FauxMoteur()
    service.app.dependency_overrides[service.obtenir_moteur] = lambda: moteur
    client = TestClient(service.app)
    client.moteur = moteur
    yield client
    service.app.dependency_overrides.clear()


def envoyer(client, photo=None, services=None, texte=None, jeton=JETON, nom="photo.jpg", type_="image/jpeg", defaut=None):
    donnees = {"services": json.dumps(SERVICES if services is None else services)}
    if texte is not None:
        donnees["texte"] = texte
    if defaut is not None:
        donnees["defaut"] = defaut
    entetes = {"Authorization": f"Bearer {jeton}"} if jeton else {}
    return client.post("/diagnostic", data=donnees, headers=entetes,
                       files={"photo": (nom, image() if photo is None else photo, type_)})


# ---------------------------------------------------------------- accès

def test_sante_ne_demande_pas_de_jeton_et_n_expose_rien_de_sensible(client):
    reponse = client.get("/sante")
    assert reponse.status_code == 200
    assert set(reponse.json()) == {"statut", "modele", "version", "assistant"}


@pytest.mark.parametrize("jeton", [None, "mauvais-jeton-de-test", JETON + "x"])
def test_diagnostic_refuse_sans_le_bon_jeton(client, jeton):
    reponse = envoyer(client, jeton=jeton)
    assert reponse.status_code == 401
    assert reponse.json()["detail"] == "Jeton manquant ou invalide."
    assert client.moteur.appels == []


def test_diagnostic_repond_503_tant_que_le_modele_n_est_pas_charge(monkeypatch):
    monkeypatch.setenv("IA_DIAGNOSTIC_JETON", JETON)
    reponse = envoyer(TestClient(service.app))
    assert reponse.status_code == 503


def test_le_service_refuse_de_demarrer_sans_jeton(monkeypatch):
    monkeypatch.delenv("IA_DIAGNOSTIC_JETON", raising=False)
    with pytest.raises(RuntimeError, match="IA_DIAGNOSTIC_JETON"):
        with TestClient(service.app):
            pass


# ---------------------------------------------------------------- diagnostic

def test_diagnostic_valide_renvoie_les_trois_meilleurs_resultats(client):
    reponse = envoyer(client, texte="  trou près de la poche  ")
    assert reponse.status_code == 200
    corps = reponse.json()
    assert [v["cle"] for v in corps["vetements"]] == ["jean", "pantalon", "jupe"]
    assert corps["defauts"][0] == {"cle": "trou", "probabilite": 0.6}
    assert [s["id"] for s in corps["services"]] == [s["id"] for s in SERVICES[:3]]
    assert corps["certain"] is True and corps["version"] == service.VERSION

    appel = client.moteur.appels[0]
    assert appel["texte"] == "trou près de la poche"
    assert appel["mode"] == "RGB"
    assert [s.nom for s in appel["services"]] == [s["nom"] for s in SERVICES]


def test_le_defaut_choisi_par_le_client_est_transmis(client):
    assert envoyer(client, defaut="fermeture cassée").status_code == 200
    assert client.moteur.appels[0]["defaut"] == "fermeture cassée"


def test_un_defaut_inconnu_est_refuse(client):
    reponse = envoyer(client, defaut="explosion")
    assert reponse.status_code == 422
    assert reponse.json()["detail"] == "Le défaut choisi est inconnu."
    assert client.moteur.appels == []


def test_un_texte_vide_n_est_pas_transmis(client):
    envoyer(client, texte="   ")
    assert client.moteur.appels[0]["texte"] is None


@pytest.mark.parametrize("format_, type_", [("PNG", "image/png"), ("WEBP", "image/webp")])
def test_png_et_webp_sont_acceptes(client, format_, type_):
    assert envoyer(client, photo=image(format_), type_=type_).status_code == 200


# ---------------------------------------------------------------- contrôles de la photo

def test_photo_trop_lourde(client, monkeypatch):
    monkeypatch.setattr(service, "TAILLE_MAX", 100)
    reponse = envoyer(client)
    assert reponse.status_code == 413
    assert client.moteur.appels == []


def test_format_non_autorise(client):
    reponse = envoyer(client, photo=image("GIF"), nom="photo.gif", type_="image/gif")
    assert reponse.status_code == 415


def test_fichier_qui_n_est_pas_une_image(client):
    reponse = envoyer(client, photo=b"<?php echo 'pas une image'; ?>", nom="photo.jpg")
    assert reponse.status_code == 422
    assert reponse.json()["detail"] == "Le fichier envoyé n'est pas une image lisible."


def test_image_tronquee(client):
    reponse = envoyer(client, photo=image()[:200])
    assert reponse.status_code == 422


def test_image_avec_trop_de_pixels(client, monkeypatch):
    monkeypatch.setattr(service.Image, "MAX_IMAGE_PIXELS", 1000)
    reponse = envoyer(client, photo=image(taille=(100, 100)))
    assert reponse.status_code == 422


# ---------------------------------------------------------------- contrôles du catalogue et du texte

@pytest.mark.parametrize("services", [
    [],
    [{"id": "a"}],
    [{"id": "", "nom": "Ourlet"}],
    [{"id": "a", "nom": "x" * 121}],
    [{"id": "a", "nom": "Ourlet"}] * 2,
    [{"id": str(i), "nom": "Service"} for i in range(201)],
])
def test_catalogue_invalide(client, services):
    assert envoyer(client, services=services).status_code == 422
    assert client.moteur.appels == []


def test_catalogue_qui_n_est_pas_du_json(client):
    reponse = client.post("/diagnostic", data={"services": "pas du json"}, headers={"Authorization": f"Bearer {JETON}"},
                          files={"photo": ("photo.jpg", image(), "image/jpeg")})
    assert reponse.status_code == 422


def test_texte_trop_long(client):
    reponse = envoyer(client, texte="a" * 301)
    assert reponse.status_code == 422
    assert "300 caractères" in reponse.json()["detail"]


# ---------------------------------------------------------------- vrai modèle (lent, ignoré par défaut)

@pytest.mark.skipif(os.environ.get("IA_TEST_MODELE") != "1", reason="IA_TEST_MODELE=1 pour tester avec le vrai CLIP")
def test_integration_avec_le_vrai_modele(monkeypatch):
    monkeypatch.setenv("IA_DIAGNOSTIC_JETON", JETON)
    with TestClient(service.app) as client:
        reponse = envoyer(client)
    assert reponse.status_code == 200
    corps = reponse.json()
    assert len(corps["services"]) == 3
    assert abs(sum(v["probabilite"] for v in corps["vetements"])) <= 1.0
