<?php
include_once 'config.php';
if ($_GET['cronpw'] !== $pw) {
    exit();
}
ini_set('auto_detect_line_endings',TRUE);
header('Content-Type: text/plain');
include('certs.php');
function startsWith($haystack, $needle)
{
     $length = strlen($needle);
     return (substr($haystack, 0, $length) === $needle);
}

function endsWith($haystack, $needle)
{
    $length = strlen($needle);
    if ($length == 0) {
        return true;
    }

    return (substr($haystack, -$length) === $needle);
}

function cleanNumberArray($data) {
    $result = [];
    foreach ($data as $key => $value) {
        if (empty($key) || ((!is_int($value)) && (!is_float($value)))) {
            var_dump('broken data');
            var_dump(json_encode([$key => $value]));
            continue;
        }
        $result[$key] = $value;
    }
    return $result;
}

function saveSubRegion($name, $source, $updated, $data = [], $deaths = [], $risky = [], $incidence = [], $geos = [], $innerUpdated = null) {
    if (is_null($innerUpdated)) {
        $innerUpdated = $updated;
    }
    $obj = array('risky' => $risky, 'source' => $source, 'updated' => $innerUpdated, 'cases' => cleanNumberArray($data), 'deaths' => cleanNumberArray($deaths), 'incidence' => cleanNumberArray($incidence), 'geos' => $geos);
    if ((!file_exists($name.'-'.$updated.'.json.hist'))) {
        var_dump('hit save '.$name.'-'.$updated);
        $fhandle = fopen($name.'-'.$updated.'.json.hist', 'w+');
        fwrite($fhandle, json_encode($obj));
        fclose($fhandle);
        $fhandle = fopen($name.'.json', 'w+');
        fwrite($fhandle, json_encode($obj));
        fclose($fhandle);
    }
}

function checkEqualData($name, $data) {
    $newestFile = null;
    $newestTime = null;
    foreach (scandir('.') as $file) {
        if (in_array($file, ['.', '..']) || (!startsWith($file, $name.'-')) || (!endsWith($file, '.json.hist'))) {
            continue;
        }
        $parts = explode('-', $file);
        $time = explode('.', $parts[count($parts)-1])[0];
        if (!is_numeric($time)) {
            continue;
        }
        $time = intval($time);
        if (is_null($newestTime) || $newestTime < $time) {
            $newestTime = $time;
            $newestFile = $file;
        }
    }
    if (!is_null($newestFile)) {
        $mayData = json_decode(file_get_contents($newestFile), true);
        $needData = $data;
        unset($mayData['updated']);
        unset($needData['updated']);
        return empty(array_diff($mayData, $needData));
    }
    return true;
}

function trimmed($str) {
    return trim(str_replace(json_decode('"\u00a0"'), '',preg_replace('/<(\/|)(.*)>/', '', str_replace('LK', '', str_replace('SK', '', str_replace('LK & SK', '', str_replace('LK & SK', '', trim($str))))))));
}

include('simple_html_dom.php');
// Niedersachsen
$data = [];
$incidence = [];
$diff = [];
$geos = [];

$source = 'https://www.apps.nlga.niedersachsen.de/corona/download.php?json=&time'.time();
$raw = json_decode(contents($source), true);
$updated = time();
$mayUpdated = strtotime($raw['updated_at']);
if ($mayUpdated > $updated) {
    $updated = $mayUpdated;
}

foreach ($raw['features'] as $feature) {
    $name = $feature['properties']['name'];
    $nameShortSplit = explode(' ', $name);

    $diff[$name] = $feature['properties']['diff'];
    $incidence[$name] = $feature['properties']['incidence'];
    $data[$name] = $feature['properties']['value'];

    if (count($nameShortSplit) > 1) {
        $short = trim($nameShortSplit[0]);
        var_dump($short);
        if ($short[strlen($short)-1] !== 'K' || strlen($short) <= 1 || strlen($short) >= 5) {
            continue;
        }
        $geo = $feature['geometry'];
        $geo['id'] = $feature['properties']['id'];
        $geos[$name] = $geo;
    }
}

saveSubRegion('niedersachsen', $source, $updated, $data, [], [], $incidence, $geos);

/* Old Niedersachsen
$doc = str_get_html(contents("https://www.niedersachsen.de/Coronavirus"));
//$doc = str_get_html(file_get_contents("testnieder.html"));

$lageNode = null;
foreach ($doc->find('.content.wrap4of4 .col.span1of4') as $node) {
    $mayNodeHeads = $node->find('.content b');
    if (count($mayNodeHeads) > 0) {
        if (trim($mayNodeHeads[0]->plaintext) === 'Aktuelle Lage in Niedersachsen') {
            $lageNode = $node->find('.content')[0];
        }
    }
}

if (is_null($lageNode)) {
    var_dump('Niedersachsen can not find $lageNode');
}

$hit_head = false;
$updated = time();
foreach ($lageNode->children as $node) {
    if ($node->tag !== 'p') {
        continue;
    }

    $plain = trim($node->plaintext);
    var_dump($plain);
    $direct_head_hit = startsWith($plain, 'Alle registrierten Fälle');
    var_dump('direct_head_hit='.($direct_head_hit ? 'true' : 'false'));
    if ($hit_head || $direct_head_hit) {

        $data = [];
        $first_skip = $direct_head_hit;
        foreach (explode("\n", $plain) as $line) {
            if ($first_skip) {
                $first_skip = false;
                continue;
            }
            list($anzahl, $woWithExtra) = preg_split('/(Fall|Fälle) (im|in) (d([A-z]{2})|)/', trim($line));
            $anzahl = intval(trim($anzahl));
            $woWithExtra = trim(preg_replace('/<(\/|)(.*)>/', '', str_replace('LK', '', str_replace('SK', '', str_replace('LK & SK', '', str_replace('LK & SK', '', trim($woWithExtra)))))));;
            
            var_dump('woWithExtra='.$woWithExtra);
            var_dump('anzahl='.$anzahl);
            if (empty($woWithExtra)) {
                continue;
            }

            var_dump('Searching for Extras (+X) YYY...');
            $wo = '';
            $hitExtraCount = 0;
            for ($i=0; $i < strlen($woWithExtra); $i++) { 
                $woCh = $woWithExtra[$i];
                if ($hitExtraCount <= 0 && $woCh !== '(' && $woCh !== ')' && $woCh !== ' ') {
                    $wo = substr($woWithExtra, $i);
                    break;
                }

                if ($hitExtraCount > 0) {
                    if ($woCh === ')') {
                        $hitExtraCount--;
                    }
                    continue;
                }

                if ($woCh === '(' && $i+1 < strlen($woWithExtra) && ($woWithExtra[$i+1] === '+' || $woWithExtra[$i+1] === '-')) {
                    $hitExtraCount++;
                    continue;
                }
            }
            var_dump($wo);
            var_dump('Searching for Extras YYY (+X)...');
            $hitExtraCount = 0;
            $currentExtra = '';
            for ($i=strlen($wo)-1; $i >= 0; $i--) { 
                $woCh = $wo[$i];
                if ($hitExtraCount <= 0 && $woCh !== '(' && $woCh !== ')' && $woCh !== ' ') {
                    $wo = substr($wo, 0, $i+1);
                    break;
                }

                if ($hitExtraCount > 0) {
                    if ($woCh === '(') {
                        $hitExtraCount--;
                        var_dump('currentExtra='.$currentExtra);
                        if (strlen($currentExtra) > 0) {
                            var_dump('last_char(currentExtra)='.$currentExtra[strlen($currentExtra)-1]);
                        } else {
                            var_dump('no last char');
                        }
                        if (strlen($currentExtra) > 0 && ($currentExtra[strlen($currentExtra)-1] !== '+' || $currentExtra[strlen($currentExtra)-1] !== '-')) {
                            continue;
                        }
                        $wo = substr($wo, 0, $i+strlen($currentExtra)+2);
                        break;
                    }
                }

                if ($woCh === ')') {
                    $hitExtraCount++;
                    continue;
                }

                $currentExtra .= $woCh;
            }
            
            var_dump('wo='.$wo);
            var_dump('anzahl='.$anzahl);
            if (array_key_exists($wo, $data)) {
                $data[$wo] += $anzahl;
            } else {
                $data[$wo] = $anzahl;
            }
        }
        saveSubRegion('niedersachsen', 'https://www.niedersachsen.de/Coronavirus', $updated, $data);
        $hit_head = false;
        continue;
    }

    if ($plain === 'Alle registrierten Fälle:') {
        $hit_head = true;
        continue;
    }

    preg_match('/zuletzt( +)aktualisiert( +)am( +)([0-9]{2})\.([0-9]{2})\.([0-9]{4}),( +)([0-9]{2}):([0-9]{2})/', $plain, $mayDate);
    if ($mayDate) {
        var_dump('hit updated');
        $updated = strtotime($mayDate[4].'/'.$mayDate[5].'/'.$mayDate[6].' '.$mayDate[8].':'.$mayDate[9]);
        continue;
    }

}*/

// Baden-Württemberg

file_put_contents(
    "Tabelle_Coronavirus-Faelle-BW.xlsx", contents(
        "https://sozialministerium.baden-wuerttemberg.de/fileadmin/redaktion/m-sm/intern/downloads/Downloads_Gesundheitsschutz/Tabelle_Coronavirus-Faelle-BW.xlsx"
    )
);
require('vendor/autoload.php');
$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
$reader->setReadDataOnly(TRUE);
$spreadsheet = $reader->load("Tabelle_Coronavirus-Faelle-BW.xlsx");

$worksheet = $spreadsheet->getActiveSheet();
$highestRow = $worksheet->getHighestRow();
$highestColumn = $worksheet->getHighestColumn();
$highestColumn++;

$defaultTime = time();
$updated = $defaultTime;
$colHeads = [];
$headRow = 1;
$currentRegion = '';
$data = [];
for ($row = 1; $row <= $highestRow; ++$row) {
    for ($col = 'A'; $col != $highestColumn; ++$col) {
        $cellValue = trim($worksheet->getCell($col . $row)->getValue());
        //if ($updated !== $defaultTime) {
            if (empty($colHeads['B']) && $headRow === $row) {
                if (empty($cellValue)) {
                    var_dump('head skip to '.$headRow);
                    $headRow++;
                    break;
                }
                var_dump('$colHeads['.$col.']='.$cellValue);
                $colHeads[$col] = $cellValue;
            } else {
                $breakIt = false;
                switch ($colHeads[$col]) {
                    case 'Stadt-/Landkreis':
                        if ($cellValue === 'Summe' || $cellValue === '' || startsWith($cellValue, 'Hinweis:')) {
                            $breakIt = true;
                            break;
                        }
                        var_dump('$wo='.$cellValue);
                        $currentRegion = $cellValue;
                        $data[$cellValue] = 0;
                        break;
                    case 'Fälle':
                        var_dump('$wo='.$currentRegion);
                        var_dump('$anzahl='.$cellValue);
                        var_dump('is_numeric($anzahl)='.is_numeric($cellValue) ? 'true' : 'false');
                        if (is_numeric($cellValue)) {
                            $data[$currentRegion] = intval($cellValue);
                        }
                        break;
                }
                if ($breakIt) {
                    break;
                }
            }
        /*} else {
            preg_match('/Stand:( +)([0-9]{2})\.([0-9]{2}|[0-9]{1})\.([0-9]{4}),( +)([0-9]{2}):([0-9]{2})( +)Uhr/', trim($cellValue), $mayDate);
            if ($mayDate) {
                var_dump('hit date row');
                $updated = strtotime($mayDate[3].'/'.$mayDate[2].'/'.$mayDate[4].' '.$mayDate[6].':'.$mayDate[7]);
                $headRow = $row+1;
                break;
            }
        }*/
    }
}
var_dump("baden-wuerttemberg.de");
var_dump($data);
$obj = array('risky' => [], 'source' => 'https://sozialministerium.baden-wuerttemberg.de/fileadmin/redaktion/m-sm/intern/downloads/Downloads_Gesundheitsschutz/Tabelle_Coronavirus-Faelle-BW.xlsx', 'updated' => $updated, 'cases' => $data, 'deaths' => []);
if ((!file_exists('baden-Württemberg-'.$updated.'.json.hist'))) {
    var_dump('hit save baden-Württemberg');
    $fhandle = fopen('baden-Württemberg-'.$updated.'.json.hist', 'w+');
    fwrite($fhandle, json_encode($obj));
    fclose($fhandle);
    $fhandle = fopen('baden-Württemberg.json', 'w+');
    fwrite($fhandle, json_encode($obj));
    fclose($fhandle);
}
// Google Docs NRW
file_put_contents(
    "nrw.csv", contents(
        "https://docs.google.com/spreadsheets/d/e/2PACX-1vRQsjoUeQMkDIhCh_77fZrSZxHRK6igaR3P9x6a6r3zdEQeb4vWZP-aYKse3QHSrVoUa8wauJ-FOWo6/pub?output=csv"
    )
);
var_dump('hit google');
$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Csv');
$spreadsheet = $reader->load('nrw.csv');
var_dump('hit sheet');
$sheetData   = $spreadsheet->getActiveSheet()->toArray();
var_dump($sheetData);
$colHeads = [];
foreach ($sheetData[0] as $colHead) {
    $colHeads[] = $colHead;
}
var_dump($colHeads);

$defaultTime = time();
$updated = $defaultTime;
$data = [];
$deaths = [];
for ($x=1; $x < count($sheetData); $x++) {
    $cells = $sheetData[$x];
    $obj = [];
    for ($i=0; $i < count($colHeads) && $i < count($cells); $i++) {
        $obj[$colHeads[$i]] = $cells[$i];
    }

    /*preg_match('/([0-9]{2}|[0-9]{1})\.([0-9]{2}|[0-9]{1})\.([0-9]{4}),( +)([0-9]{2}).([0-9]{2})( +)Uhr/', $obj['date'], $mayDate);
    $mayMinUpdated = strtotime($mayDate[2].'/'.$mayDate[1].'/'.$mayDate[3].' '.$mayDate[5].':'.$mayDate[6]);
    if (is_int($mayMinUpdated) && ($updated === $defaultTime || $mayMinUpdated < $updated)) {
        $updated = $mayMinUpdated;
    }*/

    $data[$obj['city']] = $obj['cases']-$obj['gesund'];
    var_dump('cases='.$obj['cases']);
    $deaths[$obj['city']] = $obj['tot'];
    var_dump('tot'.$obj['tot']);
}
var_dump($data);
var_dump($deaths);
$obj = array('risky' => [], 'source' => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vRQsjoUeQMkDIhCh_77fZrSZxHRK6igaR3P9x6a6r3zdEQeb4vWZP-aYKse3QHSrVoUa8wauJ-FOWo6/pub?output=csv', 'updated' => $updated, 'cases' => cleanNumberArray($data), 'deaths' => cleanNumberArray($deaths), 'incidence' => [], 'geos' => []);
if (!file_exists('nordrhein-Westfalen-'.$updated.'.json.hist')) {
    var_dump('hit save nordrhein-Westfalen');
    $fhandle = fopen('nordrhein-Westfalen-'.$updated.'.json.hist', 'w+');
    fwrite($fhandle, json_encode($obj));
    fclose($fhandle);
    $fhandle = fopen('nordrhein-Westfalen.json', 'w+');
    fwrite($fhandle, json_encode($obj));
    fclose($fhandle);
}

// Deutschland RKI
/*//var_dump(contents("https://www.rki.de/DE/Content/InfAZ/N/Neuartiges_Coronavirus/Fallzahlen.html"));
$doc = str_get_html(contents("https://www.rki.de/DE/Content/InfAZ/N/Neuartiges_Coronavirus/Fallzahlen.html"));
//$doc = str_get_html(file_get_contents("rki.html"));
var_dump('hit load');
$adminRegions = [];
$data = [];
$risky = [];
$death = [];
$mayTable = $doc->find('#main table');
if (count($mayTable) > 0) {
    var_dump('hit table');
    $table = $mayTable[0];

    $mayDateSib = $table->next_sibling();
    while ($mayDateSib !== null && ($mayDateSib->tag !== 'p' || (!startsWith(trim($mayDateSib->plaintext), 'Stand:')))) {
        $mayDateSib = $mayDateSib->next_sibling();
    }
    $updated = time();
    if ($mayDateSib !== null && $mayDateSib->tag === 'p' && startsWith(trim($mayDateSib->plaintext), 'Stand:')) {
        preg_match('/Stand:( +)([0-9]{2})\.([0-9]{2}|[0-9]{1})\.([0-9]{4}),( +)([0-9]{2}):([0-9]{2})( +)Uhr/', trim($mayDateSib->plaintext), $mayDate);
    
        if ($mayDate) {
            $updated = strtotime($mayDate[3].'/'.$mayDate[2].'/'.$mayDate[4].' '.$mayDate[6].':'.$mayDate[7]);
        }
    }

    $colHeads = [];
    foreach ($table->find('thead tr')[0]->find('th') as $colHead) {
        var_dump('hit head');
        $colHeads[] = trim($colHead->plaintext);
    }

    foreach ($table->find('tbody tr') as $row) {
        var_dump('hit row');
        $cells = $row->find('td');
        $obj = [];
        for ($i=0; $i < count($colHeads) && $i < count($cells); $i++) {
            $obj[$colHeads[$i]] = $cells[$i];
        }

        if (array_key_exists('Bundesland', $obj)) {
            var_dump('hit Bundesland');
            $adminRegionName = trim($obj['Bundesland']->plaintext);
            var_dump($adminRegionName);
            var_dump('hit '.$adminRegionName);

            if ($adminRegionName === 'Gesamt') {
                var_dump('hit Gesamt');
                continue;
            }
            var_dump('continue after Gesamt');

            $adminRegions[] = $adminRegionName;
            foreach (array_keys($obj) as $value) {
                echo json_encode($value)."\n";
            }
            $fallKey = json_decode('"Zahl be\u00adst\u00e4\u00adtig\u00adter F\u00e4lle\r\n (darunter Todes\u00adf\u00e4lle)"');
            $newClassicWay = array_key_exists('Elektronisch übermittelte Fälle', $obj);
            $classicKey = array_key_exists('Fälle', $obj) || $newClassicWay;
            var_dump('newClassicWay='. $newClassicWay ? 'true' : 'false');
            var_dump('classicKey='. $classicKey ? 'true' : 'false');
            var_dump('weirdoMethod='. array_key_exists($fallKey, $obj) ? 'true' : 'false');
            if (array_key_exists($fallKey, $obj) || $classicKey || $newClassicWay) {
                var_dump('hit Fälle');
                var_dump($newClassicWay ? str_replace('.', '',trim($obj['Elektronisch übermittelte Fälle']->plaintext)) : str_replace('.', '',trim($obj['Fälle']->plaintext)));
                if ($classicKey) {
                    $data[$adminRegionName] = $newClassicWay ? intval(str_replace('.', '',trim($obj['Elektronisch übermittelte Fälle']->plaintext))) : intval(str_replace('.', '',trim($obj['Fälle']->plaintext)));
                } else {
                    preg_match('/([0-9]+)( +|)\(([0-9]+)\)/', trim($obj[$fallKey]->plaintext), $deathSplit);
                    var_dump($obj[$fallKey]->plaintext);
                    var_dump(count($deathSplit));
                    if (count($deathSplit) === 4) {
                        $data[$adminRegionName] = intval(trim($deathSplit[1]));
                        $death[$adminRegionName] = intval(trim($deathSplit[3]));
                    } else {
                        $data[$adminRegionName] = intval(trim($obj[$fallKey]->plaintext));
                    }
                }
                var_dump($data[$adminRegionName]);
            }

            if (array_key_exists('Todesfälle', $obj)) {
                var_dump('hit Todesfälle');
                $death[$adminRegionName] = intval(trim($obj['Todesfälle']->plaintext));
            }

            if (array_key_exists('Beson­ders betroffene Ge­biete in Deutsch­land', $obj)) {
                var_dump('hit risky');
                if (!array_key_exists($adminRegionName, $risky)) {
                    $risky[$adminRegionName] = [];
                }

                foreach ($obj['Beson­ders betroffene Ge­biete in Deutsch­land']->find('li') as $liRisky) {
                    $risky[$adminRegionName][] = trim($liRisky->plaintext);
                }
            }
        }
    }
}
foreach ($adminRegions as $adminRegion) {
    $isNot = false;
    if (in_array($adminRegion, ['Sachsen-Anhalt', 'Hessen', 'Sachsen', 'Thüringen', 'Niedersachsen', 'Nordrhein-Westfalen', 'Baden-Württemberg', 'Schleswig-Holstein', 'Bayern', 'Brandenburg'])) {
        continue;
    }

    $obj = [];
    $obj['updated'] = $updated;
    $obj['source'] = 'https://www.rki.de/DE/Content/InfAZ/N/Neuartiges_Coronavirus/Fallzahlen.html';
    $obj['risky'] = array_key_exists($adminRegion, $risky) ? $risky[$adminRegion] : [];
    $obj['deaths'] = array_key_exists($adminRegion, $death) ? $death[$adminRegion] : 0;
    $obj['cases'] = array_key_exists($adminRegion, $data) ? $data[$adminRegion] : 0;
    var_dump($obj);

    $filename = strtolower($adminRegion[0]).substr($adminRegion, 1).'-germany';
    var_dump($filename);
    var_dump(file_exists($filename.'-'.$updated.'.json.hist') || checkEqualData($filename, $obj));
    var_dump('checkEqualData='.checkEqualData($filename, $obj));
    if (file_exists($filename.'-'.$updated.'.json.hist')) {
        continue;
    }

    var_dump('hit save '.$filename);

    $fhandle = fopen($filename.'.json', 'w+');
    fwrite($fhandle, json_encode($obj));
    fclose($fhandle);

    $fhandle = fopen($filename.'-'.$updated.'.json.hist', 'w+');
    fwrite($fhandle, json_encode($obj));
    fclose($fhandle);
}*/

// 23degrees
function degrees23($url) {
    $updated = time();
    $data = [];
    $deaths = [];
    $trackDeath = false;
    $currentRegion = null;
    $types = json_decode(contents($url.'?cache='.$updated), true);
    $raw_data = json_decode(contents('https://app.23degrees.io/services/publicdata/'.$types['typeSpecific']['dataId']), true);

    foreach ($raw_data['data'] as $entry) {
        foreach ($types['typeSpecific']['fields'] as $key => $definition) {
            if (!array_key_exists($key, $entry)) {
                continue;
            }
            switch ($definition['fieldType']) {
                case 'isoLabelField':
                    $currentRegion = $entry[$key];
                    if (!array_key_exists($currentRegion, $data)) {
                        $data[$currentRegion] = 0;
                    }
                    if (!array_key_exists($currentRegion, $deaths)) {
                        $deaths[$currentRegion] = 0;
                    }
                    break;
                case 'miscField':
                	if ($entry[$key] === 'Todesfälle') {
                		$trackDeath = true;
                	}
                	break;
                case 'valueField':
                    if ($trackDeath) {
                    	$deaths[$currentRegion] += intval($entry[$key]);
                    } else {
                    	$data[$currentRegion] += intval($entry[$key]);
                	}
                    $trackDeath = false;
                    break;
            }
        }
    }
    if (array_key_exists('updated_at', $raw_data)) {
        $updated = strtotime($raw_data['updated_at']);
    }
    return array($updated, $data, $deaths);
}
// Sachsen-Anhalt
list($updated, $data, $deaths) = degrees23('https://app.23degrees.io/services/public/content/byslug/V9MkV3eA6RDchdJt-choro-bestaetigte-covid-19-faelle-aus');

var_dump('Sachsen-Anhalt');
var_dump($data);

saveSubRegion('sachsen-Anhalt', 'https://app.23degrees.io/services/public/content/byslug/NFERNnLsFuxHmB6s-bar-horizontal-bestaetigte-corona-faelle-in', time(), $data, $deaths);

// Thüringen
list($updated, $data, $deaths) = degrees23('https://app.23degrees.io/services/public/content/byslug/BgkcBUMm46CgKm8x-choro-bestaetigte-coronavirus-faelle');

var_dump('Thüringen');
var_dump($data);
saveSubRegion('thüringen', 'https://app.23degrees.io/services/public/content/byslug/BgkcBUMm46CgKm8x-choro-bestaetigte-coronavirus-faelle', time(), $data, $deaths);

// Hessen
$updated = time();
$data = [];
$incidence = [];
$deaths = [];
$currentRegion = null;
$doc = str_get_html(contents('https://soziales.hessen.de/gesundheit/infektionsschutz/coronavirus-sars-cov-2/taegliche-uebersicht-der-bestaetigten-sars-cov-2-faelle-hessen'));

preg_match('/Stand( +)([0-9]{2})\.( +)(Januar|Februar|März|April|Mai|Juni|Juli|August|September|Oktober|November|Dezember)( +)([0-9]{4}),( +)([0-9]{2}):([0-9]{2})( +)Uhr/', $doc->find('.he_content_body h3')[0]->plaintext, $mayDate);
$deM = array_flip(['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember']);
/*if (count($mayDate) > 0) {
    var_dump('hit updated');
    $M = $deM[$mayDate[4]];
    $updated = strtotime($M.'/'.$mayDate[2].'/'.$mayDate[6].' '.$mayDate[8].':'.$mayDate[9]);
    var_dump($updated);
}*/
$mayTable = $doc->find('.he_content_body table');
if (count($mayTable) > 0) {
    $table = $mayTable[0];
    $mayTableBody = $table->find('tbody');
    if (count($mayTableBody) > 0) {
        $table = $mayTableBody[0];
    }

    $headers = true;
    $headRow = []; 
    foreach ($table->children as $tr) {
        if ($headers && count($tr->children) > 0) {
            for ($i = 0;$i < count($tr->children); $i++) {
                $headRow[trimmed($tr->children[$i]->plaintext)] = $i;
            }
            $headers = false;
            continue;
        }
        foreach ($headRow as $key => $index) {
            if (!array_key_exists($index, $tr->children)) {
                continue;
            }
            $continueNext = false;
            switch ($key) {
                case 'Kreis/Stadt':
                case 'Landkreis/Stadt':
                    if (empty(trimmed($tr->children[$index]->plaintext)) || trimmed($tr->children[$index]->plaintext) === 'Gesamt' || trimmed($tr->children[$index]->plaintext) === 'Wiesebaden') {
                    	var_dump('continueNext');
                        $continueNext = true;
                        break;
                    }
                    $currentRegion = trimmed($tr->children[$index]->plaintext);
                    var_dump('enter '.$currentRegion);
                    break;
                case 'bestätigte Fälle':
                case 'Gesamt':
                    var_dump('enter cases');
                    var_dump(intval(trimmed($tr->children[$index]->plaintext)));
                    $data[$currentRegion] = intval(trimmed($tr->children[$index]->plaintext));
                    break;
                case 'Inzidenz':
                    var_dump('enter incidence');
                    var_dump(floatval(str_replace(',', '.', trimmed($tr->children[$index]->plaintext))));
                    $incidence[$currentRegion] = floatval(str_replace(',', '.', trimmed($tr->children[$index]->plaintext)));
                    break;
                case 'Todesfälle':
                    var_dump('enter deaths');
                    var_dump(intval(trimmed($tr->children[$index]->plaintext)));
                	$deaths[$currentRegion] = intval(trimmed($tr->children[$index]->plaintext));
                	break;
            }
        	if ($continueNext) {
            	$continueNext = false;
            	break;
        	}
        }
            
    }
} else {
    var_dump('missing hessen table');
}

var_dump('Hessen');
var_dump($data);
// function saveSubRegion($name, $source, $updated, $data = [], $deaths = [], $risky = [], $incidence = [], $geos = [], $innerUpdated = null) {
saveSubRegion('hessen', 'https://soziales.hessen.de/gesundheit/infektionsschutz/coronavirus-sars-cov-2/taegliche-uebersicht-der-bestaetigten-sars-cov-2-faelle-hessen', $updated, $data, $deaths, [], $incidence);
// Sachsen
$updated = time();
$data = [];
$currentRegion = null;
$doc = str_get_html(contents('https://www.coronavirus.sachsen.de/infektionsfaelle-in-sachsen-4151.html'));
$mayTable = $doc->find('table');
if (count($mayTable) > 0) {
    $table = $mayTable[0];
    $mayTableBody = $table->find('tbody');
    if (count($mayTableBody) > 0) {
        $table = $mayTableBody[0];
    }
    
    foreach ($table->children as $tr) {
        if (count($tr->children) <= 1) {
            continue;
        }
        $key = trimmed($tr->children[0]->plaintext);
        if ($key === "Gesamtzahl der Infektionen" || $key === "Sachsen gesamt") {
            continue;
        }
        if (!array_key_exists($key, $data)) {
            $data[$key] = 0;
        }
        $data[$key] += intval(trimmed(explode("(", $tr->children[1]->plaintext)[0]));
    }
    $mayDateNode = $table->next_sibling();
    while ($mayDateNode !== null) {
        /*preg_match('/Stand:( +)([0-9]{2})\.( +)(Januar|Februar|März|April|Mai|Juni|Juli|August|September|Oktober|November|Dezember)( +)([0-9]{4}),( +)([0-9]{2}):([0-9]{2})( +)Uhr/', $mayDateNode->plaintext, $mayDate);
        if (count($mayDate) > 0) {
            var_dump('hit updated');
            $M = $deM[$mayDate[4]];
            $updated = strtotime($M.'/'.$mayDate[2].'/'.$mayDate[6].' '.$mayDate[8].':'.$mayDate[9]);
            break;
        }*/
        $mayDateNode = $table->next_sibling();
    }
} else {
    var_dump('missing sachsen table');
}
saveSubRegion('sachsen', 'https://www.coronavirus.sachsen.de/infektionsfaelle-in-sachsen-4151.html', $updated, $data);

file_put_contents(
    "swr.csv", contents(
        "https://d.swr.de/coronavirus/corona.csv?v=".time()
    )
);
var_dump('hit swr');
$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Csv');
$spreadsheet = $reader->load('swr.csv');
var_dump('hit sheet');
$sheetData   = $spreadsheet->getActiveSheet()->toArray();
var_dump($sheetData);
$colHeads = [];
foreach ($sheetData[0] as $colHead) {
    $colHeads[] = $colHead;
}
var_dump($colHeads);

$defaultTime = time();
$updated = $defaultTime;
$data = [];
for ($x=1; $x < count($sheetData); $x++) {
    $cells = $sheetData[$x];
    $obj = [];
    for ($i=0; $i < count($colHeads) && $i < count($cells); $i++) {
        $obj[$colHeads[$i]] = $cells[$i];
    }

    if ($obj['Land'] !== 7) {
        continue;
    }

    $mayMinUpdated = strtotime(str_replace('-', '/', $obj['Stand']));
    if (is_int($mayMinUpdated) && ($updated === $defaultTime || $mayMinUpdated < $updated)) {
        $updated = $mayMinUpdated;
    }

    $data[$obj['Gemeinde']] = intval($obj['Fallzahl']);
}
saveSubRegion('rheinland-Pfalz', 'https://d.swr.de/coronavirus/corona.csv', $updated, $data);
$firstBrandenburg = '13/03/2020';
$today = date('d/m/Y', time());;

function returnDates($fromdate, $todate) {
    $fromdate = \DateTime::createFromFormat('d/m/Y', $fromdate);
    $todate = \DateTime::createFromFormat('d/m/Y', $todate);
    return new \DatePeriod(
        $fromdate,
        new \DateInterval('P1D'),
        $todate->modify('+1 day')
    );
}
/*
var_dump('msgiv.brandenburg.de');
$name = 'brandenburg';
$data = null;
$updated = null;
$formatA = null;
$formatB = null;
$innerUpdated = null;
foreach (returnDates($firstBrandenburg, $today) as $day) {
    $lastUpdated = $updated;
    $lastFormatA = $formatA;
    $lastFormatB = $formatB;
    
    $updated = $day->getTimestamp();
    $formatA = $day->format('d-m-Y');
    $formatB = $day->format('dmY');
    /*
    if (file_exists($name.'-'.$updated.'.json.hist')) {
        $updated = $lastUpdated;
        $formatA = $lastFormatA;
        $formatB = $lastFormatB;
        continue;
    }*//*
    $lastInnerUpdated = $innerUpdated;
    $lastData = $data;

    $innerUpdated = $updated;
    $data = [];
    $currentRegion = null;
    list($success, $content, $statusCode) = contents('https://msgiv.brandenburg.de/msgiv/de/presse/pressemitteilungen/detail/~'.$formatA.'-corona-faelle-stand-'.$formatB, true);
    var_dump('https://msgiv.brandenburg.de/msgiv/de/presse/pressemitteilungen/detail/~'.$formatA.'-corona-faelle-stand-'.$formatB);
    var_dump('statusCode='.json_encode(array('value' => $statusCode)));
    var_dump('success='.json_encode(array('value' => $success)));
    if ($success === false || $statusCode != 200) {
        var_dump('skip it');
        $innerUpdated = $lastInnerUpdated;
        $data = $lastData;
        $updated = $lastUpdated;
        $formatA = $lastFormatA;
        $formatB = $lastFormatB;
        continue;
    }
    var_dump('win it');
    $doc = str_get_html($content);
    $mayTable = $doc->find('table');
    if (count($mayTable) > 0) {
        $table = $mayTable[0];
        $mayTableBody = $table->find('tbody');
        if (count($mayTableBody) > 0) {
            $table = $mayTableBody[0];
        }
        for ($i = 0;$i < count($table->children);$i++) {
            $tr = $table->children[$i];
            if ($i === 0) {
                preg_match('/Stand:( +|)([0-9]{2})\.([0-9]{2}|[0-9]{1})( +|),( +|)([0-9]{2}):([0-9]{2})( +|)Uhr/', trim($tr->children[0]->children[0]->plaintext), $mayDate);
                if (count($mayDate) > 0) {
                    var_dump($mayDate);
                    var_dump('hit updated');
                    $innerUpdated = strtotime($mayDate[3].'/'.$mayDate[2].'/2020 '.$mayDate[6].':'.$mayDate[7]);
                }
                continue;
            }
            $key = html_entity_decode(trimmed($tr->children[0]->plaintext));
            if ($key === "Brandenburg gesamt") {
                continue;
            }
            var_dump('key='.$key);
            if (!array_key_exists($key, $data)) {
                $data[$key] = 0;
            }
            $value = trimmed($tr->children[2]->plaintext) === '---' ? '0' : trimmed($tr->children[2]->plaintext);
            var_dump('key='.$value);
            $data[$key] += intval($value);
        }
        saveSubRegion('brandenburg', 'https://msgiv.brandenburg.de/msgiv/de/presse/pressemitteilungen/detail/~'.$formatA.'-corona-faelle-stand-'.$formatB, $updated, $data, [], [], [], [], $innerUpdated);
    } else {
        var_dump('missing brandenburg table');
    }
}*/

// Schleswig-Holstein
$updated = time();
$data = [];
$currentRegion = null;
$doc = str_get_html(contents('https://www.schleswig-holstein.de/DE/Landesregierung/I/Presse/_documents/Corona-Liste_Kreise.html'));

$mayTable = $doc->find('.bodyText table');
if (count($mayTable) > 0) {
    $table = $mayTable[0];
    $mayTableBody = $table->find('tbody');
    if (count($mayTableBody) > 0) {
        $table = $mayTableBody[0];
    }
    foreach ($table->children as $tr) {
        var_dump(trim($tr->children[0]->plaintext));
        if (trim($tr->children[0]->plaintext) == "SUMME") {
            continue;
        }
        $data[trim($tr->children[0]->plaintext)] = intval(trim($tr->children[2]->plaintext));
    }
} else {
    var_dump('missing schleswig-holstein table');
}

saveSubRegion('schleswig-Holstein', 'https://www.schleswig-holstein.de/DE/Landesregierung/I/Presse/_documents/Corona-Liste_Kreise.html', $updated, $data);

// Bayern
$datas = [];
$dataArr = [];
$updated = time();
$currentRegion = null;
$doc = str_get_html(contents('https://www.lgl.bayern.de/gesundheit/infektionsschutz/infektionskrankheiten_a_z/coronavirus/karte_coronavirus/index.htm'));
$pseudoKey = 0;
foreach ($doc->find('table') as $table) {
    $dataKey = ''.$pseudoKey;
    $pseudoKey++;
    $data = [];
    $mayTableBody = $table->find('tbody');
    if (count($mayTableBody) > 0) {
        $table = $mayTableBody[0];
    }
    
    for ($i = 0;$i < count($table->children);$i++) {
        $tr = $table->children[$i];
        if (count($tr->children) <= 1) {
            continue;
        }
        $key = trimmed($tr->children[0]->plaintext);
        if ($i === 0) {
            $dataKey = $key;
            continue;
        }
        if ($key === 'Gesamtergebnis') {
            continue;
        }
        if (!array_key_exists($key, $data)) {
            $data[$key] = 0;
        }
        $data[$key] += intval(trimmed(explode("(", $tr->children[1]->plaintext)[0]));
    }
    $datas[$dataKey] = $data;
    $dataArr[] = $data;
}
$data = [];
if (array_key_exists('Land-/Stadtkreis', $datas)) {
    $data = $datas['Land-/Stadtkreis'];
} else if (count($dataArr) > 1) {
    $data = $dataArr[2];
} else {
    var_dump('BOOOM Bayern');
}
saveSubRegion('bayern', 'https://www.lgl.bayern.de/gesundheit/infektionsschutz/infektionskrankheiten_a_z/coronavirus/karte_coronavirus/index.htm', $updated, $data);

// rki
$cases = [];
$deaths = [];
$raw = json_decode(contents('https://services7.arcgis.com/mOBPykOjAyBO2ZKk/arcgis/rest/services/RKI_Landkreisdaten/FeatureServer/0/query?f=json&where=1%3D1&returnGeometry=false&spatialRel=esriSpatialRelIntersects&outFields=*&orderByFields=cases%20desc&resultOffset=0&resultRecordCount=1000&cacheHint=true'), true);
// 'cases', $already, $feature['GEN'], [$feature['GEN'], $feature['county']], $feature['cases']
function mergeSubRegion($key, $already, $neededKey, $possibleKeys, $value) {
    var_dump('merge '.$key);
    var_dump(array_keys($already));
    var_dump('neededKey='.$neededKey);
    var_dump('possibleKeys='.implode(',', $possibleKeys));
    var_dump('value='.$value);
    var_dump($already);
    if (array_key_exists($key, $already)) {
        foreach ($already[$key] as $subKey => $_) {
            if (!in_array($subKey, $possibleKeys)) {
                continue;
            }
            var_dump('subKey='.$subKey);
            var_dump('let data stay');
            return $already;
        }
        var_dump('add new sub region');
        var_dump('wo='.$neededKey);
        var_dump('value='.$value);
        if (!is_array($already[$key])) {
            $already[$key] = [];
        }
        $already[$key][$neededKey] = $value;
    } else {
        var_dump('add new key');
        var_dump('wo='.$neededKey);
        var_dump('value='.$value);
        $already[$key] = [$neededKey => $value];
    }
    return $already;
}

foreach ($raw['features'] as $rkiFeature) {
    $name = strtolower($rkiFeature['attributes']['BL'][0]).substr($rkiFeature['attributes']['BL'], 1);
    var_dump($rkiFeature);
    if ($rkiFeature['attributes']['GEN'] === 'Bremerhaven' || in_array($rkiFeature['attributes']['BL'], ['Hessen', 'Nordrhein-Westfalen', 'Rheinland-Pfalz', 'Thüringen'])) {
        continue;
    }
    $filepath = $name.'.json';
    if ($rkiFeature['attributes']['BEZ'] === 'Kreisfreie Stadt' && $rkiFeature['attributes']['BL'] === $rkiFeature['attributes']['GEN']) {
    	//$name = strtolower($rkiFeature['attributes']['GEN'][0]).substr($rkiFeature['attributes']['GEN'], 1);
        var_dump('save caused '.$rkiFeature['attributes']['BEZ']);
        var_dump($name);
        $filepath = $name.'-germany.json';
        $updated = time();
        $innerUpdated = $updated;
        var_dump($filepath);

        $obj = array('source' => 'https://corona.rki.de/', 'updated' => $innerUpdated, 'cases' => $rkiFeature['attributes']['cases'], 'deaths' => $rkiFeature['attributes']['deaths']);
        var_dump($obj);
        var_dump(json_encode($obj));
        $fhandle = fopen($name.'-germany-'.$updated.'.json.hist', 'w+');
        fwrite($fhandle, json_encode($obj));
        fclose($fhandle);
        $fhandle = fopen($filepath, 'w+');
        fwrite($fhandle, json_encode($obj));
        fclose($fhandle);
        continue;
    }

    var_dump($name);
    if (!file_exists($filepath)) {
        var_dump('save anyways');
        var_dump($name);
        saveSubRegion($name, 'https://corona.rki.de/', time(), [$rkiFeature['attributes']['GEN'] => $rkiFeature['attributes']['cases']], [$rkiFeature['attributes']['GEN'] => $rkiFeature['attributes']['deaths']]);
        continue;
    }
    var_dump('the merged way');


    $already = json_decode(file_get_contents($filepath), true);
    var_dump($already);
    var_dump($rkiFeature);
    $oldAlready = $already;
    $already = mergeSubRegion('cases', $already, $rkiFeature['attributes']['GEN'], [$rkiFeature['attributes']['GEN'], $rkiFeature['attributes']['county']], $rkiFeature['attributes']['cases']);
    $already = mergeSubRegion('deaths', $already, $rkiFeature['attributes']['GEN'], [$rkiFeature['attributes']['GEN'], $rkiFeature['attributes']['county']], $rkiFeature['attributes']['deaths']);

    /*if (empty(array_diff($already, $oldAlready))) {
        continue;
    }*/

    if ($already['source'] !== 'https://corona.rki.de/') {
        if (!array_key_exists('mergedWith', $already)) {
            $already['mergedWith'][] = [];
        }
        if (in_array(['source' => 'https://corona.rki.de/', 'updated' => time()], $already['mergedWith'])) {
            continue;
        }
        $already['mergedWith'][] = ['source' => 'https://corona.rki.de/', 'updated' => time()];
    }
    $fhandle = fopen($name.'-'.time().'.json.hist', 'w+');
    fwrite($fhandle, json_encode($already));
    fclose($fhandle);
    $fhandle = fopen($filepath, 'w+');
    fwrite($fhandle, json_encode($already));
    fclose($fhandle);
}

print("finish\n");