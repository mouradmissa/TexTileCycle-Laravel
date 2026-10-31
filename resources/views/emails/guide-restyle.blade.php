<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $guide->titre }}</title>
</head>
<body style="margin:0;padding:0;background:#f6f1e8;color:#1c1c1c;font-family:Georgia,serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f1e8;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;background:#fff;border:1px solid #e4dccd;">
                    <tr>
                        <td style="padding:28px 28px 8px;">
                            <p style="margin:0 0 8px;font-family:Arial,sans-serif;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#6b645b;">TexTileCycle · Guide de réparation</p>
                            <h1 style="margin:0 0 12px;font-size:26px;line-height:1.2;">{{ $guide->titre }}</h1>
                            <p style="margin:0 0 18px;font-size:16px;line-height:1.5;">Bonjour {{ $nom }}, voici le guide enregistré sur votre compte.</p>
                            <p style="margin:0 0 18px;font-size:16px;line-height:1.6;">{{ $guide->description }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 28px 8px;">
                            <p style="margin:0 0 6px;font-family:Arial,sans-serif;font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:#6b645b;">Réparation demandée</p>
                            <p style="margin:0 0 18px;font-size:16px;line-height:1.5;">{{ $guide->instruction }}</p>
                            <p style="margin:0 0 6px;font-family:Arial,sans-serif;font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:#6b645b;">État avant</p>
                            <p style="margin:0 0 12px;font-size:16px;line-height:1.6;">{{ $guide->etat_avant }}</p>
                            <img src="{{ $message->embed($cheminAvant) }}" alt="État avant" width="560" style="display:block;width:100%;max-width:560px;height:auto;margin:0 0 18px;border:1px solid #e4dccd;">
                            <p style="margin:0 0 6px;font-family:Arial,sans-serif;font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:#6b645b;">État après</p>
                            <p style="margin:0 0 12px;font-size:16px;line-height:1.6;">{{ $guide->etat_apres }}</p>
                            <img src="{{ $message->embed($cheminApres) }}" alt="État après" width="560" style="display:block;width:100%;max-width:560px;height:auto;margin:0 0 18px;border:1px solid #e4dccd;">
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
