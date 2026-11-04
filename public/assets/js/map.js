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

    /* Past about forty points a dense centre is still a pile of overlapping dots that a thumb
       cannot tell apart. Nearby dots merge into one numbered circle; tapping it zooms in until
       they separate. Done here in a few lines rather than with a plugin, because the grid is all
       a city map needs. Trip maps keep every dot: the day colours are the point there. */
    var cluster = null;

    /* On a trip of several days the useful question is not "where is everything", it is "what is
       Tuesday". Each day gets a colour and a line in the key. One day, or no days at all, gets the
       plain treatment: a legend with one entry is furniture. */
    var dayOrder = [];
    points.forEach(function (p) {
      if (p.group && dayOrder.indexOf(p.group) === -1) dayOrder.push(p.group);
    });
    dayOrder.sort();
    var byDay = dayOrder.length > 1 && dayOrder.length <= 10;
    cluster = (!byDay && points.length > 40) ? { layer: L.layerGroup().addTo(map), cat: null } : null;
    var palette = ['#0f766e', '#b45309', '#1d4ed8', '#a21caf', '#15803d', '#be123c',
                   '#0e7490', '#7c2d12', '#4338ca', '#365314'];
    var colourFor = function (p) {
      if (!byDay) return '#0f766e';
      var i = dayOrder.indexOf(p.group);
      return palette[(i < 0 ? 0 : i) % palette.length];
    };

    var bounds = [];
    var markers = [];
    points.forEach(function (p) {
      var col = colourFor(p);
      var marker = (dots || byDay)
        ? L.circleMarker([p.lat, p.lng], {
            radius: 7, weight: 2, color: col, fillColor: col, fillOpacity: 0.75
          })
        : L.marker([p.lat, p.lng]);
      if (!cluster) marker.addTo(map);
      var html = '<b>' + escapeHtml(p.label || '') + '</b>';
      if (p.meta) html += '<br><span>' + escapeHtml(p.meta) + '</span>';
      if (p.href) html = '<a href="' + escapeAttr(p.href) + '">' + html + '</a>';
      marker.bindPopup(html, { closeButton: true, autoPan: true, maxWidth: 260 });
      markers.push({ layer: marker, cat: p.cat || null, p: p });
      bounds.push([p.lat, p.lng]);
    });

    if (bounds.length === 1) map.setView(bounds[0], 15);
    else map.fitBounds(bounds, { padding: [30, 30], maxZoom: 16 });

    /* Greedy, by screen distance: a point joins the first group whose first point is within
       RADIUS pixels at this zoom, otherwise it starts one. A fixed grid was tried first and put
       two numbered circles on top of each other wherever a dense centre straddled a cell line. */
    var RADIUS = 52;
    var drawClusters = function () {
      if (!cluster) return;
      cluster.layer.clearLayers();
      var z = map.getZoom();
      var groups = [];
      markers.forEach(function (m) {
        if (cluster.cat !== null && m.cat !== cluster.cat) return;
        var pt = map.project([m.p.lat, m.p.lng], z);
        for (var i = 0; i < groups.length; i++) {
          if (groups[i].pt.distanceTo(pt) < RADIUS) { groups[i].items.push(m); return; }
        }
        groups.push({ pt: pt, items: [m] });
      });
      groups.forEach(function (g) {
        var group = g.items;
        if (group.length === 1 || z >= map.getMaxZoom()) {
          group.forEach(function (m) { cluster.layer.addLayer(m.layer); });
          return;
        }
        var lat = 0, lng = 0, gb = [];
        group.forEach(function (m) { lat += m.p.lat; lng += m.p.lng; gb.push([m.p.lat, m.p.lng]); });
        var n = group.length;
        var size = n < 10 ? 30 : (n < 50 ? 36 : 42);
        var icon = L.divIcon({
          className: 'map-cluster',
          html: '<span>' + n + '</span>',
          iconSize: [size, size]
        });
        var c = L.marker([lat / n, lng / n], {
          icon: icon, keyboard: true, title: n + ' places here, zoom in', alt: n + ' places'
        });
        c.on('click', function () {
          var b = L.latLngBounds(gb);
          if (b.getNorthEast().equals(b.getSouthWest())) map.setView(b.getCenter(), map.getMaxZoom());
          else map.fitBounds(b, { padding: [40, 40] });
        });
        cluster.layer.addLayer(c);
      });
    };
    if (cluster) { map.on('zoomend', drawClusters); drawClusters(); }

    /* Category filtering on a city map. Only where there is something to filter: a key with one
       entry is furniture, and a row of fourteen chips over a map is a second map. */
    var cats = [];
    points.forEach(function (p) {
      if (p.cat && cats.indexOf(p.cat) === -1) cats.push(p.cat);
    });
    if (!byDay && cats.length > 1 && cats.length <= 12) {
      var bar = document.createElement('div');
      bar.className = 'map-cats';
      var mk = function (label, value) {
        var a = document.createElement('button');
        a.type = 'button';
        a.className = 'chip' + (value === null ? ' is-on' : '');
        a.textContent = label;
        a.addEventListener('click', function () {
          bar.querySelectorAll('.chip').forEach(function (c) { c.classList.remove('is-on'); });
          a.classList.add('is-on');
          var keep = [];
          markers.forEach(function (m) {
            var show = value === null || m.cat === value;
            if (show) keep.push([m.p.lat, m.p.lng]);
            if (cluster) return;
            if (show) m.layer.addTo(map);
            else map.removeLayer(m.layer);
          });
          if (cluster) { cluster.cat = value; drawClusters(); }
          if (keep.length) map.fitBounds(keep, { padding: [30, 30], maxZoom: 16 });
        });
        return a;
      };
      bar.appendChild(mk('All', null));
      cats.forEach(function (c) { bar.appendChild(mk(c, c)); });
      el.parentNode.insertBefore(bar, el);
    }

    if (byDay) {
      /* The key is also the filter. On a six day trip the useful question is "what is Tuesday",
         and a legend that only explains the colours makes the reader answer it by squinting. */
      var key = document.createElement('div');
      key.className = 'map-key';
      var select = function (g) {
        var keep = [];
        markers.forEach(function (m) {
          var show = g === null || m.p.group === g;
          if (show) { m.layer.addTo(map); keep.push([m.p.lat, m.p.lng]); }
          else map.removeLayer(m.layer);
        });
        if (keep.length) map.fitBounds(keep, { padding: [30, 30], maxZoom: 16 });
        key.querySelectorAll('.map-key-item').forEach(function (n) {
          n.classList.toggle('is-on', n.getAttribute('data-day') === (g === null ? '' : g));
        });
      };
      var item = function (label, g, colour) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'map-key-item' + (g === null ? ' is-on' : '');
        b.setAttribute('data-day', g === null ? '' : g);
        if (colour) {
          var dot = document.createElement('i');
          dot.style.background = colour;
          b.appendChild(dot);
        }
        b.appendChild(document.createTextNode(label));
        b.addEventListener('click', function () { select(g); });
        return b;
      };
      key.appendChild(item('All days', null, null));
      dayOrder.forEach(function (g, i) {
        key.appendChild(item(dayLabel(g), g, palette[i % palette.length]));
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
