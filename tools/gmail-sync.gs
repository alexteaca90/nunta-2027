/**
 * Nunta noastră 2027 — sincronizare Gmail și Google Drive → platformă.
 *
 * Rulează în contul tău Google, la fiecare 10 minute. Trimite în platformă DOAR:
 *   - emailurile de la / către adresele salvate la săli în platformă;
 *   - emailurile cu eticheta „Nunta 2027” (o pui tu pe primul email de la o sală nouă);
 *   - fișierele din folderul Drive „Nunta 2027 – Săli”, câte un subfolder pentru
 *     fiecare sală: poze, PDF-uri, conversații WhatsApp exportate (.txt sau .zip),
 *     documente Google. Fiecare fișier ajunge la sala folderului în care stă.
 * Restul căsuței și restul Drive-ului nu sunt citite și nu pleacă nicăieri.
 *
 * Instalare (o singură dată; la o versiune nouă se repetă pașii 1 și 3):
 *   1. script.google.com → proiectul tău (sau Proiect nou) → înlocuiești tot codul cu acesta.
 *   2. Servicii (+) → Drive API → Adaugă. E nevoie de el ca să se citească textul
 *      din poze și din PDF-urile scanate.
 *   3. Selectezi funcția „instaleaza” și apeși Rulează; accepți permisiunile
 *      (Gmail și Drive). Folderele sălilor se creează singure în Drive.
 */

const CONFIG = {
  url: '%%URL%%',      // adresa API-ului platformei
  token: '%%TOKEN%%',  // tokenul secret din WordPress → Nunta 2027
  label: 'Nunta 2027',
  zile: 180,           // cât de departe în trecut se uită în Gmail
  folder: 'Nunta 2027 – Săli', // folderul din Drive cu câte un subfolder pe sală
  // Numele cu care apăreți VOI în conversațiile WhatsApp exportate. Mesajele voastre
  // rămân în platformă, dar prețurile și condițiile se citesc doar din mesajele sălii.
  eu: ['Alex', 'Andreea'],
  maxMB: 10            // fișierele mai mari se adaugă manual în platformă
};

function instaleaza() {
  ScriptApp.getProjectTriggers().forEach(function (t) {
    if (t.getHandlerFunction() === 'sincronizeaza') ScriptApp.deleteTrigger(t);
  });
  ScriptApp.newTrigger('sincronizeaza').timeBased().everyMinutes(10).create();
  if (!GmailApp.getUserLabelByName(CONFIG.label)) GmailApp.createLabel(CONFIG.label);
  sincronizeaza();
  Logger.log('Gata. Sincronizarea rulează la fiecare 10 minute. Folderul din Drive: ' + radacina_().getUrl());
}

function sincronizeaza() {
  var start = Date.now();
  var contacts = api_('GET', 'inbox/contacts');
  gmail_(contacts, start);
  drive_(contacts, start);
}

/* ------------------------------- Gmail ------------------------------- */

function gmail_(contacts, start) {
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
        text: /pdf/i.test(type) ? ocr_(a, a.getName()) : ''
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

/** Text dintr-un PDF sau dintr-o poză, prin Google Drive (OCR). Cere serviciul Drive API. */
function ocr_(blobSource, name) {
  try {
    if (typeof Drive === 'undefined') return '';
    var f = Drive.Files.create(
      { name: 'nunta2027-temp-' + name, mimeType: 'application/vnd.google-apps.document' },
      blobSource.copyBlob(),
      { ocrLanguage: 'ro' }
    );
    var txt = DocumentApp.openById(f.id).getBody().getText();
    Drive.Files.remove(f.id);
    return txt.slice(0, 60000);
  } catch (e) {
    return '';
  }
}

/* ---------------------------- Google Drive ---------------------------- */

function radacina_() {
  var props = PropertiesService.getScriptProperties();
  var id = props.getProperty('radacina');
  if (id) {
    try { var f = DriveApp.getFolderById(id); if (!f.isTrashed()) return f; } catch (e) {}
  }
  var it = DriveApp.getFoldersByName(CONFIG.folder);
  var root = it.hasNext() ? it.next() : DriveApp.createFolder(CONFIG.folder);
  props.setProperty('radacina', root.getId());
  return root;
}

function norm_(s) {
  return String(s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
}

/** Un subfolder pentru fiecare sală; legătura se păstrează în descrierea folderului. */
function folderePeSali_(venues) {
  var root = radacina_(), byId = {}, byName = {}, out = {};
  var it = root.getFolders();
  while (it.hasNext()) {
    var f = it.next(), m = (f.getDescription() || '').match(/nn27:([A-Za-z0-9_-]+)/);
    if (m) byId[m[1]] = f; else byName[norm_(f.getName())] = f;
  }
  venues.forEach(function (v) {
    var f = byId[v.id] || byName[norm_(v.n)];
    if (!f) {
      if (v.st === 6) return; // sălile respinse nu primesc folder nou
      f = root.createFolder(v.n);
    }
    if (!/nn27:/.test(f.getDescription() || '')) {
      f.setDescription('nn27:' + v.id + ' — nu ștergeți acest rând: leagă folderul de sala din platformă.');
    }
    out[v.id] = f;
  });
  return out;
}

function fisiere_(folder, depth, acc) {
  var it = folder.getFiles();
  while (it.hasNext()) acc.push(it.next());
  if (depth > 0) {
    var sub = folder.getFolders();
    while (sub.hasNext()) fisiere_(sub.next(), depth - 1, acc);
  }
  return acc;
}

function drive_(contacts, start) {
  var venues = contacts.venues || [];
  if (!venues.length) return;
  var foldere = folderePeSali_(venues);
  var props = PropertiesService.getScriptProperties();
  var sent = 0;
  for (var vid in foldere) {
    var files = fisiere_(foldere[vid], 2, []);
    for (var i = 0; i < files.length; i++) {
      if (Date.now() - start > 4.5 * 60 * 1000) { Logger.log('Drive: continuă la următoarea rulare.'); return; }
      var file = files[i], key = 'f_' + file.getId(), ver = vid + '|' + file.getLastUpdated().getTime();
      if (props.getProperty(key) === ver) continue;
      try {
        sent += trimiteFisier_(file, vid);
        props.setProperty(key, ver);
      } catch (e) {
        Logger.log('Drive: ' + file.getName() + ': ' + e.message);
      }
    }
  }
  if (sent) Logger.log('Drive: trimise în platformă: ' + sent);
}

function trimiteFisier_(file, vid) {
  var mime = file.getMimeType(), name = file.getName(), id = 'drv' + file.getId();
  var base = { threadId: file.getId(), date: file.getLastUpdated().getTime(), venue: vid, direction: 'in' };
  if (mime === MimeType.GOOGLE_DOCS) {
    return trimiteText_(base, id, name, DocumentApp.openById(file.getId()).getBody().getText());
  }
  if (/zip/i.test(mime) || /\.zip$/i.test(name)) {
    var parts = Utilities.unzip(file.getBlob()), n = 0;
    parts.forEach(function (b, k) {
      n += trimiteBlob_(base, id + '-' + k, b.getName() || (name + '-' + k), b);
    });
    return n;
  }
  return trimiteBlob_(base, id, name, file.getBlob());
}

function trimiteBlob_(base, id, name, blob) {
  var type = blob.getContentType() || '', ext = (name.split('.').pop() || '').toLowerCase();
  if (/^text\//.test(type) || ext === 'txt') return trimiteText_(base, id, name, blob.getDataAsString('UTF-8'));
  if (ext === 'pdf') type = 'application/pdf';
  if (!/pdf|^image\/(jpe?g|png|webp)/i.test(type)) return 0; // video, audio etc. nu se trimit
  if (blob.getBytes().length > CONFIG.maxMB * 1024 * 1024) { Logger.log('Drive: ' + name + ' depășește ' + CONFIG.maxMB + ' MB, adăugați-l manual.'); return 0; }
  api_('POST', 'inbox', {
    message: merge_(base, { id: id, from: 'Google Drive', subject: name, body: '' }),
    attachments: [{ name: name, mime: type, data: Utilities.base64Encode(blob.getBytes()), text: ocr_(blob, name) }]
  });
  return 1;
}

function trimiteText_(base, id, name, text) {
  var wa = whatsapp_(text);
  api_('POST', 'inbox', {
    message: merge_(base, {
      id: id,
      from: wa.isWa ? 'WhatsApp' : 'Google Drive',
      subject: wa.isWa ? 'Conversație WhatsApp' + (wa.cu ? ' cu ' + wa.cu : '') : name,
      body: text.slice(-60000),
      parse: wa.parse.slice(-60000)
    }),
    attachments: []
  });
  return 1;
}

/** Desparte o conversație WhatsApp exportată: păstrează pentru citire doar mesajele sălii. */
function whatsapp_(text) {
  var re = /^\[?\d{1,2}[.\/]\d{1,2}[.\/]\d{2,4},?\s+\d{1,2}:\d{2}(?::\d{2})?(?:\s*[AaPp]\.?\s?[Mm]\.?)?\]?\s*(?:-\s*)?([^:]{1,60}):\s?(.*)$/;
  var eu = CONFIG.eu.map(norm_).filter(String), out = [], keep = true, isWa = false, altii = {};
  String(text).replace(/[\u200e\u200f]/g, '').split(/\r?\n/).forEach(function (l) {
    var m = l.match(re);
    if (m) {
      isWa = true;
      var who = norm_(m[1]);
      keep = !eu.some(function (x) { return who.indexOf(x) === 0; });
      if (keep) { out.push(m[2]); altii[m[1].trim()] = 1; }
    } else if (keep) {
      out.push(l);
    }
  });
  return { isWa: isWa, parse: isWa ? out.join('\n') : String(text), cu: Object.keys(altii).slice(0, 2).join(', ') };
}

function merge_(a, b) { var o = {}; for (var k in a) o[k] = a[k]; for (var j in b) o[j] = b[j]; return o; }

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
