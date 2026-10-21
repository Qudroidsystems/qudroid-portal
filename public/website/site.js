/* School website interactions: sticky header, mobile menu, hero slider,
   counters, reveal-on-scroll, gallery lightbox, back-to-top. No libraries. */
(function () {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));

  // Header shadow on scroll + back to top
  const header = $('.header');
  const toTop = $('.to-top');
  const onScroll = () => {
    const y = window.scrollY;
    if (header) header.classList.toggle('scrolled', y > 10);
    if (toTop) toTop.classList.toggle('show', y > 600);
  };
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();
  if (toTop) toTop.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

  // Mobile menu
  const nav = $('.nav');
  const scrim = $('.scrim');
  const setNav = (open) => {
    if (!nav) return;
    nav.classList.toggle('open', open);
    if (scrim) scrim.classList.toggle('show', open);
    document.body.style.overflow = open ? 'hidden' : '';
  };
  $$('.burger').forEach((b) => b.addEventListener('click', () => setNav(true)));
  $$('.nav-close, .scrim').forEach((b) => b.addEventListener('click', () => setNav(false)));
  $$('.nav > .item.has-drop > a').forEach((a) =>
    a.addEventListener('click', (e) => {
      if (window.innerWidth > 1060) return;
      e.preventDefault();
      a.parentElement.classList.toggle('open');
    })
  );

  // Hero slider
  const slides = $$('.hero .slide');
  if (slides.length > 1) {
    const dots = $$('.hero .dots button');
    let i = 0;
    let timer;
    const show = (n) => {
      i = (n + slides.length) % slides.length;
      slides.forEach((s, k) => s.classList.toggle('on', k === i));
      dots.forEach((d, k) => d.classList.toggle('on', k === i));
    };
    const play = () => { clearInterval(timer); timer = setInterval(() => show(i + 1), 6500); };
    dots.forEach((d, k) => d.addEventListener('click', () => { show(k); play(); }));
    play();
  }

  // Reveal on scroll + counters
  const count = (el) => {
    const target = parseInt(el.dataset.count, 10) || 0;
    const suffix = el.dataset.suffix || '';
    const start = performance.now();
    const dur = 1600;
    const step = (t) => {
      const p = Math.min(1, (t - start) / dur);
      const eased = 1 - Math.pow(1 - p, 3);
      el.textContent = Math.round(target * eased).toLocaleString() + (p === 1 ? suffix : '');
      if (p < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
  };
  if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver((entries) => {
      entries.forEach((e) => {
        if (!e.isIntersecting) return;
        e.target.classList.add('in');
        if (e.target.dataset.count !== undefined) count(e.target);
        io.unobserve(e.target);
      });
    }, { threshold: 0.15 });
    $$('.reveal, [data-count]').forEach((el) => io.observe(el));
  } else {
    $$('.reveal').forEach((el) => el.classList.add('in'));
    $$('[data-count]').forEach(count);
  }

  // Lightbox for gallery links: <a data-lightbox="group" href="big.jpg" data-caption="...">
  const groups = {};
  $$('a[data-lightbox]').forEach((a) => {
    const g = a.dataset.lightbox;
    (groups[g] = groups[g] || []).push(a);
  });
  if (Object.keys(groups).length) {
    const box = document.createElement('div');
    box.className = 'lightbox';
    box.innerHTML = '<button class="close" aria-label="Close"><i class="ri-close-line"></i></button>' +
      '<button class="prev" aria-label="Previous"><i class="ri-arrow-left-s-line"></i></button>' +
      '<img alt=""><div class="cap"></div>' +
      '<button class="next" aria-label="Next"><i class="ri-arrow-right-s-line"></i></button>';
    document.body.appendChild(box);
    const img = $('img', box);
    const cap = $('.cap', box);
    let list = [];
    let at = 0;
    const open = (g, k) => { list = groups[g]; at = k; render(); box.classList.add('show'); document.body.style.overflow = 'hidden'; };
    const close = () => { box.classList.remove('show'); document.body.style.overflow = ''; };
    const render = () => { const a = list[at]; img.src = a.href; cap.textContent = a.dataset.caption || ''; };
    const move = (d) => { at = (at + d + list.length) % list.length; render(); };
    Object.entries(groups).forEach(([g, items]) => items.forEach((a, k) => a.addEventListener('click', (e) => { e.preventDefault(); open(g, k); })));
    $('.close', box).addEventListener('click', close);
    $('.prev', box).addEventListener('click', () => move(-1));
    $('.next', box).addEventListener('click', () => move(1));
    box.addEventListener('click', (e) => { if (e.target === box) close(); });
    document.addEventListener('keydown', (e) => {
      if (!box.classList.contains('show')) return;
      if (e.key === 'Escape') close();
      if (e.key === 'ArrowLeft') move(-1);
      if (e.key === 'ArrowRight') move(1);
    });
    let sx = null;
    box.addEventListener('touchstart', (e) => { sx = e.touches[0].clientX; }, { passive: true });
    box.addEventListener('touchend', (e) => { if (sx === null) return; const dx = e.changedTouches[0].clientX - sx; if (Math.abs(dx) > 50) move(dx < 0 ? 1 : -1); sx = null; });
  }
})();
