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

    var bounds = [];
    points.forEach(function (p) {
      var marker = dots
        ? L.circleMarker([p.lat, p.lng], {
            radius: 5, weight: 2, color: '#0f766e', fillColor: '#14b8a6', fillOpacity: 0.9
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

    /* On a phone a map inside a scrolling page steals the gesture. One tap turns dragging on, so
       scrolling past it works and using it still works. */
    if (window.matchMedia && window.matchMedia('(pointer: coarse)').matches) {
      map.dragging.disable();
      el.addEventListener('click', function () { map.dragging.enable(); }, { once: true });
    }
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
