
const LS = 'atwn27';
const CFG = window.ATWN27 || {rest:'/wp-json/atwn27/v1/', nonce:'', admin:'', version:''};
const STAGES = ['De contactat','Contactată','Ofertă primită','Vizitată','Lista scurtă','Rezervată','Respinsă'];
const RS = ['În așteptare','Vine','Nu vine'];
const SCORE_LABELS = ['Locație','Meniu','Servicii','Flexibilitate'];
const I = {
  home:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 4l9 6.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/></svg>',
  guests:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><circle cx="9" cy="8" r="3.2"/><path d="M3 20c.6-3.4 3-5 6-5s5.4 1.6 6 5"/><circle cx="17" cy="9" r="2.4"/><path d="M16 14.2c2.6-.2 4.5 1.4 5 4.3"/></svg>',
  venues:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 21V9l8-5 8 5v12"/><path d="M9 21v-6h6v6"/><path d="M2 21h20"/></svg>',
  budget:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18"/><path d="M16 15h2"/></svg>',
  cal:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/></svg>',
  vend:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3c1.5 3 4 4 7 4-1 6-3.5 10-7 14-3.5-4-6-8-7-14 3 0 5.5-1 7-4z"/></svg>',
  set:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/></svg>',
  caret:'<svg class="caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m9 6 6 6-6 6"/></svg>',
  x:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>',
  check:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5 9-10"/></svg>',
  plus:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>',
  up:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 15 6-6 6 6"/></svg>',
  down:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>',
  link:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/></svg>'
};
const VIEWS = [
  ['acasa','Acasă',I.home],['invitati','Invitați',I.guests],['sali','Săli',I.venues],
  ['buget','Buget',I.budget],['calendar','Calendar',I.cal],['furnizori','Furnizori',I.vend],['setari','Setări',I.set]
];

/* ---------- stare ---------- */
const DEFAULT_SETTINGS = {date:'',budget:80000,giftRate:80,giftAvg:700,pendingRate:70,childPct:50,assumedPrice:350,refVenue:null};
function emptyState(){ return {settings:{...DEFAULT_SETTINGS}, cats:[], hh:[], p:[], v:[], budget:[], vendors:[], tasks:[]}; }
function migrate(s){
  if (s.cats.length && typeof s.cats[0]==='string'){ s.cats = s.cats.map((n,i)=>({id:'C'+(i+1),n})); s.hh.forEach(h=>{ if (typeof h.c==='number') h.c = 'C'+(h.c+1); }); }
  return s;
}
let S = emptyState();
/* ---------- server și sincronizare ---------- */
const COLS = ['cats','hh','p','v','budget','vendors','tasks'];
let BASE = {}, REV = 0, LOGS = [], PENDING_LOG = [], WHO = '', NEEDS_RENDER = false, BOOTED = false;
try{ WHO = localStorage.getItem(LS+'-who') || ''; }catch(e){}
const SYNC = {busy:false, again:false, timer:null, status:'loading', fails:0, msg:''};
function apiUrl(path){ const base = CFG.rest; if (base.includes('?')){ const [p,q] = path.split('?'); return base + p + (q ? '&'+q : ''); } return base + path; }
async function api(path, opt={}){
  const headers = {'X-WP-Nonce':CFG.nonce, 'X-ATWN27':'1'};
  let body = opt.body;
  if (opt.json){ headers['Content-Type']='application/json'; body = JSON.stringify(opt.json); }
  let r;
  try{ r = await fetch(apiUrl(path), {method:opt.method||'GET', credentials:'same-origin', cache:'no-store', headers, body}); }
  catch(e){ const err = new Error('Fără conexiune la server'); err.status = 0; throw err; }
  if (opt.raw && r.ok) return r;
  let data = null; try{ data = await r.json(); }catch(e){}
  if (!r.ok){ const err = new Error((data && data.message) || ('Eroare '+r.status)); err.status = r.status; err.data = data; throw err; }
  return data;
}
const norm = d => JSON.stringify(d);
function toRecords(s){
  const m = {}; m['settings:main'] = JSON.stringify(s.settings);
  for (const c of COLS){
    let max = s[c].reduce((a,x)=>Math.max(a, typeof x.o==='number'?x.o:-1), -1);
    for (const x of s[c]){ if (typeof x.o!=='number') x.o = ++max; m[c+':'+x.id] = JSON.stringify(x); }
  }
  return m;
}
function fromRecords(m){
  const s = emptyState();
  for (const k in m){ const i = k.indexOf(':'), c = k.slice(0,i); const d = JSON.parse(m[k]); if (c==='settings') s.settings = {...DEFAULT_SETTINGS, ...d}; else if (s[c]) s[c].push(d); }
  for (const c of COLS) s[c].sort((a,b)=>(a.o??0)-(b.o??0));
  return s;
}
function mergeJson(baseJ, localJ, serverJ){
  const b = baseJ ? JSON.parse(baseJ) : {}, l = JSON.parse(localJ), s = serverJ ? JSON.parse(serverJ) : {};
  const out = {...s};
  for (const f in l) if (JSON.stringify(l[f])!==JSON.stringify(b[f])) out[f] = l[f];
  for (const f in b) if (!(f in l)) delete out[f];
  return JSON.stringify(out);
}
function diffOps(){
  const cur = toRecords(S), ops = [];
  for (const k in cur) if (!BASE[k] || BASE[k].j!==cur[k]){ const i = k.indexOf(':'); ops.push({col:k.slice(0,i), id:k.slice(i+1), base:BASE[k]?BASE[k].v:0, data:JSON.parse(cur[k]), _j:cur[k]}); }
  for (const k in BASE) if (!(k in cur) && !k.startsWith('settings:')){ const i = k.indexOf(':'); ops.push({col:k.slice(0,i), id:k.slice(i+1), base:BASE[k].v, data:null}); }
  return ops;
}
/* Aplică înregistrări venite de pe server; păstrează modificările locale nesalvate (pe câmpuri). */
function applyServer(list){
  const cur = toRecords(S); let changed = false;
  for (const x of list){
    const k = x.col+':'+x.id, b = BASE[k];
    if (b && x.v <= b.v) continue;
    const sj = x.data===null ? null : norm(x.data);
    const localDirty = (k in cur) ? (!b || b.j!==cur[k]) : !!b;
    if (!localDirty){ if (sj===null) delete cur[k]; else cur[k] = sj; }
    else if (sj===null){ /* șters pe server, modificat aici: îl păstrăm (se recreează) */ }
    else if (!(k in cur)){ cur[k] = sj; }
    else if (!b){
      if (x.col==='hh'){
        const nid = x.id+'-'+Math.random().toString(36).slice(2,4);
        const d = JSON.parse(cur[k]); d.id = nid; cur['hh:'+nid] = JSON.stringify(d);
        for (const pk in cur) if (pk.startsWith('p:') && !BASE[pk]){ const p = JSON.parse(cur[pk]); if (p.h===x.id){ p.h = nid; cur[pk] = JSON.stringify(p); } }
      }
      cur[k] = sj;
    }
    else cur[k] = mergeJson(b.j, cur[k], sj);
    if (sj===null) delete BASE[k]; else BASE[k] = {v:x.v, j:sj};
    changed = true;
  }
  if (changed) S = migrate(fromRecords(cur));
  return changed;
}
function persistPending(){
  try{
    const ops = diffOps(), p = {};
    for (const o of ops){ const k = o.col+':'+o.id; p[k] = {b:BASE[k]||null, j:o.data===null?null:o._j}; }
    if (Object.keys(p).length || PENDING_LOG.length) localStorage.setItem(LS+'-pending', JSON.stringify({p, log:PENDING_LOG}));
    else localStorage.removeItem(LS+'-pending');
  }catch(e){}
}
function restorePending(){
  let d = null; try{ d = JSON.parse(localStorage.getItem(LS+'-pending')||'null'); }catch(e){}
  if (!d || !d.p || !Object.keys(d.p).length) return false;
  const cur = toRecords(S);
  for (const k in d.p){
    const {b, j} = d.p[k], srv = BASE[k];
    if (j===null){ if (srv && b && srv.v===b.v) delete cur[k]; continue; }
    if (!srv || !b || srv.v===b.v) cur[k] = j; else cur[k] = mergeJson(b.j, j, srv.j);
  }
  S = migrate(fromRecords(cur)); PENDING_LOG = d.log || [];
  return true;
}
const SYNC_TEXT = {loading:'Se încarcă…', ok:'Salvat', dirty:'Modificări nesalvate…', saving:'Se salvează…', offline:'Fără internet. Salvez când revine conexiunea.', error:'Nu s-a putut salva. Reîncerc…', invalid:'Modificarea a fost refuzată de server.', auth:'Sesiunea a expirat.'};
function setSync(st, msg){
  SYNC.status = st; SYNC.msg = msg || '';
  document.querySelectorAll('.sync').forEach(el=>{
    el.dataset.state = st;
    const tx = el.querySelector('.txt'); if (tx) tx.textContent = SYNC.msg || SYNC_TEXT[st] || '';
    el.title = SYNC.msg || SYNC_TEXT[st] || '';
    const b = el.querySelector('.sync-act');
    if (b){ b.hidden = !['error','auth','invalid','offline'].includes(st); b.textContent = st==='auth'||st==='invalid' ? 'Reîncarcă pagina' : 'Reîncearcă'; b.dataset.mode = st==='auth'||st==='invalid' ? 'reload' : 'retry'; }
  });
}
function schedule(delay=600){ clearTimeout(SYNC.timer); SYNC.timer = setTimeout(flush, delay); if (SYNC.status!=='saving') setSync('dirty'); persistPending(); }
async function flush(){
  if (!BOOTED) return;
  if (SYNC.busy){ SYNC.again = true; return; }
  const ops = diffOps();
  if (!ops.length){ if (PENDING_LOG.length) PENDING_LOG = []; setSync('ok'); persistPending(); return; }
  const batch = ops.slice(0,400), logs = PENDING_LOG.slice(0,20);
  SYNC.busy = true; setSync('saving');
  try{
    const res = await api('commit', {method:'POST', json:{ops:batch.map(({_j,...o})=>o), log:logs, who:WHO}});
    PENDING_LOG.splice(0, logs.length);
    const sent = {}; batch.forEach(o=>sent[o.col+':'+o.id] = o);
    const cascade = [];
    for (const a of res.applied){
      const k = a.col+':'+a.id;
      if (a.deleted){ delete BASE[k]; if (!sent[k]) cascade.push(k); }
      else if (sent[k]) BASE[k] = {v:a.v, j:sent[k]._j};
    }
    if (cascade.length){ const cur = toRecords(S); cascade.forEach(k=>delete cur[k]); S = migrate(fromRecords(cur)); refreshAfterRemote(); }
    if (res.log) LOGS = res.log;
    SYNC.fails = 0;
    if (ops.length > batch.length) SYNC.again = true;
    setSync(SYNC.again ? 'dirty' : 'ok');
  }catch(e){
    if (e.status===409 && e.data && e.data.data && e.data.data.records){
      applyServer(e.data.data.records); SYNC.again = true; refreshAfterRemote();
      toast('Aceleași date au fost modificate și pe alt dispozitiv. Le-am combinat cu modificările tale.');
    } else if (e.status===401 || e.status===403){ setSync('auth');
    } else if (e.status===400 || e.status===422){ setSync('invalid', 'Serverul a refuzat modificarea: '+e.message);
    } else { SYNC.fails++; setSync(navigator.onLine===false || e.status===0 ? 'offline' : 'error'); clearTimeout(SYNC.timer); SYNC.timer = setTimeout(flush, Math.min(30000, 1500*2**SYNC.fails)); }
  }finally{
    SYNC.busy = false; persistPending();
    if (SYNC.again){ SYNC.again = false; schedule(150); }
  }
}
async function poll(){
  if (!BOOTED || document.hidden || SYNC.busy || SYNC.status==='auth') return;
  try{
    const r = await api('changes?since='+Math.max(0, REV-20));
    if (r.log) LOGS = r.log;
    const changed = r.changes && r.changes.length ? applyServer(r.changes) : false;
    REV = Math.max(REV, r.rev||0);
    if (changed) refreshAfterRemote();
    if (['offline','error'].includes(SYNC.status)) schedule(0);
  }catch(e){ if (e.status===401 || e.status===403) setSync('auth'); else if (e.status===0) setSync('offline'); }
}
function editing(){ const a = document.activeElement; return a && a.matches && a.matches('input:not([type=range]):not([type=file]),textarea,select') && (a.closest('#view') || a.closest('#drawer')); }
function refreshAfterRemote(){ if (editing()){ NEEDS_RENDER = true; soft(); } else render(); }
document.addEventListener('focusout', ()=>{ if (NEEDS_RENDER) setTimeout(()=>{ if (!editing() && NEEDS_RENDER){ NEEDS_RENDER = false; render(); } }, 50); });
async function load(){
  const r = await api('state');
  REV = r.rev || 0; LOGS = r.log || []; BASE = {};
  const m = {};
  for (const x of r.records){ const k = x.col+':'+x.id, j = norm(x.data); BASE[k] = {v:x.v, j}; m[k] = j; }
  if (!m['settings:main']) m['settings:main'] = JSON.stringify({...DEFAULT_SETTINGS});
  S = migrate(fromRecords(m));
  return restorePending();
}
function screen(title, text, actions=''){
  $('#view').innerHTML = `<section class="panel" style="max-width:560px;margin:48px auto"><h2>${title}</h2><p class="muted" style="margin:8px 0 16px">${text}</p>${actions}</section>`;
}
function askWho(){
  const el = document.createElement('div'); el.className = 'whobox'; el.setAttribute('role','dialog'); el.setAttribute('aria-label','Cine folosește acest dispozitiv');
  el.innerHTML = `<div class="panel"><h2>Bine ai venit</h2><p class="muted">Cine folosește acest dispozitiv? Numele apare în istoricul modificărilor.</p><div class="whobtns"><button class="btn primary" data-who="Alex">Alex</button><button class="btn primary" data-who="Andreea">Andreea</button></div></div>`;
  document.body.appendChild(el);
}
async function boot(){
  setSync('loading');
  screen('Se încarcă…', 'Aducem datele de pe server.');
  let restored = false;
  try{ restored = await load(); }
  catch(e){
    if (e.status===401 || e.status===403){ setSync('auth'); screen('Sesiunea a expirat', 'Reîncărcați pagina și introduceți din nou parola.', '<button class="btn primary" data-act="reload">Reîncarcă pagina</button>'); }
    else { setSync('error', 'Nu am putut încărca datele'); screen('Nu am putut încărca datele', esc(e.message)+'. Verificați conexiunea și încercați din nou.', '<button class="btn primary" data-act="reload">Încearcă din nou</button>'); }
    return;
  }
  BOOTED = true;
  if (!S.hh.length && !S.v.length && !S.cats.length){
    setSync('ok');
    screen('Încă nu există date', 'Importați fișierul cu datele inițiale din WordPress: meniul <b>Nunta 2027</b> → Import.', CFG.admin ? `<a class="btn primary" href="${esc(CFG.admin)}">Deschide panoul din WordPress</a>` : '');
    return;
  }
  render();
  setSync(restored ? 'dirty' : 'ok');
  if (restored){ toast('Am recuperat modificările nesalvate de data trecută.'); schedule(0); }
  if (!WHO) askWho();
  setInterval(poll, 8000);
  document.addEventListener('visibilitychange', ()=>{ if (!document.hidden) poll(); else if (diffOps().length) flush(); });
  window.addEventListener('online', ()=>schedule(0));
  window.addEventListener('beforeunload', e=>{ if (diffOps().length || SYNC.busy){ e.preventDefault(); e.returnValue = ''; } });
}
const DRINKS = ['Neprecizat','Incluse complet','Parțial incluse','Nu sunt incluse'];
function offerOf(v){ if (!v.of) v.of = {date:'',valid:'',drinks:0,deposit:'',extra:0,inc:'',files:[]}; return v.of; }
function venueTotal(v,C){ const o = offerOf(v); if (!v.ppp) return 0; return C.expA*v.ppp + C.expK*(v.cp||v.ppp*S.settings.childPct/100) + num(o.extra); }
function fsize(b){ return b>1048576 ? (b/1048576).toFixed(1).replace('.',',')+' MB' : Math.max(1,Math.round(b/1024))+' KB'; }
async function addFiles(v, list){
  const o = offerOf(v); let ok=0;
  for (const file of list){
    if (!/^(application\/pdf|image\/)/.test(file.type)){ toast(`${file.name}: se pot atașa doar PDF-uri și imagini`); continue; }
    if (file.size > 25*1024*1024){ toast(`${file.name}: fișierul depășește 25 MB`); continue; }
    const fd = new FormData(); fd.append('file', file); fd.append('venue', v.id);
    toast(`Se încarcă ${file.name}…`);
    try{ const meta = await api('files', {method:'POST', body:fd}); o.files.push(meta); ok++; }
    catch(e){ toast(`${file.name}: ${e.message}`); if (e.status===401||e.status===403) setSync('auth'); }
  }
  if (ok){ if (v.st<2) v.st = 2; if (!o.date) o.date = new Date().toISOString().slice(0,10); commit(`${v.n}: ${ok===1?'ofertă atașată':ok+' fișiere atașate'}`); toast(ok===1?'Oferta a fost atașată':`${ok} fișiere atașate`); }
}
async function viewFile(vid, fid){
  const v = byId(S.v,vid), f = offerOf(v).files.find(x=>x.id===fid);
  let blob;
  try{ const r = await api('files/'+fid, {raw:true}); blob = await r.blob(); }
  catch(e){ toast(e.status===404 ? 'Fișierul nu mai există pe server' : 'Fișierul nu a putut fi deschis: '+e.message); return; }
  const url = URL.createObjectURL(blob);
  const el = document.createElement('div'); el.className='viewer'; el.setAttribute('role','dialog'); el.setAttribute('aria-label',f.name);
  el.innerHTML = `<header><span>${esc(v.n)} · ${esc(f.name)}</span><span style="display:flex;gap:8px"><a class="btn" href="${url}" target="_blank" rel="noopener">Deschide separat</a><a class="btn" href="${url}" download="${esc(f.name)}">Descarcă</a><button class="btn" data-act="closeviewer">Închide</button></span></header><div class="vbody">${f.type.startsWith('image/')?`<img src="${url}" alt="${esc(f.name)}">`:`<iframe src="${url}" title="${esc(f.name)}"></iframe>`}</div>`;
  el.dataset.url = url; document.body.appendChild(el);
}
function save(){ schedule(); }
function log(msg){ LOGS.unshift({t:Date.now(), who:WHO, m:msg}); LOGS = LOGS.slice(0,30); PENDING_LOG.push(msg); }

const UI = {view:'acasa', q:'', cat:'all', gf:'all', open:new Set(), gview:'cards', vtab:'board', vq:'', drawer:null, showRejected:false};
try{ const h = location.hash.slice(1); if (VIEWS.some(v=>v[0]===h)) UI.view = h; }catch(e){}

/* ---------- utilitare ---------- */
const $ = s => document.querySelector(s);
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const NF = new Intl.NumberFormat('ro-RO',{maximumFractionDigits:0});
const lei = n => NF.format(Math.round(n||0)) + ' lei';
const n0 = n => NF.format(Math.round(n||0));
const num = v => { const x = parseFloat(String(v).replace(/\./g,'').replace(',','.')); return isFinite(x) ? x : 0; };
const fmtDate = d => d ? new Date(d+'T12:00:00').toLocaleDateString('ro-RO',{day:'numeric',month:'long',year:'numeric'}) : '';
const rel = t => { const m = Math.round((Date.now()-t)/60000); return m<1?'acum':m<60?`acum ${m} min`:m<1440?`acum ${Math.round(m/60)} h`:new Date(t).toLocaleDateString('ro-RO'); };
const byId = (arr,id) => arr.find(x=>x.id===id);
const membersOf = hid => S.p.filter(p=>p.h===hid);
const catName = id => (S.cats.find(c=>c.id===id)||{}).n || 'Fără categorie';
const catIdx = id => { const i = S.cats.findIndex(c=>c.id===id); return i<0 ? 999 : i; };
const orderedHH = () => S.hh.map((h,i)=>[h,i]).sort((a,b)=>catIdx(a[0].c)-catIdx(b[0].c) || a[1]-b[1]).map(x=>x[0]);
const hhLabel = h => h.solo ? ((membersOf(h.id)[0]||{}).n || 'Persoană') : (h.fam || h.n);
function nextId(prefix, arr){ return prefix + String(arr.filter(x=>x.id.startsWith(prefix)).reduce((m,x)=>Math.max(m, +x.id.slice(prefix.length)||0),0)+1).padStart(3,'0'); }
const uid = p => p + Date.now().toString(36) + Math.random().toString(36).slice(2,5);
function nextP(){ return uid('P'); }
function defaultCat(){ if (UI.cat!=='all' && S.cats.some(c=>c.id===UI.cat)) return UI.cat; return (S.cats.find(c=>c.n==='Prieteni')||S.cats[0]||{}).id; }
function numbering(){ const pn={}, hn={}; let i=0; orderedHH().forEach((h,k)=>{ hn[h.id]=k+1; for (const m of membersOf(h.id)) pn[m.id]=++i; }); return {pn,hn}; }
let NUM = {pn:{},hn:{}};

/* ---------- calcule legate ---------- */
function compute(){
  const st = S.settings, pr = st.pendingRate/100;
  let listed=0, invitedP=0, answered=0, conf=0, confA=0, confK=0, pendA=0, pendK=0, declined=0;
  const invSet = new Set(S.hh.filter(h=>h.inv).map(h=>h.id));
  for (const p of S.p){
    listed++;
    if (invSet.has(p.h)) invitedP++;
    if (p.r!==0) answered++;
    if (p.r===1){ conf++; p.k?confK++:confA++; }
    else if (p.r===0){ p.k?pendK++:pendA++; }
    else declined++;
  }
  const expA = confA + pendA*pr, expK = confK + pendK*pr, expected = expA + expK;
  const ref = refVenue();
  const price = ref && ref.ppp>0 ? ref.ppp : st.assumedPrice;
  const priceSrc = ref && ref.ppp>0 ? ref.n : 'ipoteza voastră';
  const kidPrice = ref && ref.cp>0 ? ref.cp : price*st.childPct/100;
  const venueCost = expA*price + expK*kidPrice + (ref && ref.ppp>0 && ref.of ? num(ref.of.extra) : 0);
  // buget
  let cost=0, paid=0;
  const lines = S.budget.map(b=>{
    const vend = S.vendors.filter(v=>v.line===b.id);
    const booked = vend.filter(v=>v.status==='Rezervat');
    const vQuote = booked.reduce((a,v)=>a+num(v.price),0);
    const vPaid = vend.reduce((a,v)=>a+v.pays.reduce((s,p)=>s+(p.done?num(p.amount):0),0),0);
    const est = b.link==='venue' ? venueCost : num(b.est);
    const quote = vQuote || num(b.quote);
    const eff = quote>0 ? quote : est;
    const pd = num(b.paid) + vPaid;
    cost += eff; paid += pd;
    return {...b, est, quote, eff, pd, fromVendors:booked.length>0, vPaid};
  });
  // daruri pe familie
  let giftActual=0, giftForecast=0, hhWithDar=0;
  for (const h of S.hh){
    const ms = membersOf(h.id);
    if (h.dar!=null && h.dar!=='' ){ giftActual += num(h.dar); hhWithDar++; continue; }
    let a=0; for (const m of ms){ if (m.k) continue; a += m.r===1?1:m.r===0?pr:0; }
    giftForecast += a*st.giftAvg*st.giftRate/100;
  }
  const gifts = giftActual + giftForecast;
  return {listed, invitedP, answered, conf, declined, pend:pendA+pendK, expA, expK, expected, price, priceSrc, kidPrice, venueCost, ref, cost, paid, lines, gifts, giftActual, giftForecast, hhWithDar, balance:gifts-cost};
}
function refVenue(){
  const st = S.settings;
  const booked = S.v.find(v=>v.st===5);
  if (booked) return booked;
  return st.refVenue ? byId(S.v, st.refVenue) : null;
}
function fit(v, C){
  const e = Math.round(C.expected);
  if (!v.cap) return ['Capacitate necunoscută',''];
  if (v.cap < e) return [`Prea mică (${v.cap})`,'bad'];
  if (v.min && v.min > e) return [`Minim ${v.min} pers.`,'warn'];
  return [`Încape · ${v.cap}`,'ok'];
}
function venueScore(v){
  const s = v.sc.filter(x=>x>0);
  return s.length ? s.reduce((a,b)=>a+b,0)/s.length : 0;
}

/* ---------- Firul nunții ---------- */
let lastThread = {};
function renderThread(C){
  const terms = [
    ['exp', 'Invitați așteptați', n0(C.expected), 'invitati'],
    ['op','×'],
    ['price', 'Meniu / pers. · '+C.priceSrc, lei(C.price), 'sali'],
    ['op','='],
    ['venue', 'Sală și meniu', lei(C.venueCost), 'buget'],
    ['sep'],
    ['cost', `Costuri totale · din ${n0(S.settings.budget)}`, lei(C.cost), 'buget'],
    ['op','·'],
    ['gifts', 'Daruri estimate', lei(C.gifts), 'buget'],
    ['op','→'],
    ['bal', 'Sold estimat', (C.balance>=0?'+':'−')+lei(Math.abs(C.balance)), 'buget', true]
  ];
  $('#threadIn').innerHTML = terms.map(t=>{
    if (t[0]==='op') return `<span class="op" aria-hidden="true">${t[1]}</span>`;
    if (t[0]==='sep') return `<span class="sep" aria-hidden="true"></span>`;
    const changed = lastThread[t[0]]!==undefined && lastThread[t[0]]!==t[2];
    return `<button class="term ${t[4]?'res'+(C.balance<0?' neg':''):''} ${changed?'pulse':''}" data-go="${t[3]}"><small>${esc(t[1])}</small><strong class="num">${t[2]}</strong></button>`;
  }).join('');
  terms.forEach(t=>{ if (t[2]) lastThread[t[0]] = t[2]; });
}

/* ---------- navigare ---------- */
function renderNav(){
  const counts = {invitati:S.p.length, sali:S.v.filter(v=>v.st!==6).length};
  $('#nav').innerHTML = VIEWS.map(([k,l,ic])=>`<button data-go="${k}" ${UI.view===k?'aria-current="page"':''}>${ic}<span>${l}</span>${counts[k]?`<span class="count num">${counts[k]}</span>`:''}</button>`).join('');
  $('#tabbar').innerHTML = VIEWS.filter(v=>v[0]!=='setari').map(([k,l,ic])=>`<button data-go="${k}" ${UI.view===k?'aria-current="page"':''}>${ic}<span>${l}</span></button>`).join('');
}

function render(){
  if (!BOOTED) return;
  const C = compute();
  renderNav(); renderThread(C);
  const fn = {acasa:vHome, invitati:vGuests, sali:vVenues, buget:vBudget, calendar:vCal, furnizori:vVendors, setari:vSettings}[UI.view];
  $('#view').innerHTML = fn(C);
  if (UI.drawer) renderDrawer(C);
}
function soft(){ // actualizare fără a reconstrui vizualizarea (păstrează focusul)
  const C = compute(); renderThread(C);
  document.querySelectorAll('[data-live]').forEach(el=>{
    const k = el.dataset.live;
    const map = {expected:n0(C.expected), venueCost:lei(C.venueCost), cost:lei(C.cost), gifts:lei(C.gifts), balance:(C.balance>=0?'+':'−')+lei(Math.abs(C.balance)), giftActual:lei(C.giftActual), giftForecast:lei(C.giftForecast), paid:lei(C.paid)};
    if (k in map) el.textContent = map[k];
  });
  if (UI.drawer) renderDrawer(C);
}
function go(v){ UI.view = v; closeDrawer(true); try{ history.replaceState(null,'','#'+v); }catch(e){} render(); window.scrollTo({top:0}); }

/* ---------- Acasă ---------- */
function vHome(C){
  const st = S.settings;
  let cd = '';
  if (st.date){
    const days = Math.ceil((new Date(st.date+'T12:00:00') - new Date())/86400000);
    cd = `<div class="countdown"><strong class="num">${n0(days)}</strong><span>zile până pe ${fmtDate(st.date)}</span></div>`;
  } else {
    cd = `<div class="countdown"><label class="field" style="text-align:left"><span>Data nunții nu e stabilită încă</span><input class="input" type="date" id="heroDate" data-set="date" min="2027-01-01" max="2027-12-31"></label></div>`;
  }
  const L = C.listed||1;
  const invited = C.invitedP, answered = C.answered;
  const unnamedHH = S.hh.filter(h=>!h.fam && !h.solo).length;
  const ph = S.p.filter(p=>p.ph).length;
  const noInv = S.hh.filter(h=>!h.inv).length;
  const counts = STAGES.slice(0,6).map((s,i)=>S.v.filter(v=>v.st===i).length);
  const nextTasks = S.tasks.filter(t=>!t.done).sort((a,b)=>a.date.localeCompare(b.date)).slice(0,4);
  const cw = Math.min(100, C.cost/(S.settings.budget||1)*100), pw = Math.min(100, C.paid/(S.settings.budget||1)*100);
  return `
  <section class="hero">
    <div><h1 class="names">Andreea<i>&amp;</i>Alex</h1>
    <p>Totul e legat: când cineva confirmă, când primiți o ofertă sau schimbați o ipoteză, cifrele de sus se recalculează singure.</p></div>
    ${cd}
  </section>
  <div class="grid g-dash">
    <section class="panel c7">
      <div class="ph"><div><h2>Drumul invitaților</h2><div class="sub">De la listă până la confirmare, pe persoane</div></div><button class="btn" data-go="invitati">Deschide lista</button></div>
      <div class="funnel">
        ${[['Pe listă',C.listed],['Au primit invitația',invited],['Au răspuns',answered],['Vin',C.conf]].map(([l,v])=>`<div class="frow"><span>${l}</span><div class="fbar"><i style="width:${v/L*100}%"></i></div><b class="num">${n0(v)}</b></div>`).join('')}
      </div>
      <div class="scenario">
        <label for="pr"><span>Din cei care n-au răspuns încă, câți credeți că vin?</span><b class="num" id="prv">${st.pendingRate}%</b></label>
        <input type="range" id="pr" min="0" max="100" step="5" value="${st.pendingRate}" data-set="pendingRate">
        <p>Rezultat: <b data-live="expected">${n0(C.expected)}</b> invitați așteptați. Cifra intră în costul sălii, în buget și în estimarea darurilor.</p>
      </div>
    </section>
    <section class="panel c5">
      <div class="ph"><div><h2>Bani</h2><div class="sub">Calculat din buget, săli, furnizori și daruri</div></div><button class="btn" data-go="buget">Buget</button></div>
      <div class="money">
        <div class="mrow"><span>Costuri estimate</span><b data-live="cost">${lei(C.cost)}</b></div>
        <div class="stack" title="Costuri și plăți față de limita de buget"><i class="cost" style="width:${cw}%"></i><i class="paid" style="width:${pw}%"></i></div>
        <div class="limit-note">${C.cost>S.settings.budget?`<span class="tag bad">Peste limită cu ${lei(C.cost-S.settings.budget)}</span>`:`Mai aveți ${lei(S.settings.budget-C.cost)} până la limita de ${lei(S.settings.budget)}`} · achitat <span data-live="paid">${lei(C.paid)}</span></div>
        <div class="mrow"><span>Daruri estimate</span><b data-live="gifts">${lei(C.gifts)}</b></div>
        <div class="mrow"><span>Sold estimat</span><b data-live="balance" style="color:var(${C.balance>=0?'--sage':'--bad'})">${(C.balance>=0?'+':'−')+lei(Math.abs(C.balance))}</b></div>
        <ul class="top3">${C.lines.filter(l=>l.eff>0).sort((a,b)=>b.eff-a.eff).slice(0,3).map(l=>`<li><span>${esc(l.cat)}</span><b>${lei(l.eff)}</b></li>`).join('') || '<li>Adăugați estimări în buget ca să vedeți aici cele mai mari costuri.</li>'}</ul>
      </div>
    </section>
    <section class="panel c4">
      <div class="ph"><div><h2>De completat</h2><div class="sub">Ce lipsește din listă</div></div></div>
      <div class="gaps">
        <div class="gap"><b class="num">${unnamedHH}</b><div>familii încă fără nume de familie</div><button class="link" data-gf="unnamed">Vezi</button></div>
        <div class="gap"><b class="num">${ph}</b><div>persoane încă fără nume (soț, soție, copil)</div><button class="link" data-gf="ph">Vezi</button></div>
        <div class="gap"><b class="num">${noInv}</b><div>familii fără invitație trimisă</div><button class="link" data-gf="noinv">Vezi</button></div>
      </div>
    </section>
    <section class="panel c8">
      <div class="ph"><div><h2>Căutarea sălii</h2><div class="sub">${C.ref?`Calculele folosesc acum <b>${esc(C.ref.n)}</b>`:`Nicio sală aleasă pentru calcule. Folosim ipoteza de ${lei(S.settings.assumedPrice)} / persoană.`}</div></div><button class="btn" data-go="sali">Toate sălile</button></div>
      <div class="pipeline-mini">${counts.map((c,i)=>`<div class="pm"><b class="num">${c}</b><span>${STAGES[i]}</span></div>`).join('')}</div>
    </section>
    <section class="panel c6">
      <div class="ph"><div><h2>Următorii pași</h2><div class="sub">Din calendar</div></div><button class="btn" data-go="calendar">Calendar</button></div>
      <div class="todo"><ul>${nextTasks.map(taskLi).join('') || '<li class="muted">Toți pașii sunt bifați.</li>'}</ul></div>
    </section>
    <section class="panel c6">
      <div class="ph"><div><h2>Ultimele modificări</h2><div class="sub">Ce s-a schimbat și când</div></div></div>
      <ul class="log">${LOGS.slice(0,8).map(l=>`<li><span>${l.who?`<b>${esc(l.who)}</b> · `:''}${esc(l.m)}</span><time>${rel(l.t)}</time></li>`).join('') || '<li class="muted">Încă nimic. Încercați să marcați un invitat ca „Vine”.</li>'}</ul>
    </section>
  </div>`;
}
function taskLi(t){
  return `<li class="${t.done?'done':''}"><button class="chk" role="checkbox" aria-checked="${t.done}" data-act="task" data-id="${t.id}" aria-label="Bifează">${t.done?I.check:''}</button><div><div class="t">${esc(t.title)}</div>${t.note?`<div class="d">${esc(t.note)}</div>`:''}</div><span class="when">${fmtDate(t.date)}</span></li>`;
}

/* ---------- Invitați ---------- */
function hhMatches(h){
  const ms = membersOf(h.id);
  if (UI.cat!=='all' && h.c!==UI.cat) return false;
  if (UI.gf==='solo' && !h.solo) return false;
  const gf = UI.gf;
  if (gf==='unnamed' && (h.fam || h.solo)) return false;
  if (gf==='ph' && !ms.some(m=>m.ph)) return false;
  if (gf==='noinv' && h.inv) return false;
  if (gf==='conf' && !ms.some(m=>m.r===1)) return false;
  if (gf==='pend' && !ms.some(m=>m.r===0)) return false;
  if (gf==='decl' && !ms.some(m=>m.r===2)) return false;
  if (UI.q){
    const q = UI.q.toLowerCase();
    const hay = [h.n,h.fam,h.id,...ms.map(m=>m.n)].join(' ').toLowerCase();
    if (!hay.includes(q)) return false;
  }
  return true;
}
function vGuests(C){
  NUM = numbering();
  const list = orderedHH().filter(hhMatches);
  const pc = list.reduce((a,h)=>a+membersOf(h.id).length,0);
  const f = [['all','Toate'],['pend','Așteaptă răspuns'],['conf','Vin'],['decl','Nu vin'],['noinv','Fără invitație'],['unnamed','Fără nume de familie'],['ph','Locuri fără nume'],['solo','Individuali']];
  return `
  <div class="page-head"><div><h1>Invitați</h1><p>Lista e organizată pe familii. Invitația, darul și masa țin de familie, iar confirmarea e pentru fiecare persoană. Apăsați pe un nume ca să schimbați răspunsul.</p></div>
  <div style="display:flex;gap:8px;flex-wrap:wrap"><button class="btn" data-act="cats">Categorii</button><button class="btn" data-act="addsolo">${I.plus}Adaugă persoană</button><button class="btn primary" data-act="addhh">${I.plus}Adaugă familie</button></div></div>
  <div class="toolbar">
    <input class="input search" type="search" id="q" placeholder="Caută după nume, familie sau GRP-…" value="${esc(UI.q)}" aria-label="Caută invitați">
    <select class="input" id="catf" style="width:auto" aria-label="Categorie"><option value="all">Toate categoriile</option>${S.cats.map(c=>`<option value="${c.id}" ${UI.cat===c.id?'selected':''}>${esc(c.n)}</option>`).join('')}</select>
  </div>
  <div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:space-between;align-items:center;margin-bottom:14px"><div class="seg">${f.map(([k,l])=>`<button data-gf="${k}" aria-pressed="${UI.gf===k}">${l}</button>`).join('')}</div>
  <div class="vtabs"><button data-gview="cards" aria-pressed="${UI.gview!=='table'}">Familii</button><button data-gview="table" aria-pressed="${UI.gview==='table'}">Tabel</button></div></div>
  <div class="ghead"><span><b class="num">${list.filter(h=>!h.solo).length}</b> familii</span><span><b class="num">${list.filter(h=>h.solo).length}</b> individuali</span><span><b class="num">${pc}</b> persoane</span><span><b class="num">${C.conf}</b> confirmați în total</span><span>Dar înregistrat: <b data-live="giftActual">${lei(C.giftActual)}</b></span></div>
  ${!list.length ? '<div class="empty">Nicio familie nu corespunde filtrelor.</div>' : UI.gview==='table' ? guestTable(list) : `<div class="hhs">${groupedCards(list)}</div>`}`;
}
function groupedCards(list){
  let cur = null, out = '';
  for (const h of list){
    if (h.c!==cur){ cur = h.c; const n = list.filter(x=>x.c===cur); out += `<h2 class="catsec">${esc(catName(cur))}<span>${(()=>{const pc=n.reduce((a,x)=>a+membersOf(x.id).length,0);return `${n.length} ${n.length===1?'intrare':'intrări'} · ${pc} ${pc===1?'persoană':'persoane'}`})()}</span></h2>`; }
    out += hhCard(h);
  }
  return out;
}
function guestTable(list){
  const rows = [];
  for (const h of list){
    const ms = membersOf(h.id);
    ms.forEach((m,i)=>rows.push(`<tr class="${i===0?'first':''}">
      <td class="n"><span class="nr">${NUM.pn[m.id]}</span></td>
      <td class="${m.ph?'ph':''}">${esc(m.n)}</td>
      <td>${i===0?(h.solo?'<span class="muted">individual</span>':h.fam?esc(h.fam):`<span class="ph">${esc(h.n)}</span>`):''}</td>
      <td>${i===0?esc(catName(h.c)):''}</td>
      <td>${m.k?'Copil':'Adult'}</td>
      <td><select class="input" data-p="${m.id}" data-f="r" aria-label="Confirmare ${esc(m.n)}">${RS.map((r,j)=>`<option value="${j}" ${m.r===j?'selected':''}>${r}</option>`).join('')}</select></td>
      <td>${i===0?(h.inv?'Da':'Nu'):''}</td>
      <td>${i===0?esc(h.table):''}</td>
      <td class="n">${i===0&&h.dar!=null&&h.dar!==''?lei(h.dar):''}</td>
      <td class="gid">${h.id}</td></tr>`));
  }
  return `<div class="cmpwrap"><table class="gtable"><thead><tr><th class="n">Nr. crt.</th><th>Prenume</th><th>Familie</th><th>Categorie</th><th>Meniu</th><th>Confirmare</th><th>Invitație</th><th>Masa</th><th class="n">Dar (pe familie)</th><th>ID familie</th></tr></thead><tbody>${rows.join('')}</tbody></table></div>`;
}
function hhCard(h){
  const ms = membersOf(h.id), open = UI.open.has(h.id);
  const title = h.solo ? esc(hhLabel(h)) : h.fam ? esc(h.fam) : `<span class="unnamed">${esc(h.n)}</span>`;
  return `<article class="hh" data-id="${h.id}" data-open="${open?1:0}">
    <div class="hh-top">
      <div class="hh-id"><span class="hh-nr" title="Nr. crt.">${NUM.hn[h.id]}.</span><button class="hh-name" data-act="toggle" data-id="${h.id}" aria-expanded="${open}">${I.caret}${title}</button>
        ${h.solo?'<span class="tag wine">individual</span>':h.fam?'':'<span class="tag warn">fără nume de familie</span>'}${h.table?`<span class="tag gold">Masa ${esc(h.table)}</span>`:''}</div>
      <div class="hh-side">
        <button class="toggle" aria-pressed="${!!h.inv}" data-act="inv" data-id="${h.id}"><i></i>Invitație trimisă</button>
        <label class="dar">Dar <input class="input num" inputmode="numeric" data-hh="${h.id}" data-f="dar" value="${h.dar!=null&&h.dar!==''?esc(h.dar):''}" placeholder="—" aria-label="Dar ${esc(hhLabel(h))}"> lei</label>
        <span class="gid">${h.id}</span>
      </div>
      <div class="members">${ms.map(m=>`<button class="chip r${m.r} ${m.ph?'ph':''}" data-act="cycle" data-id="${m.id}" title="Nr. ${NUM.pn[m.id]} · ${RS[m.r]} · apasă pentru a schimba"><span class="nr">${NUM.pn[m.id]}</span><i></i>${esc(m.n)}${m.k?'<small>copil</small>':''}</button>`).join('')}</div>
    </div>
    ${open?`<div class="hh-body">
      <div class="hh-fields">
        ${h.solo?'':`<label class="field"><span>Nume de familie</span><input class="input" data-hh="${h.id}" data-f="fam" value="${esc(h.fam)}" placeholder="de completat"></label>`}
        <label class="field"><span>Categorie</span><select class="input" data-hh="${h.id}" data-f="c">${S.cats.map(c=>`<option value="${c.id}" ${h.c===c.id?'selected':''}>${esc(c.n)}</option>`).join('')}</select></label>
        <label class="field"><span>Masa</span><input class="input" data-hh="${h.id}" data-f="table" value="${esc(h.table)}"></label>
        <label class="field"><span>Observații</span><input class="input" data-hh="${h.id}" data-f="notes" value="${esc(h.notes)}"></label>
      </div>
      <div class="mrows">${ms.map(m=>`<div class="mr">
        <input title="Nr. crt. ${NUM.pn[m.id]}" class="input" data-p="${m.id}" data-f="n" value="${esc(m.n)}" aria-label="${h.solo?'Nume și prenume':'Prenume'}" placeholder="${h.solo?'Nume și prenume':'Prenume'}">
        <select class="input" data-p="${m.id}" data-f="k" aria-label="Meniu"><option value="0" ${!m.k?'selected':''}>Adult</option><option value="1" ${m.k?'selected':''}>Copil</option></select>
        <select class="input" data-p="${m.id}" data-f="r" aria-label="Confirmare">${RS.map((r,i)=>`<option value="${i}" ${m.r===i?'selected':''}>${r}</option>`).join('')}</select>
        <button class="iconbtn" data-act="delp" data-id="${m.id}" aria-label="Șterge ${esc(m.n)}">${I.x}</button></div>`).join('')}</div>
      ${h.solo?'':`<div class="quick"><button class="btn" data-act="addp" data-id="${h.id}">${I.plus}Adaugă persoană în familie</button>
        <span style="margin-left:auto">Toată familia:</span>
        <button class="btn" data-act="all" data-v="1" data-id="${h.id}">Vin</button><button class="btn" data-act="all" data-v="2" data-id="${h.id}">Nu vin</button></div>`}
      <div class="hh-actions">
        ${h.solo?`<button class="btn ghost" data-act="solo" data-id="${h.id}">Transformă în familie</button>`:ms.length<=1?`<button class="btn ghost" data-act="solo" data-id="${h.id}">Marchează ca persoană individuală</button>`:''}
        <button class="btn danger" style="margin-left:auto" data-act="delhh" data-id="${h.id}">${h.solo?'Șterge persoana':'Șterge familia'}</button>
      </div>
    </div>`:''}
  </article>`;
}

/* ---------- Săli ---------- */
function vVenues(C){
  const e = Math.round(C.expected);
  const ref = C.ref;
  const head = `<div class="page-head"><div><h1>Săli</h1><p>Fiecare sală trece prin aceleași etape, de la primul contact la rezervare. Costul estimat se calculează pentru cei ${n0(e)} invitați așteptați acum.</p></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap"><div class="vtabs"><button data-vtab="board" aria-pressed="${UI.vtab==='board'}">Etape</button><button data-vtab="cmp" aria-pressed="${UI.vtab==='cmp'}">Comparație${S.v.filter(v=>v.cmp).length?` (${S.v.filter(v=>v.cmp).length})`:''}</button></div><button class="btn primary" data-act="addv">${I.plus}Adaugă sală</button></div></div>
    <div class="ref">${ref?`<span>Calculele folosesc <strong>${esc(ref.n)}</strong>${ref.st===5?' (rezervată)':''}: ${ref.ppp?lei(ref.ppp)+' / pers.':'preț necunoscut, deci rămâne ipoteza'}</span>`:`<span>Nicio sală aleasă pentru calcule. Până primiți oferte, folosim ipoteza de <strong>${lei(S.settings.assumedPrice)}</strong> / persoană.</span>`}
    <label class="dar" style="margin-left:auto">Ipoteză <input class="input num" data-set="assumedPrice" value="${S.settings.assumedPrice}" inputmode="numeric" aria-label="Preț ipotetic per persoană"> lei / pers.</label></div>`;
  if (UI.vtab==='cmp') return head + venueCompare(C);
  const searchBar = `<div class="toolbar"><input class="input vsearch" type="search" id="vq" placeholder="Caută o sală" value="${esc(UI.vq)}" aria-label="Caută săli"></div>`;
  const cols = STAGES.map((s,i)=>i).filter(i=>i<6 || UI.showRejected);
  return head + searchBar + `<div class="board">${cols.map(i=>{
    const vs = S.v.filter(v=>v.st===i && (!UI.vq || v.n.toLowerCase().includes(UI.vq.toLowerCase()))).sort((a,b)=>(b.rt||0)-(a.rt||0));
    return `<div class="col"><h3><span>${STAGES[i]}</span><b class="num">${vs.length}</b></h3>${vs.map(v=>venueCard(v,C)).join('') || '<div class="col-empty">Nicio sală în această etapă.</div>'}</div>`;
  }).join('')}</div>
  <button class="link" data-act="rej" style="margin-top:8px">${UI.showRejected?'Ascunde sălile respinse':`Arată sălile respinse (${S.v.filter(v=>v.st===6).length})`}</button>`;
}
function venueCard(v,C){
  const [fl,fc] = fit(v,C);
  const isRef = C.ref && C.ref.id===v.id;
  const est = v.ppp ? lei(venueTotal(v,C)) : null;
  const nf = offerOf(v).files.length;
  return `<button class="vc ${isRef?'isref':''}" data-act="openv" data-id="${v.id}">
    <span class="vn">${esc(v.n)}</span>
    <span class="vm">${v.rt?`<span class="r">★ ${String(v.rt).replace('.',',')} (${n0(v.rc)})</span>`:''}<span class="tag ${fc}">${fl}</span>${isRef?'<span class="tag gold">în calcule</span>':''}${v.cmp?'<span class="tag wine">compar</span>':''}${nf?`<span class="tag gold">ofertă${nf>1?' ×'+nf:''}</span>`:''}</span>
    <span class="cost">${est?`Estimat <b>${est}</b> · ${lei(v.ppp)}/pers.`:'Preț neprimit încă'}</span>
  </button>`;
}
function venueCompare(C){
  const vs = S.v.filter(v=>v.cmp);
  if (!vs.length) return `<div class="empty">Deschideți o sală și apăsați „Adaugă la comparație”. Puteți compara oricâte vreți.</div>`;
  const rows = [
    ['Etapă', v=>STAGES[v.st]],
    ['Capacitate', v=>{const [l,c]=fit(v,C);return `<span class="tag ${c}">${l}</span>`}],
    ['Disponibilitate', v=>esc(v.av)||'<span class="muted">—</span>'],
    ['Preț adult', v=>v.ppp?lei(v.ppp):'<span class="muted">—</span>'],
    ['Preț copil', v=>v.cp?lei(v.cp):(v.ppp?`<span class="muted">${lei(v.ppp*S.settings.childPct/100)} (estimat)</span>`:'<span class="muted">—</span>')],
    ['Costuri extra fixe', v=>offerOf(v).extra?lei(offerOf(v).extra):'<span class="muted">—</span>'],
    ['Total pentru '+n0(C.expected)+' invitați', v=>v.ppp?'<b>'+lei(venueTotal(v,C))+'</b>':'<span class="muted">—</span>'],
    ['Băuturi', v=>+offerOf(v).drinks?DRINKS[offerOf(v).drinks]:'<span class="muted">—</span>'],
    ['Ce include', v=>esc(offerOf(v).inc)||'<span class="muted">—</span>'],
    ['Avans', v=>esc(offerOf(v).deposit)||'<span class="muted">—</span>'],
    ['Data ofertei', v=>offerOf(v).date?fmtDate(offerOf(v).date)+(offerOf(v).valid?`<br><small class="muted">valabilă până pe ${fmtDate(offerOf(v).valid)}</small>`:''):'<span class="muted">—</span>'],
    ['Oferta atașată', v=>offerOf(v).files.length?offerOf(v).files.map(f=>`<button class="link" data-act="viewfile" data-vid="${v.id}" data-id="${f.id}">${esc(f.name.length>28?f.name.slice(0,26)+'…':f.name)}</button>`).join('<br>'):'<span class="muted">nicio ofertă</span>'],
    ['Rating public', v=>v.rt?`★ ${String(v.rt).replace('.',',')} (${n0(v.rc)})`:'—'],
    ...SCORE_LABELS.map((l,i)=>['Notă '+l.toLowerCase(), v=>v.sc[i]?'★'.repeat(v.sc[i]):'<span class="muted">—</span>']),
    ['Media notelor voastre', v=>{const s=venueScore(v);return s?s.toFixed(1).replace('.',','):'<span class="muted">—</span>'}],
    ['Note', v=>esc(v.nt||v.cond)]
  ];
  return `<div class="cmpwrap"><table><thead><tr><th></th>${vs.map(v=>`<th><button class="link" data-act="openv" data-id="${v.id}">${esc(v.n)}</button></th>`).join('')}</tr></thead><tbody>${rows.map(([l,f])=>`<tr><th scope="row">${l}</th>${vs.map(v=>`<td>${f(v)}</td>`).join('')}</tr>`).join('')}</tbody></table></div>`;
}
function renderDrawer(C){
  const d = $('#drawer');
  if (!UI.drawer){ return; }
  if (UI.drawer==='__cats'){
    const active = document.activeElement && d.contains(document.activeElement) ? document.activeElement : null;
    if (active && active.matches('input[type=text],input:not([type])')) return;
    d.innerHTML = `<div class="dh"><div><h2>Categorii de invitați</h2><div class="sub muted" style="margin-top:4px">Ordinea de aici e ordinea din lista de invitați și din numerotare.</div></div><button class="iconbtn" data-act="close" aria-label="Închide">${I.x}</button></div><div class="db">${catEditor()}</div>`;
    return;
  }
  const v = byId(S.v, UI.drawer);
  if (!v){ closeDrawer(); return; }
  const isRef = C.ref && C.ref.id===v.id;
  const kid = v.cp || v.ppp*S.settings.childPct/100;
  const html = `
    <div class="dh"><div><h2>${esc(v.n)}</h2><div class="sub muted" style="margin-top:4px">Iași${v.rt?` · ★ ${String(v.rt).replace('.',',')} din ${n0(v.rc)} recenzii`:''}</div></div><button class="iconbtn" data-act="close" aria-label="Închide">${I.x}</button></div>
    <div class="db">
      <div class="field"><span>Etapă</span><div class="stages">${STAGES.map((s,i)=>`<button data-act="stage" data-i="${i}" aria-pressed="${v.st===i}">${s}</button>`).join('')}</div></div>
      <div class="calc">
        ${v.ppp?`<div class="eq"><b class="num">${n0(C.expA)}</b> adulți × <b>${lei(v.ppp)}</b> + <b class="num">${n0(C.expK)}</b> copii × <b>${lei(kid)}</b></div><div>Cost estimat: <b>${lei(venueTotal(v,C))}</b>${num(offerOf(v).extra)?` <span class="muted">(inclusiv ${lei(offerOf(v).extra)} costuri extra)</span>`:''}</div>`:'<div>Când introduceți prețul pe persoană, costul se calculează automat pentru invitații așteptați.</div>'}
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px">
          ${v.st===5?'<span class="tag gold">Rezervată: e folosită automat în calcule</span>':`<button class="btn ${isRef?'':'primary'}" data-act="setref">${isRef?'Scoate din calcule':'Folosește în calcule'}</button>`}
          <button class="btn" data-act="cmp">${v.cmp?'Scoate din comparație':'Adaugă la comparație'}</button>
        </div>
      </div>
      ${offerBlock(v)}
      <div class="df">
        <label class="field"><span>Preț meniu adult (lei)</span><input class="input num" inputmode="numeric" data-v="${v.id}" data-f="ppp" value="${v.ppp||''}"></label>
        <label class="field"><span>Preț meniu copil (lei)</span><input class="input num" inputmode="numeric" data-v="${v.id}" data-f="cp" value="${v.cp||''}" placeholder="${v.ppp?n0(v.ppp*S.settings.childPct/100)+' (estimat)':''}"></label>
        <label class="field"><span>Capacitate maximă</span><input class="input num" inputmode="numeric" data-v="${v.id}" data-f="cap" value="${v.cap||''}"></label>
        <label class="field"><span>Minim de persoane</span><input class="input num" inputmode="numeric" data-v="${v.id}" data-f="min" value="${v.min||''}"></label>
        <label class="field full"><span>Disponibilitate pentru data voastră</span><input class="input" data-v="${v.id}" data-f="av" value="${esc(v.av)}" placeholder="ex. liber pe 12 iunie, ocupat pe 19"></label>
      </div>
      <div class="field"><span>Notele voastre (1–5)</span><div class="scores">${SCORE_LABELS.map((l,i)=>`<div class="score"><span>${l}</span><div class="stars">${[1,2,3,4,5].map(n=>`<button class="${v.sc[i]>=n?'on':''}" data-act="star" data-i="${i}" data-n="${n}" aria-label="${l} ${n}">★</button>`).join('')}</div></div>`).join('')}</div></div>
      <label class="field"><span>Note, condiții, ce include</span><textarea class="input" data-v="${v.id}" data-f="nt" placeholder="${esc(v.cond)}">${esc(v.nt)}</textarea></label>
      ${v.url?`<p class="hint">Rating din <a href="${esc(v.url)}" target="_blank" rel="noopener" style="color:var(--wine)">directorul de locații</a>; verificați înainte de decizie.</p>`:''}
    </div>`;
  const active = document.activeElement && d.contains(document.activeElement) ? document.activeElement : null;
  if (active && active.matches('input:not([type=file]),textarea')) return; // nu suprascrie câmpul în care scrieți
  d.innerHTML = html;
}
function offerBlock(v){
  const o = offerOf(v);
  return `<section class="offer">
    <div><h3>Oferta primită</h3><div class="sub muted">Atașați PDF-ul sau pozele ofertei și completați câmpurile de mai jos, ca să puteți compara sălile.</div></div>
    <label class="drop" id="drop" for="offerFile"><b>Atașează oferta</b><span>PDF sau imagine · trageți fișierul aici sau apăsați</span></label>
    <input type="file" id="offerFile" accept="application/pdf,image/*" multiple hidden>
    ${o.files.length?`<div class="files">${o.files.map(f=>`<div class="file"><span class="fi">${f.type==='application/pdf'?'PDF':'IMG'}</span><span class="fn" title="${esc(f.name)}">${esc(f.name)}<br><small>${fsize(f.size)} · ${new Date(f.added).toLocaleDateString('ro-RO')}</small></span><button class="btn" data-act="viewfile" data-id="${f.id}">Vezi</button><button class="iconbtn" data-act="delfile" data-id="${f.id}" aria-label="Șterge ${esc(f.name)}">${I.x}</button></div>`).join('')}</div>`:''}
    <div class="df">
      <label class="field"><span>Data ofertei</span><input class="input" type="date" data-of="${v.id}" data-f="date" value="${esc(o.date)}"></label>
      <label class="field"><span>Valabilă până la</span><input class="input" type="date" data-of="${v.id}" data-f="valid" value="${esc(o.valid)}"></label>
      <label class="field"><span>Băuturi</span><select class="input" data-of="${v.id}" data-f="drinks">${DRINKS.map((d,i)=>`<option value="${i}" ${+o.drinks===i?'selected':''}>${d}</option>`).join('')}</select></label>
      <label class="field"><span>Avans cerut</span><input class="input" data-of="${v.id}" data-f="deposit" value="${esc(o.deposit)}" placeholder="ex. 30% la semnare"></label>
      <label class="field full"><span>Costuri extra fixe (lei): taxă sală, DJ, decor, ore suplimentare</span><input class="input num" inputmode="numeric" data-of="${v.id}" data-f="extra" value="${o.extra||''}"></label>
      <label class="field full"><span>Ce include oferta</span><textarea class="input" data-of="${v.id}" data-f="inc" placeholder="ex. meniu 4 feluri, tort, aranjamente florale, cazare miri">${esc(o.inc)}</textarea></label>
    </div>
  </section>`;
}
function catEditor(){
  const last = S.cats.length-1;
  return `<div class="cats">${S.cats.map((c,i)=>{
    const cnt = S.hh.filter(h=>h.c===c.id).length;
    if (UI.catDel===c.id) return `<div class="catdel"><span>„${esc(c.n)}” are ${cnt} ${cnt===1?'intrare':'intrări'}. Le mut în</span>
      <select class="input" id="catMove">${S.cats.filter(x=>x.id!==c.id).map(x=>`<option value="${x.id}">${esc(x.n)}</option>`).join('')}</select>
      <button class="btn danger" data-act="catdelok" data-id="${c.id}">Șterge categoria</button><button class="btn ghost" data-act="catdelno">Renunță</button></div>`;
    return `<div class="catrow"><span class="nr">${i+1}.</span><input class="input" data-cat="${c.id}" value="${esc(c.n)}" aria-label="Nume categorie">
      <span class="cnt">${cnt} ${cnt===1?'intrare':'intrări'}</span>
      <span class="moves"><button class="iconbtn" data-act="catup" data-id="${c.id}" ${i===0?'disabled':''} aria-label="Mută mai sus">${I.up}</button><button class="iconbtn" data-act="catdown" data-id="${c.id}" ${i===last?'disabled':''} aria-label="Mută mai jos">${I.down}</button><button class="iconbtn" data-act="catdel" data-id="${c.id}" ${S.cats.length<2?'disabled':''} aria-label="Șterge ${esc(c.n)}">${I.x}</button></span></div>`;
  }).join('')}</div>
  <form class="catadd" id="catForm"><input class="input" id="catNew" placeholder="Categorie nouă, ex. Vecini" aria-label="Categorie nouă"><button class="btn primary">${I.plus}Adaugă</button></form>`;
}
function openDrawer(id){ UI.drawer = id; $('#drawer').classList.add('on'); $('#drawer').setAttribute('aria-hidden','false'); $('#scrim').classList.add('on'); renderDrawer(compute()); }
function closeDrawer(silent){ UI.drawer = null; $('#drawer').classList.remove('on'); $('#drawer').setAttribute('aria-hidden','true'); $('#scrim').classList.remove('on'); if(!silent) render(); }

/* ---------- Buget ---------- */
function vBudget(C){
  const st = S.settings;
  return `<div class="page-head"><div><h1>Buget</h1><p>Linia de sală și meniu se calculează singură din invitații așteptați și sala folosită în calcule. Când un furnizor e rezervat, prețul lui devine oferta liniei, iar plățile lui intră la „Achitat”.</p></div>
  <label class="field" style="width:200px"><span>Limita de buget (lei)</span><input class="input num" inputmode="numeric" data-set="budget" value="${st.budget}"></label></div>
  <div class="cmpwrap blines"><table>
    <thead><tr><th>Categorie</th><th class="n">Estimare</th><th class="n">Ofertă</th><th class="n">Achitat</th><th class="n">Contează</th></tr></thead>
    <tbody>${C.lines.map(b=>`<tr>
      <td class="cat">${b.link==='venue'?`<div style="font-weight:600">${esc(b.cat)}</div><span class="linked">${I.link}${n0(C.expected)} invitați × ${lei(C.price)} (${esc(C.priceSrc)})</span>`:`<input class="input" data-b="${b.id}" data-f="cat" value="${esc(b.cat)}" aria-label="Categorie">`}</td>
      <td class="n">${b.link==='venue'?lei(b.est):`<input class="input num" inputmode="numeric" data-b="${b.id}" data-f="est" value="${b.est||''}" aria-label="Estimare">`}</td>
      <td class="n">${b.fromVendors?`<span class="linked">${I.link}${lei(b.quote)}</span>`:`<input class="input num" inputmode="numeric" data-b="${b.id}" data-f="quote" value="${num(S.budget.find(x=>x.id===b.id).quote)||''}" aria-label="Ofertă">`}</td>
      <td class="n"><input class="input num" inputmode="numeric" data-b="${b.id}" data-f="paid" value="${num(S.budget.find(x=>x.id===b.id).paid)||''}" aria-label="Achitat">${b.vPaid?`<div class="linked" style="justify-content:flex-end">+${lei(b.vPaid)} furnizori</div>`:''}</td>
      <td class="n"><b>${lei(b.eff)}</b></td></tr>`).join('')}
      <tr class="bsum"><td>Total</td><td></td><td></td><td class="n" data-live="paid">${lei(C.paid)}</td><td class="n" data-live="cost">${lei(C.cost)}</td></tr>
    </tbody></table></div>
  <p class="hint">„Contează” folosește oferta când există și estimarea când nu. <button class="link" data-act="addb">Adaugă o categorie</button></p>
  <section class="panel" style="margin-top:22px">
    <div class="ph"><div><h2>Daruri</h2><div class="sub">Darul se trece o singură dată, pe familie. Pentru familiile fără dar trecut, estimăm din ipotezele de mai jos și din câți adulți vin.</div></div></div>
    <div class="gifts">
      <label class="field"><span>Dar mediu pe adult (lei)</span><input class="input num" inputmode="numeric" data-set="giftAvg" value="${st.giftAvg}"></label>
      <label class="field"><span>Procent care oferă dar</span><input class="input num" inputmode="numeric" data-set="giftRate" value="${st.giftRate}"></label>
      <div class="money" style="gap:6px">
        <div class="mrow"><span>Înregistrat (${C.hhWithDar} familii)</span><b data-live="giftActual">${lei(C.giftActual)}</b></div>
        <div class="mrow"><span>Estimat pentru restul</span><b data-live="giftForecast">${lei(C.giftForecast)}</b></div>
      </div>
    </div>
  </section>`;
}

/* ---------- Calendar ---------- */
function vCal(){
  const ts = S.tasks.slice().sort((a,b)=>a.date.localeCompare(b.date));
  return `<div class="page-head"><div><h1>Calendar</h1><p>Pașii mari până la nuntă, în ordinea datelor.</p></div></div>
  <section class="panel"><div class="todo"><ul>${ts.map(t=>taskLi(t).replace('</li>',`<button class="iconbtn" data-act="deltask" data-id="${t.id}" aria-label="Șterge">${I.x}</button></li>`).replace('grid-template-columns','')).join('')}</ul></div>
  <form id="taskForm" class="addform" style="margin-top:16px;grid-template-columns:minmax(0,2fr) minmax(0,1fr) auto">
    <label class="field"><span>Pas nou</span><input class="input" id="tTitle" required placeholder="ex. Probă de meniu"></label>
    <label class="field"><span>Dată</span><input class="input" type="date" id="tDate" required></label>
    <button class="btn primary">${I.plus}Adaugă</button>
  </form></section>`;
}

/* ---------- Furnizori ---------- */
function vVendors(){
  const st = ['De contactat','Ofertă cerută','Ofertă primită','Rezervat','Renunțat'];
  return `<div class="page-head"><div><h1>Furnizori</h1><p>Fiecare furnizor ține de o categorie din buget. Când îl marcați „Rezervat”, prețul lui intră în buget, iar plățile bifate ca achitate se adună automat.</p></div></div>
  <div class="vendors">${S.vendors.map(v=>`<div class="vend">
    <div class="vend-top"><div><b>${esc(v.name)}</b> <span class="tag">${esc((S.budget.find(b=>b.id===v.line)||{}).cat||'')}</span></div>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <select class="input" style="width:auto" data-vd="${v.id}" data-f="status">${st.map(s=>`<option ${v.status===s?'selected':''}>${s}</option>`).join('')}</select>
        <label class="dar">Preț <input class="input num" data-vd="${v.id}" data-f="price" value="${v.price||''}" inputmode="numeric"> lei</label>
        <button class="iconbtn" data-act="delvd" data-id="${v.id}" aria-label="Șterge furnizor">${I.x}</button></div></div>
    <div class="pays">${v.pays.map((p,i)=>`<div class="pay">
      <input class="input" data-pay="${v.id}" data-i="${i}" data-f="label" value="${esc(p.label)}" aria-label="Plată">
      <input class="input" type="date" data-pay="${v.id}" data-i="${i}" data-f="date" value="${esc(p.date)}" aria-label="Scadență">
      <input class="input num" data-pay="${v.id}" data-i="${i}" data-f="amount" value="${p.amount||''}" inputmode="numeric" aria-label="Sumă">
      <button class="toggle" aria-pressed="${!!p.done}" data-act="paydone" data-id="${v.id}" data-i="${i}"><i></i>Achitat</button>
      <button class="iconbtn" data-act="delpay" data-id="${v.id}" data-i="${i}" aria-label="Șterge plată">${I.x}</button></div>`).join('')}
      <div><button class="btn ghost" data-act="addpay" data-id="${v.id}">${I.plus}Adaugă plată (avans, rest)</button></div></div>
  </div>`).join('') || '<div class="empty">Niciun furnizor încă. Adăugați primul mai jos: fotograf, formație, florărie…</div>'}</div>
  <section class="panel" style="margin-top:16px"><form id="vendForm" class="addform">
    <label class="field"><span>Nume</span><input class="input" id="vName" required placeholder="ex. Studio Lumina"></label>
    <label class="field"><span>Categorie din buget</span><select class="input" id="vLine">${S.budget.filter(b=>!b.link).map(b=>`<option value="${b.id}">${esc(b.cat)}</option>`).join('')}</select></label>
    <label class="field"><span>Preț (lei)</span><input class="input num" id="vPrice" inputmode="numeric"></label>
    <label class="field"><span>Stadiu</span><select class="input" id="vStatus">${st.map(s=>`<option>${s}</option>`).join('')}</select></label>
    <button class="btn primary">${I.plus}Adaugă</button>
  </form></section>`;
}

/* ---------- Setări ---------- */
function vSettings(){
  const st = S.settings;
  return `<div class="page-head"><div><h1>Setări</h1><p>Ipotezele de la care pleacă toate calculele.</p></div></div>
  <section class="panel"><div class="sgrid">
    <label class="field"><span>Data nunții</span><input class="input" type="date" data-set="date" value="${st.date}"></label>
    <label class="field"><span>Limita de buget (lei)</span><input class="input num" data-set="budget" value="${st.budget}" inputmode="numeric"></label>
    <label class="field"><span>Preț ipotetic meniu / pers. (lei)</span><input class="input num" data-set="assumedPrice" value="${st.assumedPrice}" inputmode="numeric"><p class="hint">Folosit până alegeți o sală cu preț.</p></label>
    <label class="field"><span>Meniul de copil, % din cel de adult</span><input class="input num" data-set="childPct" value="${st.childPct}" inputmode="numeric"></label>
    <label class="field"><span>Câți vin dintre cei fără răspuns (%)</span><input class="input num" data-set="pendingRate" value="${st.pendingRate}" inputmode="numeric"></label>
    <label class="field"><span>Dar mediu pe adult (lei)</span><input class="input num" data-set="giftAvg" value="${st.giftAvg}" inputmode="numeric"></label>
  </div></section>
  <section class="panel" style="margin-top:16px"><div class="ph"><div><h2>Categorii de invitați</h2><div class="sub">Redenumiți, schimbați ordinea, adăugați sau ștergeți. Ordinea de aici e ordinea din lista de invitați.</div></div></div>
  <div style="max-width:640px">${catEditor()}</div></section>
  <section class="panel" style="margin-top:16px"><div class="ph"><div><h2>Date și backup</h2><div class="sub">Datele stau pe serverul site-ului și se sincronizează între dispozitive. Backup-ul complet și importul se fac din WordPress, meniul Nunta 2027.</div></div></div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">${CFG.admin?`<a class="btn" href="${esc(CFG.admin)}">Deschide panoul din WordPress</a>`:''}<button class="btn" data-act="whoami">Pe acest dispozitiv: ${esc(WHO||'nesetat')} · schimbă</button></div>
  <p class="hint">Versiunea ${esc(CFG.version||'')}</p></section>`;
}

/* ---------- evenimente ---------- */
let toastT, UNDO=null;
function toast(m, undo){ const t=$('#toast'); t.innerHTML = esc(m) + (undo?'<button class="undo" data-act="undo">Anulează</button>':''); UNDO = undo||null; t.classList.toggle('act',!!undo); t.classList.add('on'); clearTimeout(toastT); toastT=setTimeout(()=>{t.classList.remove('on','act'); UNDO=null;}, undo?6000:2200); }
const snap = () => JSON.stringify(S);
function commit(msg, full=true){ if(msg) log(msg); save(); full ? render() : soft(); }

function applyTheme(m){
  const r = document.documentElement;
  if (m==='light'||m==='dark') r.setAttribute('data-theme',m); else r.removeAttribute('data-theme');
  document.querySelectorAll('[data-theme-set]').forEach(b=>b.setAttribute('aria-pressed', String(b.dataset.themeSet===(m||'auto'))));
  try{ localStorage.setItem(LS+'-theme', m||'auto'); }catch(e){}
}
let THEME='auto'; try{ THEME = localStorage.getItem(LS+'-theme')||'auto'; }catch(e){}
applyTheme(THEME);

document.addEventListener('click', e=>{
  const wb = e.target.closest('[data-who]'); if (wb){ WHO = wb.dataset.who; try{ localStorage.setItem(LS+'-who', WHO); }catch(x){} wb.closest('.whobox').remove(); toast(`Salut, ${WHO}!`); if (UI.view==='setari') render(); return; }
  const sa = e.target.closest('.sync-act'); if (sa){ if (sa.dataset.mode==='reload') location.reload(); else { SYNC.fails = 0; schedule(0); } return; }
  const th = e.target.closest('[data-theme-set]'); if (th){ applyTheme(th.dataset.themeSet); return; }
  const g = e.target.closest('[data-go]'); if (g){ go(g.dataset.go); return; }
  const gf = e.target.closest('[data-gf]'); if (gf){ UI.gf = gf.dataset.gf; if (UI.view!=='invitati') go('invitati'); else render(); return; }
  const gv = e.target.closest('[data-gview]'); if (gv){ UI.gview = gv.dataset.gview; render(); return; }
  const vt = e.target.closest('[data-vtab]'); if (vt){ UI.vtab = vt.dataset.vtab; render(); return; }
  const a = e.target.closest('[data-act]'); if (!a) return;
  const id = a.dataset.id, act = a.dataset.act;
  const C0 = () => compute();
  switch(act){
    case 'toggle': UI.open.has(id)?UI.open.delete(id):UI.open.add(id); render(); break;
    case 'cycle': { const p = byId(S.p,id); p.r = (p.r+1)%3; commit(`${p.n}: ${RS[p.r].toLowerCase()}`); break; }
    case 'all': { const v=+a.dataset.v; membersOf(id).forEach(m=>m.r=v); const h=byId(S.hh,id); commit(`${hhLabel(h)}: toată familia ${v===1?'vine':'nu vine'}`); break; }
    case 'inv': { const h = byId(S.hh,id); h.inv = !h.inv; commit(`${hhLabel(h)}: invitație ${h.inv?'trimisă':'netrimisă'}`); break; }
    case 'addp': { S.p.push({id:nextP(),h:id,n:'Persoană nouă',k:0,r:0,ph:1}); commit('Persoană adăugată'); break; }
    case 'delp': { const s0=snap(); const p = byId(S.p,id); S.p = S.p.filter(x=>x.id!==id); if (!membersOf(p.h).length){ S.hh = S.hh.filter(x=>x.id!==p.h); UI.open.delete(p.h); } commit(`${p.n} a fost scos din listă`); toast(`${p.n} a fost scos din listă`, s0); break; }
    case 'undo': if (UNDO){ S = migrate(JSON.parse(UNDO)); UNDO=null; commit('Ultima ștergere a fost anulată'); $('#toast').classList.remove('on','act'); } break;
    case 'addsolo': { const hid = nextId('IND-', S.hh); S.hh.push({id:hid,n:'',fam:'',solo:true,c:defaultCat(),inv:0,dar:null,notes:'',table:''}); S.p.push({id:nextP(),h:hid,n:'Persoană nouă',k:0,r:0,ph:1}); UI.open.add(hid); UI.gf='all'; UI.q=hid; UI.cat='all'; UI.gview='cards'; commit('Persoană individuală adăugată'); setTimeout(()=>{ const i=document.querySelector(`[data-id="${hid}"] [data-f="n"]`); if(i){ i.focus(); i.select(); } },0); break; }
    case 'delhh': { const s0=snap(); const h = byId(S.hh,id); const lbl = hhLabel(h); S.hh = S.hh.filter(x=>x.id!==id); S.p = S.p.filter(p=>p.h!==id); UI.open.delete(id); if (UI.q===id) UI.q=''; const msg = `${lbl}: ${h.solo?'persoană ștearsă':'familie ștearsă'} din listă`; commit(msg); toast(msg, s0); break; }
    case 'solo': { const h = byId(S.hh,id); if (!h.solo && membersOf(id).length>1){ toast('Doar o familie cu o singură persoană poate deveni individuală'); break; } h.solo = !h.solo; commit(`${hhLabel(h)}: ${h.solo?'persoană individuală':'familie'}`); break; }
    case 'cats': UI.catDel=null; openDrawer('__cats'); break;
    case 'catup': case 'catdown': { const i = S.cats.findIndex(c=>c.id===id), j = i+(act==='catup'?-1:1); if (j<0||j>=S.cats.length) break; [S.cats[i],S.cats[j]] = [S.cats[j],S.cats[i]]; const oi = S.cats[i].o; S.cats[i].o = S.cats[j].o; S.cats[j].o = oi; commit(`Categoria ${S.cats[j].n} mutată ${act==='catup'?'mai sus':'mai jos'}`); break; }
    case 'catdel': { if (S.cats.length<2) break; const c = S.cats.find(x=>x.id===id); const cnt = S.hh.filter(h=>h.c===id).length; if (!cnt){ const s0=snap(); S.cats = S.cats.filter(x=>x.id!==id); if (UI.cat===id) UI.cat='all'; commit(`Categoria ${c.n} a fost ștearsă`); toast(`Categoria ${c.n} a fost ștearsă`, s0); } else { UI.catDel = id; render(); } break; }
    case 'catdelno': UI.catDel=null; render(); break;
    case 'catdelok': { const s0=snap(); const c = S.cats.find(x=>x.id===id), to = $('#catMove').value; const cnt = S.hh.filter(h=>h.c===id).length; S.hh.forEach(h=>{ if (h.c===id) h.c = to; }); S.cats = S.cats.filter(x=>x.id!==id); UI.catDel=null; if (UI.cat===id) UI.cat='all'; commit(`Categoria ${c.n} ștearsă; ${cnt} intrări mutate în ${catName(to)}`); toast(`Categoria ${c.n} a fost ștearsă`, s0); break; }
    case 'addhh': { const hid = nextId('GRP-', S.hh); S.hh.push({id:hid,n:'Familie nouă',fam:'',c:defaultCat(),inv:0,dar:null,notes:'',table:''}); S.p.push({id:nextP(),h:hid,n:'Persoană nouă',k:0,r:0,ph:1}); UI.open.add(hid); UI.gf='all'; UI.q=hid; UI.cat='all'; UI.gview='cards'; commit('Familie nouă adăugată'); break; }
    case 'openv': openDrawer(id); break;
    case 'viewfile': viewFile(a.dataset.vid||UI.drawer, id); break;
    case 'closeviewer': { const el = a.closest('.viewer'); URL.revokeObjectURL(el.dataset.url); el.remove(); break; }
    case 'delfile': { const v = byId(S.v,UI.drawer), o = offerOf(v); const f = o.files.find(x=>x.id===id); o.files = o.files.filter(x=>x.id!==id); api('files/'+id, {method:'DELETE'}).catch(()=>{}); commit(`${v.n}: fișier șters (${f.name})`); toast('Fișierul a fost șters'); break; }
    case 'close': closeDrawer(); break;
    case 'stage': { const v = byId(S.v,UI.drawer); v.st = +a.dataset.i; if (v.st===5) S.v.forEach(x=>{ if(x!==v && x.st===5) x.st=4; }); commit(`${v.n}: ${STAGES[v.st].toLowerCase()}`); break; }
    case 'setref': { const v = byId(S.v,UI.drawer); S.settings.refVenue = S.settings.refVenue===v.id?null:v.id; commit(S.settings.refVenue?`Calculele folosesc acum ${v.n}`:'Calculele folosesc ipoteza de preț'); break; }
    case 'cmp': { const v = byId(S.v,UI.drawer); v.cmp=!v.cmp; commit(null); break; }
    case 'star': { const v = byId(S.v,UI.drawer); const i=+a.dataset.i, n=+a.dataset.n; v.sc[i] = v.sc[i]===n?0:n; commit(null); break; }
    case 'rej': UI.showRejected=!UI.showRejected; render(); break;
    case 'addv': { const n = uid('V'); S.v.push({id:n,n:'Sală nouă',cap:0,ppp:0,min:0,rt:0,rc:0,cond:'',url:'',st:0,cp:0,av:'',sc:[0,0,0,0],nt:'',cmp:false}); commit('Sală nouă adăugată'); openDrawer(n); break; }
    case 'task': { const t = byId(S.tasks,id); t.done=!t.done; commit(`${t.title}: ${t.done?'bifat':'debifat'}`); break; }
    case 'deltask': { const s0=snap(); S.tasks = S.tasks.filter(t=>t.id!==id); commit('Pas șters'); toast('Pasul a fost șters', s0); break; }
    case 'addb': { const n = S.budget.length+1; S.budget.push({id:'B'+Date.now(),cat:'Categorie nouă',est:0,quote:0,paid:0}); commit('Categorie de buget adăugată'); break; }
    case 'delvd': { const s0=snap(); S.vendors = S.vendors.filter(v=>v.id!==id); commit('Furnizor șters'); toast('Furnizorul a fost șters', s0); break; }
    case 'addpay': byId(S.vendors,id).pays.push({label:'Avans',date:'',amount:0,done:false}); commit(null); break;
    case 'delpay': byId(S.vendors,id).pays.splice(+a.dataset.i,1); commit(null); break;
    case 'paydone': { const p = byId(S.vendors,id).pays[+a.dataset.i]; p.done=!p.done; commit(`${byId(S.vendors,id).name}: ${p.label} ${p.done?'achitat':'neachitat'}`); break; }
    case 'reload': location.reload(); break;
    case 'whoami': askWho(); break;
  }
});

document.addEventListener('input', e=>{
  const t = e.target;
  if (t.id==='vq'){ UI.vq = t.value; const pos=t.selectionStart; render(); const q=$('#vq'); q.focus(); q.setSelectionRange(pos,pos); return; }
  if (t.id==='q'){ UI.q = t.value; const pos=t.selectionStart; render(); const q=$('#q'); q.focus(); q.setSelectionRange(pos,pos); return; }
  if (t.dataset.set==='pendingRate' && t.type==='range'){ S.settings.pendingRate=+t.value; $('#prv').textContent=t.value+'%'; save(); soft(); }
});
document.addEventListener('change', e=>{
  const t = e.target, f = t.dataset.f;
  if (t.id==='catf'){ UI.cat = t.value; render(); return; }
  if (t.dataset.set){
    const k = t.dataset.set; S.settings[k] = k==='date' ? t.value : num(t.value);
    if (k==='pendingRate' && t.type==='range'){ commit(`Scenariu: ${t.value}% dintre cei fără răspuns vin`, false); return; }
    commit(k==='date'?`Data nunții: ${fmtDate(t.value)}`:null, k==='date'); return;
  }
  if (t.dataset.cat){ const c = S.cats.find(x=>x.id===t.dataset.cat); const v = t.value.trim(); if (v && v!==c.n){ c.n = v; commit(`Categorie redenumită: ${v}`); } return; }
  if (t.dataset.hh){
    const h = byId(S.hh,t.dataset.hh);
    if (f==='dar'){ h.dar = t.value.trim()===''?null:num(t.value); commit(`${hhLabel(h)}: dar ${h.dar==null?'șters':lei(h.dar)}`, false); return; }
    if (f==='c'){ h.c = t.value; commit(`${hhLabel(h)} mutată în ${catName(h.c)}`); return; }
    h[f] = t.value.trim(); commit(f==='fam'&&h.fam?`Familia ${h.id} se numește acum ${h.fam}`:null, f==='fam'||f==='table'); return;
  }
  if (t.dataset.p){
    const p = byId(S.p,t.dataset.p);
    if (f==='n'){ p.n = t.value.trim()||p.n; p.ph = 0; commit(null); return; }
    p[f] = +t.value; commit(f==='r'?`${p.n}: ${RS[p.r].toLowerCase()}`:null); return;
  }
  if (t.dataset.v){
    const v = byId(S.v,t.dataset.v);
    if (['ppp','cp','cap','min'].includes(f)) v[f] = num(t.value); else v[f] = t.value;
    commit(f==='ppp'&&v.ppp?`${v.n}: ${lei(v.ppp)} / persoană`:null, false);
    render(); return;
  }
  if (t.id==='offerFile'){ addFiles(byId(S.v,UI.drawer), [...t.files]); t.value=''; return; }
  if (t.dataset.of){ const v = byId(S.v,t.dataset.of), o = offerOf(v); o[f] = f==='extra'?num(t.value):f==='drinks'?+t.value:t.value; commit(null,false); render(); return; }
  if (t.dataset.b){
    const b = byId(S.budget,t.dataset.b); b[f] = f==='cat'?t.value:num(t.value); commit(null); return;
  }
  if (t.dataset.vd){ const v = byId(S.vendors,t.dataset.vd); v[f] = f==='price'?num(t.value):t.value; commit(f==='status'?`${v.name}: ${v.status.toLowerCase()}`:null); return; }
  if (t.dataset.pay){ const p = byId(S.vendors,t.dataset.pay).pays[+t.dataset.i]; p[f] = f==='amount'?num(t.value):t.value; commit(null); return; }
});
document.addEventListener('submit', e=>{
  e.preventDefault();
  if (e.target.id==='catForm'){ const v = $('#catNew').value.trim(); if (!v) return; if (S.cats.some(c=>c.n.toLowerCase()===v.toLowerCase())){ toast('Există deja o categorie cu acest nume'); return; } S.cats.push({id:'C'+Date.now(),n:v}); commit(`Categorie nouă: ${v}`); setTimeout(()=>{ const i=$('#catNew'); if(i) i.focus(); },0); return; }
  if (e.target.id==='taskForm'){ S.tasks.push({id:'T'+Date.now(),date:$('#tDate').value,title:$('#tTitle').value.trim(),note:'',done:false}); commit('Pas nou în calendar'); }
  if (e.target.id==='vendForm'){ const name=$('#vName').value.trim(); if(!name) return; S.vendors.push({id:'D'+Date.now(),name,line:$('#vLine').value,price:num($('#vPrice').value),status:$('#vStatus').value,pays:[]}); commit(`Furnizor adăugat: ${name}`); }
});
document.addEventListener('keydown', e=>{ if (e.key!=='Escape') return; const vw = document.querySelector('.viewer'); if (vw){ URL.revokeObjectURL(vw.dataset.url); vw.remove(); return; } if (UI.drawer) closeDrawer(); });
['dragover','dragleave','drop'].forEach(ev=>document.addEventListener(ev, e=>{
  const d = e.target.closest && e.target.closest('#drop'); if (!d) return;
  e.preventDefault();
  if (ev==='dragover') d.classList.add('over'); else d.classList.remove('over');
  if (ev==='drop' && e.dataTransfer.files.length) addFiles(byId(S.v,UI.drawer), [...e.dataTransfer.files]);
}));

boot();
