
<h1>Orte</h1>

<form action="admin.php?<?php echo $this->route_params::set_current_area() ?>" method="get"
    style="float:right">
    <div>
        <label for="shtm_current_area">Gebiet: </label>
        <select id="shtm_current_area" name="shtm_id">
            <?php foreach($this->areas_list as $area): ?>
                <option value="<?php echo $area->id ?>"
                    <?php if($area->id == $this->current_area_id): ?>
                        selected>
                    <?php else: ?>
                        >
                    <?php endif ?>
                        <?php echo $area->name ?>
                </option>
            <?php endforeach ?>
        </select>

        <?php foreach($this->route_params::set_current_area_params() as $key => $value): ?>
            <input type="hidden" name="<?php echo $key ?>" value="<?php echo $value ?>">
        <?php endforeach ?>
    </div>

    <div class="button" style="float:right">
        <button type="submit">Ok</button>
    </div>
</form>


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

<p>
    <a href="?<?php echo $this->route_params::new_place() ?>">Neuen Ort anlegen</a>
</p>
