// Service worker for /grafikas phone notifications ("Tavo eilė! Eik į Kortą 4").
self.addEventListener('install', function () { self.skipWaiting(); });
self.addEventListener('activate', function (event) { event.waitUntil(self.clients.claim()); });

self.addEventListener('push', function (event) {
  var data = {};
  try { data = event.data ? event.data.json() : {}; } catch (e) { data = { title: event.data && event.data.text() }; }
  event.waitUntil(self.registration.showNotification(data.title || 'Grafikas', {
    body: data.body || '',
    tag: data.tag || undefined,
    renotify: true,
    requireInteraction: /^turn:/.test(data.tag || ''),
    vibrate: [200, 100, 200, 100, 300],
    icon: '/grafikas-icon/192.png',
    badge: '/grafikas-icon/192.png',
    data: { url: data.url || '/' }
  }));
});

self.addEventListener('notificationclick', function (event) {
  event.notification.close();
  var url = (event.notification.data && event.notification.data.url) || '/';
  event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
    for (var i = 0; i < list.length; i++) {
      if (list[i].url.indexOf(url.split('?')[0]) !== -1 && 'focus' in list[i]) { list[i].navigate(url); return list[i].focus(); }
    }
    return self.clients.openWindow(url);
  }));
});
