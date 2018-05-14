
<div class="shtm_heading_line">

    <h2>Neuer Stop für Szene #<?php echo $this->scene->id ?></h2>

    <a href="admin.php?<?php echo $this->route_params::edit_tour_stops($this->scene->tour_id) ?>">
        zur Tour
    </a>

</div>

<form id="shtm_mapstop_positions_form" action="admin.php?<?php echo $this->route_params::update_tour_stops($this->scene->tour_id) ?>" method="post"
      class="shtm_form">

    <div class="">
        <div id="scene_image" style="float: left; margin-right: 10px;">
            <canvas id="scene_canvas"></canvas>
        </div>

        <div style="float: left;">
            <div style="margin-bottom: 10px">
                <a href="admin.php?<?php echo $this->route_params::new_mapstop($this->scene->tour_id, $this->scene->id) ?>">Neuer Stop</a>
            </div>

            <div id="shtm_mapstops_menu">
            <?php $coordinates = array(); ?>
            <?php for ($i = 1; $i <= count($this->scene->mapstops); $i++): ?>
                <?php $mapstop = $this->scene->mapstops[$i - 1] ?>

                <div class="shtm_form_line" style="margin-bottom: 7px">
                    <div style="height: 100%; float: left; margin-right: 5px;">
                        <select name="shtm_tour[mapstop_ids][<?php echo $mapstop->id ?>]"
                                onChange="rerenderMapstopPositions(this)">
                            <?php for($j = 1; $j <= count($this->scene->mapstops) ; $j++): ?>
                                <option <?php echo (($j == $i) ? 'selected="true"' : '') ?>><?php echo $j ?></option>
                            <?php endfor ?>
                        </select>
                    </div>
                    <?php
                        if (isset($this->scene->coordinates[$mapstop->id])) {
                            $coordinates[$i] = $this->scene->coordinates[$mapstop->id];
                        }
                    ?>

                    <div style="float: left;">
                        <b><?php echo $mapstop->name ?></b>&nbsp;<a href="javascript:activate(<?php echo $mapstop->id ?>)">Markierung setzen</a>&nbsp;
                        |&nbsp;<a href="admin.php?<?php echo $this->route_params::edit_mapstop($mapstop->id) ?>">Bearbeiten</a>&nbsp;
                        |&nbsp;<a href="admin.php?<?php echo $this->route_params::delete_mapstop($mapstop->id) ?>">Löschen</a><br>
                        <?php echo $mapstop->description ?><br>
                    </div>

                    <div style="clear: both;"></div>
                </div>

            <?php endfor; ?>
            </div>

            <div class="shtm_button">
                <button type="submit">Speichern</button>
            </div>
        </div>

        <div style="clear: both;"></div>
    </div>

</form>

<script>
    var imageData = JSON.parse('<?php echo json_encode(wp_get_attachment_image_src($this->scene->id, [960, 720])); ?>');
    var canvas = document.getElementById("scene_canvas");
    var context = canvas.getContext('2d');
    var sprite = new Image();
    sprite.src = imageData[0];
    sprite.width = imageData[1];
    sprite.height = imageData[2];
    canvas.width = Math.min(960, sprite.width);
    canvas.height = Math.min(720, sprite.height);
    sprite.onload = function () {
        context.drawImage(sprite, 0, 0, sprite.width, sprite.height);

        <?php foreach ($coordinates as $key => $coordinate): ?>
            var text = "<?php echo $key ?>";
            var point = new Point(<?php echo $coordinate->lat ?>, <?php echo $coordinate->lon ?>);
            markOnImage(point, text);
        <?php endforeach; ?>
    };

    var mapstop_id = null;

    function activate(m_id) {
        mapstop_id = m_id;
        canvas.style.cursor = "crosshair";
        canvas.onmousedown = handleClick;
    }

    function deactivate() {
        canvas.style.cursor = "default";
        canvas.onmousedown = function(){};
        mapstop_id = null;
    }

    function handleClick(e) {
        var point = new Point(e.offsetX, e.offsetY);
        markOnImage(point);
        var url = "admin.php?<?php echo $this->route_params::set_marker(0, $this->scene->id) ?>";
        url = url.replace(/(shtm_id=).*?(&)/,'$1' + mapstop_id + '$2');
        deactivate();
        post(url, point);
    }

    function Point(x, y) {
        this.x = x;
        this.y = y;
    }

    function markOnImage(point, text) {
        var marker = new Image();
        marker.src = "http://www.clker.com/cliparts/M/A/1/v/2/L/blue-marker-th.png";
        marker.width = 40;
        marker.height = 38;
        marker.onload = function () {
            context.drawImage(marker, point.x - marker.width / 2, point.y - marker.height, marker.width, marker.height);
            if (typeof text !== "undefined") {
                context.font = "bold 15pt Courier";
                context.fillText(text, point.x - 6, point.y - marker.height / 2);
            }
        };
    }

    function post(path, params, method) {
        method = method || "post";

        var form = document.createElement("form");
        form.setAttribute("method", method);
        form.setAttribute("action", path);

        for(var key in params) {
            if(params.hasOwnProperty(key)) {
                var hiddenField = document.createElement("input");
                hiddenField.setAttribute("type", "hidden");
                hiddenField.setAttribute("name", key);
                hiddenField.setAttribute("value", params[key]);

                form.appendChild(hiddenField);
            }
        }

        document.body.appendChild(form);
        form.submit();
    }
</script>

<script type="text/javascript">
    // the list of all mapstops with positions
    var formLines = document.getElementsByClassName('shtm_form_line');

    // add a callback to every select box that rearranges the options such that
    // every position is present once and the order reflects the user's choice
    var rerenderMapstopPositions = function(elem) {
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
        var container = document.getElementById('shtm_mapstops_menu');
        while(container.firstChild) {
            container.removeChild(container.firstChild);
        }
        for(var i = 0; i < lines.length; i++) {
            container.appendChild(lines[i]);
        }
    };

</script>