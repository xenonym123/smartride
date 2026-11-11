<?php
// Copy this file to config/keys.php and put your own keys in it.
// config/keys.php is listed in .gitignore, so git never uploads it.
// Leave a key as '' and that feature runs in demo mode.

// 1. Geoapify: myprojects.geoapify.com > new project > API key (free, no card)
define('GEOAPIFY_API_KEY', '');

// 2. Maya Checkout SANDBOX (test only, no real money).
//    These are Maya's shared public sandbox keys from developers.maya.ph
//    ("Sandbox Party 1"). They're public, so other people may be testing with them too.
//    Your own sandbox keys: sign up for Maya Business Manager (sandbox).
define('MAYA_PUBLIC_KEY', 'pk-Z0OSzLvIcOI2UIvDhdTGVVfRSSeiGStnceqwUE7n0Ah');
define('MAYA_SECRET_KEY', 'sk-X8qolYjy62kIzEbr0QRK1h4b4KDVHaNcwMYk39jInSl');

// 3. OneSignal: onesignal.com > your app > Settings > Keys & IDs
define('ONESIGNAL_APP_ID', '');
define('ONESIGNAL_REST_API_KEY', '');

// 4. PhilSMS: app.philsms.com > Developers > API token. Each text uses credits.
define('PHILSMS_API_TOKEN', '');
define('PHILSMS_SENDER_ID', 'PhilSMS');   // your approved sender name (max 11 characters)

// 5. Gmail API (OAuth). Steps in APIS.md: Google Cloud project > enable Gmail API >
//    OAuth client > refresh token from the OAuth Playground (scope gmail.send).
define('GMAIL_CLIENT_ID', '');
define('GMAIL_CLIENT_SECRET', '');
define('GMAIL_REFRESH_TOKEN', '');
define('GMAIL_SENDER', '');               // the Gmail address the emails come from
