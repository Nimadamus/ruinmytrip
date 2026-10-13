/*
 * The travel map (views/travel_map.php, app/travel_map.php).
 *
 * The countries live in the URL, so every tap rewrites the address, the share link, the download
 * link and the save form together: whatever the person sees is exactly what they share. No
 * storage, no account, nothing sent until they press a button.
 */
(function () {
  'use strict';
  var root = document.querySelector('.tm');
  if (!root) return;
  var box = root.querySelector('[data-tm-map]');
  var readOnly = root.getAttribute('data-tm-readonly') === '1';
  var embed = root.hasAttribute('data-tm-embed');
  var base = root.getAttribute('data-tm-base');
  var cardBase = root.getAttribute('data-tm-card');
  var picked = {};
  (root.getAttribute('data-tm-codes') || '').split('-').forEach(function (c) { if (c) picked[c] = true; });
  var byId = {}, byName = {}, shapes = {};
  var NS = 'http://www.w3.org/2000/svg';

  function codes() { return Object.keys(picked).sort(); }
  function key() { return codes().join('-'); }
  function label(n) { return n + (n === 1 ? ' country' : ' countries'); }

  function refresh() {
    var list = codes(), n = list.length, k = key();
    Object.keys(shapes).forEach(function (id) {
      shapes[id].forEach(function (el) { el.classList.toggle('on', !!picked[id]); });
    });
    var title = root.querySelector('[data-tm-title]');
    if (title) title.innerHTML = n ? "I've been to <span data-tm-count></span>" : 'Where have you been?';
    root.querySelectorAll('[data-tm-count]').forEach(function (el) { el.textContent = label(n); });
    var chips = root.querySelector('[data-tm-chips]');
    if (chips) {
      chips.textContent = '';
      list.map(function (c) { return byId[c] ? byId[c].name : c; }).sort().forEach(function (name) {
        var s = document.createElement('span'); s.className = 'chip'; s.textContent = name; chips.appendChild(s);
      });
    }
    if (readOnly) return;
    review(list);
    var url = base + (k ? '?c=' + k : '');
    // Embedded on another page (the redesigned home page): the address bar stays that page's, and
    // the "keep going" link carries the countries over to the full map.
    if (embed) {
      root.querySelectorAll('[data-tm-open]').forEach(function (a) { a.href = url; });
    } else {
      try { history.replaceState(null, '', url); } catch (e) {}
    }
    var f = root.querySelector('[data-tm-field]'); if (f) f.value = k;
    var r = root.querySelector('[data-tm-return]'); if (r) r.value = '/map' + (k ? '?c=' + k : '');
    var d = root.querySelector('[data-tm-download]'); if (d) d.href = cardBase + '/' + (k || 'none') + '.png';
  }

  /* "Been to one of these cities?": the cities with a page here in the countries coloured in,
     each a link to start a review of it. Built from data the page already carries. */
  function review(list) {
    var box = root.querySelector('[data-tm-review]'); if (!box) return;
    var all, out = [];
    try { all = JSON.parse(box.getAttribute('data-tm-cities') || '{}'); } catch (e) { return; }
    list.forEach(function (c) { (all[c] || []).forEach(function (x) { out.push(x); }); });
    out.sort(function (a, b) { return a[1] < b[1] ? -1 : a[1] > b[1] ? 1 : 0; });
    var ul = box.querySelector('[data-tm-review-list]'), rb = box.getAttribute('data-tm-review-base');
    ul.textContent = '';
    out.slice(0, 12).forEach(function (x) {
      var a = document.createElement('a');
      a.className = 'chip tm-city'; a.rel = 'nofollow'; a.setAttribute('data-cta', 'map_review');
      a.href = rb + '?destination=' + encodeURIComponent(x[0]) + '&src=map'; a.textContent = 'Review ' + x[1];
      ul.appendChild(a);
    });
    box.hidden = !out.length;
  }

  /* One row the first time a country is tapped on this page (cta_click, detail map_tap), so the
     campaign report can tell a person who used the map from one who only opened it. Nothing about
     which country. Dropped silently if anything is missing. */
  var tapSent = false;
  function tapOnce() {
    if (tapSent) return; tapSent = true;
    try {
      var m = document.querySelector('meta[name="csrf-token"]');
      if (!m || !navigator.sendBeacon) return;
      var fd = new FormData();
      fd.append('_csrf', m.getAttribute('content') || '');
      fd.append('event', 'cta_click'); fd.append('detail', 'map_tap'); fd.append('path', location.pathname);
      navigator.sendBeacon((document.body && document.body.getAttribute('data-event-url')) || '/event', fd);
    } catch (e) { /* never worth breaking the map */ }
  }

  function toggle(id) {
    if (readOnly || !byId[id]) return;
    if (picked[id]) delete picked[id]; else { picked[id] = true; tapOnce(); }
    status('');
    refresh();
  }

  function status(msg) { var s = root.querySelector('[data-tm-status]'); if (s) s.textContent = msg; }

  function draw(geo) {
    var svg = document.createElementNS(NS, 'svg');
    svg.setAttribute('viewBox', '0 0 ' + geo.w + ' ' + geo.h);
    svg.setAttribute('role', 'img');
    svg.setAttribute('aria-label', 'World map. The countries you have been to are colored in.');
    var mapBox = root.querySelector('[data-tm-map]');
    if (mapBox && mapBox.hasAttribute('aria-label') && !mapBox.hasAttribute('role')) mapBox.setAttribute('role', 'group'); // a label needs a role
    var tip = document.createElement('div'); tip.className = 'tm-tip'; tip.hidden = true; document.body.appendChild(tip);
    var dots = [];
    geo.countries.forEach(function (c) {
      var d = c.rings.map(function (r) {
        var s = 'M' + r[0] + ',' + r[1];
        for (var i = 2; i + 1 < r.length; i += 2) s += 'L' + r[i] + ',' + r[i + 1];
        return s + 'Z';
      }).join('');
      var p = document.createElementNS(NS, 'path');
      p.setAttribute('d', d);
      if (c.id) {
        byId[c.id] = c; byName[c.name.toLowerCase()] = c.id;
        p.setAttribute('data-id', c.id);
        shapes[c.id] = [p];
        if (c.tiny) dots.push(c);
      } else {
        p.style.cursor = 'default';
      }
      svg.appendChild(p);
    });
    // Small islands get a dot on top, big enough to tap and to see once it is lit.
    dots.forEach(function (c) {
      var o = document.createElementNS(NS, 'circle');
      o.setAttribute('cx', c.c[0]); o.setAttribute('cy', c.c[1]); o.setAttribute('r', 4);
      o.setAttribute('data-id', c.id);
      svg.appendChild(o);
      shapes[c.id].push(o);
    });
    svg.addEventListener('click', function (ev) {
      var id = ev.target && ev.target.getAttribute && ev.target.getAttribute('data-id');
      if (id) toggle(id);
    });
    svg.addEventListener('mousemove', function (ev) {
      var id = ev.target && ev.target.getAttribute && ev.target.getAttribute('data-id');
      if (!id) { tip.hidden = true; return; }
      tip.textContent = byId[id].name; tip.hidden = false;
      tip.style.left = (ev.clientX + 12) + 'px'; tip.style.top = (ev.clientY + 12) + 'px';
    });
    svg.addEventListener('mouseleave', function () { tip.hidden = true; });
    box.textContent = '';
    if (readOnly) box.setAttribute('data-readonly', '');
    box.appendChild(svg);
    refresh();
  }

  var find = root.querySelector('[data-tm-find]');
  if (find) {
    var add = function () {
      var id = byName[find.value.trim().toLowerCase()];
      if (!id) return false;
      picked[id] = true; find.value = ''; status(''); refresh();
      return true;
    };
    find.addEventListener('change', add);
    find.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter') { ev.preventDefault(); if (!add()) status('No country by that name. Pick one from the list.'); }
    });
  }

  var share = root.querySelector('[data-tm-share]');
  if (share) share.addEventListener('click', function () {
    var n = codes().length;
    // from=share: whoever opens it sees it as somebody else's map, with "make your own", rather
    // than as their own map already coloured in with another person's countries.
    var url = base + (n ? '?c=' + key() + '&from=share' : '');
    var text = n ? "I've been to " + label(n) + '. Where have you been?' : 'Make your travel map';
    if (navigator.share) {
      navigator.share({ title: 'My travel map', text: text, url: url }).catch(function () {});
    } else if (navigator.clipboard) {
      navigator.clipboard.writeText(url).then(function () { status('Link copied. Paste it anywhere.'); },
        function () { status(url); });
    } else {
      status(url);
    }
  });

  var started = false;
  function load() {
    if (started) return;
    started = true;
    fetch(root.getAttribute('data-tm-geo'))
      .then(function (r) { return r.json(); })
      .then(draw)
      .catch(function () { box.innerHTML = '<p class="muted tm-loading">The map did not load. Refresh to try again.</p>'; });
  }
  if (!embed) {
    load();
  } else {
    /* On the home page the map is not what the first paint waits for: it loads once the page has,
       or straight away when somebody reaches for it. */
    box.addEventListener('pointerenter', load, { once: true });
    box.addEventListener('touchstart', load, { once: true, passive: true });
    var later = function () { setTimeout(load, 600); };
    if (document.readyState === 'complete') later(); else window.addEventListener('load', later);
  }
})();
