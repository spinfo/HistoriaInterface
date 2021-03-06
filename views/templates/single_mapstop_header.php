<div class="shtm_heading_line">

    <?php if(!$this->route_params::is_current_page($this->route_params::new_mapstop($this->mapstop->tour_id))): ?>

        <h2>Stop #<?php echo $this->mapstop->id ?></h2>

        <?php if($this->route_params::is_current_page($this->route_params::edit_mapstop($this->mapstop->id))): ?>
            <span class="shtm_not_a_link">Bearbeiten</span> |
        <?php else: ?>
            <a href="admin.php?<?php echo $this->route_params::edit_mapstop($this->mapstop->id) ?>">Bearbeiten</a> |
        <?php endif ?>

        <?php if($this->route_params::is_current_page($this->route_params::delete_mapstop($this->mapstop->id))): ?>
            <span class="shtm_not_a_link">Löschen</span>
        <?php else: ?>
            <?php if($this->scene): ?>
                <a href="admin.php?<?php echo $this->route_params::delete_mapstop($this->mapstop->id, $this->scene->id) ?>">Löschen</a>
            <?php else: ?>
                <a href="admin.php?<?php echo $this->route_params::delete_mapstop($this->mapstop->id) ?>">Löschen</a>
            <?php endif ?>
        <?php endif ?> |

    <?php else: ?>

        <h2>Neuer Stop</h2>

    <?php endif ?>

    <?php if($this->scene): ?>
        <a href="admin.php?<?php echo $this->route_params::new_scene_stop($this->scene->id) ?>">
            zur Scene
        </a>
    <?php else: ?>
        <a href="admin.php?<?php echo $this->route_params::edit_tour_stops($this->mapstop->tour_id) ?>">
            zur Tour
        </a>
    <?php endif ?>

</div>
