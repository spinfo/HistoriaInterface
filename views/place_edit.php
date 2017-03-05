
<h1><?php echo $this->heading ?></h1>

<form action=admin.php?<?php echo $this->action_params ?> method="post">
    <div>
        <label for="shtm_name">Name:</label>
        <input type="text" id="shtm_name" name="shtm_place[name]" value="<?php echo $this->place->name ?>">
    </div>
    <div>
        <label for="shtm_lat">Lat:</label>
        <input type="text" id="shtm_lat" name="shtm_place[lat]" value="<?php printf("%.6f", $this->place->lat) ?>">
    </div>
    <div>
        <label for="shtm_lon">Lon:</label>
        <input type="text" id="shtm_lon" name="shtm_place[lon]" value="<?php printf("%.6f", $this->place->lon) ?>">
    </div>

    <div class="button">
        <button type="submit">Speichern</button>
    </div>

</form>
