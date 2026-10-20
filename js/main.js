/* Shared browser behavior for the static Wamambo website. No framework required. */
(() => {
  const root = document.documentElement;
  const prefix = location.pathname.includes('/languages/') ? '../../' : '';
  const pageName = location.pathname.split('/').pop() || 'index.html';

  // Theme preference
  const themeButton = document.querySelector('nav button[title]');
  const storedTheme = localStorage.getItem('wamambo-theme');
  const dark = storedTheme ? storedTheme === 'dark' : matchMedia('(prefers-color-scheme: dark)').matches;
  root.classList.toggle('dark', dark);
  const updateThemeIcon = () => {
    if (!themeButton) return;
    themeButton.innerHTML = root.classList.contains('dark')
      ? '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>'
      : '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20.985 12.486a9 9 0 1 1-9.473-9.472c.405-.022.617.46.402.803a6 6 0 0 0 8.268 8.268c.344-.215.825-.004.803.401"/></svg>';
  };
  updateThemeIcon();
  themeButton?.addEventListener('click', () => {
    const isDark = !root.classList.contains('dark');
    root.classList.toggle('dark', isDark);
    localStorage.setItem('wamambo-theme', isDark ? 'dark' : 'light');
    updateThemeIcon();
  });

  // Localized static editions. The current language is persisted across pages.
  const languageSelect = document.querySelector('nav select');
  languageSelect?.addEventListener('change', (event) => {
    const language = event.target.value;
    localStorage.setItem('wamambo-language', language);
    location.href = language === 'en' ? `${prefix}${pageName}` : `${prefix}languages/${language}/${pageName}`;
  });

  // Fixed header and mobile navigation.
  const nav = document.querySelector('nav');
  const setHeader = () => nav?.classList.toggle('bg-background/90', scrollY > 50);
  addEventListener('scroll', setHeader, { passive: true }); setHeader();
  const mobileButton = [...document.querySelectorAll('nav button')].find((button) => !button.title);
  if (mobileButton && nav) {
    const desktopLinks = nav.querySelector('.hidden.lg\\:flex');
    const menu = document.createElement('div');
    menu.className = 'lg:hidden hidden bg-background/95 backdrop-blur-xl border-t border-border animate-fade-in';
    menu.innerHTML = `<div class="px-4 py-6 space-y-1">${desktopLinks?.innerHTML || ''}</div>`;
    nav.append(menu);
    mobileButton.addEventListener('click', () => {
      const closed = menu.classList.toggle('hidden');
      mobileButton.setAttribute('aria-label', closed ? 'Open menu' : 'Close menu');
    });
  }

  // Scroll-in animation formerly supplied by AnimatedSection.
  const animated = document.querySelectorAll('[style*="translateY(30px)"]');
  const observer = new IntersectionObserver((entries) => entries.forEach(({ isIntersecting, target }) => {
    if (isIntersecting) { target.style.opacity = '1'; target.style.transform = 'translateY(0)'; observer.unobserve(target); }
  }), { threshold: .15 });
  animated.forEach((element) => observer.observe(element));

  // Concierge panel.
  const chat = document.querySelector('.fixed.bottom-6.right-6.z-50');
  if (chat) {
    const trigger = chat.querySelector('button');
    trigger?.addEventListener('click', () => {
      const existing = chat.querySelector('[data-chat-panel]');
      if (existing) return existing.remove();
      const panel = document.createElement('div'); panel.dataset.chatPanel = '';
      panel.className = 'mb-4 w-80 bg-card border border-border rounded-2xl shadow-2xl overflow-hidden animate-scale-in';
      panel.innerHTML = '<div class="gold-gradient p-4"><h4 class="font-display text-sm font-bold text-primary-foreground">Wamambo Concierge</h4><p class="text-xs text-primary-foreground/80">Your African journey starts here</p></div><div class="p-4"><p class="bg-muted rounded-xl p-3 text-sm text-foreground">Welcome. How can we help plan your journey?</p><p class="mt-2 text-sm text-muted-foreground">Currently unavailable. Please contact us via WhatsApp to plan your journey.</p></div>';
      chat.prepend(panel);
    });
  }

  // WhatsApp booking inquiry form.
  const form = document.querySelector('form:not([data-paynow-form])');
  if (form) ['name', 'email', 'phone', 'dates', 'guests', 'interest', 'paymentMethod', 'message'].forEach((name, index) => {
    const field = form.querySelectorAll('input, select, textarea')[index]; if (field) field.name = name;
  });
  form?.addEventListener('submit', (event) => {
    event.preventDefault(); const data = new FormData(form);
    const value = (key) => String(data.get(key) || 'Not provided');
    const selected = (key) => form.querySelector(`[name="${key}"] option:checked`)?.textContent || 'Not selected';
    const message = ['New tour enquiry', '', `Name: ${value('name')}`, `Email: ${value('email')}`, `Phone: ${value('phone')}`, `Travel dates: ${value('dates')}`, `Guests: ${value('guests')}`, `Interest: ${selected('interest')}`, `Payment method: ${selected('paymentMethod')}`, `Message: ${value('message')}`].join('\n');
    location.href = `https://wa.me/263719429403?text=${encodeURIComponent(message)}`;
  });

  // Experience category filtering.
  if (pageName === 'experiences.html') {
    const buttons = [...document.querySelectorAll('main button')];
    const cards = [...document.querySelectorAll('main .grid > div.transition-all')];
    buttons.forEach((button) => button.addEventListener('click', () => {
      const category = button.textContent.trim();
      buttons.forEach((item) => item.classList.remove('gold-gradient', 'text-primary-foreground'));
      button.classList.add('gold-gradient', 'text-primary-foreground');
      cards.forEach((card) => { card.style.display = category === buttons[0].textContent.trim() || card.textContent.includes(category) ? '' : 'none'; });
    }));
  }

  // Interactive geographic SVG map and country-specific destination cards.
  if (pageName === 'destinations.html' && window.WAMAMBO_DESTINATIONS) {
    const { countries, destinations } = window.WAMAMBO_DESTINATIONS;
    const paths = [...document.querySelectorAll('main svg[role="img"] path[aria-label]')];
    const panel = [...document.querySelectorAll('main section')][2]?.querySelector('.container-luxury');
    const selectCountry = (country) => {
      paths.forEach((path) => {
        const active = path.getAttribute('aria-label')?.startsWith(country + ':');
        path.setAttribute('aria-pressed', String(active));
        path.setAttribute('fill', active ? 'hsl(var(--primary) / 0.42)' : 'hsl(var(--secondary) / 0.16)');
      });
      const info = countries.find((item) => item.key === country);
      if (!panel || !info) return;
      const list = destinations.filter((item) => item.country === country).map((item) => `<article class="overflow-hidden rounded-[2rem] border border-border bg-background shadow-[0_20px_60px_hsl(var(--foreground)/0.04)]"><img src="${item.heroImage}" alt="${item.heroAlt}" class="h-72 w-full object-cover" loading="lazy"><div class="p-7"><p class="text-xs font-body uppercase tracking-[0.3em] text-primary">${item.type}</p><h3 class="mt-3 font-display text-2xl font-bold text-foreground">${item.title}</h3><p class="mt-3 text-sm text-muted-foreground">${item.location}</p><p class="mt-6 font-body leading-relaxed text-muted-foreground">${item.summary}</p><div class="mt-4 flex flex-wrap gap-2">${item.activities.map((activity) => `<span class="rounded-full border border-border bg-card px-3 py-2 text-xs text-muted-foreground">${activity}</span>`).join('')}</div></div></article>`).join('');
      panel.innerHTML = `<div class="rounded-[2rem] border border-border bg-background p-8 mb-14"><p class="text-xs font-body uppercase tracking-[0.3em] text-primary">Selected country</p><h2 class="mt-3 font-display text-3xl font-bold text-foreground">${country}</h2><p class="mt-4 text-muted-foreground">${info.blurb}</p></div><div class="grid gap-8 lg:grid-cols-2">${list}</div>`;
    };
    paths.forEach((path) => {
      const country = path.getAttribute('aria-label').split(':')[0];
      path.setAttribute('title', country);
      path.addEventListener('click', () => selectCountry(country));
      path.addEventListener('keydown', (event) => { if (event.key === 'Enter' || event.key === ' ') selectCountry(country); });
      path.addEventListener('mouseenter', () => { if (path.getAttribute('aria-pressed') !== 'true') path.setAttribute('fill', 'hsl(var(--primary) / 0.24)'); });
      path.addEventListener('mouseleave', () => { if (path.getAttribute('aria-pressed') !== 'true') path.setAttribute('fill', 'hsl(var(--secondary) / 0.16)'); });
    });
  }
})();
