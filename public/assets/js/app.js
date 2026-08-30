// RuinMyTrip — minimal progressive enhancement (no framework).
document.addEventListener('click', function (e) {
  const t = e.target.closest('[data-confirm]');
  if (t && !confirm(t.getAttribute('data-confirm'))) e.preventDefault();
});
// Close mobile nav when a link is tapped.
document.querySelectorAll('.site-nav a').forEach(a =>
  a.addEventListener('click', () => document.body.classList.remove('nav-open')));

document.addEventListener('click', function (e) {
  const b = e.target.closest('[data-copy]');
  if (!b) return;
  e.preventDefault();
  const url = b.getAttribute('data-copy') || '';
  const prev = b.textContent;
  const done = function () { b.textContent = 'Copied'; setTimeout(function () { b.textContent = prev; }, 1600); };
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(url).then(done).catch(function () { window.prompt('Copy', url); });
  } else {
    window.prompt('Copy', url);
  }
});

// Native share where the browser has it. The button is hidden by default so a desktop browser
// without navigator.share never shows a control that would do nothing.
(function () {
  if (!navigator.share) return;
  document.querySelectorAll('.share-row .js-share-native').forEach(function (b) {
    b.hidden = false;
    b.addEventListener('click', function () {
      const row = b.closest('.share-row');
      navigator.share({
        title: row.getAttribute('data-share-text') || document.title,
        text: row.getAttribute('data-share-text') || '',
        url: row.getAttribute('data-share-url') || location.href
      }).catch(function () { /* the sharer cancelled, which is not an error */ });
    });
  });
})();

// @mention autocomplete.
//
// Mentions notify the person named and are how a conversation pulls somebody in, which only works
// if you can remember their exact username. The box does the remembering: type @ and two letters
// and pick from what comes back. Everything degrades to a plain textarea with no JS -- typing the
// username by hand has always worked and still does.
(function () {
  const url = document.body.getAttribute('data-suggest-users');
  if (!url) return;

  let box = null, target = null, items = [], active = -1, timer = null;

  function close() {
    if (box) box.remove();
    box = null; target = null; items = []; active = -1;
  }

  function tokenAt(el) {
    const pos = el.selectionStart;
    const upto = el.value.slice(0, pos);
    const m = upto.match(/(?:^|[\s(])@([A-Za-z0-9_]{1,30})$/);
    return m ? { q: m[1], start: pos - m[1].length - 1, end: pos } : null;
  }

  function render(el, tok) {
    if (!items.length) { close(); return; }
    if (!box) {
      box = document.createElement('div');
      box.className = 'card';
      box.style.cssText = 'position:absolute;z-index:60;max-width:280px;padding:4px 0;box-shadow:0 6px 20px rgba(0,0,0,.12)';
      document.body.appendChild(box);
    }
    const r = el.getBoundingClientRect();
    box.style.left = (window.scrollX + r.left + 12) + 'px';
    box.style.top = (window.scrollY + r.bottom - 6) + 'px';
    box.innerHTML = '';
    items.forEach(function (u, i) {
      const row = document.createElement('div');
      row.style.cssText = 'padding:6px 12px;cursor:pointer;' + (i === active ? 'background:rgba(0,0,0,.06)' : '');
      row.textContent = '@' + u.username + (u.name ? ' · ' + u.name : '');
      row.addEventListener('mousedown', function (e) { e.preventDefault(); pick(el, tok, u); });
      box.appendChild(row);
    });
  }

  function pick(el, tok, u) {
    const before = el.value.slice(0, tok.start);
    const after = el.value.slice(tok.end);
    el.value = before + '@' + u.username + ' ' + after;
    const caret = (before + '@' + u.username + ' ').length;
    el.setSelectionRange(caret, caret);
    close();
    el.focus();
  }

  document.addEventListener('input', function (e) {
    const el = e.target;
    if (!(el instanceof HTMLTextAreaElement)) return;
    const tok = tokenAt(el);
    if (!tok) { close(); return; }
    target = el;
    clearTimeout(timer);
    timer = setTimeout(function () {
      fetch(url + '?q=' + encodeURIComponent(tok.q), { credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.json() : { users: [] }; })
        .then(function (d) {
          if (target !== el) return;
          items = d.users || [];
          active = items.length ? 0 : -1;
          render(el, tok);
        })
        .catch(close);
    }, 150);
  });

  document.addEventListener('keydown', function (e) {
    if (!box || e.target !== target) return;
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault();
      active = (active + (e.key === 'ArrowDown' ? 1 : items.length - 1)) % items.length;
      render(target, tokenAt(target) || { start: 0, end: 0 });
    } else if (e.key === 'Enter' || e.key === 'Tab') {
      const tok = tokenAt(target);
      if (tok && items[active]) { e.preventDefault(); pick(target, tok, items[active]); }
    } else if (e.key === 'Escape') {
      close();
    }
  });

  document.addEventListener('click', function (e) { if (box && !box.contains(e.target)) close(); });
})();

// Install the service worker. It caches the shell only -- see public/sw.js for why no HTML.
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function () {
    navigator.serviceWorker.register('/sw.js').catch(function () { /* fine without it */ });
  });
}
