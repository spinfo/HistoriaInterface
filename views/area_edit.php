

<?php $this->include($this->view_helper::single_area_header_template()) ?>

<?php $this->include($this->view_helper::area_map_template()) ?>

<form action=admin.php?<?php echo $this->action_params ?> method="post"
    class="shtm_right_from_map shtm_form shtm_area_form">

    <div class="shtm_form_line">
        <label for="shtm_area_name">Name:</label>
        <input type="text" id="shtm_area_name" name="shtm_area[name]" value="<?php echo $this->area->name ?>">
    </div>

    <div id="shtm_area_coordinate_inputs">
        <div>
            <br>
            <div class="shtm_form_line">
                Süd/West:
            </div>
            <div class="shtm_form_line">
                <label for="shtm_coordinate1_lat">Lat:</label>
                <input id="shtm_coordinate1_lat" type="text" name="shtm_area[c1_lat]" value="<?php echo $this->coord_format($this->area->coordinate1->lat) ?>">
            </div>
            <div class="shtm_form_line">
                <label for="shtm_coordinate1_lon">Lon:</label>
                <input id="shtm_coordinate1_lon" type="text" name="shtm_area[c1_lon]" value="<?php echo $this->coord_format($this->area->coordinate1->lon) ?>">
            </div>
        </div>
        <div>
            <br>
            <div class="shtm_form_line">
                Nord/Ost:
            </div>
            <div class="shtm_form_line">
                <label for="shtm_coordinate2_lat">Lat:</label>
                <input id="shtm_coordinate2_lat" type="text" name="shtm_area[c2_lat]" value="<?php echo $this->coord_format($this->area->coordinate2->lat) ?>">
            </div>
            <div class="shtm_form_line">
                <label for="shtm_coordinate2_lon">Lon:</label>
                <input id="shtm_coordinate2_lon" type="text" name="shtm_area[c2_lon]" value="<?php echo $this->coord_format($this->area->coordinate2->lon) ?>">
            </div>
        </div>
    </div>

    <div class="shtm_button">
        <button type="submit">Speichern</button>
    </div>

</form>

<script type="text/javascript">

    // require the form binding script
    <?php $this->include($this->view_helper::coordinate_form_binding_js()) ?>

    // creates a single <input> tag for an area's coordinates, wraps it in a
    // a <div> and returns the latter
    function createCoordInputElem (key, value) {
        var div = document.createElement('div');

        var input = document.createElement('input');
        var name = 'shtm_area[' + key + ']';
        input.setAttribute('name', name);
        input.setAttribute('value', value);
        input.setAttribute('type', 'text');
        input.setAttribute('id', 'shtm_area_' + key);

        var label = document.createElement('label');
        label.setAttribute('for', 'shtm_area_' + key);
        if(key.substr(-3) === 'lat') {
            label.innerHTML = 'Lat: ';
        } else if(key.substr(-3) === 'lon') {
            label.innerHTML = 'Lon: ';
        }

        div.setAttribute('class', 'shtm_form_line');
        div.appendChild(label);
        div.appendChild(input);
        return div;
    };

    // A callback for the CoordinateFormBinding to render a coordinate's
    // input fields
    createInputElements = function(latLng, idx) {

        var headings = ['Süd/West:', 'Nord/Ost:'];
        var prefixes = ['c1_', 'c2_'];

        var container = document.createElement('div');

        // apend the div for the heading
        div = document.createElement('div');
        div.setAttribute('class', 'shtm_form_line');
        div.innerHTML = headings[idx];
        container.appendChild(document.createElement('br'));
        container.appendChild(div);

        // append div for latitude
        var name = prefixes[idx] + 'lat';
        var div = createCoordInputElem(name, MapUtil.formatCoordValue(latLng.lat));
        container.appendChild(div);
        // append div for latitude and longitue
        var name = prefixes[idx] + 'lon';
        var div = createCoordInputElem(name, MapUtil.formatCoordValue(latLng.lng));
        container.appendChild(div);

        return container;
    }

    // parse the area's coordinates
    latLngs = MapUtil.parseCoordinates(document.getElementById('shtm_map'));
    var areaBounds = L.latLngBounds(latLngs);

    // create a leaflet map
    var map = MapUtil.createMap('shtm_map');
    map.fitBounds(areaBounds);

    // create a form binding and fill it with the latLng of the place
    binding = CoordinateFormBinding.createWithElems(
        'shtm_map', 'shtm_area_coordinate_inputs');

    binding.display(createInputElements);

    // Initialize options for drawing, set every other element to false
    var drawOptions = {
        draw: {
            polyline: false,
            marker: false,
            circle: false,
            polygon: false,
            rectangle: {}
        },
        edit: false
    };
    drawOptions.draw.rectangle.shapeOptions = MapUtil.rectangleShape();

    // create the leaflet draw environment
    MapUtil.create_leaflet_draw_for_single_item(map, 'marker', binding,
        createInputElements, drawOptions);



    // if we are on the edit view, init rectangle to edit
    if(window.location.href.includes('shtm_a=edit')) {
        // we have to fiddle a bit with draw.js internals here, reference:
        // https://github.com/Leaflet/Leaflet.draw/blob/master/src/draw/handler/Draw.Marker.js
        var rect = new L.Draw.Rectangle(map, drawOptions.draw.rectangle);
        // rect._startLatLng = latLngs[0];
        // rect._drawShape(latLngs[1]);
        rect._shape = new L.Rectangle(areaBounds, MapUtil.rectangleShape());
        // rect._shape.setBounds(areaBounds);
        rect._fireCreatedEvent();
    }
    // we are on the new view (or something went wrong), center on somewhere in
    // Germany and zoom out far
    else {
        map.setView([50.5,10.5], 3);
    }


</script>
