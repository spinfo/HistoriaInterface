
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
        inputs.push(createCoordInputElem('lat', latLng.lat, idx));
        inputs.push(createCoordInputElem('lon', latLng.lng, idx));
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
    var latLngs;
    if(binding.latLngs.length > 0) {
        latLngs = binding.latLngs;
    } else {
        latLngs = MapUtil.parseCoordinates(document.getElementById('shtm_map_area'));
    }
    map.fitBounds(L.latLngBounds(latLngs));

    // LEAFLET DRAW CONFIGURATION
    // The following configures leaflet draw, which allows us to edit the tour
    // track.
    // Initialize the FeatureGroup to store editable layers
    var editableItems = new L.FeatureGroup();
    map.addLayer(editableItems);

    // Initialize two draw controls, one for editing and one for drawing
    var drawOptions = {
        draw: {
            polyline: {
                allowIntersection: true,
            },
            marker: false,
            circle: false,
            polygon: false,
            rectangle: false
        },
        edit: false
    };
    var editOptions = {
        draw: false,
        edit: {
            featureGroup: editableItems
        }
    }
    var drawControl = new L.Control.Draw(drawOptions);
    var editControl = new L.Control.Draw(editOptions);
    // at first we set the control to draw, this will change once a line is
    // drawn (either by the user or programmatically)
    map.addControl(drawControl);

    // a hook called when a fresh line is drawn
    map.on('draw:created', function(e) {
        var type = e.layerType,
            layer = e.layer;

        if(type != 'polyline') {
            console.warn("Non-Polyline layer drawn.");
            return;
        }
        // add all latlngs to the form binding and refresh the form
        binding.clear();
        layer._latlngs.forEach(function(latLng, idx) {
            binding.addLatLng(latLng);
        });
        binding.display(createInputElements);

        // the layer (i.e. the line) may now be edited
        layer.addTo(editableItems);

        // since there may only be one line edited disable the toolbar for
        // drawing and enable the one for editing
        drawControl.remove();
        editControl.addTo(map);
    });

    // a hook called after saving the edited line
    map.on('draw:edited', function(e) {
        // layers updated
        var layers = e.layers;

        if(layers.length > 0) {
            console.warn("More than one layer was edited.");
        }

        // remove the old latLngs from the form binding and add the new ones
        binding.clear();
        layers.eachLayer(function(layer) {
            layer._latlngs.forEach(function(latLng, idx) {
                binding.addLatLng(latLng);
            });
        });
        // tell the form binding to re-render the form
        binding.display(createInputElements);
    });

    // a hook called after a layer has been removed from the map
    map.on('draw:deleted', function(e) {
        // remove all coordinates from the binding and refresh the form
        binding.clear();
        binding.display(createInputElements);
        // remove the edit control and replace it by the draw control
        editControl.remove();
        drawControl.addTo(map);
    });

    // initialize a polyline from the latLngs
    if(binding.latLngs.length > 0) {
        line = new L.Draw.Polyline(map, drawOptions.draw.polyline);
        line.enable();

        // we need to do some manual initialization (leaflet-draw seems not to
        // intend for us to programmatically create a drawing)
        line.addHooks();
        line._currentLatLng = binding.latLngs[0];
        binding.latLngs.forEach(function(latLng) {
            line.addVertex(latLng);
        });
        line._finishShape();
    }



</script>