
<?php $this->include($this->view_helper::single_mapstop_header_template()) ?>

<form action=admin.php?<?php echo $this->route_params::update_mapstop($this->mapstop->id) ?> method="post"
    class="shtm_form">

    <?php $this->include($this->view_helper::mapstop_simple_form_template(), array('mapstop' => $this->mapstop, 'places' => $this->places)) ?>

    <div class="shtm_form_line">

        <label for="shtm_posts_menu">Seiten
        <?php $this->include($this->view_helper::tooltip_template(), array('content' => '
            Seiten enthalten die eigentlichen Informationen (Fließtext, Bilder etc.) des Tour-Stops.
            <br><br>
            Seiten sind <b>Wordpress-Beiträge</b>, die in der hier aufgeführten Reihenfolge angezeigt werden.
            <br><br>
            Damit ein Beitrag als Seite hinzugefügt werden kann, muss er als <b>Entwurf</b> gespeichert sein.
            <br><br>
            Der hier angezeigte <b>Titel</b> des Beitrags wird in der App ignoriert. Nur der Beitragsinhalt wird angezeigt.
        ')) ?>
        </label>

        <fieldset id="shtm_posts_menu" style="display: inline-block;">
            <?php for ($i = 1; $i <= count($this->posts); $i++): ?>
                <?php $post = $this->posts[$i - 1] ?>

                <div style="margin-bottom: 7px">

                    <div style="height: 100%; float: left; margin-right: 5px;">
                        <select name="shtm_mapstop[post_ids][]"
                            onChange="rerenderPostsPositions(this)">
                            <option value="">-</option>
                            <?php for($j = 1; $j <= count($this->posts) ; $j++): ?>
                                <option value="<?php echo $post->ID ?>" <?php echo (($j == $i) ? 'selected="true"' : '') ?>>
                                    <?php echo $j ?>
                                </option>
                            <?php endfor ?>
                        </select>
                    </div>

                    <div style="float: left">
                        <?php echo $post->post_title ?><br>
                        <a href="<?php echo $post->guid ?>" target="_blank">&rarr;&nbsp;Zum&nbsp;Beitrag</a>
                    </div>

                    <div style="clear: both"></div>
                </div>
            <?php endfor ?>
        </fieldset>

    </div>

    <div class="shtm_form_line">
        <label for="shtm_mapstop_add_post">Seite(n) hinzufügen:</label>

        <div id="shtm_add_post_menu" style="display: inline-block;">

            <select id="shtm_mapstop_add_post"
                style="display: block; margin-bottom: 7px"
                name="shtm_mapstop[post_ids][]"
                onChange="addPost(this)"
            >
                <option value="" selected></option>
                <?php foreach ($this->available_posts as $post): ?>
                    <option value="<?php echo $post->ID ?>">
                        <?php echo $post->post_title ?>
                    </option>
                <?php endforeach ?>
            </select>

        </div>
    </div>

    <div class="shtm_button">
        <button type="submit">Speichern</button>
    </div>

</form>


<script type="text/javascript">

    var getPostsMenuContainer = function() {
        return document.getElementById('shtm_posts_menu');
    }

    // add a callback to every select box that rearranges the options such that
    // every position is present once and the order reflects the user's choice
    var rerenderPostsPositions = function(elem) {
        var postLines = getPostsMenuContainer().children;
        // the "post line" we want to change is two levels up
        var elemLine = elem.parentNode.parentNode;
        // read the form lines into an array for easier editing
        var lines = [];
        for(var i = 0; i < postLines.length; i++) {
            lines.push(postLines[i]);
        }
        // determine the old and new positions
        var oldPos = lines.indexOf(elemLine);
        var newPos = elem.selectedIndex - 1;

        // if the new position is the empty one, don't do anything
        if(newPos < 0) {
            return;
        }
        // remove at the old position and insert at the new position
        lines.splice(oldPos, 1);
        if(newPos == lines.length) {
            lines.push(elemLine);
        } else {
            lines.splice(newPos, 0, elemLine);
        }
        // set the selection to approriate values (go two levels down again)
        for(var i = 0; i < lines.length; i++) {
            var select = lines[i].children[0].children[0];
            // ignore those elements that are meant for deletion
            if(select.selectedIndex != 0) {
                select.children[i + 1].selected = true;
            }
        }
        // remove old order from DOM and put in the new one
        var container = getPostsMenuContainer();
        while(container.firstChild) {
            container.removeChild(container.firstChild);
        }
        for(var i = 0; i < lines.length; i++) {
            container.append(lines[i]);
        }
    };

    var getAddPostMenuContainer = function() {
        return document.getElementById('shtm_add_post_menu')
    }

    var addPost = function(elem) {
        var container = getAddPostMenuContainer();

        // if the empty option is selected, remove select but never the last one
        if(elem.selectedIndex == 0) {
            if(container.children.length > 1) {
                container.removeChild(elem);
            }
            return;
        }
        // else: just clone the select box and append to the container
        var newSelect = elem.cloneNode(true);
        container.append(newSelect);
    }

</script>
