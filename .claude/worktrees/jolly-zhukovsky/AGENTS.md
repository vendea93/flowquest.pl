# FlowQuest — Strona Główna

## Serwer i konfiguracja
- **OS**: Ubuntu, Nginx, PHP 8.3-FPM
- **Nginx config**: `/etc/nginx/sites-enabled/flowquest.conf`
- **Web root**: `/var/www/flowquest.pl/` (statyczna strona HTML)
- **CRM**: `/var/www/crm.flowquest.pl/` (Perfex CRM, PHP)
- **Mail**: `/var/www/mail.flowquest.pl/` (Roundcube, PHP)
- **SSL**: Let's Encrypt — `flowquest-main` (główna + crm + mail), `flowquest.pl-0001` (wildcard *.flowquest.pl)

## Stack strony
- Czysty HTML5 + CSS + vanilla JS (brak frameworków, brak bundlera)
- `shared.css` — wspólny design system (nawigacja, stopka, typografia, zmienne CSS)
- `motion.js` — animacje scroll, przełącznik motywu jasny/ciemny, canvas particle network
- Motywy: **jasny domyślnie**, ciemny po `data-theme='dark'` na `<html>`
- Zmienna persystencji: `localStorage['fq-theme']`

## Struktura plików
```
/var/www/flowquest.pl/
├── index.html          # Strona główna
├── funkcje.html        # Katalog 59 modułów
├── oferta.html         # Pakiety i ceny
├── kontakt.html        # Formularz kontaktowy + FormSync
├── baza-wiedzy.html    # Dokumentacja i FAQ
├── monitoring.html     # Status usług
├── o-nas.html          # O firmie
├── shared.css          # Design system — EDYTUJ TU kolory/layout
├── motion.js           # Animacje i theme toggle
├── pakiety/
│   ├── core.html
│   ├── solo.html
│   └── teams.html
└── demo/               # Demo branżowe (Grav CMS, nie edytować)
```

## Zmienne CSS (motywy)
Wszystkie kolory przez CSS custom properties w `shared.css`:
- `--bg-950` … `--bg-400` — tła sekcji i kart
- `--text-100` … `--text-700` — tekst (100 = najciemniejszy/jaśniejszy)
- `--border`, `--border-light` — obramowania
- `--nav-bg`, `--dropdown-bg` — nawigacja
- `--logo-filter` — filtr logo (czarne w jasnym, białe w ciemnym motywie)

`:root` = jasny motyw (domyślny)
`:root[data-theme='dark']` = ciemny motyw

## Paleta brandowa
- Niebieski: `#2563eb` (primary), `#3b82f6` (hover)
- Zielony: `#10b981` (akcenty, check marks)
- Tekst główny jasny: `#0f172a`
- Tło główne jasne: `#f8fafc`

## Formularz kontaktowy
- Endpoint: `https://crm.flowquest.pl/form_sync/receive` (POST JSON)
- Moduł CRM: `form_sync`
- Pola: name, company, email, phone, industry, topic, employees, message

## Konwencje
- Komentarze PL (projekt polskojęzyczny)
- Klasy BEM-like, bez preprocessora
- Animacje: `motion.js` IntersectionObserver + CSS `@keyframes`
- Wszystkie nowe sekcje używają CSS vars zamiast hardcoded kolorów
- Nie używać `!important`
