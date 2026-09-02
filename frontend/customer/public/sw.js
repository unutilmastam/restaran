/*
 * Offline shell — docs/03-PHASES.md PHASE 3.
 *
 * Strategiya:
 *   app shell (html/js/css) → cache-first, orqa fonda yangilanadi
 *   API so'rovlari          → network-only, HECH QACHON keshlanmaydi
 *
 * ⚠️ API keshlanmasligi MUHIM: eskirgan menyu narxi ko'rsatilsa mijoz
 * noto'g'ri summa ko'radi. Narx har doim serverdan keladi (CLAUDE.md §2.6).
 * Buyurtma holati ham jonli bo'lishi kerak.
 */
const CACHE = 'sr-customer-v1';
const SHELL = ['./', './index.html', './manifest.webmanifest', './icon.svg'];

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

  // Boshqa origin (rasm CDN va h.k.) — brauzerning o'z keshi ishlaydi.
  if (url.origin !== self.location.origin) return;

  // Navigatsiya: tarmoq bo'lmasa app shell qaytadi, shunda SPA
  // "internet yo'q" ekranini o'zi ko'rsata oladi.
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(() => caches.match('./index.html').then((hit) => hit ?? Response.error())),
    );

    return;
  }

  event.respondWith(
    caches.match(request).then((hit) => {
      if (hit) {
        // Stale-while-revalidate: keshdan darhol beramiz, orqa fonda yangilaymiz.
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
