/**
 * A "static" class providing a bunch of map utility functions
 */
function MapUtil() {}

// create a new default map
MapUtil.createMap = function(elementId) {
    var layerConfig =  {
        'name': 'OpenStreetMap',
        'type': 'xyz',
        'url': 'http://{s}.tile.osm.org/{z}/{x}/{y}.png',
        'layerOptions': {
            'subdomains': ['a', 'b', 'c'],
            'attribution': 'Map data © <a href="http://openstreetmap.org">OpenStreetMap</a> contributors',
            'continuousWorld': false,
            'maxZoom': 19
        }
    };
    var map = L.map(elementId);
    var tiles = L.tileLayer.wms(layerConfig.url, layerConfig.layerOptions);
    map.addLayer(tiles);
    return map;
}

// a uniform look for L.polyline (or the draw equivalent)
MapUtil.lineShape = function() {
    return {
        color: '#0099cc',
        opacity: 0.75
    }
}

// parses a single coordinate tag with into a latLng array.
// NOTE: adds the coordinate id as a further property '_shtm_cid' for later
// retrieval
MapUtil.parseCoordinate = function(elem) {
    var cid = elem.getAttribute('data-cid');
    var lat = elem.getAttribute('data-lat');
    var lng = elem.getAttribute('data-lon');
    var latLng = L.latLng(lat, lng);
    latLng._shtm_cid = cid;
    return latLng;
}

// parses all coordinat-tags that are childs of elem into a latLng array.
// NOTE: adds the coordinate id as a further property '_shtm_cid' for later
// retrieval
MapUtil.parseCoordinates = function(elem) {
    var result = [];
    var elems = elem.getElementsByClassName('coordinate');
    for(var i = 0; i < elems.length; i++) {
        result.push(MapUtil.parseCoordinate(elems[i]));
    }
    return result;
}

// Format an input value according to the precision we save on coordinates
MapUtil.formatCoordValue = function(value) {
    return Number.parseFloat(value).toFixed(6);
}

// Parse mapstop data out of a html element and return a simple object.
// Treats the first coordinate below the mapstop as belonging to it and
// adds it's coordinates as a leaflet latLng.
// NOTE: sets parameter ._shtm_cid on the contained latLng
MapUtil.mapstopFromElem = function(elem) {
    var result = {
        id: elem.getAttribute('data-mapstop-id'),
        name: elem.getAttribute('data-mapstop-name'),
        description: elem.getAttribute('data-mapstop-description'),
    }

    var coords = MapUtil.parseCoordinates(elem);
    if(coords.length > 0) {
        result.latLng = coords[0];
    } else {
        console.warn("No coordinate for mapstop.");
    }

    return result
}

// LEAFLET DRAW CONFIGURATION
// The following configures leaflet draw to only allow drawing/editing of a single item.
MapUtil.create_leaflet_draw_for_single_item = function(
    map,                         // the leaflet map to use
    type,                        // type of object to use ('polyline', 'marker', 'circle', 'polygon' or 'rectangle')
    binding,                     // A CoordinateFormBinding to update the form values
    createInputElementsCallback, // A callback function to create input form elements
    drawOptions,                 // Options to use for the leaflet drawControl
) {
    // a layer for all editable items (only one in this case)
    var editableItems = new L.FeatureGroup();
    map.addLayer(editableItems);

    // initialize an edit config with a layer group of editable items
    var editOptions = {
        draw: false,
        edit: {
            featureGroup: editableItems
        }
    };
    var drawControl = new L.Control.Draw(drawOptions);
    var editControl = new L.Control.Draw(editOptions);
    // at first we set the control to draw, this will change once a line is
    // drawn (either by the user or programmatically)
    map.addControl(drawControl);

    // a hook called when a fresh line is drawn
    map.on('draw:created', function(e) {
        var type = e.layerType,
            layer = e.layer;

        // add all latlngs to the form binding and refresh the form
        binding.clear();
        // for layers with multiple latlngs, e.g. on type polyline
        if(layer.hasOwnProperty('_latlngs')) {
            layer._latlngs.forEach(function(latLng, idx) {
                binding.addLatLng(latLng);
            });
        }
        // for layers with single latlngs, e.g. on type 'marker'
        if(layer.hasOwnProperty('_latlng')) {
            binding.addLatLng(layer._latlng);
        }
        binding.display(createInputElementsCallback);

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
            // for layers with multiple latlngs, e.g. on type polyline
            if(layer.hasOwnProperty('_latlngs')) {
                layer._latlngs.forEach(function(latLng, idx) {
                    binding.addLatLng(latLng);
                });
            }
            // for layers with single latlngs, e.g. on type 'marker'
            if(layer.hasOwnProperty('_latlng')) {
                binding.addLatLng(layer._latlng);
            }
        });
        // tell the form binding to re-render the form
        binding.display(createInputElementsCallback);
    });

    // a hook called after a layer has been removed from the map
    map.on('draw:deleted', function(e) {
        // remove all coordinates from the binding and refresh the form
        binding.clear();
        binding.display(createInputElementsCallback);
        // remove the edit control and replace it by the draw control
        editControl.remove();
        drawControl.addTo(map);
    });
}