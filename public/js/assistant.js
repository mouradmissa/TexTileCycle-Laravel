// Assistant réparation : envoie la question sans recharger la page et ajoute l'échange au fil de la conversation.
// Aucune règle de validation ici : les erreurs viennent de AssistantRequest (422) et sont seulement affichées.
// La réponse arrive déjà mise en forme et échappée par Blade (vue partielle) ; le texte saisi n'est jamais injecté en HTML.
(() => {
  'use strict';

  const form = document.querySelector('[data-as-form]');
  const conversation = document.querySelector('[data-as-conversation]');
  if (!form || !conversation || !window.fetch) return;

  const champ = form.querySelector('[data-as-question]');
  const envoyerBouton = form.querySelector('[data-as-envoyer]');
  const erreur = form.querySelector('[data-as-erreur]');
  const alerte = form.querySelector('[data-as-alerte]');
  const exemples = form.querySelectorAll('[data-as-exemple]');
  let enCours = false;

  const icones = () => {
    if (window.lucide && typeof window.lucide.createIcons === 'function') window.lucide.createIcons();
  };

  const montrerErreur = (message) => {
    erreur.textContent = message || '';
    erreur.hidden = !message;
    if (message) {
      champ.setAttribute('aria-invalid', 'true');
      champ.setAttribute('aria-describedby', erreur.id);
    } else {
      champ.removeAttribute('aria-invalid');
      champ.removeAttribute('aria-describedby');
    }
  };

  const montrerAlerte = (message) => {
    alerte.replaceChildren();
    if (!message) return;
    const p = document.createElement('p');
    p.className = 'as-alerte';
    p.setAttribute('role', 'alert');
    p.textContent = message;
    alerte.append(p);
  };

  // Bulle provisoire pendant la recherche et la rédaction (quelques secondes).
  const bulleAttente = (question) => {
    const fragment = document.createDocumentFragment();

    const visiteur = document.createElement('li');
    visiteur.className = 'as-message as-message--visiteur';
    const bulleVisiteur = document.createElement('div');
    bulleVisiteur.className = 'as-bulle';
    const texte = document.createElement('p');
    texte.textContent = question;
    bulleVisiteur.append(texte);
    visiteur.append(bulleVisiteur);

    const attente = document.createElement('li');
    attente.className = 'as-message as-message--assistant as-message--attente';
    const bulle = document.createElement('div');
    bulle.className = 'as-bulle';
    const points = document.createElement('p');
    points.className = 'as-points';
    points.textContent = 'L\'assistant cherche dans les fiches…';
    bulle.append(points);
    attente.append(bulle);

    fragment.append(visiteur, attente);
    conversation.append(fragment);
    attente.scrollIntoView({ block: 'nearest' });

    return [visiteur, attente];
  };

  const occuper = (oui) => {
    enCours = oui;
    envoyerBouton.disabled = oui;
    exemples.forEach((bouton) => { bouton.disabled = oui; });
    form.setAttribute('aria-busy', oui ? 'true' : 'false');
  };

  const poser = async (question) => {
    if (enCours) return;

    montrerErreur('');
    montrerAlerte('');
    occuper(true);
    const provisoires = bulleAttente(question);

    const donnees = new FormData();
    donnees.append('_token', form.querySelector('input[name="_token"]').value);
    donnees.append('question', question);

    try {
      const reponse = await fetch(form.action, {
        method: 'POST',
        body: donnees,
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      });
      let corps = null;
      try {
        corps = await reponse.json();
      } catch (e) {
        corps = null;
      }

      provisoires.forEach((noeud) => noeud.remove());

      if (reponse.status === 200 && corps && typeof corps.html === 'string') {
        const doc = new DOMParser().parseFromString(corps.html, 'text/html');
        const noeuds = Array.from(doc.body.children, (noeud) => document.importNode(noeud, true));
        conversation.append(...noeuds);
        icones();
        champ.value = '';
        const derniere = noeuds[noeuds.length - 1];
        if (derniere) derniere.scrollIntoView({ block: 'nearest' });
        return;
      }

      if (reponse.status === 422 && corps && corps.errors) {
        montrerErreur((corps.errors.question || [])[0] || 'Votre question n\'est pas valide.');
        champ.focus();
        return;
      }

      const messages = {
        419: 'La page a expiré : rechargez-la pour poser une nouvelle question.',
        429: 'Trop de questions en une minute : patientez un instant.',
      };
      montrerAlerte(messages[reponse.status] || (corps && corps.message) || 'L\'assistant est indisponible pour le moment.');
    } catch (e) {
      provisoires.forEach((noeud) => noeud.remove());
      montrerAlerte('Connexion impossible : vérifiez votre réseau et réessayez.');
    } finally {
      occuper(false);
    }
  };

  form.addEventListener('submit', (evenement) => {
    evenement.preventDefault();
    // Bouton d'exemple : sa valeur est la question ; sinon, le texte du champ.
    const source = evenement.submitter;
    const question = source && source.hasAttribute('data-as-exemple') ? source.value : champ.value.trim();
    poser(question);
  });

  // Entrée envoie, Maj + Entrée passe à la ligne.
  champ.addEventListener('keydown', (evenement) => {
    if (evenement.key === 'Enter' && !evenement.shiftKey && !evenement.isComposing) {
      evenement.preventDefault();
      form.requestSubmit ? form.requestSubmit(envoyerBouton) : poser(champ.value.trim());
    }
  });
})();
