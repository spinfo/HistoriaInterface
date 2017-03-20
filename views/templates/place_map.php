
<div id="shtm_map" class="shtm_map_left" style="height: 300px; width: 300px;">
    <coordinate cid="<?php echo $this->place->coordinate->id ?>"
            lat="<?php echo $this->place->coordinate->lat ?>"
            lon="<?php echo $this->place->coordinate->lon ?>"></coordinate>
</div>

<script type="text/javascript">

    <?php $this->include($this->view_helper::map_util_js()) ?>

    // parse the place's coordinate
    latLng = MapUtil.parseCoordinates(document.getElementById('shtm_map'))[0];

    // create a leaflet map and center it on the coordinate with a close zoom
    var map = MapUtil.createMap('shtm_map');
    map.setView(latLng, 17);

    // add a marker for the coordinate
    L.marker(latLng).addTo(map);

</script>

