/*
 * The two funnel steps only the browser can see.
 *
 * Everything the server can observe for itself is recorded there, because a client-reported step
 * is one a client can decline to report. That leaves exactly two things worth sending: a "write a
 * review" control being pressed, and a saved draft being put back into an empty form.
 *
 * Deliberately not: scroll depth, mouse movement, focus, time on page, keystrokes. We are looking
 * for where people give up between wanting to write and having written, and none of that answers
 * it.
 *
 * Sent with sendBeacon so nothing waits on the network before the page changes, and dropped
 * silently if anything is unavailable. A measurement is never worth a broken navigation.
 */
(function () {
  'use strict';

  var meta = document.querySelector('meta[name="csrf-token"]');
  var CSRF = meta ? meta.getAttribute('content') : '';
  var URL_ = (document.body && document.body.getAttribute('data-event-url')) || '/event';

  function send(event, ctx) {
    if (!CSRF || !navigator.sendBeacon) return;
    try {
      var fd = new FormData();
      fd.append('_csrf', CSRF);
      fd.append('event', event);
      if (ctx) {
        Object.keys(ctx).forEach(function (k) {
          if (ctx[k] != null && ctx[k] !== '') fd.append(k, String(ctx[k]));
        });
      }
      navigator.sendBeacon(URL_, fd);
    } catch (e) { /* never worth interrupting anything */ }
  }

  // Any link into the review flow, wherever it sits. Marked up rather than guessed at from the
  // href, so a link that happens to point at /review/new for another reason is not counted.
  document.addEventListener('click', function (ev) {
    var el = ev.target && ev.target.closest ? ev.target.closest('[data-review-cta]') : null;
    if (!el) return;
    send('review_cta_click', {
      source: el.getAttribute('data-review-cta') || 'other',
      place_id: el.getAttribute('data-place-id') || '',
      destination_id: el.getAttribute('data-destination-id') || ''
    });
  }, true);

  /* The one social step the server cannot see: somebody putting the cursor in a composer and not
     finishing. A posted question is a row in the database; an abandoned one leaves nothing at all,
     and the gap between the two is the number that says whether the box is working.

     Focus, once per element per page. Not keystrokes, not what was typed, not how long they sat
     there. An element opts in by carrying data-track, so this never fires on a form nobody asked
     it to watch. */
  document.addEventListener('focus', function (ev) {
    var el = ev.target;
    if (!el || !el.getAttribute || el.getAttribute('data-track-sent')) return;
    var name = el.getAttribute('data-track');
    if (!name) return;
    el.setAttribute('data-track-sent', '1');
    send(name, {
      source: el.getAttribute('data-track-source') || '',
      destination_id: el.getAttribute('data-destination-id') || ''
    });
  }, true);

  /* A social call to action pressed, by name (data-cta, a closed list the server checks), with
     the page it was pressed on. Which button, on which kind of page, sends people into the trip
     first form is the question; nothing about the person is in it. */
  document.addEventListener('click', function (ev) {
    var el = ev.target && ev.target.closest ? ev.target.closest('[data-cta]') : null;
    if (!el) return;
    send('cta_click', {
      detail: el.getAttribute('data-cta'),
      destination_id: el.getAttribute('data-destination-id') || '',
      path: location.pathname
    });
  }, true);

  /* One bit per visit: a person touched this page. Added 2026-09-25 because the referrer count
     said 368 people arrived from Google in a day on which Search Console recorded zero clicks, so
     "returned a cookie" is not enough to call a session human. A script that loads pages seldom
     taps, scrolls or types. No coordinates, no timing, no target: the first such event sends one
     row and every listener is removed. Once per tab session. */
  (function () {
    var KEY = 'rmt_hi';
    try { if (sessionStorage.getItem(KEY)) return; } catch (e) { /* storage refused: still count */ }
    var kinds = ['pointerdown', 'keydown', 'touchstart', 'scroll'];
    function once() {
      kinds.forEach(function (k) { window.removeEventListener(k, once, true); });
      try { sessionStorage.setItem(KEY, '1'); } catch (e) { /* ignore */ }
      send('human_interaction', { path: location.pathname });
    }
    kinds.forEach(function (k) { window.addEventListener(k, once, { capture: true, passive: true }); });
  })();

  // Exposed so the draft script can report a restore without duplicating the beacon plumbing.
  window.rmtTrack = send;
})();
