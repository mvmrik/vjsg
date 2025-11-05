self.addEventListener('install', (event) => {
  // Service worker installed. We don't cache by default for this minimal scaffold.
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});

// Optional: respond to fetch with network-first; here we simply passthrough.
self.addEventListener('fetch', (event) => {
  // For this minimal PWA we don't intercept requests to avoid complexity.
});
