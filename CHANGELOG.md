# Changelog

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
