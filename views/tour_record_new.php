
<div>
    <?php if(count($this->publishing_check_failures) === 0): ?>
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
    <?php else: ?>
        <div class="shtm_info_text">
            Die Tour kann nicht veröffentlicht werden:
        </div>

        <?php foreach ($this->publishing_check_failures as $failure): ?>
            <div class="shtm_message shtm_message_warning">
                <b><?php echo count($failure->messages) ?> Fehler im Bereich</b>
                <a href="<?php echo $failure->link ?>"><?php echo $failure->link_name ?></a>:
                <ul>
                    <?php foreach ($failure->messages as $msg): ?>
                        <li><?php echo $msg ?></li>
                    <?php endforeach ?>
                </ul>
            </div>
        <?php endforeach ?>

        <div class="shtm_info_text">
            Vorläufige Tour-Informationen:
        </div>
    <?php endif ?>

    <?php $this->include($this->view_helper::tour_record_template()) ?>

</div>
