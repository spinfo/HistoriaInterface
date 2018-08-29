
<div class="shtm_form_line">
    <label for="shtm_mapstop_place_id">
        Ort:
        <?php $this->include($this->view_helper::tooltip_template(), array('content' => "
            Der Ort legt die <b>geographische Position</b> dieses Stops fest.
            <br><br>
            Es können nur Orte ausgewählt werden, die
            <ul>
                <li>im selben Gebiet liegen und</li>
                <li>nicht schon derselben Tour zugeordnet wurden.</li>
            </ul>
        ")) ?>
    </label>
    <select id="shtm_mapstop_place_id" name="shtm_mapstop[place_id]">
        <?php foreach ($this->places as $place): ?>
            <option value="<?php echo $place->id ?>" <?php echo (($place->id === $this->mapstop->place_id) ? 'selected' : '') ?>>
                <?php echo $place->name ?>
            </option>
        <?php endforeach ?>
    </select>
</div>

<div class="shtm_form_line">
    <label for="shtm_mapstop_name">
        Name:
        <?php
            $img_url = $this->view_helper::image_url('mapstop-name-description.png');
            $this->include($this->view_helper::tooltip_template(), array('content' => "
                Name und Beschreibung des Stops erscheinen bei Klick auf den Stop-Marker
                <br><br>
                <img src=\"$img_url\" />
            "))
        ?>
    </label>
    <input id="shtm_mapstop_name" type="text" name="shtm_mapstop[name]" value="<?php echo $this->mapstop->name ?>">
</div>

<div class="shtm_form_line">
    <label for="shtm_mapstop_description">Beschreibung:</label>
    <textarea id="shtm_mapstop_description" type="text" name="shtm_mapstop[description]" cols="35" rows="2"><?php echo $this->mapstop->description ?></textarea>
</div>

<?php if ($this->scene): ?>
    <div class="shtm_form_line">
        <label for="shtm_mapstop_type">
            Typ:
        </label>
        <select id="shtm_mapstop_type" name="shtm_mapstop[type]">
            <?php foreach ($this->mapstop::TYPES as $key => $value): ?>
                <option value="<?php echo $key ?>" <?php echo (($key === $this->mapstop->type) ? 'selected' : '') ?>>
                    <?php echo $value ?>
                </option>
            <?php endforeach ?>
        </select>
    </div>
<?php endif; ?>
