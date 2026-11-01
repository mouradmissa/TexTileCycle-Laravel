/* Reçu de don : le bouton ouvre la boîte d'impression (choisir « Enregistrer au format PDF »). */
(function () {
    'use strict';

    document.querySelectorAll('[data-imprimer]').forEach(function (bouton) {
        bouton.addEventListener('click', function () {
            window.print();
        });
    });
})();
