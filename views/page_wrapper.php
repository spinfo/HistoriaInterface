
<style>
    <!--
        .shtm_message {
            margin: 3px;
            padding: 3px;
            width: 80%;
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

        #shtm_container {
            margin: 2%;
            padding: 2%;
        }

        #shtm_page_wrapper_heading > h1 {
            display: inline;
        }

        #shtm_page_wrapper_heading .shtm_not_a_link {
            font-weight: bold;
            color: #AFAFAF;
        }
    -->
</style>


<div id="shtm_container">

    <div id="shtm_page_wrapper_heading">
        <h1>SmartHistory</h1>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;

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

