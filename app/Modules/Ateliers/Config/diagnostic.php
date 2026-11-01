<?php

/*
 * Microservice de diagnostic par photo (dossier ia-diagnostic/ à la racine du dépôt).
 * Lu via config('ateliers_diagnostic.*').
 */
return [
    // Adresse du service Python (il n'écoute que sur la machine locale).
    'url' => env('IA_DIAGNOSTIC_URL', 'http://127.0.0.1:8001'),

    // Même secret que la variable IA_DIAGNOSTIC_JETON du service Python.
    'jeton' => env('IA_DIAGNOSTIC_JETON'),

    // Délais en secondes : connexion, puis analyse complète (environ 1 s sur un processeur de portable).
    'delai_connexion' => (int) env('IA_DIAGNOSTIC_DELAI_CONNEXION', 3),
    'delai' => (int) env('IA_DIAGNOSTIC_DELAI', 20),

    // Analyses autorisées par minute et par visiteur (chaque analyse occupe le processeur du service).
    'par_minute' => (int) env('IA_DIAGNOSTIC_PAR_MINUTE', 10),

    // Assistant de réparation (RAG) : recherche dans les fiches par le même service Python, puis rédaction par
    // Gemini avec la clé déjà configurée pour le module Vêtements (config/services.php, « vision »).
    'assistant_par_minute' => (int) env('IA_ASSISTANT_PAR_MINUTE', 10),
    'assistant_delai_redaction' => (int) env('IA_ASSISTANT_DELAI_REDACTION', 20),
];
