/* Web push on the client.
 *
 * Loaded only for signed-in members when the server has VAPID keys (see views/layout/header.php).
 * Two jobs: keep an existing subscription registered with the server (once a day), and, on the
 * notifications page, offer to turn push on where the browser can do it. Nothing here asks for
 * permission on its own; the ask is a button the member presses.
 */
(function () {
  var meta = document.querySelector('meta[name="vapid-key"]');
  if (!meta || !('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) return;
  var key = meta.getAttribute('content') || '';
  var csrfMeta = document.querySelector('meta[name="csrf-token"]');
  var csrf = csrfMeta ? csrfMeta.getAttribute('content') : '';
  var cta = document.getElementById('push-cta');
  var state = document.getElementById('push-state');

  function say(text) { if (state) { state.hidden = false; state.textContent = text; } }
  function b64ToU8(s) {
    s = s.replace(/-/g, '+').replace(/_/g, '/');
    while (s.length % 4) s += '=';
    var raw = atob(s), out = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
    return out;
  }
  function register(sub) {
    var j = sub.toJSON();
    var body = new URLSearchParams();
    body.set('_csrf', csrf);
    body.set('endpoint', j.endpoint);
    body.set('p256dh', j.keys.p256dh);
    body.set('auth', j.keys.auth);
    return fetch('/push/subscribe', { method: 'POST', credentials: 'same-origin', body: body })
      .then(function (r) { return r.json(); });
  }

  navigator.serviceWorker.ready.then(function (reg) {
    return reg.pushManager.getSubscription().then(function (sub) {
      if (sub) {
        // Already on. Re-register at most daily so a rotated endpoint or a new login keeps working.
        var stamp = 'rmt_push_sync', last = 0;
        try { last = +localStorage.getItem(stamp) || 0; } catch (e) {}
        if (Date.now() - last > 86400000) {
          register(sub).then(function () { try { localStorage.setItem(stamp, String(Date.now())); } catch (e) {} }).catch(function () {});
        }
        say('Push notifications are on for this device.');
        return;
      }
      if (!cta || Notification.permission === 'denied') return;
      cta.hidden = false;
      cta.querySelector('.js-push-on').addEventListener('click', function () {
        Notification.requestPermission().then(function (p) {
          if (p !== 'granted') { cta.hidden = true; say('Notifications are blocked for this site in your browser settings.'); return; }
          return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64ToU8(key) })
            .then(register)
            .then(function (res) {
              cta.hidden = true;
              say(res && res.ok ? 'Done. This device will hear the moment somebody replies.' : 'Could not register this device.');
            });
        }).catch(function () { say('Could not turn on push on this device.'); });
      });
    });
  }).catch(function () {});
})();
