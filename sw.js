// Service Worker for Leaflet Offline Tiles - IndexedDB only, no Cache API
const dbName = 'leaflet-tiles-db';
const storeName = 'tiles';
function openDB() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(dbName, 1);
        req.onupgradeneeded = function(e) {
            const db = e.target.result;
            if (!db.objectStoreNames.contains(storeName)) {
                db.createObjectStore(storeName);
            }
        };
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}
function getTileFromDB(key) {
    return openDB().then(db => {
        return new Promise((resolve, reject) => {
            const tx = db.transaction(storeName, 'readonly');
            const req = tx.objectStore(storeName).get(key);
            req.onsuccess = () => resolve(req.result);
            req.onerror = reject;
        });
    });
}
function saveTileToDB(key, blob) {
    return openDB().then(db => {
        return new Promise((resolve, reject) => {
            const tx = db.transaction(storeName, 'readwrite');
            tx.objectStore(storeName).put(blob, key);
            tx.oncomplete = resolve;
            tx.onerror = reject;
        });
    });
}
self.addEventListener('fetch', function(event) {
    const url = event.request.url;
    // Only intercept OSM tile requests
    if (url.match(/\/\d+\/\d+\/\d+\.png$/)) {
        const match = url.match(/\/(\d+)\/(\d+)\/(\d+)\.png$/);
        if (match) {
            const key = `${match[1]}/${match[2]}/${match[3]}`;
            event.respondWith(
                getTileFromDB(key).then(blob => {
                    if (blob) {
                        console.log('[SW] Tile served from IndexedDB:', key);
                        return new Response(blob, { headers: { 'Content-Type': 'image/png' } });
                    } else {
                        return fetch(event.request).then(resp => {
                            if (resp.ok) {
                                resp.clone().blob().then(blob => {
                                    saveTileToDB(key, blob).then(() => {
                                        console.log('[SW] Tile saved to IndexedDB:', key);
                                    });
                                });
                            }
                            return resp;
                        });
                    }
                })
            );
        }
    }
}); 