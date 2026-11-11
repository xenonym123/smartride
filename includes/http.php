<?php
// Sends one request to an outside API (Geoapify, Maya, OneSignal, PhilSMS, Gmail)
// with cURL and gives back the answer as a PHP array. Every API goes through here.
//
//   $method  'GET' or 'POST'
//   $url     full address, with ?apiKey=... already on it if the API wants that
//   $headers extra headers, e.g. ['Authorization: Bearer abc123']
//   $body    array to send, or null
//   $as_form true = send $body as a normal form (Google's token page wants that), else JSON
//
// Throws an exception if the API can't be reached or answers with an error.
function api_request($method, $url, $headers = [], $body = null, $timeout = 15, $as_form = false)
{
    $ch = curl_init($url);
    $headers[] = 'Accept: application/json';
    if ($body !== null && $as_form) {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($body));
    } elseif ($body !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    if (CURL_CA_FILE !== '') {
        curl_setopt($ch, CURLOPT_CAINFO, CURL_CA_FILE);
    }

    $raw = curl_exec($ch);
    if ($raw === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception('Could not reach ' . parse_url($url, PHP_URL_HOST) . ': ' . $error);
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = json_decode($raw, true);
    if ($status >= 400 || !is_array($json)) {
        // Each API words its errors differently. Find the human-readable part.
        $detail = '';
        if (isset($json['errors'][0])) {                        // PhilSMS, OneSignal
            $detail = $json['errors'][0]['detail'] ?? $json['errors'][0];
        } elseif (isset($json['error']['message'])) {           // Google APIs (Gmail)
            $detail = $json['error']['message'];
        } elseif (isset($json['error']) && is_string($json['error'])) { // Google sign-in (token)
            $detail = $json['error'] . (isset($json['error_description']) ? ': ' . $json['error_description'] : '');
        } elseif (isset($json['message'])) {                    // Maya, Geoapify
            $detail = $json['message'];
        }
        $detail = is_string($detail) ? $detail : json_encode($detail);
        throw new Exception(parse_url($url, PHP_URL_HOST) . ' said no: HTTP ' . $status . ($detail !== '' ? ' ' . $detail : ''));
    }
    return $json;
}

// Full address of a SmartRide page, e.g. http://localhost/smartride/controllers/passenger_rides.php
// Used for links that leave the site: Maya's return pages, email links, push links.
function app_url($page)
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/' . $page;
}
