
<h1>Ort löschen</h1>

<div>Möchten Sie den folgenden Ort wirklich löschen?</div>

<ul>
    <li>ID: <?php echo $this->place->id ?></li>
    <li>Name: <?php echo $this->place->name ?></li>
    <li>Latitude: <?php printf("%.6f", $this->place->lat) ?></li>
    <li>Longitude: <?php printf("%.6f", $this->place->lon) ?></li>
</ul>

<form action=admin.php?<?php echo $this->action_params ?> method="post">

    <div class="button" id="shtm_delete_place">
        <button type="submit">Löschen</button>
    </div>

</form>