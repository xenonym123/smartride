<?php include '../views/partials/dashboard_top.php'; ?>
<?php if (!$trip): ?>
    <?php page_head('Navigate', 'Turn-by-turn directions for your line'); ?>
    <div class="card"><p class="empty">Start a trip first. Navigation follows the line of your trip.</p>
        <a class="btn" href="driver_home.php"><?= icon('play') ?>Go to Home</a></div>
<?php else: ?>
    <?php page_head('Navigate', 'Line ' . (int) $trip['route_id'] . ' · ' . e($line['short_name'] ?? $trip['route_name']) . ' · ' . e($trip['unit_code'])); ?>
    <div class="nav-banner" id="navBanner" aria-live="polite">
        <div class="nav-arrow" id="navArrow"></div>
        <div class="nav-words"><div class="nav-dist" id="navDist">Ready</div>
            <div class="nav-text" id="navText">Tap Start to begin turn-by-turn directions.</div>
            <div class="nav-then" id="navThen"></div></div>
    </div>
    <div class="nav-layout">
        <div class="card" style="padding:12px"><div class="map nav-map" id="navMap"><div class="map-note">Loading your line…</div></div></div>
        <div class="card nav-panel">
            <div class="nav-buttons">
                <button class="btn block" type="button" id="navStart"><?= icon('play') ?>Start navigation</button>
                <button class="btn ghost sm" type="button" id="navVoice" hidden>Voice on</button>
                <button class="btn ghost sm" type="button" id="navCenter" hidden>Follow me</button>
            </div>
            <?php if (PRESENTATION_TOOLS): ?>
                <div class="nav-sim">
                    <b>Drive the line for a presentation</b>
                    <span class="hint">Moves your jeepney along the line, with the voice, and sends its position to passengers.</span>
                    <div class="nav-buttons"><button class="btn ghost sm" type="button" id="navSim" data-speed="1">Simulate drive</button>
                        <select class="input sm" id="navSimSpeed" aria-label="Simulation speed"><option value="1">1× speed</option><option value="5" selected>5× speed</option><option value="10">10× speed</option></select></div>
                </div>
            <?php endif; ?>
            <h4>Coming up</h4>
            <ol class="nav-list" id="navList"><li class="muted">Loading…</li></ol>
            <p class="hint" id="navNote"></p>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
      // GEOAPIFY street names + the line's own shape -> spoken turn-by-turn directions.
      SMARTRIDE.driverNav({ url: 'api_navigation.php', gpsUrl: 'api_gps.php', simulate: <?= PRESENTATION_TOOLS ? 'true' : 'false' ?> });
    });
    </script>
<?php endif; ?>
<?php include '../views/partials/dashboard_bottom.php'; ?>
