
<?php $this->include($this->view_helper::single_tour_header_template()) ?>

<div id="shtm_map" class="shtm_map_left" style="height: 500px; width: 500px;">
    <div id="shtm_map_area">
        <coordinate cid="<?php echo $this->area->coordinate1->id ?>"
            lat="<?php echo $this->area->coordinate1->lat ?>"
            lon="<?php echo $this->area->coordinate1->lon ?>"></coordinate>

        <coordinate cid="<?php echo $this->area->coordinate2->id ?>"
            lat="<?php echo $this->area->coordinate2->lat ?>"
            lon="<?php echo $this->area->coordinate2->lon ?>"></coordinate>
    </div>
    <div id="shtm_map_track">
        <?php foreach ($this->tour->coordinates as $c): ?>
            <coordinate cid="<?php echo $c->id ?>" lat="<?php echo $c->lat ?>" lon="<?php echo $c->lon ?>"></coordinate>
        <?php endforeach ?>
    </div>
</div>

<form id="shtm_map_form" action=admin.php?<?php echo $this->route_params::update_tour($tour->id) ?> method="post"
    class="shtm_right_from_map">

    <div id="shtm_tour_track_inputs">
        <!-- input fields added dynamically -->
    </div>

    <div class="button">
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

    // fit map to the binding OR if no track coordinates exist yet, fit it to
    // the mapstops OR if there are no mapstops yet, fit it to the area
    // TODO: really do that
    var latLngs;
    if(binding.latLngs.length > 0) {
        latLngs = binding.latLngs;
    } else {
        latLngs = MapUtil.parseCoordinates(document.getElementById('shtm_map_area'));
    }
    map.fitBounds(L.latLngBounds(latLngs));

    // Initialize options for drawing, set every other element to false
    var drawOptions = {
        draw: {
            polyline: { allowIntersection: true },
            marker: false,
            circle: false,
            polygon: false,
            rectangle: false
        },
        edit: false
    };
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



</script>