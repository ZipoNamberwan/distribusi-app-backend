// A minimal service worker required for the PWA install prompt in Chrome.

self.addEventListener('install', (e) => {
    // Force the waiting service worker to become the active service worker
    self.skipWaiting();
});
