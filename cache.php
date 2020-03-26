<?php
include 'certs.php';
include 'access.php';
if (isset($_GET['q'])) {
    include 'api.php';
    $possibleCacheFiles = [];
    $rootRegionMap = [];
    foreach (array_keys($result) as $rootRegion) {
        if (is_array($result[$rootRegion]['cases'])) {
            foreach (array_keys($result[$rootRegion]['cases']) as $subRegion) {
                $key = trim(preg_replace('/^Stadt/', '', preg_replace('/\(kreisfreie Stadt\)/', '', preg_replace('/\(Stadtkreis\)/', '', $subRegion)))).', '.$rootRegion.', Germany';
                $possibleCacheFiles[$key] = 'cache/'.strtolower($subRegion).'-'.strtolower($rootRegion).'-germany';
                $rootRegionMap[$key] = $rootRegion;
            }
        } else if (is_int($result[$rootRegion]['cases'])) {
            $possibleCacheFiles[$rootRegion.', Germany'] = 'cache/'.strtolower($rootRegion).'-germany';
            $rootRegionMap[$rootRegion.', Germany'] = $rootRegion;
        }
    }

    if (in_array($_GET['q'], array_keys($possibleCacheFiles))) {
        $cacheFile = $possibleCacheFiles[$_GET['q']];
        if (isset($_GET['geoid'])) {
            $json = json_decode(file_get_contents(strtolower($rootRegionMap[$_GET['q']][0]).substr($rootRegionMap[$_GET['q']], 1).'.json'), true);
            $_GET['q'] = explode(',', $_GET['q'])[0];
            if (array_key_exists('geos', $json) && array_key_exists($_GET['q'], $json['geos'])) {
                $geo = $json['geos'][$_GET['q']];
                if (''.$geo['id'] === $_GET['geoid']) {
                    header("Access-Control-Allow-Origin: *");
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'data' => [['geojson' => $geo]]]);
                    exit();
                }
            }
        }
        if (can_cache() && !file_exists($cacheFile)) {
            $content = contents('https://nominatim.openstreetmap.org/search.php?q='.urlencode($_GET['q']).'&polygon_geojson=1&format=json');
            $cacheHandle = fopen($cacheFile, 'w+');
            fwrite($cacheHandle, $content);
            fclose($cacheHandle);
        }

        if (file_exists($cacheFile)) {
            header("Access-Control-Allow-Origin: *");
            header('Content-Type: application/json');
            echo json_encode(array('success' => true, 'data' => json_decode(file_get_contents($cacheFile))));
        } else {
            header("Access-Control-Allow-Origin: *");
            header('Content-Type: application/json');
            echo json_encode(array('success' => false, 'msg' => 'Not cached yet'));
        }
    } else {
        header("Access-Control-Allow-Origin: *");
        header('Content-Type: application/json');
        echo json_encode(array('success' => false, 'allow' => array_keys($possibleCacheFiles)));
    }
} else {
    header("Access-Control-Allow-Origin: *");
    header('Content-Type: application/json');
    echo json_encode(array('success' => false));
}