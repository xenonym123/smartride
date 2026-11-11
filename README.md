# SmartRide: Integrated Public Transport and Cashless Payment System

This used to be 1TAP. It's the same e-jeepney system for Bacolod: QR fares, a live passenger count, GPS tracking. Now it connects to five outside APIs, each with its own key:

1. **Geoapify:** landmarks from A to B on every route, bus road lines, street names, the walking route to the nearest jeepney, and location by IP.
2. **Maya Checkout (sandbox):** wallet reloads with a card or Maya wallet. Test only, no real money.
3. **PhilSMS:** a text when your fare is paid, and when your jeepney is close to the landmark where you get off.
4. **Gmail API:** forgot-password emails and payment receipts.
5. **OneSignal:** push pop-ups on the phone or computer.

`APIS.md` explains each one, with file and line numbers, setup steps and a demo script, for the presentation.

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

## API keys

All keys go in **`config/keys.php`**. That file is in `.gitignore`, so git never uploads it to GitHub.

- A fresh copy from GitHub doesn't have `keys.php` yet. Copy `config/keys.example.php` to `config/keys.php` and fill it in.
- Any key left empty runs that feature in **demo mode**, labelled "Demo mode" on screen, so the site never breaks.
- The admin's **Analytics** page shows which keys are set under **Connected APIs**, with **Test** buttons.

| Key | Where to get it |
| --- | --- |
| `GEOAPIFY_API_KEY` | myprojects.geoapify.com (free, no card) |
| `MAYA_PUBLIC_KEY`, `MAYA_SECRET_KEY` | Already filled in with Maya's **public sandbox** keys. Your own: Maya Business Manager sandbox. |
| `PHILSMS_API_TOKEN`, `PHILSMS_SENDER_ID` | dashboard.philsms.com → Developers. Each text uses credits. |
| `GMAIL_CLIENT_ID`, `GMAIL_CLIENT_SECRET`, `GMAIL_REFRESH_TOKEN`, `GMAIL_SENDER` | Google Cloud. Step by step in `APIS.md`. |
| `ONESIGNAL_APP_ID`, `ONESIGNAL_REST_API_KEY` | onesignal.com → Web app → Custom Code |

**Never upload `keys.php`.** If you upload through GitHub's website instead of git, `.gitignore` doesn't apply, so delete `config/keys.php` from the folder first.

**"Could not reach ... SSL certificate problem"** on XAMPP for Windows: set `CURL_CA_FILE` in `config/config.php` to `C:\xampp\apache\bin\curl-ca-bundle.crt`.


## Use localhost for the phone features

Browsers only allow location, notifications and the camera on `localhost` or `https`. So on the laptop, use `http://localhost/smartride/...`.

On a phone over Wi-Fi (`http://192.168...`), GPS and push are blocked by the browser. The page still works: it falls back to Geoapify's IP location, and alerts show on the page instead.

## Demo script

The full version is in `APIS.md`. The short one:

1. **Admin** → Analytics → Connected APIs → **Test** the SMS and the email.
2. **Passenger** `juandc` → Profile → Alerts → turn on *Text me* and *Email me*. Use your own phone and email on that account.
3. **Track a Jeepney:** see each route's landmarks A → B and the bus road lines. **Use my location** shows the nearest jeepney.
4. **Reload → Maya Checkout.** Pay with the sandbox Visa `4123 4501 3100 1381` (12/2030, CVV 123, password `mctest1`).
5. **Driver** `ramon` scans the passenger's QR → "Fare paid" text and email.
6. Passenger Home → **Where are you getting off?** → pick a landmark. Driver Home → **GPS simulator** → *Drive here* until the "Arriving at …" text comes.
7. **Forgot your password?** on the login page → the email arrives through the Gmail API.


## Folders

```
smartride/
  index.php            sends you to controllers/home.php
  config/              config.php (settings), keys.php (your API keys, not on GitHub),
                       keys.example.php (empty copy for GitHub), db_connect.php
  includes/
    functions.php      e(), peso(), flash(), validate_user(), error page
    auth.php           sessions, login/logout, require_role(), CSRF
    http.php           api_request(): one cURL function for every API
    geoapify.php       Geoapify: landmarks, bus route lines, street names, walking routes, IP location
    maya.php           Maya Checkout: wallet reloads
    philsms.php        PhilSMS: text messages
    gmail.php          Gmail API: emails
    onesignal.php      OneSignal: push notifications
    outbox.php         sends texts, emails and pushes after the page has answered
    icons.php
  models/              all the SQL, as plain functions
    user  wallet  fare  trip  fleet  report  log  payment  notification  geo  route  reset
  controllers/         one file per page (the URLs you open)
    api_*.php          JSON for the JavaScript on the pages
    maya_webhook.php   Maya calls this when a payment changes
  views/               the HTML
  assets/              css, js, images, libraries
  OneSignalSDKWorker.js   the service worker OneSignal needs for push
  database/            smartride_db.sql
```

`config`, `includes`, `models`, `views` and `database` are blocked from the browser with `.htaccess`.

## New pages and files

| File | What it does |
| --- | --- |
| `controllers/api_routes.php` | Geoapify: every route's landmarks A → B and bus road line |
| `controllers/api_geo.php` | Geoapify: street name, walking route, IP location |
| `controllers/passenger_pay.php` | the result of a Maya Checkout; checks with Maya before crediting |
| `controllers/api_payment_status.php` | asks Maya whether a reload is paid |
| `controllers/maya_webhook.php` | Maya says a payment changed; we check with Maya ourselves |
| `controllers/passenger_alerts.php` | saves the "Text me" / "Email me" switches |
| `controllers/forgot_password.php`, `reset_password.php` | forgot password by email (Gmail API) |
| `controllers/notifications.php`, `api_notifications.php` | your alerts list, and the bell in the sidebar |
| `models/route_model.php` | landmarks, saved route lines, the passenger's drop-off |
| `models/reset_model.php` | one-time reset links, stored as hashes |
| `models/payment_model.php` | Maya reloads, credited once |

The database has these tables for the new features: `route_stops`, `route_paths`, `password_resets`, `payments`, `notifications` and `geo_cache`. `users` has `sms_alerts` and `email_alerts`, and `fare_transactions` has `dest_stop_id` and `arrival_sent_at`.


## Settings (`config/config.php`)

- `APP_DEBUG`: keep it `false` when presenting.
- `CURL_CA_FILE`: see above. `ARRIVAL_RADIUS_M`: how close (300 m) before the "arriving" text.
- `QR_WINDOW`: seconds before a ride QR changes (30).
- `RELOAD_MIN` / `RELOAD_MAX`: ₱20 to ₱5,000.

## Notes

- Cash reloads (partner store, kiosk) are approved right away in the demo.
- The onboard camera count is still simulated with the **Someone got on / got off** buttons.
- The maps and the five APIs need internet. Demo mode works offline.
- Needs PHP 8.0+ with `mysqli` and `curl`. XAMPP has both on by default.
