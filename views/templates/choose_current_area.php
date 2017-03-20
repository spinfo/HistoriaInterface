<form action="admin.php?<?php echo $this->route_params::set_current_area() ?>" method="get"
    style="float:right">
    <div>
        <label for="shtm_current_area">Gebiet: </label>
        <select id="shtm_current_area" name="shtm_id">
            <?php foreach($this->areas_list as $area): ?>
                <option value="<?php echo $area->id ?>"
                    <?php if($area->id == $this->current_area_id): ?>
                        selected>
                    <?php else: ?>
                        >
                    <?php endif ?>
                        <?php echo $area->name ?>
                </option>
            <?php endforeach ?>
        </select>

        <?php foreach($this->route_params::set_current_area_params() as $key => $value): ?>
            <input type="hidden" name="<?php echo $key ?>" value="<?php echo $value ?>">
        <?php endforeach ?>
    </div>

    <div class="button" style="float:right">
        <button type="submit">Ok</button>
    </div>
</form>