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
    /* Where the browser has a share sheet, that IS sharing: it reaches every app the person has,
       including the three we link by hand. So the summary becomes the button and the list of
       destinations underneath never has to open. */
    var row0 = b.closest('.share-row');
    var sum = row0 && row0.querySelector('summary');
    if (sum) {
      sum.addEventListener('click', function (ev) { ev.preventDefault(); b.click(); });
      sum.textContent = 'Share';
    }
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

/* ------------------------------------------------------------------ photo pickers
 * A bare <input type="file"> tells you "3 files" and nothing else: not which ones, not whether the
 * one you meant is among them, not whether it is a photograph at all. Everything here is an
 * enhancement of a control that already works, so the form is unchanged with JavaScript off: the
 * input stays in the DOM, keeps its name, and keeps its keyboard behaviour.
 */
(function () {
  var inputs = document.querySelectorAll('input[type="file"][accept*="image"]');
  if (!inputs.length || !window.FileReader) return;

  Array.prototype.forEach.call(inputs, function (input) {
    var strip = document.createElement('div');
    strip.className = 'pick-preview';
    input.parentNode.insertBefore(strip, input.nextSibling);

    input.addEventListener('change', function () {
      strip.innerHTML = '';
      var files = Array.prototype.slice.call(input.files || []);
      if (!files.length) return;

      files.forEach(function (f) {
        if (!/^image\//.test(f.type)) return;
        var cell = document.createElement('figure');
        cell.className = 'pick-preview-cell';
        var img = document.createElement('img');
        img.alt = '';
        var cap = document.createElement('figcaption');
        /* Size shown up front, because 8MB is the limit and finding that out after the upload
           fails is the worst moment to find it out. */
        cap.textContent = f.size < 1024 ? f.size + ' B' : Math.round(f.size / 1024) + ' KB';
        if (f.size > 8 * 1024 * 1024) {
          cell.classList.add('too-big');
          cap.textContent = Math.round(f.size / 1048576 * 10) / 10 + ' MB, too large';
        }
        var reader = new FileReader();
        reader.onload = function (e) { img.src = e.target.result; };
        reader.readAsDataURL(f);
        cell.appendChild(img);
        cell.appendChild(cap);
        strip.appendChild(cell);
      });
    });
  });
})();

/* The "where" field on a plan: places this site holds, in the city the trip is to.
 *
 * A native <datalist> filled from the server. No custom dropdown, no keyboard handling to get
 * wrong, and no dependency: with JavaScript off the field is a plain text box and typing an exact
 * name still attaches the plan on the server side.
 *
 * Careful about the same thing the header suggest is careful about: responses can arrive out of
 * order, so one that does not answer what is in the box now is dropped.
 */
(function () {
  'use strict';
  var input = document.querySelector('input[data-place-suggest]');
  if (!input || !window.fetch) return;
  var list = document.getElementById(input.getAttribute('list'));
  var dest = parseInt(input.getAttribute('data-dest') || '0', 10);
  if (!list || !dest) return;

  var timer = null;
  var latest = '';

  /* A note under the field saying which of the two things is about to happen. Picking a place we
     hold attaches the plan to that place's page; typing anything else keeps it as text. Both are
     fine and they are not the same, and a person should not have to guess which they got. */
  var note = document.createElement('p');
  note.className = 'hint place-pick-note';
  note.hidden = true;
  input.parentNode.appendChild(note);

  var known = {};

  function fill(places) {
    list.innerHTML = '';
    known = {};
    places.forEach(function (p) {
      var o = document.createElement('option');
      o.value = p.name;
      if (p.type) o.label = p.type;
      list.appendChild(o);
      known[p.name.toLowerCase()] = p;
    });
    say();
  }

  function say() {
    var v = input.value.trim();
    if (v.length < 2) { note.hidden = true; return; }
    var hit = known[v.toLowerCase()];
    if (hit) {
      note.textContent = 'Links to ' + hit.name + (hit.type ? ' (' + hit.type + ')' : '');
      note.classList.add('is-linked');
    } else {
      note.textContent = 'Saved as text. Pick from the list to link it to a place.';
      note.classList.remove('is-linked');
    }
    note.hidden = false;
  }

  input.addEventListener('change', say);
  input.addEventListener('input', function () {
    say();
    var q = input.value.trim();
    latest = q;
    if (timer) clearTimeout(timer);
    if (q.length < 2) { fill([]); note.hidden = true; return; }
    timer = setTimeout(function () {
      fetch('/suggest/places?q=' + encodeURIComponent(q) + '&dest=' + dest, {
        headers: { 'Accept': 'application/json' }
      }).then(function (r) { return r.ok ? r.json() : null; })
        .then(function (d) {
          if (!d || !d.places) return;
          if (input.value.trim() !== latest) return;   // a slow answer for an older query
          fill(d.places);
        })
        .catch(function () { /* the plain text box still works */ });
    }, 180);
  });
})();
