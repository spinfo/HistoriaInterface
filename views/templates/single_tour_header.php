
<div class="shtm_heading_line">

    <h2>Tour #<?php echo $this->tour->id ?></h2>

    <?php if($this->route_params::is_current_page($this->route_params::edit_tour($this->tour->id))): ?>
        <span class="shtm_not_a_link">Tour-Info</span> |
    <?php else: ?>
        <a href="admin.php?<?php echo $this->route_params::edit_tour($this->tour->id) ?>">Tour-Info</a> |
    <?php endif ?>

    <?php if($this->route_params::is_current_page($this->route_params::edit_tour_track($this->tour->id))): ?>
        <span class="shtm_not_a_link">Tour-Weg</span> |
    <?php else: ?>
        <a href="admin.php?<?php echo $this->route_params::edit_tour_track($this->tour->id) ?>">Tour-Weg</a> |
    <?php endif ?>

    <?php if($this->route_params::is_current_page($this->route_params::edit_tour_stops($this->tour->id))): ?>
        <span class="shtm_not_a_link">Tour-Stops</span> |
    <?php else: ?>
        <a href="admin.php?<?php echo $this->route_params::edit_tour_stops($this->tour->id) ?>">Tour-Stops</a> |
    <?php endif ?>

    <?php if($this->route_params::is_current_page($this->route_params::tour_report($this->tour->id))): ?>
        <span class="shtm_not_a_link">Tour-Report</span> |
    <?php else: ?>
        <a href="admin.php?<?php echo $this->route_params::tour_report($this->tour->id) ?>">Tour-Report</a> |
    <?php endif ?>

    <?php if($this->route_params::is_current_page($this->route_params::delete_tour($this->tour->id))): ?>
        <span class="shtm_not_a_link">Löschen</span>
    <?php else: ?>
        <a href="admin.php?<?php echo $this->route_params::delete_tour($this->tour->id) ?>">Löschen</a>
    <?php endif ?>

</div>