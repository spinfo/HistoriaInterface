
<?php $this->include($this->view_helper::single_tour_header_template()) ?>

<div>
    <div class="shtm_info_text">
        Möchten Sie die folgende Tour wirklich löschen?
        <br>
        (Verknüpfte Beiträge und Medien bleiben erhalten.)
    </div>

    <ul>
        <li>ID: <?php echo $this->tour->id ?></li>
        <li>Name: <?php echo $this->tour->name ?></li>
        <li>Intro: <?php echo $this->trim_text($this->tour->intro, 40) ?></li>
    </ul>

    <form action="admin.php?<?php echo $this->route_params::destroy_tour($this->tour->id) ?>" method="post">
        <div class="shtm_button">
            <button type="submit">Tour Löschen</button>
        </div>
    </form>
</div>