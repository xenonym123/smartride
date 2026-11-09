<?php include '../views/partials/dashboard_top.php'; ?>
<?php
$sub = $trip ? $trip['unit_code'] . ' · ' . $trip['route_name'] . ' · fare ' . peso($trip['base_fare']) : 'The scanner by the jeepney door';
page_head('QR scanner', $sub);
?>
<?php if (!$trip): ?>
    <?php include '../views/partials/no_trip.php'; ?>
<?php else: ?>
<div class="scanner">
    <div class="card" style="padding:16px">
        <div class="viewer" id="viewer">
            <video id="video" playsinline muted hidden></video>
            <div class="frame" id="frame" hidden></div>
            <div class="msg" id="camMsg">
                <?= str_replace('class="ic"', 'class="ic" style="width:40px;height:40px"', icon('camera')) ?>
                <b>Point the camera at a passenger's QR</b>
                <span class="small">Your browser will ask to use the camera. On a laptop, hold the phone with the QR up to the webcam.</span>
                <button class="btn" type="button" id="startCam" style="background:#fff;color:var(--brand);border-color:#fff"><?= icon('camera') ?>Start camera</button>
            </div>
        </div>
        <form id="manual" class="searchbar" style="margin-top:14px">
            <label class="sr-only" for="code">QR code text</label>
            <input class="input" id="code" placeholder="No camera? Paste the code from the passenger's My QR page" autocomplete="off">
            <button class="btn" type="submit">Scan</button>
        </form>
    </div>
    <div class="row" style="align-content:start">
        <div class="result idle" id="result" role="status" aria-live="polite"><?= icon('scan') ?><span>Waiting for a QR…</span></div>
        <div class="card">
            <div class="statrows">
                <div class="statrow"><span>On board</span><b><span id="onboard"><?= (int) $live['onboard'] ?></span>/<?= (int) $live['capacity'] ?></b></div>
                <div class="statrow"><span>Seats left</span><b id="seatsLeft"><?= (int) $live['seats_left'] ?></b></div>
                <div class="statrow"><span>Collected this trip</span><b id="collected"><?= e($live['collected']) ?></b></div>
            </div>
        </div>
        <p class="hint">The scanner takes the fare only if the unit has a seat, the QR is current and not frozen, and the wallet covers the fare. One fare per passenger per trip.</p>
    </div>
</div>
<script src="../assets/vendor/jsQR.js"></script>
<script>
(() => {
  const url = 'api_scan.php';
  const $ = (id) => document.getElementById(id);
  const icons = { paid: <?= json_encode(icon('check')) ?>, blocked: <?= json_encode(icon('alert')) ?>, bad: <?= json_encode(icon('x')) ?> };
  let busy = false, lastCode = '', lastAt = 0;

  async function submit(code) {
    code = code.trim();
    if (!code || busy) return;
    if (code === lastCode && Date.now() - lastAt < 4000) return; // same QR still in view
    busy = true; lastCode = code; lastAt = Date.now();
    let d;
    try { d = await SMARTRIDE.post(url, { code }); }
    catch (e) { d = { result: 'error', message: 'Could not reach the server. Check the connection.' }; }
    const kind = d.result === 'paid' ? 'paid' : (d.result === 'blocked_full' || d.result === 'duplicate' ? 'blocked' : 'bad');
    $('result').className = 'result ' + kind;
    $('result').innerHTML = icons[kind] + '<span></span>';
    $('result').querySelector('span').textContent = d.message;
    if (d.trip) { $('onboard').textContent = d.onboard; $('seatsLeft').textContent = d.seats_left; $('collected').textContent = d.collected; }
    if (navigator.vibrate) navigator.vibrate(kind === 'paid' ? 80 : [60, 60, 60]);
    busy = false;
  }

  $('manual').addEventListener('submit', (e) => { e.preventDefault(); submit($('code').value); $('code').value = ''; });

  $('startCam').addEventListener('click', async () => {
    if (!navigator.mediaDevices?.getUserMedia) { $('camMsg').querySelector('b').textContent = "This browser can't use the camera. Paste the code below instead."; return; }
    let stream;
    try { stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } }); }
    catch (e) { $('camMsg').querySelector('b').textContent = 'Camera blocked. Allow it in the address bar, or paste the code below.'; return; }
    const video = $('video');
    video.srcObject = stream; video.hidden = false; $('frame').hidden = false; $('camMsg').hidden = true;
    await video.play();
    const canvas = document.createElement('canvas'), ctx = canvas.getContext('2d', { willReadFrequently: true });
    const loop = () => {
      if (video.readyState === video.HAVE_ENOUGH_DATA) {
        canvas.width = video.videoWidth; canvas.height = video.videoHeight;
        ctx.drawImage(video, 0, 0);
        const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const hit = jsQR(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' });
        if (hit && hit.data) submit(hit.data);
      }
      requestAnimationFrame(loop);
    };
    loop();
  });
})();
</script>
<?php endif; ?>
<?php include '../views/partials/dashboard_bottom.php'; ?>
