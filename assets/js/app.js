// SmartRide shared scripts: menus, fetch helper, QR drawing, maps,
// plus the browser APIs: Geolocation (GPS) and Notifications.
(function () {
  const meta = (n) => document.querySelector(`meta[name="${n}"]`)?.content || '';

  // Mobile menus
  document.addEventListener('click', (e) => {
    if (e.target.closest('[data-open-nav]')) document.body.classList.add('nav-open');
    if (e.target.closest('[data-close-nav]')) document.body.classList.remove('nav-open');
    const t = e.target.closest('[data-toggle-nav]');
    if (t) {
      const nav = document.getElementById('topnav');
      nav.classList.toggle('open');
      t.setAttribute('aria-expanded', nav.classList.contains('open'));
    }
  });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') document.body.classList.remove('nav-open'); });

  // Ask before destructive form posts (data-confirm="Are you sure?")
  document.addEventListener('submit', (e) => {
    const msg = e.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) e.preventDefault();
  });

  const SMARTRIDE = {
    csrf: meta('csrf'),
    base: meta('base'),

    async get(url) {
      const r = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      return r.json();
    },

    async post(url, body) {
      const r = await fetch(url, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF': SMARTRIDE.csrf },
        body: JSON.stringify(body || {}),
      });
      return r.json();
    },

    /** Draws a QR into el as crisp SVG. Needs vendor/qrcode.js on the page. */
    drawQR(el, text) {
      if (!el) return;
      if (!text || typeof qrcode === 'undefined') { el.innerHTML = ''; return; }
      const qr = qrcode(0, 'M');
      qr.addData(text);
      qr.make();
      el.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 0, scalable: true });
      const svg = el.querySelector('svg');
      svg.setAttribute('role', 'img');
      svg.setAttribute('aria-label', 'Your ride QR code');
      svg.querySelectorAll('path, rect').forEach((p) => { if (p.getAttribute('fill') === 'black') p.setAttribute('fill', '#17154F'); });
    },

    /**
     * Keeps a ride QR fresh. Fetches a new code from the server whenever the
     * 30-second window ends, and counts down in the [data-qr-countdown] element.
     */
    liveQR(opts) {
      let left = 0;
      const show = () => document.querySelectorAll('[data-qr-countdown]').forEach((n) => { n.textContent = `Refreshes in 0:${String(left).padStart(2, '0')}`; });
      const tick = () => {
        if (left > 0 && left < 999) { left -= 1; if (left > 0) show(); }
        if (left <= 0) refresh();
      };
      const refresh = async () => {
        left = 999; // block double refresh while waiting
        try {
          const d = await SMARTRIDE.get(opts.url);
          document.querySelectorAll(opts.target).forEach((el) => {
            el.closest('.qr-frame')?.classList.toggle('frozen', !d.payload);
            SMARTRIDE.drawQR(el, d.payload || 'FROZEN');
          });
          document.querySelectorAll('[data-qr-text]').forEach((n) => { n.textContent = d.payload || ''; });
          left = d.payload ? d.expires_in : 3600;
          if (d.payload) show();
          if (!d.payload) document.querySelectorAll('[data-qr-countdown]').forEach((n) => { n.textContent = 'Frozen'; });
        } catch (err) {
          left = 5; // try again shortly
        }
      };
      refresh();
      setInterval(tick, 1000);
    },

    /**
     * GEOLOCATION API (driver side). While a trip is running, the driver's phone sends
     * its location every 20 seconds. Any [data-gps-state] label on the page shows
     * whether it's working.
     */
    gpsBeacon(url) {
      const setState = (state, text) => document.querySelectorAll('[data-gps-state]').forEach((n) => {
        n.dataset.state = state;
        n.lastChild.textContent = text;
      });
      if (!('geolocation' in navigator)) { setState('off', 'No GPS on this device'); return; }
      let last = 0;
      navigator.geolocation.watchPosition((p) => {
        if (Date.now() - last < 20000) return;
        last = Date.now();
        SMARTRIDE.post(url, { lat: p.coords.latitude, lng: p.coords.longitude })
          .then(() => setState('on', 'GPS on · sent ' + new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })))
          .catch(() => {});
      }, (err) => {
        setState('off', err.code === 1 ? 'GPS blocked. Allow location for this site' : 'GPS unavailable');
      }, { enableHighAccuracy: true, maximumAge: 15000 });
    },

    /** Straight-line distance in km between two points (haversine formula). */
    distanceKm(lat1, lng1, lat2, lng2) {
      const rad = (d) => d * Math.PI / 180;
      const a = Math.sin(rad(lat2 - lat1) / 2) ** 2
        + Math.cos(rad(lat1)) * Math.cos(rad(lat2)) * Math.sin(rad(lng2 - lng1) / 2) ** 2;
      return 6371 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    },

    /**
     * GEOLOCATION API (passenger side). Asks the browser where the passenger is.
     * Calls onFound({lat, lng, accuracy}) or onError(message).
     */
    // ---------- Jeepney lines map (Track a Jeepney, admin Jeepney Tracking) ----------
    // At first every line is drawn in its own color. Pick a line and only that line, its
    // landmarks and its jeepneys stay on the map; pick another and the first one goes away.
    // The passenger's own location is the blue dot, and lines are sorted by how close they are.
    //   opts: { map, panel, routesUrl, fleetUrl, geoUrl, admin }
    lineMap(opts) {
      const el = document.getElementById(opts.map);
      const panel = document.getElementById(opts.panel);
      if (!el || !panel) return null;
      if (typeof L === 'undefined') { el.innerHTML = '<div class="map-note">The map library did not load.</div>'; return null; }
      el.innerHTML = '';
      const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
      const map = L.map(el, { zoomControl: true }).setView([10.665, 122.965], 13);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19, attribution: '&copy; OpenStreetMap contributors · Routes: OSM (ODbL) via DASIG, Bacolod LPTRP',
      }).addTo(map);
      const busSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6v6"/><path d="M15 6v6"/><path d="M2 12h19.6"/><path d="M18 18h3s.5-1.7.8-2.8c.1-.4.2-.8.2-1.2 0-.4-.1-.8-.2-1.2l-1.4-5C20.1 6.8 19.1 6 18 6H4a2 2 0 0 0-2 2v10h3"/><circle cx="7" cy="18" r="2"/><path d="M9 18h5"/><circle cx="16" cy="18" r="2"/></svg>';
      const SOURCE = { osm: 'Mapped street by street (OpenStreetMap)', lptrp: 'Traced from the LPTRP landmarks', geoapify: 'Road line from Geoapify', straight: 'Straight lines between landmarks' };
      const STATE = { on_trip: 'On the road', parked: 'Parked', breakdown: 'Breakdown', offline: 'Offline' };

      let routes = [];
      let units = [];
      let picked = null;     // the line on the map, or null = all lines
      let me = null;         // { lat, lng, accuracy }
      let query = '';
      const lineLayers = {}; // route id -> polyline
      const unitMarkers = {};
      let pickedLayer = null; // landmarks + casing of the picked line
      let walkLayer = null;
      let board = null;      // where to board the picked line (from api_geo.php?action=line)
      let boardFor = '';
      let youMarker = null;
      let youCircle = null;
      let fitted = false;

      // ---- distances (meters), flat-earth approximation is fine inside a city
      const toXY = (lat, lng, lat0) => [lng * 111320 * Math.cos(lat0 * Math.PI / 180), lat * 110540];
      const distToLine = (pts, lat, lng) => {
        let best = Infinity;
        const [px, py] = toXY(lat, lng, lat);
        for (let i = 1; i < pts.length; i++) {
          const [ax, ay] = toXY(pts[i - 1][0], pts[i - 1][1], lat);
          const [bx, by] = toXY(pts[i][0], pts[i][1], lat);
          const dx = bx - ax; const dy = by - ay; const len = dx * dx + dy * dy;
          const t = len ? Math.max(0, Math.min(1, ((px - ax) * dx + (py - ay) * dy) / len)) : 0;
          const d = Math.hypot(px - ax - t * dx, py - ay - t * dy);
          if (d < best) best = d;
        }
        return best;
      };
      const fmtDist = (m) => (m >= 1000 ? (m / 1000).toFixed(1) + ' km' : Math.round(m / 10) * 10 + ' m');
      const unitsOn = (id) => units.filter((u) => u.route_id === id);
      const running = (id) => unitsOn(id).filter((u) => u.state === 'on_trip').length;
      const lineNo = (r) => `<span class="line-no" style="background:${esc(r.color)}">${r.id}</span>`;

      // ---- map layers
      const drawAllLines = () => {
        routes.forEach((r) => {
          if (r.points.length < 2) return;
          const line = L.polyline(r.points, { color: r.color, weight: 4, opacity: 0.85 });
          line.bindTooltip(`<b>${r.id}</b> · ${esc(r.short)}`, { sticky: true });
          line.on('click', () => pick(r.id));
          lineLayers[r.id] = line;
        });
      };
      const showLines = () => {
        routes.forEach((r) => {
          const line = lineLayers[r.id];
          if (!line) return;
          const show = picked === null || picked === r.id;
          if (show && !map.hasLayer(line)) line.addTo(map);
          if (!show && map.hasLayer(line)) map.removeLayer(line);
          line.setStyle(picked === r.id ? { weight: 6, opacity: 1 } : { weight: 4, opacity: 0.85 });
        });
        if (pickedLayer) { map.removeLayer(pickedLayer); pickedLayer = null; }
        if (picked === null) return;
        const r = routes.find((x) => x.id === picked);
        pickedLayer = L.layerGroup();
        L.polyline(r.points, { color: '#fff', weight: 11, opacity: 0.9, interactive: false }).addTo(pickedLayer);
        r.stops.forEach((st, k) => {
          const end = k === 0 ? 'A' : (k === r.stops.length - 1 ? 'B' : '');
          const tip = `<b>${esc(st.name)}</b><br><small>Line ${r.id} · ${esc(r.short)}</small>`;
          if (end) {
            const icon = L.divIcon({ className: '', iconSize: [24, 24], iconAnchor: [12, 12], html: `<div class="stop-end" style="background:${esc(r.color)}">${end}</div>` });
            L.marker([st.lat, st.lng], { icon, title: st.name, zIndexOffset: 500 }).addTo(pickedLayer).bindTooltip(tip);
          } else {
            L.circleMarker([st.lat, st.lng], { radius: 6, color: r.color, weight: 3, fillColor: '#fff', fillOpacity: 1 }).addTo(pickedLayer).bindTooltip(tip);
          }
        });
        pickedLayer.addTo(map);
        lineLayers[r.id].bringToFront();
      };
      const drawUnits = () => {
        const seen = {};
        units.forEach((u) => {
          if (picked === null || u.route_id !== picked || u.latitude == null) return;
          seen[u.unit_code] = true;
          const live = u.state === 'on_trip';
          const full = live && u.seats_left === 0;
          const icon = L.divIcon({ className: '', iconSize: [32, 32], iconAnchor: [16, 16],
            html: `<div class="unit-marker${full ? ' full' : ''}" style="background:${live ? esc(u.color) : '#9A9CB4'}">${busSvg}</div>` });
          const far = me ? `<br>${fmtDist(SMARTRIDE.distanceKm(me.lat, me.lng, u.latitude, u.longitude) * 1000)} from you` : '';
          const seats = live ? `<br>${u.onboard}/${u.capacity} on board · ${u.seats_left ? u.seats_left + ' seats left' : 'Full'}` : `<br>${STATE[u.state] || u.state}`;
          const extra = opts.admin && !u.simulated ? `<br><small>${esc(u.plate_no || '')}${u.driver_name ? ' · ' + esc(u.driver_name) : ''}</small>` : '';
          const html = `<b>${esc(u.unit_code)}</b> · Line ${u.route_id}${seats}${far}${extra}<br><small>Last GPS ${esc(u.seen)}</small>`;
          if (unitMarkers[u.unit_code]) unitMarkers[u.unit_code].setLatLng([u.latitude, u.longitude]).setIcon(icon).setPopupContent(html);
          else unitMarkers[u.unit_code] = L.marker([u.latitude, u.longitude], { icon, title: u.unit_code, zIndexOffset: 800 }).addTo(map).bindPopup(html);
        });
        Object.keys(unitMarkers).forEach((code) => {
          if (!seen[code]) { map.removeLayer(unitMarkers[code]); delete unitMarkers[code]; }
        });
      };

      // ---- panel
      const renderList = () => {
        const q = query.trim().toLowerCase();
        let list = routes.filter((r) => !q || (r.id + ' ' + r.name + ' ' + r.stops.map((s) => s.name).join(' ')).toLowerCase().includes(q));
        if (me) list = list.slice().sort((a, b) => a.near - b.near);
        const rows = list.map((r) => {
          const n = running(r.id);
          const bits = [`${n} jeepney${n === 1 ? '' : 's'} on the road`];
          if (me) bits.push(`${fmtDist(r.near)} from you`);
          return `<button type="button" class="line-row" data-line="${r.id}">${lineNo(r)}
            <span class="line-text"><b>${esc(r.short)}</b><small>${bits.join(' · ')}</small></span>
            <span class="line-go" aria-hidden="true">›</span></button>`;
        }).join('');
        panel.innerHTML = `
          <div class="lines-head"><h3>Jeepney lines</h3><span class="pill pill-brand">${routes.length} lines</span></div>
          <p class="hint" style="margin:-4px 0 10px">Bacolod's Local Public Transport Route Plan. Tap a line to see only that line and its jeepneys.${me ? ' Closest lines first.' : ''}</p>
          <label class="sr-only" for="lineSearch">Search lines</label>
          <input class="input line-search" id="lineSearch" type="search" placeholder="Search a line or a place, e.g. Libertad" value="${esc(query)}" autocomplete="off">
          ${locNote ? `<p class="hint loc-note">${esc(locNote)}</p>` : ''}
          <div class="line-list">${rows || '<p class="empty">No line matches that.</p>'}</div>`;
        const box = panel.querySelector('#lineSearch');
        box.addEventListener('input', () => { query = box.value; const pos = box.selectionStart; renderList(); const nb = panel.querySelector('#lineSearch'); nb.focus(); nb.setSelectionRange(pos, pos); });
      };
      const renderDetail = () => {
        const r = routes.find((x) => x.id === picked);
        const mine = unitsOn(r.id).map((u) => ({ ...u, m: me && u.latitude != null ? SMARTRIDE.distanceKm(me.lat, me.lng, u.latitude, u.longitude) * 1000 : null }));
        mine.sort((a, b) => (a.state === 'on_trip' ? 0 : 1) - (b.state === 'on_trip' ? 0 : 1) || (a.m ?? 1e9) - (b.m ?? 1e9) || a.unit_code.localeCompare(b.unit_code));
        const unitRows = mine.map((u) => {
          const live = u.state === 'on_trip';
          const right = live
            ? `<span class="pill ${u.seats_left ? 'pill-green' : 'pill-amber'}">${u.seats_left ? u.seats_left + ' seats' : 'Full'}</span>`
            : `<span class="pill pill-gray">${STATE[u.state] || u.state}</span>`;
          const bits = [];
          if (live) bits.push(`${u.onboard}/${u.capacity} on board`);
          if (u.m != null) bits.push(`${fmtDist(u.m)} from you`);
          return `<button type="button" class="list-row unit-row" data-unit="${esc(u.unit_code)}">
            <span class="dot" style="background:${live ? esc(r.color) : '#9A9CB4'}"></span>
            <span class="who" style="flex:1;text-align:left"><b>${esc(u.unit_code)}</b><span>${bits.join(' · ') || 'No position yet'}</span></span>${right}</button>`;
        }).join('');
        let boardHtml = '';
        if (me && board && board.ok && board.board.meters < 40) {
          boardHtml = `<div class="board-box"><span class="muted small">Where to get on</span>
            <b>You're on this line's road</b><span>Wait by the roadside, near ${esc(board.landmark)}.</span></div>`;
        } else if (me && board && board.ok) {
          boardHtml = `<div class="board-box"><span class="muted small">Where to get on</span>
            <b>Walk ${fmtDist(board.meters)} · about ${board.minutes} min</b>
            <span>to the line near ${esc(board.landmark)}</span></div>`;
        } else if (!me) {
          boardHtml = `<p class="hint">${esc(locNote || 'Share your location to see how far you are from this line and where to get on.')}</p>`;
        }
        if (me && locNote) boardHtml += `<p class="hint loc-note">${esc(locNote)}</p>`;
        panel.innerHTML = `
          <button type="button" class="back-link" data-back>‹ All lines</button>
          <div class="line-title">${lineNo(r)}<div><b>${esc(r.name)}</b>
            <small>${esc(r.fare)} · ${r.km} km · about ${r.minutes} min end to end</small>
            <small class="${r.source === 'straight' ? 'amber' : 'muted'}">${SOURCE[r.source] || ''}</small></div></div>
          ${boardHtml}
          <h4>Jeepneys on this line (${mine.length})</h4>
          <div>${unitRows || '<p class="empty">No jeepney on this line right now.</p>'}</div>
          <h4>Landmarks</h4>
          <ol class="landmarks">${r.stops.map((s, k) => `<li><span class="lm-dot" style="border-color:${esc(r.color)}">${k === 0 ? 'A' : (k === r.stops.length - 1 ? 'B' : '')}</span>${esc(s.name)}</li>`).join('')}</ol>`;
      };
      const render = () => (picked === null ? renderList() : renderDetail());

      panel.addEventListener('click', (e) => {
        const line = e.target.closest('[data-line]');
        if (line) { pick(Number(line.dataset.line)); return; }
        if (e.target.closest('[data-back]')) { pick(null); return; }
        const unit = e.target.closest('[data-unit]');
        if (unit && unitMarkers[unit.dataset.unit]) {
          map.setView(unitMarkers[unit.dataset.unit].getLatLng(), Math.max(map.getZoom(), 15));
          unitMarkers[unit.dataset.unit].openPopup();
          if (window.innerWidth < 1180) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      });

      // ---- where to board (GEOAPIFY walking route to the closest point of the picked line)
      const loadBoard = async () => {
        if (walkLayer) { map.removeLayer(walkLayer); walkLayer = null; }
        board = null;
        if (!me || picked === null || !opts.geoUrl) return;
        const key = `${picked}:${me.lat.toFixed(4)},${me.lng.toFixed(4)}`;
        boardFor = key;
        try {
          const d = await SMARTRIDE.get(`${opts.geoUrl}?action=line&route=${picked}&lat=${me.lat}&lng=${me.lng}`);
          if (boardFor !== key || !d.ok) return;
          board = d;
          walkLayer = L.layerGroup();
          L.polyline(d.points, { color: '#1A73E8', weight: 4, dashArray: '6 8' }).addTo(walkLayer);
          const icon = L.divIcon({ className: '', iconSize: [16, 16], iconAnchor: [8, 8], html: '<div class="board-marker"></div>' });
          L.marker([d.board.lat, d.board.lng], { icon, title: 'Get on here', zIndexOffset: 900 }).addTo(walkLayer).bindTooltip('Get on here');
          walkLayer.addTo(map);
          render();
        } catch (e) { /* the page works without it */ }
      };

      // ---- pick a line (null = all lines)
      const pick = (id) => {
        picked = id;
        if (walkLayer) { map.removeLayer(walkLayer); walkLayer = null; }
        board = null;
        showLines();
        drawUnits();
        render();
        try { history.replaceState(null, '', id === null ? location.pathname + location.search : '#line-' + id); } catch (e) { /* ignore */ }
        if (id === null) {
          const all = routes.filter((r) => r.points.length > 1).flatMap((r) => r.points);
          if (all.length) map.fitBounds(all, { padding: [20, 20] });
        } else {
          const r = routes.find((x) => x.id === id);
          if (r.points.length > 1) map.fitBounds(r.points, { padding: [30, 30] });
          loadBoard();
        }
      };

      // ---- the user's own location (blue dot)
      // Only the device's own position is used for the dot: GPS on a phone, Wi-Fi / GPS on a
      // laptop. Readings keep coming in (watchPosition) and a rough reading never replaces a
      // sharper recent one. The internet-connection guess is never shown as "you".
      let locNote = '';      // shown under the line list when the reading is rough or blocked
      const placeMe = (pos, center) => {
        const moved = !me || SMARTRIDE.distanceKm(me.lat, me.lng, pos.lat, pos.lng) > 0.05;
        me = pos;
        routes.forEach((r) => { r.near = r.points.length > 1 ? distToLine(r.points, me.lat, me.lng) : Infinity; });
        const ic = L.divIcon({ className: '', iconSize: [18, 18], iconAnchor: [9, 9], html: '<div class="you-marker"></div>' });
        const label = `<b>You are here</b><br><small>Accurate to about ${fmtDist(pos.accuracy)}</small>`;
        if (youMarker) youMarker.setLatLng([pos.lat, pos.lng]).setPopupContent(label);
        else youMarker = L.marker([pos.lat, pos.lng], { icon: ic, title: 'You are here', zIndexOffset: 1000 }).addTo(map).bindPopup(label);
        if (youCircle) youCircle.setLatLng([pos.lat, pos.lng]).setRadius(pos.accuracy);
        else youCircle = L.circle([pos.lat, pos.lng], { radius: pos.accuracy, color: '#1A73E8', weight: 1, fillOpacity: 0.08, interactive: false }).addTo(map);
        locNote = pos.accuracy > 300
          ? `Your device only knows your rough spot (within ${fmtDist(pos.accuracy)}). Turn on Location / GPS, or open SmartRide on your phone for your exact spot.`
          : '';
        if (center) map.setView([pos.lat, pos.lng], Math.max(map.getZoom(), pos.accuracy > 300 ? 14 : 16));
        if (moved && picked !== null) loadBoard();
        drawUnits();
        render();
      };
      let watching = false;
      let lastFix = 0;
      const onFix = (p, center) => {
        const pos = { lat: p.coords.latitude, lng: p.coords.longitude, accuracy: Math.max(1, p.coords.accuracy || 0) };
        // Keep a sharper reading from the last 30 s over a much rougher new one.
        if (me && pos.accuracy > me.accuracy * 2 && Date.now() - lastFix < 30000) return;
        lastFix = Date.now();
        placeMe(pos, center);
      };
      const findMe = (center) => {
        if (me && center) map.setView([me.lat, me.lng], Math.max(map.getZoom(), me.accuracy > 300 ? 14 : 16));
        if (watching) return;
        if (!('geolocation' in navigator)) { locNote = "This browser can't share your location."; render(); return; }
        if (!window.isSecureContext) { locNote = 'Your location only works on https:// (or localhost). Open SmartRide from its https address.'; render(); return; }
        watching = true;
        let first = !me;
        navigator.geolocation.watchPosition(
          (p) => { onFix(p, center || first); first = false; },
          (err) => {
            if (err.code === 1) {             // blocked by the user / browser settings
              watching = false;
              locNote = 'Location is blocked. Allow it for this site (the lock icon in the address bar), then tap the target button on the map.';
              render();
            }
            // Timeouts: the watch keeps going and the next reading will arrive.
          },
          { enableHighAccuracy: true, maximumAge: 0, timeout: 30000 },
        );
      };
      const LocateControl = L.Control.extend({
        options: { position: 'topleft' },
        onAdd() {
          const b = L.DomUtil.create('button', 'map-btn');
          b.type = 'button'; b.title = 'Show my location'; b.setAttribute('aria-label', 'Show my location');
          b.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/><circle cx="12" cy="12" r="8"/></svg>';
          L.DomEvent.on(b, 'click', (e) => { L.DomEvent.stop(e); findMe(true); });
          return b;
        },
      });
      new LocateControl().addTo(map);

      // ---- data
      const loadFleet = async () => {
        try { const d = await SMARTRIDE.get(opts.fleetUrl); units = d.units; } catch (e) { return; }
        drawUnits();
        // Don't redraw the list while someone is typing in the search box.
        if (picked !== null || document.activeElement?.id !== 'lineSearch') render();
      };
      (async () => {
        try { const d = await SMARTRIDE.get(opts.routesUrl); routes = d.routes; } catch (e) {
          panel.innerHTML = '<p class="empty">The jeepney lines did not load. Refresh the page.</p>'; return;
        }
        routes.forEach((r) => { r.near = Infinity; });
        drawAllLines();
        await loadFleet();
        const m = location.hash.match(/^#line-(\d+)$/);
        const first = m && routes.some((r) => r.id === Number(m[1])) ? Number(m[1]) : null;
        pick(first);
        fitted = true;
        if (opts.locate !== false) findMe(false);
        setInterval(loadFleet, 5000);
        // Back/forward buttons and links like #line-5 switch lines too.
        window.addEventListener('hashchange', () => {
          const h = location.hash.match(/^#line-(\d+)$/);
          const id = h && routes.some((r) => r.id === Number(h[1])) ? Number(h[1]) : null;
          if (id !== picked) pick(id);
        });
      })();
      return { map, pick, findMe, isReady: () => fitted };
    },
    locate(onFound, onError) {
      if (!('geolocation' in navigator)) { onError('This browser has no location support.'); return; }
      navigator.geolocation.getCurrentPosition(
        (p) => onFound({ lat: p.coords.latitude, lng: p.coords.longitude, accuracy: p.coords.accuracy }),
        (err) => onError(err.code === 1
          ? 'Location is blocked. Allow it in your browser settings for this site, then try again.'
          : "We couldn't get your location. Try again outside or near a window."),
        { enableHighAccuracy: true, timeout: 12000, maximumAge: 30000 },
      );
    },

    /**
     * NOTIFICATIONS. The server saves every alert and pushes it through OneSignal.
     * This keeps the bell in the sidebar up to date (every 10 seconds). If push isn't
     * on for this browser, new alerts also show as a small card on the page.
     */
    push: null, // the OneSignal SDK, once it has loaded (only when an App ID is set)

    usePush(OneSignal) {
      SMARTRIDE.push = OneSignal;
      const banner = document.querySelector('[data-alerts-banner]');
      if (banner && !OneSignal.User.PushSubscription.optedIn && (!('Notification' in window) || Notification.permission !== 'denied')) banner.hidden = false;
    },

    pushIsOn() {
      return !!(SMARTRIDE.push && SMARTRIDE.push.User.PushSubscription.optedIn);
    },

    notifications(url) {
      const supported = 'Notification' in window;
      const badges = () => document.querySelectorAll('[data-notif-count]');
      const banner = document.querySelector('[data-alerts-banner]');
      if (banner && supported && Notification.permission === 'default') banner.hidden = false;

      document.addEventListener('click', async (e) => {
        if (!e.target.closest('[data-enable-alerts]')) return;
        if (banner) banner.hidden = true;
        if (SMARTRIDE.push) {
          // OneSignal shows the browser's "Allow notifications?" and subscribes this device.
          await SMARTRIDE.push.User.PushSubscription.optIn();
          if (SMARTRIDE.pushIsOn()) SMARTRIDE.toast({ title: 'Alerts are on', body: "You'll get a push for fares, reloads and low balance." });
          else SMARTRIDE.toast({ title: 'Alerts are off', body: 'You can still see everything on the Notifications page.' });
          return;
        }
        // Demo mode (no OneSignal App ID): use the browser's own notifications instead.
        if (!supported) { SMARTRIDE.toast({ title: 'Not supported', body: "This browser can't show notifications." }); return; }
        const answer = await Notification.requestPermission();
        if (answer === 'granted') SMARTRIDE.popup({ title: 'Alerts are on', body: "You'll see alerts here while SmartRide is open.", link: '' });
        else SMARTRIDE.toast({ title: 'Alerts are off', body: 'You can still see everything on the Notifications page.' });
      });

      let after = null;
      const check = async () => {
        let d;
        try { d = await SMARTRIDE.get(after === null ? url : `${url}?after=${after}`); } catch (e) { return; }
        badges().forEach((b) => { b.textContent = d.unread; b.hidden = !d.unread; });
        if (after !== null) {
          d.items.forEach((n) => {
            // "Arriving at your stop" / "Stop at ...": vibrate the phone and always show it here.
            if (SMARTRIDE.URGENT.includes(n.type)) { SMARTRIDE.buzz(); SMARTRIDE.toast(n); return; }
            // The rest: OneSignal already pushed them. Only show them here if push is off.
            if (!SMARTRIDE.pushIsOn()) SMARTRIDE.popup(n);
          });
        }
        after = d.latest;
      };
      check();
      setInterval(check, 10000);
      document.addEventListener('click', (e) => {
        if (!e.target.closest('[data-buzz-test]')) return;
        if (!SMARTRIDE.buzz()) SMARTRIDE.toast({ title: "This phone can't vibrate from the browser", body: "iPhones don't allow it. You'll still get the alert on screen and as a push." });
      });
    },

    // Alerts that make the phone vibrate.
    URGENT: ['arriving', 'stop_ahead'],

    // Vibrates the phone: long, short, long. Android browsers only (iPhones don't allow it).
    // The browser only lets a page vibrate after the person has tapped it once.
    // Returns false when this browser can't vibrate.
    buzz() {
      if (!('vibrate' in navigator)) return false;
      try { return navigator.vibrate([400, 150, 400, 150, 800]); } catch (e) { return false; }
    },

    /** One alert: a real system notification if allowed, otherwise an in-page card. */
    popup(n) {
      if ('Notification' in window && Notification.permission === 'granted') {
        try {
          const note = new Notification(n.title, { body: n.body, icon: '../assets/img/logo.png', tag: 'smartride-' + (n.notif_id || Date.now()) });
          note.onclick = () => { window.focus(); if (n.link) location.href = n.link; note.close(); };
          return;
        } catch (e) { /* some phones only allow notifications from a service worker */ }
      }
      SMARTRIDE.toast(n);
    },

    toast(n) {
      let box = document.querySelector('.toasts');
      if (!box) { box = document.createElement('div'); box.className = 'toasts'; box.setAttribute('aria-live', 'polite'); document.body.appendChild(box); }
      const t = document.createElement('div');
      t.className = 'toast';
      const b = document.createElement('b'); b.textContent = n.title;
      const s = document.createElement('span'); s.textContent = n.body || '';
      const wrap = document.createElement('div'); wrap.append(b, s);
      t.innerHTML = '<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.268 21a2 2 0 0 0 3.464 0"/><path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326"/></svg>';
      t.appendChild(wrap);
      t.addEventListener('click', () => { if (n.link) location.href = n.link; else t.remove(); });
      box.appendChild(t);
      setTimeout(() => t.remove(), 7000);
    },

    /** Leaflet map of the fleet. Shared by the passenger Track page and admin Tracking page. */
    fleetMap(elId, dataUrl, onData) {
      const el = document.getElementById(elId);
      if (!el) return;
      if (typeof L === 'undefined') {
        el.innerHTML = '<div class="map-note">The map library did not load.</div>';
        return;
      }
      el.innerHTML = '';
      const map = L.map(el, { scrollWheelZoom: false }).setView([10.672, 122.958], 13);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18, attribution: '&copy; OpenStreetMap contributors',
      }).addTo(map);
      const colors = { on_trip: '#12A150', parked: '#5B52EE', breakdown: '#E0343F', offline: '#9A9CB4' };
      const busSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6v6"/><path d="M15 6v6"/><path d="M2 12h19.6"/><path d="M18 18h3s.5-1.7.8-2.8c.1-.4.2-.8.2-1.2 0-.4-.1-.8-.2-1.2l-1.4-5C20.1 6.8 19.1 6 18 6H4a2 2 0 0 0-2 2v10h3"/><circle cx="7" cy="18" r="2"/><path d="M9 18h5"/><circle cx="16" cy="18" r="2"/></svg>';
      const markers = {};
      let fitted = false;
      const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

      const load = async () => {
        let d;
        try { d = await SMARTRIDE.get(dataUrl); } catch (e) { return; }
        const pts = [];
        d.units.forEach((u) => {
          if (u.latitude == null) return;
          const icon = L.divIcon({ className: '', iconSize: [30, 30], iconAnchor: [15, 15],
            html: `<div class="unit-marker" style="background:${colors[u.state] || '#5B52EE'}">${busSvg}</div>` });
          const seats = u.state === 'on_trip' ? `<br>${u.onboard}/${u.capacity} on board · ${u.seats_left} seats left` : '';
          const html = `<b>${esc(u.unit_code)}</b><br>${esc(u.route_name || u.state.replace('_', ' '))}${seats}<br><small>Last seen ${esc(u.seen)}</small>`;
          if (markers[u.unit_code]) markers[u.unit_code].setLatLng([u.latitude, u.longitude]).setIcon(icon).setPopupContent(html);
          else markers[u.unit_code] = L.marker([u.latitude, u.longitude], { icon, title: u.unit_code }).addTo(map).bindPopup(html);
          pts.push([u.latitude, u.longitude]);
        });
        if (!fitted && pts.length) { map.fitBounds(pts, { padding: [40, 40], maxZoom: 14 }); fitted = true; }
        if (onData) onData(d.units);
      };
      load();
      setInterval(load, 15000);
      return map;
    },

    /**
     * GEOAPIFY routes: draws each route's bus road line (from Geoapify routing) and its
     * landmarks, A at the start and B at the end. onData gets the routes for a list.
     */
    async drawRoutes(map, url, onData) {
      if (!map || typeof L === 'undefined') return;
      let d;
      try { d = await SMARTRIDE.get(url); } catch (e) { return; }
      const colors = ['#2A20D8', '#E8590C', '#0CA678', '#C2255C', '#1971C2', '#7048E8'];
      const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
      d.routes.forEach((r, i) => {
        const color = colors[i % colors.length];
        r.color = color;
        if (r.points.length > 1) {
          L.polyline(r.points, { color, weight: 4, opacity: 0.55 }).addTo(map).bindTooltip(esc(r.name), { sticky: true });
        }
        r.stops.forEach((s, k) => {
          const end = k === 0 ? 'A' : (k === r.stops.length - 1 ? 'B' : '');
          const tip = `${esc(s.name)}<br><small>${esc(r.name)}</small>`;
          if (end) {
            const icon = L.divIcon({ className: '', iconSize: [22, 22], iconAnchor: [11, 11], html: `<div class="stop-end" style="background:${color}">${end}</div>` });
            L.marker([s.lat, s.lng], { icon, title: s.name }).addTo(map).bindTooltip(tip);
          } else {
            L.circleMarker([s.lat, s.lng], { radius: 5, color, weight: 2, fillColor: '#fff', fillOpacity: 1 }).addTo(map).bindTooltip(tip);
          }
        });
      });
      if (onData) onData(d.routes);
    },
  };

  window.SMARTRIDE = SMARTRIDE;
})();
