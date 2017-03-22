

<?php $this->include($this->view_helper::single_place_header_template()) ?>

<?php $this->include($this->view_helper::place_map_template()) ?>

<form action=admin.php?<?php echo $this->action_params ?> method="post"
    class="shtm_right_from_map shtm_form shtm_place_form">

    <div class="shtm_form_line">
        <label for="shtm_name">Name:</label>
        <input type="text" id="shtm_name" name="shtm_place[name]" value="<?php echo $this->place->name ?>">
    </div>

    <div id="shtm_place_coordinate_inputs">
        <div>
            <div class="shtm_form_line">
                <label for="shtm_place_lat">Lat:</label>
                <input id="shtm_place_lat" type="text" name="shtm_place[lat]" value="<?php echo $this->coord_format($this->place->coordinate->lat) ?>">
            </div>
            <div class="shtm_form_line">
                <label for="shtm_place_lon">Lon:</label>
                <input id="shtm_place_lon" type="text" name="shtm_place[lon]" value="<?php echo $this->coord_format($this->place->coordinate->lon) ?>">
            </div>
        </div>
    </div>

    <div class="shtm_button">
        <button type="submit">Speichern</button>
    </div>

</form>

<script type="text/javascript">

    // require the form binding script
    <?php $this->include($this->view_helper::coordinate_form_binding_js()) ?>

    // require the script to edit the map
    <?php $this->include($this->view_helper::place_edit_map_js()) ?>

</script>