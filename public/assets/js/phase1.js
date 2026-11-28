/* Phase 1 (app/phase1.php): warning tabs, guest "Helpful / Saved Me" votes, Save to Trip Checklist
   with the signup prompt, and the three step trap report. Every form also works without this file. */
(function () {
  'use strict';
  var STORE = 'rmt.p1.votes';
  function readVotes() { try { return JSON.parse(window.localStorage.getItem(STORE) || '[]') || []; } catch (e) { return []; } }
  function addVote(id) { try { var v = readVotes(); if (v.indexOf(id) < 0) { v.push(id); window.localStorage.setItem(STORE, JSON.stringify(v.slice(-500))); } } catch (e) {} }
  function post(form) {
    return fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json().then(function (j) { j._status = r.status; return j; }); });
  }

  /* tabs */
  var tabs = document.querySelectorAll('[data-p1-tab]');
  function showCat(cat) {
    tabs.forEach(function (t) { t.setAttribute('aria-selected', t.getAttribute('data-p1-tab') === cat ? 'true' : 'false'); });
    document.querySelectorAll('.p1-card').forEach(function (c) { c.hidden = !(cat === 'all' || c.getAttribute('data-cat') === cat); });
    document.querySelectorAll('[data-p1-empty]').forEach(function (p) { p.hidden = p.getAttribute('data-p1-empty') !== cat; });
  }
  tabs.forEach(function (t) { t.addEventListener('click', function () { showCat(t.getAttribute('data-p1-tab')); }); });
  if (location.hash === '#scams' && document.querySelector('[data-p1-tab="scams"]')) showCat('scams');

  /* votes */
  var mine = readVotes();
  document.querySelectorAll('[data-vote-id]').forEach(function (b) {
    if (mine.indexOf(b.getAttribute('data-vote-id')) >= 0) b.setAttribute('aria-pressed', 'true');
  });
  document.querySelectorAll('form[data-p1-vote]').forEach(function (f) {
    f.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var b = f.querySelector('button'); var id = b.getAttribute('data-vote-id');
      if (b.getAttribute('aria-pressed') === 'true') return;
      b.disabled = true;
      post(f).then(function (j) {
        b.disabled = false;
        if (j.error === 'expired') { f.submit(); return; }
        if (!j.ok) return;
        b.setAttribute('aria-pressed', 'true'); addVote(id);
        var n = b.querySelector('.p1-vote-n'); if (n) { n.textContent = j.count; n.hidden = !j.count; }
      }).catch(function () { b.disabled = false; f.submit(); });
    });
  });

  /* save to checklist */
  var dlg = document.getElementById('p1-join');
  document.querySelectorAll('form[data-p1-save]').forEach(function (f) {
    f.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var b = f.querySelector('button');
      b.disabled = true;
      post(f).then(function (j) {
        b.disabled = false;
        if (j.error === 'expired' || !j.ok) { f.submit(); return; }
        if (j.join) {
          if (!dlg || typeof dlg.showModal !== 'function') { window.location.href = '/checklist/join'; return; }
          var copy = dlg.querySelector('[data-p1-join-copy]');
          if (copy) {
            if (!copy.getAttribute('data-one')) copy.setAttribute('data-one', copy.textContent);
            copy.textContent = f.getAttribute('data-full') === '1'
              ? copy.getAttribute('data-one').replace('save this warning to your trip checklist', 'save this city checklist to your trip')
              : copy.getAttribute('data-one');
          }
          dlg.showModal();
          return;
        }
        if (j.saved) { b.textContent = 'Saved to your checklist'; b.classList.add('is-saved'); }
      }).catch(function () { b.disabled = false; f.submit(); });
    });
  });

  /* three step report */
  document.querySelectorAll('form[data-p1-steps]').forEach(function (f) {
    var steps = f.querySelectorAll('.p1-step'); var bar = f.querySelectorAll('.p1-steps-bar li'); var at = 0;
    f.classList.add('p1-js');
    f.querySelectorAll('.p1-step-nav, [data-next], [data-back]').forEach(function (x) { x.hidden = false; });
    function go(i) {
      at = i;
      steps.forEach(function (s, k) { s.hidden = k !== i; });
      bar.forEach(function (l, k) { l.classList.toggle('on', k <= i); });
      var first = steps[i].querySelector('input:not([type=hidden]), textarea, select'); if (first && i > 0) first.focus();
    }
    function valid(i) {
      var ok = true;
      steps[i].querySelectorAll('input, textarea, select').forEach(function (x) { if (ok && x.willValidate && !x.checkValidity()) { x.reportValidity(); ok = false; } });
      return ok;
    }
    f.addEventListener('click', function (ev) {
      if (ev.target.closest('[data-next]')) { if (valid(at)) go(Math.min(at + 1, steps.length - 1)); }
      else if (ev.target.closest('[data-back]')) { go(Math.max(at - 1, 0)); }
    });
    f.addEventListener('submit', function (ev) { for (var i = 0; i < steps.length; i++) { if (!valid(i)) { ev.preventDefault(); go(i); valid(i); return; } } });
    go(0);
  });
})();
