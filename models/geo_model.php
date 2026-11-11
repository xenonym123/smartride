<?php
// geo_cache table: street names we already got from Geoapify, so the free
// daily limit isn't used up looking up the same spot again and again.
// Points are rounded to 4 decimals (about 11 meters).

function get_cached_address($conn, $lat, $lng)
{
    $lat_key = round($lat, 4);
    $lng_key = round($lng, 4);
    $stmt = $conn->prepare("SELECT address FROM geo_cache WHERE lat_key = ? AND lng_key = ?");
    $stmt->bind_param("dd", $lat_key, $lng_key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? $row['address'] : null;
}

function save_cached_address($conn, $lat, $lng, $address)
{
    $lat_key = round($lat, 4);
    $lng_key = round($lng, 4);
    $address = mb_substr($address, 0, 255);
    $stmt = $conn->prepare("INSERT INTO geo_cache (lat_key, lng_key, address, created_at) VALUES (?, ?, ?, NOW())
                            ON DUPLICATE KEY UPDATE address = VALUES(address), created_at = NOW()");
    $stmt->bind_param("dds", $lat_key, $lng_key, $address);
    $stmt->execute();
}
