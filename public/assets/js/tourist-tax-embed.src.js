/* RuinMyTrip tourist tax calculator embed. Served by GET /embed/tourist-tax.js with the rates
   filled in (app/tax_embed.php). Plain DOM, textContent only, styles scoped to .rmt-ttc. */
(function () {
  var D = /*RMT_DATA*/null;
  if (!D || !D.cities) return;
  var CSS = '.rmt-ttc{font:15px/1.45 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:#1d2433;background:#fff;border:1px solid #d9dee8;border-radius:12px;padding:16px;max-width:520px;box-sizing:border-box}' +
    '.rmt-ttc *{box-sizing:border-box}.rmt-ttc h3{margin:0 0 10px;font-size:17px;line-height:1.3}' +
    '.rmt-ttc label{display:block;margin:0 0 10px;font-size:13px;color:#4a5468}' +
    '.rmt-ttc select,.rmt-ttc input{display:block;width:100%;margin-top:4px;padding:9px 10px;font:inherit;color:#1d2433;background:#fff;border:1px solid #c5ccd9;border-radius:8px;min-height:42px}' +
    '.rmt-ttc .rmt-row{display:flex;gap:10px}.rmt-ttc .rmt-row label{flex:1}' +
    '.rmt-ttc .rmt-out{margin:6px 0 10px;padding:12px;background:#f4f6fa;border-radius:8px}' +
    '.rmt-ttc .rmt-total{font-size:26px;font-weight:700;margin:0}.rmt-ttc .rmt-sub{margin:4px 0 0;font-size:13px;color:#4a5468}' +
    '.rmt-ttc .rmt-credit{margin:8px 0 0;font-size:12px;color:#4a5468}.rmt-ttc .rmt-credit a{color:#b3261e}';

  function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; }
  function money(v, cur) {
    var whole = /^(JPY|ISK|HUF|CZK)$/.test(cur);
    var n = v.toLocaleString('en-GB', { minimumFractionDigits: whole ? 0 : 2, maximumFractionDigits: whole ? 0 : 2 });
    var sym = { EUR: '€', USD: '$', GBP: '£', JPY: '¥' }[cur];
    return sym ? sym + n : n + ' ' + cur;
  }
  /* Same sum as rmt_tt_estimate() in app/tourist_tax.php. */
  function estimate(city, t, nights, people, rooms, price) {
    var paid = city.cap ? Math.min(nights, city.cap) : nights;
    var night = (t.pp || 0) * people + (t.pct || 0) / 100 * price * rooms + (t.room || 0) * rooms;
    if (t.bands) { var a = 0; t.bands.forEach(function (b) { if (price >= b[0]) a = b[1]; }); night += a * people; }
    return { total: Math.round(night * paid * 100) / 100, paid: paid, night: Math.round(night * 100) / 100 };
  }
  function num(label, value, min, max) {
    var l = el('label', null, label), i = el('input');
    i.type = 'number'; i.min = min; if (max) i.max = max; i.step = 'any'; i.value = value; i.inputMode = 'decimal';
    l.appendChild(i); return { label: l, input: i };
  }

  function mount(root) {
    if (root.getAttribute('data-rmt-ready')) return;
    root.setAttribute('data-rmt-ready', '1');
    var box = el('div', 'rmt-ttc');
    box.appendChild(el('h3', null, 'Tourist tax calculator ' + D.year));
    var cl = el('label', null, 'City'), cs = el('select');
    D.cities.forEach(function (c, i) { var o = el('option', null, c.name); o.value = String(i); cs.appendChild(o); });
    var want = root.getAttribute('data-city');
    D.cities.forEach(function (c, i) { if (c.key === want) cs.value = String(i); });
    cl.appendChild(cs); box.appendChild(cl);
    var tl = el('label', null, 'Where you are staying'), ts = el('select'); tl.appendChild(ts); box.appendChild(tl);
    var row = el('div', 'rmt-row'), n = num('Nights', 3, 1, 60), p = num('People who pay', 2, 1, 20), r = num('Rooms', 1, 1, 10);
    row.appendChild(n.label); row.appendChild(p.label); row.appendChild(r.label); box.appendChild(row);
    var pr = num('Room price per night', 150, 0); box.appendChild(pr.label);
    var out = el('div', 'rmt-out'), total = el('p', 'rmt-total'), sub = el('p', 'rmt-sub'), tip = el('p', 'rmt-sub');
    out.appendChild(total); out.appendChild(sub); out.appendChild(tip); box.appendChild(out);
    var credit = el('p', 'rmt-credit'), a = el('a', null, 'RuinMyTrip');
    credit.appendChild(document.createTextNode('Rates with sources: tourist tax calculator by '));
    credit.appendChild(a); box.appendChild(credit);

    function city() { return D.cities[+cs.value]; }
    function fillTiers() {
      var c = city(); ts.textContent = '';
      c.tiers.forEach(function (t, i) { var o = el('option', null, t.label || c.rate); o.value = String(i); ts.appendChild(o); });
      tl.style.display = c.tiers.length > 1 ? '' : 'none';
    }
    function update() {
      var c = city(), t = c.tiers[+ts.value] || c.tiers[0];
      var usesPeople = t.pp != null || t.bands, usesRooms = t.pct != null || t.room != null, usesPrice = t.pct != null || t.bands;
      p.label.style.display = usesPeople ? '' : 'none';
      r.label.style.display = usesRooms ? '' : 'none';
      pr.label.style.display = usesPrice ? '' : 'none';
      pr.label.firstChild.textContent = (t.bands ? 'Price per person per night' : 'Room price per night') + ' (' + c.cur + ')';
      function v(x, lo, hi) { var k = parseFloat(x.value); if (!isFinite(k)) k = lo; return Math.max(lo, hi ? Math.min(hi, k) : k); }
      var nights = Math.round(v(n.input, 1, 60)), res = estimate(c, t, nights, Math.round(v(p.input, 1, 20)), Math.round(v(r.input, 1, 10)), v(pr.input, 0));
      total.textContent = money(res.total, c.cur);
      sub.textContent = money(res.night, c.cur) + ' a night for ' + res.paid + (res.paid === 1 ? ' night' : ' nights') +
        (res.paid < nights ? ' (' + c.name + ' stops charging after ' + res.paid + ')' : '') + '.';
      tip.textContent = c.tip || '';
      a.href = c.url;
    }
    cs.addEventListener('change', function () { fillTiers(); update(); });
    [ts, n.input, p.input, r.input, pr.input].forEach(function (x) { x.addEventListener('input', update); x.addEventListener('change', update); });
    fillTiers(); update();
    root.appendChild(box);
  }

  function run() {
    if (!document.getElementById('rmt-ttc-css')) { var s = el('style', null, CSS); s.id = 'rmt-ttc-css'; (document.head || document.documentElement).appendChild(s); }
    Array.prototype.forEach.call(document.querySelectorAll('[data-rmt-tax-calc]'), mount);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run); else run();
})();
