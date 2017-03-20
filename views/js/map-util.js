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
            'maxZoom': 18
        }
    };
    var map = L.map(elementId);
    var tiles = L.tileLayer.wms(layerConfig.url, layerConfig.layerOptions);
    map.addLayer(tiles);
    return map;
}

// parses all <coordinate> tags that are childs of elem into a latLng array.
// NOTE: adds the coordinate id as a further property '_shtm_cid' for later
// retrieval
MapUtil.parseCoordinates = function(elem) {
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