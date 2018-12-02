
<?php $this->include($this->view_helper::single_tour_header_template()) ?>

<form id="shtm_scene_positions_form" action="admin.php?<?php echo $this->route_params::update_tour_scenes($this->tour->id) ?>" method="post"
      class="shtm_form">

    <div style="margin-bottom: 12px">
        <a href="admin.php?<?php echo $this->route_params::new_scene($this->tour->id) ?>">Scene hinzufügen</a>
    </div>

    <div id="shtm_scenes_menu">

        <?php for ($i = 1; $i <= count($this->tour->scenes); $i++): ?>
            <?php $scene = $this->tour->scenes[$i - 1] ?>

            <div class="shtm_form_line" style="margin-bottom: 7px">

                <div style="height: 100%; float: left; margin-right: 5px;">
                    <select name="shtm_tour[scene_ids][<?php echo $scene->id ?>]"
                            onChange="rerenderScenePositions(this)">
                        <?php for($j = 1; $j <= count($this->tour->scenes) ; $j++): ?>
                            <option <?php echo (($j == $i) ? 'selected="true"' : '') ?>><?php echo $j ?></option>
                        <?php endfor ?>
                    </select>
                </div>

                <div style="width: 150px; float: left; margin-right: 5px;">
                    <?php echo wp_get_attachment_image($scene->post_id); ?>
                </div>

                <div style="float: left">
                    <b><?php echo $scene->title ?></b>&nbsp;|&nbsp;<a href="admin.php?<?php echo $this->route_params::delete_scene($scene->id) ?>">Löschen</a><br>
                    <?php echo $scene->description ?><br>
                </div>

                <div style="clear: both"></div>
            </div>
        <?php endfor ?>

    </div>

    <div class="shtm_button">
        <button type="submit">Speichern</button>
    </div>

</form>

<script type="text/javascript">
    // the list of all mapstops with positions
    var formLines = document.getElementsByClassName('shtm_form_line');

    // add a callback to every select box that rearranges the options such that
    // every position is present once and the order reflects the user's choice
    var rerenderScenePositions = function(elem) {
        // the "form line" we want to change is two levels up
        var elemLine = elem.parentNode.parentNode;
        // read the form lines into an array for easier editing
        var lines = [];
        for(var i = 0; i < formLines.length; i++) {
            lines.push(formLines[i]);
        }
        // determine the old and new positions
        var oldPos = lines.indexOf(elemLine);
        var newPos = elem.selectedIndex;
        // remove at the old position and insert at the new position
        lines.splice(oldPos, 1);
        if(newPos == lines.length) {
            lines.push(elemLine);
        } else {
            lines.splice(newPos, 0, elemLine);
        }
        // set the selection to approriate values (go two levels down again)
        for(var i = 0; i < lines.length; i++) {
            lines[i].children[0].children[0].children[i].selected = true;
        }
        // remove old order from DOM and put in the new one
        var container = document.getElementById('shtm_scenes_menu');
        while(container.firstChild) {
            container.removeChild(container.firstChild);
        }
        for(var i = 0; i < lines.length; i++) {
            container.appendChild(lines[i]);
        }
    };
</script>