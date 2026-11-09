    </main>
</div>
<script src="../assets/js/app.js"></script>
<?php if (!empty($use_map)): ?><script src="../assets/vendor/leaflet/leaflet.js"></script><?php endif; ?>
<script>SMARTRIDE.notifications('api_notifications.php'); // Notification API: new alerts every 10 seconds</script>
<?php if ($_SESSION['role'] === 'driver' && !empty($trip)): ?>
<script>SMARTRIDE.gpsBeacon('api_gps.php'); // Geolocation API: send the phone's location while a trip runs</script>
<?php endif; ?>
</body>
</html>
