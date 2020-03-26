<?php
function contents($url, $returnError=false, $userAgent='covid19-map-de-cacher',$referer='covid19.perfectykills.de') {
    $curl = curl_init();
    
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        'Accept: */*',
        'User-Agent: '.$userAgent,
        'Referer: '.$referer,
        'Cache-Control: no-cache',
        'Pragma: no-cache',
        'TE: Trailers'
    ));
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);

    $data = curl_exec($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    if($data === false) {
        if ($returnError) {
            return [false, curl_error($curl), $httpcode];
        }
    }

    curl_close($curl);

    if ($returnError) {
        return [true, $data, $httpcode];
    }
    return $data;
}
/*const DL_MEMORY_USAGE = 4069;
function download($file_source, $file_target) {
    echo "Downloading file ".json_encode($file_source)." to ".json_encode($file_target)."\n";
    $rh = fopen($file_source, 'rb');
    $wh = fopen($file_target, 'w+b');
    if (!$rh || !$wh) {
        return false;
    }

    while (!feof($rh)) {
        if (fwrite($wh, fread($rh, DL_MEMORY_USAGE)) === FALSE) {
            return false;
        }
        flush();
    }

    fclose($rh);
    fclose($wh);

    return true;
}
download("https://curl.haxx.se/ca/cacert.pem", "certs.crt");*/