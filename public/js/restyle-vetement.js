document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('restyle-modal');
  const formulaire = modal?.querySelector('[data-restyle-form]');
  if (!modal || !formulaire) return;

  const jeton = formulaire.querySelector('input[name="_token"]')?.value;
  const urlLibre = formulaire.dataset.restyleUrl;
  const urlGuide = formulaire.dataset.restyleGuideUrl;
  const message = modal.querySelector('[data-restyle-message]');
  const resultat = modal.querySelector('[data-restyle-resultat]');
  const avant = modal.querySelector('[data-restyle-avant]');
  const apres = modal.querySelector('[data-restyle-apres]');
  const instruction = modal.querySelector('[data-restyle-instruction]');
  const apercu = modal.querySelector('[data-restyle-apercu]');
  const champPhoto = modal.querySelector('[data-restyle-photo]');
  const bouton = modal.querySelector('[data-restyle-envoyer]');
  const boutonGuide = modal.querySelector('[data-restyle-guide-envoyer]');
  const blocGuide = modal.querySelector('[data-restyle-guide]');
  const titreGuide = modal.querySelector('[data-restyle-guide-titre]');
  const descriptionGuide = modal.querySelector('[data-restyle-guide-description]');
  const etatAvantGuide = modal.querySelector('[data-restyle-guide-etat-avant]');
  const etatApresGuide = modal.querySelector('[data-restyle-guide-etat-apres]');
  let urlCourante = urlLibre;
  let apercuLocal = '';
  let jetonRendu = '';
  let imageApres = '';

  const ouvrir = () => {
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    if (window.lucide) lucide.createIcons();
  };

  const dire = (texte, erreur) => {
    if (!message) return;
    message.hidden = !texte;
    message.textContent = texte || '';
    message.classList.toggle('is-erreur', Boolean(erreur));
  };

  const montrerOrigine = (src) => {
    apercuLocal = src || '';
    if (!apercu) return;
    apercu.hidden = !src;
    if (src) apercu.src = src;
  };

  const reinitialiser = () => {
    urlCourante = urlLibre;
    if (instruction) instruction.value = '';
    if (champPhoto) champPhoto.value = '';
    montrerOrigine('');
    dire('', false);
    if (resultat) resultat.hidden = true;
    jetonRendu = '';
    imageApres = '';
    if (blocGuide) blocGuide.hidden = true;
    if (boutonGuide) {
      boutonGuide.hidden = false;
      boutonGuide.disabled = false;
      boutonGuide.textContent = 'Enregistrer le guide';
    }
  };

  document.querySelectorAll('[data-open-modal="restyle-modal"]').forEach((declencheur) => {
    declencheur.addEventListener('click', () => reinitialiser());
  });

  document.querySelectorAll('[data-restyle-piece]').forEach((declencheur) => {
    declencheur.addEventListener('click', () => {
      reinitialiser();
      urlCourante = declencheur.dataset.restylePiece || urlLibre;
      montrerOrigine(declencheur.dataset.restylePhoto || '');
      if (instruction && declencheur.dataset.restyleInstruction) {
        instruction.value = declencheur.dataset.restyleInstruction;
      }
      ouvrir();
    });
  });

  modal.querySelectorAll('[data-restyle-suggestion]').forEach((suggestion) => {
    suggestion.addEventListener('click', () => {
      if (!instruction) return;
      instruction.value = suggestion.textContent.trim();
      instruction.focus();
    });
  });

  champPhoto?.addEventListener('change', () => {
    const fichier = champPhoto.files?.[0];
    if (!fichier) return;
    urlCourante = urlLibre;
    if (apercuLocal.startsWith('blob:')) URL.revokeObjectURL(apercuLocal);
    montrerOrigine(URL.createObjectURL(fichier));
  });

  formulaire.addEventListener('submit', async (event) => {
    event.preventDefault();
    const texte = instruction?.value.trim() || '';
    if (texte.length < 8) {
      dire('Décrivez la réparation en quelques mots.', true);
      return;
    }

    const depuisPiece = urlCourante !== urlLibre;
    if (!depuisPiece && !champPhoto?.files?.[0] && !apercuLocal) {
      dire('Choisissez la photo d\'origine du vêtement.', true);
      return;
    }

    const corps = new FormData();
    corps.append('_token', jeton);
    corps.append('instruction', texte);
    if (!depuisPiece && champPhoto?.files?.[0]) {
      corps.append('photo', champPhoto.files[0]);
    }

    dire('Envoi de la photo et de l\'instruction…', false);
    if (bouton) bouton.disabled = true;
    try {
      let data = {};
      for (let essai = 0; essai < 8; essai += 1) {
        const reponse = await fetch(urlCourante, {
          method: 'POST',
          body: corps,
          headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': jeton,
          },
        });
        data = await reponse.json().catch(() => ({}));
        if (reponse.status === 202 && data.en_attente) {
          dire(data.message || 'Le générateur gratuit traite la photo…', false);
          await new Promise((resoudre) => setTimeout(resoudre, Math.min(data.relancer_dans || 12, 15) * 1000));
          continue;
        }
        if (!reponse.ok || !data.image) {
          dire(data.message || data.errors?.instruction?.[0] || data.errors?.photo?.[0] || 'L\'image n\'a pas pu être générée.', true);
          return;
        }
        break;
      }
      if (!data.image) {
        dire(data.message || 'Le générateur gratuit traite encore la photo. Relancez dans un instant.', false);
        return;
      }

      dire('', false);
      jetonRendu = data.jeton || '';
      imageApres = data.image || '';
      if (avant) avant.src = apercuLocal;
      if (apres) apres.src = imageApres;
      if (blocGuide) blocGuide.hidden = true;
      if (boutonGuide) {
        boutonGuide.hidden = false;
        boutonGuide.disabled = false;
        boutonGuide.textContent = 'Enregistrer le guide';
        boutonGuide.scrollIntoView({ block: 'nearest' });
      }
      if (resultat) {
        resultat.hidden = false;
        resultat.scrollIntoView({ block: 'nearest' });
      }
    } catch (error) {
      dire('Le restyle ne répond pas. Réessayez dans un instant.', true);
    } finally {
      if (bouton) bouton.disabled = false;
    }
  });

  boutonGuide?.addEventListener('click', async () => {
    const texte = instruction?.value.trim() || '';
    if (texte.length < 8 || (!jetonRendu && !imageApres)) {
      dire('Générez d\'abord l\'image de la réparation.', true);
      return;
    }

    const depuisPiece = urlCourante !== urlLibre;
    const corps = new FormData();
    corps.append('_token', jeton);
    corps.append('instruction', texte);
    if (jetonRendu) corps.append('jeton', jetonRendu);
    else corps.append('image_apres', imageApres);

    const identifiant = urlCourante.match(/\/mes-vetements\/([^/]+)\/restyle$/);
    if (depuisPiece && identifiant) {
      corps.append('vetement_id', identifiant[1]);
    } else if (champPhoto?.files?.[0]) {
      corps.append('photo', champPhoto.files[0]);
    } else {
      dire('Choisissez à nouveau la photo d\'origine.', true);
      return;
    }

    dire('Enregistrement du guide et envoi de l\'e-mail…', false);
    boutonGuide.disabled = true;
    try {
      const reponse = await fetch(urlGuide, {
        method: 'POST',
        body: corps,
        headers: {
          Accept: 'application/json',
          'X-CSRF-TOKEN': jeton,
        },
      });
      const data = await reponse.json().catch(() => ({}));
      if (!data.guide) {
        dire(data.message || data.errors?.instruction?.[0] || data.errors?.photo?.[0] || data.errors?.jeton?.[0] || 'Le guide n\'a pas pu être enregistré.', true);
        boutonGuide.disabled = false;
        return;
      }

      if (titreGuide) titreGuide.textContent = data.guide.titre || 'Guide de réparation';
      if (descriptionGuide) descriptionGuide.textContent = data.guide.description || '';
      if (etatAvantGuide) etatAvantGuide.textContent = data.guide.etat_avant || '';
      if (etatApresGuide) etatApresGuide.textContent = data.guide.etat_apres || '';
      if (blocGuide) {
        blocGuide.hidden = false;
        blocGuide.scrollIntoView({ block: 'nearest' });
      }
      dire(data.message || 'Guide enregistré.', !data.envoye);
      boutonGuide.hidden = false;
      if (data.envoye) {
        boutonGuide.disabled = true;
        boutonGuide.textContent = 'Guide envoyé';
      } else {
        boutonGuide.disabled = false;
      }
    } catch (error) {
      dire('L\'envoi du guide ne répond pas. Réessayez dans un instant.', true);
      boutonGuide.disabled = false;
    }
  });
});
