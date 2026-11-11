<?php
// Talks to the Geoapify API (geolocation). The key goes in the URL as ?apiKey=...
// and stays on the server, so the browser never sees it.
//
// What SmartRide uses it for:
//   1. Reverse geocoding:  lat/lng  ->  "Lacson Street, Bacolod"
//   2. Routing (walk):     the walking route from the passenger to the nearest jeepney
//   3. IP geolocation:     a rough location when the phone's GPS is blocked
//
// Docs: https://apidocs.geoapify.com

require_once __DIR__ . '/http.php';
require_once __DIR__ . '/../models/geo_model.php';

// True when there's no key, so SmartRide makes a rough answer itself for the demo.
function geoapify_demo_mode()
{
    return GEOAPIFY_API_KEY === '';
}

function geoapify_get($path, $params)
{
    $params['apiKey'] = GEOAPIFY_API_KEY;
    return api_request('GET', GEOAPIFY_API . $path . '?' . http_build_query($params), [], null, 8);
}

// 1. Street name for a point. Saved in geo_cache so the same spot isn't looked up twice.
function geoapify_address($conn, $lat, $lng)
{
    $cached = get_cached_address($conn, $lat, $lng);
    if ($cached !== null) {
        return $cached;
    }
    if (geoapify_demo_mode()) {
        return number_format($lat, 4) . ', ' . number_format($lng, 4);
    }

    $data = geoapify_get('/geocode/reverse', ['lat' => $lat, 'lon' => $lng, 'format' => 'json', 'lang' => 'en']);
    $place = $data['results'][0] ?? null;
    if (!$place) {
        return number_format($lat, 4) . ', ' . number_format($lng, 4);
    }
    // address_line1 is the street or the place name, e.g. "Robinsons Place Bacolod".
    $address = $place['address_line1'] ?? ($place['formatted'] ?? '');
    if (!empty($place['city']) && strpos($address, $place['city']) === false) {
        $address .= ', ' . $place['city'];
    }
    save_cached_address($conn, $lat, $lng, $address);
    return $address;
}

// 2. Walking route. Returns meters, seconds and the points of the line [[lat, lng], ...].
function geoapify_walk_route($from_lat, $from_lng, $to_lat, $to_lng)
{
    if (geoapify_demo_mode()) {
        // Demo: a straight line, made 30% longer for the turns, at 5 km/h.
        $meters = straight_line_km($from_lat, $from_lng, $to_lat, $to_lng) * 1000 * 1.3;
        return ['meters' => round($meters), 'seconds' => round($meters / 1.39),
                'points' => [[$from_lat, $from_lng], [$to_lat, $to_lng]], 'demo' => true];
    }

    $data = geoapify_get('/routing', [
        'waypoints' => $from_lat . ',' . $from_lng . '|' . $to_lat . ',' . $to_lng,
        'mode'      => 'walk',
    ]);
    $route = $data['features'][0] ?? null;
    if (!$route) {
        throw new Exception('Geoapify found no walking route.');
    }
    // The line comes as MultiLineString: [[ [lng, lat], [lng, lat], ... ]]. Leaflet wants [lat, lng].
    $points = [];
    foreach ($route['geometry']['coordinates'] as $line) {
        foreach ($line as $pair) {
            $points[] = [$pair[1], $pair[0]];
        }
    }
    return ['meters' => round($route['properties']['distance']), 'seconds' => round($route['properties']['time']),
            'points' => $points, 'demo' => false];
}

// 3. Rough location from the internet connection's IP address (city level).
function geoapify_ip_location($ip)
{
    if (geoapify_demo_mode()) {
        return ['lat' => 10.6765, 'lng' => 122.9509, 'city' => 'Bacolod (demo)'];
    }
    $params = [];
    // On XAMPP the visitor is often 127.0.0.1 or 192.168.x.x. Those aren't real internet
    // addresses, so leave ip out and Geoapify uses the PC's own public IP.
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        $params['ip'] = $ip;
    }
    $data = geoapify_get('/ipinfo', $params);
    if (!isset($data['location']['latitude'])) {
        throw new Exception('Geoapify could not place this IP address.');
    }
    return [
        'lat'  => (float) $data['location']['latitude'],
        'lng'  => (float) $data['location']['longitude'],
        'city' => $data['city']['name'] ?? ($data['country']['name'] ?? 'your area'),
    ];
}

// Straight-line distance in km (haversine formula). Same math as in app.js.
function straight_line_km($lat1, $lng1, $lat2, $lng2)
{
    $dlat = deg2rad($lat2 - $lat1);
    $dlng = deg2rad($lng2 - $lng1);
    $a = sin($dlat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dlng / 2) ** 2;
    return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
}


// ---------- Route landmarks and bus route lines ----------

require_once __DIR__ . '/../models/route_model.php';

// Bacolod City center, and how far around it to search for landmarks.
define('BACOLOD_LAT', 10.6765);
define('BACOLOD_LNG', 122.9509);

// 4. Forward geocoding: "SM City Bacolod" -> its position. Only looks within 15 km of Bacolod.
function geoapify_find_place($name)
{
    $data = geoapify_get('/geocode/search', [
        'text'   => $name . ', Bacolod City, Negros Occidental, Philippines',
        'filter' => 'circle:' . BACOLOD_LNG . ',' . BACOLOD_LAT . ',15000',
        'bias'   => 'proximity:' . BACOLOD_LNG . ',' . BACOLOD_LAT,
        'limit'  => 1,
        'format' => 'json',
    ]);
    $place = $data['results'][0] ?? null;
    if (!$place || !isset($place['lat'], $place['lon'])) {
        return null;
    }
    return ['lat' => (float) $place['lat'], 'lng' => (float) $place['lon'], 'address' => $place['formatted'] ?? $name];
}

// 5. Routing (bus): the road a bus takes from landmark A, through each landmark, to B.
function geoapify_bus_route($stops)
{
    $waypoints = [];
    foreach ($stops as $stop) {
        $waypoints[] = $stop['latitude'] . ',' . $stop['longitude'];
    }
    $data = geoapify_get('/routing', ['waypoints' => implode('|', $waypoints), 'mode' => 'bus']);
    $route = $data['features'][0] ?? null;
    if (!$route) {
        throw new Exception('Geoapify found no bus route.');
    }
    $points = [];
    foreach ($route['geometry']['coordinates'] as $line) {
        foreach ($line as $pair) {
            $points[] = [round($pair[1], 6), round($pair[0], 6)]; // [lng, lat] -> [lat, lng]
        }
    }
    return ['points' => $points, 'meters' => (int) round($route['properties']['distance']),
            'seconds' => (int) round($route['properties']['time'])];
}

// The line to draw for a route. Saved after the first time, so Geoapify is asked once per route.
// With a key: finds any landmark not looked up yet, then asks for the bus route.
// Without a key (demo) or if Geoapify fails: straight lines from landmark to landmark.
function route_path_for($conn, $route_id)
{
    $saved = get_saved_route_path($conn, $route_id);
    // A straight-line copy is replaced as soon as there's a key.
    if ($saved && !($saved['source'] === 'straight' && !geoapify_demo_mode())) {
        return ['points' => json_decode($saved['points'], true), 'meters' => (int) $saved['meters'],
                'seconds' => (int) $saved['seconds'], 'source' => $saved['source']];
    }

    $stops = get_route_stops($conn, $route_id);
    if (count($stops) < 2) {
        return ['points' => [], 'meters' => 0, 'seconds' => 0, 'source' => 'straight'];
    }

    if (!geoapify_demo_mode()) {
        try {
            foreach ($stops as $i => $stop) {
                if (!$stop['geocoded']) {
                    $found = geoapify_find_place($stop['name']);
                    if ($found) {
                        update_stop_position($conn, $stop['stop_id'], $found['lat'], $found['lng']);
                        $stops[$i]['latitude'] = $found['lat'];
                        $stops[$i]['longitude'] = $found['lng'];
                    }
                }
            }
            $route = geoapify_bus_route($stops);
            save_route_path($conn, $route_id, $route['points'], $route['meters'], $route['seconds'], 'geoapify');
            $route['source'] = 'geoapify';
            return $route;
        } catch (Exception $ex) {
            error_log('SmartRide Geoapify route: ' . $ex->getMessage()); // fall back to straight lines
        }
    }

    // Straight lines between landmarks, about 20 km/h for the time.
    $points = [];
    $meters = 0;
    foreach ($stops as $i => $stop) {
        $points[] = [(float) $stop['latitude'], (float) $stop['longitude']];
        if ($i > 0) {
            $meters += straight_line_km($stops[$i - 1]['latitude'], $stops[$i - 1]['longitude'], $stop['latitude'], $stop['longitude']) * 1000;
        }
    }
    $meters = (int) round($meters);
    save_route_path($conn, $route_id, $points, $meters, (int) round($meters / 5.5), 'straight');
    return ['points' => $points, 'meters' => $meters, 'seconds' => (int) round($meters / 5.5), 'source' => 'straight'];
}
