/* Maps of published things.
 *
 * Every marker here is something somebody chose to put on this site: a place we hold, or a plan
 * whose owner made it visible. There is no geolocation call in this file and there must never be
 * one: a plan is an intention, a live position is surveillance.
 *
 * Progressive: if Leaflet fails to load, the container stays empty and the list that is already on
 * the page is still the answer. Nothing depends on the map to be usable.
 */
(function () {
  'use strict';

  function draw(el) {
    var raw = el.getAttribute('data-points');
    if (!raw) return;
    var points;
    try { points = JSON.parse(raw); } catch (e) { return; }
    if (!points || !points.length) return;

    var map = L.map(el, { scrollWheelZoom: false, attributionControl: true });
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 18,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    /* Eighty teardrop pins over a city centre is a blue blob, not a map. Past a couple of dozen
       points the marker becomes a small dot, which reads as a distribution and still clicks. */
    var dots = points.length > 25;

    /* On a trip of several days the useful question is not "where is everything", it is "what is
       Tuesday". Each day gets a colour and a line in the key. One day, or no days at all, gets the
       plain treatment: a legend with one entry is furniture. */
    var dayOrder = [];
    points.forEach(function (p) {
      if (p.group && dayOrder.indexOf(p.group) === -1) dayOrder.push(p.group);
    });
    dayOrder.sort();
    var byDay = dayOrder.length > 1 && dayOrder.length <= 10;
    var palette = ['#0f766e', '#b45309', '#1d4ed8', '#a21caf', '#15803d', '#be123c',
                   '#0e7490', '#7c2d12', '#4338ca', '#365314'];
    var colourFor = function (p) {
      if (!byDay) return '#0f766e';
      var i = dayOrder.indexOf(p.group);
      return palette[(i < 0 ? 0 : i) % palette.length];
    };

    var bounds = [];
    points.forEach(function (p) {
      var col = colourFor(p);
      var marker = (dots || byDay)
        ? L.circleMarker([p.lat, p.lng], {
            radius: 7, weight: 2, color: col, fillColor: col, fillOpacity: 0.75
          }).addTo(map)
        : L.marker([p.lat, p.lng]).addTo(map);
      var html = '<b>' + escapeHtml(p.label || '') + '</b>';
      if (p.meta) html += '<br><span>' + escapeHtml(p.meta) + '</span>';
      if (p.href) html = '<a href="' + escapeAttr(p.href) + '">' + html + '</a>';
      marker.bindPopup(html);
      bounds.push([p.lat, p.lng]);
    });

    if (bounds.length === 1) map.setView(bounds[0], 15);
    else map.fitBounds(bounds, { padding: [30, 30], maxZoom: 16 });

    if (byDay) {
      var key = document.createElement('div');
      key.className = 'map-key';
      dayOrder.forEach(function (g, i) {
        var item = document.createElement('span');
        item.className = 'map-key-item';
        var dot = document.createElement('i');
        dot.style.background = palette[i % palette.length];
        item.appendChild(dot);
        item.appendChild(document.createTextNode(dayLabel(g)));
        key.appendChild(item);
      });
      el.parentNode.insertBefore(key, el.nextSibling);
    }

    /* On a phone a map inside a scrolling page steals the gesture. One tap turns dragging on, so
       scrolling past it works and using it still works. */
    if (window.matchMedia && window.matchMedia('(pointer: coarse)').matches) {
      map.dragging.disable();
      el.addEventListener('click', function () { map.dragging.enable(); }, { once: true });
    }
  }

  /* "2026-10-13" is a fact and "Tue 13 Oct" is a day. Parsed as a plain date rather than through
     the Date constructor's timezone rules, which would show the day before in the Americas. */
  function dayLabel(g) {
    var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(g || '');
    if (!m) return g || '';
    var d = new Date(Date.UTC(+m[1], +m[2] - 1, +m[3]));
    var days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    var mon = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return days[d.getUTCDay()] + ' ' + d.getUTCDate() + ' ' + mon[d.getUTCMonth()];
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function escapeAttr(s) { return escapeHtml(s); }

  function init() {
    if (typeof L === 'undefined') return;
    var nodes = document.querySelectorAll('.map-canvas[data-points]');
    for (var i = 0; i < nodes.length; i++) draw(nodes[i]);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
