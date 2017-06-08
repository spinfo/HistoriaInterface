
<div id="shtm_map" class="shtm_map_left" style="height: 400px; width: 400px;">
     <?php $this->include($this->view_helper::coordinate_template(), array('coordinate' => $this->area->coordinate1)) ?>
     <?php $this->include($this->view_helper::coordinate_template(), array('coordinate' => $this->area->coordinate2)) ?>
</div>

<script type="text/javascript">

    <?php $this->include($this->view_helper::map_util_js()) ?>

    // only create the map if we are not on an edit/new page
    // (does it's own map creation)
    url = window.location.href;
    if(!(url.search('shtm_a=edit') > -1) && !(url.search('shtm_a=new') > -1)) {
        // parse the area's coordinates
        var latLngs = MapUtil.parseCoordinates(document.getElementById('shtm_map'));
        var areaBounds = L.latLngBounds(latLngs)

        // create a leaflet map and center it on the area
        var map = MapUtil.createMap('shtm_map');
        map.fitBounds(areaBounds);

        // draw the area as a rectangle
        L.rectangle(areaBounds, MapUtil.rectangleShape()).addTo(map);
    }

</script>

