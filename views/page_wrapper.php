
<style>
    <!--
        .shtm_message {
            margin: 10px;
            padding: 5px;
            width: 50%;
        }

        .shtm_message_success {
            background-color: rgba(92, 184, 92, 0.4);
        }

        .shtm_message_info {
            background-color: rgba(91, 192, 222, 0.4);
        }

        .shtm_message_warning {
            background-color: rgba(240, 173, 78, 0.4);
        }

        .shtm_message_error {
            background-color: rgba(217, 83, 79, 0.4);
        }

        .shtm_info_text {
            margin: 0 12px 12px 0;
            font-style: italic;
        }

        #shtm_container {
            margin: 2%;
            padding: 2%;
        }

        /* header styles */
        .shtm_heading_line {
            margin-bottom: 15px;
        }
        .shtm_heading_line > h1, .shtm_heading_line > h2 {
            display: inline;
            padding-right: 15px;
        }
        .shtm_not_a_link {
            font-weight: bold;
            color: #AFAFAF;
        }

        /* tables on the index pages */
        .shtm_index_table {
            margin-top: 12px;
        }
        .shtm_index_table tr > td {
            padding: 3px 7px;
        }
        .shtm_index_table > tbody > tr:nth-child(odd) {
            background-color: #dfdfdf;
        }

        /* input forms */
        form.shtm_form {
            /*width: 100%;*/
        }
        form.shtm_form.shtm_place_form {
            width: 50px;
        }
        form.shtm_form > div.shtm_form_line,
        form.shtm_form > div > div.shtm_form_line,
        form.shtm_form > div > div > div.shtm_form_line {
            margin: 3px;
            width: 100%;
        }
        form.shtm_form > div.shtm_form_line > label,
        form.shtm_form > div > div.shtm_form_line > label,
        form.shtm_form > div > div > div.shtm_form_line > label {
            margin-top: 5px;
            display: inline-block;
            width: 10%;
            height: 100%;
            line-height: 100%;
            vertical-align: top;
        }
        form.shtm_form > .shtm_button {
            margin-top: 12px;
        }

        /* maps: display to the left */
        .shtm_map_left {
            float: left;
        }
        .shtm_right_from_map {
            margin-left: 20px;
            float: left;
        }

        /* revert "list-style: none" from somewhere in wordpress */
        #shtm_container ul {
            list-style: disc outside none;
        }
    -->
</style>


<div id="shtm_container">

    <div id="shtm_page_wrapper_heading" class="shtm_heading_line">
        <h1>SmartHistory</h1>

        <?php if($this->route_params::is_current_page($this->route_params::index_places())): ?>
            <span class="shtm_not_a_link">Orte</span> |
        <?php else: ?>
            <a href="admin.php?<?php echo $this->route_params::index_places() ?>">Orte</a> |
        <?php endif ?>

        <?php if($this->route_params::is_current_page($this->route_params::index_tours())): ?>
            <span class="shtm_not_a_link">Touren</span>
        <?php else: ?>
            <a href="admin.php?<?php echo $this->route_params::index_tours() ?>">Touren</a>
        <?php endif ?>
    </div>

    <div id="shtm_messages">

        <?php foreach($this->message_service->messages as $msg): ?>

            <div class="shtm_message shtm_message_<?php echo $msg->get_label() ?>">

                <b><?php echo $msg->get_prefix() ?>: </b><?php echo $msg->text ?>

            </div>

        <?php endforeach ?>

    </div>

    <div id="shtm_content">

        <?php $this->content->render() ?>

    </div>
</div>

