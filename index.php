<?php
// This file outputs a Leaflet map with offline tile caching using IndexedDB and a service worker
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Offline Leaflet Map with IndexedDB</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1.0">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        html, body {
            height: 100%;
            width: 100%;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            overflow: hidden;
        }
        #map {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            height: 100%;
            width: 100%;
            z-index: 0;
        }
        #downloadBtn {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1000;
            padding: 10px 20px;
            background: #fff;
            border: 1px solid #888;
            border-radius: 4px;
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        #progressBarContainer {
            position: absolute;
            top: 50px;
            right: 10px;
            z-index: 1001;
            width: 300px;
            background: #eee;
            border: 1px solid #888;
            border-radius: 4px;
            display: none;
        }
        #progressBar { width: 0%; height: 20px; background: #4caf50; border-radius: 4px; }
        #progressText { position: absolute; left: 0; right: 0; text-align: center; top: 0; line-height: 20px; font-size: 12px; }
        /* Add margin to Leaflet controls so they don't overlap with the button */
        .leaflet-top.leaflet-left { margin-top: 60px; margin-left: 10px; }
        @media (max-width: 600px) {
            #progressBarContainer { width: 90vw; left: 5vw; right: 5vw; }
            #downloadBtn { padding: 8px 10px; font-size: 14px; }
        }
    </style>
</head>
<body>
    <button id="downloadBtn" style="color: #fff; background-color: #ff0000;">Download Tiles for Offline</button>
    <div id="progressBarContainer">
        <div id="progressBar"></div>
        <div id="progressText"></div>
    </div>
    <div id="map"></div>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
    // --- IndexedDB Helper ---
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
    // --- Map Setup ---
    const map = L.map('map').setView([14.5995, 120.9842], 13); // Manila
    // Tile URL template
    const tileUrl = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
    // Use default Leaflet tile layer (service worker will intercept requests)
    const tileLayer = L.tileLayer(tileUrl, {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    });
    tileLayer.addTo(map);
    // --- Download Tiles Button ---
    const downloadBtn = document.getElementById('downloadBtn');
    const progressBarContainer = document.getElementById('progressBarContainer');
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    downloadBtn.onclick = async function() {
        console.log('Download button clicked');
        const bounds = map.getBounds();
        const zoom = map.getZoom();
        // Calculate tile x/y range for the current bounds and zoom
        function latLngToTileXY(lat, lng, z) {
            const n = Math.pow(2, z);
            const x = Math.floor((lng + 180) / 360 * n);
            const y = Math.floor((1 - Math.log(Math.tan(lat * Math.PI / 180) + 1 / Math.cos(lat * Math.PI / 180)) / Math.PI) / 2 * n);
            return {x, y};
        }
        const sw = bounds.getSouthWest();
        const ne = bounds.getNorthEast();
        const min = latLngToTileXY(sw.lat, sw.lng, zoom);
        const max = latLngToTileXY(ne.lat, ne.lng, zoom);
        // Clamp and ensure min <= max
        const minX = Math.min(min.x, max.x);
        const maxX = Math.max(min.x, max.x);
        const minY = Math.min(min.y, max.y);
        const maxY = Math.max(min.y, max.y);
        // OSM valid tile range
        const tileCount = Math.pow(2, zoom);
        const clamp = v => Math.max(0, Math.min(tileCount - 1, v));
        const tiles = [];
        for (let x = clamp(minX); x <= clamp(maxX); x++) {
            for (let y = clamp(minY); y <= clamp(maxY); y++) {
                tiles.push({z: zoom, x, y});
            }
        }
        console.log(`Tiles to download: ${tiles.length}`);
        if (tiles.length === 0) {
            alert('No tiles to download!');
            return;
        }
        let downloaded = 0;
        progressBarContainer.style.display = 'block';
        progressBar.style.width = '0%';
        progressText.textContent = `0 / ${tiles.length}`;
        for (const [i, t] of tiles.entries()) {
            const url = tileUrl.replace('{s}', 'a').replace('{z}', t.z).replace('{x}', t.x).replace('{y}', t.y);
            const key = `${t.z}/${t.x}/${t.y}`;
            console.log(`Fetching tile: ${key} from ${url}`);
            try {
                const blob = await saveTileToDB(key, await (await fetch(url)).blob());
                downloaded++;
                console.log(`Saved tile ${key}`);
            } catch (e) {
                console.error(`Error fetching tile ${key}:`, e);
            }
            progressBar.style.width = `${((i+1)/tiles.length)*100}%`;
            progressText.textContent = `${i+1} / ${tiles.length}`;
        }
        progressBarContainer.style.display = 'none';
        alert(`Downloaded ${downloaded} tiles for offline use!`);
    };
    // --- Service Worker Registration ---
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('sw.js').then(function(reg) {
            console.log('Service worker registered.', reg);
        }).catch(function(err) {
            console.error('Service worker registration failed:', err);
        });
    }
    </script>
</body>
</html> 