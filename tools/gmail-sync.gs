/**
 * Nunta noastră 2027 — sincronizare Gmail și Google Drive → platformă.
 *
 * Rulează în contul tău Google, la fiecare 10 minute. Trimite în platformă DOAR:
 *   - emailurile de la / către adresele salvate la săli în platformă;
 *   - emailurile cu eticheta „Nunta 2027” (o pui tu pe primul email de la o sală nouă);
 *   - fișierele din folderele Drive de mai jos (CONFIG.folder): în ele, oricât de adânc
 *     (ex. „Mail/Crystal”, „Whatsapp/Crystal”), fiecare folder cu numele unei săli
 *     (se recunosc și „GrandMarion”, „HR-HotelCapitol”, „ComplexEOS”) e citit:
 *     poze, PDF-uri, Word, conversații WhatsApp (.txt / .zip), documente Google.
 *     Un fișier care există deja în platformă (venit pe email) nu se mai adaugă.
 *     Video și fișierele prea mari pentru site apar în platformă ca link spre Drive.
 * Restul căsuței și restul Drive-ului nu sunt citite și nu pleacă nicăieri.
 *
 * Instalare (o singură dată; la o versiune nouă se repetă pașii 1 și 3):
 *   1. script.google.com → proiectul tău (sau Proiect nou) → înlocuiești tot codul cu acesta.
 *   2. Servicii (+) → Drive API → Adaugă. E nevoie de el ca să se citească textul
 *      din poze și din PDF-urile scanate.
 *   3. Selectezi funcția „instaleaza” și apeși Rulează; accepți permisiunile
 *      (Gmail și Drive).
 * Ca să vezi ce folder a fost legat de ce sală (fără să trimită nimic), rulează „verifica”.
 */

const CONFIG = {
  url: '%%URL%%',      // adresa API-ului platformei
  token: '%%TOKEN%%',  // tokenul secret din WordPress → Nunta 2027
  label: 'Nunta 2027',
  zile: 180,           // cât de departe în trecut se uită în Gmail
  // Folderele din Drive citite (nume sau ID). Primul se creează dacă nu există niciunul,
  // și doar în el se fac automat foldere pentru sălile noi.
  folder: ['Nunta 2027 – Săli', '_PLATFORMA-WEDDINGS'],
  // Numele cu care apăreți VOI în conversațiile WhatsApp exportate. Mesajele voastre
  // rămân în platformă, dar prețurile și condițiile se citesc doar din mesajele sălii.
  eu: ['Alex', 'Andreea'],
  maxMB: 25            // limita platformei; fișierele mai mari se adaugă manual (comprimate)
};

function instaleaza() {
  ScriptApp.getProjectTriggers().forEach(function (t) {
    if (t.getHandlerFunction() === 'sincronizeaza') ScriptApp.deleteTrigger(t);
  });
  ScriptApp.newTrigger('sincronizeaza').timeBased().everyMinutes(10).create();
  if (!GmailApp.getUserLabelByName(CONFIG.label)) GmailApp.createLabel(CONFIG.label);
  sincronizeaza();
  Logger.log('Gata. Sincronizarea rulează la fiecare 10 minute. Foldere Drive: ' + radacini_().map(function (f) { return f.getName(); }).join(', '));
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
      if (!/pdf|^image\//i.test(type) || a.getSize() > CONFIG.maxMB * 1024 * 1024) return;
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

function radacini_() {
  var out = [], seen = {};
  [].concat(CONFIG.folder).forEach(function (x) {
    var f = null;
    try { f = DriveApp.getFolderById(x); } catch (e) {}
    if (!f) { var it = DriveApp.getFoldersByName(x); while (it.hasNext()) { var c = it.next(); if (!c.isTrashed()) { f = c; break; } } }
    if (f && !f.isTrashed() && !seen[f.getId()]) { seen[f.getId()] = 1; out.push(f); }
  });
  if (!out.length) out.push(DriveApp.createFolder([].concat(CONFIG.folder)[0]));
  return out;
}

function norm_(s) {
  return String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
}

// Cuvinte prea generale ca să deosebească o sală de alta.
var GENERAL_ = ['sala', 'salon', 'hotel', 'restaurant', 'complex', 'event', 'events', 'evenimente', 'ballroom',
  'venue', 'iasi', 'the', 'and', 'by', 'wedding', 'weddings', 'hall', 'resort', 'pensiunea', 'boutique', 'grand', 'de', 'la', 'le'];

/** Sala al cărei nume (sau nume alternativ) se regăsește în numele folderului; '' dacă nu e clar. */
function potrivire_(numeFolder, venues) {
  var compact = norm_(numeFolder).replace(/ /g, ''), best = '', score = 0, tie = false;
  if (!compact) return '';
  venues.forEach(function (v) {
    [v.n].concat(v.a || []).forEach(function (n) {
      var tok = norm_(n).split(' ').filter(function (t) { return t.length >= 3 && GENERAL_.indexOf(t) < 0; });
      var full = norm_(n).replace(/ /g, '');
      var sc = full === compact ? 1000 : (tok.length && tok.every(function (t) { return compact.indexOf(t) >= 0; }) ? tok.join('').length : 0);
      if (sc > score) { score = sc; best = v.id; tie = false; } else if (sc && sc === score && best !== v.id) { tie = true; }
    });
  });
  return tie ? '' : best;
}

/** Folderele sălilor, oricât de adânc (max. 3 niveluri) în folderele din CONFIG. */
function folderePeSali_(venues, log) {
  var out = {}, roots = radacini_(), ids = {};
  venues.forEach(function (v) { ids[v.id] = v; });
  function walk(folder, depth, path) {
    var it = folder.getFolders();
    while (it.hasNext()) {
      var f = it.next(), name = f.getName(), p = path + '/' + name;
      var m = (f.getDescription() || '').match(/nn27:([A-Za-z0-9_-]+)/);
      var vid = m && ids[m[1]] ? m[1] : potrivire_(name, venues);
      if (vid) {
        (out[vid] = out[vid] || []).push(f);
        if (!m) f.setDescription('nn27:' + vid + ' — nu ștergeți acest rând: leagă folderul de sala din platformă.');
        if (log) log.push(p + '  →  ' + ids[vid].n);
      } else if (depth < 3) {
        if (!f.getFolders().hasNext() && log) log.push(p + '  →  NERECUNOSCUT (redenumește-l ca sala din platformă)');
        walk(f, depth + 1, p);
      }
    }
  }
  roots.forEach(function (r) { walk(r, 1, r.getName()); });
  // Foldere noi pentru săli doar în folderul principal al platformei, dacă există.
  var main = roots.filter(function (r) { return r.getName() === [].concat(CONFIG.folder)[0]; })[0];
  if (main) venues.forEach(function (v) {
    if (!out[v.id] && v.st !== 6) {
      var f = main.createFolder(v.n);
      f.setDescription('nn27:' + v.id + ' — nu ștergeți acest rând: leagă folderul de sala din platformă.');
      out[v.id] = [f];
    }
  });
  return out;
}

/** Arată în jurnal ce folder e legat de ce sală. Nu trimite nimic. */
function verifica() {
  var log = [];
  folderePeSali_(api_('GET', 'inbox/contacts').venues || [], log);
  Logger.log('\n' + log.join('\n'));
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
    var files = [];
    foldere[vid].forEach(function (f) { fisiere_(f, 2, files); });
    for (var i = 0; i < files.length; i++) {
      if (Date.now() - start > 4.5 * 60 * 1000) { Logger.log('Drive: continuă la următoarea rulare.'); return; }
      var file = files[i], key = 'f_' + file.getId(), ver = vid + '|' + file.getLastUpdated().getTime();
      if (props.getProperty(key) === ver) continue;
      try {
        sent += trimiteFisier_(file, vid);
        props.setProperty(key, ver);
      } catch (e) {
        if (/413|too large|exceeds|Limit/i.test(e.message) || file.getSize() > 2 * 1024 * 1024) {
          // Serverul site-ului nu primește un fișier atât de mare (limita de upload a găzduirii):
          // îl punem ca link spre Drive, ca să nu lipsească din platformă.
          Logger.log('Drive: ' + file.getName() + ' pus ca link (' + e.message.slice(0, 80) + ')');
          trimiteLink_(file, vid); sent++; props.setProperty(key, ver);
        } else {
          Logger.log('Drive: ' + file.getName() + ': ' + e.message);
        }
      }
    }
  }
  if (sent) Logger.log('Drive: trimise în platformă: ' + sent);
}

function baza_(file, vid) {
  return { threadId: file.getId(), date: file.getLastUpdated().getTime(), venue: vid, direction: 'in' };
}

/** Video, audio sau fișiere peste limită: în platformă apar ca link spre Drive. */
function trimiteLink_(file, vid) {
  api_('POST', 'inbox', {
    message: merge_(baza_(file, vid), {
      id: 'drv' + file.getId(), from: 'Google Drive', subject: file.getName(), body: '',
      links: [{ name: file.getName(), url: file.getUrl(), mime: file.getMimeType(), size: file.getSize() }]
    }),
    attachments: []
  });
  return 1;
}

function trimiteFisier_(file, vid) {
  var mime = file.getMimeType(), name = file.getName(), id = 'drv' + file.getId();
  var base = baza_(file, vid);
  if (/^(video|audio)\//.test(mime) || file.getSize() > CONFIG.maxMB * 1024 * 1024) return trimiteLink_(file, vid);
  if (mime === MimeType.GOOGLE_DOCS) {
    return trimiteText_(base, id, name, DocumentApp.openById(file.getId()).getBody().getText());
  }
  if (/wordprocessingml|msword|opendocument\.text/i.test(mime) || /\.(docx?|odt)$/i.test(name)) {
    return trimiteWord_(base, id, name, file.getBlob());
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

/** Word → PDF (platforma păstrează doar PDF-uri și poze), plus textul pentru citirea ofertei. */
function trimiteWord_(base, id, name, blob) {
  if (typeof Drive === 'undefined') { Logger.log('Drive: ' + name + ': adăugați serviciul Drive API ca să se poată citi fișierele Word.'); return 0; }
  var tmp = Drive.Files.create({ name: 'nunta2027-temp-' + name, mimeType: 'application/vnd.google-apps.document' }, blob);
  try {
    var text = DocumentApp.openById(tmp.id).getBody().getText();
    var pdf = DriveApp.getFileById(tmp.id).getAs('application/pdf').setName(name.replace(/\.\w+$/, '') + '.pdf');
    api_('POST', 'inbox', {
      message: merge_(base, { id: id, from: 'Google Drive', subject: name, body: '' }),
      attachments: [{ name: pdf.getName(), mime: 'application/pdf', data: Utilities.base64Encode(pdf.getBytes()), text: text.slice(0, 60000) }]
    });
    return 1;
  } finally {
    Drive.Files.remove(tmp.id);
  }
}

function trimiteBlob_(base, id, name, blob) {
  var type = blob.getContentType() || '', ext = (name.split('.').pop() || '').toLowerCase();
  if (/^text\//.test(type) || ext === 'txt') return trimiteText_(base, id, name, blob.getDataAsString('UTF-8'));
  if (ext === 'pdf') type = 'application/pdf';
  if (!/pdf|^image\/(jpe?g|png|webp)/i.test(type)) return 0; // video, audio etc. nu se trimit
  if (blob.getBytes().length > CONFIG.maxMB * 1024 * 1024) { Logger.log('Drive: ' + name + ' (din arhivă) depășește ' + CONFIG.maxMB + ' MB.'); return 0; }
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
