
<?php $this->include($this->view_helper::single_mapstop_header_template()) ?>

<?php
    if (1) {
        $url = $this->route_params::create_mapstop($this->mapstop->tour_id, $this->scene->id);
    } else {
        $url = $this->route_params::create_mapstop($this->mapstop->tour_id);
    }
?>

<form action="admin.php?<?php echo $url ?>" method="post" class="shtm_form">

    <?php $this->include($this->view_helper::mapstop_simple_form_template(), array('mapstop' => $this->mapstop, 'places' => $this->places)) ?>

    <div class="shtm_button">
        <button type="submit">Speichern</button>
    </div>

</form>