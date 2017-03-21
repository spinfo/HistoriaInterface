// creates a single <input> tag for a place's coordinate, wraps it in a
// a <div> and returns the latter
function createCoordInputElem (key, value, index) {
    var div = document.createElement('div');

    var input = document.createElement('input');
    var name = 'shtm_place[' + key + ']';
    input.setAttribute('name', name);
    input.setAttribute('value', value);
    input.setAttribute('type', 'text');

    div.appendChild(input);
    return div;
};

// A callback for the CoordinateFormBinding to render a coordinate's
// input fields
createInputElements = function(latLng, idx) {
    var container = document.createElement('div');

    var div = createCoordInputElem('lat', MapUtil.formatCoordValue(latLng.lat), idx);
    div.innerHTML = 'Lat: ' + div.innerHTML;
    container.appendChild(div);

    var div = createCoordInputElem('lon', MapUtil.formatCoordValue(latLng.lng), idx);
    div.innerHTML = 'Lon: ' + div.innerHTML;
    container.appendChild(div);

    return container;
}

// parse the place's coordinate
coordLatLng = MapUtil.parseCoordinates(document.getElementById('shtm_map'))[0];

// create a leaflet map
var map = MapUtil.createMap('shtm_map');

// create a form binding and fill it with the latLng of the place
binding = CoordinateFormBinding.createWithElems(
    'shtm_map', 'shtm_place_coordinate_inputs');
binding.display(createInputElements);

// Initialize options for drawing, set every other element to false
var drawOptions = {
    draw: {
        polyline: false,
        marker: {},
        circle: false,
        polygon: false,
        rectangle: false
    },
    edit: false
};

// create the leaflet draw environment
MapUtil.create_leaflet_draw_for_single_item(map, 'marker', binding,
    createInputElements, drawOptions);

// if we are on the edit view:
// initialize a marker from the place's latLng, center close to the place
if(window.location.href.includes('shtm_a=edit')) {
    // we have to fiddle a bit with draw.js internals here, reference:
    // https://github.com/Leaflet/Leaflet.draw/blob/master/src/draw/handler/Draw.Marker.js
    var marker = new L.Draw.Marker(map, drawOptions.draw.marker);
    marker._marker = L.marker(coordLatLng);
    marker._fireCreatedEvent();
    map.setView(coordLatLng, 15);
}
// else if we are on the new place view:
// the coordinate represents the area's center, just zoom a bit further out
else if(window.location.href.includes('shtm_a=new')) {
    map.setView(coordLatLng, 11);
}
// something went wrong, center on somewhere in Germany, zoom out far and log an error
else {
    console.error("Bad route for place edit map: " + window.location.href);
    map.setView([50.5,10.5], 3);
}