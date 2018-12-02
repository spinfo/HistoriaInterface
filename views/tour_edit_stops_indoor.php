
<?php $this->include($this->view_helper::single_tour_header_template()) ?>

<div id="shtm_scenes_menu">

    <?php for ($i = 1; $i <= count($this->tour->scenes); $i++): ?>
        <?php $scene = $this->tour->scenes[$i - 1] ?>

        <div class="shtm_form_line" style="margin-bottom: 7px">

            <div style="width: 150px; float: left; margin-right: 5px;">
                <?php echo wp_get_attachment_image($scene->post_id); ?>
            </div>

            <div style="float: left">
                <strong><?php echo $scene->title ?></strong><br>
                <?php echo $scene->description ?><br>
                <a href="admin.php?<?php echo $this->route_params::new_scene_stop($scene->id) ?>">Stops hinzufügen</a>
            </div>

            <div style="clear: both"></div>
        </div>
    <?php endfor ?>

</div>