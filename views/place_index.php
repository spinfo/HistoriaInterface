
<h1>Orte</h1>

<table id="shtm_place_index">
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
            <td><?php echo $place->lat ?></td>
            <td><?php echo $place->lon ?></td>
            <td>
                <?php if($this->user_service->user_may_edit_place($place)): ?>
                    <a href="?<?php echo $this->route_params->edit_place($place->id) ?>">Bearbeiten</a>
                <?php endif ?>
            </td>
            <td>
                <?php if($this->user_service->user_may_edit_place($place)): ?>
                    <a href="?<?php echo $this->route_params->delete_place($place->id) ?>">Löschen</a>
                <?php endif ?>
            </td>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>

<p>
    <a href="?<?php echo $this->route_params->new_place() ?>">Neuen Ort anlegen</a>
</p>
