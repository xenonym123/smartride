<?php include '../views/partials/dashboard_top.php'; ?>
<?php page_head('Track a jeepney', 'Bacolod\'s 24 jeepney lines, where you are, and every jeepney on the line you pick'); ?>
<div class="lines-layout">
    <div class="card" style="padding:16px"><div class="map" id="map"><div class="map-note">Loading the map…</div></div></div>
    <div class="card lines-panel" id="linesPanel"><p class="empty">Loading the lines…</p></div>
</div>
<p class="hint">Your location stays on this phone. SmartRide only uses it to sort the lines by distance and show you where to get on.
    Lines 1–3, 5–11 and 16 are mapped street by street on OpenStreetMap; the others are traced from the LPTRP landmarks, so check the signboard.
    Route data © OpenStreetMap contributors (ODbL), via the DASIG Bacolod route pack.</p>
<script>
document.addEventListener('DOMContentLoaded', () => {
  // Jeepney lines (24 LPTRP lines) + live jeepneys + your location. GEOAPIFY: where to get on (walking route).
  SMARTRIDE.lineMap({ map: 'map', panel: 'linesPanel', routesUrl: 'api_routes.php', fleetUrl: 'api_fleet.php', geoUrl: 'api_geo.php' });
});
</script>
<?php include '../views/partials/dashboard_bottom.php'; ?>
