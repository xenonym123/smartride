    </main>
</div>
<script src="../assets/js/app.js"></script>
<?php if (!empty($use_map)): ?><script src="../assets/vendor/leaflet/leaflet.js"></script><?php endif; ?>
<?php if (ONESIGNAL_APP_ID !== ''):
    // ONESIGNAL web push. The App ID is meant to be public; the REST API key is not, so it never appears here.
    // The service worker file sits in the SmartRide folder, e.g. /smartride/OneSignalSDKWorker.js
    $base = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
?>
<script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer></script>
<script>
window.OneSignalDeferred = window.OneSignalDeferred || [];
OneSignalDeferred.push(async function (OneSignal) {
  await OneSignal.init({
    appId: <?= json_encode(ONESIGNAL_APP_ID) ?>,
    allowLocalhostAsSecureOrigin: true,
    serviceWorkerPath: <?= json_encode(ltrim($base . '/OneSignalSDKWorker.js', '/'), JSON_UNESCAPED_SLASHES) ?>,
    serviceWorkerParam: { scope: <?= json_encode($base . '/', JSON_UNESCAPED_SLASHES) ?> },
  });
  await OneSignal.login(<?= json_encode(onesignal_external_id($_SESSION['user_id'])) ?>); // link this browser to this user
  SMARTRIDE.usePush(OneSignal);
});
</script>
<?php endif; ?>
<script>SMARTRIDE.notifications('api_notifications.php'); // new alerts for the bell, every 10 seconds</script>
<?php if ($_SESSION['role'] === 'driver' && !empty($trip)): ?>
<script>SMARTRIDE.gpsBeacon('api_gps.php'); // Geolocation API: send the phone's location while a trip runs</script>
<?php endif; ?>
</body>
</html>
