
<div class="shtm_heading_line">

    <h2>Scene für Tour #<?php echo $this->tour->id ?></h2>

    <a href="admin.php?<?php echo $this->route_params::edit_tour_track($this->tour->id) ?>">
        zur Tour
    </a>

</div>


<form action="admin.php?<?php echo $this->route_params::add_scene($this->tour->id) ?>" method="post"
      class="shtm_form">

    <div class="shtm_form_line">
        <label for="shtm_scene">Scene:</label>
        <select id="shtm_scene" name="shtm_scene[id]">
            <?php foreach ($this->scenes as $scene): ?>
                <option value="<?php echo $scene->id ?>">
                    <?php echo $scene->title ?>
                </option>
            <?php endforeach ?>
        </select>
    </div>

    <div class="shtm_button">
        <button type="submit">Speichern</button>
    </div>

</form>