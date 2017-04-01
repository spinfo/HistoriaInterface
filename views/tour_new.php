
<h2>Tour erstellen</h2>

<form action="admin.php?<?php echo $this->action_params ?>" method="post"
    class="shtm_form">

    <div class="shtm_info_text">
        Geben Sie hier einen Namen und ein Gebiet für die Tour ein.<br>
        Der Name kann später noch geändert werden.
    </div>

    <div class="shtm_form_line">
        <label for="shtm_tour_name">Name:</label>
        <input type="text" id="shtm_tour_name" name="shtm_tour[name]" value="">
    </div>

    <div class="shtm_form_line">
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

    <div class="shtm_button">
        <button type="submit">Speichern</button>
    </div>
</form>
