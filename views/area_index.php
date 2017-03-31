
<div>
    <h2>Gebiete</h2>

    <?php if($this->user_service->is_admin()): ?>
        <a href="?<?php echo $this->route_params::new_area() ?>">Gebiet hinzufügen</a>
    <?php endif ?>
</div>

<table id="shtm_area_index" class="shtm_index_table">
    <thead>
        <tr>
            <th>id</th>
            <th>Name</th>
            <th>#Touren</th>
            <?php if($this->user_service->is_admin()): ?>
                <th><!-- Bearbeiten --></th>
                <th><!-- Löschen --></th>
            <?php endif ?>
        </tr>
    </thead>
    <tbody>
    <?php foreach($this->areas as $area): ?>
        <tr>
            <td><?php echo $area->id ?></td>
            <td><?php echo $this->trim_text($area->name, 40) ?></td>
            <td>
                <a href="?<?php echo $this->route_params::index_tours($area->id) ?>" title="Touren im Gebiet">
                    <?php echo $this->tour_counts[$area->id] ?>
                </a>
            </td>
            <?php if($this->user_service->is_admin()): ?>
                <td>
                    <a href="?<?php echo $this->route_params::edit_area($area->id) ?>">Bearbeiten</a>
                </td>
            <?php endif ?>
            <?php if($this->user_service->is_admin()): ?>
                <td>
                    <a href="?<?php echo $this->route_params::delete_area($area->id) ?>">Löschen</a>
                </td>
            <?php endif ?>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>