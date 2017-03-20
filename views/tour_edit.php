
<?php $this->include($this->view_helper::single_tour_header_template()) ?>

<form action=admin.php?<?php echo $this->route_params::update_tour($tour->id) ?> method="post">

    <div>
        Gebiet: <?php echo $this->area->name ?>
    </div>

    <div>
        <label for="shtm_name">Name:</label>
        <input type="text" id="shtm_name" name="shtm_tour[name]" value="<?php echo $this->tour->name ?>">
    </div>

    <div>
        <label for="shtm_name">Einführung:</label>
        <input type="text" id="shtm_name" name="shtm_tour[intro]" value="<?php echo $this->tour->intro ?>">
    </div>

    <div>
        <label for="shtm_name">Typ:</label>
        <input type="text" id="shtm_name" name="shtm_tour[type]" value="<?php echo $this->tour->type ?>">
    </div>

    <div>
        <label for="shtm_name">Entfernung (m):</label>
        <input type="text" id="shtm_name" name="shtm_tour[walk_length]" value="<?php echo $this->tour->walk_length ?>">
    </div>

    <div>
        <label for="shtm_name">Dauer (min):</label>
        <input type="text" id="shtm_name" name="shtm_tour[duration]" value="<?php echo $this->tour->duration ?>">
    </div>

    <div>
        <label for="shtm_name">Was:</label>
        <input type="text" id="shtm_name" name="shtm_tour[tag_what]" value="<?php echo $this->tour->tag_what ?>">
    </div>

    <div>
        <label for="shtm_name">Wo:</label>
        <input type="text" id="shtm_name" name="shtm_tour[tag_where]" value="<?php echo $this->tour->tag_where ?>">
    </div>

    <div>
        <label for="shtm_name">Wann:</label>
        <input type="text" id="shtm_name" name="shtm_tour[tag_when_start]" value="<?php echo $this->datetime_format($this->tour->get_tag_when_start()) ?>">-
        <input type="text" id="shtm_name" name="shtm_tour[tag_when_end]" value="<?php echo $this->datetime_format($this->tour->get_tag_when_end()) ?>">
    </div>

    <div>
        <label for="shtm_name">Zugänglichkeit</label>
        <input type="text" id="shtm_name" name="shtm_tour[accessibility]" value="<?php echo $this->tour->accessibility ?>">
    </div>

    <div class="button">
        <button type="submit">Speichern</button>
    </div>

</form>