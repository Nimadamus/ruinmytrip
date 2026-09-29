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
    var url = base + (k ? '?c=' + k : '');
    try { history.replaceState(null, '', url); } catch (e) {}
    var f = root.querySelector('[data-tm-field]'); if (f) f.value = k;
    var r = root.querySelector('[data-tm-return]'); if (r) r.value = '/map' + (k ? '?c=' + k : '');
    var d = root.querySelector('[data-tm-download]'); if (d) d.href = cardBase + '/' + (k || 'none') + '.png';
  }

  function toggle(id) {
    if (readOnly || !byId[id]) return;
    if (picked[id]) delete picked[id]; else picked[id] = true;
    status('');
    refresh();
  }

  function status(msg) { var s = root.querySelector('[data-tm-status]'); if (s) s.textContent = msg; }

  function draw(geo) {
    var svg = document.createElementNS(NS, 'svg');
    svg.setAttribute('viewBox', '0 0 ' + geo.w + ' ' + geo.h);
    svg.setAttribute('role', 'img');
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
    var url = base + (n ? '?c=' + key() : '');
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

  fetch(root.getAttribute('data-tm-geo'))
    .then(function (r) { return r.json(); })
    .then(draw)
    .catch(function () { box.innerHTML = '<p class="muted tm-loading">The map did not load. Refresh to try again.</p>'; });
})();
