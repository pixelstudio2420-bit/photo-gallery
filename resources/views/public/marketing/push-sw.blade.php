// Service Worker for Web Push
// Served at /push-sw.js with Service-Worker-Allowed: /

self.addEventListener('install', function(e) { self.skipWaiting(); });
self.addEventListener('activate', function(e) { return self.clients.claim(); });

self.addEventListener('push', function(event) {
    var data = {};
    try { data = event.data ? event.data.json() : {}; } catch(e) { data = { title: 'New message', body: event.data ? event.data.text() : '' }; }

    var title = data.title || 'New message';
    var options = {
        body: data.body || '',
        icon: data.icon || '/favicon.ico',
        badge: '/favicon.ico',
        data: {
            click_url: data.click_url || '/',
            cid: data.cid || null,
        },
    };
    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function(event) {
    event.notification.close();
    var data = event.notification.data || {};
    var url = data.click_url || '/';
    if (data.cid) {
        url = '{{ url("/m/pc") }}/' + data.cid + '?to=' + encodeURIComponent(url);
    }
    event.waitUntil(clients.openWindow(url));
});
