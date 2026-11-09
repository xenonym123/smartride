<?php include '../views/partials/dashboard_top.php'; ?>
<?php
$sub = $trip ? $trip['unit_code'] . ' · ' . $trip['route_name'] : 'Live count from the onboard camera';
$tools = $trip ? '<span class="pill pill-brand gps-state" data-gps-state>' . icon('gps') . 'GPS starting…</span>' : '';
page_head('Passenger count', $sub, $tools);
?>
<?php if (!$trip): ?>
    <?php include '../views/partials/no_trip.php'; ?>
<?php else: ?>
<div class="row cols-main">
    <div class="card">
        <h3>Live from the onboard camera</h3>
        <div class="live">
            <div class="ring">
                <svg viewBox="0 0 100 100" aria-hidden="true"><circle cx="50" cy="50" r="42" stroke="#ECEBFE" stroke-width="11" fill="none"/>
                    <circle id="ringFill" cx="50" cy="50" r="42" stroke="#2A20D8" stroke-width="11" fill="none" stroke-linecap="round" stroke-dasharray="0 264"/></svg>
                <div class="n"><b id="onboard"><?= (int) $live['onboard'] ?></b><span>of <?= (int) $live['capacity'] ?> seats</span></div>
            </div>
            <div class="statrows">
                <div class="statrow"><span>Seats left</span><b id="seatsLeft"><?= (int) $live['seats_left'] ?></b></div>
                <div class="statrow"><span>Boarded this trip</span><b id="boarded"><?= (int) $live['boarded'] ?></b></div>
                <div class="statrow"><span>Got off this trip</span><b id="alighted"><?= (int) $live['alighted'] ?></b></div>
            </div>
        </div>
        <div class="statusbar ok" id="statusBar"><span class="pulse"></span><span id="statusText">Taking fares.</span></div>
        <div style="margin-top:22px">
            <h3 style="margin-bottom:4px">Camera simulator</h3>
            <p class="hint">There's no camera in the demo, so use these for riders who board or get off. A paid QR scan adds one automatically.</p>
            <div class="sim">
                <button class="btn ghost" type="button" data-delta="1"><?= icon('plus') ?>Someone got on</button>
                <button class="btn ghost" type="button" data-delta="-1"><?= icon('minus') ?>Someone got off</button>
            </div>
        </div>
    </div>
    <div class="row" style="align-content:start">
        <div class="card stat-card"><div class="label">Collected this trip<?= icon('peso') ?></div><div class="value" id="collected"><?= e($live['collected']) ?></div>
            <div class="sub"><span id="paid"><?= (int) $live['paid'] ?></span> paid scans · <span id="blocked"><?= (int) $live['blocked'] ?></span> blocked</div></div>
        <div class="card"><h3>Latest scans</h3><div id="alerts"></div></div>
    </div>
</div>
<script>
(() => {
  const dataUrl = 'api_count.php', changeUrl = 'api_count.php'; // same file: GET reads, POST changes
  const $ = (id) => document.getElementById(id);
  const tone = { paid: ['green', 'Paid'], blocked_full: ['amber', 'Blocked · full'], blocked_balance: ['red', 'Low balance'], blocked_frozen: ['red', 'QR frozen'] };
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  function paint(d) {
    if (!d.trip) { location.reload(); return; }
    $('onboard').textContent = d.onboard; $('seatsLeft').textContent = d.seats_left;
    $('boarded').textContent = d.boarded; $('alighted').textContent = d.alighted;
    $('collected').textContent = d.collected; $('paid').textContent = d.paid; $('blocked').textContent = d.blocked;
    const ring = $('ringFill'); ring.setAttribute('stroke-dasharray', `${Math.round(264 * d.onboard / d.capacity)} 264`);
    ring.setAttribute('stroke', d.full ? '#C77A00' : '#2A20D8');
    $('statusBar').className = 'statusbar ' + (d.full ? 'full' : 'ok');
    $('statusText').textContent = d.full ? `Full. The scanner is blocking new scans until someone gets off.` : `Taking fares. The scanner blocks new scans at ${d.capacity}.`;
    $('alerts').innerHTML = d.alerts.length ? d.alerts.map((a) => `<div class="list-row"><div class="who"><b>${esc(a.name)}</b><span>${esc(a.time)}</span></div><span class="pill pill-${tone[a.status][0]}">${tone[a.status][1]}</span></div>`).join('')
      : '<p class="empty">No scans yet on this trip.</p>';
  }
  paint(<?= json_encode($live, JSON_HEX_TAG | JSON_HEX_AMP) ?>);
  setInterval(async () => { try { paint(await SMARTRIDE.get(dataUrl)); } catch (e) {} }, 5000);
  document.querySelectorAll('[data-delta]').forEach((b) => b.addEventListener('click', async () => {
    b.disabled = true;
    try { const d = await SMARTRIDE.post(changeUrl, { delta: +b.dataset.delta }); if (d.trip) paint(d); } finally { b.disabled = false; }
  }));
})();
</script>
<?php endif; ?>
<?php include '../views/partials/dashboard_bottom.php'; ?>
