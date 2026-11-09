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
<script>
document.addEventListener('DOMContentLoaded', () => {
  const list = document.getElementById('unitList');
  const tone = { on_trip: 'green', parked: 'brand', breakdown: 'red', offline: 'gray' };
  const label = { on_trip: 'On a trip', parked: 'Parked', breakdown: 'Breakdown', offline: 'Offline' };
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const SPEED_KMH = 15; // average jeepney speed in city traffic, for the time estimate

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

    // The nearest jeepney that is on a trip and still has a seat.
    if (!me) return;
    const ok = units.filter((u) => u.state === 'on_trip' && u.seats_left > 0 && u.km != null);
    const box = document.getElementById('nearest');
    const text = document.getElementById('nearestText');
    box.hidden = false;
    if (!ok.length) { text.innerHTML = '<b>No jeepney with a free seat is on the road right now.</b>'; return; }
    const n = ok[0];
    const mins = Math.max(1, Math.round(n.km / SPEED_KMH * 60));
    text.innerHTML = `<span class="muted small">Nearest jeepney with a seat</span>
      <div class="big">${esc(n.unit_code)} · ${n.km.toFixed(1)} km</div>
      <span>${esc(n.route_name)} · about ${mins} min away · ${n.seats_left} seats left</span>`;
  };

  const map = SMARTRIDE.fleetMap('map', 'api_fleet.php', (data) => { units = data; draw(); });

  // GEOLOCATION API: ask the browser for the passenger's position.
  const btn = document.getElementById('locateBtn');
  const findMe = () => {
    btn.disabled = true;
    btn.lastChild.textContent = 'Finding you…';
    SMARTRIDE.locate((pos) => {
      me = pos;
      btn.disabled = false;
      btn.lastChild.textContent = 'Update my location';
      if (map && typeof L !== 'undefined') {
        const icon = L.divIcon({ className: '', iconSize: [18, 18], iconAnchor: [9, 9], html: '<div class="you-marker"></div>' });
        if (youMarker) youMarker.setLatLng([pos.lat, pos.lng]);
        else youMarker = L.marker([pos.lat, pos.lng], { icon, title: 'You are here', zIndexOffset: 1000 }).addTo(map).bindPopup('<b>You are here</b>');
        map.setView([pos.lat, pos.lng], 14);
      }
      draw();
    }, (message) => {
      btn.disabled = false;
      btn.lastChild.textContent = 'Use my location';
      SMARTRIDE.toast({ title: 'Location', body: message });
    });
  };
  btn.addEventListener('click', findMe);
  if (new URLSearchParams(location.search).get('locate') === '1') findMe();
});
</script>
<?php include '../views/partials/dashboard_bottom.php'; ?>
