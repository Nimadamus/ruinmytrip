/*
 * Autocomplete for the header search boxes.
 *
 * Progressive enhancement, not a replacement. Every search form here already works without
 * JavaScript: it submits to /search and always will. This adds a suggestion list on top and gets
 * out of the way the moment anything is wrong -- a failed request, a missing element, an old
 * browser -- leaving the plain form behind, working.
 *
 * Three things it is careful about:
 *
 *   1. STALE RESPONSES. Typing "L", "La", "Las", "Las V" fires four requests and they can come
 *      back in any order. Every response carries the query it answered and is dropped unless it
 *      matches what is in the box now, so a slow answer for "La" can never overwrite "Las V".
 *   2. THE KEYBOARD. Down, Up, Enter, Escape, Home and End all work, the active option is visibly
 *      and programmatically marked, and Enter with nothing selected does the ordinary thing:
 *      submit the form and go to the results page.
 *   3. NOT GETTING IN THE WAY. Two characters minimum, a 180ms debounce, one request in flight at
 *      a time, and clicking a suggestion navigates immediately -- the click is logged with
 *      sendBeacon, which does not delay navigation and does not care whether it succeeds.
 */
(function () {
  'use strict';

  var MIN_CHARS = 2;
  var DEBOUNCE_MS = 180;

  var meta = document.querySelector('meta[name="csrf-token"]');
  var CSRF = meta ? meta.getAttribute('content') : '';

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text != null) n.textContent = text;
    return n;
  }

  function attach(input, idx) {
    var form = input.closest('form');
    if (!form) return;

    var panel = el('div', 'suggest-panel');
    panel.id = 'suggest-panel-' + idx;
    panel.setAttribute('role', 'listbox');
    panel.hidden = true;
    form.appendChild(panel);

    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-controls', panel.id);
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('autocomplete', 'off');

    var options = [];        // the flat list of selectable rows, in visual order
    var active = -1;
    var timer = null;
    var controller = null;   // aborts the request that is no longer wanted
    var lastQuery = '';

    function close() {
      panel.hidden = true;
      input.setAttribute('aria-expanded', 'false');
      input.removeAttribute('aria-activedescendant');
      active = -1;
    }

    function setActive(i) {
      if (!options.length) return;
      if (active >= 0 && options[active]) {
        options[active].classList.remove('is-active');
        options[active].setAttribute('aria-selected', 'false');
      }
      active = (i + options.length) % options.length;
      var node = options[active];
      node.classList.add('is-active');
      node.setAttribute('aria-selected', 'true');
      input.setAttribute('aria-activedescendant', node.id);
      // Keep the highlighted row on screen when arrowing past the fold.
      if (node.scrollIntoView) node.scrollIntoView({ block: 'nearest' });
    }

    function go(node) {
      var url = node.getAttribute('data-url');
      if (!url) return;
      // Logged, then navigate. sendBeacon hands the request to the browser and returns; nothing
      // here waits on the network before the page changes.
      try {
        if (navigator.sendBeacon && CSRF) {
          var fd = new FormData();
          fd.append('_csrf', CSRF);
          fd.append('q', lastQuery);
          fd.append('type', node.getAttribute('data-type') || '');
          fd.append('id', node.getAttribute('data-id') || '');
          fd.append('position', node.getAttribute('data-position') || '0');
          navigator.sendBeacon(form.getAttribute('data-suggest-click') || '/suggest/click', fd);
        }
      } catch (e) { /* analytics is never worth a broken navigation */ }
      window.location.href = url;
    }

    function render(data) {
      panel.textContent = '';
      options = [];
      active = -1;

      if (!data || !data.groups || !data.groups.length) {
        close();
        return;
      }

      var n = 0;
      data.groups.forEach(function (group) {
        if (!group.items || !group.items.length) return;   // never an empty heading
        var head = el('div', 'suggest-group', group.label);
        head.setAttribute('role', 'presentation');
        panel.appendChild(head);

        group.items.forEach(function (item) {
          var row = el('a', 'suggest-item');
          row.id = panel.id + '-opt-' + n;
          row.setAttribute('role', 'option');
          row.setAttribute('aria-selected', 'false');
          row.setAttribute('href', item.url);
          row.setAttribute('data-url', item.url);
          row.setAttribute('data-type', item.type);
          row.setAttribute('data-id', item.id);
          row.setAttribute('data-position', String(n));
          row.appendChild(el('span', 'suggest-name', item.name));
          if (item.subtitle) row.appendChild(el('span', 'suggest-sub', item.subtitle));
          row.addEventListener('mousedown', function (ev) {
            ev.preventDefault();          // beat the blur that would close the panel first
            go(row);
          });
          panel.appendChild(row);
          options.push(row);
          n++;
        });
      });

      if (!options.length) { close(); return; }
      panel.hidden = false;
      input.setAttribute('aria-expanded', 'true');
    }

    function fetchSuggestions(q) {
      if (controller) controller.abort();
      controller = ('AbortController' in window) ? new AbortController() : null;

      var url = (form.getAttribute('data-suggest-url') || '/suggest') + '?q=' + encodeURIComponent(q);
      fetch(url, {
        headers: { 'Accept': 'application/json' },
        signal: controller ? controller.signal : undefined,
        credentials: 'same-origin'
      })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (data) {
          // The guard against a slow answer arriving after a newer one: what is in the box now is
          // the only query whose results may be shown.
          if (input.value.trim() !== q) return;
          render(data);
        })
        .catch(function () { /* offline, aborted, or a bad response: leave the plain form alone */ });
    }

    input.addEventListener('input', function () {
      var q = input.value.trim();
      lastQuery = q;
      if (timer) clearTimeout(timer);
      if (q.length < MIN_CHARS) { close(); return; }
      timer = setTimeout(function () { fetchSuggestions(q); }, DEBOUNCE_MS);
    });

    input.addEventListener('keydown', function (ev) {
      if (panel.hidden || !options.length) {
        if (ev.key === 'Escape') input.blur();
        return;                                   // Enter falls through and submits the form
      }
      switch (ev.key) {
        case 'ArrowDown': ev.preventDefault(); setActive(active + 1); break;
        case 'ArrowUp':   ev.preventDefault(); setActive(active - 1); break;
        case 'Home':      ev.preventDefault(); setActive(0); break;
        case 'End':       ev.preventDefault(); setActive(options.length - 1); break;
        case 'Escape':    ev.preventDefault(); close(); break;
        case 'Tab':       close(); break;
        case 'Enter':
          // Only when something is highlighted. Otherwise this is an ordinary search submit and
          // the results page is exactly where the person should end up.
          if (active >= 0 && options[active]) { ev.preventDefault(); go(options[active]); }
          break;
      }
    });

    input.addEventListener('blur', function () {
      // A tick, so a click on a suggestion registers before the panel goes away.
      setTimeout(close, 120);
    });
    input.addEventListener('focus', function () {
      if (options.length && input.value.trim().length >= MIN_CHARS) {
        panel.hidden = false;
        input.setAttribute('aria-expanded', 'true');
      }
    });
  }

  var inputs = document.querySelectorAll('form[role="search"] input[type="search"]');
  Array.prototype.forEach.call(inputs, attach);
})();
