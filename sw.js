const CACHE_NAME = 'faturafacil-v2.9';
const urlsToCache = [
    './',
    './index.html',
    './assets/css/style.css?v=20260122',
    './assets/js/main.js?v=20260122',
    'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js?v=20260122',
    './assets/img/logo.png',
    './assets/img/logo_icon.png',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css?v=20260122',
    'https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.0/jquery.min.js?v=20260122',
    'https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js?v=20260122'
];

self.addEventListener('install', (event) => {
    // Force new SW to enter waiting phase immediately
    self.skipWaiting();
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(urlsToCache))
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME) {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    event.respondWith(
        caches.match(event.request)
            .then((response) => response || fetch(event.request))
    );
});

// Push Notification Logic
self.addEventListener('push', function (event) {
    let promiseChain;

    if (event.data) {
        // Payload sent directly (encrypted) - Not implemented in PHP yet
        const data = event.data.json();
        promiseChain = Promise.resolve(data);
    } else {
        // Payload-less: Fetch from server (Tickle-and-Fetch)
        promiseChain = self.registration.pushManager.getSubscription()
            .then(sub => {
                if (!sub) throw new Error('No subscription');
                return fetch('./api/check_msg.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ endpoint: sub.endpoint })
                });
            })
            .then(resp => resp.json())
            .catch(err => {
                // Fallback if fetch fails
                return {
                    title: 'Nova Mensagem',
                    body: 'Toque para visualizar.',
                    url: '/'
                };
            });
    }

    event.waitUntil(
        promiseChain.then(data => {
            const options = {
                body: data.body || 'Nova notificação',
                icon: 'assets/img/logo_icon.png',
                badge: 'assets/img/logo_icon.png',
                vibrate: [100, 50, 100],
                data: {
                    url: data.url || '/'
                }
            };
            return self.registration.showNotification(data.title || 'FaturaFacil', options);
        })
    );
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            // 1. Try to find existing window to focus
            const urlToOpen = new URL(event.notification.data.url, self.location.origin).href;

            for (let i = 0; i < clientList.length; i++) {
                const client = clientList[i];
                if (client.url === urlToOpen && 'focus' in client) {
                    return client.focus();
                }
            }
            // 2. If not found, open new
            if (clients.openWindow) {
                return clients.openWindow(event.notification.data.url);
            }
        })
    );
});
