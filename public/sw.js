// A minimal service worker required for the PWA install prompt in Chrome.

self.addEventListener('install', (e) => {
    // Force the waiting service worker to become the active service worker
    self.skipWaiting();
});

self.addEventListener('fetch', (e) => {
    // Chrome requires a fetch event handler for a valid PWA.
    // We don't need any offline caching strategy here, just a pass-through.
});
