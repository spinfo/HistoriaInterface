

<?php $this->include($this->view_helper::choose_current_area_template()) ?>

<div>
    <h2>Touren</h2>

    <a href="?<?php echo $this->route_params::new_tour() ?>">Tour hinzufügen</a>
</div>

<table id="shtm_tour_index" class="shtm_index_table">
    <thead>
        <tr>
            <th>id</th>
            <th>Name</th>
            <th>Typ</th>
            <th>Autor*in</th>
            <th><!-- Bearbeiten --></th>
            <th><!-- Löschen --></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach($this->tours_list as $tour): ?>
        <tr>
            <td><?php echo $tour->id ?></td>
            <td><?php echo $this->trim_text($tour->name, 60) ?></td>
            <td><?php echo $tour->get_type_name() ?></td>
            <td><?php echo $this->user_service->get_user($tour->user_id)->user_login ?></td>
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
