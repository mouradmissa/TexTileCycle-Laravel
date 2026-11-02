<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $titre }}</title>
</head>
<body style="margin:0;padding:0;background:#f6f1e8;color:#1c1c1c;font-family:Georgia,serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f1e8;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;background:#fff;border:1px solid #e4dccd;">
                    <tr>
                        <td style="padding:28px 28px 8px;">
                            <p style="margin:0 0 8px;font-family:Arial,sans-serif;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#6b645b;">TexTileCycle · Rendez-vous</p>
                            <h1 style="margin:0 0 12px;font-size:26px;line-height:1.2;">{{ $titre }}</h1>
                            <p style="margin:0 0 18px;font-size:16px;line-height:1.5;">Bonjour {{ $nom }}, {{ $intro }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 28px 8px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:16px;line-height:1.5;border-top:1px solid #e4dccd;">
                                @foreach(array_filter([
                                    'Vêtement' => $vetement,
                                    'Atelier' => $rdv->atelierNom(),
                                    'Adresse' => $rdv->atelier?->adresse,
                                    'Téléphone' => $rdv->atelier?->telephone,
                                    'Service' => $rdv->serviceNom(),
                                    'Date' => $date,
                                    'Durée estimée' => $rdv->dureeFormatee(),
                                    'Statut' => $rdv->statutLabel(),
                                ], fn ($valeur) => filled($valeur)) as $libelle => $valeur)
                                    <tr>
                                        <td style="padding:10px 0;border-bottom:1px solid #e4dccd;font-family:Arial,sans-serif;font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:#6b645b;width:40%;">{{ $libelle }}</td>
                                        <td style="padding:10px 0;border-bottom:1px solid #e4dccd;">{{ $valeur }}</td>
                                    </tr>
                                @endforeach
                            </table>
                            @if($rdv->commentaire)
                                <p style="margin:18px 0 6px;font-family:Arial,sans-serif;font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:#6b645b;">Votre commentaire</p>
                                <p style="margin:0 0 12px;font-size:16px;line-height:1.6;">{{ $rdv->commentaire }}</p>
                            @endif
                            @if(! empty($propositions))
                                <p style="margin:22px 0 8px;font-family:Arial,sans-serif;font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:#6b645b;">Créneaux de remplacement proposés par l'IA</p>
                                @foreach($propositions as $proposition)
                                    <div style="margin:0 0 8px;padding:12px 14px;border:1px solid #e4dccd;border-left:3px solid #2E7D32;">
                                        <p style="margin:0 0 4px;font-size:16px;font-weight:bold;">{{ $proposition['libelle'] }}</p>
                                        <p style="margin:0;font-family:Arial,sans-serif;font-size:13px;line-height:1.5;color:#4e6058;">
                                            {{ $proposition['atelier'] }}{{ ($proposition['meme_atelier'] ?? false) ? ' (même atelier)' : '' }}
                                            · {{ $proposition['duree_libelle'] }}
                                            · {{ ['faible' => 'Affluence faible', 'moyenne' => 'Affluence moyenne', 'forte' => 'Journée chargée'][$proposition['affluence']] ?? '' }}
                                        </p>
                                    </div>
                                @endforeach
                                <p style="margin:0 0 8px;font-family:Arial,sans-serif;font-size:12px;color:#6b645b;">Disponibles au moment de l'envoi de ce message.</p>
                            @endif
                            <p style="margin:22px 0 8px;">
                                <a href="{{ $lien }}" style="display:inline-block;background:#1c3b2a;color:#fff;text-decoration:none;font-family:Arial,sans-serif;font-size:14px;font-weight:bold;padding:12px 20px;">{{ $bouton ?? 'Voir mon rendez-vous' }}</a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 28px 28px;font-family:Arial,sans-serif;font-size:13px;line-height:1.5;color:#6b645b;">
                            Ce message a été envoyé à l'adresse de votre compte TexTileCycle.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
