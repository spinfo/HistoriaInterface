/**
 * A class to map html <input> form fields to leaflet latLngs.
 *
 * The class is initialised with a DOM Element, where it will add it's input
 * fields.
 *
 * NOTE: This assumes that the latLngs added have an additional parameter
 * 'latLng._shtm_cid' set for the coordinates id. (Or else will set it as)
 * an empty string.
 */
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

    /**
     * Create a form for the coordinates/latLngs this object has.
     * Uses the callback like this:
     *
     *      elementCreateCallback(latLng, idx)
     *
     * to create the new element from the latLng, which has index
     * idx in this objects collection.
     */
    this.display = function(elementCreateCallback) {
        // remove old input elements
        while(this.domElem.firstChild) {
            this.domElem.removeChild(this.domElem.firstChild);
        }
        // and add new ones
        for (var i = 0; i < this.latLngs.length; i++) {
            var elem = elementCreateCallback(this.latLngs[i], i);
            this.domElem.appendChild(elem);
        }
    };
}

// Create a form binding using the <coordinate> tags from the initial DOM
// element as input and using the form Element as the DOM element to append
// <input> tags to.
CoordinateFormBinding.createWithElems = function(initialElementId, formElementId) {
    // create the binding
    var tracksInputDiv = document.getElementById(formElementId);
    var binding = new CoordinateFormBinding(tracksInputDiv);
    // parse coordinates into the binding
    var latLngs = MapUtil.parseCoordinates(document.getElementById(initialElementId));
    latLngs.forEach(function(latLng) {
        binding.addLatLng(latLng);
    });
    return binding;
}