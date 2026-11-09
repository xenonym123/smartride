# The three APIs in SmartRide

Each section covers what the API is, where it lives in the code, how a request flows, and how to demo it. Line numbers match the files in this zip.

---

## 1. Geolocation API

**What it is.** It's built into every browser (`navigator.geolocation`). The page asks, the browser shows "Allow location?", and if you say yes it hands back your latitude and longitude from the phone's GPS or Wi-Fi. No key and no cost. It only works on `localhost` or `https`.

**Where it's used**

| What | File | Line |
| --- | --- | --- |
| Passenger: ask for the location once (`getCurrentPosition`) | `assets/js/app.js` | 123–125 |
| Distance between two points (haversine formula) | `assets/js/app.js` | 112 |
| "Use my location" button, blue dot, nearest jeepney with a seat | `views/passenger/track.php` | 50, 69 |
| Driver: keep sending the position during a trip (`watchPosition`) | `assets/js/app.js` | 93, 100 |
| Turned on only while a trip is running | `views/partials/dashboard_bottom.php` | 7 |
| Server saves the position, rejects impossible coordinates | `controllers/api_gps.php` | 22 |
| Maps read the newest position of every unit | `models/fleet_model.php` → `get_fleet()` |

**How it flows**

Driver's phone → `watchPosition` → every 20 s `POST api_gps.php {lat, lng}` → `gps_pings` table → `api_fleet.php` → both maps.

Passenger → **Use my location** → `getCurrentPosition` → the distance to each jeepney is worked out in the browser → "BCN 105 · 0.9 km · about 4 min away · 7 seats left". The time assumes 15 km/h in city traffic.

The passenger's location never leaves the phone. Only the driver's gets saved.

**Demo**

As `juandc`, open Track a Jeepney → Use my location → Allow. The driver's pages show **GPS on · sent 10:42 PM** at the top. Block location and you get a plain message telling you how to allow it.

---

## 2. PayMongo QR Ph (payment gateway)

**What it is.** PayMongo is a Philippine payment gateway. QR Ph is the national QR standard, so one code works in GCash, Maya, and most bank apps. SmartRide uses it for wallet reloads. We call PayMongo's REST API with cURL, and our secret key goes in the `Authorization: Basic` header.

**Where it's used**

| What | File | Line |
| --- | --- | --- |
| Demo mode when there's no key | `includes/paymongo.php` | 15 |
| One request to PayMongo (cURL, Basic auth with the secret key) | `includes/paymongo.php` | 22, 29 |
| 1. Create a payment intent: amount in centavos, QR Ph only | `includes/paymongo.php` | 62 |
| 2. Create a `qrph` payment method with the passenger's name and email | `includes/paymongo.php` | 71 |
| 3. Attach them. PayMongo answers with the QR image | `includes/paymongo.php` | 76, 82 |
| 4. Ask PayMongo for the status (`succeeded` = paid) | `includes/paymongo.php` | 95 |
| 5. Check the webhook signature (HMAC-SHA256, max 5 min old) | `includes/paymongo.php` | 104, 120, 123 |
| Reload form: QR Ph → PayMongo | `controllers/passenger_reload.php` | 43, 50 |
| Friendly message if PayMongo is down | `controllers/passenger_reload.php` | 53 |
| Pay page: shows the QR, checks every 3 s | `views/passenger/pay.php` | 75 |
| Status check credits the wallet when it's paid | `controllers/api_payment_status.php` | 24 |
| Webhook from PayMongo (`payment.paid`, `payment.failed`, `qrph.expired`) | `controllers/paymongo_webhook.php` | 24, 36 |
| Credit the wallet once only, even if both arrive | `models/payment_model.php` | 56, 64 |
| Simulate button works in demo mode only | `controllers/passenger_pay.php` | 31 |

**How it flows**

```
Passenger: Reload ₱100 by QR Ph
  → POST /v1/payment_intents         amount 10000, payment_method_allowed ["qrph"]
  → POST /v1/payment_methods         type "qrph", billing name + email
  → POST /v1/payment_intents/{id}/attach
  ← next_action.code.image_url       (the QR, as a base64 image)
Passenger scans it with GCash / Maya / bank app and pays
  → PayMongo marks the intent "succeeded" and sends payment.paid to our webhook
  → SmartRide: payments.status = 'paid', reloads row, wallet + ₱100, notification
```

**Safety points to mention**

- The wallet is credited only after PayMongo confirms. Clicking "back" or faking a form does nothing.
- The `UPDATE ... WHERE status = 'pending'` means a payment can turn into money only once. That holds even if the webhook and the status check land at the same time.
- A webhook without PayMongo's correct signature gets `401` and is ignored. So does a signature older than 5 minutes, so an old request can't be replayed.
- The secret key stays on the server in `config.php`. The browser never sees it.
- Each passenger can only open their own payments. `get_payment()` checks the wallet.

**Demo**

With no key: Reload → QR Ph → ₱100 → **Simulate payment (demo only)**. The page flips to Paid and the balance goes up.

With a test key: you get a real QR Ph code from PayMongo. Don't pay it with real money. Use PayMongo's test link if it shows one.

---

## 3. Notification API

**What it is.** Also built into the browser (`Notification`). After you allow it once, a page can show system pop-ups like an app does: corner of the screen on a laptop, notification shade on Android. SmartRide saves each alert in a `notifications` table. Every page asks the server for new ones every 10 seconds, and the browser shows each one as a pop-up. If notifications are blocked, it shows a small card on the page instead.

**Where it's used**

| What | File | Line |
| --- | --- | --- |
| Save an alert | `models/notification_model.php` | 6 |
| Only the alerts the browser hasn't seen | `models/notification_model.php` | 38 |
| JSON endpoint the pages ask every 10 s | `controllers/api_notifications.php` | 24 |
| Ask permission ("Turn on alerts" button) | `assets/js/app.js` | 148 |
| Check for new alerts every 10 seconds | `assets/js/app.js` | 139 |
| Show the system pop-up (`new Notification`) | `assets/js/app.js` | 170 |
| Fallback card when pop-ups are blocked | `assets/js/app.js` | 178 |
| Started on every dashboard page | `views/partials/dashboard_bottom.php` | 5 |
| Fare paid / low balance / unit full / blocked fare / frozen QR scanned | `controllers/api_scan.php` | 83–115 |
| Reload successful | `models/payment_model.php` → `complete_payment()`, `controllers/passenger_reload.php` |
| New passenger signed up (to admins) | `controllers/register.php` |
| Notifications page + unread badge in the sidebar | `controllers/notifications.php`, `views/partials/dashboard_top.php` |

**Who gets what**

| Who | When |
| --- | --- |
| Passenger | fare paid, balance low, not enough balance, unit full, reload successful, QR frozen / unfrozen, someone scanned your frozen QR |
| Driver | unit is full |
| Admin | a new passenger signed up |

**Demo**

As `juandc`, press **Turn on alerts** → Allow. In another window the driver scans juandc's QR. Within about 10 seconds a "Fare paid · ₱13.00" pop-up shows up, and the bell in the sidebar gets a number.
