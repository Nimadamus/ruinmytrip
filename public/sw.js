/* RuinMyTrip service worker.
 *
 * Deliberately small, and deliberately does NOT cache HTML. Every page here is personalised --
 * your feed, your matches, your notifications, whether the composer is even shown -- and a cached
 * page served to the next person on a shared phone is a privacy bug, not a speed win. What it
 * caches is the shell: stylesheet, script, icons, and an offline page to land on instead of the
 * browser's dinosaur.
 */
const VERSION = 'rmt-v2';
const SHELL = [
  '/offline.html',
  '/assets/css/app.css',
  '/assets/js/app.js',
  '/assets/img/icon-192.png',
];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(VERSION).then((c) => c.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== VERSION).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;

  // Pages: always the network. Offline, land somewhere that explains itself.
  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(() => caches.match('/offline.html')));
    return;
  }

  // Static assets: serve what we have, refresh it in the background.
  if (/^\/assets\//.test(url.pathname)) {
    e.respondWith(
      caches.match(req).then((hit) => {
        const live = fetch(req).then((res) => {
          if (res && res.status === 200) {
            const copy = res.clone();
            caches.open(VERSION).then((c) => c.put(req, copy));
          }
          return res;
        }).catch(() => hit);
        return hit || live;
      })
    );
  }
});

/* Web push: the server sends {title, body, url, tag} (see app/push.php). Show it, and on tap go
   where it points, reusing an open tab when there is one. */
self.addEventListener('push', (e) => {
  let d = {};
  try { d = e.data ? e.data.json() : {}; } catch (_) { d = { body: e.data ? e.data.text() : '' }; }
  e.waitUntil(self.registration.showNotification(d.title || 'RuinMyTrip', {
    body: d.body || '',
    icon: '/assets/img/icon-192.png',
    badge: '/assets/img/icon-192.png',
    tag: d.tag || undefined,
    data: { url: d.url || '/notifications' },
  }));
});

self.addEventListener('notificationclick', (e) => {
  e.notification.close();
  const url = (e.notification.data && e.notification.data.url) || '/notifications';
  e.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
    for (const c of list) {
      if ('focus' in c) { c.navigate(url); return c.focus(); }
    }
    return self.clients.openWindow(url);
  }));
});
