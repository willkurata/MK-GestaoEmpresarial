/*
 * Service worker do PWA do aluno.
 * Estratégia: "network first" com cache de reserva, para que a tela de
 * acesso (QR) abra mesmo sem internet na porta da academia.
 */
const CACHE = 'mk-academia-v1';
const SHELL = ['./', './index.html', './manifest.webmanifest', './icon.svg'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(SHELL)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((key) => key !== CACHE)
                        .map((key) => caches.delete(key)),
                ),
            ),
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (
        request.method !== 'GET' ||
        !request.url.startsWith(self.location.origin)
    ) {
        return;
    }

    event.respondWith(
        fetch(request)
            .then((response) => {
                const copy = response.clone();
                void caches
                    .open(CACHE)
                    .then((cache) => cache.put(request, copy));

                return response;
            })
            .catch(() =>
                caches
                    .match(request)
                    .then((cached) => cached || caches.match('./index.html')),
            ),
    );
});

self.addEventListener('push', (event) => {
    const data = event.data ? event.data.json() : {};

    event.waitUntil(
        self.registration.showNotification(data.title || 'MK Academia', {
            body: data.body || '',
            icon: './icon.svg',
            data: { url: data.url || './#/aluno' },
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    event.waitUntil(self.clients.openWindow(event.notification.data.url));
});
