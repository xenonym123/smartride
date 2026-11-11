<?php include '../views/partials/dashboard_top.php'; ?>
<?php page_head('Track a jeepney', 'Where each unit is now, and how many seats are left',
    '<button class="btn" type="button" id="locateBtn">' . icon('locate') . 'Use my location</button>'); ?>
<div class="nearest" id="nearest" hidden>
    <?= icon('pin') ?>
    <div style="flex:1;min-width:0" id="nearestText"></div>
</div>
<div class="row cols-main">
    <div class="card" style="padding:16px"><div class="map" id="map"><div class="map-note">Loading the map…</div></div></div>
    <div class="card">
        <h3>Units</h3>
        <div id="unitList"><p class="empty">Loading…</p></div>
        <p class="hint" style="margin-top:12px">Updates every 15 seconds. Units that are parked, broken down, or offline can't take fares.</p>
        <p class="hint">Your location stays on this phone. SmartRide only uses it to measure how far each jeepney is.</p>
    </div>
</div>
<div class="card">
    <h3>Routes and landmarks</h3>
    <p class="hint" style="margin-top:-6px">Each route runs from landmark A to landmark B. The road line comes from Geoapify (bus mode).</p>
    <div id="routeList"><p class="empty">Loading…</p></div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const list = document.getElementById('unitList');
  const tone = { on_trip: 'green', parked: 'brand', breakdown: 'red', offline: 'gray' };
  const label = { on_trip: 'On a trip', parked: 'Parked', breakdown: 'Breakdown', offline: 'Offline' };
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const SPEED_KMH = 15; // average jeepney speed in city traffic, for the time estimate
  const pinIcon = <?= json_encode(icon('pin')) ?>;
  const walkIcon = <?= json_encode(icon('route')) ?>;

  let me = null;      // the passenger's location, once they share it
  let units = [];
  let youMarker = null;

  const draw = () => {
    // Distance from the passenger to each unit (only once we know where they are).
    units.forEach((u) => {
      u.km = (me && u.latitude != null) ? SMARTRIDE.distanceKm(me.lat, me.lng, u.latitude, u.longitude) : null;
    });
    const rank = (u) => (u.state === 'on_trip' ? 0 : 1);
    units.sort((a, b) => rank(a) - rank(b) || ((a.km ?? 1e9) - (b.km ?? 1e9)));

    list.innerHTML = units.map((u) => {
      const far = u.km != null ? ` · ${u.km.toFixed(1)} km away` : '';
      const right = u.state === 'on_trip'
        ? `<span class="pill ${u.seats_left ? 'pill-green' : 'pill-amber'}">${u.seats_left ? u.seats_left + ' seats' : 'Full'}</span>`
        : `<span class="pill pill-${tone[u.state]}">${label[u.state]}</span>`;
      return `<div class="list-row">
        <span class="dot ${tone[u.state]}"></span>
        <div class="who" style="flex:1"><b>${esc(u.unit_code)}</b><span>${esc(u.route_name || 'Not on a route')}${u.state === 'on_trip' ? ' · ' + u.onboard + '/' + u.capacity : ''}${far}</span></div>
        ${right}</div>`;
    }).join('');
    showNearest();
  };

  // The nearest jeepney that is on a trip and still has a seat.
  let routedTo = null;   // which unit the walking route was drawn to
  let routeLine = null;
  let walk = '';         // "Walk 350 m · about 5 min to reach it"
  let near = '';         // "You're near Lacson Street, Bacolod"
  const showNearest = () => {
    if (!me) return;
    const ok = units.filter((u) => u.state === 'on_trip' && u.seats_left > 0 && u.km != null);
    const box = document.getElementById('nearest');
    const text = document.getElementById('nearestText');
    box.hidden = false;
    if (!ok.length) { text.innerHTML = `<b>No jeepney with a free seat is on the road right now.</b>${near}`; return; }
    const n = ok[0];
    const mins = Math.max(1, Math.round(n.km / SPEED_KMH * 60));
    text.innerHTML = `<span class="muted small">Nearest jeepney with a seat</span>
      <div class="big">${esc(n.unit_code)} · ${n.km.toFixed(1)} km</div>
      <span>${esc(n.route_name)} · about ${mins} min away · ${n.seats_left} seats left</span>${walk}${near}`;
    if (routedTo !== n.unit_code) drawRoute(n.unit_code);
  };

  // GEOAPIFY: street name of where the passenger is (reverse geocoding).
  const whereAmI = async () => {
    try {
      const d = await SMARTRIDE.get(`api_geo.php?action=where&lat=${me.lat}&lng=${me.lng}`);
      if (d.ok) near = `<span class="muted small" style="display:block;margin-top:6px">${pinIcon} You're near ${esc(d.address)}${d.demo ? ' · <span class="pill pill-amber">Demo mode</span>' : ''}</span>`;
    } catch (e) { /* the page works without it */ }
    showNearest();
  };

  // GEOAPIFY: walking route from the passenger to that jeepney, drawn as a dashed line.
  const drawRoute = async (code) => {
    routedTo = code;
    try {
      const d = await SMARTRIDE.get(`api_geo.php?action=route&lat=${me.lat}&lng=${me.lng}&unit=${encodeURIComponent(code)}`);
      if (!d.ok) return;
      if (routeLine) routeLine.remove();
      if (map && typeof L !== 'undefined') routeLine = L.polyline(d.points, { color: '#2A20D8', weight: 4, dashArray: '6 8' }).addTo(map);
      const dist = d.meters >= 1000 ? (d.meters / 1000).toFixed(1) + ' km' : d.meters + ' m';
      walk = `<span style="display:block;margin-top:6px">${walkIcon} Walk ${dist} · about ${d.minutes} min to reach it${d.demo ? ' <span class="pill pill-amber">Demo mode</span>' : ''}</span>`;
      showNearest();
    } catch (e) { routedTo = null; }
  };

  const map = SMARTRIDE.fleetMap('map', 'api_fleet.php', (data) => { units = data; draw(); });

  // GEOAPIFY: each route's bus road line and landmarks, plus a list under the map.
  SMARTRIDE.drawRoutes(map, 'api_routes.php', (routes) => {
    document.getElementById('routeList').innerHTML = routes.map((r) => `
      <div class="route-row"><span class="swatch" style="background:${r.color}"></span>
        <div><b>${esc(r.name)}</b> · ${esc(r.fare)} · ${r.km} km, about ${r.minutes} min by bus
          ${r.source === 'straight' ? '<span class="pill pill-amber">Demo mode · straight lines</span>' : ''}
          <div class="stops">${r.stops.map((st, k) => (k === 0 ? '<b>A</b> ' : (k === r.stops.length - 1 ? '<b>B</b> ' : '')) + esc(st.name)).join(' → ')}</div>
        </div></div>`).join('');
  });

  const placeMe = (pos, zoom) => {
    me = pos;
    routedTo = null;
    if (map && typeof L !== 'undefined') {
      const ic = L.divIcon({ className: '', iconSize: [18, 18], iconAnchor: [9, 9], html: '<div class="you-marker"></div>' });
      if (youMarker) youMarker.setLatLng([pos.lat, pos.lng]);
      else youMarker = L.marker([pos.lat, pos.lng], { icon: ic, title: 'You are here', zIndexOffset: 1000 }).addTo(map).bindPopup('<b>You are here</b>');
      map.setView([pos.lat, pos.lng], zoom);
    }
    draw();
    whereAmI();
  };

  // Get the passenger's position from the phone (browser GPS). If that's blocked,
  // ask GEOAPIFY's IP geolocation for a rough spot instead.
  const btn = document.getElementById('locateBtn');
  const findMe = () => {
    btn.disabled = true;
    btn.lastChild.textContent = 'Finding you…';
    SMARTRIDE.locate((pos) => {
      btn.disabled = false;
      btn.lastChild.textContent = 'Update my location';
      placeMe(pos, 15);
    }, async (message) => {
      btn.disabled = false;
      btn.lastChild.textContent = 'Use my location';
      try {
        const d = await SMARTRIDE.get('api_geo.php?action=ip');
        if (!d.ok) throw new Error();
        SMARTRIDE.toast({ title: 'Using your rough area', body: `${message} For now we're using ${d.city}, from your internet connection.` });
        placeMe({ lat: d.lat, lng: d.lng }, 13);
      } catch (e) {
        SMARTRIDE.toast({ title: 'Location', body: message });
      }
    });
  };
  btn.addEventListener('click', findMe);
  if (new URLSearchParams(location.search).get('locate') === '1') findMe();
});
</script>
<?php include '../views/partials/dashboard_bottom.php'; ?>
