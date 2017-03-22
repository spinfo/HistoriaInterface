
<div id="shtm_map" class="shtm_map_left" style="height: 500px; width: 500px;">
    <div id="shtm_map_area">
         <?php $this->include($this->view_helper::coordinate_template(), array('coordinate' => $this->area->coordinate1)) ?>
         <?php $this->include($this->view_helper::coordinate_template(), array('coordinate' => $this->area->coordinate2)) ?>
    </div>
    <div id="shtm_map_track">
        <?php foreach ($this->tour->coordinates as $c): ?>
             <?php $this->include($this->view_helper::coordinate_template(), array('coordinate' => $c)) ?>
        <?php endforeach ?>
    </div>
    <div id="shtm_map_mapstops">
        <?php foreach ($this->tour->mapstops as $mapstop): ?>
            <div data-mapstop-id="<?php echo $mapstop->id ?>"
                data-mapstop-name="<?php echo $mapstop->name ?>"
                data-mapstop-description="<?php echo $mapstop->description ?>">
                <?php $this->include($this->view_helper::coordinate_template(), array('coordinate' => $mapstop->place->coordinate)) ?>
            </div>
        <?php endforeach ?>
    </div>
</div>