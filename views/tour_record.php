
<div>
    <div class="shtm_info_text">
        <?php if($this->record->is_active): ?>
            Die Tour ist mit folgenden Informationen veröffentlicht:
        <?php else: ?>
            Die Tour ist zurzeit nicht veröffentlicht.
        <?php endif ?>
    </div>

    <?php $this->include($this->view_helper::tour_record_template()) ?>
</div>
