
<h2>Ort löschen</h2>

<div>Möchten Sie den folgenden Ort wirklich löschen?</div>

<ul>
    <li>ID: <?php echo $this->place->id ?></li>
    <li>Name: <?php echo $this->place->name ?></li>
    <li>Latitude: <?php printf("%.6f", $this->place->coordinate->lat) ?></li>
    <li>Longitude: <?php printf("%.6f", $this->place->coordinate->lon) ?></li>
</ul>

<form action=admin.php?<?php echo $this->action_params ?> method="post">

    <div class="button" id="shtm_delete_place">
        <button type="submit">Löschen</button>
    </div>

</form>