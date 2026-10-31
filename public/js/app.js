document.addEventListener('DOMContentLoaded', () => {
  if (window.lucide) lucide.createIcons();

  const menuBtn = document.querySelector('[data-mobile-menu]');
  const nav = document.querySelector('[data-front-nav]');
  if (menuBtn && nav) {
    menuBtn.addEventListener('click', () => {
      const ouvert = nav.classList.toggle('open');
      menuBtn.setAttribute('aria-expanded', ouvert ? 'true' : 'false');
      menuBtn.setAttribute('aria-label', ouvert ? 'Fermer le menu' : 'Ouvrir le menu');
    });
  }

  const visible = (el) => el
    && !el.hidden
    && !el.disabled
    && el.getAttribute('aria-hidden') !== 'true'
    && el.getClientRects().length > 0;

  const focusables = (racine) => [...racine.querySelectorAll(
    'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
  )].filter(visible);

  const premierChamp = (dialogue) => {
    if (!dialogue) return null;
    return [...dialogue.querySelectorAll('input, select, textarea')].find(visible)
      || focusables(dialogue)[0]
      || dialogue;
  };

  const donnerFocus = (el) => {
    if (!el) return;
    if (!el.matches('a[href], button, input, select, textarea')) {
      el.setAttribute('tabindex', '-1');
    }
    setTimeout(() => el.focus(), 0);
  };

  let retourFocus = null;

  const dialogueDe = (backdrop) => backdrop?.querySelector('[role="dialog"]') || backdrop;

  const closeModal = (backdrop) => {
    if (!backdrop) return;
    backdrop.classList.remove('open');
    backdrop.setAttribute('aria-hidden', 'true');
    if (retourFocus && typeof retourFocus.focus === 'function') {
      retourFocus.focus();
    }
    retourFocus = null;
  };

  const openModal = (backdrop) => {
    if (!backdrop) return;
    retourFocus = document.activeElement;
    backdrop.classList.add('open');
    backdrop.setAttribute('aria-hidden', 'false');
    donnerFocus(premierChamp(dialogueDe(backdrop)));
  };

  document.querySelectorAll('[data-open-modal]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const id = btn.getAttribute('data-open-modal');
      openModal(document.getElementById(id));
    });
  });

  document.querySelectorAll('[data-close-modal]').forEach((el) => {
    el.addEventListener('click', () => {
      closeModal(el.closest('.modal-backdrop'));
    });
  });

  document.querySelectorAll('.modal-backdrop').forEach((backdrop) => {
    backdrop.addEventListener('click', (e) => {
      if (e.target === backdrop) closeModal(backdrop);
    });
  });

  document.addEventListener('keydown', (e) => {
    const ouvert = document.querySelector('.modal-backdrop.open');
    if (!ouvert) return;
    if (e.key === 'Escape') {
      e.preventDefault();
      closeModal(ouvert);
      return;
    }
    if (e.key !== 'Tab') return;
    const els = focusables(dialogueDe(ouvert));
    if (!els.length) return;
    const premier = els[0];
    const dernier = els[els.length - 1];
    if (e.shiftKey && document.activeElement === premier) {
      e.preventDefault();
      dernier.focus();
    } else if (!e.shiftKey && document.activeElement === dernier) {
      e.preventDefault();
      premier.focus();
    }
  });

  document.querySelectorAll('.modal-backdrop.open').forEach((backdrop) => {
    const dialogue = dialogueDe(backdrop);
    const invalide = [...(dialogue?.querySelectorAll('[aria-invalid="true"]') || [])].find(visible);
    donnerFocus(invalide || premierChamp(dialogue));
  });

  document.querySelectorAll('[data-condition-radios], [data-action-radios]').forEach((group) => {
    group.querySelectorAll('input[type="radio"]').forEach((input) => {
      input.addEventListener('change', () => {
        group.querySelectorAll('.radio, .action-choice').forEach((label) => label.classList.remove('active'));
        input.closest('.radio, .action-choice')?.classList.add('active');
      });
    });
  });

  document.querySelectorAll('[data-upload-zone]').forEach((zone) => {
    const input = zone.querySelector('[data-upload-input]');
    const label = zone.querySelector('[data-upload-label]');
    if (!input) return;

    zone.addEventListener('click', (e) => {
      if (e.target === input) return;
      if (zone.tagName === 'LABEL' && zone.contains(input)) return;
      input.click();
    });

    input.addEventListener('change', () => {
      const file = input.files?.[0];
      if (file && label) {
        label.textContent = file.name;
      }
      afficherApercu(zone, file);
      if (zone.dataset.analyseUrl && file) {
        analyserPhotoVetement(zone, file);
      }
    });

    zone.addEventListener('dragover', (e) => {
      e.preventDefault();
      zone.classList.add('dragover');
    });

    zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));

    zone.addEventListener('drop', (e) => {
      e.preventDefault();
      zone.classList.remove('dragover');
      const file = e.dataTransfer?.files?.[0];
      if (file && file.type.startsWith('image/')) {
        input.files = e.dataTransfer.files;
        input.dispatchEvent(new Event('change'));
      }
    });
  });
});

function afficherApercu(zone, file) {
  const preview = zone.querySelector('[data-upload-preview]');
  if (!preview) return;
  if (preview.dataset.objectUrl) {
    URL.revokeObjectURL(preview.dataset.objectUrl);
    delete preview.dataset.objectUrl;
  }
  if (!file) {
    preview.hidden = true;
    preview.removeAttribute('src');
    return;
  }
  const url = URL.createObjectURL(file);
  preview.dataset.objectUrl = url;
  preview.src = url;
  preview.hidden = false;
}

function panneauAnalyse(zone) {
  return zone.parentElement?.querySelector('[data-analyse-panel]') || null;
}

function afficherPanneauAnalyse(zone, { titre, resume, badge, defauts, variante }) {
  const panneau = panneauAnalyse(zone);
  if (!panneau) return;
  panneau.hidden = false;
  panneau.classList.remove('analyse-ia--reparable', 'analyse-ia--inutile', 'analyse-ia--irreparable', 'analyse-ia--hors_sujet', 'analyse-ia--erreur');
  if (variante) panneau.classList.add('analyse-ia--' + variante);
  const titreEl = panneau.querySelector('[data-analyse-title]');
  const resumeEl = panneau.querySelector('[data-analyse-resume]');
  const badgeEl = panneau.querySelector('[data-analyse-badge]');
  const liste = panneau.querySelector('[data-analyse-defauts]');
  if (titreEl) titreEl.textContent = titre || 'Analyse de la photo';
  if (resumeEl) resumeEl.textContent = resume || '';
  if (badgeEl) badgeEl.textContent = badge || '';
  if (liste) {
    liste.replaceChildren();
    (defauts || []).forEach((defaut) => {
      const item = document.createElement('li');
      item.textContent = defaut;
      liste.appendChild(item);
    });
  }
}

function choisirRadio(form, name, value) {
  const radios = form.querySelectorAll(`input[type="radio"][name="${name}"]`);
  radios.forEach((radio) => {
    if (radio.value === value) {
      radio.checked = true;
      radio.dispatchEvent(new Event('change', { bubbles: true }));
    }
  });
}

function appliquerAnalyseAuFormulaire(form, data) {
  if (!data || data.est_vetement === false) return;

  const type = form.querySelector('[name="type"]');
  if (type && data.type && [...type.options].some((option) => option.value === data.type)) {
    type.value = data.type;
  }

  const matiere = form.querySelector('[name="material"]');
  if (matiere && data.matiere) {
    matiere.value = data.matiere;
  }

  if (data.etat) choisirRadio(form, 'condition_label', data.etat);
  if (data.recommandation === 'reparation' || data.recommandation === 'don') {
    choisirRadio(form, 'intended_action', data.recommandation);
  }

  const description = form.querySelector('[name="description"]');
  if (description && !description.value.trim() && Array.isArray(data.defauts) && data.defauts.length) {
    description.value = data.defauts.join('. ');
  }
}

async function analyserPhotoVetement(zone, file) {
  const form = zone.closest('form');
  const jeton = form?.querySelector('input[name="_token"]')?.value;
  if (!form || !jeton) return;

  if (!file.type.startsWith('image/') || file.size > 5 * 1024 * 1024) {
    afficherPanneauAnalyse(zone, {
      titre: 'Photo refusée',
      resume: 'Utilisez une image JPG, PNG ou WebP de 5 Mo maximum.',
      variante: 'erreur',
    });
    return;
  }

  if (zone._analyseEnCours) zone._analyseEnCours.abort();
  const controleur = new AbortController();
  zone._analyseEnCours = controleur;

  afficherPanneauAnalyse(zone, {
    titre: 'Analyse en cours',
    resume: 'Lecture de la photo pour estimer l\'état et la possibilité de réparation…',
    variante: 'hors_sujet',
  });

  const corps = new FormData();
  corps.append('photo', file);
  corps.append('_token', jeton);

  try {
    const reponse = await fetch(zone.dataset.analyseUrl, {
      method: 'POST',
      body: corps,
      headers: {
        Accept: 'application/json',
        'X-CSRF-TOKEN': jeton,
      },
      signal: controleur.signal,
    });
    const data = await reponse.json().catch(() => ({}));
    if (!reponse.ok || data.disponible === false) {
      const message = data.message || data.errors?.photo?.[0] || 'L\'analyse de la photo a échoué.';
      afficherPanneauAnalyse(zone, { titre: 'Analyse indisponible', resume: message, variante: 'erreur' });
      return;
    }

    afficherPanneauAnalyse(zone, {
      titre: data.verdict_libelle || 'Analyse de la photo',
      resume: data.resume || '',
      badge: data.recommandation_libelle || '',
      defauts: data.defauts || [],
      variante: data.verdict || 'hors_sujet',
    });
    appliquerAnalyseAuFormulaire(form, data);
  } catch (error) {
    if (error.name === 'AbortError') return;
    afficherPanneauAnalyse(zone, {
      titre: 'Analyse indisponible',
      resume: 'La photo n\'a pas pu être analysée. Vous pouvez tout de même déclarer la pièce.',
      variante: 'erreur',
    });
  }
}

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-chat-vetement]').forEach(initialiserChatVetement);
});

function initialiserChatVetement(racine) {
  const panneau = racine.querySelector('[data-chat-panel]');
  const bascule = racine.querySelector('[data-chat-toggle]');
  const fermer = racine.querySelector('[data-chat-fermer]');
  const formulaire = racine.querySelector('[data-chat-form]');
  const messages = racine.querySelector('[data-chat-messages]');
  const champ = racine.querySelector('[data-chat-input]');
  const jeton = formulaire?.querySelector('input[name="_token"]')?.value;
  const historique = [];

  const ouvrir = (visible) => {
    if (!panneau || !bascule) return;
    panneau.hidden = !visible;
    bascule.setAttribute('aria-expanded', visible ? 'true' : 'false');
    if (visible) champ?.focus();
  };

  bascule?.addEventListener('click', () => ouvrir(panneau.hidden));
  fermer?.addEventListener('click', () => ouvrir(false));

  const ajouterBulle = (texte, cote) => {
    const bulle = document.createElement('p');
    bulle.className = 'chat-vetement-bulle chat-vetement-bulle--' + cote;
    bulle.textContent = texte;
    messages?.appendChild(bulle);
    if (messages) messages.scrollTop = messages.scrollHeight;
    return bulle;
  };

  formulaire?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const texte = champ?.value.trim();
    if (!texte || !jeton || !racine.dataset.chatUrl) return;

    ajouterBulle(texte, 'user');
    champ.value = '';
    champ.disabled = true;
    const attente = ajouterBulle('Réponse en cours…', 'bot');

    try {
      const reponse = await fetch(racine.dataset.chatUrl, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': jeton,
        },
        body: JSON.stringify({ message: texte, historique }),
      });
      const data = await reponse.json().catch(() => ({}));
      const contenu = data.reponse || data.message || 'Le conseiller textile est indisponible.';
      attente.textContent = contenu;
      historique.push({ role: 'user', contenu: texte });
      historique.push({ role: 'assistant', contenu });
      if (historique.length > 6) historique.splice(0, historique.length - 6);
    } catch (error) {
      attente.textContent = 'Le conseiller textile ne répond pas. Réessayez dans un instant.';
    } finally {
      champ.disabled = false;
      champ.focus();
    }
  });
}
