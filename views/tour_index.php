
<h1>Touren</h1>

<?php $this->include($this->view_helper::choose_current_area_template()) ?> 

<table id="shtm_tour_index">
    <thead>
        <tr>
            <th>id</th>
            <th>Name</th>
            <th>Einführung</th>
            <th>Typ</th>
            <th><!-- Bearbeiten --></th>
            <th><!-- Löschen --></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach($this->tours_list as $tour): ?>
        <tr>
            <td><?php echo $tour->id ?></td>
            <td><?php echo $this->trim_text($tour->name, 40) ?></td>
            <td><?php echo $this->trim_text($tour->intro, 40) ?></td>
            <td><?php echo $this->tour_type_name($tour->type) ?></td>
            <td>
                <?php if($this->user_service->user_may_edit_tour($tour)): ?>
                    <a href="?<?php echo $this->route_params::edit_tour($tour->id) ?>">Bearbeiten</a>
                <?php endif ?>
            </td>
            <td>
                <?php if($this->user_service->user_may_edit_tour($tour)): ?>
                    <a href="?<?php echo $this->route_params::delete_tour($tour->id) ?>">Löschen</a>
                <?php endif ?>
            </td>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>

<p>
    <a href="?<?php echo $this->route_params::new_tour() ?>">Neue Tour anlegen</a>
</p>
