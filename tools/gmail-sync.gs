/**
 * Nunta noastră 2027 — sincronizare Gmail → platformă.
 *
 * Rulează în contul tău Google, la fiecare 10 minute. Trimite în platformă
 * DOAR emailurile schimbate cu sălile și furnizorii:
 *   - cele de la / către adresele salvate la săli în platformă;
 *   - cele cu eticheta „Nunta 2027” (o pui tu pe primul email de la o sală nouă).
 * Restul căsuței nu este citit și nu pleacă nicăieri.
 *
 * Instalare (o singură dată):
 *   1. script.google.com → Proiect nou → lipești tot acest cod.
 *   2. (Opțional, pentru PDF-uri scanate) Servicii (+) → Drive API → Adaugă.
 *   3. Selectezi funcția „instaleaza” și apeși Rulează; accepți permisiunile.
 */

const CONFIG = {
  url: '%%URL%%',      // adresa API-ului platformei
  token: '%%TOKEN%%',  // tokenul secret din WordPress → Nunta 2027
  label: 'Nunta 2027',
  zile: 180            // cât de departe în trecut se uită
};

function instaleaza() {
  ScriptApp.getProjectTriggers().forEach(function (t) {
    if (t.getHandlerFunction() === 'sincronizeaza') ScriptApp.deleteTrigger(t);
  });
  ScriptApp.newTrigger('sincronizeaza').timeBased().everyMinutes(10).create();
  if (!GmailApp.getUserLabelByName(CONFIG.label)) GmailApp.createLabel(CONFIG.label);
  sincronizeaza();
  Logger.log('Gata. Sincronizarea rulează la fiecare 10 minute.');
}

function sincronizeaza() {
  var start = Date.now();
  var contacts = api_('GET', 'inbox/contacts');
  var emails = contacts.emails || [];
  var parts = ['label:"' + CONFIG.label + '"'];
  if (emails.length) {
    var list = emails.join(' OR ');
    parts.push('from:(' + list + ')');
    parts.push('to:(' + list + ')');
  }
  var query = '(' + parts.join(' OR ') + ') newer_than:' + CONFIG.zile + 'd -in:spam -in:trash';
  var threads = GmailApp.search(query, 0, 100);
  var me = Session.getActiveUser().getEmail().toLowerCase();

  var messages = [];
  threads.forEach(function (th) {
    th.getMessages().forEach(function (m) { messages.push({ m: m, th: th }); });
  });
  if (!messages.length) return;

  // Platforma spune ce are deja, ca să nu trimitem de două ori.
  var known = {};
  var ids = messages.map(function (x) { return x.m.getId(); });
  for (var i = 0; i < ids.length; i += 200) {
    (api_('POST', 'inbox/known', { ids: ids.slice(i, i + 200) }).known || []).forEach(function (id) { known[id] = 1; });
  }

  var sent = 0;
  for (var k = 0; k < messages.length; k++) {
    if (Date.now() - start > 4.5 * 60 * 1000) break; // limita de timp Google
    var m = messages[k].m, th = messages[k].th;
    if (known[m.getId()]) continue;
    var from = m.getFrom();
    var atts = [];
    m.getAttachments({ includeInlineImages: false }).forEach(function (a) {
      var type = a.getContentType();
      if (!/pdf|^image\//i.test(type) || a.getSize() > 15 * 1024 * 1024) return;
      atts.push({
        name: a.getName(),
        mime: type,
        data: Utilities.base64Encode(a.getBytes()),
        text: /pdf/i.test(type) ? pdfText_(a) : ''
      });
    });
    api_('POST', 'inbox', {
      message: {
        id: m.getId(),
        threadId: th.getId(),
        date: m.getDate().getTime(),
        from: from,
        to: [m.getTo(), m.getCc()].join(','),
        subject: m.getSubject(),
        body: m.getPlainBody().slice(0, 60000),
        direction: from.toLowerCase().indexOf(me) >= 0 ? 'out' : 'in'
      },
      attachments: atts
    });
    sent++;
  }
  if (sent) Logger.log('Trimise în platformă: ' + sent);
}

/** Text din PDF prin Google Drive (merge și pentru PDF-uri scanate). Opțional. */
function pdfText_(att) {
  try {
    if (typeof Drive === 'undefined') return '';
    var f = Drive.Files.create(
      { name: 'nunta2027-temp-' + att.getName(), mimeType: 'application/vnd.google-apps.document' },
      att.copyBlob(),
      { ocrLanguage: 'ro' }
    );
    var txt = DocumentApp.openById(f.id).getBody().getText();
    Drive.Files.remove(f.id);
    return txt.slice(0, 60000);
  } catch (e) {
    return '';
  }
}

function api_(method, path, body) {
  var base = CONFIG.url;
  var url = base.indexOf('?') >= 0 ? base + path : base.replace(/\/?$/, '/') + path;
  var opts = {
    method: method.toLowerCase(),
    headers: { 'X-NN27-Token': CONFIG.token },
    muteHttpExceptions: true
  };
  if (body) {
    opts.contentType = 'application/json';
    opts.payload = JSON.stringify(body);
  }
  var r = UrlFetchApp.fetch(url, opts);
  var code = r.getResponseCode();
  var text = r.getContentText();
  if (code >= 300) throw new Error('Platforma a răspuns ' + code + ': ' + text.slice(0, 300));
  var j = text.indexOf('{');
  return JSON.parse(j > 0 ? text.slice(j) : text);
}
