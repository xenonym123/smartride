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
     * NOTIFICATIONS API. Asks the server for new alerts every 10 seconds and pops each
     * one up with the browser's Notification API. If notifications are off or blocked,
     * the alert shows as a small card in the corner of the page instead.
     */
    notifications(url) {
      const supported = 'Notification' in window;
      const badges = () => document.querySelectorAll('[data-notif-count]');
      const banner = document.querySelector('[data-alerts-banner]');
      if (banner && supported && Notification.permission === 'default') banner.hidden = false;

      document.addEventListener('click', async (e) => {
        if (!e.target.closest('[data-enable-alerts]')) return;
        if (!supported) { SMARTRIDE.toast({ title: 'Not supported', body: "This browser can't show notifications." }); return; }
        const answer = await Notification.requestPermission();
        if (banner) banner.hidden = true;
        if (answer === 'granted') SMARTRIDE.popup({ title: 'Alerts are on', body: "You'll get a pop-up for fares, reloads and low balance.", link: '' });
        else SMARTRIDE.toast({ title: 'Alerts are off', body: 'You can still see everything on the Notifications page.' });
      });

      let after = null;
      const check = async () => {
        let d;
        try { d = await SMARTRIDE.get(after === null ? url : `${url}?after=${after}`); } catch (e) { return; }
        badges().forEach((b) => { b.textContent = d.unread; b.hidden = !d.unread; });
        if (after !== null) d.items.forEach((n) => SMARTRIDE.popup(n));
        after = d.latest;
      };
      check();
      setInterval(check, 10000);
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
  };

  window.SMARTRIDE = SMARTRIDE;
})();
