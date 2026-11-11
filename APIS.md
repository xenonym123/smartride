# The five APIs in SmartRide

| API | What SmartRide uses it for | Key(s) in `config/keys.php` | Where to get it |
| --- | --- | --- | --- |
| **Geoapify** | Route landmarks A→B, bus road lines, street names, walking route, location by IP | `GEOAPIFY_API_KEY` | myprojects.geoapify.com. Free, no card. |
| **Maya Checkout** | Wallet reloads (sandbox, test only, no real money) | `MAYA_PUBLIC_KEY`, `MAYA_SECRET_KEY` | Maya's public sandbox keys are already filled in. Your own: Maya Business Manager sandbox. |
| **PhilSMS** | Texts: "fare paid" and "arriving near your stop" | `PHILSMS_API_TOKEN`, `PHILSMS_SENDER_ID` | dashboard.philsms.com → Developers |
| **Gmail API** | Forgot-password emails and payment receipts | `GMAIL_CLIENT_ID`, `GMAIL_CLIENT_SECRET`, `GMAIL_REFRESH_TOKEN`, `GMAIL_SENDER` | Google Cloud (steps below) |
| **OneSignal** | Push pop-ups on phones and computers | `ONESIGNAL_APP_ID`, `ONESIGNAL_REST_API_KEY` | onesignal.com → Web app → Custom Code |

**Where the keys live.** All keys go in `config/keys.php`. `config/config.php` reads that file at line 24. `.gitignore` keeps `keys.php` off GitHub, so a fresh copy from GitHub starts with `config/keys.example.php` instead: copy it to `keys.php` and fill it in.

**When a key is missing.** That API runs in **demo mode**, with a "Demo mode" label on screen, so the site never breaks.

**One function for every call.** All five APIs go through `api_request()` in `includes/http.php` (line 12).

**The admin card.** Admin → Analytics → **Connected APIs** shows which keys are set (never the keys themselves). It has **Test** buttons for push, SMS and email, and each test goes to the admin's own account.

**Why texts and emails don't slow anything down.** Texts, emails and pushes go into an **outbox** (`includes/outbox.php`). They're sent *after* the page has answered the browser (line 28 sends `Content-Length`, then the queue goes out). So the driver's scanner shows "Paid" right away, even if PhilSMS takes a second or two.

---

## 1. Geoapify: route landmarks and bus routes

Every route now has **landmarks from A to B**. For example, Libertad – SM Bacolod is:

> **A** Libertad Public Market → Bacolod City Hall → Bacolod Public Plaza → San Sebastian Cathedral → **B** SM City Bacolod

| What | File | Line |
| --- | --- | --- |
| Key goes on every request as `?apiKey=` | `includes/geoapify.php` | 21–23 |
| **Forward geocoding:** landmark name → position, only within 15 km of Bacolod | `includes/geoapify.php` | 123, 127 |
| **Routing, `mode=bus`:** A → every landmark → B, following roads | `includes/geoapify.php` | 140, 146 |
| Route line saved in `route_paths`, so each route is fetched once | `includes/geoapify.php` | 164, 166 |
| JSON for the maps: every route with landmarks and line | `controllers/api_routes.php` | 14 |
| Lines and A/B markers drawn on the map | `assets/js/app.js` | 259 |
| Passenger Track page: routes on the map, plus a list | `views/passenger/track.php` | 103 |
| Admin: add a landmark (Geoapify finds it by name), remove one, "Update from Geoapify" | `controllers/admin_tracking.php` | 79, 91, 115 |
| Reverse geocoding (street names), walking route, IP location | `includes/geoapify.php` | 28, 53, 82 |

**About the starting positions.** The demo landmarks start with approximate positions. The first time a map loads with your key, Geoapify looks every landmark up **by name** and saves the real position (`geocoded = 1`). Then it asks for the bus route between them. To redo it, click Admin → Jeepney Tracking → **Update from Geoapify**.

---

## 2. PhilSMS: "fare paid" and "arriving at your stop"

| What | File | Line |
| --- | --- | --- |
| Bearer token, `639…` number, sender id, `plain` | `includes/philsms.php` | 31, 39 |
| `0917…` → `63917…` | `includes/philsms.php` | 18 |
| Only fare paid + arriving, and only if "Text me" is on | `models/notification_model.php` | 33 |
| Passenger turns "Text me" on (Profile → Alerts) | `controllers/passenger_alerts.php` | 28 |
| Passenger picks their drop-off landmark (Home) | `controllers/passenger_home.php` | 23 |
| Only a landmark on the route they're riding is accepted | `models/route_model.php` | 122 |
| Every driver GPS ping: rider within 300 m of their stop? → text | `controllers/api_gps.php` | 34–36 |
| Text goes out only once per ride | `models/route_model.php` | 148 |
| GPS simulator for the demo (drive to a landmark) | `views/driver/home.php` | 42 |

**How "arriving" works:**

1. The passenger pays (QR scan).
2. Home shows "You're riding BCN 105 · Libertad – SM Bacolod. Where are you getting off?" They pick **SM City Bacolod**.
3. The driver's phone sends its GPS every 20 seconds. When the jeepney is within **300 m** (`ARRIVAL_RADIUS_M`, `config.php` line 51), the passenger gets: *"SmartRide: Arriving at SM City Bacolod. BCN 105 is about 250 m away. Get ready to get off."*

Texts are **off until the passenger turns them on**. Every text uses PhilSMS credits, and the demo accounts have made-up numbers that might belong to real people.

---

## 3. Maya Checkout (sandbox), which replaces PayMongo

| What | File | Line |
| --- | --- | --- |
| Basic auth: key + `:` | `includes/maya.php` | 25–27 |
| **Create checkout** with the **public** key: amount, reference number, return links | `includes/maya.php` | 31, 37 |
| Passenger goes to Maya's page | `controllers/passenger_reload.php` | 43, 62 |
| **Check the payment** with the **secret** key, by reference number | `includes/maya.php` | 67, 69 |
| Only `PAYMENT_SUCCESS` for the **right amount** counts | `includes/maya.php` | 80 |
| Pay page, status check and webhook all ask Maya the same way | `models/payment_model.php` | 48 |
| Wallet credited once only (`WHERE status = 'pending'`) | `models/payment_model.php` | 85, 93 |
| Webhook: takes only the reference number, then asks Maya | `controllers/maya_webhook.php` | 21, 26 |
| "Simulate payment" works only in demo mode | `controllers/passenger_pay.php` | 33 |

**Security points.** Maya's webhooks aren't signed, so we **never trust the message or the "success" redirect**. A fake webhook or a typed-in `?result=success` URL can't add money, because SmartRide always asks Maya with the secret key.

**Sandbox test accounts** (also shown on the pay page):
- **Visa** `4123 4501 3100 1381`: expiry 12/2030, CVV 123, 3-D Secure password `mctest1`.
- **Maya wallet** `+639900100900`: password `Password@1`, OTP `123456`.

The shared sandbox keys are public, so other people may be testing with them at the same time.

---

## 4. Gmail API: forgot password and payment receipts

| What | File | Line |
| --- | --- | --- |
| Refresh token → access token (OAuth) | `includes/gmail.php` | 21 |
| Send: RFC 2822 email, base64url, `users/me/messages/send` | `includes/gmail.php` | 36, 50 |
| Same answer whether or not the email exists | `controllers/forgot_password.php` | 28 |
| At most 3 links an hour per account | `controllers/forgot_password.php` | 30 |
| Random 64-character token; only its **SHA-256 hash** is saved | `models/reset_model.php` | 7, 10 |
| Link works once, for 30 minutes | `models/reset_model.php` | 29, 41 |
| New password saved with `password_hash` | `controllers/reset_password.php` | 33 |
| Fare and reload receipts, only if "Email me" is on | `models/notification_model.php` | 38 |

**Setting up the Gmail API** (about 15 minutes, one time):

1. Go to <https://console.cloud.google.com>. Create a project, then **APIs & Services → Library → Gmail API → Enable**.
2. **OAuth consent screen:** External, Testing. Add your own Gmail as a **test user**.
3. **Credentials → Create credentials → OAuth client ID → Web application.** Add the redirect URI `https://developers.google.com/oauthplayground`. Copy the **client ID** and **client secret**.
4. Open <https://developers.google.com/oauthplayground>. Click ⚙ → **Use your own OAuth credentials**, and paste them in.
5. In the scope box, type `https://www.googleapis.com/auth/gmail.send`, then **Authorize APIs** and sign in with that Gmail.
6. Click **Exchange authorization code for tokens** and copy the **refresh token**.
7. Put the client ID, client secret, refresh token and your Gmail address (`GMAIL_SENDER`) in `config/keys.php`.

While the Google project is in **Testing**, the refresh token stops working after **7 days**. Get a new one (steps 4–6) the day before you present.

**If the email doesn't arrive**, look for a line starting with `SmartRide Gmail:` in `C:\xampp\apache\logs\error.log` (or `C:\xampp\php\logs\php_error_log`). It now quotes Google's own reason. The usual ones:
- `HTTP 403 Gmail API has not been used in project … or it is disabled`: step 1 was skipped, or done in a different project. Enable the Gmail API, wait 2–3 minutes, try again.
- `HTTP 403 Request had insufficient authentication scopes`: the refresh token wasn't made with `gmail.send`. Redo steps 5–6.
- `HTTP 400 invalid_grant`: the refresh token expired (7 days) or was copied wrong. Redo steps 4–6.
- No line at all: the email you typed isn't on any SmartRide account, so nothing was sent (on purpose, see above).

**Without Gmail keys (demo mode):** the reset link is written to the PHP error log (`C:\xampp\php\logs\php_error_log`), never shown on screen.

---

## 5. OneSignal: push notifications

The same as before: `includes/onesignal.php` sends the push at line 30, and the SDK and `OneSignal.login` are set up in `views/partials/dashboard_bottom.php` (lines 10, 14 and 20). Pushes now go out through the outbox too.

---

## Demo script

1. **Admin** → Analytics → **Connected APIs**: five tags. Press **Test** on PhilSMS and Gmail and you get a text and an email.
2. **Passenger** `juandc` → Profile → **Alerts** → turn on *Text me* and *Email me*. Use your own phone and email on that account.
3. **Track a Jeepney:** the four routes with their landmarks A → B and bus road lines from Geoapify.
4. **Reload → Maya Checkout ₱100.** Pay on Maya's sandbox page with the test Visa. Back on SmartRide it says "Paid", and an email receipt arrives.
5. **Driver** `ramon` scans juandc's QR → "Fare paid" text + email.
6. juandc's Home → **Where are you getting off?** → pick the last landmark (B).
7. Driver Home → **GPS simulator** → *Drive here*, landmark by landmark. Near B, juandc gets the "Arriving at …" text.
8. Sign out → **Forgot your password?** → the email arrives → set a new password.
