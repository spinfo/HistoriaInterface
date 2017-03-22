
<?php $this->include($this->view_helper::single_place_header_template()) ?>

<?php $this->include($this->view_helper::place_map_template()) ?>

<div class="shtm_right_from_map">
    <div class="shtm_info_text">
        Möchten Sie den folgenden Ort wirklich löschen?
    </div>

    <ul>
        <li>ID: <?php echo $this->place->id ?></li>
        <li>Name: <?php echo $this->place->name ?></li>
        <li>Latitude: <?php echo $this->coord_format($this->place->coordinate->lat) ?></li>
        <li>Longitude: <?php echo $this->coord_format($this->place->coordinate->lon) ?></li>
    </ul>

    <form action=admin.php?<?php echo $this->action_params ?> method="post">

    <div class="shtm_button" id="shtm_delete_place">
        <button type="submit">Ort Löschen</button>
    </div>

    </form>
</div>