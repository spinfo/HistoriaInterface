
<?php $this->include($this->view_helper::single_mapstop_header_template()) ?>

<div>
    <div class="shtm_info_text">
        Möchten Sie den folgenden Stop wirklich löschen?
        <br>
        (Verknüfte Orte und Beiträge bleiben bestehen.)
    </div>

    <ul>
        <li>ID: <?php echo $this->mapstop->id ?></li>
        <li>Name: <?php echo $this->mapstop->name ?></li>
        <li>Beschreibung: <?php echo $this->mapstop->description ?></li>
    </ul>

    <form action="admin.php?<?php echo $this->route_params::destroy_mapstop($this->mapstop->id) ?>" method="post">
        <div class="shtm_button">
            <button type="submit">Stop Löschen</button>
        </div>
    </form>
</div>
