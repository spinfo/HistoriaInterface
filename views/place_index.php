
<h2>Orte</h2>

<?php $this->include($this->view_helper::choose_current_area_template()) ?>

<div>
    <a href="?<?php echo $this->route_params::new_place() ?>">Ort hinzufügen</a>
</div>

<table id="shtm_place_index" class="shtm_index_table">
    <thead>
        <tr>
            <th>id</th>
            <th>Name</th>
            <th>Latitude</th>
            <th>Longitude</th>
            <th></th>
            <th></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach($this->places_list as $place): ?>
        <tr>
            <td><?php echo $place->id ?></td>
            <td><?php echo $place->name ?></td>
            <td><?php echo $place->coordinate->lat ?></td>
            <td><?php echo $place->coordinate->lon ?></td>
            <td>
                <?php if($this->user_service->user_may_edit_place($place)): ?>
                    <a href="?<?php echo $this->route_params::edit_place($place->id) ?>">Bearbeiten</a>
                <?php endif ?>
            </td>
            <td>
                <?php if($this->user_service->user_may_edit_place($place)): ?>
                    <a href="?<?php echo $this->route_params::delete_place($place->id) ?>">Löschen</a>
                <?php endif ?>
            </td>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>
