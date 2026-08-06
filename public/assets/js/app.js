// RuinMyTrip — minimal progressive enhancement (no framework, no build step).
document.addEventListener('click', function (e) {
  const t = e.target.closest('[data-confirm]');
  if (t && !confirm(t.getAttribute('data-confirm'))) e.preventDefault();
});

// Close mobile nav when a link is tapped.
document.querySelectorAll('.site-nav a').forEach(a =>
  a.addEventListener('click', () => document.body.classList.remove('nav-open')));

/**
 * Destination autocomplete.
 *
 * Progressive enhancement in the strict sense: every input this attaches to already sits inside a
 * working GET form, so with JS off (or if /api/suggest is unreachable) the field still submits to
 * /search and returns real results — including the typo-tolerant "did you mean" fallback, which is
 * computed server-side for exactly this reason. Nothing here is required to use the site.
 */
(function () {
  const MIN = 2;
  const cache = new Map();

  function debounce(fn, ms) {
    let t;
    return function (...a) { clearTimeout(t); t = setTimeout(() => fn.apply(this, a), ms); };
  }

  document.querySelectorAll('input[data-suggest]').forEach(function (input) {
    const wrap = input.closest('.ac-wrap');
    const list = wrap && wrap.querySelector('.ac-list');
    if (!list) return;
    let items = [];
    let sel = -1;

    function close() { list.classList.remove('on'); list.innerHTML = ''; sel = -1; items = []; }

    function render(data) {
      if (!data.length) { close(); return; }
      items = data;
      list.innerHTML = data.map(function (it, i) {
        // Values come from our own API, but they are still user-influenced (destination names are
        // data). Escape rather than interpolate raw into innerHTML.
        const label = String(it.label).replace(/[&<>"']/g, c =>
          ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const kind = it.type === 'category' ? '<span class="k">warnings</span>'
                   : (it.fuzzy ? '<span class="k">did you mean?</span>' : '');
        return '<a role="option" data-i="' + i + '" href="' + it.url + '">' + label + ' ' + kind + '</a>';
      }).join('');
      list.classList.add('on');
      sel = -1;
    }

    const fetchSuggestions = debounce(function () {
      const q = input.value.trim();
      if (q.length < MIN) { close(); return; }
      if (cache.has(q)) { render(cache.get(q)); return; }
      fetch('/api/suggest?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
        .then(r => r.ok ? r.json() : { items: [] })
        .then(function (d) {
          const list2 = (d && d.items) || [];
          cache.set(q, list2);
          // The field may have moved on while the request was in flight.
          if (input.value.trim() === q) render(list2);
        })
        .catch(close);   // an unreachable endpoint must never break the form
    }, 180);

    input.addEventListener('input', fetchSuggestions);
    input.addEventListener('focus', function () { if (input.value.trim().length >= MIN) fetchSuggestions(); });

    input.addEventListener('keydown', function (e) {
      if (!list.classList.contains('on')) return;
      const links = list.querySelectorAll('a');
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        links.forEach(l => l.classList.remove('sel'));
        sel = e.key === 'ArrowDown' ? Math.min(sel + 1, links.length - 1) : Math.max(sel - 1, -1);
        if (sel >= 0) links[sel].classList.add('sel');
      } else if (e.key === 'Enter' && sel >= 0) {
        e.preventDefault();
        window.location = items[sel].url;
      } else if (e.key === 'Escape') {
        close();
      }
    });

    document.addEventListener('click', function (e) { if (!wrap.contains(e.target)) close(); });
  });
})();
