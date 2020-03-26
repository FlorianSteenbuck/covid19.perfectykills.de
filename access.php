<?php
function get_client_ip() {
    $ipaddress = '';
    if (getenv('HTTP_CLIENT_IP')) {
        $ipaddress = getenv('HTTP_CLIENT_IP');
    }
    else if(getenv('HTTP_X_FORWARDED_FOR')) {
        $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
    }
    else if(getenv('HTTP_X_FORWARDED')) {
        $ipaddress = getenv('HTTP_X_FORWARDED');
    }
    else if(getenv('HTTP_FORWARDED_FOR')){
        $ipaddress = getenv('HTTP_FORWARDED_FOR');
    }
    else if(getenv('HTTP_FORWARDED')) {
       $ipaddress = getenv('HTTP_FORWARDED');
    }
    else if(getenv('REMOTE_ADDR')) {
        $ipaddress = getenv('REMOTE_ADDR');
    }
    else {
        $ipaddress = null;
    }
    return $ipaddress;
}
header('Content-Type: text/plain');
if (isset($_GET['isset'])) {
    $ip = get_client_ip();
    if (is_null($ip)) {
        echo "wrong";
    } else {
        $cacher = fopen('cacher.txt', 'w+');
        fwrite($cacher, $ip);
        fclose($cacher);
        echo "ok";
    }
}
function can_cache() {
    return file_get_contents('cacher.txt') === get_client_ip();
}