/* FlowQuest Site - Motion & Interactivity */

(function () {
  const doc = document;
  const root = doc.documentElement;
  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const mobileNavQuery = window.matchMedia('(max-width: 768px)');
  const themeColorMeta = doc.querySelector('meta[name="theme-color"]');
  const industrySlugMap = {
    beauty: 'beauty',
    'beauty & spa': 'beauty',
    'beauty i spa': 'beauty',
    hotel: 'hotel',
    warsztat: 'warsztat',
    nieruchomosci: 'nieruchomosci',
    'nieruchomości': 'nieruchomosci',
    logistyka: 'logistyka',
    kursy: 'kursy',
    gastronomia: 'gastronomia',
    'serwis www': 'serwis-www',
    ecommerce: 'ecommerce',
    'e-commerce': 'ecommerce',
    'e-commerce b2b': 'handel-b2b',
    'ecommerce b2b': 'handel-b2b',
    medycyna: 'medycyna',
    oze: 'oze',
    'hvac & oze': 'oze',
    'agencje & it': 'agencja',
    agencja: 'agencja',
    rekrutacja: 'rekrutacja',
    eventy: 'eventy',
    konsultant: 'konsultant',
    budownictwo: 'budownictwo',
    'handel b2b': 'handel-b2b',
    fitness: 'fitness',
    prawo: 'prawo',
  };

  function normalizeLabel(value) {
    return (value || '')
      .toLowerCase()
      .replace(/&/g, ' & ')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/\s+/g, ' ')
      .trim();
  }

  function getRelativePrefix() {
    const path = window.location.pathname.replace(/\\/g, '/');
    if (path.includes('/pakiety/') || path.includes('/branze/')) return '../';
    return '';
  }

  function getActiveNavKey() {
    const path = window.location.pathname.replace(/\\/g, '/');

    if (path.endsWith('/funkcje.html')) return 'product';
    if (path.endsWith('/oferta.html') || path.includes('/pakiety/')) return 'packages';
    if (path.includes('/branze/')) return 'industries';
    if (
      path.endsWith('/baza-wiedzy.html') ||
      path.endsWith('/monitoring.html') ||
      path.endsWith('/o-nas.html')
    ) {
      return 'resources';
    }
    if (path.endsWith('/kontakt.html')) return 'contact';
    return 'home';
  }

  function getNavLinkClass(activeKey, expected) {
    return activeKey === expected ? ' class="active"' : '';
  }

  function renderUnifiedNavigation() {
    const header = doc.querySelector('.site-header');
    if (!header) return;

    header.id = 'siteHeader';
    header.innerHTML = '<div class="container nav-inner"></div>';

    const navInner = header.querySelector('.nav-inner');
    if (!navInner) return;

    const prefix = getRelativePrefix();
    const activeKey = getActiveNavKey();

    navInner.innerHTML = `
      <a href="${prefix}index.html" class="site-logo" aria-label="FlowQuest">
        <span class="site-logo-mark">
          <img src="https://go.flowquest.pl/uploads/company/2d792704c2078d4b3d0214c7dcd54ec8.png" alt="FlowQuest" class="logo-img logo-on-light">
          <img src="https://go.flowquest.pl/uploads/company/ac3475e3c47b743cd02d10be1c3131cb.png" alt="FlowQuest" class="logo-img logo-on-dark">
        </span>
      </a>
      <nav class="nav-links" id="navLinks">
        <a href="${prefix}index.html"${getNavLinkClass(activeKey, 'home')}>Start</a>
        <div class="nav-item dropdown">
          <a href="${prefix}funkcje.html"${getNavLinkClass(activeKey, 'product')}>Produkt</a>
          <div class="dropdown-menu dropdown-menu-wide">
            <div class="dropdown-section">
              <div class="dropdown-title">Platforma</div>
              <a href="${prefix}funkcje.html" class="dropdown-link-rich"><strong>59 modułów w jednym systemie</strong><span>CRM, sprzedaż, projekty, finanse, komunikacja i automatyzacje dla MŚP.</span></a>
              <a href="${prefix}oferta.html" class="dropdown-link-rich"><strong>Pakiety i limity</strong><span>Od Core Free po Business 10+, z jasną ścieżką rozwoju i modułami premium.</span></a>
            </div>
            <div class="dropdown-section">
              <div class="dropdown-title">Start i wdrożenie</div>
              <a href="${prefix}kontakt.html" class="dropdown-link-rich"><strong>Audyt i demo 1:1</strong><span>Dobieramy branżę, moduły i scenariusz wdrożenia pod konkretny proces.</span></a>
              <a href="${prefix}baza-wiedzy.html" class="dropdown-link-rich"><strong>Baza wiedzy</strong><span>FAQ, odpowiedzi sprzedażowe i techniczne oraz treści pod SEO i AI search.</span></a>
            </div>
          </div>
        </div>
        <div class="nav-item dropdown">
          <a href="${prefix}oferta.html"${getNavLinkClass(activeKey, 'packages')}>Pakiety</a>
          <div class="dropdown-menu dropdown-menu-wide">
            <div class="dropdown-section">
              <div class="dropdown-title">Abonament</div>
              <a href="${prefix}oferta.html" class="dropdown-link-rich"><strong>Core Free, Basic, Team, Business</strong><span>Plany dopasowane do startu, JDG, małych zespołów i większych wdrożeń.</span></a>
              <a href="${prefix}pakiety/core.html" class="dropdown-link-rich"><strong>Case study pakietów</strong><span>Zobacz, jak rdzeń i scenariusze działają w praktyce u różnych typów firm.</span></a>
            </div>
            <div class="dropdown-section">
              <div class="dropdown-title">Rozszerzenia</div>
              <a href="${prefix}oferta.html#bran%C5%BCe" class="dropdown-link-rich"><strong>Pakiety branżowe</strong><span>Basic plus 2-3 moduły, dane demo i gotowy template do klonowania.</span></a>
              <a href="${prefix}oferta.html#dodatki" class="dropdown-link-rich"><strong>Dodatki i usługi</strong><span>AI, landing pages, HR, księgowość, magazyn i wdrożenia premium.</span></a>
            </div>
          </div>
        </div>
        <div class="nav-item dropdown">
          <a href="${prefix}branze/index.html"${getNavLinkClass(activeKey, 'industries')}>Branże</a>
          <div class="dropdown-menu dropdown-menu-wide">
            <div class="dropdown-section">
              <div class="dropdown-title">Najczęściej wybierane</div>
              <a href="${prefix}branze/beauty.html" class="dropdown-link-rich"><strong>Beauty i medycyna estetyczna</strong><span>Rezerwacje, przypomnienia, historie klientów i reaktywacja wizyt.</span></a>
              <a href="${prefix}branze/hotel.html" class="dropdown-link-rich"><strong>Hotel i hospitality</strong><span>Obsługa gościa, catering, zadania i upsell usług dodatkowych.</span></a>
              <a href="${prefix}branze/warsztat.html" class="dropdown-link-rich"><strong>Warsztat i serwis</strong><span>Zlecenia, części, historia pojazdu i komunikacja z klientem.</span></a>
              <a href="${prefix}branze/nieruchomosci.html" class="dropdown-link-rich"><strong>Nieruchomości</strong><span>Leady, listingi, follow-upy i domykanie transakcji w jednym pipeline.</span></a>
            </div>
            <div class="dropdown-section">
              <div class="dropdown-title">Rosnące scenariusze</div>
              <a href="${prefix}branze/logistyka.html" class="dropdown-link-rich"><strong>Logistyka i flota</strong><span>Trasy, kierowcy, zgłoszenia i dokumentacja operacyjna.</span></a>
              <a href="${prefix}branze/ecommerce.html" class="dropdown-link-rich"><strong>E-commerce i handel B2B</strong><span>Omni-sales, zamówienia, magazyn i dokumenty sprzedażowe.</span></a>
              <a href="${prefix}branze/oze.html" class="dropdown-link-rich"><strong>OZE i instalacje</strong><span>Lead, oferta, montaż, serwis i dokumentacja po wdrożeniu.</span></a>
              <a href="${prefix}branze/index.html" class="dropdown-link-rich"><strong>Wszystkie branże</strong><span>Pełny katalog gotowych podstron, modułów i scenariuszy wdrożeniowych.</span></a>
            </div>
          </div>
        </div>
        <div class="nav-item dropdown">
          <a href="${prefix}baza-wiedzy.html"${getNavLinkClass(activeKey, 'resources')}>Zasoby</a>
          <div class="dropdown-menu">
            <a href="${prefix}baza-wiedzy.html">Baza wiedzy</a>
            <a href="${prefix}o-nas.html">O nas</a>
            <a href="${prefix}monitoring.html">Status systemu</a>
          </div>
        </div>
        <a href="${prefix}kontakt.html"${getNavLinkClass(activeKey, 'contact')}>Kontakt</a>
        <div class="mobile-nav-cta">
          <a href="${prefix}kontakt.html" class="btn-nav-outline">Umów bezpłatny audyt</a>
          <a href="https://demo.flowquest.pl/" class="btn-nav-primary" target="_blank" rel="noopener"><i class="fa-solid fa-play"></i> Demo na żywo</a>
        </div>
      </nav>
      <div class="nav-cta">
        <button class="theme-toggle" type="button" aria-label="Przelacz motyw">
          <i class="fa-solid fa-moon"></i>
        </button>
        <div class="cta-links">
          <a href="${prefix}kontakt.html" class="btn-nav-outline">Umów audyt</a>
          <a href="https://demo.flowquest.pl/" class="btn-nav-primary" target="_blank" rel="noopener"><i class="fa-solid fa-play"></i> Demo na żywo</a>
        </div>
      </div>
      <button class="hamburger" id="hamburgerBtn" type="button" aria-label="Menu" aria-controls="navLinks" aria-expanded="false">
        <i class="fa-solid fa-bars"></i>
      </button>
    `;
  }

  function getCrosslinkConfig() {
    const path = window.location.pathname.replace(/\\/g, '/');
    const prefix = getRelativePrefix();

    const shared = {
      product: {
        href: `${prefix}funkcje.html`,
        eyebrow: 'Produkt',
        title: 'Poznaj wszystkie moduły',
        desc: 'Zobacz, jak CRM, projekty, faktury i komunikacja spinają się w jednym systemie.',
      },
      packages: {
        href: `${prefix}oferta.html`,
        eyebrow: 'Pakiety',
        title: 'Porównaj pakiety i limity',
        desc: 'Przejdź od Core Free do planu dopasowanego do skali firmy i branży.',
      },
      industries: {
        href: `${prefix}branze/index.html`,
        eyebrow: 'Branże',
        title: 'Wybierz gotowy scenariusz dla swojej branży',
        desc: 'Sprawdź wdrożenia pod beauty, warsztat, hotel, nieruchomości i kolejne modele pracy.',
      },
      knowledge: {
        href: `${prefix}baza-wiedzy.html`,
        eyebrow: 'Baza wiedzy',
        title: 'Przeczytaj odpowiedzi i FAQ',
        desc: 'Treści produktowe i wdrożeniowe, które prowadzą użytkownika do kolejnego kroku.',
      },
      contact: {
        href: `${prefix}kontakt.html`,
        eyebrow: 'Kontakt',
        title: 'Umów audyt i demo 1:1',
        desc: 'Dobierzemy pakiet, branżę i zakres wdrożenia bez zgadywania.',
      },
      about: {
        href: `${prefix}o-nas.html`,
        eyebrow: 'O nas',
        title: 'Sprawdź, jak pracujemy',
        desc: 'Zobacz, dlaczego wdrożenia prowadzimy etapami i bez chaosu po stronie klienta.',
      },
      monitoring: {
        href: `${prefix}monitoring.html`,
        eyebrow: 'Status',
        title: 'Sprawdź status usług',
        desc: 'Bieżąca dostępność, monitoring i transparentność działania całego ekosystemu.',
      },
      core: {
        href: `${prefix}pakiety/core.html`,
        eyebrow: 'Pakiet',
        title: 'Zobacz case study Core',
        desc: 'Jak wygląda uporządkowany start i pierwszy etap wdrożenia bez przepalania budżetu.',
      },
    };

    if (path.endsWith('/index.html') || path === '/' || path.endsWith('/flowquest.pl')) {
      return {
        kicker: 'Powiązane ścieżki',
        title: 'Przejdź dalej w naturalnym rytmie decyzji',
        desc: 'Ta sekcja domyka linkowanie między stronami i prowadzi użytkownika od ogólnego obrazu do konkretnej decyzji.',
        items: [shared.product, shared.packages, shared.industries, shared.knowledge],
      };
    }

    if (path.endsWith('/funkcje.html')) {
      return {
        kicker: 'Kolejny krok',
        title: 'Po obejrzeniu modułów pokaż użytkownikowi wdrożenie i cenę',
        desc: 'Z funkcji najczęściej przechodzi się dalej do pakietu, branży albo kontaktu z doradcą.',
        items: [shared.packages, shared.industries, shared.knowledge, shared.contact],
      };
    }

    if (path.endsWith('/oferta.html')) {
      return {
        kicker: 'Przed decyzją',
        title: 'Zamknij ścieżkę od pakietu do wdrożenia',
        desc: 'Porównanie planów powinno prowadzić dalej do konkretnego scenariusza, funkcji i rozmowy o wdrożeniu.',
        items: [shared.core, shared.industries, shared.product, shared.contact],
      };
    }

    if (path.endsWith('/kontakt.html')) {
      return {
        kicker: 'Przed wysłaniem formularza',
        title: 'Daj użytkownikowi szybkie drogi powrotu do ważnych stron',
        desc: 'Jeśli ktoś nie jest jeszcze gotowy na kontakt, powinien od razu dostać pakiet, branżę i wiedzę pomocniczą.',
        items: [shared.packages, shared.industries, shared.knowledge, shared.about],
      };
    }

    if (path.endsWith('/baza-wiedzy.html')) {
      return {
        kicker: 'Po lekturze',
        title: 'Przenieś ruch z treści do produktu i oferty',
        desc: 'FAQ i artykuły powinny naturalnie prowadzić do funkcji, pakietów, branż oraz kontaktu.',
        items: [shared.product, shared.packages, shared.industries, shared.contact],
      };
    }

    if (path.endsWith('/monitoring.html')) {
      return {
        kicker: 'Zasoby',
        title: 'Po statusie systemu pokaż realny kontekst biznesowy',
        desc: 'Użytkownik sprawdzający dostępność powinien mieć prosty dostęp do wsparcia, dokumentacji i oferty.',
        items: [shared.knowledge, shared.contact, shared.about, shared.packages],
      };
    }

    if (path.endsWith('/o-nas.html')) {
      return {
        kicker: 'Zaufanie',
        title: 'Domknij historię firmy następnym logicznym kliknięciem',
        desc: 'Po stronie o nas użytkownik zwykle chce przejść do pakietów, wiedzy albo porozmawiać o wdrożeniu.',
        items: [shared.contact, shared.packages, shared.knowledge, shared.industries],
      };
    }

    if (path.includes('/pakiety/')) {
      return {
        kicker: 'Pakiety',
        title: 'Rozszerz case study o pełną ofertę i branże',
        desc: 'Strony pakietowe powinny odsyłać do pełnego porównania, modułów, scenariuszy branżowych i audytu.',
        items: [shared.packages, shared.product, shared.industries, shared.contact],
      };
    }

    if (path.includes('/branze/') && !path.endsWith('/branze/index.html')) {
      return {
        kicker: 'Branża',
        title: 'Po stronie branżowej pokaż produkt, cenę i następny krok',
        desc: 'Użytkownik oglądający scenariusz dla swojej branży powinien od razu widzieć moduły, pakiet i możliwość rozmowy.',
        items: [shared.packages, shared.product, shared.knowledge, shared.contact],
      };
    }

    if (path.endsWith('/branze/index.html')) {
      return {
        kicker: 'Katalog branż',
        title: 'Podłącz katalog branż do oferty, funkcji i kontaktu',
        desc: 'Hub branżowy ma prowadzić dalej do szczegółów wdrożenia, zakresu produktu i finalnego kontaktu.',
        items: [shared.packages, shared.product, shared.knowledge, shared.contact],
      };
    }

    if (
      path.endsWith('/polityka-prywatnosci.html') ||
      path.endsWith('/regulamin.html') ||
      path.endsWith('/linkedin.html') ||
      path.endsWith('/facebook.html')
    ) {
      return {
        kicker: 'FlowQuest',
        title: 'Wróć do najważniejszych stron serwisu',
        desc: 'Nawet z treści pomocniczych użytkownik powinien mieć prostą drogę z powrotem do produktu i kontaktu.',
        items: [shared.product, shared.packages, shared.industries, shared.contact],
      };
    }

    return null;
  }

  function renderInternalCrosslinks() {
    const footer = doc.querySelector('.site-footer');
    if (!footer || doc.querySelector('.crosslink-band')) return;

    const config = getCrosslinkConfig();
    if (!config || !Array.isArray(config.items) || !config.items.length) return;

    const section = doc.createElement('section');
    section.className = 'crosslink-band section';
    section.setAttribute('aria-label', 'Powiazane strony');

    const cards = config.items
      .map(
        (item) => `
          <a class="crosslink-card" href="${item.href}">
            <span class="crosslink-eyebrow">${item.eyebrow}</span>
            <strong>${item.title}</strong>
            <span>${item.desc}</span>
            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
          </a>
        `
      )
      .join('');

    section.innerHTML = `
      <div class="container">
        <div class="crosslink-shell scroll-reveal">
          <div class="crosslink-copy">
            <span class="section-label">${config.kicker}</span>
            <h2>${config.title}</h2>
            <p>${config.desc}</p>
          </div>
          <div class="crosslink-grid">
            ${cards}
          </div>
        </div>
      </div>
    `;

    footer.parentNode.insertBefore(section, footer);
  }

  function setupIndustryLinks() {
    const prefix = getRelativePrefix();

    doc.querySelectorAll('.ind-tile, .industry-article, .ind-pkg-card').forEach((element) => {
      const source =
        element.querySelector('.ind-name') ||
        element.querySelector('.article-tag') ||
        element.querySelector('.ind-pkg-name');

      if (!source) return;

      const slug = industrySlugMap[normalizeLabel(source.textContent)];
      if (!slug) return;

      const href = `${prefix}branze/${slug}.html`;
      element.dataset.href = href;
      element.style.cursor = 'pointer';

      element.addEventListener('click', (event) => {
        const target = event.target;
        if (target && target.closest('a, button')) return;
        window.location.href = href;
      });

      element.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') return;
        event.preventDefault();
        window.location.href = href;
      });

      element.setAttribute('tabindex', '0');
      element.setAttribute('role', 'link');

      const cta = element.querySelector('.ind-pkg-cta');
      if (cta && cta.getAttribute('href') === 'kontakt.html') {
        cta.setAttribute('href', href);
      }
    });
  }

  function setVisible(elements) {
    elements.forEach((element) => element.classList.add('visible'));
  }

  function createObserver(callback, options) {
    if (!('IntersectionObserver' in window)) return null;
    return new IntersectionObserver(callback, options);
  }

  function setupRevealAnimations() {
    const revealElements = Array.from(
      doc.querySelectorAll('.reveal, .reveal-left, .reveal-right, .scroll-reveal')
    );

    if (!revealElements.length) return;

    if (prefersReducedMotion.matches) {
      setVisible(revealElements);
      return;
    }

    const observer = createObserver((entries, instance) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('visible');
        instance.unobserve(entry.target);
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

    if (!observer) {
      setVisible(revealElements);
      return;
    }

    revealElements.forEach((element) => observer.observe(element));
  }

  function setupStickyHeader() {
    const header = doc.querySelector('.site-header');
    if (!header) return;

    const navInner = header.querySelector('.nav-inner');
    const SCROLL_START = 10;
    const SCROLL_END = 80;
    let ticking = false;

    const updateNav = () => {
      const y = window.scrollY;
      const raw = (y - SCROLL_START) / (SCROLL_END - SCROLL_START);
      const progress = Math.max(0, Math.min(1, raw));

      header.classList.toggle('scrolled', progress > 0);

      if (navInner) {
        const maxRadius = mobileNavQuery.matches ? 14 : 22;
        navInner.style.setProperty('--nav-t', progress.toFixed(3));
        navInner.style.borderRadius = `${progress * maxRadius}px`;
      }

      ticking = false;
    };

    window.addEventListener('scroll', () => {
      if (ticking) return;
      ticking = true;
      window.requestAnimationFrame(updateNav);
    }, { passive: true });

    updateNav();
  }

  /* ── Magnetic hover effect for CTA buttons ── */
  function setupMagneticButtons() {
    if (prefersReducedMotion.matches || mobileNavQuery.matches) return;

    const btns = doc.querySelectorAll('.btn-cta-glow, .btn-nav-primary');
    if (!btns.length) return;

    btns.forEach((btn) => {
      btn.addEventListener('mousemove', (e) => {
        const rect = btn.getBoundingClientRect();
        const x = e.clientX - rect.left - rect.width / 2;
        const y = e.clientY - rect.top - rect.height / 2;
        btn.style.transform = `translate(${x * 0.12}px, ${y * 0.12}px)`;
      });

      btn.addEventListener('mouseleave', () => {
        btn.style.transform = '';
      });
    });
  }

  /* ── Stagger reveal for grid children ── */
  function setupStaggerReveal() {
    const grids = doc.querySelectorAll('.bento, .pkg-grid, .ind-grid, .testi-grid, .featured-grid, .industry-article-grid, .crosslink-grid');
    grids.forEach((grid) => {
      const children = Array.from(grid.children);
      children.forEach((child, i) => {
        child.style.transitionDelay = `${i * 0.06}s`;
      });
    });
  }

  function animateCounter(element) {
    const target = Number.parseInt(element.dataset.target || '', 10);
    if (Number.isNaN(target)) return;

    if (prefersReducedMotion.matches) {
      element.textContent = target.toLocaleString('pl-PL');
      return;
    }

    const duration = 1600;
    const start = performance.now();

    const update = (now) => {
      const progress = Math.min((now - start) / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3);
      element.textContent = Math.round(eased * target).toLocaleString('pl-PL');
      if (progress < 1) window.requestAnimationFrame(update);
    };

    window.requestAnimationFrame(update);
  }

  function setupCounters() {
    const counters = Array.from(doc.querySelectorAll('[data-target]'));
    if (!counters.length) return;

    if (prefersReducedMotion.matches) {
      counters.forEach(animateCounter);
      return;
    }

    const observer = createObserver((entries, instance) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        animateCounter(entry.target);
        instance.unobserve(entry.target);
      });
    }, { threshold: 0.5 });

    if (!observer) {
      counters.forEach(animateCounter);
      return;
    }

    counters.forEach((counter) => observer.observe(counter));
  }

  function setupAccordion() {
    const triggers = Array.from(doc.querySelectorAll('.accordion-trigger'));
    if (!triggers.length) return;

    triggers.forEach((button) => {
      button.addEventListener('click', () => {
        const body = button.nextElementSibling;
        const accordion = button.closest('.accordion');
        if (!body || !accordion) return;

        const wasOpen = button.classList.contains('open');

        accordion.querySelectorAll('.accordion-trigger').forEach((item) => {
          item.classList.remove('open');
          if (item.nextElementSibling) {
            item.nextElementSibling.classList.remove('open');
          }
        });

        if (!wasOpen) {
          button.classList.add('open');
          body.classList.add('open');
        }
      });
    });
  }

  function setupMobileNav() {
    const nav = doc.getElementById('navLinks');
    const hamburger = doc.querySelector('.hamburger');
    const headerCtas = doc.querySelector('.nav-cta .cta-links');
    let navOverlay = doc.getElementById('navOverlay');

    if (!nav || !hamburger) return;

    function ensureNavOverlay() {
      if (navOverlay || !doc.body) return navOverlay;
      navOverlay = doc.createElement('div');
      navOverlay.id = 'navOverlay';
      navOverlay.className = 'nav-overlay';
      doc.body.append(navOverlay);
      return navOverlay;
    }

    function closeMobileDropdowns() {
      doc.querySelectorAll('.nav-item.dropdown.open').forEach((item) => {
        item.classList.remove('open');
        const link = item.querySelector(':scope > a');
        if (link) link.setAttribute('aria-expanded', 'false');
      });
    }

    function syncHamburger(open) {
      hamburger.setAttribute('aria-expanded', String(open));
      hamburger.setAttribute('aria-label', open ? 'Zamknij menu' : 'Otworz menu');

      const icon = hamburger.querySelector('i');
      if (icon) {
        icon.className = open ? 'fa-solid fa-xmark' : 'fa-solid fa-bars';
      }
    }

    function setNavOpen(open) {
      nav.classList.toggle('open', open);
      doc.body.classList.toggle('nav-open', open);
      syncHamburger(open);

      const overlay = ensureNavOverlay();
      if (overlay) overlay.classList.toggle('visible', open);

      if (!open) closeMobileDropdowns();
    }

    function ensureMobileNavCta() {
      if (!headerCtas || nav.querySelector('.mobile-nav-cta')) return;

      const mobileCta = doc.createElement('div');
      mobileCta.className = 'mobile-nav-cta';

      headerCtas.querySelectorAll('a').forEach((link) => {
        mobileCta.appendChild(link.cloneNode(true));
      });

      if (mobileCta.children.length) nav.appendChild(mobileCta);
    }

    ensureMobileNavCta();
    setNavOpen(false);

    doc.querySelectorAll('.nav-item.dropdown > a').forEach((link) => {
      link.setAttribute('aria-haspopup', 'true');
      link.setAttribute('aria-expanded', 'false');

      link.addEventListener('click', (event) => {
        if (!mobileNavQuery.matches) return;

        const item = link.closest('.nav-item.dropdown');
        if (!item) return;

        const isOpen = item.classList.contains('open');
        event.preventDefault();
        closeMobileDropdowns();

        if (!isOpen) {
          item.classList.add('open');
          link.setAttribute('aria-expanded', 'true');
        }
      });
    });

    hamburger.setAttribute('type', 'button');
    hamburger.setAttribute('aria-controls', 'navLinks');
    syncHamburger(false);

    const overlay = ensureNavOverlay();
    if (overlay) {
      overlay.addEventListener('click', () => setNavOpen(false));
    }

    hamburger.addEventListener('click', (event) => {
      event.preventDefault();
      setNavOpen(!nav.classList.contains('open'));
    });

    nav.querySelectorAll('a').forEach((link) => {
      link.addEventListener('click', () => {
        if (!mobileNavQuery.matches) return;

        if (link.closest('.dropdown-menu')) {
          setNavOpen(false);
          return;
        }

        if (!link.closest('.nav-item.dropdown')) {
          setNavOpen(false);
        }
      });
    });

    doc.addEventListener('click', (event) => {
      if (!nav.classList.contains('open')) return;
      if (nav.contains(event.target) || hamburger.contains(event.target)) return;
      setNavOpen(false);
    });

    doc.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') setNavOpen(false);
    });

    const handleBreakpointChange = () => {
      if (!mobileNavQuery.matches) setNavOpen(false);
    };

    if (typeof mobileNavQuery.addEventListener === 'function') {
      mobileNavQuery.addEventListener('change', handleBreakpointChange);
    } else {
      window.addEventListener('resize', handleBreakpointChange);
    }
  }

  function setupFormsApi() {
    window.FlowQuestForms = {
      endpoint: 'https://crm.flowquest.pl/form_sync/receive',
      async submit(payload) {
        const response = await fetch(this.endpoint, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        });

        let data = {};
        try {
          data = await response.json();
        } catch (error) {
          data = {};
        }

        if (!response.ok || data.success === false) {
          throw new Error(data.message || 'Nie udalo sie wyslac formularza.');
        }

        return data;
      },
    };
  }

  function setupThemeToggle() {
    const THEME_KEY = 'fq-theme';
    const LEGACY_THEME_KEY = 'flowquest-theme';
    const themeToggle = doc.querySelector('.theme-toggle');

    const storage = {
      get(key) {
        try {
          return localStorage.getItem(key);
        } catch (error) {
          return null;
        }
      },
      set(key, value) {
        try {
          localStorage.setItem(key, value);
        } catch (error) {
          return null;
        }
        return value;
      },
    };

    function getThemeIcon(mode) {
      return mode === 'light' ? 'fa-sun' : 'fa-moon';
    }

    function updateThemeColor(mode) {
      if (!themeColorMeta) return;
      themeColorMeta.setAttribute('content', mode === 'dark' ? '#0a1121' : '#2563eb');
    }

    function ensureThemeGuard() {
      if (doc.getElementById('fq-theme-guard')) return;

      const style = doc.createElement('style');
      style.id = 'fq-theme-guard';
      style.textContent = `
        html.theme-switching *,
        html.theme-switching *::before,
        html.theme-switching *::after {
          transition: none;
          animation-duration: 0.001ms;
          animation-delay: 0ms;
        }
      `;
      doc.head.append(style);
    }

    function applyTheme(mode) {
      ensureThemeGuard();
      root.classList.add('theme-switching');
      root.setAttribute('data-theme', mode);
      storage.set(THEME_KEY, mode);
      updateThemeColor(mode);

      if (!themeToggle) return;

      themeToggle.innerHTML = `<i class="fa-solid ${getThemeIcon(mode)}"></i>`;
      themeToggle.setAttribute('aria-pressed', String(mode === 'dark'));
      themeToggle.setAttribute(
        'aria-label',
        mode === 'dark' ? 'Przelacz na jasny motyw' : 'Przelacz na ciemny motyw'
      );

      window.requestAnimationFrame(() => {
        window.requestAnimationFrame(() => {
          root.classList.remove('theme-switching');
        });
      });
    }

    const queryTheme = new URLSearchParams(window.location.search).get('theme');
    const storedTheme = storage.get(THEME_KEY) || storage.get(LEGACY_THEME_KEY);
    const initialTheme =
      queryTheme === 'dark' || queryTheme === 'light' ? queryTheme : (storedTheme || 'light');

    applyTheme(initialTheme);

    if (!themeToggle) return;

    themeToggle.addEventListener('click', () => {
      const nextTheme = root.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
      applyTheme(nextTheme);
    });
  }

  /* ── Smooth parallax on hero section ── */
  function setupHeroParallax() {
    if (prefersReducedMotion.matches || mobileNavQuery.matches) return;
    const hero = doc.querySelector('.hero');
    if (!hero) return;

    let ticking = false;
    window.addEventListener('scroll', () => {
      if (ticking) return;
      ticking = true;
      window.requestAnimationFrame(() => {
        const y = window.scrollY;
        const heroH = hero.offsetHeight;
        if (y < heroH) {
          const rate = y * 0.3;
          hero.style.transform = `translate3d(0, ${rate}px, 0)`;
          hero.style.opacity = Math.max(0, 1 - y / (heroH * 0.8));
        }
        ticking = false;
      });
    }, { passive: true });
  }

  /* ── Smooth section label slide-in ── */
  function setupLabelAnimations() {
    if (prefersReducedMotion.matches) return;
    const labels = doc.querySelectorAll('.section-label');
    if (!labels.length) return;

    const observer = createObserver((entries, instance) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        entry.target.style.opacity = '1';
        entry.target.style.transform = 'translateY(0)';
        instance.unobserve(entry.target);
      });
    }, { threshold: 0.3 });

    if (!observer) return;
    labels.forEach((label) => {
      label.style.opacity = '0';
      label.style.transform = 'translateY(12px)';
      label.style.transition = 'opacity .6s cubic-bezier(.22,1,.36,1), transform .6s cubic-bezier(.22,1,.36,1)';
      observer.observe(label);
    });
  }

  /* ── Tilt effect on bento/feature cards ── */
  function setupCardTilt() {
    if (prefersReducedMotion.matches || mobileNavQuery.matches) return;
    const cards = doc.querySelectorAll('.bento-card, .pkg-card, .step-card');
    if (!cards.length) return;

    cards.forEach((card) => {
      card.addEventListener('mousemove', (e) => {
        const rect = card.getBoundingClientRect();
        const x = (e.clientX - rect.left) / rect.width - 0.5;
        const y = (e.clientY - rect.top) / rect.height - 0.5;
        card.style.transform = `perspective(600px) rotateY(${x * 4}deg) rotateX(${-y * 4}deg) scale(1.01)`;
      });
      card.addEventListener('mouseleave', () => {
        card.style.transform = '';
        card.style.transition = 'transform .4s cubic-bezier(.22,1,.36,1)';
      });
      card.addEventListener('mouseenter', () => {
        card.style.transition = 'transform .1s ease-out';
      });
    });
  }

  /* ── Smooth number counting with decimal support ── */
  function setupStatHighlight() {
    const stats = doc.querySelectorAll('.stat-big');
    if (!stats.length || prefersReducedMotion.matches) return;

    const observer = createObserver((entries, instance) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        entry.target.style.opacity = '1';
        entry.target.style.transform = 'translateY(0) scale(1)';
        instance.unobserve(entry.target);
      });
    }, { threshold: 0.3 });

    if (!observer) return;
    stats.forEach((stat) => {
      stat.style.opacity = '0';
      stat.style.transform = 'translateY(20px) scale(0.95)';
      stat.style.transition = 'opacity .7s cubic-bezier(.22,1,.36,1), transform .7s cubic-bezier(.22,1,.36,1)';
      observer.observe(stat);
    });
  }

  function init() {
    renderUnifiedNavigation();
    renderInternalCrosslinks();
    setupRevealAnimations();
    setupStickyHeader();
    setupCounters();
    setupAccordion();
    setupMobileNav();
    setupIndustryLinks();
    setupFormsApi();
    setupThemeToggle();
    setupMagneticButtons();
    setupStaggerReveal();
    setupHeroParallax();
    setupLabelAnimations();
    setupCardTilt();
    setupStatHighlight();
  }

  init();
})();
