# OfflineMap (Leaflet + IndexedDB)

This project provides a simple web interface for viewing OpenStreetMap maps with Leaflet, and allows users to download map tiles for offline use. Tiles are stored in the browser's IndexedDB, so the map works even without an internet connection (for downloaded areas).

## Features
- **Leaflet map viewer** with OpenStreetMap tiles
- **Download Tiles for Offline** button: saves visible map tiles to IndexedDB
- **Offline support**: works without internet for downloaded areas
- **Progress bar** for tile downloads
- **Mobile-friendly**: fullscreen map, responsive controls

## How to Run

### 1. Prerequisites
- PHP installed (for local server)
- A modern web browser (Chrome, Edge, Firefox, etc.)

### 2. Start the PHP Server
Open a terminal and run:
```sh
cd /path/to/OfflineMap
php -S localhost:8000
```

### 3. Open the App
Go to [http://localhost:8000/index.php](http://localhost:8000/index.php) in your browser.

## How to Use
1. **Pan/zoom** the map to your area of interest.
2. Click the **Download Tiles for Offline** button (red, top-right).
3. Wait for the progress bar to finish. Tiles for the current view/zoom are saved to your browser.
4. **Go offline** (disable your internet connection).
5. Reload the page and pan/zoom in the downloaded area—the map will still work!

## What to Expect
- **Tiles are stored in your browser's IndexedDB** (`leaflet-tiles-db` > `tiles`).
- **Persistence:** Tiles remain until you clear browser storage or use incognito mode.
- **Only the downloaded area/zoom will work offline.**
- **No server-side storage:** All data is local to your browser.
- **Works on desktop and mobile browsers.**

## Troubleshooting
- If you don't see tiles in IndexedDB, make sure you:
  - Allowed the service worker to register (check DevTools > Application > Service Workers)
  - Are not in incognito/private mode
  - Cleared old cache/service workers if you changed code
- If you see CORS or network errors, try a different network or check if OpenStreetMap is accessible.
- If the map doesn't fill the screen, refresh or try a different browser.

## Customization
- You can change the map's default location or zoom in `index.php`.
- You can style the download button by editing its CSS or inline style.

## Limitations
- Only the visible area and current zoom level are downloaded.
- Downloading large areas or many zoom levels may use significant browser storage.
- This is a demo/prototype; for production, consider more robust offline strategies.

---

**Enjoy your offline map!** 