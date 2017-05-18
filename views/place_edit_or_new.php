

<?php $this->include($this->view_helper::single_place_header_template()) ?>

<?php $this->include($this->view_helper::place_map_template()) ?>

<form action=admin.php?<?php echo $this->action_params ?> method="post"
    class="shtm_right_from_map shtm_form shtm_place_form">

    <?php if($this->route_params::is_current_page($this->route_params::new_place())): ?>
        <?php $this->include($this->view_helper::area_selection_template(), array(
                'name' => 'shtm_place[area_id]',
                'areas' => $this->areas,
                'selected_area_id' => $this->current_area_id,
        )) ?>
    <?php else: ?>
        <div class="shtm_form_line">
            <label>Gebiet: <?php echo $this->area->name ?></label>
        </div>
    <?php endif ?>



    <div class="shtm_form_line">
        <label for="shtm_name">Name:
            <?php $this->include($this->view_helper::tooltip_template(), array('content' => '
                Der Ortsname dient der <b>allgemeinen Identifizierung</b> des Ortes und taucht nicht direkt bei den Stops einer Tour auf.
                <br><br>
                Nach Möglichkeit besteht er aus:
                <ul>
                    <li>Straßenname und Hausnr., z.B. <b>Weidengasse 13</b></li>
                    <li>Genaue geographische Bezeichnung, z.B. <b>Weidenpark</b></li>
                </ul>
            ')) ?>
        </label>
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