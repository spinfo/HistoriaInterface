
<?php $this->include($this->view_helper::single_tour_header_template()) ?>

<?php $this->include($this->view_helper::tour_map_template()) ?>

<form id="shtm_map_form" action=admin.php?<?php echo $this->route_params::update_tour($tour->id) ?> method="post"
    class="shtm_right_from_map">

    <?php foreach ($this->tour->mapstops as $mapstop): ?>
        <b><?php echo $mapstop->name ?></b><br>
        <?php echo $mapstop->description ?><br>
        --------------------------------<br>
    <?php endforeach ?>

    <div class="button">
        <button type="submit">Speichern</button>
    </div>

</form>



<script type="text/javascript">

    <?php $this->include($this->view_helper::map_util_js()) ?>

    // set up the leaflet map
    var map = MapUtil.createMap('shtm_map');

    // parse mapstops data and display on the map
    var mapstopElems = document.getElementById('shtm_map_mapstops').children;
    var mapstops = [];
    var mapstopsGroup = L.layerGroup();
    for(var i = 0; i < mapstopElems.length; i++) {
        var mapstop = MapUtil.mapstopFromElem(mapstopElems[i]);
        mapstops.push(mapstop);

        var marker = L.marker(mapstop.latLng, { zIndexOffset: -1000, opacity: 1.0 });
        marker.bindPopup('<b>' + mapstop.name + '</b><br>' + mapstop.description);
        mapstopsGroup.addLayer(marker);
    }
    mapstopsGroup.addTo(map);

    // parse track data and display as a polyline on the map
    var trackLatLngs = MapUtil.parseCoordinates(document.getElementById('shtm_map_track'));
    var line = L.polyline(trackLatLngs, MapUtil.lineShape());
    line.addTo(map);

    // fit the map to either mapstops, track or (if all else fails) the area
    var latLngs;
    if(mapstops.length > 0) {
        latLngs = mapstops.map(function(mapstop) {
            return mapstop.latLng;
        });
    } else if(trackLatLngs.length > 0) {
        latLngs = trackLatLngs
    } else {
        latLngs = MapUtil.parseCoordinates(document.getElementById('shtm_map_area'));
    }
    map.fitBounds(L.latLngBounds(latLngs));

</script>

