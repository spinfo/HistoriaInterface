
<div>
    <div class="shtm_info_text">
        Die Tour würde mit den folgenden Informationen veröffentlicht werden.
    </div>

    <form method="get">
        <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>">
        <input type="hidden" name="<?php echo $this->route_params::KEYS['controller'] ?>" value="tour_record">
        <input type="hidden" name="<?php echo $this->route_params::KEYS['action'] ?>" value="create">
        <input type="hidden" name="<?php echo $this->route_params::KEYS['tour_id'] ?>" value="<?php echo $this->record->tour_id ?>">

        <div class="shtm_button">
            <button type="submit">Jetzt veröffentlichen</button>
        </div>
    </form>

    <?php $this->include($this->view_helper::tour_record_template()) ?>

</div>
