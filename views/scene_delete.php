
<?php $this->include($this->view_helper::single_tour_header_template()) ?>

<div>
    <div class="shtm_info_text">
        Möchten Sie die folgende Szene aus der Tour wirklich löschen?
        <br>
        (Die Szene selbst bleibt unberührt erhalten.)
    </div>

    <div style="width: 150px; float: left; margin-right: 5px;">
        <?php echo wp_get_attachment_image($this->scene->post_id); ?>
    </div>

    <div style="float: left">
        <div><strong><?php echo $this->scene->title ?></strong></div>
        <div><?php echo $this->scene->description ?></div>
    </div>

    <div style="clear: both; margin-bottom: 10px;"></div>

    <form action="admin.php?<?php echo $this->route_params::destroy_scene($this->scene->id) ?>" method="post">
        <div class="shtm_button">
            <button type="submit">Scene aus Tour Löschen</button>
        </div>
    </form>
</div>