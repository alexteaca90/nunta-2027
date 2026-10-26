# Nunta noastră 2027

Platforma privată de organizare a nunții Andreei și a lui Alex, ca plugin WordPress de sine stătător.

Conține: invitați (familii și persoane individuale, categorii editabile), săli (etape, oferte atașate, comparație), buget legat de invitați și sală, furnizori cu plăți, calendar și daruri.

## Instalare (prima dată)

1. **Module → Adaugă modul → Încarcă modul** și alegeți `nunta-2027.zip`, apoi **Activează**.
2. În meniul nou **Nunta 2027**, la „Pagina aplicației”, scrieți o parolă de cel puțin 8 caractere și apăsați **Creează pagina**. Pagina va fi la `/nunta-2027/`, protejată cu parolă.
3. La **Import** alegeți fișierul cu datele inițiale (`nunta-2027-date-initiale.json`) și apăsați **Verifică fișierul**. Citiți raportul, apoi **Confirmă importul**.
4. Deschideți pagina, introduceți parola și alegeți cine sunteți (Alex sau Andreea).

Fișierul cu date nu face parte din acest repository: conține nume reale și se păstrează separat.

## Actualizări

Fiecare versiune nouă este publicată aici ca release, cu `nunta-2027.zip` atașat. WordPress o afișează la **Module** ca „Actualizare disponibilă”. Un click o instalează. Dacă o versiune schimbă structura bazei de date, pluginul face singur un backup înainte de migrare.

## Securitate

- Datele se citesc și se scriu numai prin `/wp-json/atwn27/v1/`.
- Fiecare cerere verifică pe server parola paginii (cookie-ul `wp-postpass_*`) sau un administrator autentificat.
- Dacă pagina nu are parolă, accesul anonim e refuzat.
- Antetul `X-ATWN27` e obligatoriu, ceea ce blochează cererile trimise de pe alte site-uri (CSRF).
- Ofertele atașate stau în `wp-content/uploads/atwn27-private/`, cu acces direct blocat și nume aleatorii. Se descarcă numai prin API, după verificarea parolei.
- Răspunsurile API sunt marcate `no-store`, iar pagina nu e indexată și nu intră în cache.

## Date și sincronizare

- Tabele proprii: `atwn27_records` (o înregistrare per familie, persoană, sală etc., fiecare cu versiune), `atwn27_revs`, `atwn27_log` și `atwn27_files`.
- Mesajul „Salvat” apare numai după ce serverul a confirmat salvarea.
- Dacă salvarea eșuează, modificarea rămâne în browser și se retrimite, inclusiv după un refresh.
- Dacă două persoane modifică aceeași înregistrare, serverul răspunde 409. Aplicația combină modificările pe câmpuri și le retrimite, fără să suprascrie în tăcere.
- Celelalte dispozitive primesc modificările în câteva secunde.

## Backup și revenire

- **Nunta 2027 → Descarcă backup complet** salvează toate datele într-un fișier JSON.
- Pentru restaurare, importați acel fișier. Înainte de orice import se face automat un backup, care apare în listă.
- **Revenire la o versiune anterioară a pluginului:** dezactivați pluginul, încărcați ZIP-ul versiunii vechi și reactivați-l. Datele rămân în tabele.
- **Dezinstalare:** dezactivarea nu șterge nimic.

## Dezvoltare

`bin/build-zip.sh` construiește `dist/nunta-2027.zip`.
