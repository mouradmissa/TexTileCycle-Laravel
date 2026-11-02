/**
 * Relais HTTPS pour TexTileCycle.
 * L'offre gratuite Render bloque les ports SMTP. Ce script reçoit le message
 * en HTTPS et l'envoie avec le compte Gmail qui l'exécute.
 *
 * Déploiement : application web, exécuter en tant que moi, accès : tout le monde.
 * Propriété de script MAIL_RELAY_SECRET = la même valeur que sur Render.
 */
function doPost(e) {
  var attendu = PropertiesService.getScriptProperties().getProperty('MAIL_RELAY_SECRET');
  try {
    var data = JSON.parse((e.postData && e.postData.contents) || '{}');
    if (!attendu || !data || data.secret !== attendu) {
      return reponse({ok: false, error: 'refus'});
    }

    var options = {
      htmlBody: data.html || data.text || ' ',
      name: data.fromName || 'TexTileCycle'
    };
    if (data.replyTo) {
      options.replyTo = data.replyTo;
    }

    var inline = {};
    (data.inline || []).forEach(function (img) {
      if (!img || !img.cid || !img.data) {
        return;
      }
      inline[img.cid] = Utilities.newBlob(
        Utilities.base64Decode(img.data),
        img.mime || 'application/octet-stream',
        img.name || 'image'
      );
    });
    if (Object.keys(inline).length) {
      options.inlineImages = inline;
    }

    var fichiers = [];
    (data.attachments || []).forEach(function (fichier) {
      if (!fichier || !fichier.data) {
        return;
      }
      fichiers.push(Utilities.newBlob(
        Utilities.base64Decode(fichier.data),
        fichier.mime || 'application/octet-stream',
        fichier.name || 'fichier'
      ));
    });
    if (fichiers.length) {
      options.attachments = fichiers;
    }

    MailApp.sendEmail(data.to, data.subject || 'TexTileCycle', data.text || ' ', options);
    return reponse({ok: true});
  } catch (err) {
    return reponse({ok: false, error: String(err)});
  }
}

function reponse(objet) {
  return ContentService
    .createTextOutput(JSON.stringify(objet))
    .setMimeType(ContentService.MimeType.JSON);
}
