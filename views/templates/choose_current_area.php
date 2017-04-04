<form action="admin.php" method="get"
    style="float:right">
    <div>
        <?php foreach($this->route_params::current_get_params(array($this->route_params::KEYS['area_id'])) as $key => $value): ?>
            <input type="hidden" name="<?php echo $key ?>" value="<?php echo $value ?>">
        <?php endforeach ?>

        <label for="shtm_current_area">Gebiet: </label>
        <select id="shtm_current_area" name="shtm_area_id" onchange="this.form.submit()">
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
    </div>

    <noscript>
        <div class="button" style="float:right">
            <button type="submit">Ok</button>
        </div>
    </noscript>
</form>