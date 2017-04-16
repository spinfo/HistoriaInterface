

<div>
    <div class="shtm_info_text">
        Die Tour wurde mit folgenden Informationen veröffentlicht:
    </div>

    <ul>
        <li><b>Tour-Name</b>: <?php echo $this->record->name ?></li>
        <li><b>Gebiet</b>: <?php echo $this->area->name ?></li>
        <li><b>Veröffentlicht von</b>: <?php echo $this->user_service->get_user($this->record->user_id)->user_login ?></li>
        <li>
            <b>Download</b>:<br>
            <a href="<?php echo $this->record->media_url ?>"><?php echo $this->record->media_url ?></a>
        </li>
        <li>
            <b>Download-Größe</b>: <?php printf("%.2f", ($this->record->download_size / 1000000)) ?> MB
        </li>
        <li>
            <b>Inhalt</b>:
            <xmp class="shtm_tour_report"><?php echo $this->record->content ?></xmp>
        </li>
    </ul>


</div>

