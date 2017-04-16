

<?php $this->include($this->view_helper::choose_current_area_template()) ?>

<div>
    <h2>Veröffentlichte Touren</h2>

    <?php if(count($this->publishable_tours) > 0): ?>
        <form action="admin.php?" method="get">
            <div class="shtm_form_line">
                <input type="hidden" name="page" value="<?php echo $_GET['page'] ?>">
                <input type="hidden" name="<?php echo $this->route_params::KEYS['controller'] ?>" value="tour_record">
                <input type="hidden" name="<?php echo $this->route_params::KEYS['action'] ?>" value="new">

                <label for="shtm_publish_tour">Tour veröffentlichen: </label>
                <br>
                <select id="shtm_publish_tour" name="<?php echo $this->route_params::KEYS['tour_id'] ?>">
                    <?php foreach($this->publishable_tours as $tour): ?>
                        <option value="<?php echo $tour->id ?>">
                            <?php echo $tour->name ?>
                        </option>
                    <?php endforeach ?>
                </select>

                <div class="shtm_button" style="display: inline-block;">
                    <button type="submit">Neu Veröffentlichen</button>
                </div>
            </div>
        </form>
    <?php else: ?>
        <div><i>Keine Touren im Gebiet zu veröffentlichen</i></div>
    <?php endif ?>
</div>

<?php if(count($this->records) > 0): ?>
    <table class="shtm_index_table">
        <thead>
            <tr>
                <th>id</th>
                <th>Name</th>
                <th>Aktiv</th>
                <th><!-- Neue Version --></th>
                <th><!-- Nicht mehr veröffentlichen --></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach($this->records as $record): ?>
            <tr>
                <td><?php echo $record->id ?></td>
                <td><?php echo $this->trim_text($record->name, 60) ?></td>
                <td><?php echo $record->is_active ?></td>
                <td>
                    <?php if($this->user_service->user_may_publish_tours()): ?>
                        <a href="?<?php echo $this->route_params::new_tour_record($record->tour_id) ?>">Neue Version</a>
                    <?php endif ?>
                </td>
                <td>
                    <?php if($this->user_service->user_may_publish_tours()): ?>
                        <a href="?<?php echo $this->route_params::deactivate_tour_record($record->id) ?>">Nicht mehr veröffentlichen</a>
                    <?php endif ?>
                </td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
<?php else: ?>
    <div><i>Keine veröffentlichten Touren im Gebiet</i></div>
<?php endif ?>
