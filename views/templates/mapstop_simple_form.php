
<div class="shtm_form_line">
    <label for="shtm_mapstop_place_id">Ort:</label>
    <select id="shtm_mapstop_place_id" name="shtm_mapstop[place_id]">
        <?php foreach ($this->places as $place): ?>
            <option value="<?php echo $place->id ?>" <?php echo (($place->id === $this->mapstop->place_id) ? 'selected' : '') ?>>
                <?php echo $place->name ?>
            </option>
        <?php endforeach ?>
    </select>
</div>

<div class="shtm_form_line">
    <label for="shtm_mapstop_name">Name:</label>
    <input id="shtm_mapstop_name" type="text" name="shtm_mapstop[name]" value="<?php echo $this->mapstop->name ?>">
</div>

<div class="shtm_form_line">
    <label for="shtm_mapstop_description">Beschreibung:</label>
    <textarea id="shtm_mapstop_description" type="text" name="shtm_mapstop[description]" cols="35" rows="2"><?php echo $this->mapstop->description ?></textarea>
</div>
