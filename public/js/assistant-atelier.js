// Assistant de l'espace atelier : ouvre le panneau flottant et envoie les questions sans recharger la page.
// Aucune règle de validation ici (AssistantAtelierRequest s'en charge) ; la réponse arrive déjà échappée par Blade.
(() => {
  'use strict';

  const icones = () => {
    if (window.lucide && typeof window.lucide.createIcons === 'function') window.lucide.createIcons();
  };

  /* ---------------------------------------------------------------- Bouton flottant */

  const flottant = document.querySelector('[data-aa-flottant]');
  if (flottant) {
    const bouton = flottant.querySelector('[data-aa-ouvrir]');
    const panneau = flottant.querySelector('[data-aa-panneau]');
    const champ = panneau.querySelector('[data-aa-question]');

    const ouvrir = (oui) => {
      panneau.hidden = !oui;
      bouton.setAttribute('aria-expanded', oui ? 'true' : 'false');
      flottant.classList.toggle('est-ouvert', oui);
      if (oui) champ.focus();
      else bouton.focus();
    };

    // Avec JavaScript, le lien vers la page devient un bouton qui ouvre le panneau.
    bouton.setAttribute('role', 'button');
    bouton.addEventListener('click', (e) => {
      e.preventDefault();
      ouvrir(panneau.hidden);
    });
    flottant.querySelector('[data-aa-fermer]').addEventListener('click', () => ouvrir(false));
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && !panneau.hidden) ouvrir(false);
    });
  }

  /* ---------------------------------------------------------------- Envoi des questions */

  document.querySelectorAll('[data-aa-form]').forEach((form) => {
    const conteneur = form.parentElement;
    const fil = conteneur.querySelector('[data-aa-fil]');
    const champ = form.querySelector('[data-aa-question]');
    const erreur = form.querySelector('[data-aa-erreur]');
    const alerte = form.querySelector('[data-aa-alerte]');
    const boutons = form.querySelectorAll('button');
    let enCours = false;

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
      alerte.textContent = message || '';
      alerte.hidden = !message;
    };

    const attente = (texte) => {
      const moi = document.createElement('li');
      moi.className = 'aa-msg aa-msg--moi';
      const p = document.createElement('p');
      p.textContent = texte;
      moi.append(p);
      const patience = document.createElement('li');
      patience.className = 'aa-msg aa-msg--assistant aa-msg--attente';
      const p2 = document.createElement('p');
      p2.textContent = 'Je regarde vos données…';
      patience.append(p2);
      fil.append(moi, patience);
      patience.scrollIntoView({ block: 'nearest' });
      return [moi, patience];
    };

    const envoyer = async (donnees, texteAffiche) => {
      if (enCours) return;
      enCours = true;
      boutons.forEach((b) => { b.disabled = true; });
      montrerErreur('');
      montrerAlerte('');
      const provisoires = attente(texteAffiche);

      try {
        const reponse = await fetch(form.action, {
          method: 'POST',
          body: donnees,
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          credentials: 'same-origin',
        });
        let corps = null;
        try { corps = await reponse.json(); } catch (e) { corps = null; }
        provisoires.forEach((n) => n.remove());

        if (reponse.status === 200 && corps && typeof corps.html === 'string') {
          const doc = new DOMParser().parseFromString(corps.html, 'text/html');
          const noeuds = Array.from(doc.body.children, (n) => document.importNode(n, true));
          fil.append(...noeuds);
          icones();
          champ.value = '';
          if (noeuds.length) noeuds[noeuds.length - 1].scrollIntoView({ block: 'nearest' });
          return;
        }

        if (reponse.status === 422 && corps && corps.errors) {
          montrerErreur((corps.errors.question || corps.errors.outil || [])[0] || 'Votre question n\'est pas valide.');
          champ.focus();
          return;
        }

        const messages = { 419: 'La page a expiré : rechargez-la.', 429: 'Trop de questions en une minute : patientez un instant.' };
        montrerAlerte(messages[reponse.status] || (corps && corps.message) || 'L\'assistant est indisponible pour le moment.');
      } catch (e) {
        provisoires.forEach((n) => n.remove());
        montrerAlerte('Connexion impossible : vérifiez votre réseau.');
      } finally {
        enCours = false;
        boutons.forEach((b) => { b.disabled = false; });
      }
    };

    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const donnees = new FormData();
      donnees.append('_token', form.querySelector('input[name="_token"]').value);
      const source = e.submitter;

      if (source && source.hasAttribute('data-aa-raccourci')) {
        donnees.append('outil', source.value);
        envoyer(donnees, source.dataset.libelle || source.textContent.trim());
        return;
      }

      donnees.append('question', champ.value.trim());
      envoyer(donnees, champ.value.trim() || '…');
    });
  });
})();
