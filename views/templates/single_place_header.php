
<div class="shtm_heading_line">

    <?php if(!$this->route_params::is_current_page($this->route_params::new_place())): ?>

        <h2>Ort #<?php echo $this->place->id ?></h2>

        <?php if($this->route_params::is_current_page($this->route_params::edit_place($this->place->id))): ?>
            <span class="shtm_not_a_link">Bearbeiten</span> |
        <?php else: ?>
            <a href="admin.php?<?php echo $this->route_params::edit_place($this->place->id) ?>">Bearbeiten</a> |
        <?php endif ?>

        <?php if($this->route_params::is_current_page($this->route_params::delete_place($this->place->id))): ?>
            <span class="shtm_not_a_link">Löschen</span>
        <?php else: ?>
            <a href="admin.php?<?php echo $this->route_params::delete_place($this->place->id) ?>">Löschen</a>
        <?php endif ?>

    <?php else: ?>

        <h2>Neuer Ort</h2>

    <?php endif ?>

</div>
