/*
 * Waiter PWA service worker.
 *
 * ⚠️ API HECH QACHON KESHLANMAYDI (customer PWA dagi bilan bir xil
 * qoida). Afitsant uchun bu yanada muhimroq: eskirgan ro'yxat allaqachon
 * yetkazilgan buyurtmani ko'rsatishi yoki boshqa afitsantga o'tgan
 * buyurtmani "meniki" deb ko'rsatishi mumkin edi.
 *
 * Keshlanadigan narsa — faqat app shell (html/js/css/ikonka).
 */
const CACHE = 'sr-waiter-v1';
const SHELL = ['/', '/index.html', '/manifest.webmanifest', '/icon.svg'];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE).then((cache) => cache.addAll(SHELL)).then(() => self.skipWaiting()),
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
      .then(() => self.clients.claim()),
  );
});

self.addEventListener('fetch', (event) => {
  const { request } = event;

  if (request.method !== 'GET') return;

  const url = new URL(request.url);

  // API — hech qachon keshlanmaydi.
  if (url.pathname.includes('/api/')) return;
  if (url.origin !== self.location.origin) return;

  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(() => caches.match('/index.html').then((hit) => hit ?? Response.error())),
    );

    return;
  }

  event.respondWith(
    caches.match(request).then((hit) => {
      if (hit) {
        void fetch(request)
          .then((response) => {
            if (response.ok) void caches.open(CACHE).then((cache) => cache.put(request, response));
          })
          .catch(() => undefined);

        return hit;
      }

      return fetch(request).then((response) => {
        if (response.ok && response.type === 'basic') {
          const copy = response.clone();
          void caches.open(CACHE).then((cache) => cache.put(request, copy));
        }

        return response;
      });
    }),
  );
});

/*
 * PUSH TAYYORLIGI — docs/03-PHASES.md PHASE 7.
 *
 * Backend `push_subscriptions` jadvali bilan tayyor; haqiqiy yuborish
 * PHASE 9/11 da qo'shiladi. Bu yerdagi handler'lar shu paytgacha ham
 * ishlaydi, shunda serverdan kelgan birinchi push YO'QOLMAYDI.
 */
self.addEventListener('push', (event) => {
  if (!event.data) return;

  let payload = {};

  try {
    payload = event.data.json();
  } catch {
    payload = { title: event.data.text() };
  }

  event.waitUntil(
    self.registration.showNotification(payload.title ?? 'Smart Restaurant', {
      body: payload.body ?? '',
      icon: '/icon.svg',
      badge: '/icon.svg',
      // Bir xil buyurtma uchun bir nechta bildirishnoma to'planmasin.
      tag: payload.tag ?? 'order',
      renotify: true,
      data: payload.data ?? {},
    }),
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
      // Ilova ochiq bo'lsa uni old planga chiqaramiz, yangisini ochmaymiz.
      const open = clients.find((client) => client.url.includes(self.location.origin));

      if (open !== undefined) return open.focus();

      return self.clients.openWindow('/');
    }),
  );
});
