<?php
include_once 'certs.php';

$filter = [];
$singleMode = !((isset($_GET['all'])) || (isset($_GLOBALS['all'])));
$result = [];
foreach (scandir('.') as $mayData) {
	if (in_array($mayData, ['.', '..']) || count(explode('.', $mayData)) !== 2 || explode('.', $mayData)[1] !== 'json') {
		continue;
	}
	$name = explode('.', $mayData)[0];
	if (strlen($name) >= 8 && substr($name, strlen($name)-7, strlen($name)-1) === "germany") {
		$name = substr($name, 0, strlen($name)-8);
	}
	$result[strtoupper($name[0]).substr($name, 1)] = json_decode(file_get_contents($mayData), true);
}

if ((!isset($_GET['q'])) && (!$_GLOBALS['FROM_FILE'])) {
	header("Access-Control-Allow-Origin: *");
	header('Content-Type: application/json');

	foreach ($result as $rootRegion => $set) {
		// 'risky' => $risky, 'source' => $source, 'updated' => $innerUpdated, 'cases' => $data, 'deaths' => $deaths, 'incidence' => $incidence, 'geos' => $geos
		if (array_key_exists('cases', $set) && is_array($set['cases'])) {
			if ((!array_key_exists('cases', $set))) {
				$result[$rootRegion]['cases'] = [];
			}
	
			if ((!array_key_exists('geos', $set))) {
				$result[$rootRegion]['geos'] = [];
			}
	
			foreach ($result[$rootRegion]['geos'] as $geoKey => $geoSet) {
				if ($singleMode) {
					$result[$rootRegion]['geos'][$geoKey] = ['id' => $geoSet['id']];
				}
			}
			
			/*
			if (!$singleMode) {
				foreach ($result[$rootRegion]['cases'] as $caseKey => $caseSet) {
					if (array_key_exists($caseSet, $result[$rootRegion]['geos'])) {
						continue;
					}
				}
			}*/
	
			if ((!array_key_exists('risky', $set))) {
				$result[$rootRegion]['risky'] = [];
			}
	
			if ((!array_key_exists('deaths', $set))) {
				$result[$rootRegion]['deaths'] = [];
			}
	
			if ((!array_key_exists('incidence', $set))) {
				$result[$rootRegion]['incidence'] = [];
			}
		} else {
			if ((!array_key_exists('cases', $set)) || (!is_int($set['cases']))) {
				$result[$rootRegion]['cases'] = 0;
			}
	
			if ((!array_key_exists('geos', $set)) || (!is_array($set['geos']))) {
				$result[$rootRegion]['geos'] = [];
			} else if ($singleMode) {
				$result[$rootRegion]['geos'] = ['id' => $result[$rootRegion]['geos']['id']];
			}
	
			if ((!array_key_exists('risky', $set)) || (!is_array($set['risky']))) {
				$result[$rootRegion]['risky'] = [];
			}
	
			if ((!array_key_exists('deaths', $set)) || (!is_int($set['deaths']))) {
				$result[$rootRegion]['deaths'] = 0;
			}
	
			if ((!array_key_exists('incidence', $set)) || ((!is_int($set['incidence'])) && (!is_float($set['incidence'])))) {
				$result[$rootRegion]['incidence'] = 0;
			}
		}

		if (array_key_exists('mergedWith', $set) && is_array($set['mergedWith'])) {
			$newMergedWith = [];
			foreach ($result[$rootRegion]['mergedWith'] as $mergedWith) {
				if (in_array($mergedWith, $newMergedWith)) {
					continue;
				}
				$newMergedWith[] = $mergedWith;
			}
			$result[$rootRegion]['mergedWith'] = $newMergedWith;
		}
	}

	echo json_encode($result, JSON_FORCE_OBJECT);
}