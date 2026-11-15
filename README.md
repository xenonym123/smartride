# SmartRide: Integrated Public Transport and Cashless Payment System

This used to be 1TAP. It's the same e-jeepney system for Bacolod: QR fares, a live passenger count, GPS tracking. Now it connects to five outside APIs, each with its own key:

1. **Geoapify:** the walking route to where you get on a line, street names, location by IP, and landmarks + bus road lines for routes the admin adds.
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

## Put it online (HelioHost)

### Before you upload

These matter because the site will be public, and this GitHub repo is public too.

1. **Passwords.** The demo passwords are written in this README, and the password hashes are in `smartride_db.sql`, so anyone can read them. After importing (step 5 below), log in as **admin**, open **User Management**, and give `admin` and every account you'll use (for example `juandc`, `ramon`) a new password. Then switch off every other account in phpMyAdmin → SQL:
   ```sql
   UPDATE users SET status = 'inactive' WHERE username NOT IN ('admin', 'juandc', 'ramon');
   ```
2. **`PRESENTATION_TOOLS`** in `config/config.php`: keep it `true` for the presentation (simulated jeepneys, "Move to a landmark"). Set it to `false` as soon as real riders use the site.
3. **`APP_DEBUG`** stays `false`, so visitors never see error details.
4. **Maya** uses the public sandbox keys, so no real money moves. That's fine for a school project. Real payments need your own Maya business account.

What's already in place: login limit (5 wrong passwords = 15-minute lock), cash reloads only count after an admin confirms them, every form has a CSRF token, every SQL query uses `bind_param`, every page escapes what it shows, passwords are hashed, the session cookie is `httponly` (and `secure` on https), `config`, `includes`, `models`, `views` and `database` are blocked from the web, and `README.md`, `.sql` files and folder listings are never served.

### Steps

1. **Log in** to HelioHost and open **Plesk**.
2. **PHP version:** Websites & Domains → your domain → **PHP** → pick **8.1 or newer**. (`mysqli` and `curl` are on by default.)
3. **Make the database:** Websites & Domains → **Databases** → **Add Database**. Give it a name and a database user with a strong password. Write down the database name, user name and password (HelioHost may put your username in front of the names).
4. **Get the SQL file ready:** in VS Code, copy `database/smartride_db.sql` to a new file (for example `smartride_heliohost.sql`) and **delete these three lines** near the top. HelioHost doesn't let a SQL file make or delete databases:
   ```sql
   DROP DATABASE IF EXISTS smartride_db;
   CREATE DATABASE smartride_db CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
   USE smartride_db;
   ```
5. **Import it:** Databases → your database → **phpMyAdmin** → **Import** → pick `smartride_heliohost.sql` → **Import**.
6. **Upload the site:** on your laptop, zip the *contents* of the `smartride` folder **without** `.git` and `database`. In Plesk → **Files**, open your site's folder (`httpdocs` for the main domain, or `smartride.helioho.st` for an addon domain), upload the zip and click **Extract**. `index.php` should end up directly in that folder.
7. **Database settings:** in Files, open `config/config.php` and set:
   ```php
   $db_host = 'localhost';
   $db_user = 'the database user from step 3';
   $db_pass = 'its password';
   $db_name = 'the database name from step 3';
   ```
8. **API keys:** upload your `config/keys.php` into the `config` folder. It isn't in the zip from GitHub, and it must never go to GitHub.
9. **https:** Websites & Domains → **SSL/TLS Certificates**: install the free Let's Encrypt certificate if there isn't one yet. Then **Hosting Settings** → tick **Permanent SEO-safe 301 redirect from HTTP to HTTPS**. Phones only allow location, camera and push on https.
10. **OneSignal:** in your OneSignal app, change the **Site URL** to `https://smartride.helioho.st` (your real address).
11. **Check it:** open the site, log in as admin → **Analytics → Connected APIs** and press each **Test** button. Then do step 1 of "Before you upload" (passwords) if you haven't yet.

If a page shows **500 Internal Server Error** right after uploading, open `.htaccess` in the main folder and delete the line `Options -Indexes` (some servers don't allow it).

**Gmail:** while your Google project is in Testing, the refresh token stops working after 7 days. Make a new one (see `APIS.md`) the day before you present.

### Updating an existing local database

If your XAMPP database is older than this version, re-import `smartride_db.sql`, or run:
```sql
ALTER TABLE fare_transactions ADD alighted_at DATETIME NULL AFTER arrival_sent_at;
ALTER TABLE payments MODIFY provider ENUM('maya','demo','store','kiosk') NOT NULL;
CREATE TABLE route_guidance (
  route_id SMALLINT UNSIGNED NOT NULL, guide MEDIUMTEXT NOT NULL, updated_at DATETIME NOT NULL,
  PRIMARY KEY (route_id),
  CONSTRAINT fk_guidance_route FOREIGN KEY (route_id) REFERENCES routes (route_id) ON DELETE CASCADE
) ENGINE=InnoDB;
```

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
- Any key left empty switches that feature off (Maya Checkout disappears, texts and emails aren't sent), so the site never breaks.
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
3. **Track a Jeepney:** Bacolod's 24 LPTRP jeepney lines, closest to you first. Pick a line and only that line and its jeepneys show; pick another and the first disappears. The blue dot is you.
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

- **Jeepney lines:** the 24 lines of Bacolod's Local Public Transport Route Plan, traced on the roads. Road lines © OpenStreetMap contributors (ODbL), from the [DASIG Bacolod route pack](https://github.com/andrewnyu/dasig-offline-transit). Lines 1–3, 5–11 and 16 are mapped street by street; the rest are traced from the LPTRP landmarks. Details in `APIS.md`.
- **Simulated jeepneys:** while `PRESENTATION_TOOLS` is `true` in `config/config.php`, each line has 2 simulated units that drive it by themselves (48 in total). They take no fares. Set it to `false` before going live; the real units are BCN 105–109.

- **Driver navigation:** drivers on a trip open **Navigate** for turn-by-turn voice directions along their line ("In 250 meters, turn left onto Lacson Street"), with a pop-up for each turn and landmark. Details in `APIS.md`.
- **Vibration:** when the "Arriving at …" alert comes, the passenger's phone vibrates (long, short, long), and so does the driver's for "Stop at …". It works in Android browsers while SmartRide is open, after the person has tapped the page once (browsers require that). iPhones don't allow web pages to vibrate. Those two alerts are also sent as urgent pushes, which phones show (and vibrate for) right away, even when SmartRide is closed.
- **Riding the same jeepney again:** after **I got off**, the next scan on that trip is a new ride and a new fare. Without the tap, a second scan within 10 minutes is "already paid" (the same QR shown twice); later than that, the old ride is closed and the new one charged. Change the 10 minutes with `RIDE_AGAIN_AFTER_MIN` in `config/config.php`.
- Cash reloads (partner store, kiosk) give the passenger a reference like `C-4F2A91BE`. The money lands when an admin presses **Cash received** on the Analytics page. Requests nobody confirms expire after 24 hours.
- The onboard camera count is still simulated with the **Someone got on / got off** buttons.
- The maps and the five APIs need internet. Without it, the rest of SmartRide still works.
- Needs PHP 8.0+ with `mysqli` and `curl`. XAMPP has both on by default.
