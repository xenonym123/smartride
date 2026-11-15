<?php
// Turn-by-turn directions for driving a jeepney line (the driver's Navigate screen).
//
// Each line is already a road line (route_paths). From its shape SmartRide finds where the
// road turns: the direction before and after each point, measured over about 25 m so small
// wiggles don't count. Those turns become steps like "Turn left onto Lacson Street".
// GEOAPIFY reverse geocoding (type=street) names the street after each turn.
// The steps are worked out once per line and saved in route_guidance.

require_once __DIR__ . '/geoapify.php';
require_once __DIR__ . '/../models/route_model.php';

define('TURN_MIN_DEGREES', 30);   // smaller bends than this aren't turns
define('TURN_LOOK_M', 25);        // how far before/after a point to measure the direction

// Compass direction from one point to another, 0-360 (0 = north, 90 = east).
function bearing_deg($a, $b)
{
    $la1 = deg2rad($a[0]);
    $la2 = deg2rad($b[0]);
    $dl = deg2rad($b[1] - $a[1]);
    $y = sin($dl) * cos($la2);
    $x = cos($la1) * sin($la2) - sin($la1) * cos($la2) * cos($dl);
    return fmod(rad2deg(atan2($y, $x)) + 360, 360);
}

// Distance along the line, in meters, from the start to every point.
function along_distances($points)
{
    $dist = [0.0];
    for ($k = 1; $k < count($points); $k++) {
        $dist[] = $dist[$k - 1] + 1000 * straight_line_km($points[$k - 1][0], $points[$k - 1][1], $points[$k][0], $points[$k][1]);
    }
    return $dist;
}

// The point $meters along the line.
function point_at($points, $dist, $meters)
{
    $meters = max(0, min($meters, $dist[count($dist) - 1]));
    for ($k = 1; $k < count($points); $k++) {
        if ($dist[$k] >= $meters) {
            $part = $dist[$k] - $dist[$k - 1];
            $t = $part > 0 ? ($meters - $dist[$k - 1]) / $part : 0;
            return [$points[$k - 1][0] + ($points[$k][0] - $points[$k - 1][0]) * $t,
                    $points[$k - 1][1] + ($points[$k][1] - $points[$k - 1][1]) * $t];
        }
    }
    return $points[count($points) - 1];
}

// Every turn on the line: [at_m, lat, lng, degrees] with degrees < 0 = left, > 0 = right.
function find_turns($points)
{
    if (count($points) < 3) {
        return [];
    }
    $dist = along_distances($points);
    $total = $dist[count($dist) - 1];
    $found = [];
    for ($k = 1; $k < count($points) - 1; $k++) {
        $here = $dist[$k];
        if ($here < TURN_LOOK_M || $here > $total - TURN_LOOK_M) {
            continue;
        }
        $in = bearing_deg(point_at($points, $dist, $here - TURN_LOOK_M), $points[$k]);
        $out = bearing_deg($points[$k], point_at($points, $dist, $here + TURN_LOOK_M));
        $delta = fmod($out - $in + 540, 360) - 180;   // -180..180, + = right
        if (abs($delta) >= TURN_MIN_DEGREES) {
            $found[] = [$here, $points[$k][0], $points[$k][1], $delta];
        }
    }
    // One bend shows up at several points next to each other: keep the sharpest within 40 m.
    $turns = [];
    foreach ($found as $t) {
        $last = count($turns) - 1;
        if ($last >= 0 && $t[0] - $turns[$last][0] < 40) {
            if (abs($t[3]) > abs($turns[$last][3])) {
                $turns[$last] = $t;
            }
            continue;
        }
        $turns[] = $t;
    }
    return $turns;
}

// "left" / "right" / "slight_left" / "sharp_right" / "uturn" ...
function turn_kind($degrees)
{
    $side = $degrees < 0 ? 'left' : 'right';
    $a = abs($degrees);
    if ($a >= 160) {
        return 'uturn';
    }
    if ($a < 60) {
        return 'slight_' . $side;
    }
    if ($a > 125) {
        return 'sharp_' . $side;
    }
    return $side;
}

function turn_words($kind)
{
    $words = [
        'left' => 'Turn left', 'right' => 'Turn right',
        'slight_left' => 'Keep left', 'slight_right' => 'Keep right',
        'sharp_left' => 'Make a sharp left', 'sharp_right' => 'Make a sharp right',
        'uturn' => 'Make a U-turn',
    ];
    return $words[$kind];
}

// GEOAPIFY: the name of the street at a point ('' if there's no key or no name).
function geoapify_street($lat, $lng)
{
    if (geoapify_demo_mode()) {
        return '';
    }
    try {
        $data = geoapify_get('/geocode/reverse', ['lat' => $lat, 'lon' => $lng, 'type' => 'street', 'format' => 'json', 'lang' => 'en']);
    } catch (Exception $ex) {
        error_log('SmartRide Geoapify street: ' . $ex->getMessage());
        return '';
    }
    $place = $data['results'][0] ?? [];
    return (string) ($place['street'] ?? ($place['name'] ?? ($place['address_line1'] ?? '')));
}

// The directions for one line, from its shape only (no street names yet):
// turns and landmarks in driving order, and the line itself.
function build_guidance($conn, $route_id)
{
    $path = route_path_for($conn, $route_id);
    $points = $path['points'];
    if (count($points) < 2) {
        return null;
    }
    $dist = along_distances($points);
    $total = $dist[count($dist) - 1];

    $steps = [];
    foreach (find_turns($points) as $t) {
        // Where to read the new street's name: a little way after the turn, clearly on the new road.
        $after = point_at($points, $dist, $t[0] + 30);
        $steps[] = ['type' => 'turn', 'kind' => turn_kind($t[3]), 'at' => (int) round($t[0]),
                    'lat' => round($t[1], 6), 'lng' => round($t[2], 6),
                    'name_at' => [round($after[0], 6), round($after[1], 6)], 'street' => null, 'text' => '', 'skip' => false];
    }
    // Landmarks, placed where the line passes them (closest point, searched in driving order).
    $from = 0;
    foreach (get_route_stops($conn, $route_id) as $stop) {
        $best = null;
        for ($k = $from; $k < count($points); $k++) {
            $d = straight_line_km($points[$k][0], $points[$k][1], (float) $stop['latitude'], (float) $stop['longitude']);
            if ($best === null || $d < $best[0]) {
                $best = [$d, $k];
            }
        }
        if ($best && $best[0] < 0.2) {
            $from = $best[1];
            $steps[] = ['type' => 'stop', 'kind' => 'stop', 'at' => (int) round($dist[$best[1]]),
                        'lat' => (float) $stop['latitude'], 'lng' => (float) $stop['longitude'],
                        'street' => '', 'text' => $stop['name'], 'skip' => false];
        }
    }
    usort($steps, function ($a, $b) { return $a['at'] <=> $b['at']; });

    return ['route_id' => (int) $route_id, 'points' => $points, 'total' => (int) round($total),
            'start_street' => null, 'keyed' => !geoapify_demo_mode(), 'complete' => geoapify_demo_mode(), 'steps' => $steps];
}

// Writes each turn's words from the street names known so far. A gentle bend that stays on
// the same street is skipped; a turn whose street isn't known (yet) is just "Turn left".
function compose_guidance_text($guide)
{
    $before = (string) ($guide['start_street'] ?? '');
    foreach ($guide['steps'] as $i => $step) {
        if ($step['type'] !== 'turn') {
            continue;
        }
        $street = (string) ($step['street'] ?? '');
        $slight = strpos($step['kind'], 'slight_') === 0;
        $guide['steps'][$i]['skip'] = $slight && ($street === '' || $street === $before);
        $guide['steps'][$i]['text'] = turn_words($step['kind']) . ($street !== '' && $street !== $before ? ' onto ' . $street : '');
        if ($street !== '') {
            $before = $street;
        }
    }
    return $guide;
}

// Saved directions for a line. The first time, they're made from the line's shape right away.
// GEOAPIFY then names the streets a few at a time (up to $budget seconds per request,
// the turns still ahead of the driver first), and every request saves what it found, so each line is named once.
function route_guidance_for($conn, $route_id, $budget = 6.0, $from_m = 0)
{
    $stmt = $conn->prepare("SELECT guide FROM route_guidance WHERE route_id = ?");
    $stmt->bind_param("i", $route_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $guide = $row ? json_decode($row['guide'], true) : null;
    $changed = false;
    if (!$guide) {
        $guide = build_guidance($conn, $route_id);
        if (!$guide) {
            return null;
        }
        $changed = true;
    }
    // Made without a key, and now there is one: name the streets after all.
    if (!$guide['keyed'] && !geoapify_demo_mode()) {
        $guide['keyed'] = true;
        $guide['complete'] = false;
        $changed = true;
    }

    if (!$guide['complete']) {
        $start = microtime(true);
        if ($guide['start_street'] === null) {
            $guide['start_street'] = geoapify_street($guide['points'][0][0], $guide['points'][0][1]);
            $changed = true;
        }
        $left = 0;
        // Turns still ahead of the driver first, then the rest of the loop.
        $order = array_keys($guide['steps']);
        usort($order, function ($a, $b) use ($guide, $from_m) {
            $ahead_a = $guide['steps'][$a]['at'] >= $from_m ? 0 : 1;
            $ahead_b = $guide['steps'][$b]['at'] >= $from_m ? 0 : 1;
            return [$ahead_a, $guide['steps'][$a]['at']] <=> [$ahead_b, $guide['steps'][$b]['at']];
        });
        foreach ($order as $i) {
            $step = $guide['steps'][$i];
            if ($step['type'] !== 'turn' || $step['street'] !== null) {
                continue;
            }
            if (microtime(true) - $start > $budget) {
                $left++;
                continue;
            }
            $guide['steps'][$i]['street'] = geoapify_street($step['name_at'][0], $step['name_at'][1]);
            $changed = true;
        }
        $guide['complete'] = $left === 0;
    }

    $guide = compose_guidance_text($guide);
    if ($changed) {
        $json = json_encode($guide);
        $stmt = $conn->prepare("REPLACE INTO route_guidance (route_id, guide, updated_at) VALUES (?, ?, NOW())");
        $stmt->bind_param("is", $route_id, $json);
        $stmt->execute();
    }
    return $guide;
}

// A line's road changed (admin edited a Geoapify route): its directions must be made again.
function clear_route_guidance($conn, $route_id)
{
    $stmt = $conn->prepare("DELETE FROM route_guidance WHERE route_id = ?");
    $stmt->bind_param("i", $route_id);
    $stmt->execute();
}
