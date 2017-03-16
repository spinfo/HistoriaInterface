
<h1>Tour erstellen</h1>

<form action="admin.php?<?php echo $this->action_params ?>">
    <div>
        <label for="shtm_tour_name">Name:</label>
        <input type="text" id="shtm_tour_name" name="shtm_tour[name]" value="">
    </div>

    <div>
        <label for="shtm_tour_area">Gebiet: </label>
        <select id="shtm_tour_area" name="shtm_tour[area_id]">
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
</form>
