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

  // Exposed so the draft script can report a restore without duplicating the beacon plumbing.
  window.rmtTrack = send;
})();
