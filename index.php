<?php
$_GLOBALS['FROM_FILE'] = true;
include 'api.php';
$possibleCacheFiles = [];
foreach (array_keys($result) as $rootRegion) {
    if (is_array($result[$rootRegion]['cases'])) {
        foreach (array_keys($result[$rootRegion]['cases']) as $subRegion) {
            $possibleCacheFiles[$subRegion.', '.$rootRegion.', Germany'] = 'cache/'.strtolower($subRegion).'-'.strtolower($rootRegion).'-germany';
        }
    } else if (is_int($result[$rootRegion]['cases'])) {
        $possibleCacheFiles[$rootRegion.', Germany'] = 'cache/'.strtolower($rootRegion).'-germany';
    }
}
?>
<link rel="stylesheet" href="leaflet.css"/>
<script type="text/javascript">
  function toggle(id) {
    var div = document.getElementById(id);
    if (div.style.display !== 'none') {
        div.style.display = 'none';
    }
    else {
        div.style.display = 'block';
    }
  }
</script>
<script src="leaflet.js"></script>
<div style="background-color: #999; opacity: 0.5; text-align: right; position: fixed;right: 10px;top: 10px;z-index: 99999999999999999999999999999999999999999999999999999999;"><input type="submit" value="Aktualisieren" onclick="JavaScript:refresh();"><br><input type="submit" value="Quellen" onclick="JavaScript:toggle('sources');"><br><span style="display: none;" id="sources"></span></div>
<div id="total" style="background-color: #999; text-align: left; position: fixed;left: 10px;bottom: 10px;z-index: 99999999999999999999999999999999999999999999999999999999;"></div>
<div id="map" style="width: 100%;height: 100%;"></div>
<script type="text/javascript">
    var map = null,
        factor = 10,
        boundingGroup = [],
        cache = [],
        requests = [],
        sources = [],
        totalCount = 0,
        totalDeaths = 0;
    <?php/*
    foreach ($possibleCacheFiles as $key => $filePath) {
      if (!file_exists($filePath)) {
        continue;
      }
    ?>
      cache[<?php echo json_encode($key); ?>] = JSON.parse(<?php echo json_encode(file_get_contents($filePath)); ?>);
    <?php
    }*/
    ?>
    function calcColor(count) {
    	var hexRed = (parseInt(count * factor) > 255 ? 255 : parseInt(count * factor)).toString(16),
    		hexGreen = (parseInt(count * factor) < 255 ? 121 : parseInt(count * factor)-255).toString(16),
    		hexBlue = (parseInt(count * factor) < 610 ? 121 : parseInt(count * factor)-610).toString(16);
        hexRed = hexRed.length == 1 ? '0' + hexRed : hexRed;
		hexGreen = hexGreen.length == 1 ? '0' + hexGreen : hexGreen;
		hexBlue = hexBlue.length == 1 ? '0' + hexBlue : hexBlue;

        return '#' + hexRed + '7979';
    }
    if (localStorage && localStorage.getItem('cache') !== null) {
      cache = JSON.parse(localStorage.getItem('cache'));
    }

    function parseNominatim(txt, addr, count, deaths, incidence) {
      try {
        var nominatim = JSON.parse(txt),
            currentType = null,
            geo = null;
    
      if (!nominatim.success) {
        return;
      }
    
      cache[addr] = txt;
      nominatim = nominatim.data;
    
      if (nominatim.length === 0) {
        return;
      }
    
      for (var i = 0; i < nominatim.length; i++) {
        if (['Polygon', 'MultiPolygon'].indexOf(nominatim[i].geojson.type) !== -1 || (['Polygon', 'MultiPolygon'].indexOf(nominatim[i].geojson.type) === -1 || currentType !== null) && nominatim[i].geojson.type === 'Point') {
          currentType = nominatim[i].geojson.type;
    
          if (nominatim[i].geojson.type === 'Point') {
            geo = L.circle([nominatim[i].geojson.coordinates[1], nominatim[i].geojson.coordinates[0]], 150*count);
            geo.setStyle({fillColor: calcColor(count), color: calcColor(count)});
          } else {
            geo = L.geoJSON([{
              "type": "Feature",
              "properties": {
                "name": count
              },
              "geometry": nominatim[i].geojson
            }], {
              style: function style(feature) {
                return {
                  color: calcColor(feature.properties.name)
                };
              }
            });
          }
    
          if (['Polygon', 'MultiPolygon'].indexOf(nominatim[i].geojson.type) !== -1) {
            break;
          }
        }
      }
      geo.bindTooltip(addr+"<br>Fälle: "+count+"<br>Tote: "+deaths+"<br>Inzidenz: "+incidence).openTooltip();
      boundingGroup.push(geo);
      geo.addTo(map);
      } catch(ex) {
        console.log(addr+':\n'+ex+'\nJSON:\n'+txt);
      }
    }
    
    function refresh() {
      var ownReq = new XMLHttpRequest(),
          osmL = L.tileLayer('https://{s}.covid19.perfectykills.de/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
      });
      document.getElementById('sources').innerHTML = '';
      boundingGroup = [];
      for (var p = 0; p < requests.length; p++) {
        requests[p].abort();
      }

      totalCount = 0;
      totalDeaths = 0;
    
      if (map !== null) {
        map.remove();
        map = null;
      }
    
      map = L.map('map').setView([50.55, 9.5], 6.45);
      osmL.addTo(map);
    
      if (ownReq) {
        ownReq.open('GET', 'api.php?time='+new Date().getTime(), true);
    
        ownReq.onreadystatechange = function () {
          if (ownReq.readyState == 4) {
            var regions = JSON.parse(ownReq.responseText);
            for (var _rootRegion in regions) {
              var SubRegionRootRegionRequest = (function(__regions, __subRegion, __rootRegion) {
                var self = this,
                    count = __regions[__rootRegion].cases[__subRegion],
                    deaths = __regions[__rootRegion].deaths[__subRegion] === undefined ? 'keine Daten' : __regions[__rootRegion].deaths[__subRegion],
                    incidence = __regions[__rootRegion].incidence[__subRegion] === undefined ? 'noch nicht berechnet' : __regions[__rootRegion].incidence[__subRegion],
                    geos = __regions[__rootRegion].geos === undefined ? null : __regions[__rootRegion].geos[__subRegion],
                    nominatimReq = new XMLHttpRequest();
                if (__subRegion.length >= 5 && __subRegion.substr(0, 5) === 'Stadt') {
                  __subRegion = __subRegion.substr(5).trim();
                }
                var addr = __subRegion.replace('(Stadtkreis)', '').replace('(kreisfreie Stadt)', '').trim() + ', ' + __rootRegion+', Germany';
                if (cache[addr] === undefined) {
                  nominatimReq.open('GET', 'cache.php?q=' + encodeURIComponent(addr) + (geos !== null ? '&geoid='+geos : '') + '&time='+new Date().getTime(), true);
    
                  nominatimReq.onreadystatechange = function () {
                    if (nominatimReq.readyState == 4) {
                      parseNominatim(nominatimReq.responseText, addr, count, deaths, incidence);
                    }
                  };
    
                  nominatimReq.send(null);
                  requests.push(nominatimReq);
                } else {
                  parseNominatim(cache[addr], addr, count, deaths, incidence);
                }

                return self;
              }),
              DirectAddrRequest = (function(__count, __deaths, __addr){
                var self = this,
                    nominatimReq = new XMLHttpRequest();
                if (cache[__addr] === undefined) {
                  nominatimReq.open('GET', 'cache.php?q=' + encodeURIComponent(__addr), true);
    
                  nominatimReq.onreadystatechange = function () {
                    if (nominatimReq.readyState == 4) {
                      parseNominatim(nominatimReq.responseText, __addr, __count, __deaths, 'noch nicht berechnet');
                    }
                  };
    
                  nominatimReq.send(null);
                  requests.push(nominatimReq);
                } else {
                  parseNominatim(cache[__addr],__addr, __count, __deaths, 'noch nicht berechnet');
                }
                return self;
              });
              
              if (sources.indexOf(regions[_rootRegion].source+', '+new Date(regions[_rootRegion].updated*1000).toLocaleString()) === -1) {
              	sources.push(regions[_rootRegion].source);
                var sourceSpan = document.createElement('span');
                sourceSpan.innerHTML = regions[_rootRegion].source+', '+new Date(regions[_rootRegion].updated*1000).toLocaleString();
                document.getElementById('sources').appendChild(sourceSpan);
                document.getElementById('sources').appendChild(document.createElement('br'));
              }
              
              if (typeof regions[_rootRegion].cases === 'object') {
                for (var _subRegion in regions[_rootRegion].cases) {
                  totalCount += regions[_rootRegion].cases[_subRegion];
                  new SubRegionRootRegionRequest(regions, _subRegion, _rootRegion);
                }
                for (var _subRegion in regions[_rootRegion].deaths) {
                  totalDeaths += regions[_rootRegion].deaths[_subRegion];
                }
              } else {
                new DirectAddrRequest(regions[_rootRegion].cases, regions[_rootRegion].deaths, _rootRegion+', Germany');
                totalCount += regions[_rootRegion].cases;
                totalDeaths += regions[_rootRegion].deaths;
              }
            }
            document.getElementById('total').innerHTML = '<b>Gesamt:</b><br>Fälle:'+totalCount+'<br>Tote:'+totalDeaths;
          }
        };
    
        ownReq.send(null);
        requests.push(ownReq);
      }
    }
    
    refresh();
</script>