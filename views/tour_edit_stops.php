
<?php $this->include($this->view_helper::single_tour_header_template()) ?>

<?php $this->include($this->view_helper::tour_map_template()) ?>

<form id="shtm_mapstop_positions_form" action="admin.php?<?php echo $this->route_params::update_tour_stops($this->tour->id) ?>" method="post"
    class="shtm_right_from_map shtm_form">

    <div style="margin-bottom: 12px">
        <a href="admin.php?<?php echo $this->route_params::new_mapstop($this->tour->id) ?>">Stop hinzufügen</a>
    </div>

    <div id="shtm_mapstops_menu">

        <?php for ($i = 1; $i <= count($this->tour->mapstops); $i++): ?>
            <?php $mapstop = $this->tour->mapstops[$i - 1] ?>

            <div class="shtm_form_line" style="margin-bottom: 7px">

                <div style="height: 100%; float: left; margin-right: 5px;">
                    <select name="shtm_tour[mapstop_ids][<?php echo $mapstop->id ?>]"
                        onChange="rerenderMapstopPositions(this)">
                        <?php for($j = 1; $j <= count($this->tour->mapstops) ; $j++): ?>
                            <option <?php echo (($j == $i) ? 'selected="true"' : '') ?>><?php echo $j ?></option>
                        <?php endfor ?>
                    </select>
                </div>

                <div style="float: left">
                    <b><?php echo $mapstop->name ?></b>&nbsp;<a href="admin.php?<?php echo $this->route_params::edit_mapstop($mapstop->id) ?>">Bearbeiten</a>&nbsp;|&nbsp;<a href="admin.php?<?php echo $this->route_params::delete_mapstop($mapstop->id) ?>">Löschen</a><br>
                    <?php echo $mapstop->description ?><br>
                </div>

                <div style="clear: both"></div>
            </div>
        <?php endfor ?>

    </div>

    <div class="shtm_button">
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

    // fit the map to either mapstops, track or (if all else fails) to the area
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

    // the list of all mapstops with positions
    var formLines = document.getElementsByClassName('shtm_form_line');

    // add a callback to every select box that rearranges the options such that
    // every position is present once and the order reflects the user's choice
    var rerenderMapstopPositions = function(elem) {
        // the "form line" we want to change is two levels up
        var elemLine = elem.parentNode.parentNode;
        // read the form lines into an array for easier editing
        var lines = [];
        for(var i = 0; i < formLines.length; i++) {
            lines.push(formLines[i]);
        }
        // determine the old and new positions
        var oldPos = lines.indexOf(elemLine);
        var newPos = elem.selectedIndex;
        // remove at the old position and insert at the new position
        lines.splice(oldPos, 1);
        if(newPos == lines.length) {
            lines.push(elemLine);
        } else {
            lines.splice(newPos, 0, elemLine);
        }
        // set the selection to approriate values (go two levels down again)
        for(var i = 0; i < lines.length; i++) {
            lines[i].children[0].children[0].children[i].selected = true;
        }
        // remove old order from DOM and put in the new one
        var container = document.getElementById('shtm_mapstops_menu');
        while(container.firstChild) {
            container.removeChild(container.firstChild);
        }
        for(var i = 0; i < lines.length; i++) {
            container.appendChild(lines[i]);
        }
    };


</script>

