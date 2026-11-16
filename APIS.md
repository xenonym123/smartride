# The five APIs in SmartRide

| API | What SmartRide uses it for | Key(s) in `config/keys.php` | Where to get it |
| --- | --- | --- | --- |
| **Geoapify** | Route landmarks A→B, bus road lines, street names, walking route, location by IP | `GEOAPIFY_API_KEY` | myprojects.geoapify.com. Free, no card. |
| **Maya Checkout** | Wallet reloads (sandbox, test only, no real money) | `MAYA_PUBLIC_KEY`, `MAYA_SECRET_KEY` | Maya's public sandbox keys are already filled in. Your own: Maya Business Manager sandbox. |
| **PhilSMS** | Texts: "fare paid" and "arriving near your stop" | `PHILSMS_API_TOKEN`, `PHILSMS_SENDER_ID` | dashboard.philsms.com → Developers |
| **Gmail API** | Forgot-password emails and payment receipts | `GMAIL_CLIENT_ID`, `GMAIL_CLIENT_SECRET`, `GMAIL_REFRESH_TOKEN`, `GMAIL_SENDER` | Google Cloud (steps below) |
| **OneSignal** | Push pop-ups on phones and computers | `ONESIGNAL_APP_ID`, `ONESIGNAL_REST_API_KEY` | onesignal.com → Web app → Custom Code |

**Where the keys live.** All keys go in `config/keys.php`. `config/config.php` reads that file at line 24. `.gitignore` keeps `keys.php` off GitHub, so a fresh copy from GitHub starts with `config/keys.example.php` instead: copy it to `keys.php` and fill it in.

**When a key is missing.** That feature switches itself off (Maya Checkout disappears from Reload, texts and emails aren't sent) and the rest of SmartRide keeps working. Admin → Connected APIs shows "Not set up".

**One function for every call.** All five APIs go through `api_request()` in `includes/http.php` (line 12).

**The admin card.** Admin → Analytics → **Connected APIs** shows which keys are set (never the keys themselves). It has **Test** buttons for push, SMS and email, and each test goes to the admin's own account.

**Why texts and emails don't slow anything down.** Texts, emails and pushes go into an **outbox** (`includes/outbox.php`). They're sent *after* the page has answered the browser (line 28 sends `Content-Length`, then the queue goes out). So the driver's scanner shows "Paid" right away, even if PhilSMS takes a second or two.

---

## 1. Jeepney lines (Bacolod LPTRP) and Geoapify

**Track a Jeepney** shows Bacolod's **24 jeepney lines** from the Local Public Transport Route Plan (LPTRP), each in its own color, like lugar.ph:

- At first every line is on the map, and the panel lists them **closest to you first** ("10 m from you").
- **Pick a line** and only that line, its landmarks (A → B) and **its jeepneys** stay on the map. Pick another and the first one disappears. The address remembers it (`#line-6`), so you can share a link to a line.
- The **blue dot** is you: the device's own location (GPS on a phone), followed as you move, with a circle showing how accurate it is. A rough reading never replaces a sharper one, and the internet-connection guess is never used for the dot. If the device only knows a rough spot (a laptop without GPS), the panel says so.
- **Where to get on:** SmartRide finds the closest point of the picked line and Geoapify draws the walking route there.

| What | File | Line |
| --- | --- | --- |
| The 24 lines, landmarks and road lines (seed data) | `database/smartride_db.sql` | `-- routes: the 24 jeepney lines` |
| JSON: every line with color, landmarks, road line | `controllers/api_routes.php` | 13 |
| The line-picker map (all lines → one line + its jeepneys, your location) | `assets/js/app.js` | 128 |
| Demo jeepneys that drive their line by themselves | `models/fleet_model.php` | 104 |
| Closest point of a line to you | `models/fleet_model.php` | 187 |
| **Geoapify walking route** to that point | `controllers/api_geo.php` | 54, 78 |
| Key goes on every request as `?apiKey=` | `includes/geoapify.php` | 21–23 |
| **Forward geocoding:** landmark name → position, only within 15 km of Bacolod | `includes/geoapify.php` | 123 |
| **Routing, `mode=bus`:** for a route the admin adds, A → every landmark → B | `includes/geoapify.php` | 140, 164 |
| Admin: add a landmark (Geoapify finds it by name), remove one, "Update from Geoapify" | `controllers/admin_tracking.php` | 82, 110, 118 |
| Reverse geocoding (street names), IP location | `includes/geoapify.php` | 28, 82 |

**Where the lines come from.** The road lines are from the [DASIG Bacolod route pack](https://github.com/andrewnyu/dasig-offline-transit), built from OpenStreetMap. Lines 1–3, 5–11 and 16 are **mapped street by street** on OpenStreetMap (`source = 'osm'`). The other 13 are **traced from the LPTRP landmarks** along OpenStreetMap roads (`source = 'lptrp'`), so they're a best estimate. Riders should still check the signboard. Each landmark sits on its line's road. Route data © OpenStreetMap contributors, under the Open Database License (ODbL); the credit is on the map and on the page.

**Simulated jeepneys.** While `PRESENTATION_TOOLS` is `true` in `config/config.php`, each line has 2 simulated units (`jeepneys.simulated = 1`, 48 in total) that drive their line at 15 km/h. They have no driver and take no fares; admins see them tagged "Simulated". Set `PRESENTATION_TOOLS` to `false` for a live service: only real units (BCN 105–109 and any you add) stay on the map, moving when a driver's phone sends GPS.

**Geoapify and the traced lines.** The 24 traced lines never call Geoapify routing. Adding or removing a landmark on one keeps its road line. A route the admin **adds** gets its landmarks found by name and its bus road line from Geoapify, as before.

---

## 1b. Driver navigation: turn-by-turn voice directions

Drivers on a trip get **Navigate** in their menu. It follows the line of the trip:

- A big banner shows the next instruction and its distance ("250 m · Turn left onto San Juan Street"), and the one after it.
- A **voice** says it twice: about 250 m before ("In 250 meters, turn left onto San Juan Street.") and at the turn. Landmarks are announced too, with how many riders get off there ("Approaching SM City Bacolod. 2 riders are getting off here."). The voice is the phone's own (Web Speech API), so it needs no internet and no key.
- Each instruction also **pops up** at the bottom of the screen. If the jeepney leaves the line, it says "You're off the route."
- The screen stays on while navigating (Screen Wake Lock), and the phone's GPS keeps going to passengers as usual.
- **Simulate drive** (only while `PRESENTATION_TOOLS` is on) drives the line at 1×, 5× or 10× speed for a presentation, with the voice, and sends the jeepney's position, so passengers see it move and get their "arriving" alert.

| What | File | Line |
| --- | --- | --- |
| Turns found from the line's shape (direction 25 m before vs after; ≥ 30° = a turn) | `includes/guidance.php` | 53 |
| **Geoapify reverse geocoding, `type=street`**: the street after each turn | `includes/guidance.php` | 117 |
| Saved per line; street names filled in a few seconds at a time, turns ahead first | `includes/guidance.php` | 198 |
| JSON for the Navigate screen | `controllers/api_navigation.php` | 1 |
| Following the jeepney along the line, banner, voice, pop-ups, simulation | `assets/js/app.js` | 129 |

Without a Geoapify key, the directions still work: "Turn left", "Turn right", landmarks, just without street names. Gentle bends that stay on the same street aren't announced.

## 2. PhilSMS: "fare paid" and "arriving at your stop"

| What | File | Line |
| --- | --- | --- |
| Bearer token on every request | `includes/philsms.php` | 50 |
| `639…` number, `plain`, sender ID (left out if empty, so PhilSMS uses the account's default) | `includes/philsms.php` | 76–82 |
| `0917…` → `63917…` | `includes/philsms.php` | 36 |
| PhilSMS can say "error" inside an HTTP 200, so the answer's `status` is checked too | `includes/philsms.php` | 57 |
| Credits left (`/balance`) | `includes/philsms.php` | 92 |
| Admin **Test** sends right away and shows PhilSMS's answer on screen | `controllers/admin_home.php` | 30 |
| Only fare paid + arriving, and only if "Text me" is on | `models/notification_model.php` | 33 |
| Passenger turns "Text me" on (Profile → Alerts) | `controllers/passenger_alerts.php` | 28 |
| Passenger picks their drop-off landmark (Home) | `controllers/passenger_home.php` | 23 |
| Only a landmark on the route they're riding is accepted | `models/route_model.php` | 122 |
| Every driver GPS ping: rider within 300 m of their stop? → text | `controllers/api_gps.php` | 34–36 |
| Text goes out only once per ride | `models/route_model.php` | 148 |
| "Move to a landmark" for presentations (only while `PRESENTATION_TOOLS` is on) | `views/driver/home.php` | 43 |

**How "arriving" works:**

1. The passenger pays (QR scan).
2. Home shows "You're riding BCN 105 · Banago – Libertad Loop. Where are you getting off?" They pick **Libertad Public Market**.
3. The driver's phone sends its GPS every 20 seconds. When the jeepney is within **300 m** (`ARRIVAL_RADIUS_M`, `config.php` line 51), the passenger gets: *"SmartRide: Arriving at SM City Bacolod. BCN 105 is about 250 m away. Get ready to get off."*

**Sender ID.** PhilSMS only sends from a sender name approved for your account (dashboard.philsms.com → Sending → **Sender ID** → Request for new one). Until one is approved, set `PHILSMS_SENDER_ID` to `''` and PhilSMS uses the account's default, if it has one. If the Test button says *Sender ID "…" is not authorized*, that's this.

**Token.** Make the token on **dashboard.philsms.com**. A token from app.philsms.com is *Unauthenticated.* here (set `PHILSMS_API` in `keys.php` if your account is on app.philsms.com).

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

**Without Gmail keys:** no email is sent; the reset link is written to the PHP error log (`C:\xampp\php\logs\php_error_log`), never shown on screen.

---

## 5. OneSignal: push notifications

The same as before: `includes/onesignal.php` sends the push at line 30, and the SDK and `OneSignal.login` are set up in `views/partials/dashboard_bottom.php` (lines 10, 14 and 20). Pushes now go out through the outbox too.

---

## Demo script

1. **Admin** → Analytics → **Connected APIs**: five tags. Press **Test** on PhilSMS and Gmail and you get a text and an email.
2. **Passenger** `juandc` → Profile → **Alerts** → turn on *Text me* and *Email me*. Use your own phone and email on that account.
3. **Track a Jeepney:** the 24 lines, closest first. Pick a line: only it and its jeepneys show, plus where to get on (Geoapify walking route). Pick another line and the first one goes away.
4. **Reload → Maya Checkout ₱100.** Pay on Maya's sandbox page with the test Visa. Back on SmartRide it says "Paid", and an email receipt arrives.
5. **Driver** `ramon` scans juandc's QR → "Fare paid" text + email.
6. juandc's Home → **Where are you getting off?** → pick the last landmark (B).
7. Driver Home → **GPS simulator** → *Drive here*, landmark by landmark. Near B, juandc gets the "Arriving at …" text.
8. Sign out → **Forgot your password?** → the email arrives → set a new password.
