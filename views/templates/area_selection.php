<div class="shtm_form_line">
    <label for="shtm_area_selection">Gebiet: </label>
    <select id="shtm_area_selection" name="<?php echo $this->name ?>">
        <?php foreach($this->areas as $area): ?>
            <option value="<?php echo $area->id ?>"
                <?php if($area->id == $this->selected_area_id): ?>
                    selected>
                <?php else: ?>
                    >
                <?php endif ?>
                    <?php echo $area->name ?>
            </option>
        <?php endforeach ?>
    </select>
</div>