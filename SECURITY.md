# Where the security is in SmartRide

One section per member. Line numbers match the files in this zip. If you edit a file, they'll move a little.

The three APIs (Geolocation, PayMongo, Notifications) have their own map in `APIS.md`. Their security points are listed there too: the webhook signature, the secret key staying on the server, and each wallet getting credited only once.

---

## Member 1 — Password hashing and verification

**Where to look**

| What | File | Line |
| --- | --- | --- |
| Password hashed before saving (sign up, admin adds user) | `models/user_model.php` | 37 |
| Password hashed on reset / change | `models/user_model.php` | 61 |
| Typed password checked against the hash at login | `controllers/login.php` | 33 |
| Current password checked before a profile change | `controllers/passenger_profile.php` | 22 |
| At least 8 characters | `includes/functions.php` | 234 |
| Same error for a wrong username and a wrong password | `controllers/login.php` | 36 |

**What to say**

We never store the real password. `password_hash()` turns it into a bcrypt hash (starts with `$2y$`) with a random salt, so two people with the same password still get different hashes. At login, `password_verify()` compares what you typed to that hash.

Our old `auth.php` said "Invalid password" for one case and "User not found" for the other. That tells an attacker which usernames are real. Now both say "Wrong username or password."

**Demo**

Open phpMyAdmin → `users` → `password_hash` column. Nothing readable, just `$2y$10$...`. Then sign in with a wrong password and with a made-up username. Same message both times.

---

## Member 2 — MySQLi and SQL injection

**Where to look**

| What | File | Line |
| --- | --- | --- |
| MySQLi connection | `config/db_connect.php` | 10 |
| Failed queries throw errors instead of failing quietly | `config/db_connect.php` | 7 |
| Prepared statements with `bind_param` (every model works like this) | `models/user_model.php` | 10, 20, 29, 40, 48 |
| Search with optional filters, still bound | `models/user_model.php` | 104 |
| `IN (?, ?, ?)` built with one `?` per value | `models/log_model.php` | 45, 61 |
| Fare taken only if the balance covers it, in the same query | `models/wallet_model.php` | 109 |
| Transaction: balance, fare and count saved together or not at all | `controllers/api_scan.php` | 64 |

**What to say**

There's no SQL in the controllers or views. It's all in `models/`, and every query is a prepared statement. The `?` goes into the SQL, and the real value is sent separately with `bind_param("si", $username, $id)`. The `"s"` means string and the `"i"` means integer. Whatever the user types gets treated as data, never as SQL, so `' OR '1'='1` is just a weird username that doesn't exist.

**Demo**

Type `' OR '1'='1' -- ` as the username and anything as the password. You get "Wrong username or password." Then try it in the admin user search. You get "No accounts match."

---

## Member 3 — Sessions, logout and access control

**Where to look**

| What | File | Line |
| --- | --- | --- |
| Cookie settings: HttpOnly, SameSite, strict mode | `includes/auth.php` | 7–10 |
| New session id on login | `includes/auth.php` | 17 |
| Logout: `session_unset()`, `session_destroy()`, new id | `includes/auth.php` | 31–36 |
| Logout only from our own button (POST + token) | `controllers/logout.php` | 14 |
| `require_role()`: not signed in → login, wrong role → your own dashboard | `includes/auth.php` | 50–63 |
| Dashboards aren't cached, so Back after logout shows nothing | `includes/auth.php` | 53 |
| Every page starts with `session_start()` and `require_role()` | e.g. `controllers/passenger_reload.php` | 14–15 |
| Deactivated accounts can't sign in | `controllers/login.php` | 40 |
| Sign-up can only make passengers | `controllers/register.php` | 32 |
| Admin can't deactivate or demote themselves | `controllers/admin_users.php` 20, `controllers/admin_user_form.php` 33 |
| CSRF token on every form and every fetch() | `includes/auth.php` 79–106, `views/partials/head.php` 3, `assets/js/app.js` 37 |

**What to say**

The role in the session comes from the database at login, never from the form. In the old site you could post `accountType=admin` to `auth.php` and register yourself as an admin. Now `register.php` always makes a passenger.

`session_regenerate_id(true)` gives you a new session id when you log in and when you log out, so an id someone saw before is useless. Every dashboard page calls `require_role()` first. A passenger who types `admin_home.php` in the address bar just gets sent back to their own home.

The CSRF token is a random secret stored in the session and copied into every form. A fake form on another website can't know it, so the server refuses its request.

**Demo**

1. Sign in as a passenger and type `controllers/admin_home.php` in the address bar. You're bounced back.
2. Log out, then press the browser's Back button. You land on the login page, not the old dashboard.
3. Open DevTools → Application → Cookies. `smartride_session` is HttpOnly, and the value changes after you log in.

---

## Member 4 — XSS, validation and error handling

**Where to look**

| What | File | Line |
| --- | --- | --- |
| `e()` = `htmlspecialchars()`, used on everything we print | `includes/functions.php` | 32–34 |
| Example: user names in the admin table | `views/admin/users.php` | 27 |
| Data inside a `<script>` tag is escaped too | `views/driver/count.php` | 59 |
| JavaScript lists escape names before adding them | `views/passenger/track.php` | 22 |
| Server-side checks: name, email, PH mobile number, username, password | `includes/functions.php` | 216–241 |
| Reload amount and method checked on the server | `controllers/passenger_reload.php` | 30–37 |
| Filters only accept known values | `controllers/admin_users.php` | 36 |
| Friendly page when the database is down, details go to the log | `config/db_connect.php` | 14–16 |
| Friendly page for any other error | `includes/functions.php` | 8–26 |
| Error details hidden unless `APP_DEBUG` is on | `config/config.php` | 12 |

**What to say**

XSS is when someone saves HTML or JavaScript, like a name of `<script>alert(1)</script>`, and the site prints it raw, so it runs in other people's browsers. Every value from the database or a form goes through `e()` before it's printed, which turns `<` into `&lt;`. The browser shows the text and doesn't run it.

The HTML `required` and `min`/`max` can be deleted in DevTools, so the server checks everything again. Bad input sends you back to the form with the error under the right box, and what you typed is still there.

The old `db_connect.php` did `die("Connection failed: " . $conn->connect_error)`, which shows the database error to anyone. Now visitors see "We can't reach the database", and the real reason goes to the PHP error log.

**Demo**

1. As admin, add a user named `<script>alert(1)</script>`. The User Management list shows the text, and no alert pops up.
2. On Reload Wallet, use DevTools to remove `min="20"` and reload ₱5. You get "Reload between ₱20 and ₱5,000."
3. Stop MySQL in XAMPP and reload any page. You see the friendly message, not a PHP error.
