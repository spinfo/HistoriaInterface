
<h1>Tourweg bearbeiten</h1>

<h3>
    <i><?php echo $this->tour->name ?></i>
</h3>

<div id="shtm-map" style="height: 500px; width: 500px; float: left">
    <div id="shtm-map-area">
        <coordinate cid="<?php echo $this->area->coordinate1->id ?>"
            lat="<?php echo $this->area->coordinate1->lat ?>"
            lon="<?php echo $this->area->coordinate1->lon ?>"></coordinate>

        <coordinate cid="<?php echo $this->area->coordinate2->id ?>"
            lat="<?php echo $this->area->coordinate2->lat ?>"
            lon="<?php echo $this->area->coordinate2->lon ?>"></coordinate>
    </div>
    <div id="shtm-map-track">
        <?php foreach ($this->tour->coordinates as $c): ?>
            <coordinate cid="<?php echo $c->id ?>" lat="<?php echo $c->lat ?>" lon="<?php echo $c->lon ?>"></coordinate>
        <?php endforeach ?>
    </div>
</div>

<form id="shtm-map-form" action=admin.php?<?php echo $this->route_params::update_tour($tour->id) ?> method="post"
    style="margin-left: 20px; float: left">

    <div id="shtm-tour-track-inputs">
        <!-- input fields added dynamically -->
    </div>

    <div class="button">
        <button type="submit">Speichern</button>
    </div>

</form>


<script type="text/javascript">

    // A class to map html form fields to leaflet latLngs
    function CoordinateFormBinding(domElem) {

        this.domElem = domElem;
        this.latLngs = [];

        // add the latLng to our collection
        this.addLatLng = function(latLng) {
            // if a latLng does not have an id it gets an empty string
            // (indicating to the backend should create it rather than update)
            if(typeof latLng._shtm_cid === 'undefined') {
                latLng._shtm_cid = "";
            }
            this.latLngs.push(latLng);
        };

        // clear all latLngs
        this.clear = function() {
            this.latLngs = [];
        }

        // create a single <input> tag for a coordinate's value
        this.createCoordInputElem = function(key, value, index) {
            var input = document.createElement('input');
            var name = 'shtm_tour[coordinates][' + index + '][' + key + ']';
            input.setAttribute('name', name);
            input.setAttribute('value', value);
            input.setAttribute('type', 'text');
            return input;
        };

        // create a form for the coordinates/latLngs this object has
        this.display = function() {
            // remove old input elements
            while(this.domElem.firstChild) {
                this.domElem.removeChild(this.domElem.firstChild);
            }
            // and add new ones
            for (var i = 0; i < this.latLngs.length; i++) {
                var latLng = this.latLngs[i];

                var inputs = [];
                inputs.push(this.createCoordInputElem('lat', latLng.lat, i));
                inputs.push(this.createCoordInputElem('lon', latLng.lng, i));
                var input = this.createCoordInputElem('id', latLng._shtm_cid, i);
                input.setAttribute('type', 'hidden');
                inputs.push(input);

                var container = document.createElement('div');
                inputs.forEach(function(inputElem) {
                    container.appendChild(inputElem);
                });

                this.domElem.appendChild(container);
            }
        };
    }

    // create a new default map
    function createMap() {
        var layerConfig =  {
            'name': 'OpenStreetMap',
            'type': 'xyz',
            'url': 'http://{s}.tile.osm.org/{z}/{x}/{y}.png',
            'layerOptions': {
                'subdomains': ['a', 'b', 'c'],
                'attribution': 'Map data © <a href="http://openstreetmap.org">OpenStreetMap</a> contributors',
                'continuousWorld': false,
                'maxZoom': 18
            }
        };
        var map = L.map('shtm-map');
        var tiles = L.tileLayer.wms(layerConfig.url, layerConfig.layerOptions);
        map.addLayer(tiles);
        return map;
    }

    // parses all <coordinate> tags that are childs of elem into a latLng array
    // adds the coordinate id as a further property '_shtm_cid' for later
    // retrieval
    function parseCoordinates(elem) {
        var result = [];
        var elems = elem.getElementsByTagName('coordinate');
        for(var i = 0; i < elems.length; i++) {
            var cid = elems[i].getAttribute('cid');
            var lat = elems[i].getAttribute('lat');
            var lng = elems[i].getAttribute('lon');
            latLng = L.latLng(lat, lng);
            latLng._shtm_cid = cid;
            result.push(latLng);
        }
        return result;
    }

    // create a form binding from the specified Element ids
    function createFormBindingWithElems(initialElementId, formElementId) {
        // create the binding
        var tracksInputDiv = document.getElementById(formElementId);
        var binding = new CoordinateFormBinding(tracksInputDiv);
        // parse coordinates into the binding
        var latLngs = parseCoordinates(document.getElementById(initialElementId));
        latLngs.forEach(function(latLng) {
            binding.addLatLng(latLng);
        });

        return binding;
    }

    // create a leaflet map object to contain everything
    var map = createMap();

    // create a form binding and fill it with the latLngs
    binding = createFormBindingWithElems('shtm-map-track', 'shtm-tour-track-inputs');
    binding.display();

    // fit map to the binding OR if no track coordinates exist yet, fit it to
    // the mapstops OR if there are no mapstops yet, fit it to the area
    var latLngs;
    if(binding.latLngs.length > 0) {
        latLngs = binding.latLngs;
    } else {
        latLngs = parseCoordinates(document.getElementById('shtm-map-area'));
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
        binding.display();

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
        binding.display();
    });

    // a hook called after a layer has been removed from the map
    map.on('draw:deleted', function(e) {
        // remove all coordinates from the binding and refresh the form
        binding.clear();
        binding.display();
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