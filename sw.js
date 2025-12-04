const CACHE_NAME = 'absenhima-v1';

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(clients.claim());
});

self.addEventListener('push', (event) => {
    let data = { title: 'Notifikasi', body: 'Ada event baru!', url: '/user/dashboard.php' };
    
    try {
        if (event.data) {
            data = event.data.json();
        }
    } catch (e) {
        console.error('Error parsing push data:', e);
    }

    const options = {
        body: data.body || 'Ada event absen baru yang dimulai!',
        icon: data.icon || '/uploads/settings/logo.png',
        badge: data.badge || '/uploads/settings/logo.png',
        vibrate: [100, 50, 100],
        data: {
            url: data.url || '/user/dashboard.php',
            dateOfArrival: Date.now()
        },
        actions: [
            { action: 'open', title: 'Buka' },
            { action: 'close', title: 'Tutup' }
        ],
        requireInteraction: true,
        tag: 'event-notification'
    };

    event.waitUntil(
        self.registration.showNotification(data.title || 'Event Absen', options)
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    if (event.action === 'close') {
        return;
    }

    const urlToOpen = event.notification.data?.url || '/user/dashboard.php';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then((clientList) => {
                for (const client of clientList) {
                    if (client.url.includes('/user/') && 'focus' in client) {
                        return client.focus();
                    }
                }
                if (clients.openWindow) {
                    return clients.openWindow(urlToOpen);
                }
            })
    );
});
