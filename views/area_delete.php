
<?php $this->include($this->view_helper::single_area_header_template()) ?>

<?php $this->include($this->view_helper::area_map_template()) ?>

<div class="shtm_right_from_map">
    <?php if(count($this->tours) === 0): ?>
        <div class="shtm_info_text">
            Möchten Sie das folgende Gebiet wirklich
            <a target="_blank" href="admin.php?<?php echo $this->route_params::index_places($this->area->id) ?>">mit allen Orten</a>
            löschen?
        </div>
    <?php endif ?>

    <ul>
        <li>ID: <?php echo $this->area->id ?></li>
        <li>Name: <?php echo $this->area->name ?></li>
    </ul>

    <form action=admin.php?<?php echo $this->route_params::destroy_area($this->area->id) ?> method="post">

    <?php if(count($this->tours) === 0): ?>
        <div class="shtm_button">
            <button type="submit">Löschen</button>
        </div>
    <?php else: ?>
        <div class="shtm_message shtm_message_warning">
            Das Gebiet kann nicht gelöscht werden, da es noch mit mindestens einer Tour verknüpft ist.
            <br>
            <a target="_blank" href="admin.php?<?php echo $this->route_params::index_tours($this->area->id) ?>">Touren im Gebiet</a>:
            <div style="padding-left: 12px">
                <ul>
                    <?php foreach ($this->tours as $tour): ?>
                        <li>
                            <a target="_blank" href="admin.php?<?php echo $this->route_params::edit_tour($tour->id) ?>">
                                <?php echo $tour->name ?>
                            </a>
                        </li>
                    <?php endforeach ?>
                </ul>
            </div>
        </div>
    <?php endif ?>

    </form>
</div>
