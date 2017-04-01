
<div class="shtm_heading_line">

    <?php if(!$this->route_params::is_current_page($this->route_params::new_area())): ?>

        <h2>Gebiet #<?php echo $this->area->id ?></h2>

        <?php if($this->route_params::is_current_page($this->route_params::edit_area($this->area->id))): ?>
            <span class="shtm_not_a_link">Bearbeiten</span> |
        <?php else: ?>
            <a href="admin.php?<?php echo $this->route_params::edit_area($this->area->id) ?>">Bearbeiten</a> |
        <?php endif ?>

        <?php if($this->route_params::is_current_page($this->route_params::delete_area($this->area->id))): ?>
            <span class="shtm_not_a_link">Löschen</span>
        <?php else: ?>
            <a href="admin.php?<?php echo $this->route_params::delete_area($this->area->id) ?>">Löschen</a>
        <?php endif ?>

    <?php else: ?>

        <h2>Neues Gebiet</h2>

    <?php endif ?>

</div>
