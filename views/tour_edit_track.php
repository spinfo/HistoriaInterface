
<?php $this->include($this->view_helper::single_tour_header_template()) ?>

<?php $this->include($this->view_helper::tour_map_template()) ?>

<form id="shtm_map_form" class="shtm_form" action="admin.php?<?php echo $this->route_params::update_tour($this->tour->id) ?>" method="post"
    class="shtm_right_from_map shtm_form">

    <div id="shtm_tour_track_inputs">
        <!-- input fields added dynamically -->
    </div>

    <div class="shtm_button">
        <button type="submit">Speichern</button>
    </div>

</form>


<script type="text/javascript">

    <?php $this->include($this->view_helper::map_util_js()) ?>
    <?php $this->include($this->view_helper::coordinate_form_binding_js()) ?>

    // create a single <input> tag for a coordinate's value
    function createCoordInputElem (key, value, index) {
        var input = document.createElement('input');
        var name = 'shtm_tour[coordinates][' + index + '][' + key + ']';
        input.setAttribute('name', name);
        input.setAttribute('value', value);
        input.setAttribute('type', 'text');
        return input;
    };

    // A callback for the CoordinateFormBinding to render a coordinate's input
    // fields
    createInputElements = function(latLng, idx) {
        var inputs = [];
        var lat = MapUtil.formatCoordValue(latLng.lat);
        var lon = MapUtil.formatCoordValue(latLng.lng);
        inputs.push(createCoordInputElem('lat', lat, idx));
        inputs.push(createCoordInputElem('lon', lon, idx));
        var input = createCoordInputElem('id', latLng._shtm_cid, idx);
        input.setAttribute('type', 'hidden');
        inputs.push(input);

        var container = document.createElement('div');
        inputs.forEach(function(inputElem) {
            container.appendChild(inputElem);
        });
        container.innerHTML = (idx + 1) + ': ' + container.innerHTML;
        return container;
    }

    // create a leaflet map object to contain everything
    var map = MapUtil.createMap('shtm_map');

    // create a form binding and fill it with the latLngs
    binding = CoordinateFormBinding.createWithElems(
        'shtm_map_track', 'shtm_tour_track_inputs');
    binding.display(createInputElements);

    // parse mapstops into array
    var mapstopElems = document.getElementById('shtm_map_mapstops').children;
    var mapstops = [];
    for(var i = 0; i < mapstopElems.length; i++) {
        mapstops.push(MapUtil.mapstopFromElem(mapstopElems[i]));
    }

    // fit map to the binding OR if no track coordinates exist yet, fit it to
    // the mapstops OR if there are no mapstops yet, fit it to the area
    var latLngs;
    if(binding.latLngs.length > 0) {
        latLngs = binding.latLngs;
    } else if(mapstops.length > 0) {
        latLngs = mapstops.map(function(mapstop) {
            return mapstop.latLng;
        });
    } else {
        latLngs = MapUtil.parseCoordinates(document.getElementById('shtm_map_area'));
    }
    map.fitBounds(L.latLngBounds(latLngs));

    // Initialize options for drawing, set every other element to false
    var drawOptions = {
        draw: {
            polyline: {
                allowIntersection: true,
                shapeOptions: {}
            },
            marker: false,
            circle: false,
            polygon: false,
            rectangle: false
        },
        edit: false
    };
    drawOptions.draw.polyline.shapeOptions = MapUtil.lineShape();

    // create the leaflet draw environment
    MapUtil.create_leaflet_draw_for_single_item(map, 'polyline', binding,
        createInputElements, drawOptions);

    // initialize a polyline from the latLngs
    if(binding.latLngs.length > 0) {
        line = new L.Draw.Polyline(map, drawOptions.draw.polyline);
        line.enable();

        // we need to do some manual initialization (leaflet-draw seems not to
        // intend for us to programmatically create a drawing), reference:
        // https://github.com/Leaflet/Leaflet.draw/blob/master/src/draw/handler/Draw.Polyline.js
        line.addHooks();
        line._currentLatLng = binding.latLngs[0];
        binding.latLngs.forEach(function(latLng) {
            line.addVertex(latLng);
        });
        line._finishShape();
    }

    // if mapstops are present display them as markers on the map
    var mapstopsGroup = L.layerGroup();
    for(var i = 0; i < mapstops.length; i++) {
        var mapstop = mapstops[i];
        var marker = L.marker(mapstop.latLng, { zIndexOffset: -1000, opacity: 1.0 });
        marker.bindPopup('<b>' + mapstop.name + '</b><br>' + mapstop.description);
        mapstopsGroup.addLayer(marker);
    }
    mapstopsGroup.addTo(map);



</script>