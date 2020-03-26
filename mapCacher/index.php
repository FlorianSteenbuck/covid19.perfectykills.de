<?php
include 'access.php';
include 'certs.php';
$mimeSplit = explode('.', $_SERVER['REQUEST_URI']);
if (count($mimeSplit) !== 2 || $mimeSplit[1] !== 'png') {
	exit();
}
$name = str_replace('/', '-', $mimeSplit[0]);
if (!preg_match('/^[A-z0-9\-]+$/', $name)) {
	exit();
}
if (file_exists($name.'.png')) {
	header('Content-Type: image/png');
	echo file_get_contents($name.'.png');
} else if (can_cache()) {
	list($success, $data) = contents('https://a.tile.openstreetmap.org'.$_SERVER['REQUEST_URI'], true, 'covid19-tile-cacher-de', $_SERVER['SERVER_NAME']);
	if ($success) {
		header('Content-Type: image/png');
		echo $data;
		if (!file_exists($name.'.png')) {
			$fhandle = fopen($name.'.png', 'w+');
			fwrite($fhandle, $data);
			fclose($fhandle);
		}
	}
} else {
  $id = rand(0, 4);
  $sub = 'a';
  if ($id === 1) {
  	$sub = 'b';
  } else if ($id === 2) {
  	$sub = 'c';
  }

  header('Location: https://'.$sub.'.tile.openstreetmap.org'.$_SERVER['REQUEST_URI']);
}