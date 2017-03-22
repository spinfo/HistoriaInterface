
<div id="shtm_map" class="shtm_map_left" style="height: 300px; width: 300px;">
     <?php $this->include($this->view_helper::coordinate_template(), array('coordinate' => $this->place->coordinate)) ?>
</div>

<script type="text/javascript">

    <?php $this->include($this->view_helper::map_util_js()) ?>

    // only create the map if we are not on an edit page
    // (does it's own map creation)
    url = window.location.href;
    if(!url.includes('shtm_a=edit') && !url.includes('shtm_a=new')) {

        // parse the place's coordinate
        coordLatLng = MapUtil.parseCoordinates(document.getElementById('shtm_map'))[0];

        // create a leaflet map and center it on the coordinate with a close zoom
        var map = MapUtil.createMap('shtm_map');
        map.setView(coordLatLng, 15);

        L.marker(coordLatLng).addTo(map);
    }

</script>

