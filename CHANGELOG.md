# Changelog

## 0.2.0 (26 octombrie 2026)

**Emailuri din Gmail**
- Un script Google Apps Script, care rulează în contul Gmail al proprietarului, trimite în platformă, la fiecare 10 minute, doar emailurile schimbate cu sălile. Le alege după adresele salvate la săli sau după eticheta „Nunta 2027”. Endpointul `/inbox` e protejat cu un token secret.
- Pagina nouă **Emailuri**: corespondența grupată pe săli, emailurile nealocate (cu alegerea sălii) și sălile de la care așteptați răspuns de peste 4 zile.
- Potrivirea cu sala se face după adresă, după domeniu sau după numele sălii. După o alocare manuală, adresa e reținută și emailurile următoare de la ea vin automat.
- **Oferta se citește automat** din textul emailului și din PDF-uri: preț pe persoană (inclusiv variantele de meniu), meniu copii, avans, băuturi, capacitate, minim de persoane, valabilitate, taxe extra. Se completează doar câmpurile goale ale sălii și sunt marcate „citit automat din email”. PDF-ul se atașează la sală, iar sala trece la „Ofertă primită”. Ofertele în euro se convertesc după cursul din Setări.
- În fișa sălii: adresele de email și ultimele emailuri.

**Responsive**
- Conținutul folosește lățimea ecranului (până la 1440 px).
- Coloanele sălilor nu mai au scroll lateral: se așază pe toată lățimea, iar pe telefon se alege etapa din butoane.
- Pe telefon, bara cu cifre de sus e o grilă de 3 × 2.
- Toate paginile au fost verificate la 360, 414, 768, 1024, 1280, 1440 și 1920 px, fără depășiri pe orizontală.

## 0.1.4 (26 octombrie 2026)

- Furnizori direct în Buget: fiecare categorie se deschide și arată furnizorii ei, cu bifa „În buget”, prețul și cât s-a plătit.
- Din Buget se poate muta în categorie un furnizor existent, se poate adăuga unul nou sau se poate scoate unul din categorie.
- Mai mulți furnizori bifați în aceeași categorie se adună (de exemplu foto și video separat).
- Furnizorii fără categorie apar la Furnizori, în secțiunea „Fără categorie”.
- Bugetul pe telefon e afișat ca o listă de carduri, nu ca tabel.

## 0.1.3 (26 octombrie 2026)

- Reparat: formularele (furnizor, pas în calendar, categorie) nu se mai golesc și butonul nu mai „pierde” click-ul. Sincronizarea redesena pagina chiar și când nu se schimbase nimic (după ștergeri recente), exact în momentul click-ului.
- Pagina nu se mai redesenează cât timp butonul mouse-ului e apăsat sau cât timp un formular are text completat.
- Ce ai scris într-un formular se păstrează dacă pagina se actualizează între timp.

## 0.1.2 (26 octombrie 2026)

- Furnizorii sunt grupați pe categoriile din buget. Fiecare categorie are buton propriu de adăugare și sumar: rezervat, interval de oferte, achitat, rest de plată.
- După adăugare, furnizorul nou apare evidențiat, cu confirmare.
- Detalii furnizor: contact, telefon, email, note și mutarea în altă categorie.
- În buget, coloana „Furnizori” arată numărul de furnizori și intervalul ofertelor și duce direct la categoria respectivă.
- Pe Acasă: următoarea plată scadentă.
- O pagină veche cu același shortcode nu mai poate prelua pagina aplicației.

## 0.1.1 (26 octombrie 2026)

- Prefix tehnic propriu (`nn27`) pentru constante, tabele, opțiuni și API (`/wp-json/nn27/v1/`). Prefixul vechi, `atwn27`, era folosit și de un plugin mai vechi de pe site, iar cele două intrau în conflict.
- Datele importate în v0.1.0 se mută automat în tabelele noi.
- Avertisment în panou, cu link de dezactivare, dacă e activ alt plugin „nunta”.
- Aplicația tolerează un avertisment PHP afișat înaintea răspunsului JSON.
- Importul acceptă și fișierele cu formatul vechi.

## 0.1.0 (26 octombrie 2026)

Prima versiune.

- Aplicație fullscreen, independentă de temă, cu ecran de parolă propriu. Temă luminoasă, temă întunecată (negru, alb și auriu) sau automată.
- Invitați: familii și persoane individuale, nr. crt., categorii editabile (redenumire, ordine, ștergere cu mutarea intrărilor), confirmare per persoană, dar per familie, vizualizare tabel.
- Săli: etape de la contact la rezervare, oferte atașate (PDF și imagini), comparație, cost calculat pentru invitații așteptați.
- Buget legat de invitați, sală și furnizori. Daruri estimate și sold.
- Furnizori cu plăți. Calendar.
- Date pe server, cu versiune per înregistrare, detectarea conflictelor și sincronizare între dispozitive.
- Panou de administrare: crearea paginii, import cu verificare (dry-run), export, backup automat înainte de import.
- Actualizări din release-urile GitHub.
- Testat pe WordPress 6.8.3 (PHP 8.3) cu 27 de teste de acceptanță trecute.
