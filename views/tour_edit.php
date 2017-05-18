
<?php $this->include($this->view_helper::single_tour_header_template()) ?>

<form action=admin.php?<?php echo $this->route_params::update_tour($this->tour->id) ?> method="post"
    class="shtm_form">

    <div class="shtm_form_line">
        Gebiet: <?php echo $this->area->name ?>
    </div>

    <br>

    <div class="shtm_form_line">
        <label for="shtm_tour_name">Name:</label>
        <input id="shtm_tour_name" type="text" name="shtm_tour[name]" value="<?php echo $this->tour->name ?>">
    </div>

    <div class="shtm_form_line">
        <label for="shtm_tour_intro">Einführung:</label>
        <textarea id="shtm_tour_intro" type="text" name="shtm_tour[intro]" cols="45" rows="7"><?php echo $this->tour->intro ?></textarea>
    </div>

    <div class="shtm_form_line">
        <label for="shtm_tour_type">Typ:</label>
        <select id="shtm_tour_type" name="shtm_tour[type]">
            <?php foreach ($this->tour::TYPES as $type_key => $type_name): ?>
                <option value="<?php echo $type_key ?>"
                    <?php if($this->tour->type === $type_key): ?>
                        selected>
                    <?php else: ?>
                        >
                    <?php endif ?>

                    <?php echo $type_name ?>
                </option>
            <?php endforeach ?>
        </select>
    </div>

    <div class="shtm_form_line">
        <label for="shtm_tour_walk_length">Entfernung (m):</label>
        <input id="shtm_tour_walk_length" type="text" name="shtm_tour[walk_length]" value="<?php echo $this->tour->walk_length ?>">
    </div>

    <div class="shtm_form_line">
        <label for="shtm_tour_duration">Dauer (min):</label>
        <input id="shtm_tour_duration" type="text" name="shtm_tour[duration]" value="<?php echo $this->tour->duration ?>">
    </div>

    <div class="shtm_form_line">
        <label for="shtm_tour_tag_what">Was:</label>
        <input id="shtm_tour_tag_what" type="text" name="shtm_tour[tag_what]" value="<?php echo $this->tour->tag_what ?>">
    </div>

    <div class="shtm_form_line">
        <label for="shtm_tour_tag_where">Wo:</label>
        <input id="shtm_tour_tag_where" type="text" name="shtm_tour[tag_where]" value="<?php echo $this->tour->tag_where ?>">
    </div>

    <div class="shtm_form_line">
        <label for="shtm_tour_tag_when_start">Wann:
            <?php $this->include($this->view_helper::tooltip_template(), array('content' => "
                    Mögliche Datumsformate:
                    <ul>
                        <li>01.01.1994 13:07:01</li>
                        <li>01.01.1994 13:07</li>
                        <li>01.01.1994</li>
                        <li>01.1994</li>
                        <li>1994</li>
                    </ul>
            ")) ?>
        </label>
        <input id="shtm_tour_tag_when_start" type="text" name="shtm_tour[tag_when_start]" value="<?php echo $this->datetime_format($this->tour->get_tag_when_start(), $this->tour->tag_when_start_format) ?>">-
        <input id="shtm_tour_name" type="text" name="shtm_tour[tag_when_end]" value="<?php echo $this->datetime_format($this->tour->get_tag_when_end(), $this->tour->tag_when_end_format) ?>">
    </div>

    <div class="shtm_form_line">
        <label for="shtm_tour_accessibility">Zugänglichkeit</label>
        <input id="shtm_tour_accessibility" type="text" name="shtm_tour[accessibility]" value="<?php echo $this->tour->accessibility ?>">
    </div>

    <div class="shtm_button">
        <button type="submit">Speichern</button>
    </div>

</form>