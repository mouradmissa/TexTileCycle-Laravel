/* IA du module Rendez-vous : créneaux suggérés dans le formulaire et assistant de réservation. */
(function () {
  'use strict';

  const AFFLUENCE = { faible: 'Affluence faible', moyenne: 'Affluence moyenne', forte: 'Journée chargée' };

  const jetonCsrf = () =>
    document.querySelector('meta[name="csrf-token"]')?.content
    || document.querySelector('input[name="_token"]')?.value
    || '';

  const element = (balise, classe, texte) => {
    const noeud = document.createElement(balise);
    if (classe) noeud.className = classe;
    if (texte !== undefined) noeud.textContent = texte;
    return noeud;
  };

  const premiereErreur = (data, defaut) =>
    data?.message && !data.errors ? data.message
      : Object.values(data?.errors || {})[0]?.[0] || data?.message || defaut;

  /* ------------------------------------------------------------------
   |  Formulaire : bouton « Suggérer un créneau »
   |------------------------------------------------------------------ */
  function initialiserCreneaux(bloc) {
    const bouton = bloc.querySelector('[data-creneaux-ia-bouton]');
    const liste = bloc.querySelector('[data-creneaux-ia-liste]');
    const duree = bloc.querySelector('[data-creneaux-ia-duree]');
    const message = bloc.querySelector('[data-creneaux-ia-message]');
    const champ = (id) => document.getElementById(id);

    const informer = (texte, erreur = false) => {
      message.hidden = !texte;
      message.textContent = texte || '';
      message.classList.toggle('is-erreur', erreur);
    };

    const appliquer = (creneau, carte) => {
      const date = champ('rdv-date');
      const selectDuree = champ('rdv-duree');
      if (date) date.value = creneau.date;
      if (selectDuree) {
        if (![...selectDuree.options].some((o) => o.value === String(creneau.duree))) {
          selectDuree.add(new Option(creneau.duree_libelle, creneau.duree));
        }
        selectDuree.value = String(creneau.duree);
      }
      liste.querySelectorAll('.creneau-ia').forEach((c) => c.classList.remove('is-choisi'));
      carte.classList.add('is-choisi');
      informer('Créneau appliqué : ' + creneau.libelle + ' (' + creneau.duree_libelle + '). Vérifiez puis confirmez le rendez-vous.');
    };

    bouton?.addEventListener('click', async () => {
      const atelier = champ('rdv-atelier')?.value;
      if (!atelier) {
        informer('Choisissez d\'abord un atelier.', true);
        return;
      }

      const params = new URLSearchParams({ atelier_id: atelier });
      const optionnels = { service_id: 'rdv-service', vetement_id: 'rdv-vetement', commentaire: 'rdv-commentaire' };
      Object.entries(optionnels).forEach(([nom, id]) => {
        const valeur = champ(id)?.value?.trim();
        if (valeur) params.set(nom, valeur);
      });
      if (bloc.dataset.ignorer) params.set('ignorer', bloc.dataset.ignorer);

      bouton.disabled = true;
      liste.replaceChildren();
      duree.hidden = true;
      informer('Analyse du planning de l\'atelier…');

      try {
        const reponse = await fetch(bloc.dataset.url + '?' + params, { headers: { Accept: 'application/json' } });
        const data = await reponse.json().catch(() => ({}));
        if (!reponse.ok) {
          informer(premiereErreur(data, 'Suggestion indisponible pour le moment.'), true);
          return;
        }

        duree.hidden = false;
        duree.textContent = 'Durée estimée : ' + data.duree_libelle + ' — ' + (data.duree_raisons || []).join(' · ');

        if (!data.creneaux?.length) {
          informer('Aucun créneau libre dans les 14 prochains jours pour cet atelier.', true);
          return;
        }

        data.creneaux.forEach((creneau, rang) => {
          const carte = element('button', 'creneau-ia');
          carte.type = 'button';
          carte.append(element('strong', '', (rang === 0 ? '★ ' : '') + creneau.libelle));
          const ligne = element('span', 'creneau-ia__ligne');
          ligne.append(element('span', '', creneau.duree_libelle), element('span', 'affluence-ia affluence-ia--' + creneau.affluence, AFFLUENCE[creneau.affluence]));
          carte.append(ligne, element('small', '', creneau.raisons.join(' · ')), element('small', '', 'Score IA : ' + creneau.score + '/100'));
          carte.addEventListener('click', () => appliquer(creneau, carte));
          liste.append(carte);
        });
        informer('Cliquez sur un créneau pour remplir la date et la durée.');
      } catch (erreur) {
        informer('Suggestion indisponible pour le moment.', true);
      } finally {
        bouton.disabled = false;
      }
    });
  }

  /* ------------------------------------------------------------------
   |  Assistant de réservation (bulle flottante)
   |------------------------------------------------------------------ */
  function initialiserAssistant(racine) {
    const panneau = racine.querySelector('[data-assistant-panel]');
    const bascule = racine.querySelector('[data-assistant-toggle]');
    const messages = racine.querySelector('[data-assistant-messages]');
    const formulaire = racine.querySelector('[data-assistant-form]');
    const champ = racine.querySelector('[data-assistant-input]');
    let contexte = {};
    let occupe = false;

    const ouvrir = (visible) => {
      panneau.hidden = !visible;
      bascule.setAttribute('aria-expanded', visible ? 'true' : 'false');
      if (visible) champ?.focus();
    };
    bascule?.addEventListener('click', () => ouvrir(panneau.hidden));
    racine.querySelector('[data-assistant-fermer]')?.addEventListener('click', () => ouvrir(false));

    const defiler = () => { messages.scrollTop = messages.scrollHeight; };
    const bulle = (texte, cote) => {
      const noeud = element('p', 'chat-vetement-bulle chat-vetement-bulle--' + cote, texte);
      messages.append(noeud);
      defiler();
      return noeud;
    };

    const poster = async (url, corps) => {
      const reponse = await fetch(url, {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': jetonCsrf() },
        body: JSON.stringify(corps),
      });
      return { ok: reponse.ok, data: await reponse.json().catch(() => ({})) };
    };

    const reserver = async (proposition, bouton) => {
      bouton.disabled = true;
      bouton.textContent = 'Réservation…';
      try {
        const { ok, data } = await poster(racine.dataset.urlReserver, {
          vetement_id: proposition.vetement_id,
          atelier_id: proposition.atelier_id,
          service_id: proposition.service_id,
          date_rdv: proposition.date,
          duree: proposition.duree,
        });
        if (!ok) {
          bulle(premiereErreur(data, 'La réservation a échoué.') + ' Demandez-moi un autre créneau.', 'bot');
          bouton.disabled = false;
          bouton.textContent = 'Réserver';
          return;
        }
        messages.querySelectorAll('[data-reserver]').forEach((b) => { b.disabled = true; });
        bouton.textContent = 'Réservé ✓';
        const confirmation = bulle(data.message + ' ', 'bot');
        const lien = element('a', '', 'Voir le rendez-vous');
        lien.href = data.url;
        confirmation.append(lien);
        defiler();
      } catch (erreur) {
        bulle('La réservation a échoué. Réessayez dans un instant.', 'bot');
        bouton.disabled = false;
        bouton.textContent = 'Réserver';
      }
    };

    const afficherPropositions = (propositions) => {
      const bloc = element('div', 'assistant-rdv__propositions');
      propositions.forEach((p) => {
        const carte = element('div', 'assistant-rdv__proposition');
        carte.append(
          element('strong', '', p.libelle),
          element('span', '', p.atelier + (p.note ? ' — ★ ' + p.note : '')),
          element('small', '', [p.service, p.duree_libelle + ' estimées', p.prix].filter(Boolean).join(' · ')),
          element('small', '', AFFLUENCE[p.affluence] + ' — score ' + p.score + '/100'),
        );
        if (p.adresse) carte.append(element('small', '', p.adresse));
        const bouton = element('button', 'btn btn-primary small', 'Réserver');
        bouton.type = 'button';
        bouton.dataset.reserver = '';
        bouton.addEventListener('click', () => reserver(p, bouton));
        carte.append(bouton);
        bloc.append(carte);
      });
      messages.append(bloc);
      defiler();
    };

    const afficherChoix = (choix) => {
      const bloc = element('div', 'assistant-rdv__choix');
      choix.forEach((libelle) => {
        const bouton = element('button', '', libelle);
        bouton.type = 'button';
        bouton.addEventListener('click', () => { bloc.remove(); envoyer(libelle); });
        bloc.append(bouton);
      });
      messages.append(bloc);
      defiler();
    };

    const envoyer = async (texte) => {
      if (occupe || !texte) return;
      occupe = true;
      champ.disabled = true;
      bulle(texte, 'user');
      const attente = bulle('Je cherche les meilleurs créneaux…', 'bot');

      try {
        const { ok, data } = await poster(racine.dataset.url, { message: texte, contexte });
        attente.textContent = ok ? data.reponse : premiereErreur(data, 'L\'assistant est indisponible pour le moment.');
        if (ok) {
          contexte = data.contexte || contexte;
          if (data.choix?.length) afficherChoix(data.choix);
          if (data.propositions?.length) afficherPropositions(data.propositions);
        }
      } catch (erreur) {
        attente.textContent = 'L\'assistant ne répond pas. Réessayez dans un instant.';
      } finally {
        occupe = false;
        champ.disabled = false;
        champ.focus();
      }
    };

    formulaire?.addEventListener('submit', (event) => {
      event.preventDefault();
      const texte = champ.value.trim();
      champ.value = '';
      envoyer(texte);
    });
  }

  /* ------------------------------------------------------------------
   |  Replanification d'un rendez-vous annulé (page « Mes rendez-vous »)
   |------------------------------------------------------------------ */
  function initialiserReplanification(carte) {
    const bouton = carte.querySelector('[data-replanif-bouton]');
    const liste = carte.querySelector('[data-replanif-liste]');
    const message = carte.querySelector('[data-replanif-message]');
    let charge = false;

    const informer = (texte, erreur = false) => {
      message.hidden = !texte;
      message.textContent = texte || '';
      message.classList.toggle('is-erreur', erreur);
    };

    const reprogrammer = async (proposition, action) => {
      liste.querySelectorAll('button').forEach((b) => { b.disabled = true; });
      action.textContent = 'Reprogrammation…';
      try {
        const reponse = await fetch(carte.dataset.urlReprogrammer, {
          method: 'POST',
          headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': jetonCsrf() },
          body: JSON.stringify({
            atelier_id: proposition.atelier_id,
            service_id: proposition.service_id,
            date_rdv: proposition.date,
            duree: proposition.duree,
          }),
        });
        const data = await reponse.json().catch(() => ({}));
        if (!reponse.ok) {
          informer(premiereErreur(data, 'La reprogrammation a échoué.') + ' Relancez la recherche.', true);
          liste.querySelectorAll('button').forEach((b) => { b.disabled = false; });
          action.textContent = 'Reprogrammer';
          charge = false;
          bouton.hidden = false;
          return;
        }
        carte.classList.add('est-faite');
        liste.replaceChildren();
        informer(data.message);
        const lien = element('a', '', ' Voir le nouveau rendez-vous');
        lien.href = data.url;
        message.append(lien);
      } catch (erreur) {
        informer('La reprogrammation a échoué. Réessayez dans un instant.', true);
        liste.querySelectorAll('button').forEach((b) => { b.disabled = false; });
        action.textContent = 'Reprogrammer';
      }
    };

    const chercher = async () => {
      if (charge) return;
      charge = true;
      bouton.disabled = true;
      liste.replaceChildren();
      informer('L\'IA cherche des créneaux de remplacement…');

      try {
        const reponse = await fetch(carte.dataset.url, { headers: { Accept: 'application/json' } });
        const data = await reponse.json().catch(() => ({}));
        if (!reponse.ok) {
          informer(premiereErreur(data, 'Recherche indisponible pour le moment.'), true);
          charge = false;
          return;
        }
        if (!data.propositions?.length) {
          informer('Aucun créneau libre dans les 14 prochains jours. Prenez un nouveau rendez-vous dans un autre atelier.', true);
          return;
        }

        data.propositions.forEach((p, rang) => {
          const choix = element('div', 'creneau-ia');
          choix.append(element('strong', '', (rang === 0 ? '★ ' : '') + p.libelle));
          choix.append(element('span', '', p.atelier + (p.meme_atelier ? ' (même atelier)' : ' (autre atelier)')));
          const ligne = element('span', 'creneau-ia__ligne');
          ligne.append(element('span', '', p.duree_libelle), element('span', 'affluence-ia affluence-ia--' + p.affluence, AFFLUENCE[p.affluence]));
          choix.append(ligne, element('small', '', [p.service, p.prix, 'score ' + p.score + '/100'].filter(Boolean).join(' · ')));
          const action = element('button', 'btn btn-primary small', 'Reprogrammer');
          action.type = 'button';
          action.addEventListener('click', () => reprogrammer(p, action));
          choix.append(action);
          liste.append(choix);
        });
        bouton.hidden = true;
        informer('Choisissez un créneau : le nouveau rendez-vous est envoyé à l\'atelier.');
      } catch (erreur) {
        informer('Recherche indisponible pour le moment.', true);
        charge = false;
      } finally {
        bouton.disabled = false;
      }
    };

    bouton?.addEventListener('click', chercher);
    const viser = () => {
      if (location.hash === '#' + carte.id) {
        carte.scrollIntoView({ behavior: 'smooth', block: 'center' });
        chercher();
      }
    };
    window.addEventListener('hashchange', viser);
    if ('auto' in carte.dataset) chercher();
    viser();
  }

  const demarrer = () => {
    document.querySelectorAll('[data-creneaux-ia]').forEach(initialiserCreneaux);
    document.querySelectorAll('[data-assistant-rdv]').forEach(initialiserAssistant);
    document.querySelectorAll('[data-replanif]').forEach(initialiserReplanification);
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', demarrer);
  } else {
    demarrer();
  }
})();
