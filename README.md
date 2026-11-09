# SmartRide: Integrated Public Transport and Cashless Payment System

This used to be 1TAP. Same e-jeepney system for Bacolod: QR fares, a live passenger count, GPS tracking. Now it has three outside APIs built in:

1. **Geolocation API.** The browser's GPS. Passengers find the nearest jeepney with a free seat. Drivers' phones send the jeepney's position.
2. **PayMongo QR Ph.** A real payment gateway for wallet reloads. Pay with GCash, Maya or any bank app.
3. **Notification API.** Pop-up alerts on the phone or computer when a fare is paid, your balance runs low, a reload lands, or the jeepney fills up.

`APIS.md` explains each one, with file and line numbers, for the presentation.

The code style is the same as before: plain PHP functions, MySQLi with `bind_param`, one file per page.

## Run it on XAMPP

1. Copy the `smartride` folder into `C:\xampp\htdocs\`.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open <http://localhost/phpmyadmin>, click **Import**, pick `smartride/database/smartride_db.sql`, click **Import**. That makes a new database called `smartride_db`. Your old `onetap_db` and `erased_db` stay as they are.
4. Go to <http://localhost/smartride/>.

If your MySQL root user has a password, put it in `config/config.php`.

Re-import the SQL file the day before you present. The demo trips and fares are dated from when you import, so "today" stays today.

## Accounts

| Role | Username | Password |
| --- | --- | --- |
| Admin (operator side) | `admin` | your existing admin password |
| Driver | `ramon` | `smartride2026` |
| Passenger | `juandc` | `smartride2026` |

The other demo passengers also use `smartride2026`. The old accounts (`user`, `driver`, `driver2`, `admin`) keep their old passwords.

## Turning on PayMongo

It works without PayMongo. With no key in `config/config.php`, SmartRide runs in **demo mode**: QR Ph shows a sample QR and a **Simulate payment (demo only)** button. There's a yellow "Demo mode" tag on the page so nobody mistakes it for a real payment.

To use the real gateway in test mode:

1. Make an account at <https://dashboard.paymongo.com>. Go to **Developers → API Keys** and copy the **secret key** (`sk_test_...`).
2. Paste it into `config/config.php`:
   ```php
   define('PAYMONGO_SECRET_KEY', 'sk_test_xxxxxxxx');
   ```
3. Reload with **QR Ph**. You get a real QR Ph code from PayMongo.

Read this before you scan anything:

- **Don't pay a test QR with your real GCash.** PayMongo says QR Ph codes are real even in test mode, and paying one moves real money. If PayMongo includes a test link, the pay page shows **Open PayMongo's test page to simulate paying**. Use that instead.
- PayMongo may need to turn on QR Ph for your account first. If you get "PayMongo said no", email their support.
- **"Could not reach PayMongo... SSL certificate problem"** on XAMPP for Windows: set `CURL_CA_FILE` in `config.php` to `C:\xampp\apache\bin\curl-ca-bundle.crt`.
- Never upload `config.php` with your key to GitHub, and don't put it on a slide.

**Webhook (optional).** The pay page checks with PayMongo every 3 seconds on its own, so you don't need a webhook on localhost. If you want one anyway: PayMongo has to reach your PC, so run a tunnel like `ngrok http 80`. Then add a webhook in the PayMongo dashboard pointing to `https://<your-ngrok-url>/smartride/controllers/paymongo_webhook.php` with the events `payment.paid`, `payment.failed` and `qrph.expired`. Put its secret (`whsk_...`) in `PAYMONGO_WEBHOOK_SECRET`.

## Geolocation and notifications need localhost

Browsers only give location and notifications to pages on `localhost` or `https`. That's the same rule as the camera scanner. So on the laptop, use `http://localhost/smartride/...`.

On a phone over Wi-Fi (`http://192.168...`), location and system pop-ups are blocked. The page still works, and alerts show as a card in the corner instead.

## Demo script

You need two windows, or a laptop plus a phone.

1. **Passenger** (`juandc`): press **Turn on alerts** on Home and allow it.
2. **Track a Jeepney → Use my location.** Allow location. A blue dot appears, and the top card shows the nearest jeepney with a seat, how far it is, and about how many minutes away.
3. **Reload Wallet → QR Ph → ₱100.** The PayMongo QR page opens. In demo mode, press **Simulate payment**. The page flips to "Paid" by itself and a "Reload successful" alert pops up.
4. **Driver** (`ramon`, trip already running): open **QR Scanner** and scan the passenger's **My QR**. Within 10 seconds the passenger gets "Fare paid · ₱13.00".
5. On **Passenger Count**, press **Someone got on** until 25/25. The driver gets "BCN 105 is full". The next passenger who scans gets told the unit is full, and no fare is taken.
6. The **GPS on · sent 10:42 PM** tag at the top of the driver's pages shows the phone's location going out.

## Folders

```
smartride/
  index.php            sends you to controllers/home.php
  config/              config.php (settings + PayMongo keys), db_connect.php
  includes/
    functions.php      e(), peso(), flash(), validate_user(), error page
    auth.php           sessions, login/logout, require_role(), CSRF
    paymongo.php       talks to the PayMongo API with cURL
    icons.php
  models/              all the SQL, as plain functions
    user  wallet  fare  trip  fleet  report  log  payment  notification
  controllers/         one file per page (the URLs you open)
    api_*.php          JSON for the JavaScript on the pages
    paymongo_webhook.php   PayMongo calls this when a payment is paid
  views/               the HTML
  assets/              css, js (app.js has the Geolocation + Notification code), images, libraries
  database/            smartride_db.sql
```

`config`, `includes`, `models`, `views` and `database` are blocked from the browser with `.htaccess`.

## New pages and files

| File | What it does |
| --- | --- |
| `controllers/passenger_pay.php` | shows the PayMongo QR Ph code and waits for payment |
| `controllers/api_payment_status.php` | asks PayMongo whether the reload is paid |
| `controllers/paymongo_webhook.php` | PayMongo tells us a payment is paid, failed or expired |
| `controllers/notifications.php` | list of your alerts, for every role |
| `controllers/api_notifications.php` | new alerts for the pop-ups, every 10 seconds |
| `includes/paymongo.php` | the PayMongo API calls |
| `models/payment_model.php` | `payments` table |
| `models/notification_model.php` | `notifications` table |

The database has two new tables, `payments` and `notifications`. `reloads.method` also accepts `qrph` now.

## Settings (`config/config.php`)

- `APP_DEBUG`: keep it `false` when presenting.
- `PAYMONGO_SECRET_KEY`, `PAYMONGO_WEBHOOK_SECRET`, `CURL_CA_FILE`: see above.
- `QR_WINDOW`: seconds before a ride QR changes (30).
- `RELOAD_MIN` / `RELOAD_MAX`: ₱20 to ₱5,000. PayMongo won't take less than ₱20 anyway.

## Notes

- Cash reloads (partner store, kiosk) are approved right away in the demo.
- The onboard camera count is still simulated with the **Someone got on / got off** buttons.
- The maps need internet (OpenStreetMap). So does real PayMongo. Everything else, demo mode included, works offline.
- Needs PHP 8.0+ with `mysqli` and `curl`. XAMPP has both on by default.
