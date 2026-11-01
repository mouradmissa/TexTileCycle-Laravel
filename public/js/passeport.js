// Passeport de réparation : copier le lien, partager (téléphone), imprimer en certificat.
// Sans JavaScript, les boutons restent cachés et l'adresse se copie depuis le champ en lecture seule.
(function () {
  'use strict';

  document.querySelectorAll('[data-pp-actions]').forEach(function (actions) {
    var bloc = actions.closest('[data-pp-bloc]') || document;
    var champ = bloc.querySelector('[data-pp-lien]');
    var statut = bloc.querySelector('[data-pp-statut]');
    var partager = actions.querySelector('[data-pp-partager]');
    var lien = actions.dataset.ppUrl || (champ ? champ.value : window.location.href);

    function annoncer(message) {
      if (statut) {
        statut.textContent = message;
      }
    }

    actions.hidden = false;

    actions.querySelector('[data-pp-copier]').addEventListener('click', function () {
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(lien).then(function () {
          annoncer('Lien copié.');
        }, function () {
          annoncer('Copie impossible : sélectionnez l\'adresse et copiez-la.');
        });
        return;
      }

      // Hors HTTPS (démo sur le réseau local) : sélection du champ puis commande de copie.
      if (champ) {
        champ.focus();
        champ.select();
        annoncer(document.execCommand('copy') ? 'Lien copié.' : 'Adresse sélectionnée : copiez-la (Ctrl + C).');
      } else {
        annoncer('Copie impossible ici : ouvrez le passeport et copiez son adresse.');
      }
    });

    if (partager && typeof navigator.share === 'function') {
      partager.hidden = false;
      partager.addEventListener('click', function () {
        navigator.share({ title: document.title, url: lien }).catch(function () { /* partage annulé */ });
      });
    }

    var imprimer = actions.querySelector('[data-pp-imprimer]');
    if (imprimer) {
      imprimer.addEventListener('click', function () {
        window.print();
      });
    }
  });
})();
